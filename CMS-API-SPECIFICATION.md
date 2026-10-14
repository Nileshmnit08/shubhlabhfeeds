# Shubh Labh Website CMS — API Specification (CMS-API-SPECIFICATION.md)

**Project:** Shubh Labh Public Website  
**Protocol:** HTTPS / RESTful JSON / Server-Side Direct Data Access  
**Evaluation Date:** 2026-10-14  
**Audit Status:** Blueprint Specification Only — Zero Code/API Modifications in this Phase

---

## 1. Architectural Integration Model

To maintain the ultra-fast, premium performance established in previous sprints, the public website integrates with the CMS via a **Dual-Mode Consumption Architecture**:

```
+-----------------------------------------------------------------------------------+
|  1. Primary Server-Side Rendering (SSR) via index.php                             |
|     - Directly queries MySQL via high-performance PDO.                            |
|     - Injects published data into the existing HTML/CSS structure on initial load.|
|     - 0ms client fetch lag. 100% crawlable by Googlebot / social bots.            |
|                                                                                   |
|  2. Read-Only Public REST API (/api/public/...)                                   |
|     - Used for dynamic client updates without full page reload.                   |
|     - Only returns published records (`status = 'published'`).                    |
|     - HTTP caching headers enabled (Cache-Control: public, max-age=300).          |
|                                                                                   |
|  3. Protected Admin REST API (/api/admin/...)                                     |
|     - Powering the secure `/admin` dashboard.                                     |
|     - Requires authenticated session cookie + CSRF header token.                  |
|     - Full CRUD access with role-based authorization.                             |
+-----------------------------------------------------------------------------------+
```

---

## 2. Public Read-Only Endpoints

All public endpoints are strictly read-only, require no authentication, omit unpublished drafts, and output standard JSON with UTF-8 encoding.

### A. `GET /api/public/bootstrap`
Retrieves all global public data required to render the layout in a single request (settings, navigation, announcement).

#### Request:
`GET /api/public/bootstrap HTTP/1.1`  
`Host: shubhlabhfeed.com`  
`Accept: application/json`

#### Response (`200 OK`):
```json
{
  "status": "success",
  "data": {
    "settings": {
      "brand_name": "SHUBH LABH",
      "brand_tagline": "DAIRY NUTRITION",
      "announcement": {
        "is_active": true,
        "text": "Nutrition made with farmers in mind ✳ Serving dairy ambitions across Rajasthan"
      },
      "meta_title": "Shubh Labh | Better Nutrition. Stronger Dairy.",
      "meta_description": "Shubh Labh — cattle feed and dairy nutrition solutions for farmers and dairy businesses.",
      "theme_color": "#143d28",
      "crm_login_url": "https://shubhlabh-crm.vercel.app/"
    },
    "navigation": [
      { "id": 1, "label": "Home", "url": "#home", "target": "_self" },
      { "id": 2, "label": "Products", "url": "#products", "target": "_self" },
      { "id": 3, "label": "About us", "url": "#about", "target": "_self" },
      { "id": 4, "label": "Knowledge", "url": "#knowledge", "target": "_self" },
      { "id": 5, "label": "Become a dealer", "url": "#dealer", "target": "_self" },
      { "id": 6, "label": "Contact", "url": "#contact", "target": "_self" }
    ],
    "contact_channels": {
      "whatsapp_url": "https://wa.me/?text=Hello%20Shubh%20Labh%2C%20I%20would%20like%20to%20know%20more.",
      "phone": null,
      "email": null,
      "address": null,
      "status_note": "Official phone, email and address pending verification"
    }
  }
}
```

---

### B. `GET /api/public/products`
Retrieves all published products sorted by `display_order ASC`.

#### Request:
`GET /api/public/products HTTP/1.1`

#### Response (`200 OK`):
```json
{
  "status": "success",
  "count": 3,
  "data": [
    {
      "id": 1,
      "name": "Shubh Labh Dairy Special",
      "slug": "dairy-special",
      "category": {
        "name": "Daily Nutrition",
        "badge": "CATTLE FEED"
      },
      "short_description": "A core cattle-feed range for everyday dairy nutrition.",
      "packaging_description": "Standard 50kg bag",
      "image": {
        "url": "/uploads/products/dairy_special_bag.webp",
        "alt_text": "Shubh Labh Dairy Special cattle feed packaging"
      },
      "enquiry_url": "#contact"
    },
    {
      "id": 2,
      "name": "Performance Premix",
      "slug": "performance-premix",
      "category": {
        "name": "Premix",
        "badge": "NUTRITION SUPPORT"
      },
      "short_description": "A nutrition premix designed for targeted formulation needs.",
      "packaging_description": null,
      "image": null,
      "enquiry_url": "#contact"
    },
    {
      "id": 3,
      "name": "Specialised Nutrition",
      "slug": "specialised-nutrition",
      "category": {
        "name": "Dairy Nutrition",
        "badge": "SPECIALISED RANGE"
      },
      "short_description": "Explore mineral and other nutrition solutions for dairy needs.",
      "packaging_description": null,
      "image": null,
      "enquiry_url": "#contact"
    }
  ]
}
```

