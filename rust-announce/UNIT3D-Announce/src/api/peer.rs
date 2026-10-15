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
