# Sam & Max Hit the Road (CD Talkie)

ScummVM game ID: `samnmax`  
UNIT3D catalog ID: `samnmax`

## Required files (3 files)

```
samnmax.000
samnmax.001
monster.sog
```

## Source

Files are inside the local collection at:
```
gaming/ScummVM Collection 1.2/Sam & Max Hit the Road/Sam & Max Hit the Road/samnmax/
```

## Installation

```bash
sudo find "gaming/ScummVM Collection 1.2/Sam & Max Hit the Road/Sam & Max Hit the Road/samnmax/" \
     -maxdepth 1 -type f \
     -exec cp {} public/games/samnmax/ \;
sudo chown -R 82:82 public/games/samnmax/
```
