=== RESTful Menu Fetcher ===
Contributors: gunjanjaswal
Tags: rest api, menus, navigation, headless, json
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://buymeacoffee.com/gunjanjaswal

Expose WordPress menus via a custom REST API endpoint for headless and external applications.

== Description ==

RESTful Menu Fetcher adds custom REST API routes to expose your WordPress navigation menus as JSON.

**Base namespace:** `restful-menu/v1`

**Endpoints:**

- `GET /wp-json/restful-menu/v1/menus`
  Returns all registered menus with locations and items.

- `GET /wp-json/restful-menu/v1/menus/<id>`
  Returns a single menu and its items by menu ID.

- `GET /wp-json/restful-menu/v1/locations`
  Returns all registered menu locations with assigned menus.

- `GET /wp-json/restful-menu/v1/locations/<location>`
  Returns a menu assigned to a specific location (e.g., 'primary', 'footer').

**Query Parameters:**

- `nested=true` - Returns menu items in a hierarchical tree structure with parent-child relationships.
  Example: `/wp-json/restful-menu/v1/menus/2?nested=true`

This is useful for headless WordPress setups or any external app that needs to read your menu structure.

== Installation ==

1. Upload the `restful-menu-fetcher` folder to your `wp-content/plugins` directory.
2. Activate **RESTful Menu Fetcher** from the Plugins screen in WordPress.
3. Ensure you have at least one menu configured under **Appearance → Menus**.

== Usage ==

Example requests:

**Get all menus:**
`https://your-site.com/wp-json/restful-menu/v1/menus`

**Get single menu by ID:**
`https://your-site.com/wp-json/restful-menu/v1/menus/2`

**Get all menu locations:**
`https://your-site.com/wp-json/restful-menu/v1/locations`

**Get menu by location (e.g., 'primary'):**
`https://your-site.com/wp-json/restful-menu/v1/locations/primary`

**Get nested menu structure:**
`https://your-site.com/wp-json/restful-menu/v1/menus/2?nested=true`
`https://your-site.com/wp-json/restful-menu/v1/locations/primary?nested=true`

Use these URLs directly from your front-end application or API client.

== Support the Developer ==

If you find this plugin useful, you can support the developer:

- Website: https://gunjanjaswal.me
- GitHub: https://github.com/gunjanjaswal/RESTful-Menu-Fetcher
- Buy Me a Coffee: https://buymeacoffee.com/gunjanjaswal

== Contributing ==

Contributions, issues, and feature requests are welcome!
GitHub: https://github.com/gunjanjaswal/RESTful-Menu-Fetcher
Issues: https://github.com/gunjanjaswal/RESTful-Menu-Fetcher/issues

== Changelog ==

= 1.1.0 =
* Added support for filtering menus by location.
* Added nested menu hierarchy with `nested=true` query parameter.
* Added `/locations` endpoint to list all menu locations.
* Added `/locations/<location>` endpoint to get menu by location.

= 1.0.0 =
* Initial release.
