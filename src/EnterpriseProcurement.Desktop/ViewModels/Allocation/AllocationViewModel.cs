using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;

namespace EnterpriseProcurement.Desktop.ViewModels.Allocation;

public partial class AllocationViewModel : ObservableObject
{
    private readonly IAllocationService _allocations;
    private readonly IInventoryService _inventory;
    private readonly ISessionContext _session;

    [ObservableProperty] private ObservableCollection<AllocationDto> _items = new();
    [ObservableProperty] private ObservableCollection<InventoryItemDto> _availableItems = new();
    [ObservableProperty] private InventoryItemDto? _selectedItem;
    [ObservableProperty] private string _employeeName = string.Empty;
    [ObservableProperty] private string? _office;
    [ObservableProperty] private string? _message;
    [ObservableProperty] private AllocationDto? _selected;

    public AllocationViewModel(IAllocationService allocations, IInventoryService inventory, ISessionContext session)
    {
        _allocations = allocations;
        _inventory = inventory;
        _session = session;
        _ = LoadAsync();
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        var deptId = _session.IsAdmin ? null : _session.ActiveDepartmentId;
        var page = await _allocations.GetPagedAsync(deptId, null, 1, 200);
        Items = new ObservableCollection<AllocationDto>(page.Items);
        var inv = await _inventory.GetPagedAsync(deptId, null, false, 1, 200);
        AvailableItems = new ObservableCollection<InventoryItemDto>(inv.Items.Where(i => i.StockQuantity > 0));
    }

    [RelayCommand]
    private async Task CreateAsync()
    {
        if (SelectedItem is null || _session.ActiveDepartmentId is null && !_session.IsAdmin)
        {
            Message = "Select an item and department.";
            return;
        }

        var deptId = _session.ActiveDepartmentId ?? SelectedItem.DepartmentId ?? Guid.Empty;
        await _allocations.CreateAsync(new CreateAllocationRequest(
            SelectedItem.Id, deptId, _session.Current!.UserId, null, EmployeeName, Office,
            SelectedItem.Location, 1, DateTime.UtcNow.AddMonths(6), ItemCondition.Good, null));

        Message = "Allocation request created.";
        await LoadAsync();
    }

    [RelayCommand]
    private async Task ApproveAsync()
    {
        if (Selected is null) return;
        await _allocations.ApproveAsync(Selected.Id, _session.Current!.UserId);
        Message = "Allocation issued.";
        await LoadAsync();
    }

    [RelayCommand]
    private async Task ReturnAsync()
    {
        if (Selected is null) return;
        await _allocations.ReturnAsync(Selected.Id, ItemCondition.Good, "Returned in good condition", _session.Current!.UserId);
        Message = "Item returned to stock.";
        await LoadAsync();
    }
}
