# SL-WEB-11 — CMS Admin Specification & Implementation Roadmap
## Shubh Labh Website CMS Architecture

**Project:** Shubh Labh Public Website CMS  
**Document ID:** `CMS-IMPLEMENTATION-ROADMAP.md`  
**Status:** Audit & Planning Draft (Read-Only Blueprint)  
**Target Environment:** PHP 8.2+ / MySQL 8.0+ / Apache or Nginx  
**Scope:** Admin panel module specifications (Phase C) and phased micro-sprint roadmap (Phase G).

---

## PART 1 — CMS ADMIN PANEL SPECIFICATION (Phase C)

The `/admin` panel is a lightweight, responsive, accessible, and secure administrative web interface designed to allow Shubh Labh operators to update website content without writing HTML or code.

```
+-------------------------------------------------------------------------------+
|                       SHUBH LABH CMS ADMIN NAVIGATION                         |
|  [Logo] Shubh Labh Admin   | User: admin@shubhlabhfeeds.com | [View Site] [Logout]
+-------------------------------------------------------------------------------+
| [1. Dashboard]       | Overview, content counts, quick action cards           |
| [2. Website Content] | Hero, About Us, Section headers, Trust strip, CTAs     |
| [3. Products]        | Cattle feed items, categories, packaging, status       |
| [4. Knowledge Hub]   | Nutrition articles, guides, categories, SEO tags       |
| [5. Media Library]   | Image uploads, alt text, safe deletion, storage stats  |
| [6. Contact & Biz]   | Official phone, WhatsApp, email, address, CRM links   |
| [7. Navigation/Menu] | Menu item labels, ordering, footer links & copyright   |
| [8. Publish & Audit] | Draft previews, revision diffs, publication log        |
+-------------------------------------------------------------------------------+
```

---

### Module 1: Admin Dashboard
- **Purpose:** Centralized operational hub displaying website health and content inventory.
- **Key Features:**
  - **Metric Cards:**
    - Published Products count vs. Draft count.
    - Published Articles count vs. Draft count.
    - Total Media Assets stored and total disk usage (MB).
    - Last website content publication timestamp.
  - **Quick Action Buttons:**
    - "New Product", "New Article", "Upload Media", "Edit Contact Info".
  - **Recent Activity Feed:** Last 10 administrative actions from `audit_logs` (e.g. *Admin updated Hero Heading at 10:14 AM*).
  - **System Health Monitor:** Database connectivity status, upload directory permissions check, and PHP version verification.

---

### Module 2: Website Content Editor
- **Purpose:** Manage copy and imagery for non-dynamic structural sections of the public website.
- **Key Sections Managed:**
  1. **Announcement Bar:**
     - Toggle visibility (on/off).
     - Text copy.
     - Optional link URL and link label.
  2. **Hero Section:**
     - Badge text (e.g., *"Premium Cattle Nutrition"*).
     - Hero Headline (supporting emphasized words).
     - Hero Subtitle / value proposition paragraph.
     - Primary CTA button label and anchor/URL.
     - Secondary CTA button label and anchor/URL.
     - Hero Background Image (Media Library selector).
  3. **Trust Strip / Metrics:**
     - Metric stat 1, 2, 3, 4 (value + label, e.g. *"100% Balanced Feed"*, *"Lab Tested"*).
  4. **About Shubh Labh:**
     - Section eyebrow, title, and descriptive body text.
     - Value proposition cards (titles, descriptions, icons).
     - Feature image selector.
  5. **Become a Dealer Section:**
     - Dealer section headline and descriptive copy.
     - Benefits bullet points.
     - Enquiry CTA button text and destination.
  6. **SEO & Meta Settings:**
     - Default Meta Title (with character counter, recommended 50-60 chars).
     - Default Meta Description (with character counter, recommended 150-160 chars).
     - OpenGraph sharing image selector.

---

