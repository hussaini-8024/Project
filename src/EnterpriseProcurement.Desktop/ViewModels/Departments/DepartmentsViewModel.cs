using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;

namespace EnterpriseProcurement.Desktop.ViewModels.Departments;

public partial class DepartmentsViewModel : ObservableObject
{
    private readonly IDepartmentService _service;
    private readonly ISessionContext _session;

    [ObservableProperty] private ObservableCollection<DepartmentDto> _items = new();
    [ObservableProperty] private string _searchText = string.Empty;
    [ObservableProperty] private string _code = string.Empty;
    [ObservableProperty] private string _name = string.Empty;
    [ObservableProperty] private string? _description;
    [ObservableProperty] private string? _headName;
    [ObservableProperty] private string? _message;
    [ObservableProperty] private DepartmentDto? _selected;

    public DepartmentsViewModel(IDepartmentService service, ISessionContext session)
    {
        _service = service;
        _session = session;
        _ = LoadAsync();
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        var page = await _service.GetPagedAsync(SearchText, 1, 200);
        Items = new ObservableCollection<DepartmentDto>(page.Items);
    }

    [RelayCommand]
    private async Task SaveAsync()
    {
        if (string.IsNullOrWhiteSpace(Code) || string.IsNullOrWhiteSpace(Name))
        {
            Message = "Code and Name are required.";
            return;
        }

        var actor = _session.Current!.UserId;
        if (Selected is null)
        {
            await _service.CreateAsync(new CreateDepartmentRequest(Code, Name, Description, HeadName, null, null, null, null), actor);
            Message = "Department created.";
        }
        else
        {
            await _service.UpdateAsync(new UpdateDepartmentRequest(
                Selected.Id, Code, Name, Description, HeadName, null, null, null, Selected.AnnualBudget, Selected.Status), actor);
            Message = "Department updated.";
        }

        ClearForm();
        await LoadAsync();
    }

    [RelayCommand]
    private void Edit()
    {
        if (Selected is null) return;
        Code = Selected.Code;
        Name = Selected.Name;
        Description = Selected.Description;
        HeadName = Selected.HeadName;
    }

    [RelayCommand]
    private async Task DeactivateAsync()
    {
        if (Selected is null) return;
        await _service.DeactivateAsync(Selected.Id, _session.Current!.UserId);
        Message = "Department deactivated.";
        await LoadAsync();
    }

    [RelayCommand]
    private void ClearForm()
    {
        Selected = null;
        Code = Name = string.Empty;
        Description = HeadName = null;
    }
}
