using System.Windows;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Desktop.Services;
using EnterpriseProcurement.Desktop.Views.Dashboard;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.ViewModels.Login;

public partial class LoginViewModel : ObservableObject
{
    private readonly IAuthService _authService;
    private readonly ISessionContext _session;

    [ObservableProperty] private string _username = "admin";
    [ObservableProperty] private string _password = string.Empty;
    [ObservableProperty] private bool _rememberMe;
    [ObservableProperty] private string? _errorMessage;
    [ObservableProperty] private bool _isBusy;
    [ObservableProperty] private bool _requiresTwoFactor;
    [ObservableProperty] private string? _twoFactorCode;
    [ObservableProperty] private string? _statusMessage;

    public LoginViewModel(IAuthService authService, ISessionContext session)
    {
        _authService = authService;
        _session = session;
    }

    [RelayCommand]
    private async Task LoginAsync()
    {
        ErrorMessage = null;
        StatusMessage = null;

        if (string.IsNullOrWhiteSpace(Username) || string.IsNullOrWhiteSpace(Password))
        {
            ErrorMessage = "Username and password are required.";
            return;
        }

        try
        {
            IsBusy = true;
            var result = await _authService.LoginAsync(
                new LoginRequest(Username.Trim(), Password, RememberMe, TwoFactorCode),
                Environment.MachineName,
                "127.0.0.1");

            if (result.RequiresTwoFactor)
            {
                RequiresTwoFactor = true;
                StatusMessage = "Enter your two-factor authentication code.";
                return;
            }

            if (!result.Success || result.Session is null)
            {
                ErrorMessage = result.Message ?? "Login failed.";
                return;
            }

            _session.Current = result.Session;
            var shell = App.Services.GetRequiredService<MainShellViewModel>();
            var window = new MainWindow { DataContext = shell };
            window.Show();

            foreach (Window w in Application.Current.Windows)
            {
                if (w is Views.Login.LoginWindow)
                {
                    w.Close();
                    break;
                }
            }
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
        finally
        {
            IsBusy = false;
        }
    }

    [RelayCommand]
    private async Task ForgotPasswordAsync()
    {
        if (string.IsNullOrWhiteSpace(Username))
        {
            ErrorMessage = "Enter your username or email first.";
            return;
        }

        await _authService.ForgotPasswordAsync(new ForgotPasswordRequest(Username.Trim()));
        StatusMessage = "If the account exists, a password reset token was generated. Contact your administrator.";
    }
}
