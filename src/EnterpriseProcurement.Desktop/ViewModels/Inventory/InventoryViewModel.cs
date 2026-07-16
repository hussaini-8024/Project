using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;

namespace EnterpriseProcurement.Desktop.ViewModels.Inventory;

public partial class InventoryViewModel : ObservableObject
{
    private readonly IInventoryService _service;
    private readonly ISessionContext _session;

    [ObservableProperty] private ObservableCollection<InventoryItemDto> _items = new();
    [ObservableProperty] private string _searchText = string.Empty;
    [ObservableProperty] private bool _lowStockOnly;
    [ObservableProperty] private string _name = string.Empty;
    [ObservableProperty] private string? _category = "General";
    [ObservableProperty] private decimal _stockQuantity = 10;
    [ObservableProperty] private decimal _minimumStock = 2;
    [ObservableProperty] private decimal _cost = 100;
    [ObservableProperty] private string? _message;
    [ObservableProperty] private InventoryItemDto? _selected;
    [ObservableProperty] private string _barcodeLookup = string.Empty;

    public InventoryViewModel(IInventoryService service, ISessionContext session)
    {
        _service = service;
        _session = session;
        _ = LoadAsync();
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        var deptId = _session.IsAdmin ? null : _session.ActiveDepartmentId;
        var page = await _service.GetPagedAsync(deptId, SearchText, LowStockOnly, 1, 200);
        Items = new ObservableCollection<InventoryItemDto>(page.Items);
    }

    [RelayCommand]
    private async Task CreateAsync()
    {
        var deptId = _session.ActiveDepartmentId ?? _session.Current?.Departments.FirstOrDefault()?.Id;
        await _service.CreateAsync(new CreateInventoryItemRequest(
            Name, InventoryItemType.Asset, Category, null, null, null, deptId, "Store",
            StockQuantity, MinimumStock, StockQuantity * 5, "Pcs", DateTime.UtcNow, null, null,
            null, Cost, ItemCondition.New, null), _session.Current!.UserId);

        Message = "Inventory item created with barcode/QR codes.";
        Name = string.Empty;
        await LoadAsync();
    }

    [RelayCommand]
    private async Task LookupBarcodeAsync()
    {
        if (string.IsNullOrWhiteSpace(BarcodeLookup)) return;
        var item = await _service.GetByBarcodeAsync(BarcodeLookup.Trim());
        Message = item is null ? "No item found for barcode." : $"Found: {item.Name} ({item.ItemCode}) — Stock {item.StockQuantity}";
        if (item is not null) Selected = item;
    }
}
