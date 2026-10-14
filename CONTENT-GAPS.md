# Shubh Labh Website — Business Content & Asset Inventory (CONTENT-GAPS.md)

**Project:** Shubh Labh Public Website  
**Date:** 2026-10-14  
**Audit Purpose:** Distinguish verified business content from missing assets requiring Product Owner input prior to production release.

---

## 1. Business Information & Asset Gap Matrix

| Field | Current Value / Source | Verification Status | Action Required |
| :--- | :--- | :--- | :--- |
| **Official Logo** | CSS/SVG text mark (`शुभ लाभ` emblem) in [index.html](file:///D:/ShubhLabhWebsite/index.html#L36) | **Placeholder / Temporary Vector** | Provide official vector logo (SVG) or master high-res transparent PNG for header and footer. |
| **Product Packaging Images** | CSS illustrative art (`.art-bag`, `.art-sun`, `.art-hill`) in [index.html](file:///D:/ShubhLabhWebsite/index.html#L73-L75) | **Illustrative Mockup** | Supply authentic photographs/packshots of actual commercial bags (Dairy Special, Performance Premix, Dairy Care). |
| **Product Information** | 3 products (*Dairy Special*, *Performance Premix*, *Specialised Nutrition*) | **Verified Structural Baseline** | Preserve verified names and descriptions. Confirm exact nutritional guarantees and bag sizes against commercial labels. |
| **Business Address** | Placeholder text: *"To be confirmed before launch"* | **Missing / Absent** | Product Owner to provide official registered office and manufacturing plant address in Rajasthan/India. |
| **Telephone / WhatsApp** | Generic link: `https://wa.me/?text=...` without target phone number | **Unconfigured / Missing Phone** | Product Owner to provide official WhatsApp Business phone number and customer support helpline. |
| **Official Email** | Placeholder text: *"Phone, email and business address will be added after verification."* | **Missing / Absent** | Product Owner to provide official public contact email (e.g., `contact@shubhlabh...` or `sales@...`). |
| **Dealer Enquiry Action** | `#contact` anchor scroll pointing to WhatsApp and CRM portal | **Functional Static Baseline** | Confirm whether dealer enquiries should route exclusively to WhatsApp/email or to a dedicated external intake form. |
| **Company Narrative & Values** | Farmer-first dairy nutrition narrative in [index.html](file:///D:/ShubhLabhWebsite/index.html#L67) | **Verified Working Baseline** | Retain grounded, farmer-friendly tone. Do not invent unverified establishment years, production metrics, or certifications. |
| **CRM Portal Destination** | `https://shubhlabh-crm.vercel.app/` | **Verified & Fixed** | Maintain exact URL. Keep isolated from public site navigation. |
| **Production Domain** | None assumed (local preview only) | **Pending Owner Selection** | Product Owner to specify final apex domain (e.g., `shubhlabhfeed.com`). |

---

## 2. Asset Request List for Product Owner

To transition the website from **Staging / Preview Ready** to **Public Production Launch**, please supply:

1. **Brand Assets:**
   - Vector master logo (`logo.svg` or `logo.png` minimum 600px width).
   - Official brand favicon / app icons (`favicon.ico`, `apple-touch-icon.png`).
2. **Product Imagery:**
   - 3 packshot images on clean backgrounds (minimum 800x800px, WebP or PNG) for:
     1. *Shubh Labh Dairy Special* (Cattle Feed)
     2. *Performance Premix* (Nutrition Premix)
     3. *Specialised Nutrition* (Mineral / Dairy Care)
3. **Official Contact Information:**
   - WhatsApp Business telephone number (in international format, e.g., `+91XXXXXXXXXX`).
   - Customer support telephone number.
   - Public business email address.
   - Registered factory / office postal address.
4. **Production Domain & Hosting Decision:**
   - Target production domain name.
   - Preferred static hosting provider (e.g. Vercel, Cloudflare Pages, Netlify, or existing hosting).
