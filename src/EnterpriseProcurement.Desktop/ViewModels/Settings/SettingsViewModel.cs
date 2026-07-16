using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.Interfaces;
using CompanyProfileDto = EnterpriseProcurement.Core.Interfaces.CompanyProfileDto;

namespace EnterpriseProcurement.Desktop.ViewModels.Settings;

public partial class SettingsViewModel : ObservableObject
{
    private readonly ISettingsService _settings;
    private readonly IBackupService _backup;

    [ObservableProperty] private string _organizationName = string.Empty;
    [ObservableProperty] private string? _email;
    [ObservableProperty] private string? _phone;
    [ObservableProperty] private string? _primaryColor = "#0B3D5C";
    [ObservableProperty] private string? _message;
    [ObservableProperty] private string? _lastBackupPath;
    private CompanyProfileDto? _profile;

    public SettingsViewModel(ISettingsService settings, IBackupService backup)
    {
        _settings = settings;
        _backup = backup;
        _ = LoadAsync();
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        _profile = await _settings.GetCompanyProfileAsync();
        if (_profile is null) return;
        OrganizationName = _profile.OrganizationName;
        Email = _profile.Email;
        Phone = _profile.Phone;
        PrimaryColor = _profile.PrimaryColor;
    }

    [RelayCommand]
    private async Task SaveAsync()
    {
        if (_profile is null) return;
        await _settings.SaveCompanyProfileAsync(_profile with
        {
            OrganizationName = OrganizationName,
            Email = Email,
            Phone = Phone,
            PrimaryColor = PrimaryColor
        });
        Message = "Settings saved.";
    }

    [RelayCommand]
    private async Task BackupAsync()
    {
        LastBackupPath = await _backup.BackupAsync();
        Message = $"Backup created: {LastBackupPath}";
    }

    [RelayCommand]
    private async Task RestoreAsync()
    {
        var backups = await _backup.ListBackupsAsync();
        var latest = backups.FirstOrDefault();
        if (latest is null)
        {
            Message = "No backups found.";
            return;
        }

        await _backup.RestoreAsync(latest);
        Message = $"Restored from: {latest}. Restart the application.";
    }
}
