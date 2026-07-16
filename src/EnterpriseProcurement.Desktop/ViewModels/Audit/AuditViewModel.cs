using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Interfaces;

namespace EnterpriseProcurement.Desktop.ViewModels.Audit;

public partial class AuditViewModel : ObservableObject
{
    private readonly IAuditService _audit;

    [ObservableProperty] private ObservableCollection<AuditLogDto> _items = new();
    [ObservableProperty] private string _searchText = string.Empty;

    public AuditViewModel(IAuditService audit)
    {
        _audit = audit;
        _ = LoadAsync();
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        var page = await _audit.GetPagedAsync(SearchText, null, null, null, null, 1, 200);
        Items = new ObservableCollection<AuditLogDto>(page.Items);
    }
}
