using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;

namespace EnterpriseProcurement.Desktop.ViewModels.Procurement;

public partial class ProcurementViewModel : ObservableObject
{
    private readonly IProcurementService _service;
    private readonly ISessionContext _session;

    [ObservableProperty] private ObservableCollection<PurchaseRequestDto> _items = new();
    [ObservableProperty] private string _title = string.Empty;
    [ObservableProperty] private string _description = string.Empty;
    [ObservableProperty] private decimal _estimatedBudget = 1000;
    [ObservableProperty] private string _itemName = "New Item";
    [ObservableProperty] private decimal _quantity = 1;
    [ObservableProperty] private decimal _unitPrice = 100;
    [ObservableProperty] private string? _message;
    [ObservableProperty] private PurchaseRequestDto? _selected;

    public ProcurementViewModel(IProcurementService service, ISessionContext session)
    {
        _service = service;
        _session = session;
        _ = LoadAsync();
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        var deptId = _session.IsAdmin ? null : _session.ActiveDepartmentId;
        var page = await _service.GetPagedAsync(deptId, null, null, 1, 200);
        Items = new ObservableCollection<PurchaseRequestDto>(page.Items);
    }

    [RelayCommand]
    private async Task CreateDraftAsync()
    {
        if (_session.Current?.ActiveDepartmentId is null && !_session.IsAdmin)
        {
            Message = "Select a department first.";
            return;
        }

        var deptId = _session.ActiveDepartmentId ?? _session.Current!.Departments.First().Id;
        await _service.CreateAsync(new CreatePurchaseRequestRequest(
            deptId,
            _session.Current!.UserId,
            Title,
            Description,
            null,
            EstimatedBudget,
            DateTime.UtcNow.AddDays(30),
            new[]
            {
                new PurchaseRequestItemDto(Guid.Empty, ItemName, null, null, "Pcs", Quantity, UnitPrice, Quantity * UnitPrice, null)
            }), default);

        Message = "Draft purchase request created.";
        Title = Description = string.Empty;
        await LoadAsync();
    }

    [RelayCommand]
    private async Task SubmitAsync()
    {
        if (Selected is null) return;
        await _service.SubmitAsync(Selected.Id, _session.Current!.UserId);
        Message = "Request submitted for approval.";
        await LoadAsync();
    }

    [RelayCommand]
    private async Task ApproveAsync()
    {
        if (Selected is null) return;
        await _service.ApproveAsync(Selected.Id, _session.Current!.UserId, "Approved");
        Message = "Request approved.";
        await LoadAsync();
    }

    [RelayCommand]
    private async Task RejectAsync()
    {
        if (Selected is null) return;
        await _service.RejectAsync(Selected.Id, _session.Current!.UserId, "Rejected by reviewer");
        Message = "Request rejected.";
        await LoadAsync();
    }
}
