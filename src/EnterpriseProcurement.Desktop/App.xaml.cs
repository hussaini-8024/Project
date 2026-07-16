using System.IO;
using System.Windows;
using EnterpriseProcurement.Business;
using EnterpriseProcurement.Data.Context;
using EnterpriseProcurement.Data.Seed;
using EnterpriseProcurement.Desktop.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;

namespace EnterpriseProcurement.Desktop;

public partial class App : Application
{
    public static IHost AppHost { get; private set; } = null!;
    public static IServiceProvider Services => AppHost.Services;

    protected override async void OnStartup(StartupEventArgs e)
    {
        base.OnStartup(e);

        var dbPath = Path.Combine(AppContext.BaseDirectory, "epims.db");

        AppHost = Host.CreateDefaultBuilder()
            .ConfigureAppConfiguration(cfg =>
            {
                cfg.SetBasePath(AppContext.BaseDirectory);
                cfg.AddJsonFile("appsettings.json", optional: true, reloadOnChange: true);
            })
            .ConfigureServices((context, services) =>
            {
                var config = context.Configuration;
                var sqlitePath = config["Database:SqlitePath"] ?? dbPath;
                if (!Path.IsPathRooted(sqlitePath))
                    sqlitePath = Path.Combine(AppContext.BaseDirectory, sqlitePath);

                var connectionString = config.GetConnectionString("DefaultConnection")
                    ?? $"Data Source={sqlitePath}";

                // Ensure Sqlite path is absolute for backup service consistency
                var mutable = new ConfigurationBuilder()
                    .AddConfiguration(config)
                    .AddInMemoryCollection(new Dictionary<string, string?>
                    {
                        ["Database:SqlitePath"] = sqlitePath,
                        ["ConnectionStrings:DefaultConnection"] = connectionString.Contains("Data Source=") && !connectionString.Contains(":\\") && !connectionString.Contains("=/")
                            ? $"Data Source={sqlitePath}"
                            : connectionString
                    })
                    .Build();

                services.AddSingleton<IConfiguration>(mutable);
                services.AddBusinessLayer(mutable);
                services.AddSingleton<ISessionContext, SessionContext>();
                services.AddSingleton<INavigationService, NavigationService>();
                services.AddTransient<ViewModels.Login.LoginViewModel>();
                services.AddTransient<ViewModels.Dashboard.MainShellViewModel>();
                services.AddTransient<ViewModels.Dashboard.DashboardViewModel>();
                services.AddTransient<ViewModels.Departments.DepartmentsViewModel>();
                services.AddTransient<ViewModels.Users.UsersViewModel>();
                services.AddTransient<ViewModels.Procurement.ProcurementViewModel>();
                services.AddTransient<ViewModels.Inventory.InventoryViewModel>();
                services.AddTransient<ViewModels.Allocation.AllocationViewModel>();
                services.AddTransient<ViewModels.Vendors.VendorsViewModel>();
                services.AddTransient<ViewModels.Reports.ReportsViewModel>();
                services.AddTransient<ViewModels.Audit.AuditViewModel>();
                services.AddTransient<ViewModels.Settings.SettingsViewModel>();
            })
            .Build();

        await AppHost.StartAsync();

        using var scope = Services.CreateScope();
        var db = scope.ServiceProvider.GetRequiredService<AppDbContext>();
        await db.Database.EnsureCreatedAsync();
        await DbSeeder.SeedAsync(db);

        DispatcherUnhandledException += (_, args) =>
        {
            MessageBox.Show(args.Exception.Message, "Unexpected Error", MessageBoxButton.OK, MessageBoxImage.Error);
            args.Handled = true;
        };

        var login = new Views.Login.LoginWindow();
        MainWindow = login;
        login.Show();
    }

    protected override async void OnExit(ExitEventArgs e)
    {
        if (AppHost is not null)
            await AppHost.StopAsync();
        base.OnExit(e);
    }
}
