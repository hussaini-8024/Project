# Giga Class Market — Installation & Operations Guide

Production-ready WordPress LMS / course marketplace packaged as:

- **Plugin:** `giga-class-market/plugin/giga-class-market/` (business logic)
- **Theme:** `giga-class-market/theme/giga-class-market/` (presentation)

## Phase 1 audit findings (this repository)

This GitHub repository previously contained an Ansible AWX Office 2016 project. **No existing WordPress site, theme, or WooCommerce install was present in the workspace.**

Implementation strategy used:

1. Keep existing Ansible files on `main` history intact.
2. Deliver Giga Class Market as an installable WordPress plugin + theme under `giga-class-market/`.
3. Validate against a local WordPress 7.x + MariaDB environment.

If you already have a live WordPress host with admin access, deploy the plugin and theme there (recommended for production).

---

## Requirements

| Component | Version / notes |
|-----------|-----------------|
| WordPress | 6.0+ (tested on 7.0.3) |
| PHP | 8.0+ (tested on 8.3) |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Required plugins | **None** (core plugin is self-contained) |
| Optional | SMTP plugin for reliable email delivery; WhatsApp Business API credentials later |

---

## Installation (production host)

1. **Backup** your WordPress files and database.
2. Upload/copy:
   - `giga-class-market/plugin/giga-class-market` → `wp-content/plugins/giga-class-market`
   - `giga-class-market/theme/giga-class-market` → `wp-content/themes/giga-class-market`
3. In **WP Admin → Plugins**, activate **Giga Class Market**.
4. In **Appearance → Themes**, activate **Giga Class Market**.
5. Go to **Settings → Permalinks**, choose **Post name**, save (flushes rewrites).
6. Open **Giga Class Market → Settings** and configure company, payment accounts, and WhatsApp number. Set **Email (outgoing From address)** to `Official@gigaclassmarket.com` so messages are not sent as `wordpress@…`.
7. Create courses under **Courses** (`gcm_course`), mark up to **3** as featured.
8. Add modules/lessons via course curriculum tools / database curriculum service (course edit + curriculum AJAX), or seed script for demos.

### Pages created on activation

| Slug | Purpose |
|------|---------|
| `/` (Home) | Front page with hero, featured courses, benefits, reviews, contact CTA |
| `/about/` | About + CEO message |
| `/contact/` | Contact form (DB-backed) |
| `/login/` | Student login |
| `/student-dashboard/` | Student area (noindex) |
| `/course-learn/` | Lesson player (noindex) |
| `/payment/` | Payment instructions |
| `/payment-verification/` | Payment verification form |
| `/privacy-policy/` | Privacy |
| `/terms/` | Terms |

Courses marketplace URL: **`/courses/`** (custom post type archive).

---

## Configuration checklist

### Company
- Name, email, phone, WhatsApp, address, business hours, social links

### Payment methods
- Bank / JazzCash / Easypaisa account title, number, instructions

### Security
- Default temporary password (default `Student@giga`) — stored only as WordPress password hash when applied
- Max upload size for payment screenshots

### Featured courses
- Hard limit: **exactly 3**
- Marking a 4th featured course automatically demotes the lowest-priority / oldest featured course

### CEO message (About page)
Editable via **Appearance → Customize** theme mods / options:
- `gcm_ceo_name`, `gcm_ceo_designation`, `gcm_ceo_title`, `gcm_ceo_message`, `gcm_ceo_photo`

---

## Core workflow (must work end-to-end)

```
Browse → Course details → Buy Now → Payment info → Proceed
→ Verification form → Pending/Under Review
→ Admin Approve → Create/find student account → Enroll
→ Send credentials (email + WhatsApp fallback)
→ Student login → My Courses → Learn → Progress sync
```

Rejected payments **never** enroll.

Logged-in students purchasing another course enroll on the **same account**.

---

## Database tables

Created on activation (`{prefix}` = `wp_` by default):