---

### C. `GET /api/public/knowledge`
Retrieves published educational articles with cover images and summaries.

#### Response (`200 OK`):
```json
{
  "status": "success",
  "count": 2,
  "disclaimer": "Educational topics are introductory. Feeding plans and animal-health concerns should be discussed with a qualified nutritionist or veterinarian.",
  "data": [
    {
      "id": 1,
      "title": "Building a balanced feeding routine",
      "slug": "building-a-balanced-feeding-routine",
      "category": "Feeding Basics",
      "summary": "Understand why consistency and a balanced ration matter.",
      "cover_image": {
        "url": "https://images.unsplash.com/photo-1500595046743-cd271d694d30?auto=format&fit=crop&w=1200&q=85",
        "alt_text": "Open dairy farmland pasture at sunrise - cattle feeding ration routine"
      },
      "published_at": "2026-10-14 00:00:00"
    },
    {
      "id": 2,
      "title": "Care across the dairy journey",
      "slug": "care-across-the-dairy-journey",
      "category": "Herd Care",
      "summary": "Learn about the changing needs of dairy animals.",
      "cover_image": {
        "url": "https://images.unsplash.com/photo-1524024973431-2ad916746881?auto=format&fit=crop&w=900&q=85",
        "alt_text": "Healthy dairy cow grazing in green meadow - herd care and nutrition"
      },
      "published_at": "2026-10-14 00:00:00"
    }
  ]
}
```

---

## 3. Protected Admin Endpoints

All admin routes require an authenticated HTTP session (`PHPSESSID`) with `SameSite=Strict; HttpOnly; Secure`, and a valid CSRF header (`X-CSRF-Token: <token>`) for write actions (`POST`, `PUT`, `DELETE`).

| Method | Endpoint | Description | Access Role |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/admin/auth/login` | Authenticate admin user with username & password | Public |
| `POST` | `/api/admin/auth/logout` | Invalidate current session and destroy cookies | Authenticated |
| `GET` | `/api/admin/auth/me` | Return current logged-in user profile & role | Authenticated |
| `GET` | `/api/admin/dashboard` | Return content metrics (published/draft counts) | Authenticated |
| `GET` | `/api/admin/sections/{key}` | Fetch section content fields for editing | Authenticated |
| `PUT` | `/api/admin/sections/{key}` | Update section content fields & publication state | Editor+ |
| `GET` | `/api/admin/products` | List all products (including drafts & archived) | Authenticated |
| `POST` | `/api/admin/products` | Create new product record | Editor+ |
| `PUT` | `/api/admin/products/{id}` | Update existing product record | Editor+ |
| `DELETE`| `/api/admin/products/{id}`| Archive or delete product record | Super Admin |
| `GET` | `/api/admin/knowledge` | List all knowledge articles | Authenticated |
| `POST` | `/api/admin/knowledge` | Create new knowledge article | Editor+ |
| `PUT` | `/api/admin/knowledge/{id}`| Update existing knowledge article | Editor+ |
| `GET` | `/api/admin/media` | List paginated media library assets with previews | Authenticated |
| `POST` | `/api/admin/media` | Upload and sanitize new media asset (multipart) | Editor+ |
| `DELETE`| `/api/admin/media/{id}` | Remove media asset and clear foreign references | Super Admin |
| `GET` | `/api/admin/settings` | List all system settings categorized by group | Authenticated |
| `PUT` | `/api/admin/settings` | Update system settings (contact info, branding) | Super Admin |

---

## 4. Standardized Error Format (RFC 7807)

All API errors return appropriate HTTP status codes paired with structured JSON:

```json
{
  "type": "https://shubhlabhfeed.com/errors/validation-error",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The product name is required and cannot be empty.",
  "invalid_params": [
    {
      "name": "name",
      "reason": "Product name must be between 3 and 150 characters."
    }
  ]
}
```

---

## 5. Graceful Fallback & Error Resilience

1. **Database Offline State:** If the MySQL database connection fails, the frontend catches the exception and immediately renders the static content baseline established in `SL-WEB-05`. Visitors experience zero broken pages or ugly error stacks.
2. **Empty Content State:** If a newly created product has no image, the system falls back to the refined CSS geometric packaging illustrations (`feed-image`, `premix-image`, `mineral-image`).
3. **No Draft Leaks:** SQL queries on public routes enforce `WHERE status = 'published' AND is_published = 1`. Draft content is cryptographically segregated from the public API layer.
