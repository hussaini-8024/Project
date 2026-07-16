namespace EnterpriseProcurement.Core.Constants;

public static class AppConstants
{
    public const string ApplicationName = "Enterprise Procurement & Inventory Management System";
    public const string ApplicationShortName = "EPIMS";
    public const string Version = "1.0.0";
    public const int MaxFailedLoginAttempts = 5;
    public const int LockoutMinutes = 15;
    public const int SessionTimeoutMinutes = 30;
    public const int PasswordResetTokenHours = 24;
    public const int RememberMeDays = 30;
    public const string DefaultAdminUsername = "admin";
    public const string DefaultAdminPassword = "Admin@123";
    public const string DefaultConnectionKey = "DefaultConnection";
    public const string DatabaseProviderKey = "DatabaseProvider";
}
