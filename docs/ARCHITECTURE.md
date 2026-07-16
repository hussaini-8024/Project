# EPIMS Architecture

## Overview

Enterprise Procurement & Inventory Management System (EPIMS) is a Windows desktop application built with a three-tier architecture:

| Layer | Project | Responsibility |
|-------|---------|----------------|
| Presentation | `EnterpriseProcurement.Desktop` | WPF UI, MVVM, navigation, session |
| Business | `EnterpriseProcurement.Business` | Auth, RBAC, procurement, inventory, reports |
| Data | `EnterpriseProcurement.Data` | EF Core, seeding, persistence |
| Core | `EnterpriseProcurement.Core` | Entities, DTOs, interfaces, security helpers |

## Security

- BCrypt password hashing (work factor 12)
- Account lockout after failed login attempts
- Optional two-factor authentication
- Role-based + menu-level + department-level permissions
- Soft-delete query filters
- Encrypted AES database backups
- Full audit trail (user, department, machine, IP, old/new values)

## Multi-department access

Users may be linked to one or more departments. After login they switch the active department. Non-admin data queries are scoped to the active department. Super admins see all departments.

## Approval workflow

```
Employee → Department Head → Procurement Manager → Finance → Administrator → Purchase Order
```

## Database providers

Configured in `appsettings.json`:

- `Sqlite` (default, zero-config local/offline)
- `SqlServer` (preferred for enterprise deployments)

```json
{
  "Database": {
    "Provider": "SqlServer",
    "ConnectionString": "Server=.;Database=EPIMS;Trusted_Connection=True;TrustServerCertificate=True"
  },
  "ConnectionStrings": {
    "DefaultConnection": "Server=.;Database=EPIMS;Trusted_Connection=True;TrustServerCertificate=True"
  }
}
```

## Installer

`installer/EPIMS-Setup.iss` (Inno Setup) produces `Setup.exe` with:

- Installation wizard
- Desktop shortcut
- Start Menu shortcuts
- Writable `Backups`, `Exports`, `Attachments` folders
- Windows 7 SP1+ compatibility target
