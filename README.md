# Enterprise Procurement & Inventory Management System (EPIMS)

Professional Windows desktop application for multi-department procurement, inventory, allocation, and centralized administration.

## Features

- **RBAC security** — roles, menu-level permissions (View/Add/Edit/Delete/Print/Export/Approve), department scoping
- **Multi-department users** — assign users to multiple departments and switch context after login
- **Procurement** — draft → approval workflow → quotations → PO → goods receipt → inventory
- **Inventory** — assets/consumables, barcode & QR codes, stock movements, low-stock alerts
- **Allocations** — issue/return with acknowledgement and condition tracking
- **Vendors** — NTN/STRN, bank details, ratings, documents
- **Dashboards** — admin and department-scoped user dashboards
- **Reports** — CSV export (PDF/Excel-ready extension points)
- **Audit logs** — user, department, computer, IP, old/new values
- **Backup & restore** — AES-encrypted backups
- **Installer** — Inno Setup `Setup.exe` with desktop/Start Menu shortcuts

## Technology

| Area | Choice |
|------|--------|
| UI | C# WPF (.NET 8), MVVM (`CommunityToolkit.Mvvm`) |
| Backend | .NET 8 class libraries |
| Database | SQL Server (preferred) or SQLite (default offline) |
| Architecture | Presentation / Business / Data / Core |
| Installer | Inno Setup (`installer/EPIMS-Setup.iss`) |

## Solution structure

```
src/
  EnterpriseProcurement.Core/        # Entities, DTOs, interfaces, password hashing
  EnterpriseProcurement.Data/        # EF Core DbContext, seed data
  EnterpriseProcurement.Business/    # Application services
  EnterpriseProcurement.Desktop/     # WPF client
tests/
  EnterpriseProcurement.Tests/       # Unit/integration tests
installer/
  EPIMS-Setup.iss                    # Setup.exe definition
scripts/
  build-windows.ps1                  # Publish + installer (Windows)
  build-and-test.sh                  # CI-friendly core build/test
```

## Default credentials

| User | Password | Access |
|------|----------|--------|
| `admin` | `Admin@123` | Super Admin (all departments/menus) |
| `qasim` | `Qasim@123` | IT + Procurement departments |

Change these immediately in production.

## Quick start (Windows)

### Prerequisites

- [.NET 8 SDK](https://dotnet.microsoft.com/download/dotnet/8.0)
- Windows 7 SP1 / 8 / 10 / 11 (or supported Server editions)
- Optional: [SQL Server](https://www.microsoft.com/sql-server) for enterprise DB
- Optional: [Inno Setup 6](https://jrsoftware.org/isinfo.php) to build `Setup.exe`

### Run from source

```powershell
dotnet restore EnterpriseProcurement.sln
dotnet run --project src/EnterpriseProcurement.Desktop/EnterpriseProcurement.Desktop.csproj
```

### Configure SQL Server

Edit `src/EnterpriseProcurement.Desktop/appsettings.json`:

```json
{
  "Database": {
    "Provider": "SqlServer"
  },
  "ConnectionStrings": {
    "DefaultConnection": "Server=YOUR_SERVER;Database=EPIMS;Trusted_Connection=True;TrustServerCertificate=True"
  }
}
```

### Build Setup.exe

```powershell
./scripts/build-windows.ps1
```

Output: `dist/EPIMS-Setup.exe`

## Tests

Core business logic runs on any OS:

```bash
./scripts/build-and-test.sh
# or
dotnet test tests/EnterpriseProcurement.Tests/EnterpriseProcurement.Tests.csproj
```

> The WPF project (`net8.0-windows`) compiles only on Windows.

## Module map

| Module | Capabilities |
|--------|--------------|
| Departments | Create / edit / deactivate |
| Users | Create, reset password, activate/deactivate, multi-department assignment |
| Permissions | Role + per-user menu actions |
| Procurement | Requests, approvals, PO, receive goods |
| Inventory | Items, barcode/QR, stock adjust/transfer, low stock |
| Allocations | Issue, acknowledge, return |
| Vendors | Master data + documents |
| Reports | CSV exports |
| Audit | Searchable activity log |
| Settings | Company profile, theme colors, backup/restore |

## License

Proprietary — all rights reserved for the owning organization.
