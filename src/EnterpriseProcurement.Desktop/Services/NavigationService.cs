using System.Windows.Controls;

namespace EnterpriseProcurement.Desktop.Services;

public interface INavigationService
{
    event EventHandler<UserControl?>? Navigated;
    void NavigateTo(string viewKey);
    string? CurrentView { get; }
}

public class NavigationService : INavigationService
{
    public event EventHandler<UserControl?>? Navigated;
    public string? CurrentView { get; private set; }

    public void NavigateTo(string viewKey)
    {
        CurrentView = viewKey;
        UserControl? view = viewKey switch
        {
            "dashboard" => new Views.Dashboard.DashboardView(),
            "departments" => new Views.Departments.DepartmentsView(),
            "users" => new Views.Users.UsersView(),
            "procurement" or "procurement.requests" => new Views.Procurement.ProcurementView(),
            "inventory" or "inventory.items" => new Views.Inventory.InventoryView(),
            "allocations" => new Views.Allocation.AllocationView(),
            "vendors" => new Views.Vendors.VendorsView(),
            "reports" => new Views.Reports.ReportsView(),
            "audit" => new Views.Audit.AuditView(),
            "settings" or "backup" or "settings.company" => new Views.Settings.SettingsView(),
            _ => new Views.Dashboard.DashboardView()
        };

        Navigated?.Invoke(this, view);
    }
}
