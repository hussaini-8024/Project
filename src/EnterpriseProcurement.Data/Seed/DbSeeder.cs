using EnterpriseProcurement.Core.Constants;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Security;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Data.Seed;

public static class DbSeeder
{
    public static async Task SeedAsync(AppDbContext db, CancellationToken ct = default)
    {
        await db.Database.EnsureCreatedAsync(ct);

        if (!await db.Roles.AnyAsync(ct))
            await SeedRolesAsync(db, ct);

        if (!await db.Menus.AnyAsync(ct))
            await SeedMenusAndPermissionsAsync(db, ct);

        if (!await db.Departments.AnyAsync(ct))
            await SeedDepartmentsAsync(db, ct);

        if (!await db.Users.AnyAsync(ct))
            await SeedUsersAsync(db, ct);

        if (!await db.CompanyProfiles.AnyAsync(ct))
            await SeedCompanyAsync(db, ct);

        if (!await db.AppSettings.AnyAsync(ct))
            await SeedSettingsAsync(db, ct);

        if (!await db.Vendors.AnyAsync(ct))
            await SeedVendorsAsync(db, ct);

        if (!await db.InventoryItems.AnyAsync(ct))
            await SeedSampleInventoryAsync(db, ct);

        await db.SaveChangesAsync(ct);
    }

    private static async Task SeedRolesAsync(AppDbContext db, CancellationToken ct)
    {
        db.Roles.AddRange(
            new Role { Name = "Administrator", Description = "Full system access", IsSystemRole = true },
            new Role { Name = "Department Head", Description = "Approves department requests", IsSystemRole = true },
            new Role { Name = "Procurement Manager", Description = "Manages procurement workflow", IsSystemRole = true },
            new Role { Name = "Finance Officer", Description = "Budget and finance approval", IsSystemRole = true },
            new Role { Name = "Department User", Description = "Standard department user", IsSystemRole = true },
            new Role { Name = "Inventory Clerk", Description = "Inventory and allocation operations", IsSystemRole = true }
        );
        await db.SaveChangesAsync(ct);
    }

    private static async Task SeedMenusAndPermissionsAsync(AppDbContext db, CancellationToken ct)
    {
        var menus = new List<Menu>
        {
            new() { Key = MenuKeys.Dashboard, Title = "Dashboard", Icon = "Dashboard", Route = "Dashboard", SortOrder = 1, RequiresDepartment = false },
            new() { Key = MenuKeys.Departments, Title = "Departments", Icon = "Building", Route = "Departments", SortOrder = 2, RequiresDepartment = false },
            new() { Key = MenuKeys.Users, Title = "Users", Icon = "Users", Route = "Users", SortOrder = 3, RequiresDepartment = false },
            new() { Key = MenuKeys.Roles, Title = "Roles", Icon = "Shield", Route = "Roles", SortOrder = 4, RequiresDepartment = false },
            new() { Key = MenuKeys.Permissions, Title = "Permissions", Icon = "Key", Route = "Permissions", SortOrder = 5, RequiresDepartment = false },
            new() { Key = MenuKeys.Procurement, Title = "Procurement", Icon = "Cart", Route = "Procurement", SortOrder = 10 },
            new() { Key = MenuKeys.ProcurementRequests, Title = "Purchase Requests", Icon = "Document", Route = "Procurement/Requests", SortOrder = 11 },
            new() { Key = MenuKeys.ProcurementApprovals, Title = "Approvals", Icon = "Check", Route = "Procurement/Approvals", SortOrder = 12 },
            new() { Key = MenuKeys.ProcurementOrders, Title = "Purchase Orders", Icon = "Order", Route = "Procurement/Orders", SortOrder = 13 },
            new() { Key = MenuKeys.ProcurementHistory, Title = "Purchase History", Icon = "History", Route = "Procurement/History", SortOrder = 14 },
            new() { Key = MenuKeys.Vendors, Title = "Vendors", Icon = "Vendor", Route = "Vendors", SortOrder = 15 },
            new() { Key = MenuKeys.Inventory, Title = "Inventory", Icon = "Box", Route = "Inventory", SortOrder = 20 },
            new() { Key = MenuKeys.InventoryItems, Title = "Items & Assets", Icon = "Tag", Route = "Inventory/Items", SortOrder = 21 },
            new() { Key = MenuKeys.InventoryStock, Title = "Stock Management", Icon = "Stock", Route = "Inventory/Stock", SortOrder = 22 },
            new() { Key = MenuKeys.InventoryMovements, Title = "Stock Movements", Icon = "Transfer", Route = "Inventory/Movements", SortOrder = 23 },
            new() { Key = MenuKeys.Allocations, Title = "Allocations", Icon = "Allocate", Route = "Allocations", SortOrder = 30 },
            new() { Key = MenuKeys.Reports, Title = "Reports", Icon = "Report", Route = "Reports", SortOrder = 40 },
            new() { Key = MenuKeys.AuditLogs, Title = "Audit Logs", Icon = "Audit", Route = "Audit", SortOrder = 50, RequiresDepartment = false },
            new() { Key = MenuKeys.Notifications, Title = "Notifications", Icon = "Bell", Route = "Notifications", SortOrder = 60, RequiresDepartment = false },
            new() { Key = MenuKeys.Settings, Title = "Settings", Icon = "Settings", Route = "Settings", SortOrder = 70, RequiresDepartment = false },
            new() { Key = MenuKeys.Backup, Title = "Backup & Restore", Icon = "Backup", Route = "Backup", SortOrder = 71, RequiresDepartment = false },
            new() { Key = MenuKeys.CompanyProfile, Title = "Company Profile", Icon = "Company", Route = "Settings/Company", SortOrder = 72, RequiresDepartment = false }
        };

        db.Menus.AddRange(menus);
        await db.SaveChangesAsync(ct);

        var adminRole = await db.Roles.FirstAsync(r => r.Name == "Administrator", ct);
        var deptUserRole = await db.Roles.FirstAsync(r => r.Name == "Department User", ct);
        var allMenus = await db.Menus.ToListAsync(ct);

        foreach (var menu in allMenus)
        {
            db.RoleMenuPermissions.Add(new RoleMenuPermission
            {
                RoleId = adminRole.Id,
                MenuId = menu.Id,
                Actions = PermissionAction.All
            });
        }

        var deptMenus = allMenus.Where(m =>
            m.Key is MenuKeys.Dashboard or MenuKeys.Procurement or MenuKeys.ProcurementRequests
                or MenuKeys.Inventory or MenuKeys.InventoryItems or MenuKeys.Allocations
                or MenuKeys.Notifications or MenuKeys.Reports).ToList();

        foreach (var menu in deptMenus)
        {
            var actions = menu.Key == MenuKeys.Reports
                ? PermissionAction.View | PermissionAction.Print | PermissionAction.Export
                : PermissionAction.View | PermissionAction.Add | PermissionAction.Edit | PermissionAction.Print;

            db.RoleMenuPermissions.Add(new RoleMenuPermission
            {
                RoleId = deptUserRole.Id,
                MenuId = menu.Id,
                Actions = actions
            });
        }

        await db.SaveChangesAsync(ct);
    }

