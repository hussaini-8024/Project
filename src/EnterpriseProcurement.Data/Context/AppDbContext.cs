using EnterpriseProcurement.Core.Entities;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Data.Context;

public class AppDbContext : DbContext
{
    public AppDbContext(DbContextOptions<AppDbContext> options) : base(options)
    {
    }

    public DbSet<Department> Departments => Set<Department>();
    public DbSet<User> Users => Set<User>();
    public DbSet<Role> Roles => Set<Role>();
    public DbSet<Menu> Menus => Set<Menu>();
    public DbSet<UserDepartment> UserDepartments => Set<UserDepartment>();
    public DbSet<RoleMenuPermission> RoleMenuPermissions => Set<RoleMenuPermission>();
    public DbSet<UserMenuPermission> UserMenuPermissions => Set<UserMenuPermission>();
    public DbSet<Vendor> Vendors => Set<Vendor>();
    public DbSet<VendorDocument> VendorDocuments => Set<VendorDocument>();
    public DbSet<PurchaseRequest> PurchaseRequests => Set<PurchaseRequest>();
    public DbSet<PurchaseRequestItem> PurchaseRequestItems => Set<PurchaseRequestItem>();
    public DbSet<Quotation> Quotations => Set<Quotation>();
    public DbSet<ApprovalHistory> ApprovalHistories => Set<ApprovalHistory>();
    public DbSet<InventoryItem> InventoryItems => Set<InventoryItem>();
    public DbSet<StockMovement> StockMovements => Set<StockMovement>();
    public DbSet<Allocation> Allocations => Set<Allocation>();
    public DbSet<Attachment> Attachments => Set<Attachment>();
    public DbSet<AuditLog> AuditLogs => Set<AuditLog>();
    public DbSet<Notification> Notifications => Set<Notification>();
    public DbSet<AppSetting> AppSettings => Set<AppSetting>();
    public DbSet<CompanyProfile> CompanyProfiles => Set<CompanyProfile>();
    public DbSet<UserSession> UserSessions => Set<UserSession>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        base.OnModelCreating(modelBuilder);

