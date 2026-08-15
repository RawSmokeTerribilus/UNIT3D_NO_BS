FROM rust:1.95-bookworm AS builder

WORKDIR /build

COPY rust-announce/UNIT3D-Announce/Cargo.toml rust-announce/UNIT3D-Announce/Cargo.lock ./
COPY rust-announce/UNIT3D-Announce/.sqlx ./.sqlx
COPY rust-announce/UNIT3D-Announce/src ./src

RUN cargo build --release

FROM debian:bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl python3 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=builder /build/target/release/unit3d-announce /usr/local/bin/unit3d-announce
COPY .docker/announce/entrypoint.sh /usr/local/bin/announce-entrypoint.sh

RUN chmod +x /usr/local/bin/announce-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/announce-entrypoint.sh"]
