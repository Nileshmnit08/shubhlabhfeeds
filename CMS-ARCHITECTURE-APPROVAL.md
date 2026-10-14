# SL-WEB-11A — Final Architecture & Hosting Approval Specification
## Shubh Labh Website CMS Architecture

**Project:** Shubh Labh Public Website CMS  
**Document ID:** `CMS-ARCHITECTURE-APPROVAL.md`  
**Evaluation Date:** 2026-10-14  
**Sprint:** SL-WEB-11A (Architecture Review & Approval Preparation)  
**Status:** Read-Only Review & Implementation-Ready Proposal  
**Canonical Directory:** `D:\ShubhLabhWebsite`  
**GitHub Repository:** `https://github.com/Nileshmnit08/shubhlabhfeeds`  
**External CRM (Strictly Isolated):** `https://shubhlabh-crm.vercel.app/`

---

## 1. Executive Architecture Decision

### 1.1 Recommended Architecture: Option A (Unified PHP 8.2+ & MySQL Deployment)
Following a comprehensive audit of the website's static codebase ([index.html](file:///D:/ShubhLabhWebsite/index.html), [styles.css](file:///D:/ShubhLabhWebsite/styles.css), [script.js](file:///D:/ShubhLabhWebsite/script.js)), business operational profile, and performance requirements, **Option A (Unified PHP 8.2+ and MySQL on compatible shared or virtual private hosting)** is recommended for implementation.

```
+--------------------------------------------------------------------------------------------------+
|                      RECOMMENDED ARCHITECTURE: UNIFIED PHP & MYSQL (OPTION A)                    |
|                                                                                                  |
|   PUBLIC VISITORS & SEARCH CRAWLERS                  CMS OPERATORS / EDITORS                     |
|                |                                                |                                |
|                | HTTPS (Port 443)                               | HTTPS (Port 443)               |
|                v                                                v                                |
|   +------------------------------------------------------------------------------------------+   |
|   |                      COMMERCIAL WEB HOSTING (APACHE / NGINX)                             |   |
|   |                                                                                          |   |
|   |   +---------------------------------------+  +---------------------------------------+   |   |
|   |   |      PUBLIC FRONT CONTROLLER          |  |       ADMIN CMS PORTAL (/admin)       |   |   |
|   |   |  - index.php                          |  |  - Dashboard & Section Editors        |   |   |
|   |   |  - Server-Side Rendered (Zero CLS)    |  |  - Product & Knowledge CRUD           |   |   |
|   |   |  - Serves static CSS/JS unchanged     |  |  - Hardened Media Uploader            |   |   |
|   |   |  - Offline Static Fallback Cache      |  |  - Auth Middleware & CSRF Guards      |   |   |
|   |   +---------------------------------------+  +---------------------------------------+   |   |
|   |                       |                                          |                       |   |
|   |                       +--------------------+---------------------+                       |   |
|   |                                            |                                             |   |
|   |                                            v (Local Unix Socket / 127.0.0.1:3306)        |   |
|   |                               +-------------------------+                                |   |
|   |                               |      MYSQL DATABASE     |                                |   |
|   |                               |  - InnoDB, utf8mb4      |                                |   |
|   |                               |  - 3NF Normalized       |                                |   |
|   |                               +-------------------------+                                |   |
|   |                                            |                                             |   |
|   |                                            v                                             |   |
|   |                               +-------------------------+                                |   |
|   |                               |     UPLOADS STORAGE     |                                |   |
|   |                               |  - /uploads/ (Images)   |                                |   |
|   |                               |  - Script Exec BLOCKED  |                                |   |
|   |                               +-------------------------+                                |   |
|   +------------------------------------------------------------------------------------------+   |
|                                                                                                  |
|   EXTERNAL DEPENDENCY (ISOLATED):                                                                |
|   - CRM Portal Button: Strictly targets https://shubhlabh-crm.vercel.app/ in new tab.            |
|   - Zero shared databases, zero API cross-talk, zero Supabase configuration sharing.             |
+--------------------------------------------------------------------------------------------------+
```