### Module 3: Product Management
- **Purpose:** Full CRUD operations for cattle feed products and categories.
- **Field Specifications:**
  - **Product Name:** Required text input (e.g. *"Shubh Labh Balanced Dairy Ration"*).
  - **Slug:** Auto-generated from name with uniqueness check against `products` table.
  - **Category:** Dropdown selector linking to `product_categories` (e.g. *Lactating Cow Feed, Dry Cow Feed, Calf Starter*).
  - **Short Description:** Text area (max 300 characters) displayed on product cards.
  - **Full Description:** Rich-text or markdown editor for extended nutritional details and feeding instructions.
  - **Primary Image:** Media Library selector (JPEG/PNG/WebP).
  - **Additional Images:** Multi-image gallery selector.
  - **Packaging Options:** Structured list (e.g., `["25kg Bag", "50kg Woven Poly Bag"]`).
  - **Display Order:** Integer for custom sorting on the public page.
  - **Publication Status:** `draft` | `published` | `archived`.
  - **Important Guardrail:** Product specifications (protein %, fat %, fiber %) are strictly optional and must never be auto-generated or faked. If unverified, fields remain blank.

---

### Module 4: Nutrition & Knowledge Hub
- **Purpose:** Editorial publishing system for farmer education, feeding guides, and dairy nutrition articles.
- **Field Specifications:**
  - **Article Title:** Required text.
  - **Slug:** Auto-generated, URL-safe, unique.
  - **Category:** Categorization (e.g. *Dairy Health, Ration Balancing, Seasonal Care*).
  - **Summary / Excerpt:** Short summary for grid cards (max 250 characters).
  - **Article Body:** Safe rich-text editor (headings, paragraphs, bullet lists, blockquotes, images).
  - **Cover Image:** Media Library selector with required Alt text.
  - **Author Name:** Operator name or default *"Shubh Labh Technical Team"*.
  - **Publication Date:** Datepicker with scheduling capability (publish immediately or set future date).
  - **SEO Metadata:** Custom page title, canonical tag, and meta description per article.
  - **Publication Status:** `draft` | `published` | `archived`.

---

### Module 5: Media Library
- **Purpose:** Centralized repository for all imagery used across the public website.
- **Capabilities:**
  - **Drag-and-Drop Uploader:** Supports bulk uploads with immediate client-side and server-side validation.
  - **Accepted File Types:** `.jpg`, `.jpeg`, `.png`, `.webp`, `.svg`.
  - **File Size Caps:** Max 5 MB for bitmap images, max 1 MB for SVGs.
  - **Sanitization & Renaming:** Original names stripped; files saved as random UUIDv4 hashes.
  - **Image Processing:** Automatic generation of optimized WebP thumbnail previews upon upload.
  - **Metadata Management:** Alt text editor (required for accessibility compliance before insertion into content).
  - **Usage Tracking:** Displays which section, product, or article is currently referencing a given media asset.
  - **Safe Deletion:** Deleting an image checks for active foreign key references; warns the administrator if the image is currently in use.

---

### Module 6: Contact & Business Settings
- **Purpose:** Single source of truth for business identity, customer support channels, and dealer dispatch.
- **Editable Elements:**
  - **Legal Entity Name:** (e.g. *Shubh Labh Feeds*).
  - **Official Telephone:** Public telephone number (validates phone format).
  - **WhatsApp Business Number:** WhatsApp contact with click-to-chat prefilled text.
  - **Public Email Address:** General enquiry email address.
  - **Physical / Factory Address:** Multi-line postal address.
  - **Social Links:** Facebook, YouTube, LinkedIn, Instagram URLs.
  - **Dealer Enquiry Action:** Email destination address or external form URL for incoming dealer leads.
  - **CRM Destination URL:** Fixed link to `https://shubhlabh-crm.vercel.app/` (locked/read-only to prevent tampering).
  - **Unconfigured Field Policy:** Any unverified contact detail displays a prominent *"Unconfigured - Not shown to public"* indicator. Never generate mock phone numbers or placeholder addresses.

---

### Module 7: Navigation & Footer Editor
- **Purpose:** Control header navigation ordering and footer legal text.
- **Capabilities:**
  - Re-order top navigation links via drag-and-drop.
  - Edit navigation labels (e.g. change *"Products"* to *"Our Feeds"*).
  - Toggle visibility of specific sections.
  - Footer copyright notice and disclaimer text.
  - Footer secondary links (e.g. Privacy Policy, Terms of Supply).

