use std::sync::Arc;

use axum::{
    Json,
    extract::State,
    http::StatusCode,
    response::{IntoResponse, Response},
};
use serde::Deserialize;
use tracing::info;

use crate::{scheduler, state::AppState};

#[derive(Deserialize)]
pub struct Reap {
    pub active_peer_ttl: u64,
}

/// Marca como inactivos, ya, los pares que llevan más de `active_peer_ttl`
/// segundos sin anunciar. Mismo trabajo que el barrido periódico, a demanda.
pub async fn reap(State(state): State<Arc<AppState>>, Json(reap): Json<Reap>) -> Response {
    let announce_max = state.config.load().announce_max as u64;

    // Por debajo de ANNOUNCE_MAX se llevaría por delante pares vivos que
    // todavía no han tenido turno de anunciar. Misma regla que ACTIVE_PEER_TTL.
    if reap.active_peer_ttl <= announce_max {
        return (
            StatusCode::UNPROCESSABLE_ENTITY,
            format!("active_peer_ttl must be greater than ANNOUNCE_MAX ({announce_max})"),
        )
            .into_response();
    }

    info!(
        "Reaping peers on demand with active_peer_ttl {}.",
        reap.active_peer_ttl
    );

    scheduler::reap_older_than(&state, reap.active_peer_ttl);

    (StatusCode::OK, "Reaped stale peers.").into_response()
}

#[derive(Deserialize)]
pub struct ReapUser {
    pub torrent_id: u32,
    pub user_id: u32,
    pub inactive_for: u64,
}

/// «Limpiar mis pares»: los pares de un socio en un torrent que llevan más de
/// `inactive_for` segundos sin anunciar. Responde con cuántos ha marcado.
pub async fn reap_user(
    State(state): State<Arc<AppState>>,
    Json(reap): Json<ReapUser>,
) -> Response {
    let announce_max = state.config.load().announce_max as u64;

    // Un par vivo anuncia como mucho cada ANNOUNCE_MAX: por debajo podría
    // marcarse uno vivo, y su siguiente anuncio entraría como par nuevo con la
    // subida y la bajada a cero.
    if reap.inactive_for <= announce_max {
        return (
            StatusCode::UNPROCESSABLE_ENTITY,
            format!("inactive_for must be greater than ANNOUNCE_MAX ({announce_max})"),
        )
            .into_response();
    }

    let reaped =
        scheduler::reap_user_peers(&state, reap.torrent_id, reap.user_id, reap.inactive_for);

    info!(
        "Reaped {reaped} peers of user {} on torrent {} on demand.",
        reap.user_id, reap.torrent_id
    );

    (StatusCode::OK, reaped.to_string()).into_response()
}
