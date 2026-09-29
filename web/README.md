# Stride — Coursera-style learning platform

Public visitor dashboard and supporting pages for a multi-role learning site.

## Roles (coming next for dashboards)

- **Visitors** — browse landing, reviews, about, contact
- **Students** — purchase courses (login: As Student)
- **Teachers** — upload courses with instructor ID (login: As Teacher)
- **Admins** — manage the platform

## Pages

| Route | Description |
| --- | --- |
| `/` | Landing: premium logo, top 3 courses, Explore more, what students learn, reviews |
| `/explore` | Full course catalog |
| `/about` | About us |
| `/contact` | Contact form + WhatsApp button |
| `/login?role=student\|teacher` | Role-specific login placeholders |

Update `WHATSAPP_NUMBER` in `src/lib/courses.ts` with your real number.

## Run locally

```bash
cd web
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000).
