using EnterpriseProcurement.Core.Constants;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Helpers;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Core.Security;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class AuthService : IAuthService
{
    private readonly AppDbContext _db;
    private readonly IPermissionService _permissions;
    private readonly IAuditService _audit;

    public AuthService(AppDbContext db, IPermissionService permissions, IAuditService audit)
    {
        _db = db;
        _permissions = permissions;
        _audit = audit;
    }

    public async Task<LoginResult> LoginAsync(
        LoginRequest request,
        string? computerName = null,
        string? ipAddress = null,
        CancellationToken ct = default)
    {
        var username = request.Username?.Trim() ?? string.Empty;
        var user = await _db.Users
            .Include(u => u.Role)
            .Include(u => u.UserDepartments).ThenInclude(ud => ud.Department)
            .FirstOrDefaultAsync(u => u.Username == username || u.Email == username, ct);

        if (user is null)
            return new LoginResult(false, "Invalid username or password.", null);

        if (user.Status == UserStatus.Inactive)
            return new LoginResult(false, "Account is deactivated. Contact administrator.", null);

        if (user.Status == UserStatus.Locked || (user.LockoutEnd.HasValue && user.LockoutEnd > DateTime.UtcNow))
            return new LoginResult(false, "Account is locked due to multiple failed attempts. Try again later.", null);

        if (!PasswordHasher.Verify(request.Password, user.PasswordHash))
        {
            user.FailedLoginAttempts++;
            if (user.FailedLoginAttempts >= AppConstants.MaxFailedLoginAttempts)
            {
                user.Status = UserStatus.Locked;
                user.LockoutEnd = DateTime.UtcNow.AddMinutes(AppConstants.LockoutMinutes);
            }
            await _db.SaveChangesAsync(ct);
            await _audit.LogAsync(user.Id, null, "Auth", "LoginFailed", "User", user.Id,
                computerName: computerName, ipAddress: ipAddress, details: "Invalid password", ct: ct);
            return new LoginResult(false, "Invalid username or password.", null);
        }

        if (user.TwoFactorEnabled)
        {
            if (string.IsNullOrWhiteSpace(request.TwoFactorCode))
                return new LoginResult(false, "Two-factor authentication code required.", null, RequiresTwoFactor: true);

            // Optional TOTP placeholder: accept matching secret for demo/local use
            if (!string.Equals(request.TwoFactorCode, user.TwoFactorSecret, StringComparison.Ordinal))
                return new LoginResult(false, "Invalid two-factor authentication code.", null, RequiresTwoFactor: true);
        }

        user.FailedLoginAttempts = 0;
        user.LockoutEnd = null;
        user.Status = UserStatus.Active;
        user.LastLoginAt = DateTime.UtcNow;

        var primaryDept = user.UserDepartments.FirstOrDefault(d => d.IsPrimary && d.IsActive)
            ?? user.UserDepartments.FirstOrDefault(d => d.IsActive);

        var sessionToken = CodeGenerator.GenerateToken();
        if (request.RememberMe)
            user.RememberToken = sessionToken;

        _db.UserSessions.Add(new UserSession
        {
            UserId = user.Id,
            ActiveDepartmentId = primaryDept?.DepartmentId,
            ComputerName = computerName ?? Environment.MachineName,
            IpAddress = ipAddress,
            SessionToken = sessionToken,
            LoginAt = DateTime.UtcNow,
            LastActivityAt = DateTime.UtcNow,
            IsActive = true
        });

        await _db.SaveChangesAsync(ct);

        var session = await BuildSessionAsync(user, primaryDept?.DepartmentId, sessionToken, ct);
        await _audit.LogAsync(user.Id, primaryDept?.DepartmentId, "Auth", "Login", "User", user.Id,
            computerName: computerName, ipAddress: ipAddress, ct: ct);

        return new LoginResult(true, "Login successful.", session);
    }

    public async Task LogoutAsync(Guid userId, string? sessionToken = null, CancellationToken ct = default)
    {
        var sessions = await _db.UserSessions
            .Where(s => s.UserId == userId && s.IsActive && (sessionToken == null || s.SessionToken == sessionToken))
            .ToListAsync(ct);

        foreach (var s in sessions)
        {
            s.IsActive = false;
            s.LogoutAt = DateTime.UtcNow;
        }

        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(userId, sessions.FirstOrDefault()?.ActiveDepartmentId, "Auth", "Logout", "User", userId, ct: ct);
    }

    public async Task<bool> ForgotPasswordAsync(ForgotPasswordRequest request, CancellationToken ct = default)
    {
        var key = request.UsernameOrEmail.Trim();
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Username == key || u.Email == key, ct);
        if (user is null) return true; // do not reveal existence

        user.PasswordResetToken = CodeGenerator.GenerateToken();
        user.PasswordResetExpiry = DateTime.UtcNow.AddHours(AppConstants.PasswordResetTokenHours);
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(user.Id, null, "Auth", "ForgotPassword", "User", user.Id,
            details: "Reset token generated", ct: ct);
        return true;
    }

    public async Task<bool> ResetPasswordAsync(ResetPasswordRequest request, CancellationToken ct = default)
    {
        var user = await _db.Users.FirstOrDefaultAsync(u =>
            u.PasswordResetToken == request.Token &&
            u.PasswordResetExpiry != null &&
            u.PasswordResetExpiry > DateTime.UtcNow, ct);

        if (user is null) return false;

        user.PasswordHash = PasswordHasher.Hash(request.NewPassword);
        user.PasswordResetToken = null;
        user.PasswordResetExpiry = null;
        user.FailedLoginAttempts = 0;
        user.LockoutEnd = null;
        user.Status = UserStatus.Active;
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(user.Id, null, "Auth", "ResetPassword", "User", user.Id, ct: ct);
        return true;
    }

    public async Task<bool> ChangePasswordAsync(ChangePasswordRequest request, CancellationToken ct = default)
    {
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Id == request.UserId, ct);
        if (user is null || !PasswordHasher.Verify(request.CurrentPassword, user.PasswordHash))
            return false;

        user.PasswordHash = PasswordHasher.Hash(request.NewPassword);
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(user.Id, null, "Auth", "ChangePassword", "User", user.Id, ct: ct);
        return true;
    }

    public async Task<UserSessionDto?> ValidateSessionAsync(string sessionToken, CancellationToken ct = default)
    {
        var session = await _db.UserSessions
            .Include(s => s.User).ThenInclude(u => u!.Role)
            .Include(s => s.User).ThenInclude(u => u!.UserDepartments).ThenInclude(ud => ud.Department)
            .FirstOrDefaultAsync(s => s.SessionToken == sessionToken && s.IsActive, ct);

        if (session?.User is null) return null;

        var timeout = TimeSpan.FromMinutes(AppConstants.SessionTimeoutMinutes);
        if (DateTime.UtcNow - session.LastActivityAt > timeout)
        {
            session.IsActive = false;
            session.LogoutAt = DateTime.UtcNow;
            await _db.SaveChangesAsync(ct);
            return null;
        }

        session.LastActivityAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);
        return await BuildSessionAsync(session.User, session.ActiveDepartmentId, sessionToken, ct);
    }

    public async Task<UserSessionDto?> SwitchDepartmentAsync(
        Guid userId,
        Guid departmentId,
        string sessionToken,
        CancellationToken ct = default)
    {
        var allowed = await _db.UserDepartments.AnyAsync(ud =>
            ud.UserId == userId && ud.DepartmentId == departmentId && ud.IsActive, ct);

        var user = await _db.Users.AsNoTracking().FirstOrDefaultAsync(u => u.Id == userId, ct);
        if (user is null) return null;
        if (!user.IsSuperAdmin && !allowed)
            throw new UnauthorizedAccessException("You do not have access to this department.");

        var session = await _db.UserSessions.FirstOrDefaultAsync(s =>
            s.UserId == userId && s.SessionToken == sessionToken && s.IsActive, ct);
        if (session is null) return null;

        session.ActiveDepartmentId = departmentId;
        session.LastActivityAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);

        var fullUser = await _db.Users
            .Include(u => u.Role)
            .Include(u => u.UserDepartments).ThenInclude(ud => ud.Department)
            .FirstAsync(u => u.Id == userId, ct);

        await _audit.LogAsync(userId, departmentId, "Auth", "SwitchDepartment", "Department", departmentId, ct: ct);
        return await BuildSessionAsync(fullUser, departmentId, sessionToken, ct);
    }

    private async Task<UserSessionDto> BuildSessionAsync(User user, Guid? departmentId, string sessionToken, CancellationToken ct)
    {
        var departments = user.IsSuperAdmin
            ? await _db.Departments.AsNoTracking()
                .Where(d => d.Status == DepartmentStatus.Active)
                .OrderBy(d => d.Name)
                .Select(d => new DepartmentDto(d.Id, d.Code, d.Name, d.Description, d.HeadName, d.Status, d.AnnualBudget, false))
                .ToListAsync(ct)
            : user.UserDepartments
                .Where(ud => ud.IsActive && ud.Department.Status == DepartmentStatus.Active)
                .Select(ud => new DepartmentDto(
                    ud.Department.Id, ud.Department.Code, ud.Department.Name, ud.Department.Description,
                    ud.Department.HeadName, ud.Department.Status, ud.Department.AnnualBudget, ud.IsPrimary))
                .ToList();

        var activeName = departments.FirstOrDefault(d => d.Id == departmentId)?.Name;
        var permissions = await _permissions.GetEffectivePermissionsAsync(user.Id, departmentId, ct);

        return new UserSessionDto(
            user.Id, user.Username, user.FullName, user.Email, user.IsSuperAdmin,
            user.RoleId, user.Role?.Name, departmentId, activeName,
            departments, permissions, sessionToken, user.PreferDarkMode,
            user.PreferredLanguage ?? "en");
    }
}
