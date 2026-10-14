# Shubh Labh Website CMS — Data Model Proposal (CMS-DATA-MODEL.md)

**Project:** Shubh Labh Public Website  
**Database Target:** MySQL 8.0+ / MariaDB 10.6+  
**Collation:** `utf8mb4_unicode_ci` (Full Unicode support for Hindi glyphs `शुभ लाभ` and English)  
**Evaluation Date:** 2026-10-14  
**Audit Status:** Blueprint Specification Only — Zero Database Modifications in this Phase

---

## 1. Schema Architecture & Design Philosophy

The database schema is designed according to Third Normal Form (3NF), prioritizing data integrity, referential constraints, indexing for sub-millisecond public query execution, and complete isolation from the external Shubh Labh CRM.

```
+------------------+         +--------------------+         +-----------------------+
|   admin_users    |         |    media_assets    |         |  product_categories   |
+------------------+         +--------------------+         +-----------------------+
| id (PK)          |<---+    | id (PK)            |<---+    | id (PK)               |
| username (UQ)    |    |    | file_name          |    |    | name                  |
| email (UQ)       |    |    | file_path          |    |    | slug (UQ)             |
| password_hash    |    |    | mime_type          |    |    | display_order         |
| role             |    |    | file_size          |    |    +-----------------------+
| last_login_at    |    |    | alt_text           |    |                ^
+------------------+    |    | uploaded_by (FK) --+    |                |
                        |    +--------------------+    |                | (FK: category_id)
                        |              ^               |                |
+------------------+    |              | (FK)          |    +-----------+-----------+
|    audit_logs    |    |              +---------------+    |       products        |
+------------------+    |              |                    +-----------------------+
| id (PK)          |    |              | (FK: main_image_id)| id (PK)               |
| user_id (FK) ----+----+              |                    | category_id (FK)      |
| action           |                   +---------------+    | name                  |
| entity_type      |                   | (FK: cover)   |    | slug (UQ)             |
| entity_id        |         +---------+----------+    |    | short_description     |
| created_at       |         | knowledge_articles |    |    | full_description      |
+------------------+         +--------------------+    |    | main_image_id (FK) ---+
                             | id (PK)            |    |    | packaging_desc        |
+------------------+         | title              |    |    | display_order         |
|  site_settings   |         | slug (UQ)          |    |    | status (ENUM)         |
+------------------+         | summary            |    |    | created_at / upd_at   |
| id (PK)          |         | content_body       |    |    +-----------------------+
| setting_key (UQ) |         | cover_image_id (FK)+----+
| setting_value    |         | category           |
| setting_group    |         | display_order      |
| is_public (BOOL) |         | status (ENUM)      |
+------------------+         | published_at       |
                             +--------------------+
```

---

## 2. Table Specifications & DDL Blueprints

### A. `admin_users` — Administrative Authentication
Stores authorized administrative operators with password hashes generated via `PASSWORD_ARGON2ID` or `PASSWORD_BCRYPT`.

