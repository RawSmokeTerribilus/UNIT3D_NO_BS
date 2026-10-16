use std::sync::Arc;

use crate::config::Config;
use crate::queue::torrent_update::{Index, TorrentUpdate};
use crate::state::AppState;
use crate::store::peer;
use crate::store::torrent::Torrent;
use crate::store::hitrun_redownload::HitRunRedownloadStore;
use chrono::{Duration, Utc};
use indexmap::IndexMap;
use tokio::time::Instant;
use tracing::{error, info};

pub async fn handle(state: &Arc<AppState>) {
    let mut interval = tokio::time::interval(std::time::Duration::from_millis(1));
    let mut counter = 0_u64;

    loop {
        interval.tick().await;
        counter += 1;

        if counter % state.config.load().flush_interval_milliseconds == 0 {
            state.queues.flush(state).await;
        }

        if counter % (state.config.load().peer_expiry_interval * 1000) == 0 {
            reap(state).await;
            recount(state);
        }

        if counter % (HITRUN_REDOWNLOAD_RELOAD_SECONDS * 1000) == 0 {
            reload_hitrun_redownloads(state).await;
        }
    }
}

/// NOBS: cada cuanto se relee de la base el conjunto de re-descargas por hit
/// and run. Es tambien el retraso maximo con el que un aviso nuevo, cerrado o
/// borrado se refleja en el announce.
const HITRUN_REDOWNLOAD_RELOAD_SECONDS: u64 = 300;

/// Sustituye el conjunto entero por lo que diga la base. La consulta va fuera
/// del lock; el cambio, de golpe. Si la consulta falla el conjunto se VACIA:
/// mejor dejar a alguien bloqueado cinco minutos que abrir con datos viejos.
pub async fn reload_hitrun_redownloads(state: &Arc<AppState>) {
    let result = HitRunRedownloadStore::from_db(&state.pool).await;

    let mut store = state.stores.hitrun_redownloads.write();
    let previous = store.len();

    match result {
        Ok(fresh) => {
            *store = fresh;

            if store.len() != previous {
                info!(
                    "Hit and run re-download pairs reloaded: {previous} -> {}.",
                    store.len()
                );
            }
        }
        Err(e) => {
            *store = HitRunRedownloadStore::default();

            error!("Hit and run re-download pairs cleared, reload failed: {e:#}");
        }
    }
}

/// Remove peers that have not announced for some time
pub async fn reap(state: &Arc<AppState>) {
    let active_peer_ttl = state.config.load().active_peer_ttl;

    reap_older_than(state, active_peer_ttl);
}

/// Igual que `reap`, pero con un TTL de pares activos elegido por quien llama.
/// Lo usa el botón «Limpiar pares viejos» del panel (`auto:flush_peers`) para
/// que la memoria del announce suelte los mismos pares que la base.
pub fn reap_older_than(state: &Arc<AppState>, active_peer_ttl: u64) {
    use rayon::prelude::*;

    let start = Instant::now();
    let config = state.config.load();
    let ttl = Duration::seconds(active_peer_ttl.try_into().unwrap());
    let active_cutoff = Utc::now().checked_sub_signed(ttl).unwrap();
    let ttl = Duration::seconds(config.inactive_peer_ttl.try_into().unwrap());
    let inactive_cutoff = Utc::now().checked_sub_signed(ttl).unwrap();

    let mut torrent_store = state.stores.torrents.lock();

    torrent_store.par_values_mut().for_each(|torrent| {
        let mut seeder_delta: i32 = 0;
        let mut leecher_delta: i32 = 0;

        // If a peer is marked as inactive and it has not announced for
        // more than inactive_peer_ttl, then it is permanently deleted.
        torrent
            .peers
            .retain(|_, peer| inactive_cutoff <= peer.updated_at || peer.is_active);

        for (index, peer) in torrent.peers.iter_mut() {
            // Peers get marked as inactive if not announced for more than
            // active_peer_ttl seconds. User peer count and torrent peer
            // count are updated to reflect.
            if peer.updated_at < active_cutoff && peer.is_active {
                deactivate_peer(state, &config, index, peer, &mut seeder_delta, &mut leecher_delta);
            }
        }

        // Update peer count of torrents and users
        apply_torrent_deltas(state, torrent, seeder_delta, leecher_delta);
    });

    let elapsed = start.elapsed().as_millis();
    info!("Expired stale peers in {elapsed} ms.")
}

/// Botón «Limpiar mis pares» de la ficha del torrent: marca inactivos los
/// pares de un socio en un torrent que llevan más de `inactive_for` segundos
/// sin anunciar. Devuelve cuántos ha marcado.
pub fn reap_user_peers(
    state: &Arc<AppState>,
    torrent_id: u32,
    user_id: u32,
    inactive_for: u64,
) -> u32 {
    let config = state.config.load();
    let ttl = Duration::seconds(inactive_for.try_into().unwrap());
    let cutoff = Utc::now().checked_sub_signed(ttl).unwrap();

    let mut torrent_store = state.stores.torrents.lock();

    let Some(torrent) = torrent_store.get_mut(&torrent_id) else {
        return 0;
    };

    let mut seeder_delta: i32 = 0;
    let mut leecher_delta: i32 = 0;
    let mut reaped = 0;

    for (index, peer) in torrent.peers.iter_mut() {
        if index.user_id == user_id && peer.updated_at < cutoff && peer.is_active {
            deactivate_peer(state, &config, index, peer, &mut seeder_delta, &mut leecher_delta);
            reaped += 1;
        }
    }

    apply_torrent_deltas(state, torrent, seeder_delta, leecher_delta);

    reaped
}

