use std::str::FromStr;
use std::sync::Arc;

use axum::extract::{Json, Path, State};
use axum::http::StatusCode;
use serde::Deserialize;
use tracing::info;

use anyhow::Result;

use crate::model::{info_hash::InfoHash, torrent_status::TorrentStatus};
use crate::state::AppState;
use crate::store::torrent::Torrent;

#[derive(Clone, Deserialize)]
pub struct APIInsertTorrent {
    pub id: u32,
    pub status: TorrentStatus,
    pub info_hash: String,
    pub is_deleted: bool,
    pub seeders: u32,
    pub leechers: u32,
    pub times_completed: u32,
    pub download_factor: u8,
    pub upload_factor: u8,
}

pub async fn upsert(
    State(state): State<Arc<AppState>>,
    Json(torrent): Json<APIInsertTorrent>,
) -> StatusCode {
    if let Ok(info_hash) = InfoHash::from_str(&torrent.info_hash) {
        info!("Inserting torrent with id {}.", torrent.id);
        let mut torrent_store = state.stores.torrents.lock();

        // Si ya está en memoria, mandan sus contadores (salen de sus pares, que
        // son la realidad); los de la base sólo valen para un torrent nuevo.
        // Así «Sincronizar torrents» actualiza estado y factores sin pisar
        // contadores con una copia que puede ir unos segundos por detrás.
        let (seeders, leechers, times_completed, peers) = match torrent_store.swap_remove(&torrent.id) {
            Some(old) => (old.seeders, old.leechers, old.times_completed, old.peers),
            None => (torrent.seeders, torrent.leechers, torrent.times_completed, Default::default()),
        };

        torrent_store.insert(
            torrent.id,
            Torrent {
                id: torrent.id,
                status: torrent.status,
                is_deleted: torrent.is_deleted,
                seeders,
                leechers,
                times_completed,
                download_factor: torrent.download_factor,
                upload_factor: torrent.upload_factor,
                peers,
            },
        );

        drop(torrent_store);

        state
            .stores
            .infohash2id
            .write()
            .insert(info_hash, torrent.id);

        return StatusCode::OK;
    }

    StatusCode::BAD_REQUEST
}

#[derive(Clone, Deserialize)]
pub struct APIRemoveTorrent {
    pub id: u32,
}

pub async fn destroy(
    State(state): State<Arc<AppState>>,
    Json(torrent): Json<APIRemoveTorrent>,
) -> StatusCode {
    let mut torrent_guard = state.stores.torrents.lock();

    if let Some(torrent) = torrent_guard.get_mut(&torrent.id) {
        info!("Removing torrent with id {}.", torrent.id);
        torrent.is_deleted = true;

        return StatusCode::OK;
    }

    StatusCode::BAD_REQUEST
}

pub async fn show(
    State(state): State<Arc<AppState>>,
    Path(id): Path<u32>,
) -> Result<Json<Torrent>, StatusCode> {
    state
        .stores
        .torrents
        .lock()
        .get(&id)
        .map(|torrent| Json(torrent.clone()))
        .ok_or(StatusCode::NOT_FOUND)
}
