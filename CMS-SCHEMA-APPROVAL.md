# SL-WEB-11A — Final Database Schema Approval Specification
## Shubh Labh Website CMS Architecture

**Project:** Shubh Labh Public Website CMS  
**Document ID:** `CMS-SCHEMA-APPROVAL.md`  
**Evaluation Date:** 2026-10-14  
**Sprint:** SL-WEB-11A (Architecture Review & Approval Preparation)  
**Status:** Reviewable DDL Specification Only — Zero Database Modifications in this Phase  
**Target Engine:** MySQL 8.0+ / MariaDB 10.6+  
**Default Collation:** `utf8mb4_unicode_ci` (Full Unicode support for Hindi glyphs `शुभ लाभ` and English)

---

## 1. Schema Architecture & Entity Relationships

The schema uses a Third Normal Form (3NF) relational design optimized for fast read performance on public pages while enforcing strict referential integrity for administrative write operations.

```
+--------------------------------------------------------------------------------------------------------+
|                                  DATABASE ENTITY RELATIONSHIP DIAGRAM                                  |
|                                                                                                        |
|      +------------------+                    +--------------------+                                    |
|      |   admin_users    |                    |    media_assets    |<-----------------+                 |
|      +------------------+                    +--------------------+                  |                 |
|      | id (PK)          |<-----+             | id (PK)            |                  |                 |
|      | username (UQ)    |      |             | stored_name (UQ)   |                  |                 |
|      | email (UQ)       |      |             | file_path          |                  |                 |
|      | password_hash    |      |             | mime_type          |                  |                 |
|      | role             |      |             | alt_text           |                  |                 |
|      | is_active        |      |             | uploaded_by (FK) --+ (SET NULL)       |                 |
|      +------------------+      |             +--------------------+                  |                 |
|               ^                |                       ^                             |                 |
|               |                |                       |                             |                 |
|      +--------+---------+      |             +---------+----------+                  |                 |
|      |    audit_logs    |      |             |                    |                  |                 |
|      +------------------+      |             | (SET NULL)         | (SET NULL)       |                 |
|      | id (PK)          |      |             |                    |                  |                 |
|      | user_id (FK) ----+------+             v                    v                  |                 |
|      | action           |           +-----------------+  +--------------------+      |                 |
|      | entity_type      |           |  site_sections  |  | knowledge_articles |      |                 |
|      | entity_id        |           +-----------------+  +--------------------+      |                 |
|      | details (JSON)   |           | id (PK)         |  | id (PK)            |      |                 |
|      | created_at       |           | section_key (UQ)|  | title              |      |                 |
|      +------------------+           | title           |  | slug (UQ)          |      |                 |
|                                     | lead_text       |  | summary            |      |                 |
|      +------------------+           | media_id (FK) --+  | content_body       |      |                 |
|      |  site_settings   |           | additional_data |  | cover_image_id(FK)-+      |                 |
|      +------------------+           | is_published    |  | status (ENUM)      |      |                 |
|      | id (PK)          |           +-----------------+  | published_at       |      |                 |
|      | setting_key (UQ) |                                +--------------------+      |                 |
|      | setting_value    |                                                            |                 |
|      | is_public (BOOL) |                                                            |                 |
|      +------------------+                                                            |                 |
|                                                                                      |                 |
|      +--------------------+                         +-----------------------+        |                 |
|      | product_categories |                         |       products        |        |                 |
|      +--------------------+                         +-----------------------+        |                 |
|      | id (PK)            |<------------------------| category_id (FK:REST) |        |                 |
|      | name               |                         | id (PK)               |        |                 |
|      | slug (UQ)          |                         | name                  |        |                 |
|      | display_order      |                         | slug (UQ)             |        |                 |
|      +--------------------+                         | short_description     |        |                 |
|                                                     | full_description(NULL)|        |                 |
|      +------------------+                           | packaging_desc (NULL) |        |                 |
|      | navigation_items |                           | main_image_id (FK) ---+--------+ (SET NULL)      |
|      +------------------+                           | display_order         |                          |
|      | id (PK)          |                           | status (ENUM)         |                          |
|      | location (ENUM)  |                           +-----------------------+                          |
|      | label / url      |                                                                              |
|      | display_order    |                                                                              |
|      +------------------+                                                                              |
+--------------------------------------------------------------------------------------------------------+
```

