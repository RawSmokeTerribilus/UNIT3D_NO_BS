# UNIT3D Arcade — Gaming Setup Guide

This document explains how to set up the ScummVM WebAssembly arcade from
scratch. All binary assets (engine, game ROMs, cover art) are gitignored;
only the directory skeleton and this guide are versioned.

---

## Architecture overview

```
public/
├── engine/               ← ScummVM WASM runtime (gitignored, ~50 MB)
│   ├── scummvm.js
│   ├── scummvm.wasm
│   └── data/plugins/
│       └── libscumm.so
├── games/                ← Game ROM files (gitignored, copyright)
│   ├── mi1-vga/
│   ├── mi2-talkie/
│   ├── maniac-mansion/
│   ├── loom/
│   ├── zak-mckracken/
│   ├── indy-atlantis/
│   └── samnmax/
└── img/games/            ← Cover art (gitignored)
    ├── mi1-vga.jpg
    ├── mi2-talkie.jpg
    ├── maniac-mansion.png
    ├── loom.png
    ├── zak-mckracken.png
    ├── indy-atlantis.png
    └── samnmax.png
```

`public/` is a Docker volume mount owned by uid=82 (container www-data).
All writes to it require `sudo`.

---

## Step 1 — Install the ScummVM engine

Place three files from a custom ScummVM WASM build:

```bash
sudo mkdir -p public/engine/data/plugins/
sudo cp /path/to/build/scummvm.js    public/engine/
sudo cp /path/to/build/scummvm.wasm  public/engine/
sudo cp /path/to/build/libscumm.so   public/engine/data/plugins/
sudo chown -R 82:82 public/engine/
```

**Build requirements:**
- Asyncify enabled (required for coroutine-style blocking)
- No SharedArrayBuffer / pthreads (no cross-origin isolation header needed)
- Only the `SCUMM` engine plugin compiled in

---

## Step 2 — Install game files

The local collection lives in `gaming/ScummVM Collection 1.2/`.
Each game has a subdirectory with the same name as the collection entry, and
inside that a ScummVM game data directory.

> **Important:** Use `sudo find … -exec cp` instead of `sudo cp *` when paths
> contain spaces — glob expansion breaks with sudo.

### The Secret of Monkey Island (mi1-vga) — 9 files

```bash
sudo find "gaming/ScummVM Collection 1.2/The Secret of Monkey Island/The Secret of Monkey Island/monkey/" \
     -maxdepth 1 -type f -exec cp {} public/games/mi1-vga/ \;
```

Files: `000.LFL`, `901-904.LFL`, `DISK01-04.LEC`

---

### Monkey Island 2: LeChuck's Revenge (mi2-talkie) — 3 files

Source is in the backup directory (not in Collection 1.2):

```bash
sudo find gaming/BackUps/mi2-talkie/ \
     -maxdepth 1 -type f -exec cp {} public/games/mi2-talkie/ \;
```

Files: `MONKEY2.000`, `MONKEY2.001`, `monkey2.sog`

---

### Maniac Mansion (maniac-mansion) — 54 files

```bash
sudo find "gaming/ScummVM Collection 1.2/Maniac Mansion/Maniac Mansion/maniac/" \
     -maxdepth 1 -type f -exec cp {} public/games/maniac-mansion/ \;
```

Files: `00.lfl` through `53.lfl`

---

### Loom — 7 files

```bash
sudo find "gaming/ScummVM Collection 1.2/Loom/Loom/loom/" \
     -maxdepth 1 -type f -exec cp {} public/games/loom/ \;
```

Files: `000.lfl`, `901-904.lfl`, `disk01.lec`, `track1.ogg`

---

### Zak McKracken and the Alien Mindbenders (zak-mckracken) — 83 files

```bash
sudo find "gaming/ScummVM Collection 1.2/Zak McKracken and the Alien Mindbenders/Zak McKracken and the Alien Mindbenders/zak/" \
     -maxdepth 1 -type f -exec cp {} public/games/zak-mckracken/ \;
```

Files: `00-59.lfl`, `98.lfl`, `99.lfl`, `track1-21.ogg`

---

### Indiana Jones and the Fate of Atlantis (indy-atlantis) — 3 files

```bash
sudo find "gaming/ScummVM Collection 1.2/Indiana Jones and the Fate of Atlantis/Indiana Jones and the Fate of Atlantis/atlantis/" \
     -maxdepth 1 -type f -exec cp {} public/games/indy-atlantis/ \;
```

Files: `atlantis.000`, `atlantis.001`, `monster.sog`

---

### Sam & Max Hit the Road (samnmax) — 3 files

```bash
sudo find "gaming/ScummVM Collection 1.2/Sam & Max Hit the Road/Sam & Max Hit the Road/samnmax/" \
     -maxdepth 1 -type f -exec cp {} public/games/samnmax/ \;
```

Files: `samnmax.000`, `samnmax.001`, `monster.sog`

---

## Step 3 — Install cover art

Rename and copy cover images to match the catalog IDs:

| Filename expected | Game |
|-------------------|------|
| `mi1-vga.jpg` | The Secret of Monkey Island |
| `mi2-talkie.jpg` | Monkey Island 2: LeChuck's Revenge |
| `maniac-mansion.png` | Maniac Mansion |
| `loom.png` | Loom |
| `zak-mckracken.png` | Zak McKracken |
| `indy-atlantis.png` | Indiana Jones and the Fate of Atlantis |
| `samnmax.png` | Sam & Max Hit the Road |

Cover originals are at `gaming/ScummVM Collection 1.2/<Game>/<Game> - Cover.png`.

```bash
sudo chown -R 82:82 public/img/games/
```

---

## Step 4 — Restore Docker ownership

After installing anything into `public/`:

```bash
sudo chown -R 82:82 public/
```

---

## Verify installation

```bash
# Check each game dir has more than just the README:
for d in public/games/*/; do echo "$d: $(ls "$d" | wc -l) files"; done

# Check engine:
ls -lh public/engine/scummvm.js public/engine/scummvm.wasm public/engine/data/plugins/libscumm.so
```

---

## Deferred games

The following games are in the local collection but not yet installed — they
are SCUMM-engine compatible but deferred:

| Game | Directory | Notes |
|------|-----------|-------|
| Full Throttle | `gaming/ScummVM Collection 1.2/Full Throttle/` | Large files |
| The Dig | `gaming/ScummVM Collection 1.2/The Dig/` | Large files |

---

## Gitignore strategy

The `.gitignore` ignores binary data but tracks the directory skeleton:

```gitignore
public/engine/scummvm.js
public/engine/scummvm.wasm
public/engine/data/
public/games/*/*          # ignores files inside game dirs
!public/games/*/README.md # but tracks README.md in each
public/img/games/*
!public/img/games/README.md
```

This means the `public/games/mi1-vga/` directory itself (and its `README.md`)
are in git; only the ROM files inside are excluded.
