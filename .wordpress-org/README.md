# WordPress.org store assets

This folder contains **only** banners, icons, and screenshots for the plugin page on wordpress.org — **not** the plugin runtime assets (`canity/assets/`).

The [10up deploy action](https://github.com/10up/action-wordpress-plugin-deploy) copies the contents to SVN `assets/` at **trunk level** (next to `trunk/`, not inside it).

## Expected files

| File | Size | Purpose |
|------|------|---------|
| `icon-128x128.png` | 128×128 | Plugin icon |
| `icon-256x256.png` | 256×256 | Plugin icon (Retina) |
| `banner-772x250.png` | 772×250 | Store banner |
| `banner-1544x500.png` | 1544×500 | Store banner (Retina) |
| `screenshot-1.png` … | 1200×900 recommended | Screenshots (order matches readme.txt) |

File names and specs: [Plugin Assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)

## Note

`.wordpress-org/` is listed in `.distignore` and is **not** included in the plugin ZIP. Only the deploy to wordpress.org uses these files.
