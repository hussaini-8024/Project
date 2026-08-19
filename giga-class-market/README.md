# Giga Class Market

Premium WordPress course marketplace + LMS for **Giga Class Market**.

## Package contents

```
giga-class-market/
├── plugin/giga-class-market/   # Core business logic plugin
├── theme/giga-class-market/    # Premium front-end theme
├── scripts/seed-demo.sh        # Optional demo seeder (WP-CLI)
└── docs/INSTALLATION.md        # Full setup, security, testing docs
```

## Quick start

1. Copy plugin → `wp-content/plugins/giga-class-market`
2. Copy theme → `wp-content/themes/giga-class-market`
3. Activate plugin, then theme
4. Flush permalinks
5. Configure **Giga Class Market → Settings**

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for the complete guide.

## Stack

- WordPress users/roles (`gcm_student`)
- Custom post types for courses, testimonials, slides
- Custom tables for payments, enrollments, progress, contacts, audit logs
- Manual payment verification (Bank / JazzCash / Easypaisa)
- Email + WhatsApp `wa.me` fallback for credential delivery

Website inquiries use the **Services** page form (`/services/#inquiry`). `/contact/` redirects there.
