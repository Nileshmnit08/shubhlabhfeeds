# SL-WEB-11 — CMS Security Plan & Threat Model
## Shubh Labh Website CMS Architecture

**Project:** Shubh Labh Public Website CMS  
**Document ID:** `CMS-SECURITY-PLAN.md`  
**Status:** Audit & Planning Draft (Read-Only Blueprint)  
**Target Runtime:** PHP 8.2+ / MySQL 8.0+ / Apache or Nginx  
**Scope:** Security hardening, authentication, authorization, session controls, upload safety, database security, and disaster recovery.

---

## 1. Executive Summary & Security Principles

This security plan governs the design and deployment of the administrative Content Management System (CMS) for the Shubh Labh public website. The architecture strictly isolates the public website and CMS from the external Shubh Labh CRM (`https://shubhlabh-crm.vercel.app/`).

### Core Security Tenets
1. **Zero Impact on CRM:** The CMS operates with complete independence from the Supabase/CRM environment. No CRM database credentials, session tokens, or API keys are stored or referenced within the CMS.
2. **Defense in Depth:** Security controls are enforced in layers (network/web server, application routing, authentication middleware, validation, parameterization, and output encoding).
3. **Least Privilege:** Web server processes run under non-root users (`www-data` or cPanel dedicated user), database accounts have restricted grants (no `FILE` or `SUPER` grants), and administrative roles enforce strict write authorizations.
4. **Fail-Closed by Design:** Authentication, file validation, and authorization failures abort execution immediately, log an event, and return generic, uninformative error messages to the client.

---

## 2. Threat Modeling (STRIDE Analysis)

| Threat Category | Potential Attack Vector | Impact | Mitigations & Countermeasures |
| :--- | :--- | :--- | :--- |
| **Spoofing** | Brute-force attacks against `/admin/login`, credential stuffing, session hijacking. | Unauthorized admin access, defacement, malicious content injection. | Argon2id password hashing, strict session regeneration on login, `HttpOnly`/`SameSite=Strict`/`Secure` cookies, IP/account rate-limiting (5 failures / 15 min), generic error messages. |
| **Tampering** | Parameter manipulation, SQL injection, cross-site request forgery (CSRF), tampering with published slugs. | Database alteration, unauthorized record deletion, unauthorized state change. | PDO prepared statements with native parameters (`PDO::ATTR_EMULATE_PREPARES = false`), anti-CSRF token verification (`hash_equals()`) on all POST/PUT/DELETE requests, strict schema constraints. |
| **Repudiation** | Malicious administrator modifying content or deleting records without attribution. | Inability to identify malicious or compromised user accounts. | Immutable `audit_logs` table tracking user ID, action (`CREATE`, `UPDATE`, `DELETE`, `PUBLISH`), IP address, user agent, and timestamp. |
| **Information Disclosure** | Verbose error stack traces, directory indexing, exposed `.env` files, path traversal in image endpoints. | Leaked database credentials, server paths, internal configuration. | Display errors disabled in production (`display_errors = 0`), configuration stored outside web root (`/private/.env`), web server deny rules for sensitive files, `basename()` path sanitization. |
| **Denial of Service (DoS)** | Exhaustion of upload storage, CPU spikes via malicious decompression, flooding public APIs. | Website unavailability, disk exhaustion. | Strict file size caps (5 MB images, 1 MB SVG), rate-limiting on login and write endpoints, memory and execution time limits in `php.ini`. |
| **Elevation of Privilege** | Normal visitor attempting to call `/api/admin/*` endpoints without authentication. | Full administrative takeover of website content. | Strict authorization middleware verified prior to dispatching any administrative controller. Session identity validated on every single request. |

---

## 3. Authentication & Credential Security

### 3.1 Password Hashing Architecture
- **Algorithm:** **Argon2id** (`PASSWORD_ARGON2ID` in PHP 8.2+) is the mandatory default.
- **Fallback / Standard:** **Bcrypt** (`PASSWORD_BCRYPT` with cost factor $\ge 12$) if Argon2id is unsupported by the host PHP build.
- **Algorithm Upgrading:** The authentication handler calls `password_needs_rehash()` upon every successful login to automatically upgrade hashes as security parameters evolve.
- **Password Policy:**
  - Minimum 12 characters.
  - Must include at least 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special symbol.
  - Checked against common breached password blocklists.