/// Recuenta los contadores DERIVADOS a partir de la lista de pares en memoria,
/// que es la realidad que conoce el announce (lo que han anunciado los
/// clientes): seeders/leechers de cada torrent y pares de cada socio.
///
/// Los contadores se llevan sumando y restando en cada anuncio; si alguna vez
/// se descuadran (un sync desde la base, una carga tras un corte de luz) no
/// vuelven solos. Esto los iguala a la lista de pares en cada barrido y al
/// arrancar, sin depender de reinicios ni de nadie. Las correcciones de los
/// torrents van a la base como deltas en el volcado normal: la base sigue
/// siendo la copia de seguridad, alineada con la memoria.
///
/// Los acumulados (tráfico, seedtime, balance) NO se tocan: sólo existen como
/// suma de anuncios.
///
/// Los torrents se corrigen exactos (sus contadores cambian dentro del mismo
/// bloqueo que sus pares). Los socios pueden quedar ±1 si un anuncio cae entre
/// su parte de torrent y su parte de socio; el siguiente recuento lo iguala.
pub fn recount(state: &Arc<AppState>) {
    use rayon::prelude::*;

    let start = Instant::now();
    let config = state.config.load();
    let mut torrent_store = state.stores.torrents.lock();

    let torrents_corrected: usize = torrent_store
        .par_values_mut()
        .map(|torrent| {
            let (seeders, leechers) = torrent.peers.values().fold((0_u32, 0_u32), |(s, l), peer| {
                (
                    s + peer.is_included_in_seed_list(&config) as u32,
                    l + peer.is_included_in_leech_list(&config) as u32,
                )
            });

            let seeder_delta = (i64::from(seeders) - i64::from(torrent.seeders)) as i32;
            let leecher_delta = (i64::from(leechers) - i64::from(torrent.leechers)) as i32;

            if seeder_delta != 0 || leecher_delta != 0 {
                apply_torrent_deltas(state, torrent, seeder_delta, leecher_delta);

                1
            } else {
                0
            }
        })
        .sum();

    let mut user_counts: IndexMap<u32, (u32, u32)> = IndexMap::new();

    for torrent in torrent_store.values() {
        for (index, peer) in torrent.peers.iter() {
            if peer.is_included_in_peer_list(&config) {
                let counts = user_counts.entry(index.user_id).or_insert((0, 0));

                if peer.is_seeder {
                    counts.0 += 1;
                } else {
                    counts.1 += 1;
                }
            }
        }
    }

    let mut users_corrected = 0;

    for (user_id, user) in state.stores.users.write().iter_mut() {
        let (seeding, leeching) = user_counts.get(user_id).copied().unwrap_or((0, 0));

        if user.num_seeding != seeding || user.num_leeching != leeching {
            user.num_seeding = seeding;
            user.num_leeching = leeching;
            users_corrected += 1;
        }
    }

    let elapsed = start.elapsed().as_millis();
    info!(
        "Recounted counters from peers in {elapsed} ms: {torrents_corrected} torrents and {users_corrected} users corrected."
    )
}

/// Marca un par como inactivo y descuenta al socio y al torrent lo que
/// aportaba a las listas.
fn deactivate_peer(
    state: &Arc<AppState>,
    config: &Config,
    index: &peer::Index,
    peer: &mut peer::Peer,
    seeder_delta: &mut i32,
    leecher_delta: &mut i32,
) {
    if peer.is_included_in_peer_list(config) {
        state
            .stores
            .users
            .write()
            .entry(index.user_id)
            .and_modify(|user| {
                if peer.is_seeder {
                    user.num_seeding = user.num_seeding.saturating_sub(1);
                } else {
                    user.num_leeching = user.num_leeching.saturating_sub(1);
                }
            });
        match peer.is_seeder {
            true => *seeder_delta -= 1,
            false => *leecher_delta -= 1,
        }
    }

    peer.is_active = false;
}

fn apply_torrent_deltas(
    state: &Arc<AppState>,
    torrent: &mut Torrent,
    seeder_delta: i32,
    leecher_delta: i32,
) {
    if seeder_delta != 0 || leecher_delta != 0 {
        torrent.seeders = torrent.seeders.saturating_add_signed(seeder_delta);
        torrent.leechers = torrent.leechers.saturating_add_signed(leecher_delta);

        state.queues.torrents.lock().upsert(
            Index {
                torrent_id: torrent.id,
            },
            TorrentUpdate {
                seeder_delta,
                leecher_delta,
                times_completed_delta: 0,
                balance_delta: 0,
            },
        );
    }
}
