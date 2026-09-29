# CANITY

Embeds services, events, and packages from the CANITY Partner API into WordPress via shortcode or Gutenberg block.

## Repository structure

```
canity-wordpress-plugin-playground/   ← Git repo
├── .distignore                       ← Excludes for deploy/ZIP (10up Action)
├── .wordpress-org/                   ← Store banners, icons, screenshots (≠ canity/assets/)
├── .github/workflows/deploy.yml      ← Deploy to wordpress.org (on tag push)
├── README.md                         ← this file
└── canity/                           ← WordPress plugin (slug)
    ├── canity.php
    ├── uninstall.php
    ├── readme.txt
    ├── includes/
    └── assets/                       ← Runtime CSS, fonts, JS, placeholder images
```

The `canity/` folder matches the slug in the WordPress Plugin Directory. The Git repo name and plugin slug are intentionally separate.

### Two different `assets/` folders

| Path | Purpose |
|------|---------|
| `canity/assets/` | Plugin runtime assets (CSS, fonts, JS, images) — ends up in `trunk/assets/` on SVN |
| `.wordpress-org/` | Store marketing only (772×250 banners, 128/256 icons, screenshots) — deploy action copies to SVN `assets/` **next to** `trunk/` |

Same name on wordpress.org SVN, different meaning. Details: [.wordpress-org/README.md](.wordpress-org/README.md).

## Release / WordPress.org

1. Add store assets to `.wordpress-org/` (see README there).
2. Maintain `canity/readme.txt` (`Stable tag` = version in `canity.php`).
3. Create a Git tag → GitHub Action deploys via `BUILD_DIR=canity` and `SLUG=canity`.
4. Configure secrets `SVN_USERNAME` / `SVN_PASSWORD` in the GitHub repo.

`.distignore` keeps repo files (README, `.wordpress-org`, CI) out of the plugin package.

## Local testing with LocalWP

### Option 1: Symlink (recommended for development)

Code changes are live immediately — no re-upload needed.

```bash
ln -s /Users/<your-username>/repo/canity-wordpress-plugin-playground/canity \
  ~/"Local Sites/<your-site-name>/app/public/wp-content/plugins/canity"
```

Then activate the **CANITY** plugin under **Plugins** in WP Admin.

> In LocalWP, right-click the site → *Reveal in Finder* to find the site path quickly.

### Option 2: ZIP upload (simulates production install)

```bash
cd /Users/<your-username>/repo/canity-wordpress-plugin
zip -r canity.zip canity -x "canity/.DS_Store" -x "canity/**/.DS_Store"
```

Then in WP Admin: **Plugins → Add New → Upload Plugin** → select `canity.zip` → install → activate.

## Setup after installation

1. Open **Einstellungen → CANITY**, enter your Partner API token, and save. Create the token in CANITY under *Mein Business → Partner API*.
2. Add the shortcode `[canity type="services"]`, `[canity type="events" limit="5"]`, or `[canity type="packages"]` to a page.
3. Or add the **CANITY Liste** block in the block editor and choose content type and count.
4. Optional: Enable the detail view under *Einstellungen → CANITY* (modal, dedicated page with `[canity_detail]`, or inline).

A full end-user guide is in [canity/readme.txt](canity/readme.txt) (*Usage* section).

## Detail view

Under **Einstellungen → CANITY**, the detail view can be embedded locally. The booking button always links to canity.de. Deep links for modal/inline: `#canity-detail/{type}/{id}`.

## During development

### Flush cache

API responses are cached for 15 minutes. Use **Cache jetzt leeren** under *Einstellungen → CANITY*, or temporarily lower the TTL in [canity/includes/class-canity-api.php](canity/includes/class-canity-api.php).

### View PHP errors

In LocalWP: *Site → Utilities → Open Site Shell*, and set `WP_DEBUG` and `WP_DEBUG_LOG` to `true` in `wp-config.php`. Logs are written to `app/public/wp-content/debug.log`.

## Plugin structure

```
canity/canity.php                     Plugin header, bootstrap, constants
canity/uninstall.php                  Cleans up options + transients on uninstall
canity/includes/
  class-canity-api.php              Partner API client + 15-min transient cache
  class-canity-settings.php         Settings page (Einstellungen → CANITY)
  class-canity-detail.php           Detail rendering + [canity_detail]
  class-canity-rest.php             REST endpoint for modal/inline
  class-canity-shortcodes.php       [canity type="services|events|packages"]
  class-canity-block.php            Gutenberg blocks canity/list and canity/detail
  class-canity-assets.php           Conditional CSS loading
canity/assets/
  css/canity.css                    Namespaced .canity-* styles
  js/canity.js                      Modal, inline, deep-link handling
  js/block.js                       Block editor script
canity/readme.txt                   WordPress.org directory metadata
```
