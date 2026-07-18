# WordPress.org plugin assets

These files are uploaded to the **`assets/` root of the WordPress.org SVN
repository** (not shipped inside the plugin ZIP). `icon.svg` here is the brand
source; the rest must be produced from it before submission.

Required before submitting to the plugin directory:

| File | Size | Purpose |
|------|------|---------|
| `icon.svg` **or** `icon-256x256.png` + `icon-128x128.png` | 256²/128² | Directory + admin icon |
| `banner-772x250.png` | 772×250 | Plugin header banner |
| `banner-1544x500.png` | 1544×500 | Retina header banner |
| `screenshot-1.png` … | any | Match the `== Screenshots ==` captions in `readme.txt` |

Brand: navy `#0f1c33`, lime `#bfdb00`, off-white `#f5f5f0`. Mark: `icon.svg`.

`icon.svg` is provided and is a valid WordPress.org icon on its own. Banners and
screenshots still need to be created (screenshots are easiest to capture from a
real page once the plugin is installed via `npm run env:start`).