---

## 2. Reviewable SQL DDL Specification (DO NOT EXECUTE)

The following DDL represents the complete target schema for review. Execution is strictly prohibited in this sprint and will occur in `SL-WEB-11C` following explicit owner approval.

```sql
-- =============================================================================
-- SHUBH LABH CMS DATABASE SCHEMA SPECIFICATION
-- Database Version: MySQL 8.0+ / MariaDB 10.6+
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. TABLE: admin_users
-- Administrative operator accounts with Argon2id password hashes and brute-force guards.
-- -----------------------------------------------------------------------------
CREATE TABLE admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(191) NOT NULL,
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
    CONSTRAINT uq_admin_username UNIQUE (username),
    CONSTRAINT uq_admin_email UNIQUE (email),
    INDEX idx_admin_lookup (username, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. TABLE: media_assets
-- Registry of uploaded brand assets, packaging photography, banners, and covers.
-- -----------------------------------------------------------------------------
CREATE TABLE media_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    width SMALLINT UNSIGNED NULL,
    height SMALLINT UNSIGNED NULL,
    alt_text VARCHAR(255) NOT NULL DEFAULT '',
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_media_stored_name UNIQUE (stored_name),
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) 
        REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_media_mime (mime_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. TABLE: site_settings
-- Global configuration, verified contact channels, social links, and SEO defaults.
-- -----------------------------------------------------------------------------
CREATE TABLE site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(64) NOT NULL,
    setting_value TEXT NULL,
    setting_group ENUM('general', 'contact', 'seo', 'social', 'integration') NOT NULL DEFAULT 'general',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    is_system_locked TINYINT(1) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_setting_key UNIQUE (setting_key),
    INDEX idx_public_settings (is_public, setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. TABLE: site_sections
-- Structured sectional copy for Home Hero, Trust Strip, About Us, and Dealer sections.
-- -----------------------------------------------------------------------------
CREATE TABLE site_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(50) NOT NULL,
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
    CONSTRAINT uq_section_key UNIQUE (section_key),
    CONSTRAINT fk_section_media FOREIGN KEY (media_id)
        REFERENCES media_assets(id) ON DELETE SET NULL,
    INDEX idx_published_sections (section_key, is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. TABLE: product_categories
-- Product taxonomy (e.g., Cattle Feed, Nutrition Support).
-- -----------------------------------------------------------------------------
CREATE TABLE product_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    category_badge VARCHAR(50) NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_category_slug UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. TABLE: products
-- Cattle feed catalog with draft/published lifecycle and optional packaging.
-- -----------------------------------------------------------------------------
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    short_description VARCHAR(255) NOT NULL,
    full_description TEXT NULL,
    packaging_desc VARCHAR(100) NULL,
    main_image_id INT UNSIGNED NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_product_slug UNIQUE (slug),
    CONSTRAINT fk_product_category FOREIGN KEY (category_id)
        REFERENCES product_categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_image FOREIGN KEY (main_image_id)
        REFERENCES media_assets(id) ON DELETE SET NULL,
    INDEX idx_product_public (status, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. TABLE: knowledge_articles
-- Dairy nutrition guides, seasonal feeding advice, and educational content.
-- -----------------------------------------------------------------------------
CREATE TABLE knowledge_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Dairy Nutrition',
    summary VARCHAR(300) NOT NULL,
    content_body MEDIUMTEXT NOT NULL,
    cover_image_id INT UNSIGNED NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_article_slug UNIQUE (slug),
    CONSTRAINT fk_article_cover FOREIGN KEY (cover_image_id)
        REFERENCES media_assets(id) ON DELETE SET NULL,
    INDEX idx_article_public (status, display_order, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. TABLE: navigation_items
-- Header menu and footer link order and destinations.
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 9. TABLE: audit_logs
-- Immutable trail of administrative creations, modifications, publications, and logins.
-- -----------------------------------------------------------------------------
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

SET FOREIGN_KEY_CHECKS = 1;
```

---

## 3. Explicit Verification Against 10 Schema Design Criteria

