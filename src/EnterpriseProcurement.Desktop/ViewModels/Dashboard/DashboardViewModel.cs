using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;

namespace EnterpriseProcurement.Desktop.ViewModels.Dashboard;

public partial class DashboardViewModel : ObservableObject
{
    private readonly IDashboardService _dashboard;
    private readonly ISessionContext _session;

    [ObservableProperty] private DashboardStatsDto? _stats;
    [ObservableProperty] private string _welcomeTitle = "Dashboard";
    [ObservableProperty] private bool _isAdmin;

    public DashboardViewModel(IDashboardService dashboard, ISessionContext session)
    {
        _dashboard = dashboard;
        _session = session;
        _ = LoadAsync();
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        IsAdmin = _session.IsAdmin;
        WelcomeTitle = _session.IsAdmin
            ? "Administrator Dashboard"
            : $"{_session.Current?.ActiveDepartmentName ?? "Department"} Dashboard";

        Stats = _session.IsAdmin
            ? await _dashboard.GetAdminDashboardAsync()
            : await _dashboard.GetUserDashboardAsync(
                _session.Current!.UserId,
                _session.ActiveDepartmentId);
    }
}
