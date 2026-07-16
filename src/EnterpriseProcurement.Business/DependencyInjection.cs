using EnterpriseProcurement.Business.Services;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Business;

public static class DependencyInjection
{
    public static IServiceCollection AddBusinessLayer(this IServiceCollection services, IConfiguration configuration)
    {
        var provider = configuration["Database:Provider"] ?? "Sqlite";
        var connectionString = configuration.GetConnectionString("DefaultConnection")
            ?? configuration["Database:ConnectionString"]
            ?? $"Data Source={Path.Combine(AppContext.BaseDirectory, "epims.db")}";

        // Desktop apps resolve services from the root provider; use Singleton lifetimes.
        var lifetime = ServiceLifetime.Singleton;
        services.AddDataLayer(connectionString, provider, lifetime);

        services.AddSingleton<IAuditService, AuditService>();
        services.AddSingleton<INotificationService, NotificationService>();
        services.AddSingleton<IPermissionService, PermissionService>();
        services.AddSingleton<IAuthService, AuthService>();
        services.AddSingleton<IDepartmentService, DepartmentService>();
        services.AddSingleton<IUserService, UserService>();
        services.AddSingleton<IVendorService, VendorService>();
        services.AddSingleton<IInventoryService, InventoryService>();
        services.AddSingleton<IProcurementService, ProcurementService>();
        services.AddSingleton<IAllocationService, AllocationService>();
        services.AddSingleton<IDashboardService, DashboardService>();
        services.AddSingleton<ISearchService, SearchService>();
        services.AddSingleton<ISettingsService, SettingsService>();
        services.AddSingleton<IBackupService, BackupService>();
        services.AddSingleton<IReportService, ReportService>();

        return services;
    }
}
