# ScummVM WebAssembly Engine

This directory contains the ScummVM WebAssembly runtime. The binary files are
gitignored (~50 MB total) and must be installed manually.

## Required files

| File | Size (approx) | Description |
|------|--------------|-------------|
| `scummvm.js` | ~9 MB | Emscripten JS loader |
| `scummvm.wasm` | ~37 MB | WebAssembly binary (Asyncify build, no pthreads) |
| `data/plugins/libscumm.so` | ~3 MB | SCUMM engine plugin |

## Source

These files come from a custom ScummVM WASM build configured with:
- **Asyncify** (required for coroutine-style blocking)
- **No SharedArrayBuffer / pthreads** (cross-origin isolation not required)
- Only the `SCUMM` engine plugin compiled in (`libscumm.so`)

The built files currently running on this server are located at:
`/home/rawserver/UNIT3D_Develop/public/engine/` (host path, volume-mounted into Docker).

## Installation

```bash
# Copy your build output here (adapt source path as needed):
sudo cp /path/to/build/scummvm.js     public/engine/
sudo cp /path/to/build/scummvm.wasm   public/engine/
sudo mkdir -p public/engine/data/plugins/
sudo cp /path/to/build/libscumm.so    public/engine/data/plugins/

# Restore Docker ownership:
sudo chown -R 82:82 public/engine/
```

## Notes

- Only SCUMM-engine games are playable with this build (Monkey Island,
  Maniac Mansion, Loom, Zak McKracken, Indiana Jones, Sam & Max…).
- Full Throttle and The Dig use a different engine (HE) and require
  additional plugins — deferred for a future build.
