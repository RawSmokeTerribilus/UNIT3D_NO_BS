# Maniac Mansion

ScummVM game ID: `maniac`  
UNIT3D catalog ID: `maniac-mansion`

## Required files (54 files)

```
00.lfl  01.lfl  02.lfl  03.lfl  04.lfl  05.lfl  06.lfl  07.lfl  08.lfl  09.lfl
10.lfl  11.lfl  12.lfl  13.lfl  14.lfl  15.lfl  16.lfl  17.lfl  18.lfl  19.lfl
20.lfl  21.lfl  22.lfl  23.lfl  24.lfl  25.lfl  26.lfl  27.lfl  28.lfl  29.lfl
30.lfl  31.lfl  32.lfl  33.lfl  34.lfl  35.lfl  36.lfl  37.lfl  38.lfl  39.lfl
40.lfl  41.lfl  42.lfl  43.lfl  44.lfl  45.lfl  46.lfl  47.lfl  48.lfl  49.lfl
50.lfl  51.lfl  52.lfl  53.lfl
```

## Source

Files are inside the local collection at:
```
gaming/ScummVM Collection 1.2/Maniac Mansion/Maniac Mansion/maniac/
```

## Installation

```bash
sudo find "gaming/ScummVM Collection 1.2/Maniac Mansion/Maniac Mansion/maniac/" \
     -maxdepth 1 -type f \
     -exec cp {} public/games/maniac-mansion/ \;
sudo chown -R 82:82 public/games/maniac-mansion/
```