- `gcm_modules`, `gcm_lessons`
- `gcm_enrollments`, `gcm_progress`
- `gcm_payments`, `gcm_contacts`
- `gcm_audit_log`, `gcm_notifications`

CPTs: `gcm_course`, `gcm_testimonial`, `gcm_slide`  
Taxonomy: `gcm_category`  
Role: `gcm_student`

---

## Student accounts

Students are **GCM Students**, not normal WordPress staff users:

- They log in only at `/login/` and use the student dashboard.
- They are hidden from **Users → All Users** by default.
- Manage them under **Giga Class Market → Students**.
- WordPress still stores the account securely in the background (required for passwords/sessions), but the role is `GCM Student` only — not Administrator/Editor/Author.

---

## WhatsApp delivery

- Configure **Business WhatsApp number (sender)** in **Giga Class Market → Settings → WhatsApp** (example: `+966509136037`).
- This number is shown on the site contact/WhatsApp buttons and is recorded as the sender for student messages.
- **Send Account Details** queues email and opens a prefilled `wa.me` chat **to the student**. Open that chat while logged into WhatsApp as the business number above.
- Architecture stores notification rows (including sender/to numbers) for future official WhatsApp Business API integration.

---

## Security highlights

- Nonces on all AJAX endpoints
- Capability checks for admin actions
- Server-side enrollment checks for lesson access
- Authoritative course price from DB (client cannot spoof amount)
- Payment screenshots served via authenticated private endpoint
- Students redirected away from `wp-admin`
- Student dashboards `noindex`
- Passwords via `wp_hash_password` / `wp_set_password` only

---

## Backup

Before updates:

```bash
wp db export gcm-backup.sql
tar -czf gcm-files-backup.tar.gz wp-content/plugins/giga-class-market wp-content/themes/giga-class-market wp-content/uploads
```

Restore with `wp db import` and extract the archive.

---

## Deployment checklist

- [ ] Plugin + theme uploaded and activated
- [ ] Permalinks flushed
- [ ] Company + payment settings filled
- [ ] Logo / favicon set
- [ ] At least 1 published course with modules/lessons
- [ ] 3 featured courses selected
- [ ] Contact form test submission appears in admin
- [ ] Payment test → approve → student can log in and watch lessons
- [ ] SMTP configured for real email
- [ ] SSL enabled
- [ ] Privacy / Terms reviewed by legal counsel

---

## Testing checklist (verified in local environment)

Visitor: home, slider, courses, search/filter, course details, contact, about, theme toggle  
Student: login, dashboard, profile, progress, multi-course enrollment on same account  
Admin: dashboard stats, course CPT, payments approve/reject, freeze/unfreeze, contacts, settings, audit log  
Security: guest dashboard redirect, unpaid access denied, unauthorized approve blocked, password hash ok

---

## Local seed script

```bash
export WP_PATH=/path/to/wordpress
bash giga-class-market/scripts/seed-demo.sh
```

Seeds settings, slides, testimonials, sample courses + curriculum.

---

## Feature map

| Feature | Location |
|---------|----------|
| Activation / pages / tables | `plugin/.../class-gcm-activator.php`, `class-gcm-installer.php` |
| Courses CPT + featured logic | `class-gcm-post-types.php`, `class-gcm-course-service.php` |
| Payments / enrollment | `class-gcm-payment-service.php`, `class-gcm-enrollment-service.php` |
| Progress | `class-gcm-progress-service.php`, `class-gcm-curriculum-service.php` |
| AJAX API | `includes/ajax/class-gcm-ajax.php` |
| Admin UI | `includes/class-gcm-admin.php`, `admin/views/*` |
| Public theme | `theme/giga-class-market/*` |

---

## Default local admin (dev only)

If using the cloud agent local WordPress sandbox:

- URL: `http://127.0.0.1:8080`
- Admin: `admin` / `Admin@giga2026`
- Demo student after seed approval: email `ali.student@example.com` / temp password `Student@giga`

**Change all passwords before any public deployment.**
