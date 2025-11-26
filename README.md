# RESTful Menu Fetcher

A WordPress plugin that exposes navigation menus via custom REST API endpoints. Perfect for headless WordPress, decoupled applications, and external integrations.

## 🚀 Features

- ✅ Expose all WordPress menus via REST API
- ✅ Get menus by ID or location (e.g., `primary`, `footer`)
- ✅ Nested menu hierarchy support with `?nested=true`
- ✅ List all registered menu locations
- ✅ Clean, structured JSON output
- ✅ No authentication required (public endpoints)
- ✅ Lightweight and performant

## 📦 Installation

1. Download or clone this repository
2. Copy the `restful-menu-fetcher` folder to your WordPress `wp-content/plugins` directory
3. Activate the plugin from **WP Admin → Plugins**
4. Ensure you have menus configured under **Appearance → Menus**

## 🔌 API Endpoints

Base namespace: `restful-menu/v1`

### Get All Menus
```
GET /wp-json/restful-menu/v1/menus
```
Returns all registered menus with their locations and items.

**Example Response:**
```json
[
  {
    "id": 2,
    "name": "Main Menu",
    "slug": "main-menu",
    "locations": ["primary"],
    "items": [...]
  }
]
```

### Get Single Menu by ID
```
GET /wp-json/restful-menu/v1/menus/{id}
```
Returns a specific menu and its items.

**Example:**
```
GET /wp-json/restful-menu/v1/menus/2
```

### Get All Menu Locations
```
GET /wp-json/restful-menu/v1/locations
```
Returns all registered menu locations with assigned menus.

**Example Response:**
```json
[
  {
    "location": "primary",
    "description": "Primary Menu",
    "menu_id": 2,
    "menu_name": "Main Menu"
  }
]
```

### Get Menu by Location
```
GET /wp-json/restful-menu/v1/locations/{location}
```
Returns the menu assigned to a specific location.

**Example:**
```
GET /wp-json/restful-menu/v1/locations/primary
```

## 🌳 Nested Menu Structure

Add `?nested=true` to any menu endpoint to get a hierarchical tree structure:

```
GET /wp-json/restful-menu/v1/menus/2?nested=true
GET /wp-json/restful-menu/v1/locations/primary?nested=true
```

**Flat structure (default):**
```json
{
  "items": [
    {"id": 1, "title": "Home", "parent": 0},
    {"id": 2, "title": "About", "parent": 0},
    {"id": 3, "title": "Team", "parent": 2}
  ]
}
```

**Nested structure (`?nested=true`):**
```json
{
  "items": [
    {
      "id": 1,
      "title": "Home",
      "children": []
    },
    {
      "id": 2,
      "title": "About",
      "children": [
        {
          "id": 3,
          "title": "Team",
          "children": []
        }
      ]
    }
  ]
}
```

## 📝 Menu Item Properties

Each menu item includes:

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Menu item ID |
| `title` | string | Display title |
| `url` | string | Link URL |
| `parent` | int | Parent item ID (0 for top-level) |
| `order` | int | Menu order |
| `type` | string | Item type (post_type, taxonomy, custom, etc.) |
| `object` | string | Object type (page, post, category, etc.) |
| `object_id` | int | ID of the linked object |
| `target` | string | Link target (_blank, etc.) |
| `classes` | array | CSS classes |
| `xfn` | string | XFN relationship |
| `children` | array | Child items (only in nested mode) |

## 💡 Usage Examples

### JavaScript (Fetch API)
```javascript
// Get primary menu
fetch('https://your-site.com/wp-json/restful-menu/v1/locations/primary')
  .then(response => response.json())
  .then(menu => console.log(menu));

// Get nested menu structure
fetch('https://your-site.com/wp-json/restful-menu/v1/menus/2?nested=true')
  .then(response => response.json())
  .then(menu => console.log(menu));
```

### React Example
```jsx
import { useEffect, useState } from 'react';

function Navigation() {
  const [menu, setMenu] = useState(null);

  useEffect(() => {
    fetch('https://your-site.com/wp-json/restful-menu/v1/locations/primary?nested=true')
      .then(res => res.json())
      .then(data => setMenu(data));
  }, []);

  if (!menu) return <div>Loading...</div>;

  return (
    <nav>
      {menu.items.map(item => (
        <a key={item.id} href={item.url}>{item.title}</a>
      ))}
    </nav>
  );
}
```

### cURL
```bash
# Get all menus
curl https://your-site.com/wp-json/restful-menu/v1/menus

# Get menu by location
curl https://your-site.com/wp-json/restful-menu/v1/locations/primary

# Get nested structure
curl "https://your-site.com/wp-json/restful-menu/v1/menus/2?nested=true"
```

## 🛠️ Development

### Requirements
- WordPress 5.0+
- PHP 7.0+

### File Structure
```
restful-menu-fetcher/
├── restful-menu-fetcher.php  # Main plugin file
├── readme.txt                # WordPress.org readme
└── README.md                 # GitHub readme
```

## 📄 License

GPL v2 or later

## 👨‍💻 Author

**Gunjan Jaswal**

- Website: [https://gunjanjaswal.me](https://gunjanjaswal.me)
- GitHub: [https://github.com/gunjanjaswal/RESTful-Menu-Fetcher](https://github.com/gunjanjaswal/RESTful-Menu-Fetcher)

## ☕ Support

If you find this plugin useful, consider supporting the developer:

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-Support-yellow?style=for-the-badge&logo=buy-me-a-coffee)](https://buymeacoffee.com/gunjanjaswal)

## 🤝 Contributing

Contributions, issues, and feature requests are welcome! Feel free to check the [issues page](https://github.com/gunjanjaswal/RESTful-Menu-Fetcher/issues).

## ⭐ Show Your Support

Give a ⭐️ if this project helped you!

---

Made with ❤️ by [Gunjan Jaswal](https://gunjanjaswal.me)
