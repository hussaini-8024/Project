# UniMeet — university classroom system

This repository now includes **UniMeet**, a complete daily-use virtual classroom for a BSCS faculty: login, courses, enrollment, LiveKit video, attendance, Smart 720p, and lecture AI.

**Start here:** [unimeet/README.md](unimeet/README.md)

```
cd unimeet/backend && npm install && npm run db:reset && npm run dev
cd unimeet/frontend && npm install && npm run dev
```

Open http://localhost:5173  
Demo password for every seeded account: `UniMeet@2026`  
Try Ali (`STU-1001`) and John (`STU-1004`) on the live Database Systems class to see enrollment enforcement.

---

The original Ansible AWX playbooks for Microsoft Office 2016 LAN install remain in `playbooks/`, `roles/`, and `scripts/`.