---

### Module 8: Preview, Publishing & Destructive Action Safeguards
- **Draft Preview:**
  - Administrators can preview pending drafts on the actual public frontend layout using a temporary signed session token (`?preview=true&token=...`).
  - Draft content is never displayed to public visitors.
- **Publishing Workflow:**
  - Single-click "Publish Changes" button compiles and promotes draft state to live.
  - Validation check runs before publishing (verifies required fields, alt text, and valid URLs).
- **Destructive Action Protection:**
  - Deleting products, articles, or media requires typing the entity name or confirming via a modal dialogue.
  - Soft-deletes / archiving preferred over hard deletion to prevent accidental loss.

---

## PART 2 — MICRO-SPRINT ROADMAP (Phase G)

The CMS implementation is structured into 10 controlled micro-sprints (`SL-WEB-11A` through `SL-WEB-11J`). Each sprint is self-contained with explicit dependencies and objective acceptance criteria.

```
+---------------------------------------------------------------------------------------+
|                              CMS IMPLEMENTATION ROADMAP                               |
|                                                                                       |
|  [SL-WEB-11A] Architecture & Schema Approval (GATE 1)                                 |
|        |                                                                              |
|        v                                                                              |
|  [SL-WEB-11B] Backend Foundation & Auth  ---> [SL-WEB-11C] Database Migrations & APIs |
|        |                                              |                               |
|        v                                              v                               |
|  [SL-WEB-11D] Admin Dashboard & Content  ---> [SL-WEB-11E] Products & Media Library   |
|        |                                              |                               |
|        v                                              v                               |
|  [SL-WEB-11F] Nutrition Hub Management   ---> [SL-WEB-11G] Contact, Nav & SEO         |
|        |                                              |                               |
|        +----------------------+-----------------------+                               |
|                               |                                                       |
|                               v                                                       |
|  [SL-WEB-11H] Public Website Integration                                              |
|                               |                                                       |
|                               v                                                       |
|  [SL-WEB-11I] Security Hardening & Regression Testing                                 |
|                               |                                                       |
|                               v                                                       |
|  [SL-WEB-11J] Deployment Preparation & Controlled Launch (FINAL GATE)                  |
+---------------------------------------------------------------------------------------+
```

---

### Sprint SL-WEB-11A — CMS Architecture and Schema Approval
- **Goal:** Formal sign-off on architecture option, normalized MySQL schema, security plan, and roadmap.
- **Dependencies:** Delivery of all five blueprint documents.
- **Acceptance Criteria:**
  1. Product Owner approves the selection of Architecture Option A (Unified PHP 8.2+ & MySQL on standard hosting).
  2. Database schema approved without unverified product fields.
  3. Security controls and backup strategy verified.
  4. Permission granted to begin backend scaffolding.

---

### Sprint SL-WEB-11B — Backend Foundation and Secure Admin Authentication
- **Goal:** Scaffolding the backend runtime directory structure, database connection manager, and session authentication.
- **Dependencies:** SL-WEB-11A approval.
- **Key Deliverables:**
  - Directory structure (`/admin`, `/api`, `/config`, `/uploads`, `/migrations`).
  - PDO database wrapper with connection pooling and error handling.
  - Argon2id password hashing implementation.
  - Secure login controller at `/admin/login` with brute-force rate-limiting (5 attempts / 15 min).
  - Session regeneration on login, `HttpOnly`/`SameSite=Strict`/`Secure` cookie headers.
  - CSRF token generator and validation middleware.
  - Clean logout handler.
- **Acceptance Criteria:**
  1. Admin user can securely log in and log out.
  2. Failed login attempts increment rate limiter; 5th failure triggers 15-minute lock.
  3. Direct access to `/admin/dashboard` without session redirects to `/admin/login`.
  4. Session ID changes upon successful authentication.

---