    private static async Task SeedDepartmentsAsync(AppDbContext db, CancellationToken ct)
    {
        var names = new[]
        {
            ("IT", "Information Technology"),
            ("HR", "Human Resources"),
            ("FIN", "Finance"),
            ("PRC", "Procurement"),
            ("ADM", "Administration"),
            ("ENG", "Engineering"),
            ("SEC", "Security"),
            ("ACC", "Accounts"),
            ("OPS", "Operations"),
            ("LOG", "Logistics")
        };

        foreach (var (code, name) in names)
        {
            db.Departments.Add(new Department
            {
                Code = code,
                Name = name,
                Description = $"{name} Department",
                Status = DepartmentStatus.Active,
                AnnualBudget = 1_000_000
            });
        }

        await db.SaveChangesAsync(ct);
    }

    private static async Task SeedUsersAsync(AppDbContext db, CancellationToken ct)
    {
        var adminRole = await db.Roles.FirstAsync(r => r.Name == "Administrator", ct);
        var deptRole = await db.Roles.FirstAsync(r => r.Name == "Department User", ct);
        var it = await db.Departments.FirstAsync(d => d.Code == "IT", ct);
        var prc = await db.Departments.FirstAsync(d => d.Code == "PRC", ct);
        var net = await db.Departments.FirstOrDefaultAsync(d => d.Code == "ENG", ct);

        var admin = new User
        {
            Username = AppConstants.DefaultAdminUsername,
            Email = "admin@epims.local",
            FullName = "System Administrator",
            PasswordHash = PasswordHasher.Hash(AppConstants.DefaultAdminPassword),
            RoleId = adminRole.Id,
            IsSuperAdmin = true,
            Status = UserStatus.Active,
            Designation = "Super Admin"
        };

        var qasim = new User
        {
            Username = "qasim",
            Email = "qasim@epims.local",
            FullName = "Qasim Hussaini",
            PasswordHash = PasswordHasher.Hash("Qasim@123"),
            RoleId = deptRole.Id,
            Status = UserStatus.Active,
            EmployeeCode = "EMP-001",
            Designation = "IT Officer"
        };

        db.Users.AddRange(admin, qasim);
        await db.SaveChangesAsync(ct);

        db.UserDepartments.AddRange(
            new UserDepartment { UserId = qasim.Id, DepartmentId = it.Id, IsPrimary = true },
            new UserDepartment { UserId = qasim.Id, DepartmentId = prc.Id, IsPrimary = false }
        );

        if (net is not null)
            db.UserDepartments.Add(new UserDepartment { UserId = qasim.Id, DepartmentId = net.Id, IsPrimary = false });

        await db.SaveChangesAsync(ct);
    }

