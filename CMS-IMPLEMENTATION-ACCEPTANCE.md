# SL-WEB-11A — CMS Implementation Acceptance & Quality Assurance Contract
## Shubh Labh Website CMS Architecture

**Project:** Shubh Labh Public Website CMS  
**Document ID:** `CMS-IMPLEMENTATION-ACCEPTANCE.md`  
**Evaluation Date:** 2026-10-14  
**Sprint:** SL-WEB-11A (Architecture Review & Approval Preparation)  
**Status:** Read-Only Verification Contract — Implementation Gated  
**Canonical Directory:** `D:\ShubhLabhWebsite`  
**External CRM (Strictly Isolated):** `https://shubhlabh-crm.vercel.app/`

---

## 1. Implementation Governance & Approval Gates

The CMS rollout is governed by three strict, sequential **Approval Gates**. No subsequent micro-sprint may proceed until the designated gate conditions are formally signed off by the Product Owner.

```
+--------------------------------------------------------------------------------------------------+
|                                     CMS GOVERNANCE GATES                                         |
|                                                                                                  |
|   [SL-WEB-11A] Architecture & Schema Review                                                      |
|        |                                                                                         |
|        v                                                                                         |
|   =============================================================================                  |
|   GATE 1: ARCHITECTURE & SCHEMA APPROVAL (CURRENT GATE)                                          |
|   Condition: Product Owner sign-off on Architecture Option A & 9-table MySQL schema.             |
|   =============================================================================                  |
|        |                                                                                         |
|        +---> [SL-WEB-11B] Backend Foundation & Auth                                              |
|        +---> [SL-WEB-11C] Database Migrations & Content APIs                                     |
|        +---> [SL-WEB-11D] Admin Dashboard & Section Editor                                       |
|        +---> [SL-WEB-11E] Product Catalog & Media Library                                        |
|        +---> [SL-WEB-11F] Nutrition & Knowledge Hub                                              |
|        +---> [SL-WEB-11G] Contact, Navigation & Publishing Controls                              |
|        |                                                                                         |
|        v                                                                                         |
|   =============================================================================                  |
|   GATE 2: CMS ADMIN FUNCTIONALITY & SECURITY SIGN-OFF                                            |
|   Condition: Zero security flaws, successful CRUD verification, upload hardening confirmed.      |
|   =============================================================================                  |
|        |                                                                                         |
|        +---> [SL-WEB-11H] Public Website Integration & Offline Failover                          |
|        +---> [SL-WEB-11I] Security Penetration & Multi-Device Responsive QA                      |
|        |                                                                                         |
|        v                                                                                         |
|   =============================================================================                  |
|   GATE 3: PRE-LAUNCH PRODUCTION SIGN-OFF                                                         |
|   Condition: Database backups automated, SSL active, zero regression on static design/CRM link.  |
|   =============================================================================                  |
|        |                                                                                         |
|        v                                                                                         |
|   [SL-WEB-11J] Deployment Preparation & Controlled Launch                                        |
+--------------------------------------------------------------------------------------------------+
```

---

## 2. Micro-Sprint Dependencies & Acceptance Criteria

