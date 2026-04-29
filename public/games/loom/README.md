# Loom (CD Talkie)

ScummVM game ID: `loom`  
UNIT3D catalog ID: `loom`

## Required files (7 files)

```
000.lfl
901.lfl
902.lfl
903.lfl
904.lfl
disk01.lec
track1.ogg
```

## Source

Files are inside the local collection at:
```
gaming/ScummVM Collection 1.2/Loom/Loom/loom/
```

## Installation

```bash
sudo find "gaming/ScummVM Collection 1.2/Loom/Loom/loom/" \
     -maxdepth 1 -type f \
     -exec cp {} public/games/loom/ \;
sudo chown -R 82:82 public/games/loom/
```