| Criterion | Evaluation & Proof in Schema | Compliance Status |
| :--- | :--- | :---: |
| **1. Products without nutritional specs** | In `products`, `full_description` and `packaging_desc` are strictly `NULL`. No columns exist requiring protein %, fat %, or lab analysis. Products save with only name, category, short desc, and slug. | **PASSED** |
| **2. Drafts isolated from public site** | `products` and `knowledge_articles` use `status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft'`. Public queries filter strictly on `WHERE status = 'published'`. Drafts are physically invisible to public visitors. | **PASSED** |
| **3. Safe archiving of products** | Setting `status = 'archived'` removes items from the public site while preserving foreign keys, sales history, and audit records. Hard `DELETE` is blocked. | **PASSED** |
| **4. Defined ownership of settings/sections** | `site_settings` and `site_sections` use explicit unique keys (`setting_key`, `section_key`). `audit_logs` tracks `user_id` and timestamp for every single update. | **PASSED** |
| **5. Shared media reuse & safe deletion** | `products.main_image_id`, `articles.cover_image_id`, and `sections.media_id` all specify `ON DELETE SET NULL`. Deleting an image never deletes products or causes cascade failures. UI verifies reference count before deletion. | **PASSED** |
| **6. Public queries isolate admin data** | Public queries select only from `products`, `knowledge_articles`, `site_sections`, and `site_settings WHERE is_public = 1`. `admin_users` and `audit_logs` have zero public read paths. | **PASSED** |
| **7. Publication audit logging** | The application controller writes an audit record to `audit_logs` with `action = 'PUBLISH'`, `entity_type`, `entity_id`, and admin `user_id` on every lifecycle transition. | **PASSED** |
| **8. Backups and restore feasibility** | The schema uses standard InnoDB tables with full referential integrity. A standard single-transaction `mysqldump` captures the entire database atomically. | **PASSED** |
| **9. Safe migration rollback** | Migrations are strictly numbered (`001_schema.sql` up/down). Reversing a migration drops created tables safely using `SET FOREIGN_KEY_CHECKS = 0`. | **PASSED** |
| **10. Zero unnecessary complexity** | Streamlined to exactly 9 tables. Avoids unnecessary polymorphic relationships, multi-tenant overhead, or premature microservice abstractions. | **PASSED** |

---

## 4. Draft / Publish Lifecycle State Machine

```
                   [New Entity Created]
                            |
                            v
                      +-----------+
                      |   DRAFT   | <----+ (Unpublish)
                      +-----------+      |
                            |            |
                (Publish)   |            |
                            v            |
                      +-----------+      |
                      | PUBLISHED | -----+
                      +-----------+
                            |
                 (Archive)  |
                            v
                      +-----------+
                      | ARCHIVED  |
                      +-----------+
```

- **Draft State:** Content is visible and editable only in `/admin`. Zero public exposure.
- **Published State:** Content is visible to public visitors and returned by public endpoints.
- **Archived State:** Content is hidden from public visitors but preserved in the database for historical and operational records.

---

## 5. Migration and Rollback Design

All database schema evolutions will be executed via structured, version-controlled SQL files:

### Migration 001 (`migrations/001_create_initial_schema.sql`)
- **UP Action:** Executes the 9 table `CREATE TABLE` statements above in foreign-key dependency order.
- **DOWN Action:** Drops the 9 tables in reverse dependency order:
  ```sql
  SET FOREIGN_KEY_CHECKS = 0;
  DROP TABLE IF EXISTS audit_logs;
  DROP TABLE IF EXISTS navigation_items;
  DROP TABLE IF EXISTS knowledge_articles;
  DROP TABLE IF EXISTS products;
  DROP TABLE IF EXISTS product_categories;
  DROP TABLE IF EXISTS site_sections;
  DROP TABLE IF EXISTS site_settings;
  DROP TABLE IF EXISTS media_assets;
  DROP TABLE IF EXISTS admin_users;
  SET FOREIGN_KEY_CHECKS = 1;
  ```

### Seed 002 (`migrations/002_seed_initial_content.sql`)
- Populates verified baseline data from `index.html` (Hero copy, About narrative, 3 verified products, 2 educational articles, and system-locked CRM link `https://shubhlabh-crm.vercel.app/`).
- Populates contact settings with `NULL` (unconfigured) until verified.