        modelBuilder.Entity<Department>(e =>
        {
            e.HasIndex(x => x.Code).IsUnique();
            e.Property(x => x.Code).HasMaxLength(20);
            e.Property(x => x.Name).HasMaxLength(150);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<User>(e =>
        {
            e.HasIndex(x => x.Username).IsUnique();
            e.HasIndex(x => x.Email).IsUnique();
            e.Property(x => x.Username).HasMaxLength(80);
            e.Property(x => x.Email).HasMaxLength(200);
            e.Property(x => x.FullName).HasMaxLength(200);
            e.HasOne(x => x.Role).WithMany(x => x.Users).HasForeignKey(x => x.RoleId).OnDelete(DeleteBehavior.SetNull);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<Role>(e =>
        {
            e.HasIndex(x => x.Name).IsUnique();
            e.Property(x => x.Name).HasMaxLength(100);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<Menu>(e =>
        {
            e.HasIndex(x => x.Key).IsUnique();
            e.Property(x => x.Key).HasMaxLength(100);
            e.Property(x => x.Title).HasMaxLength(150);
            e.HasOne(x => x.Parent).WithMany(x => x.Children).HasForeignKey(x => x.ParentId).OnDelete(DeleteBehavior.Restrict);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<UserDepartment>(e =>
        {
            e.HasIndex(x => new { x.UserId, x.DepartmentId }).IsUnique();
            e.HasOne(x => x.User).WithMany(x => x.UserDepartments).HasForeignKey(x => x.UserId).OnDelete(DeleteBehavior.Cascade);
            e.HasOne(x => x.Department).WithMany(x => x.UserDepartments).HasForeignKey(x => x.DepartmentId).OnDelete(DeleteBehavior.Cascade);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<RoleMenuPermission>(e =>
        {
            e.HasIndex(x => new { x.RoleId, x.MenuId }).IsUnique();
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<UserMenuPermission>(e =>
        {
            e.HasIndex(x => new { x.UserId, x.MenuId, x.DepartmentId }).IsUnique();
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<Vendor>(e =>
        {
            e.HasIndex(x => x.Code).IsUnique();
            e.Property(x => x.CompanyName).HasMaxLength(200);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<PurchaseRequest>(e =>
        {
            e.HasIndex(x => x.RequestNumber).IsUnique();
            e.Property(x => x.Title).HasMaxLength(250);
            e.Property(x => x.EstimatedBudget).HasPrecision(18, 2);
            e.Property(x => x.ApprovedBudget).HasPrecision(18, 2);
            e.Property(x => x.InvoiceAmount).HasPrecision(18, 2);
            e.HasOne(x => x.Department).WithMany(x => x.PurchaseRequests).HasForeignKey(x => x.DepartmentId).OnDelete(DeleteBehavior.Restrict);
            e.HasOne(x => x.RequestedBy).WithMany().HasForeignKey(x => x.RequestedById).OnDelete(DeleteBehavior.Restrict);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<PurchaseRequestItem>(e =>
        {
            e.Property(x => x.Quantity).HasPrecision(18, 2);
            e.Property(x => x.UnitPrice).HasPrecision(18, 2);
            e.Ignore(x => x.TotalPrice);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<Quotation>(e =>
        {
            e.Property(x => x.TotalAmount).HasPrecision(18, 2);
            e.Property(x => x.TaxAmount).HasPrecision(18, 2);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<InventoryItem>(e =>
        {
            e.HasIndex(x => x.ItemCode).IsUnique();
            e.HasIndex(x => x.Barcode);
            e.HasIndex(x => x.AssetTag);
            e.Property(x => x.Name).HasMaxLength(250);
            e.Property(x => x.StockQuantity).HasPrecision(18, 2);
            e.Property(x => x.MinimumStock).HasPrecision(18, 2);
            e.Property(x => x.MaximumStock).HasPrecision(18, 2);
            e.Property(x => x.Cost).HasPrecision(18, 2);
            e.Ignore(x => x.IsLowStock);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<StockMovement>(e =>
        {
            e.Property(x => x.Quantity).HasPrecision(18, 2);
            e.Property(x => x.QuantityBefore).HasPrecision(18, 2);
            e.Property(x => x.QuantityAfter).HasPrecision(18, 2);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<Allocation>(e =>
        {
            e.HasIndex(x => x.AllocationNumber).IsUnique();
            e.Property(x => x.Quantity).HasPrecision(18, 2);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<AuditLog>(e =>
        {
            e.HasIndex(x => x.ActionAt);
            e.HasIndex(x => x.Module);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<Notification>(e =>
        {
            e.HasIndex(x => new { x.UserId, x.IsRead });
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<AppSetting>(e =>
        {
            e.HasIndex(x => x.Key).IsUnique();
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<CompanyProfile>(e =>
        {
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<UserSession>(e =>
        {
            e.HasIndex(x => x.SessionToken);
            e.HasQueryFilter(x => !x.IsDeleted);
        });

        modelBuilder.Entity<Attachment>(e => e.HasQueryFilter(x => !x.IsDeleted));
        modelBuilder.Entity<VendorDocument>(e => e.HasQueryFilter(x => !x.IsDeleted));
        modelBuilder.Entity<ApprovalHistory>(e => e.HasQueryFilter(x => !x.IsDeleted));
    }

    public override Task<int> SaveChangesAsync(CancellationToken cancellationToken = default)
    {
        var now = DateTime.UtcNow;
        foreach (var entry in ChangeTracker.Entries<BaseEntity>())
        {
            if (entry.State == EntityState.Modified)
                entry.Entity.UpdatedAt = now;
        }

        return base.SaveChangesAsync(cancellationToken);
    }
}