### Sprint SL-WEB-11C — Database Migrations and Content APIs
- **Goal:** Execute automated schema migrations and build foundational JSON read APIs.
- **Dependencies:** SL-WEB-11B.
- **Key Deliverables:**
  - Migration runner (`migrate.php`) executing SQL scripts in transactional order.
  - Seed script creating default initial admin account, site sections, and baseline static content.
  - Read-only public API endpoints:
    - `GET /api/public/bootstrap`
    - `GET /api/public/products`
    - `GET /api/public/knowledge`
    - `GET /api/public/contact`
  - JSON response standardizer with ETags and caching headers.
- **Acceptance Criteria:**
  1. Running migration creates all 9 tables with proper indexes and foreign keys.
  2. Public API returns valid JSON matching `CMS-API-SPECIFICATION.md`.
  3. No administrative or password data is exposed in public API outputs.

---

### Sprint SL-WEB-11D — Admin Dashboard and Website Content Editor
- **Goal:** Build the visual `/admin` interface for general website copy and section management.
- **Dependencies:** SL-WEB-11B, SL-WEB-11C.
- **Key Deliverables:**
  - Admin shell layout with responsive sidebar, header, and flash notification system.
  - Dashboard overview page with live count cards and recent activity logs.
  - Website Content Editor interface for Hero, Trust Strip, About Us, and Dealer sections.
  - Form validation with CSRF checks on save operations.
  - Audit logging on every update.
- **Acceptance Criteria:**
  1. Administrator can edit Hero heading and subtitle; changes persist in database.
  2. Inputs pass through XSS filtering and server-side length validation.
  3. Dashboard reflects updated modification timestamp.

---

### Sprint SL-WEB-11E — Product Management and Media Library
- **Goal:** Build product catalog administration and hardened image upload system.
- **Dependencies:** SL-WEB-11C, SL-WEB-11D.
- **Key Deliverables:**
  - Media Library module:
    - Secure upload pipeline with MIME sniffing, magic byte checks, and UUID file renaming.
    - SVG sanitizer stripping scripts and event handlers.
    - Grid view of uploaded assets with copyable URL and Alt text editor.
    - Non-executable `/uploads/.htaccess` protection.
  - Product Management module:
    - List view with pagination, search, and category filtering.
    - Add/Edit product form with name, slug generator, category dropdown, packaging tags, and image picker.
    - Display order re-ordering.
    - Publication status toggle (`draft`, `published`, `archived`).
- **Acceptance Criteria:**
  1. Uploading a `.php` file disguised as an image is strictly blocked with HTTP 400.
  2. Valid images are uploaded, assigned UUIDs, and viewable in the media grid.
  3. New product can be added, edited, and toggled between draft and published.

---

### Sprint SL-WEB-11F — Nutrition & Knowledge Management
- **Goal:** Build content management module for articles and farmer education guides.
- **Dependencies:** SL-WEB-11E (Media Library integration).
- **Key Deliverables:**
  - Article list table with status badges and publication date filters.
  - Article editor with title, slug, excerpt, safe rich-text editor, cover image picker, and SEO meta tags.
  - Publication scheduler / immediate publish toggle.
  - Soft-delete / archive confirmation modal.
- **Acceptance Criteria:**
  1. Articles can be saved as drafts or published.
  2. Unsanitized HTML tags (e.g. `<script>`, `onerror`) pasted into the body are stripped.
  3. Draft articles do not appear in the public knowledge API output.

---

### Sprint SL-WEB-11G — Contact, Navigation, SEO and Publishing Controls
- **Goal:** Build settings modules for business information, navigation menu order, and SEO controls.
- **Dependencies:** SL-WEB-11D.
- **Key Deliverables:**
  - Business Settings editor (phone, WhatsApp, email, postal address, social links).
  - Unconfigured state indicators for missing contact info.
  - Navigation menu editor for re-ordering header links.
  - Global SEO & Social Sharing editor (meta title, description, OG image).
  - CRM Login link verification (enforcing immutable `https://shubhlabh-crm.vercel.app/`).
  - Draft Preview generator with signed preview tokens.
