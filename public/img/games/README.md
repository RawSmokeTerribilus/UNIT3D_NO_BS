# Game Cover Art

Cover images for the arcade catalog. Files are gitignored and must be copied
manually. See `docs/GAMING_SETUP.md` for the full install procedure.

## Required files

| Filename | Format | Game |
|----------|--------|------|
| `mi1-vga.jpg` | JPG | The Secret of Monkey Island (VGA) |
| `mi2-talkie.jpg` | JPG | Monkey Island 2: LeChuck's Revenge (CD Talkie) |
| `maniac-mansion.png` | PNG | Maniac Mansion |
| `loom.png` | PNG | Loom (CD Talkie) |
| `zak-mckracken.png` | PNG | Zak McKracken and the Alien Mindbenders |
| `indy-atlantis.png` | PNG | Indiana Jones and the Fate of Atlantis |
| `samnmax.png` | PNG | Sam & Max Hit the Road |

## Installation

```bash
# Copy covers from your local collection:
sudo cp gaming/ScummVM\ Collection\ 1.2/*/\*Cover*.png public/img/games/
# Then rename to match the catalog IDs listed above.
sudo chown -R 82:82 public/img/games/
```