### 3.2 Login Rate Limiting & Account Protection
- **Throttle Window:** Maximum 5 failed attempts per IP address and username within a 15-minute rolling window.
- **Lockout Mechanism:** If threshold is reached, subsequent attempts are blocked for 15 minutes. An exponential backoff is triggered for repeat offenders.
- **Generic Error Responses:** In all failure states (invalid username, invalid password, inactive account), the response must be identical:
  ```json
  {
    "status": 401,
    "error": "Unauthorized",
    "message": "Invalid username or password."
  }
  ```
  No hint is given as to whether the username exists.

### 3.3 Session Management & Hardening
- **Session Configuration (`php.ini` or runtime directives):**
  ```ini
  session.cookie_httponly = 1
  session.cookie_secure = 1
  session.cookie_samesite = "Strict"
  session.use_strict_mode = 1
  session.use_only_cookies = 1
  session.gc_maxlifetime = 7200
  ```
- **Session Fixation Prevention:**
  - `session_regenerate_id(true)` is executed immediately upon successful authentication.
  - `session_regenerate_id(true)` is executed upon role changes or privilege elevation.
- **Inactivity Timeout:**
  - Active session lifetime: 8 hours maximum.
  - Idle timeout: 30 minutes of inactivity triggers automatic session expiration and logout.
- **Session Fingerprinting:**
  - Store hash of user agent and IP subnet in `$_SESSION['auth_fingerprint']`.
  - Invalidate session if user agent changes drastically mid-session.

---

## 4. Cross-Site Request Forgery (CSRF) Protection

### 4.1 Token Generation & Verification
- State-changing operations (HTTP POST, PUT, DELETE, PATCH) require a cryptographically secure pseudo-random CSRF token:
  ```php
  if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  ```
- Admin panel form requests include the token in a hidden input `<input type="hidden" name="csrf_token" value="...">`.
- Admin API requests accept the token via the `X-CSRF-Token` header.
- Verification is performed using timing-attack-safe string comparison:
  ```php
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted_token ?? '')) {
      http_response_code(403);
      exit(json_encode(['error' => 'Invalid or missing CSRF token']));
  }
  ```

---

## 5. Input Validation, Database Security & Output Encoding

### 5.1 SQL Injection Prevention
- **Rule:** Zero raw SQL queries with string interpolation or concatenation.
- **PDO Enforcement:**
  ```php
  $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false, // Enforce true server-side prepared statements
  ]);
  ```
- All dynamic query parameters are passed via PDO prepared statements (`$stmt->prepare()` with `$stmt->execute($params)`).

### 5.2 Server-Side Input Validation
- Whitelist validation on all input parameters:
  - **Slugs:** `^[a-z0-9]+(?:-[a-z0-9]+)*$` (length: 3 to 120 characters).
  - **Status:** Whitelist `['draft', 'published', 'archived']`.
  - **Emails:** `filter_var($email, FILTER_VALIDATE_EMAIL)`.
  - **Integers / IDs:** `filter_var($id, FILTER_VALIDATE_INT)`.
  - **URLs:** Whitelist scheme (`https://` or `tel:` or `mailto:`).

### 5.3 Output Encoding & Cross-Site Scripting (XSS) Prevention
- In the CMS admin panel and server-rendered views, all user-controlled data is escaped before rendering:
  ```php
  function e(string $value): string {
      return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
  ```
- Rich-text fields (such as article bodies) are strictly sanitized via a whitelist HTML sanitizer (allowing only `p`, `strong`, `em`, `ul`, `ol`, `li`, `h2`, `h3`, `blockquote`, `a` with verified `href`). Script tags, iframes, inline event handlers (`onclick`, `onload`), and `javascript:` URIs are completely stripped.

---

## 6. Secure Media Library & Upload Hardening

Unrestricted file uploads represent the highest security risk on PHP hosting. The CMS implements multi-layered upload defense.

