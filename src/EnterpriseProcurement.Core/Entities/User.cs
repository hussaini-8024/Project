using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Entities;

public class User : BaseEntity
{
    public string Username { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public string PasswordHash { get; set; } = string.Empty;
    public string FullName { get; set; } = string.Empty;
    public string? Phone { get; set; }
    public string? EmployeeCode { get; set; }
    public string? Designation { get; set; }
    public Guid? RoleId { get; set; }
    public Role? Role { get; set; }
    public UserStatus Status { get; set; } = UserStatus.Active;
    public bool IsSuperAdmin { get; set; }
    public bool TwoFactorEnabled { get; set; }
    public string? TwoFactorSecret { get; set; }
    public int FailedLoginAttempts { get; set; }
    public DateTime? LockoutEnd { get; set; }
    public DateTime? LastLoginAt { get; set; }
    public string? PasswordResetToken { get; set; }
    public DateTime? PasswordResetExpiry { get; set; }
    public string? RememberToken { get; set; }
    public string? ProfileImagePath { get; set; }
    public string? PreferredLanguage { get; set; } = "en";
    public bool PreferDarkMode { get; set; }

    public ICollection<UserDepartment> UserDepartments { get; set; } = new List<UserDepartment>();
    public ICollection<UserMenuPermission> MenuPermissions { get; set; } = new List<UserMenuPermission>();
    public ICollection<AuditLog> AuditLogs { get; set; } = new List<AuditLog>();
    public ICollection<Notification> Notifications { get; set; } = new List<Notification>();
}
