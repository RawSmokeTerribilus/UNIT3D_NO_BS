# Monkey Island 2: LeChuck's Revenge (CD Talkie)

ScummVM game ID: `monkey2`  
UNIT3D catalog ID: `mi2-talkie`

## Required files (3 files)

```
MONKEY2.000
MONKEY2.001
monkey2.sog
```

## Source

Files are in the local backup at:
```
gaming/BackUps/mi2-talkie/
```

## Installation

```bash
sudo find gaming/BackUps/mi2-talkie/ \
     -maxdepth 1 -type f \
     -exec cp {} public/games/mi2-talkie/ \;
sudo chown -R 82:82 public/games/mi2-talkie/
```
