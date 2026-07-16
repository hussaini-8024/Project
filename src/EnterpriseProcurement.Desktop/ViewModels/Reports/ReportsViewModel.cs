using System.IO;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;

namespace EnterpriseProcurement.Desktop.ViewModels.Reports;

public partial class ReportsViewModel : ObservableObject
{
    private readonly IReportService _reports;
    private readonly ISessionContext _session;

    [ObservableProperty] private string? _message;

    public ReportsViewModel(IReportService reports, ISessionContext session)
    {
        _reports = reports;
        _session = session;
    }

    [RelayCommand]
    private async Task ExportProcurementAsync()
    {
        var bytes = await _reports.ExportProcurementCsvAsync(_session.IsAdmin ? null : _session.ActiveDepartmentId, null, null);
        await SaveAsync("procurement-report.csv", bytes);
    }

    [RelayCommand]
    private async Task ExportInventoryAsync()
    {
        var bytes = await _reports.ExportInventoryCsvAsync(_session.IsAdmin ? null : _session.ActiveDepartmentId);
        await SaveAsync("inventory-report.csv", bytes);
    }

    [RelayCommand]
    private async Task ExportAllocationsAsync()
    {
        var bytes = await _reports.ExportAllocationCsvAsync(_session.IsAdmin ? null : _session.ActiveDepartmentId);
        await SaveAsync("allocation-report.csv", bytes);
    }

    [RelayCommand]
    private async Task ExportAuditAsync()
    {
        var bytes = await _reports.ExportAuditCsvAsync(null, null);
        await SaveAsync("audit-report.csv", bytes);
    }

    private async Task SaveAsync(string fileName, byte[] bytes)
    {
        var dir = Path.Combine(AppContext.BaseDirectory, "Exports");
        Directory.CreateDirectory(dir);
        var path = Path.Combine(dir, $"{DateTime.Now:yyyyMMdd-HHmmss}-{fileName}");
        await File.WriteAllBytesAsync(path, bytes);
        Message = $"Exported: {path}";
    }
}