```
+--------------------------------------------------------------------------+
|                        FILE UPLOAD SECURITY PIPELINE                     |
|                                                                          |
|  [Incoming File]                                                         |
|         |                                                                |
|         v                                                                |
|  1. Size Check (<= 5MB image, <= 1MB SVG)                                |
|         |                                                                |
|         v                                                                |
|  2. Whitelist Extension Check (.jpg, .jpeg, .png, .webp, .svg only)     |
|         |                                                                |
|         v                                                                |
|  3. Server-Side MIME Type Sniffing (finfo_file / finfo_buffer)           |
|         |                                                                |
|         v                                                                |
|  4. Magic Bytes Inspection (Verify binary header matches MIME)           |
|         |                                                                |
|         v                                                                |
|  5. SVG Sanitization (XML Parser removes <script>, onload, external DTD) |
|         |                                                                |
|         v                                                                |
|  6. Filename Neutralization (Generate cryptographic UUIDv4.ext)          |
|         |                                                                |
|         v                                                                |
|  7. Store in Non-Executable Directory (/uploads/ with php engine disabled|
|         |                                                                |
|         v                                                                |
|  8. Log in media_assets table & Return Safe URL                          |
+--------------------------------------------------------------------------+
```

### 6.1 Upload Validation Rules
1. **Extension Whitelist:** Only `.jpg`, `.jpeg`, `.png`, `.webp`, `.svg` are permitted. Double extensions (e.g., `image.php.png` or `file.phtml.jpg`) are rejected.
2. **MIME Sniffing:** Extension inspection alone is insufficient. The server inspects contents via PHP `fileinfo`:
   ```php
   $finfo = new finfo(FILEINFO_MIME_TYPE);
   $mime = $finfo->file($_FILES['file']['tmp_name']);
   $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
   if (!in_array($mime, $allowedMimes, true)) {
       throw new InvalidArgumentException('Invalid file type detected.');
   }
   ```
3. **Magic Bytes Verification:** Bitmap images must validate with `getimagesize()` or GD/Imagick inspection.
4. **SVG Sanitization:** SVGs can carry executable JavaScript. Uploaded SVGs pass through an XML sanitizer that strips:
   - `<script>` elements
   - Inline event listeners (`onload`, `onerror`, `onclick`, etc.)
   - External entity definitions (`<!ENTITY>`, `SYSTEM`) to prevent XML External Entity (XXE) attacks.
5. **Filename Neutralization:** The original client-provided filename is discarded. The file is renamed to a random UUIDv4 hash:
   `550e8400-e29b-41d4-a716-446655440000.webp`.
6. **Execution Prevention in Upload Directory:**
   The `/uploads/` directory must strictly prevent PHP and CGI execution.
   - **Apache (`/uploads/.htaccess`):**
     ```apache
     # Disable script execution
     <FilesMatch "\.(php|phtml|php3|php4|php5|php7|php8|phps|cgi|pl|py|sh)$">
         Require all denied
     </FilesMatch>
     php_flag engine off
     Options -Indexes -ExecCGI
     ```
   - **Nginx Configuration:**
     ```nginx
     location ^~ /uploads/ {
         # Prevent PHP execution
         location ~ \.php$ {
             deny all;
         }
         try_files $uri =404;
     }
     ```

---

## 7. Configuration Security & Secrets Management

### 7.1 Location of Configuration Secrets
- Database credentials, session encryption keys, and environment settings are stored in an environment configuration file (`.env` or `config.php`).
- **File Placement:**
  - Recommended: Stored in a `/private/` directory located **above** the web root (`public_html/`).
  - Web Server Deny Rule: If hosted within the repository, the web server explicitly blocks access to `.env`, `.git`, `.gitignore`, `composer.json`, and `.md` files.

### 7.2 Directory Layout & Access Control

```
/shubhlabh-cms-root/               <-- Non-public (above web root)
├── .env                           <-- Database credentials & secrets (protected)
├── config/
│   ├── database.php
│   └── app.php
├── migrations/
│   ├── 001_initial_schema.sql
│   └── 002_seed_data.sql
├── logs/                          <-- Application error and security logs
└── public_html/                   <-- Public web root (served by Apache/Nginx)
    ├── .htaccess                  <-- Hardened server configuration
    ├── index.php                  <-- Front controller / public website
    ├── styles.css                 <-- Preserved CSS
    ├── script.js                  <-- Preserved JS
    ├── admin/                     <-- CMS Admin Panel Entry
    │   ├── index.php
    │   └── assets/
    ├── api/                       <-- JSON endpoints
    │   ├── public/
    │   └── admin/
    └── uploads/                   <-- Non-executable media directory
        └── .htaccess              <-- Script execution blocked
```