### 1.2 Alternatives Evaluated and Tradeoff Matrix

| Evaluation Dimension | Option A: Unified PHP 8.2+ & MySQL (Recommended) | Option B: Decoupled Static Edge + Headless PHP API | Option C: Pure Static (Current Baseline) |
| :--- | :--- | :--- | :--- |
| **Operating Cost** | **Lowest ($2.50 – $6/mo):** Single commercial hosting plan (Hostinger, cPanel, Cloudways, Hetzner). | **Moderate ($10 – $25/mo):** Dual hosting tiers (Vercel/Cloudflare Pages + separate PHP/MySQL backend host + S3 storage). | **Free ($0/mo):** Cloudflare Pages or Vercel static hosting. |
| **Content Autonomy** | **Complete:** Business owner updates products, articles, phone, and hero text directly in `/admin`. | **Complete:** Business owner updates via headless API panel. | **None:** Requires code modification, Git commits, and redeployment for every comma change. |
| **SEO & Crawlability** | **Optimal:** 100% server-side rendered HTML sent to Googlebot/Bingbot on initial packet. | **Sub-Optimal:** Client-side JavaScript hydration waterfall; risks empty previews if API lags. | **Optimal:** Fast static HTML, but fixed content. |
| **Rural Mobile Latency** | **Instant First Paint (< 0.6s):** Single round-trip HTML response; no client-side API round trips. | **Delayed First Paint (1.8s – 3.5s):** Skeleton cards while browser performs secondary `fetch()` requests over 3G/4G. | **Instant First Paint (< 0.5s)**. |
| **Operational Simplicity** | **High:** One codebase, one deployment destination, unified database and media backup in single tarball. | **Low:** Two environments, CORS headers, pre-flight requests, separate staging, synchronized releases. | **High:** Static files only. |
| **Failure Mode** | **Graceful:** Automatic failover to static cached baseline if MySQL socket times out. | **Fragmented:** Frontend displays error banner or blank grid if API endpoint is unreachable. | Site never goes down; cannot be dynamically updated. |

---

## 2. Technical Evaluation Against Website Realities

