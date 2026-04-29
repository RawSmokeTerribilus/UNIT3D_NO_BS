# The Secret of Monkey Island (VGA CD)

ScummVM game ID: `monkey`  
UNIT3D catalog ID: `mi1-vga`

## Required files (9 files)

```
000.LFL
901.LFL
902.LFL
903.LFL
904.LFL
DISK01.LEC
DISK02.LEC
DISK03.LEC
DISK04.LEC
```

## Source

Files are inside the local collection at:
```
gaming/ScummVM Collection 1.2/The Secret of Monkey Island/The Secret of Monkey Island/monkey/
```

## Installation

```bash
sudo find "gaming/ScummVM Collection 1.2/The Secret of Monkey Island/The Secret of Monkey Island/monkey/" \
     -maxdepth 1 -type f \
     -exec cp {} public/games/mi1-vga/ \;
sudo chown -R 82:82 public/games/mi1-vga/
```
