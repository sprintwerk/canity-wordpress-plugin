=== CANITY ===
Contributors: sprintwerk
Tags: canity
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Official CANITY WordPRess plugin to display services, events, and packages from the CANITY Partner API using a shortcode or Gutenberg block.

== Description ==

This plugin connects your WordPress site to your CANITY account and displays services, events, and packages on any page. By default, clicks open the detail page on canity.de. Optionally, the detail view can be embedded locally (modal, dedicated page, or inline).

**Features**

* Settings page under *Settings → CANITY* for the Partner API token
* A single shortcode `[canity type="services|events|packages"]` and `[canity_detail]` for the detail page
* Gutenberg block "CANITY List" as a convenient wrapper
* Optional embedded detail view (modal, dedicated WordPress page, inline) with deep links (`#canity-detail/{type}/{id}`)
* Booking button always links to canity.de
* Responses are cached for 15 minutes (Transients API), reducing API load
* Custom CSS with `.canity-` namespacing, loaded only where needed; can be disabled under *Settings → CANITY* (enabled by default)

== Installation ==

1. Upload the plugin to `wp-content/plugins/canity` or install it from the plugin directory.
2. Activate the plugin.
3. Under *Settings → CANITY*, enter and save your **Partner API token**. Create the token in CANITY under *My Business → Partner API* (shown only once).
4. Add the shortcode or block to any page.

== Usage ==

= Shortcode =

There is a single shortcode `[canity]`. Use the `type` attribute to choose what to display. The shortcode works on any page, post, or widget area.

**Display services**

`[canity type="services"]`

Lists all active individual offerings from your business as clickable cards — with image, title, optional "Online" label, duration, and price.

**Display events / group offerings**

`[canity type="events"]`

Shows all upcoming events with image, date badge, title, number of dates, and price.

**Display packages and multi-visit cards**

`[canity type="packages"]`

Displays all prepaid packages as compact cards with title, unit count, and price.

= Attributes =

The optional `limit` attribute restricts how many items are shown:

`[canity type="services" limit="3"]`
`[canity type="events" limit="5"]`
`[canity type="packages" limit="4"]`

Without `limit` (or with `limit="0"`), all items are displayed.

**Detail view**

Under *Settings → CANITY*, you can embed the detail view on your site:

* **Off (default):** Click opens canity.de in a new tab.
* **Modal:** Details in a modal; deep link e.g. `#canity-detail/events/{id}`.
* **Dedicated page:** Click goes to a WordPress page with `[canity_detail]` and query parameters `?canity_type=…&canity_id=…`.
* **Inline:** Detail expands below the card (only one panel open at a time).

Shortcode override: `[canity type="services" detail="modal"]` (values: `external`, `modal`, `page`, `inline`, or empty for global setting).