### 2.1 Preserving Existing Frontend Design & Behavioral Assets
The existing static frontend represents an approved visual design system featuring:
- High-contrast corporate palette (`--forest-900: #143d28`, `--brand-green: #1b5e20`, `--gold-500: #d4a373`).
- 6 mandatory sections: **Home (`#home`)**, **Products (`#products`)**, **About (`#about`)**, **Nutrition & Knowledge (`#knowledge`)**, **Become a Dealer (`#dealer`)**, and **Contact (`#contact`)**.
- Responsive breakpoints at `1050px`, `800px`, and `520px` in [styles.css](file:///D:/ShubhLabhWebsite/styles.css).
- Vanilla JavaScript in [script.js](file:///D:/ShubhLabhWebsite/script.js) managing scroll observers, sticky header compression, and mobile menu toggles.

**Execution in Option A:**
`index.php` acts as a server-side pre-compiler that consumes the exact markup of `index.html`. It dynamically injects CMS database values into approved content placeholders while preserving 100% of the CSS class hierarchy, DOM IDs, and JavaScript event bindings. The visual output is completely indistinguishable from the static original.

### 2.2 Offline Resilience & Database Failover Protocol
A critical vulnerability of database-driven websites is database downtime (e.g., during host maintenance or restart). Option A introduces an **Active Fallback Mechanism**:
1. When `index.php` initializes, it establishes a PDO connection with a strict 2-second timeout.
2. If the connection succeeds, it renders live CMS content and writes a sanitized static snapshot to `/cache/page_cache.html`.
3. If the connection fails (e.g. `PDOException`), the application does **not** output a fatal error or blank screen. It instantly logs the incident, falls back to `/cache/page_cache.html` (or the baseline `index.html`), and serves visitors seamlessly.

### 2.3 Strict CRM Isolation Boundary
The Shubh Labh CRM hosted at `https://shubhlabh-crm.vercel.app/` is a completely autonomous operational system. 
- The CMS has **zero** connection strings, zero Supabase credentials, and zero API integrations with the CRM.
- The CRM Login link in the header and contact section is hard-coded at the configuration level as an immutable constant:
  ```php
  define('CRM_LOGIN_URL', 'https://shubhlabh-crm.vercel.app/');
  ```
- It cannot be modified, edited, or overwritten from the CMS administrative interface.

---

## 3. Hosting Compatibility & Verification Checklist

To confirm compatibility before deploying the unified architecture, the target hosting environment must satisfy this checklist.

| Technical Requirement | Minimum Specification | Recommended Specification | Status / Verification Method |
| :--- | :--- | :--- | :--- |
| **PHP Runtime** | PHP 8.2.0+ | PHP 8.2 or 8.3 | **PENDING HOST SELECTION** (`php -v`) |
| **Database Engine** | MySQL 8.0+ or MariaDB 10.6+ | MySQL 8.0.35+ | **PENDING HOST SELECTION** (`mysql -V`) |
| **Database Extensions** | `pdo_mysql`, `mysqli` | `pdo_mysql` enabled | **PENDING HOST SELECTION** (`php -m`) |
| **Security Extensions** | `fileinfo`, `openssl`, `mbstring` | Enabled | **PENDING HOST SELECTION** (`php -m`) |
| **Password Hashing** | `ext-sodium` or Bcrypt support | Argon2id (`PASSWORD_ARGON2ID`) | **PENDING HOST SELECTION** (`password_algos()`) |
| **Web Server** | Apache 2.4+ (with `mod_rewrite`) or Nginx | Apache with `.htaccess` support | **PENDING HOST SELECTION** |
| **HTTPS / TLS** | TLS 1.2 or TLS 1.3 | Automated Let's Encrypt SSL | **PENDING DOMAIN ASSIGNMENT** |
| **Session Security** | Supports `SameSite`, `HttpOnly`, `Secure` | Enabled in `php.ini` | **PENDING HOST SELECTION** |
| **File Upload Directives** | `upload_max_filesize >= 10M`, `post_max_size >= 12M` | 10 MB limit | **PENDING HOST SELECTION** |
| **Directory Configuration** | Ability to place app core above `public_html/` | Document root mapping | **PENDING HOST SELECTION** |
| **Scheduled Backups** | Cron job runner or host automated snapshot | Daily `mysqldump` | **PENDING HOST SELECTION** |

*Note: All items marked **PENDING HOST SELECTION** cannot be empirically validated until the Product Owner selects and provides credentials for the hosting environment. No hosting has been provisioned during this read-only sprint.*

---

## 4. First-Administrator Provisioning Protocol

Because the initial administrator email address has **not been provided**, the CMS must strictly avoid insecure defaults:
- **Forbidden:** No hardcoded admin credentials in code (e.g., `admin / admin123`).
- **Forbidden:** No open, public `/admin/register` endpoint on the web.
- **Approved Provisioning Mechanism:**
  When database migrations are run during Sprint `SL-WEB-11B`, the setup script executes an interactive CLI provisioning tool or generates a single-use, cryptographically signed setup token written to a secure server-side file (`/private/setup_token.txt`):
  ```
  CLI Command: php cli/create_admin.php --email="owner@domain.com"
  -- OR --
  Single-Use Setup URL: https://[domain]/admin/setup?token=7f9a2e... (expires in 1 hour)
  ```
  This guarantees that administrative access can only be initialized by an operator with direct server or hosting control panel access.

---

## 5. Outstanding Owner Decisions

The following business and infrastructure parameters remain pending Product Owner resolution:

1. **Hosting Provider Choice:** Confirmation of shared or VPS hosting environment supporting PHP 8.2+ and MySQL 8.0+.
2. **Initial Administrator Identity:** Delivery of the official administrator email address for the initial super-admin account.
3. **Official Contact Data:** Verification of official customer care phone number, WhatsApp Business phone number, and physical factory address.
4. **Product Packaging Photography:** Provision of authentic commercial packaging photography for *Dairy Special*, *Performance Premix*, and *Specialised Nutrition*.