- **Acceptance Criteria:**
  1. Phone, WhatsApp, and email update correctly and validate format.
  2. CRM Login destination URL remains strictly locked and verified.
  3. Navigation re-ordering updates menu order on public site.

---

### Sprint SL-WEB-11H — Public Website Integration
- **Goal:** Connect existing public website pages to CMS data while maintaining 100% design fidelity and offline fallback.
- **Dependencies:** SL-WEB-11C through SL-WEB-11G.
- **Key Deliverables:**
  - Front controller / hydration script dynamically populating Hero, Products, About Us, Knowledge, and Contact sections from database.
  - Strict preservation of all existing CSS styles, classes, and responsive breakpoints.
  - Graceful fallback mechanism: if database connection fails, website automatically falls back to baseline static markup without error screens.
  - Loading skeleton indicators during initial client-side data fetch (if API mode) or seamless instant server rendering (if Option A unified).
- **Acceptance Criteria:**
  1. Public website displays exact CMS-published content.
  2. Design, styling, fonts, and responsiveness remain identical to current static site.
  3. CRM Login button continues to navigate to `https://shubhlabh-crm.vercel.app/`.
  4. Disconnecting database serves static fallback content cleanly.

---

### Sprint SL-WEB-11I — Security, Regression and Responsive Testing
- **Goal:** Comprehensive end-to-end audit, penetration testing, and multi-device QA.
- **Dependencies:** SL-WEB-11H.
- **Key Deliverables:**
  - Automated penetration test for SQL injection, XSS, CSRF, and file upload execution.
  - Brute force test on `/admin/login` verifying rate-limiting lockout.
  - Mobile, tablet, and desktop responsive testing across Chrome, Safari, Firefox, and Edge.
  - PageSpeed / Lighthouse performance audit verifying performance score $\ge 90$.
- **Acceptance Criteria:**
  1. Zero high or medium severity vulnerabilities found.
  2. 100% responsive compatibility across 320px to 2560px screen widths.
  3. Zero broken links, missing assets, or console errors.

---

### Sprint SL-WEB-11J — Deployment Preparation and Controlled Launch
- **Goal:** Production hosting setup, database provisioning, SSL installation, and controlled rollout.
- **Dependencies:** SL-WEB-11I approval.
- **Key Deliverables:**
  - Production environment configuration and database provisioning.
  - Automated backup schedule configuration (daily database dumps, weekly media backups).
  - SSL/TLS certificate installation and HTTPS enforcement.
  - Admin user onboarding and initial password provisioning.
  - Post-launch monitoring and smoke test.
- **Acceptance Criteria:**
  1. CMS admin panel accessible over HTTPS at `/admin`.
  2. Public website fully operational with CMS-managed content.
  3. Backups executing successfully and verified.
  4. Final sign-off from Product Owner.

---

## PART 3 — REQUIRED OWNER DECISIONS & BLOCKERS

Before advancing past the audit gate into implementation (`SL-WEB-11A`), the Product Owner must resolve the following key decisions:

| Decision Item | Options | Recommendation | Impact |
| :--- | :--- | :--- | :--- |
| **1. Hosting Environment** | A. Shared/VPS Linux Hosting with PHP 8.2+ & MySQL 8.0+<br>B. Static Host (Vercel) + Remote PHP/MySQL API host | **Option A (Unified Shared/VPS)** | Lower cost (single host), zero CORS overhead, server-side rendering for SEO, unified backups. |
| **2. Admin Account Setup** | Initial admin email and temporary password delivery method. | Generated securely during migration seed, output to server console/secure log. | Prevents sending plain-text credentials over unencrypted channels. |
| **3. Media Storage Location** | Local server filesystem (`/uploads/`) vs. S3-compatible cloud storage (Cloudflare R2 / AWS S3). | **Local filesystem** initially; scalable to S3 later if catalog expands. | Simplest implementation with zero recurring cloud storage fees. |
| **4. Contact & Business Data Verification** | Verify official telephone, WhatsApp number, email, and factory postal address. | Keep unconfigured fields hidden until confirmed by owner. | Prevents publishing incorrect or placeholder company data. |
