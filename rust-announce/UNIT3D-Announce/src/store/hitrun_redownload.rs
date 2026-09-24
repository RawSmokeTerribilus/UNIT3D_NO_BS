//! NOBS: pares (socio, torrent) que un socio con la descarga cortada por hit and
//! run puede volver a bajar, y NINGUN otro torrent. Sin esto, quien borro los
//! ficheros no podia recuperarlos para sembrar: el announce le cortaba en
//! seco y los avisos (que solo se cierran sembrando) no caducaban nunca.
//!
//! La base es la unica fuente de verdad. No hay endpoint de API: el scheduler
//! relee el conjunto entero cada cierto tiempo y lo sustituye de golpe, asi que
//! no hay deriva posible por borrados masivos, reinicios o una llamada perdida.
//! Si la consulta falla, el conjunto se vacia: falla hacia "cerrado".

use std::ops::Deref;

use futures_util::TryStreamExt;
use indexmap::IndexSet;
use sqlx::MySqlPool;

use anyhow::{Context, Result};

/// Solo entra un par si se cumplen TODAS estas condiciones:
///
/// - aviso del sistema (`warned_by = 1`), activo y no borrado: el staff no puede
///   crear avisos con torrent, y AutoWarning solo avisa sobre algo que el socio
///   ya bajo en mas de un 50 %. Nunca da acceso a contenido nuevo.
/// - el socio existe, esta bloqueado (`can_download = 0`) y el bloqueo lo
///   explica el H&R: avisos activos >= `hitrun.max_warnings`. Si ese ajuste no
///   esta en `settings`, la comparacion da NULL y no entra nadie.
/// - el staff no le ha cortado la descarga a mano (`staff_download_blocks`).
/// - hay historial vivo con bajada real y el torrent no esta borrado.
/// - su grupo tiene descarga de verdad: ni slots a 0 (Sanguijuela,
///   Aniquilados, Guest...) ni castigado/sin validar/desactivado. Los slots a 0
///   NO rechazan el announce (solo esconden al peer), por eso se filtran aqui y
///   el announce lo vuelve a mirar en vivo.
const QUERY: &str = r#"
    SELECT
        w.user_id,
        w.torrent_id
    FROM warnings w
    JOIN users u
        ON u.id = w.user_id
        AND u.deleted_at IS NULL
        AND u.can_download = 0
    JOIN `groups` g
        ON g.id = u.group_id
        AND (g.download_slots IS NULL OR g.download_slots > 0)
        AND g.slug NOT IN ('banned', 'validating', 'disabled')
    JOIN history h
        ON h.user_id = w.user_id
        AND h.torrent_id = w.torrent_id
        AND h.deleted_at IS NULL
        AND h.actual_downloaded > 0
    JOIN torrents t
        ON t.id = w.torrent_id
        AND t.deleted_at IS NULL
    WHERE w.active = 1
        AND w.deleted_at IS NULL
        AND w.warned_by = 1
        AND w.torrent_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM staff_download_blocks b WHERE b.user_id = w.user_id
        )
        AND (
            SELECT COUNT(*) FROM warnings w2
            WHERE w2.user_id = w.user_id
                AND w2.active = 1
                AND w2.deleted_at IS NULL
        ) >= (
            SELECT CAST(s.value AS UNSIGNED) FROM settings s
            WHERE s.`key` = 'hitrun.max_warnings'
        )
"#;

#[derive(Default)]
pub struct HitRunRedownloadStore {
    inner: IndexSet<HitRunRedownload>,
}

impl HitRunRedownloadStore {
    pub async fn from_db(db: &MySqlPool) -> Result<HitRunRedownloadStore> {
        sqlx::query_as::<_, HitRunRedownload>(QUERY)
            .fetch(db)
            .try_fold(HitRunRedownloadStore::default(), |mut store, pair| async move {
                store.inner.insert(pair);

                Ok(store)
            })
            .await
            .context("Failed loading hit and run re-download pairs.")
    }
}

impl Deref for HitRunRedownloadStore {
    type Target = IndexSet<HitRunRedownload>;

    fn deref(&self) -> &Self::Target {
        &self.inner
    }
}

#[derive(Eq, Hash, PartialEq, sqlx::FromRow)]
pub struct HitRunRedownload {
    pub user_id: u32,
    pub torrent_id: u32,
}