### SL-WEB-11B: Backend Foundation & Secure Admin Authentication
- **Prerequisites:** Formal sign-off on Gate 1 (`CMS-ARCHITECTURE-APPROVAL.md` and `CMS-SCHEMA-APPROVAL.md`).
- **Dependencies:** None.
- **Acceptance Criteria:**
  1. Directory structure initialized (`/app`, `/config`, `/public/admin`, `/public/uploads`).
  2. PDO database connection singleton configured with native prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`).
  3. First-admin provisioning tool (`cli/create_admin.php` or single-use setup token) operational without hard-coded credentials.
  4. Authentication controller handles login using `password_verify()` with `Argon2id` (or `Bcrypt` cost $\ge 12$).
  5. Session cookies enforce `HttpOnly`, `SameSite=Strict`, and `Secure` attributes.
  6. Session fixation defense: `session_regenerate_id(true)` called upon login.
  7. Rate limiter blocks authentication attempts after 5 consecutive failures for 15 minutes.
  8. Unauthenticated requests to `/admin/*` redirect cleanly to `/admin/login`.

---

### SL-WEB-11C: Database Migrations & Content Read APIs
- **Prerequisites:** SL-WEB-11B.
- **Dependencies:** Database connection manager.
- **Acceptance Criteria:**
  1. Migration runner executes `001_create_initial_schema.sql` cleanly, creating all 9 tables.
  2. Seed script `002_seed_initial_content.sql` populates baseline content from `index.html`.
  3. Migration rollback script (`down`) cleanly drops tables without foreign key deadlock.
  4. Public read endpoints operational:
     - `GET /api/public/bootstrap`
     - `GET /api/public/products` (returns only `status = 'published'`)
     - `GET /api/public/knowledge` (returns only `status = 'published'`)
     - `GET /api/public/contact` (returns verified fields; unconfigured fields return `null`).
  5. Output format strictly complies with `CMS-API-SPECIFICATION.md`. Zero exposure of `admin_users` or password hashes.

---

### SL-WEB-11D: Admin Dashboard & Section Content Editor
- **Prerequisites:** SL-WEB-11B, SL-WEB-11C.
- **Dependencies:** Admin session middleware, CSRF token validator.
- **Acceptance Criteria:**
  1. Responsive `/admin` shell renders metric cards (published counts, media storage).
  2. Section Editor interface loads existing text for Hero, Trust Strip, About Us, and Dealer sections.
  3. Form submissions require valid CSRF token; invalid/missing token returns HTTP 403.
  4. Content updates pass server-side validation and persist in `site_sections`.
  5. Every update writes a record to `audit_logs` capturing administrator ID and timestamp.

---

### SL-WEB-11E: Product Catalog Management & Hardened Media Library
- **Prerequisites:** SL-WEB-11B, SL-WEB-11C.
- **Dependencies:** Admin layout, Media uploader.
- **Acceptance Criteria:**
  1. Media Library uploader validates MIME type via `finfo` and magic bytes. Executable files (`.php`, `.phtml`, `.cgi`, etc.) are blocked.
  2. Uploaded files are renamed to UUIDv4 hashes and stored in `/uploads/`.
  3. Direct script execution in `/uploads/` is physically blocked via `.htaccess` / server configuration.
  4. Products can be created, edited, and categorized without mandatory nutritional specs.
  5. Products support draft/publish/archive lifecycle:
     - Draft products do not appear in public product API.
     - Archived products are hidden from public queries while retaining historical references.
  6. Deleting a media asset sets foreign keys in `products` to `NULL` without cascading deletions.

---

### SL-WEB-11F: Nutrition & Knowledge Management
- **Prerequisites:** SL-WEB-11D, SL-WEB-11E.
- **Dependencies:** Media Library integration.
- **Acceptance Criteria:**
  1. Educational articles can be created with title, slug, summary, category, cover image, and body.
  2. Rich-text article body is sanitized server-side; malicious elements (`<script>`, inline `onclick`, `javascript:` URIs) are stripped.
  3. Publication scheduler allows setting a future publication date; future-dated articles remain hidden from public visitors until the scheduled timestamp.
  4. Slug generation enforces unique, URL-safe values.

---

### SL-WEB-11G: Contact, Navigation, SEO & Publishing Controls
- **Prerequisites:** SL-WEB-11D.
- **Dependencies:** Site settings repository.
- **Acceptance Criteria:**
  1. Contact editor updates phone, WhatsApp, email, and address fields.
  2. Unconfigured fields display transparent *"Unconfigured — Not shown to public"* status.
  3. CRM Portal URL (`https://shubhlabh-crm.vercel.app/`) is system-locked and cannot be edited.
  4. Navigation editor allows re-ordering header and footer links.
  5. Global SEO editor allows modifying meta title, meta description, and social sharing image.
  6. Draft preview feature generates temporary signed tokens (`?preview=true&token=...`) to inspect pending drafts without making them public.

---

### SL-WEB-11H: Public Website Integration & Static Failover Fallback
- **Prerequisites:** Gate 2 Approval (SL-WEB-11B through SL-WEB-11G).
- **Dependencies:** Verified CMS backend.
- **Acceptance Criteria:**
  1. `index.php` dynamically renders content from `site_sections`, `products`, `knowledge_articles`, and `site_settings`.
  2. Public visual design, responsive breakpoints (`1050px`, `800px`, `520px`), animations, and typography remain **100% identical** to current static site.
  3. CRM Login buttons continue to open `https://shubhlabh-crm.vercel.app/` in a new tab.
  4. Disconnecting MySQL triggers the **Active Fallback Mechanism**: page serves cached HTML snapshot with zero 500 errors or visible disruptions.

---

### SL-WEB-11I: Security Audit, Penetration Testing & Multi-Device QA
- **Prerequisites:** SL-WEB-11H.
- **Dependencies:** Completed public and admin implementation.
- **Acceptance Criteria:**
  1. Automated vulnerability scan reports zero SQL injection, XSS, CSRF, or file inclusion flaws.
  2. Penetration test verifies that direct HTTP calls to admin endpoints without session cookies return HTTP 401/403.
  3. Brute force test on `/admin/login` triggers rate limiter lockout after 5 attempts.
  4. Responsive layout passes testing across mobile (320px–480px), tablet (768px–1024px), and desktop (1280px+).
  5. Google Lighthouse performance score meets $\ge 90$ on desktop and mobile.

---

### SL-WEB-11J: Deployment Preparation & Controlled Launch
- **Prerequisites:** Gate 3 Approval (SL-WEB-11I signed off).
- **Dependencies:** Hosting access and domain configuration.
- **Acceptance Criteria:**
  1. Hosting environment passes `CMS-ARCHITECTURE-APPROVAL.md` compatibility checklist.
  2. Database migrations executed and initial administrator provisioned via secure CLI token.
  3. Automated daily `mysqldump` and weekly media backup cron jobs configured and tested.
  4. SSL/TLS certificate installed; HTTP traffic automatically redirects to HTTPS.
  5. Production smoke test completed; final handoff to Product Owner.

---

## 3. Explicit Quality Assurance & Test Protocol

| Test Phase | Tooling / Methodology | Verification Scope | Target Benchmark |
| :--- | :--- | :--- | :--- |
| **SQL Injection QA** | Automated penetration payloads on all search and ID parameters. | Verify that all dynamic values execute via PDO prepared parameters. | Zero raw SQL string interpolations. |
| **XSS & Output QA** | Form fuzzing with `<script>`, `<svg/onload>`, `javascript:` payloads. | Verify `htmlspecialchars()` escaping on templates and HTML sanitization on article bodies. | Zero stored or reflected script execution. |
| **Upload Pipeline QA** | Attempt upload of `shell.php`, `image.php.jpg`, SVG with embedded script. | Verify MIME sniffing via `finfo`, UUID renaming, and `.htaccess` execution denial. | 100% executable files blocked. |
| **Failover QA** | Simulate MySQL service interruption or socket disconnect. | Verify `index.php` serves cached static baseline. | HTTP 200 returned; zero customer error banners. |
| **CRM Isolation QA** | Inspect DOM and network requests for all CRM login interactions. | Verify target URL is strictly `https://shubhlabh-crm.vercel.app/` with `rel="noopener noreferrer"`. | Zero CRM database/auth cross-talk. |
| **Responsive QA** | Device emulation (iPhone SE, Pixel 7, iPad Air, 1080p Desktop). | Verify navigation drawer, table overflows, typography scaling, and card layouts. | Zero layout breakage or horizontal scrollbars. |
