using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;

namespace EnterpriseProcurement.Desktop.ViewModels.Vendors;

public partial class VendorsViewModel : ObservableObject
{
    private readonly IVendorService _service;
    private readonly ISessionContext _session;

    [ObservableProperty] private ObservableCollection<VendorDto> _items = new();
    [ObservableProperty] private string _companyName = string.Empty;
    [ObservableProperty] private string? _contactPerson;
    [ObservableProperty] private string? _phone;
    [ObservableProperty] private string? _email;
    [ObservableProperty] private string? _ntn;
    [ObservableProperty] private string? _message;

    public VendorsViewModel(IVendorService service, ISessionContext session)
    {
        _service = service;
        _session = session;
        _ = LoadAsync();
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        var page = await _service.GetPagedAsync(null, 1, 200);
        Items = new ObservableCollection<VendorDto>(page.Items);
    }

    [RelayCommand]
    private async Task CreateAsync()
    {
        await _service.CreateAsync(new CreateVendorRequest(
            CompanyName, ContactPerson, null, null, null, Phone, Email, Ntn, null, null, null, null, null),
            _session.Current!.UserId);
        Message = "Vendor created.";
        CompanyName = string.Empty;
        await LoadAsync();
    }
}