The **Book**/**Buy** button in the detail view always links to canity.de.

= Gutenberg block =

If you prefer a visual workflow, add the **"CANITY List"** block in the block editor. In the sidebar you can choose the content type (Services / Events / Packages), the number of items, and optionally the detail view — without shortcode syntax.

For **Dedicated page** mode, use the **"CANITY Detail"** block (or shortcode `[canity_detail]`) on the detail page.

= Caching =

API responses are cached for 15 minutes. Under *Settings → CANITY*, use **Flush cache now** to clear the cache manually — useful when you want changes in CANITY to appear on your site immediately.

== FAQ ==

= Where do I get the Partner API token, and what if I lose it? =

Create the token in CANITY under *My Business → Partner API*. It is shown only once — copy it immediately. Enter it under *Settings → CANITY* in WordPress and save. If you lose the token, revoke it in CANITY, create a new one, and update the plugin settings.

= Why don't changes in CANITY show up on my site right away? =

API responses are cached for 15 minutes to reduce load on the CANITY API. Use **Flush cache now** under *Settings → CANITY* to refresh the data immediately after you update offerings in CANITY.

= Can visitors book directly on my WordPress site? =

No. The plugin displays lists and optional detail views on your site. The **Book** / **Buy** button always links to canity.de, where the actual booking or purchase takes place.

= Which detail view should I use — external, modal, dedicated page, or inline? =

* **External (default):** Click opens the detail page on canity.de in a new tab — simplest setup, no extra configuration.
* **Modal:** Details open in a modal on your site; supports deep links (e.g. `#canity-detail/events/{id}`).
* **Dedicated page:** Click navigates to a WordPress page with `[canity_detail]` and query parameters (`?canity_type=…&canity_id=…`).
* **Inline:** Detail expands below the clicked card (only one panel open at a time).

We recommend **external** or **modal**: external keeps visitors on the canonical CANITY booking flow; modal keeps them on your site while still linking booking to canity.de. Configure the default under *Settings → CANITY*, or override per shortcode with `detail="external"` or `detail="modal"`.

= Is personal data from my website visitors sent to CANITY? =

No. Only the Partner API token stored in your settings (sent server-side as an `Authorization` header) and a plugin User-Agent are transmitted to the CANITY Partner API. Images are loaded directly from CANITY servers in the visitor's browser. When visitors click through to details or booking, they leave your website for canity.de.

== External services ==

This plugin uses the external service **CANITY** ([canity.de](https://canity.de)) to display services, events, and packages from your CANITY business on your WordPress site.

= CANITY Partner API =

**Service:** CANITY Partner API (`https://canity.de/api`)

**Purpose:** Fetching list and detail data (services, events, packages, business profile) for display via shortcode, Gutenberg block, or embedded detail view.

**What data is sent?**

* The **Partner API token** stored in the plugin settings (as an `Authorization: Bearer …` HTTP header).
* A **User-Agent** header with the plugin version (`CANITY-WP-Plugin/…`).

**No personal data from your website visitors** is transmitted to CANITY.

**When is data sent?**

* When **saving the API token** under *Settings → CANITY* (validation via `/partner/v1/me` and `/partner/v1/business`).
* When **serving a page** with the shortcode or block (server-side read requests to `/partner/v1/services`, `/partner/v1/events`, and `/partner/v1/packages`).
* With **embedded detail view** (modal, inline, or dedicated page): additional read requests to the respective detail endpoints; in modal/inline mode, optionally via the WordPress REST endpoint `/wp-json/canity/v1/detail` (which forwards the request to CANITY server-side).

API responses are cached in WordPress transients for up to **15 minutes**.

= Images and links =

**Images:** The API returns image URLs (e.g. for service, event, and trainer photos). These are loaded directly from CANITY servers in your visitors' browsers (`<img src="…">`).

**Links:** Detail and booking buttons link to public pages under `https://canity.de` (e.g. `canity.de/b/{business-slug}/…`). When clicked, the visitor leaves your website.

= Legal =

* Terms of use: [https://www.canity.de/agb](https://www.canity.de/agb)
* Privacy policy: [https://www.canity.de/datenschutz](https://www.canity.de/datenschutz)

== Privacy and security ==

= Partner API token =

The Partner API token (`cnty_sk_…`) is stored in the WordPress option `canity_options` **in plain text**. This matches common practice for WordPress plugins that require API keys.

**Who has access?**

* WordPress users with **Manage options** (`manage_options`) can set or replace the token under *Settings → CANITY*.
* The admin UI shows only a **truncated prefix** (e.g. `cnty_sk_12345678…`); the full key is not shown again there.
* The full token is readable in the **database** (`wp_options`) and in **database backups/exports**.

**Recommendations**

* Protect admin accounts and backup access accordingly.
* If compromised, revoke the token in CANITY and enter a new one in the plugin settings.

This plugin does not store or transmit personal data from your website visitors to CANITY (see *External services*).

== Resources ==

This plugin bundles the following third-party resource:

Inter font, Copyright 2016 The Inter Project Authors (https://github.com/rsms/inter)
Licensed under the SIL Open Font License, Version 1.1 (see `assets/fonts/OFL.txt`)
Source: https://rsms.me/inter/

Placeholder images (`assets/img/placeholder_service.png`, `assets/img/placeholder_event.png`)
Copyright CANITY. Included with permission for use in this plugin.

== Changelog ==

= 1.0.0 =
* Initial release.
