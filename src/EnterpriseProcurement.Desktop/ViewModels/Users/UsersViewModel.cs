using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;

namespace EnterpriseProcurement.Desktop.ViewModels.Users;

public partial class UsersViewModel : ObservableObject
{
    private readonly IUserService _users;
    private readonly IDepartmentService _departments;
    private readonly ISessionContext _session;

    [ObservableProperty] private ObservableCollection<UserDto> _items = new();
    [ObservableProperty] private ObservableCollection<DepartmentDto> _allDepartments = new();
    [ObservableProperty] private string _searchText = string.Empty;
    [ObservableProperty] private string _username = string.Empty;
    [ObservableProperty] private string _email = string.Empty;
    [ObservableProperty] private string _fullName = string.Empty;
    [ObservableProperty] private string _password = "Welcome@123";
    [ObservableProperty] private string? _message;
    [ObservableProperty] private UserDto? _selected;
    [ObservableProperty] private DepartmentDto? _primaryDepartment;

    public UsersViewModel(IUserService users, IDepartmentService departments, ISessionContext session)
    {
        _users = users;
        _departments = departments;
        _session = session;
        _ = LoadAsync();
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        var page = await _users.GetPagedAsync(SearchText, null, 1, 200);
        Items = new ObservableCollection<UserDto>(page.Items);
        AllDepartments = new ObservableCollection<DepartmentDto>(await _departments.GetAllActiveAsync());
        PrimaryDepartment ??= AllDepartments.FirstOrDefault();
    }

    [RelayCommand]
    private async Task CreateAsync()
    {
        if (PrimaryDepartment is null)
        {
            Message = "Select a primary department.";
            return;
        }

        await _users.CreateAsync(new CreateUserRequest(
            Username, Email, Password, FullName, null, null, null, null, false,
            new[] { PrimaryDepartment.Id }, PrimaryDepartment.Id), _session.Current!.UserId);

        Message = "User created.";
        Username = Email = FullName = string.Empty;
        await LoadAsync();
    }

    [RelayCommand]
    private async Task ResetPasswordAsync()
    {
        if (Selected is null) return;
        await _users.ResetPasswordAsync(Selected.Id, "Welcome@123", _session.Current!.UserId);
        Message = $"Password reset for {Selected.Username}.";
    }

    [RelayCommand]
    private async Task ToggleStatusAsync()
    {
        if (Selected is null) return;
        var next = Selected.Status == UserStatus.Active ? UserStatus.Inactive : UserStatus.Active;
        await _users.SetStatusAsync(Selected.Id, next, _session.Current!.UserId);
        await LoadAsync();
    }
}
