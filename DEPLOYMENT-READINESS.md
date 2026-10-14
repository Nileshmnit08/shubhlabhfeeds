# Shubh Labh Static Website — Deployment Readiness Report (DEPLOYMENT-READINESS.md)

**Project:** Shubh Labh Public Website  
**Path:** `D:\ShubhLabhWebsite`  
**Evaluation Date:** 2026-10-14  
**Sprint:** SL-WEB-08  
**Deployment Status:** **BLOCKED (Gated — Awaiting Explicit Owner Deployment Authorization)**  

---

## 1. Static Site Architecture & Readiness Audit

| Checkpoint | Status | Verification Details |
| :--- | :--- | :--- |
| **Framework & Runtime** | **Ready** | Pure static HTML5, CSS3, ES6+. Zero build step, zero compiler dependencies, zero npm dependencies. |
| **Asset Paths** | **Ready** | All local references are clean relative paths: `styles.css`, `script.js`. Zero dependencies on local absolute filesystem paths (`D:\...`). |
| **External CDNs** | **Ready** | Google Fonts (`DM Sans`, `Manrope`) and verified Unsplash photography load over secure HTTPS with `preconnect` tags. |
| **Metadata & Favicons** | **Ready** | Title, description, OpenGraph tags, Twitter Card tags, theme-color (`#143d28`), and inline SVG favicon data URI are configured. Zero non-existent local asset 404s. |
| **CRM Login Target** | **Verified** | All 4 login references strictly target `https://shubhlabh-crm.vercel.app/` with `target="_blank"` and `rel="noopener noreferrer"`. |
| **Security & Secrets** | **Verified Clean** | Zero API keys, environment files, secrets, or privileged credentials exist in code. Zero cookies or private tracking scripts. |
| **Local Dependencies** | **None** | Can be hosted on any standard static web server (Vercel, Cloudflare Pages, Netlify, AWS S3/CloudFront, GitHub Pages, or Apache/Nginx). |

---

## 2. Hosting Recommendations & Technical Options

Since the website is 100% static, it should be deployed to a modern, edge-cached static hosting platform **independent** of the existing CRM Vercel project:

### Recommended Option: Cloudflare Pages or Standalone Vercel Project
- **Build Command:** None (leave empty)
- **Output Directory:** `.` (root directory)
- **Node Version:** Not applicable
- **Routing Rules:** Standard static routing (serves `index.html` at `/`)
- **HTTPS/SSL:** Automatic TLS 1.3 certificates provided by edge host.

> [!WARNING]
> **CRM Isolation Rule:** The existing CRM deployment (`shubhlabh-crm.vercel.app`) must **NEVER** be overwritten or connected to this public website. If Vercel is selected for the public website, it must be created as a completely separate project (e.g. `shubhlabh-public-web`).

---

## 3. Files to Publish

The minimal production deployment bundle consists of exactly:
1. `index.html` (Main entry page and 6 sections)
2. `styles.css` (Design system, typography, components, and responsive styles)
3. `script.js` (Navigation drawer, scroll observer, and sticky header behavior)

*Exclusions:* `.gitignore`, `README.md`, `CONTENT-GAPS.md`, and `DEPLOYMENT-READINESS.md` are documentation files and should remain excluded from the web bundle.

---

## 4. Required Product Owner Decisions Prior to Deployment

1. **Hosting Platform Selection:** Confirm whether Vercel (new project), Cloudflare Pages, or another hosting provider should be used.
2. **Contact Detail Approvals:** Confirm whether to publish with current transparent placeholder indicators or wait until official phone, WhatsApp number, email, and address are inserted.
3. **Domain Name Assignment:** Provide the target domain name for SSL assignment.

---

## 5. Deployment & Rollback Protocol

### Deployment Steps (Upon Explicit Approval):
1. Confirm canonical repository commit state.
2. Connect approved static host to the canonical GitHub repository `main` branch (or upload the 3 static files).
3. Set root directory to `.` with no build command.
4. Execute initial staging preview deployment.
5. Run automated post-deployment smoke tests.

### Post-Deployment Smoke Test Checklist:
- [ ] Root URL loads over HTTPS with HTTP/2 or HTTP/3.
- [ ] Header sticky compression and mobile menu toggle function.
- [ ] All 6 section anchors smooth-scroll to correct positions (`#home`, `#about`, `#products`, `#knowledge`, `#dealer`, `#contact`).
- [ ] CRM Login link opens `https://shubhlabh-crm.vercel.app/` in new tab.
- [ ] Images render without broken placeholders or layout shifts.
- [ ] Lighthouse score meets accessibility (>95) and performance (>90) standards.

### Instant Rollback Plan:
- If hosted via edge static platform (e.g. Vercel/Cloudflare Pages), roll back instantly via the dashboard to the previous deployment ID with zero downtime.
- If hosted via static storage, redeploy the verified baseline static files.
