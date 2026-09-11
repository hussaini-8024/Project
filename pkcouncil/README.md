# PKCouncil — custom WordPress learning platform

Premium public website, student portal, teacher portal, and WordPress admin for **PKCouncil**.

This is not a generic LMS and does not use WordPress users for students or teachers.

## Architecture

- **Theme `pkcouncil`** — public site, portals, dark/light UI
- **Plugin `pkcouncil-core`** — accounts, courses, payments, quizzes, chat, access control, admin
- Custom database tables (`wp_pkc_*`)
- Custom sessions (students / teachers)
- WordPress users are administrators only
- `wp-login.php` redirects to `/login/` for everyone except logged-in admins

```
Public site → Buy course → Payment verification (pending)
  → Admin approves → Create account → Student logs in
  → Only purchased courses, protected files, quizzes, teacher chat
```

Teachers see only assigned courses and those courses' students.

## Install on an existing WordPress site

Download these two zips (also in `dist/`):

- `pkcouncil-theme.zip` — Appearance → Themes → Add New → Upload Theme
- `pkcouncil-core-plugin.zip` — Plugins → Add New → Upload Plugin

Then:

1. Activate **PKCouncil Core**
2. Activate the **PKCouncil** theme
3. Settings → Permalinks → Save (flush rewrite rules)

Admin login is your **WordPress administrator** username and password, on `/login/` or in `wp-admin`.

## Local (PHP + MariaDB)

WordPress core is not stored in this repository. Use Docker (below) or point a WordPress install at:

- `plugin/` → `wp-content/plugins/pkcouncil-core`
- `theme/` → `wp-content/themes/pkcouncil`

Then activate the plugin and theme, and flush permalinks.

## Docker

```bash
cd pkcouncil
docker compose up -d
```

Complete the WordPress installer, activate **PKCouncil Core** and the **PKCouncil** theme.

## Demo accounts (after plugin activation / seed)

| Role | Username | Password |
|------|----------|----------|
| Admin (WordPress) | `admin` | set during WP install |
| Student | `aisha.khan` | `Student@PKCouncil` |
| Teacher | `dr.farah` | `Teacher@PKCouncil` |

Passwords are stored with `password_hash()`. Students and teachers are forced to change the initial password.

Coupons: `WELCOME10`, `MDCAT20` (MDCAT course only).

Payment methods: EasyPaisa, JazzCash, Bank Transfer (manual verification).

## Security notes

- Course files live in `wp-content/uploads/pkc-protected/` and are not publicly listable
- Delivery is `/pkc-file/{id}/` after server-side enrolment checks
- Quiz timers are enforced on the server (`expires_at`); the browser timer is display-only
- Prices and coupon discounts are calculated on the server
- CSRF tokens are bound to the custom session

## Admin

WordPress → **PKCouncil** menu: website copy, courses, students, teachers, payments, quiz bank, quizzes, reviews, coupons, contacts, messages, audit log, settings.
