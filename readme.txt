=== EndPointy Menus ===
Contributors: gunjanjaswal
Tags: rest api, menus, navigation, headless, json
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://ko-fi.com/gunjanjaswal

Expose WordPress menus via a custom REST API endpoint for headless and external applications.

== Description ==

EndPointy Menus adds custom REST API routes to expose your WordPress navigation menus as JSON.

**Base namespace:** `endpointy-menus/v1`

**Endpoints:**

- `GET /wp-json/endpointy-menus/v1/menus`
  Returns all registered menus with locations and items.

- `GET /wp-json/endpointy-menus/v1/menus/<id>`
  Returns a single menu and its items by menu ID.

- `GET /wp-json/endpointy-menus/v1/menus/slug/<slug>`
  Returns a single menu and its items by menu slug.

- `GET /wp-json/endpointy-menus/v1/locations`
  Returns all registered menu locations with assigned menus.

- `GET /wp-json/endpointy-menus/v1/locations/<location>`
  Returns a menu assigned to a specific location (e.g., 'primary', 'footer').

**Query Parameters:**

- `nested=true` - Returns menu items in a hierarchical tree structure with parent-child relationships.
  Example: `/wp-json/endpointy-menus/v1/menus/2?nested=true`

- `meta=true` - Adds extra item data: description, attr_title, current flag, featured image URL and post excerpt.
  Example: `/wp-json/endpointy-menus/v1/menus/2?meta=true`

- `fields=id,title,url` - Returns only the listed fields per item (children are always kept in nested mode).
  Example: `/wp-json/endpointy-menus/v1/menus/2?fields=id,title,url`

Parameters can be combined, e.g. `?nested=true&meta=true&fields=id,title,url,children`.

**Settings (Settings → EndPointy Menus):**

- Response caching with a configurable lifetime (transients, auto-flushed when a menu is edited).
- Optional API-key protection via `X-API-Key` header or `api_key` query parameter.
- CORS allow-list for browser-based consumers.
- Per-IP rate limiting (requests/minute) with X-RateLimit headers and 429 responses.
- ETag / Last-Modified conditional requests returning 304 Not Modified.

**Developer hooks:**

- `endpointy_menus_item` filter - modify each formatted menu item before output.

This is useful for headless WordPress setups or any external app that needs to read your menu structure.

== Installation ==

1. Upload the `endpointy-menus` folder to your `wp-content/plugins` directory.
2. Activate **EndPointy Menus** from the Plugins screen in WordPress.
3. Ensure you have at least one menu configured under **Appearance → Menus**.

== Usage ==

Example requests:

**Get all menus:**
`https://your-site.com/wp-json/endpointy-menus/v1/menus`

**Get single menu by ID:**
`https://your-site.com/wp-json/endpointy-menus/v1/menus/2`

**Get all menu locations:**
`https://your-site.com/wp-json/endpointy-menus/v1/locations`

**Get menu by location (e.g., 'primary'):**
`https://your-site.com/wp-json/endpointy-menus/v1/locations/primary`

**Get nested menu structure:**
`https://your-site.com/wp-json/endpointy-menus/v1/menus/2?nested=true`
`https://your-site.com/wp-json/endpointy-menus/v1/locations/primary?nested=true`

Use these URLs directly from your front-end application or API client.

== Support the Developer ==

If you find this plugin useful, you can support the developer:

- Website: https://gunjanjaswal.me
- GitHub: https://github.com/gunjanjaswal/Endpointy-Menus
- Ko-fi: https://ko-fi.com/gunjanjaswal

== Contributing ==

Contributions, issues, and feature requests are welcome!
GitHub: https://github.com/gunjanjaswal/Endpointy-Menus
Issues: https://github.com/gunjanjaswal/Endpointy-Menus/issues

== Changelog ==

= 1.2.0 =
* Added admin settings page under Settings → EndPointy Menus.
* Added response caching (transients) with configurable lifetime and automatic flush on menu edits.
* Added optional API-key authentication (X-API-Key header or api_key query param).
* Added configurable CORS allow-list.
* Added per-IP rate limiting with X-RateLimit headers and 429 responses.
* Added ETag / Last-Modified conditional requests (304 Not Modified).
* Added `/menus/slug/<slug>` endpoint to fetch a menu by slug.
* Added `meta=true` query parameter for description, featured image, excerpt and current flag.
* Added `fields=` query parameter to limit returned item fields.
* Added `endpointy_menus_item` filter for developers.
* Added menu item `count` to menu payloads and `menu_slug` to locations.

= 1.1.1 =
* Updated "Tested up to" to WordPress 7.0.
* Updated donation link to Ko-fi (https://ko-fi.com/gunjanjaswal).

= 1.1.0 =
* Added support for filtering menus by location.
* Added nested menu hierarchy with `nested=true` query parameter.
* Added `/locations` endpoint to list all menu locations.
* Added `/locations/<location>` endpoint to get menu by location.

= 1.0.0 =
* Initial release.
