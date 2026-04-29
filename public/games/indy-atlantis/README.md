# Indiana Jones and the Fate of Atlantis (CD Talkie)

ScummVM game ID: `atlantis`  
UNIT3D catalog ID: `indy-atlantis`

## Required files (3 files)

```
atlantis.000
atlantis.001
monster.sog
```

## Source

Files are inside the local collection at:
```
gaming/ScummVM Collection 1.2/Indiana Jones and the Fate of Atlantis/Indiana Jones and the Fate of Atlantis/atlantis/
```

## Installation

```bash
sudo find "gaming/ScummVM Collection 1.2/Indiana Jones and the Fate of Atlantis/Indiana Jones and the Fate of Atlantis/atlantis/" \
     -maxdepth 1 -type f \
     -exec cp {} public/games/indy-atlantis/ \;
sudo chown -R 82:82 public/games/indy-atlantis/
```