```sql
CREATE TABLE admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'editor') NOT NULL DEFAULT 'editor',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_lookup (username, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### B. `media_assets` — Unified Media Library
Tracks all uploaded brand logos, packaging photography, banner images, and educational article covers.

```sql
CREATE TABLE media_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL UNIQUE,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    width SMALLINT UNSIGNED NULL,
    height SMALLINT UNSIGNED NULL,
    alt_text VARCHAR(255) NOT NULL DEFAULT '',
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) 
        REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_media_mime (mime_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### C. `site_settings` — Key-Value Store for Global Business Data
Stores contact details, header/footer configuration, SEO defaults, and external portal destinations.

```sql
CREATE TABLE site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(64) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_group ENUM('general', 'contact', 'seo', 'social', 'integration') NOT NULL DEFAULT 'general',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_public_settings (is_public, setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Pre-Populated Default Seed Values:
- `brand_name` = `'SHUBH LABH'`
- `brand_tagline` = `'DAIRY NUTRITION'`
- `announcement_text` = `'Nutrition made with farmers in mind ✳ Serving dairy ambitions across Rajasthan'`
- `announcement_active` = `'1'`
- `crm_login_url` = `'https://shubhlabh-crm.vercel.app/'` *(Immutable CRM link)*
- `whatsapp_number` = `NULL` *(Pending Product Owner confirmation)*
- `phone_number` = `NULL` *(Pending Product Owner confirmation)*
- `public_email` = `NULL` *(Pending Product Owner confirmation)*
- `postal_address` = `NULL` *(Pending Product Owner confirmation)*

### D. `site_sections` — Structured Sectional Content
Allows in-place editing of narrative text for Home, About, Trust Strip, Dealership, and Guiding Values.

```sql
CREATE TABLE site_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    subtitle TEXT NULL,
    eyebrow VARCHAR(100) NULL,
    lead_text TEXT NULL,
    body_text MEDIUMTEXT NULL,
    primary_cta_text VARCHAR(100) NULL,
    primary_cta_url VARCHAR(255) NULL,
    secondary_cta_text VARCHAR(100) NULL,
    secondary_cta_url VARCHAR(255) NULL,
    media_id INT UNSIGNED NULL,
    additional_data JSON NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_section_media FOREIGN KEY (media_id)
        REFERENCES media_assets(id) ON DELETE SET NULL,
    INDEX idx_published_sections (section_key, is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### E. `product_categories` & `products` — Product Catalog
Manages cattle feeds, formulation premixes, and dairy nutrition supplements.

```sql
CREATE TABLE product_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    category_badge VARCHAR(50) NULL, -- e.g., 'CATTLE FEED', 'NUTRITION SUPPORT'
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    short_description VARCHAR(255) NOT NULL,
    full_description TEXT NULL,
    packaging_desc VARCHAR(100) NULL,
    main_image_id INT UNSIGNED NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_category FOREIGN KEY (category_id)
        REFERENCES product_categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_image FOREIGN KEY (main_image_id)
        REFERENCES media_assets(id) ON DELETE SET NULL,
    INDEX idx_product_public (status, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### F. `knowledge_articles` — Dairy Nutrition & Farmer Education
Manages educational feeding guides and animal husbandry insights.

```sql
CREATE TABLE knowledge_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL DEFAULT 'Dairy Nutrition',
    summary VARCHAR(300) NOT NULL,
    content_body MEDIUMTEXT NOT NULL,
    cover_image_id INT UNSIGNED NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_cover FOREIGN KEY (cover_image_id)
        REFERENCES media_assets(id) ON DELETE SET NULL,
    INDEX idx_article_public (status, display_order, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### G. `navigation_items` — Header & Footer Link Architecture
Controls order and destinations for navigation items.

```sql
CREATE TABLE navigation_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location ENUM('header', 'footer') NOT NULL DEFAULT 'header',
    label VARCHAR(50) NOT NULL,
    url VARCHAR(255) NOT NULL,
    target VARCHAR(20) NOT NULL DEFAULT '_self',
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_nav_order (location, is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### H. `audit_logs` — Administrative Accountability
Logs all admin logins, product edits, setting changes, and media deletions.

```sql
CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Referential Integrity & Deletion Rules

1. **Media Asset Deletion:** When a `media_asset` is deleted, all foreign keys referencing it (`products.main_image_id`, `knowledge_articles.cover_image_id`, `site_sections.media_id`) are set to `NULL` via `ON DELETE SET NULL`. The frontend automatically falls back to standard CSS geometric graphics or default placeholders.
2. **Category Deletion:** A `product_category` cannot be deleted if products are assigned to it (`ON DELETE RESTRICT`). Products must first be reassigned.
3. **Soft-Archive:** Products and articles support `status = 'archived'` rather than hard SQL `DELETE`, preserving audit history and URL integrity.

---

## 4. Migration & Backup Strategy

1. **Versioned Migrations:** All schema changes are tracked in numbered SQL scripts (`001_initial_schema.sql`, `002_seed_initial_data.sql`).
2. **Automated Database Backups:** Standard `mysqldump` command scheduled via server cron:
   ```bash
   mysqldump --single-transaction --quick --lock-tables=false -u shubhlabh_user -p shubhlabh_cms | gzip > /backups/db_$(date +%Y%m%d_%H%M%S).sql.gz
   ```
3. **Zero Data Loss Rollback:** Migrations feature corresponding `down` rollback scripts.