---

## 8. HTTP Security Headers & Transport Security

### 8.1 HTTPS Enforcement
- HTTPS (TLS 1.2 / TLS 1.3) is mandatory across all pages and API routes.
- Automatic 301 redirection from HTTP to HTTPS.

### 8.2 Security Headers Specification
The following headers must be emitted by Apache/Nginx or the PHP front controller:

```http
# Prevent clickjacking / framing
X-Frame-Options: SAMEORIGIN

# Prevent MIME sniffing
X-Content-Type-Options: nosniff

# Control cross-origin referrer leakage
Referrer-Policy: strict-origin-when-cross-origin

# Restrict permissions for sensitive browser features
Permissions-Policy: geolocation=(), camera=(), microphone=()

# HTTP Strict Transport Security (HSTS) - Enabled once SSL verified
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload

# Content Security Policy (Admin Panel)
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; frame-ancestors 'self';
```

---

## 9. CORS Policy (For Decoupled / API Scenarios)

If the public frontend and the CMS API are hosted under different subdomains or hosts (e.g. `www.shubhlabhfeeds.com` and `api.shubhlabhfeeds.com`):
- **CORS Allowed Origins:** Explicit whitelist of authorized frontend domains (e.g., `https://shubhlabhfeeds.com`, `https://www.shubhlabhfeeds.com`). Wildcards (`*`) with credentials are strictly forbidden.
- **Allowed Methods:** `GET, HEAD, OPTIONS` on public endpoints.
- **Allowed Headers:** `Content-Type, Accept, X-Requested-With`.
- **Admin Endpoints:** Admin endpoints (`/api/admin/*`) require identical origin or same-site credential validation, rejecting third-party cross-origin requests.

---

## 10. Database Backup, Recovery & Disaster Plan

### 10.1 Backup Regimen
1. **Automated Daily Backups:**
   - Automated `mysqldump` scheduled via cron or cPanel backup runner.
   - Backups stored in a secure, non-public directory or encrypted off-site S3/B2 storage.
   - Command pattern:
     ```bash
     mysqldump --opt --single-transaction --quick --routines \
       -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "/backups/db_backup_$(date +%Y%m%d_%H%M%S).sql.gz"
     ```
2. **Retention Policy:**
   - Daily backups retained for 14 days.
   - Weekly backups retained for 8 weeks.
   - Monthly backups retained for 12 months.
3. **Media Assets Backup:**
   - The `/uploads/` directory is synchronized weekly with the backup storage.

### 10.2 Disaster Recovery & Rollback Plan
- **Recovery Time Objective (RTO):** $< 30$ minutes to restore website and CMS functionality.
- **Recovery Point Objective (RPO):** Maximum 24 hours of CMS updates.
- **Emergency Static Failover:**
  If the MySQL database or PHP environment experiences catastrophic failure:
  1. The static baseline website files (`index.html`, `styles.css`, `script.js`) remain in place as an immediate fallback.
  2. The server configuration can instantly route traffic back to `index.html` via `.htaccess` rewrite rule, maintaining 100% public uptime while the database is restored.

---

## 11. Security Audit Verification & Compliance Checklist

Before production release, the CMS must pass this verification checklist:

- [ ] All password storage uses `PASSWORD_ARGON2ID` or `PASSWORD_BCRYPT` (cost $\ge 12$).
- [ ] Session cookies enforce `HttpOnly`, `SameSite=Strict`, and `Secure`.
- [ ] CSRF tokens verified on every administrative POST/PUT/DELETE.
- [ ] All database queries execute via parameterized PDO statements.
- [ ] Stored XSS defense verified: all rendered outputs pass through `htmlspecialchars()`.
- [ ] File uploads reject executable files, check MIME types via `finfo`, and rename to UUIDs.
- [ ] The `/uploads/` directory denies PHP execution via `.htaccess` / Nginx config.
- [ ] Login endpoint limits failed attempts to 5 per 15 minutes.
- [ ] Authentication errors are completely generic.
- [ ] Configuration and credentials are inaccessible via public HTTP.
- [ ] HTTPS redirect and security headers active on all responses.
- [ ] Public website maintains safe static fallback in case of database unavailability.
- [ ] Zero connection or exposure of Shubh Labh CRM database credentials.
