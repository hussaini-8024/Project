using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.DTOs;

public record LoginRequest(
    string Username,
    string Password,
    bool RememberMe = false,
    string? TwoFactorCode = null);

public record LoginResult(
    bool Success,
    string? Message,
    UserSessionDto? Session,
    bool RequiresTwoFactor = false);

public record UserSessionDto(
    Guid UserId,
    string Username,
    string FullName,
    string Email,
    bool IsSuperAdmin,
    Guid? RoleId,
    string? RoleName,
    Guid? ActiveDepartmentId,
    string? ActiveDepartmentName,
    IReadOnlyList<DepartmentDto> Departments,
    IReadOnlyList<MenuPermissionDto> Permissions,
    string SessionToken,
    bool PreferDarkMode,
    string PreferredLanguage);

public record ForgotPasswordRequest(string UsernameOrEmail);
public record ResetPasswordRequest(string Token, string NewPassword);
public record ChangePasswordRequest(Guid UserId, string CurrentPassword, string NewPassword);
