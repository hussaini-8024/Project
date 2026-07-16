using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;
using EnterpriseProcurement.Desktop.Views.Login;

namespace EnterpriseProcurement.Desktop.ViewModels.Dashboard;

public partial class MainShellViewModel : ObservableObject
{
    private readonly ISessionContext _session;
    private readonly INavigationService _navigation;
    private readonly IAuthService _authService;
    private readonly INotificationService _notifications;
    private readonly ISearchService _search;

    [ObservableProperty] private string _userDisplayName = string.Empty;
    [ObservableProperty] private string _roleDisplay = string.Empty;
    [ObservableProperty] private DepartmentDto? _selectedDepartment;
    [ObservableProperty] private ObservableCollection<DepartmentDto> _departments = new();
    [ObservableProperty] private ObservableCollection<MenuPermissionDto> _menus = new();
    [ObservableProperty] private ObservableCollection<NotificationDto> _notificationsList = new();
    [ObservableProperty] private string _searchText = string.Empty;
    [ObservableProperty] private ObservableCollection<SearchResultDto> _searchResults = new();
    [ObservableProperty] private object? _currentContent;
    [ObservableProperty] private int _unreadCount;
    [ObservableProperty] private bool _isDarkMode;

    public MainShellViewModel(
        ISessionContext session,
        INavigationService navigation,
        IAuthService authService,
        INotificationService notifications,
        ISearchService search)
    {
        _session = session;
        _navigation = navigation;
        _authService = authService;
        _notifications = notifications;
        _search = search;

        _navigation.Navigated += (_, view) => CurrentContent = view;
        _ = InitializeAsync();
    }

    private async Task InitializeAsync()
    {
        var s = _session.Current;
        if (s is null) return;

        UserDisplayName = s.FullName;
        RoleDisplay = s.IsSuperAdmin ? "Administrator" : (s.RoleName ?? "User");
        IsDarkMode = s.PreferDarkMode;
        Departments = new ObservableCollection<DepartmentDto>(s.Departments);
        SelectedDepartment = Departments.FirstOrDefault(d => d.Id == s.ActiveDepartmentId) ?? Departments.FirstOrDefault();
        Menus = new ObservableCollection<MenuPermissionDto>(
            s.Permissions.Where(p => p.ParentId is null || p.MenuKey.Contains('.') == false)
                .OrderBy(p => p.SortOrder));

        var notes = await _notifications.GetUnreadAsync(s.UserId);
        NotificationsList = new ObservableCollection<NotificationDto>(notes);
        UnreadCount = notes.Count;

        _navigation.NavigateTo("dashboard");
    }

    [RelayCommand]
    private void Navigate(string? key)
    {
        if (!string.IsNullOrWhiteSpace(key))
            _navigation.NavigateTo(key);
    }

    [RelayCommand]
    private async Task SwitchDepartmentAsync()
    {
        if (_session.Current is null || SelectedDepartment is null) return;
        var updated = await _authService.SwitchDepartmentAsync(
            _session.Current.UserId,
            SelectedDepartment.Id,
            _session.Current.SessionToken);
        if (updated is not null)
        {
            _session.Current = updated;
            Menus = new ObservableCollection<MenuPermissionDto>(updated.Permissions.OrderBy(p => p.SortOrder));
            _navigation.NavigateTo("dashboard");
        }
    }

    [RelayCommand]
    private async Task SearchAsync()
    {
        if (_session.Current is null || string.IsNullOrWhiteSpace(SearchText)) return;
        var results = await _search.SearchAsync(
            SearchText,
            _session.ActiveDepartmentId,
            _session.IsAdmin);
        SearchResults = new ObservableCollection<SearchResultDto>(results);
    }

    [RelayCommand]
    private async Task LogoutAsync()
    {
        if (_session.Current is not null)
            await _authService.LogoutAsync(_session.Current.UserId, _session.Current.SessionToken);

        _session.Clear();
        var login = new LoginWindow();
        login.Show();
        foreach (System.Windows.Window w in System.Windows.Application.Current.Windows)
        {
            if (w is Views.Dashboard.MainWindow)
            {
                w.Close();
                break;
            }
        }
    }

    [RelayCommand]
    private async Task MarkNotificationsReadAsync()
    {
        if (_session.Current is null) return;
        await _notifications.MarkAllReadAsync(_session.Current.UserId);
        UnreadCount = 0;
    }
}