    private static async Task SeedCompanyAsync(AppDbContext db, CancellationToken ct)
    {
        db.CompanyProfiles.Add(new CompanyProfile
        {
            OrganizationName = "Enterprise Procurement Organization",
            LegalName = "EPIMS Enterprise Ltd.",
            Address = "Head Office",
            City = "Islamabad",
            Country = "Pakistan",
            Email = "info@epims.local",
            Phone = "+92-51-0000000",
            PrimaryColor = "#0B3D5C",
            SecondaryColor = "#1A6B8A",
            AccentColor = "#C45C26",
            FiscalYearStartMonth = 7
        });
        await db.SaveChangesAsync(ct);
    }

    private static async Task SeedSettingsAsync(AppDbContext db, CancellationToken ct)
    {
        db.AppSettings.AddRange(
            new AppSetting { Key = "SessionTimeoutMinutes", Value = "30", Category = "Security" },
            new AppSetting { Key = "MaxFailedLoginAttempts", Value = "5", Category = "Security" },
            new AppSetting { Key = "LockoutMinutes", Value = "15", Category = "Security" },
            new AppSetting { Key = "EnableTwoFactor", Value = "false", Category = "Security" },
            new AppSetting { Key = "Theme", Value = "Light", Category = "UI" },
            new AppSetting { Key = "Language", Value = "en", Category = "UI" },
            new AppSetting { Key = "BackupPath", Value = "Backups", Category = "Backup" },
            new AppSetting { Key = "AutoBackupEnabled", Value = "true", Category = "Backup" }
        );
        await db.SaveChangesAsync(ct);
    }

    private static async Task SeedVendorsAsync(AppDbContext db, CancellationToken ct)
    {
        db.Vendors.AddRange(
            new Vendor
            {
                Code = "VND-00001",
                CompanyName = "TechSource Solutions",
                ContactPerson = "Ali Raza",
                Phone = "+92-300-1111111",
                Email = "sales@techsource.local",
                City = "Karachi",
                NTN = "1234567-8",
                Rating = 4.5m,
                IsActive = true
            },
            new Vendor
            {
                Code = "VND-00002",
                CompanyName = "Office Essentials Co.",
                ContactPerson = "Sara Khan",
                Phone = "+92-300-2222222",
                Email = "info@officeessentials.local",
                City = "Lahore",
                NTN = "2345678-9",
                Rating = 4.0m,
                IsActive = true
            }
        );
        await db.SaveChangesAsync(ct);
    }

    private static async Task SeedSampleInventoryAsync(AppDbContext db, CancellationToken ct)
    {
        var it = await db.Departments.FirstAsync(d => d.Code == "IT", ct);
        var vendor = await db.Vendors.FirstAsync(ct);

        db.InventoryItems.AddRange(
            new InventoryItem
            {
                ItemCode = "IT-AST-00001",
                Name = "Dell Latitude Laptop",
                ItemType = InventoryItemType.Electronics,
                Category = "Computers",
                Brand = "Dell",
                Model = "Latitude 5540",
                Barcode = "BCITAST00001240601",
                SerialNumber = "DL5540-001",
                AssetTag = "AST-IT-001",
                DepartmentId = it.Id,
                Location = "IT Store Room",
                StockQuantity = 12,
                MinimumStock = 3,
                MaximumStock = 50,
                Cost = 185000,
                Status = ItemStatus.Available,
                Condition = ItemCondition.New,
                SupplierId = vendor.Id,
                PurchaseDate = DateTime.UtcNow.AddMonths(-2),
                WarrantyExpiry = DateTime.UtcNow.AddYears(2)
            },
            new InventoryItem
            {
                ItemCode = "IT-CNS-00001",
                Name = "Network Cable Cat6",
                ItemType = InventoryItemType.Consumable,
                Category = "Networking",
                Brand = "Generic",
                DepartmentId = it.Id,
                Location = "IT Store Room",
                StockQuantity = 25,
                MinimumStock = 50,
                MaximumStock = 500,
                Unit = "Meters",
                Cost = 80,
                Status = ItemStatus.Available,
                Condition = ItemCondition.New,
                SupplierId = vendor.Id
            }
        );
        await db.SaveChangesAsync(ct);
    }
}
