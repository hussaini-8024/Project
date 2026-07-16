using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Core.Security;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class UserService : IUserService
{
    private readonly AppDbContext _db;
    private readonly IAuditService _audit;

    public UserService(AppDbContext db, IAuditService audit)
    {
        _db = db;
        _audit = audit;
    }

    public async Task<PagedResult<UserDto>> GetPagedAsync(string? search, Guid? departmentId, int page, int pageSize, CancellationToken ct = default)
    {
        var query = _db.Users.AsNoTracking()
            .Include(u => u.Role)
            .Include(u => u.UserDepartments).ThenInclude(ud => ud.Department)
            .AsQueryable();

        if (departmentId.HasValue)
            query = query.Where(u => u.UserDepartments.Any(ud => ud.DepartmentId == departmentId && ud.IsActive));

        if (!string.IsNullOrWhiteSpace(search))
        {
            var s = search.Trim().ToLowerInvariant();
            query = query.Where(u =>
                u.Username.ToLower().Contains(s) ||
                u.FullName.ToLower().Contains(s) ||
                u.Email.ToLower().Contains(s));
        }

        var total = await query.CountAsync(ct);
        var users = await query.OrderBy(u => u.FullName)
            .Skip((page - 1) * pageSize).Take(pageSize)
            .ToListAsync(ct);

        return new PagedResult<UserDto>(users.Select(Map).ToList(), total, page, pageSize);
    }

    public async Task<UserDto?> GetByIdAsync(Guid id, CancellationToken ct = default)
    {
        var user = await _db.Users.AsNoTracking()
            .Include(u => u.Role)
            .Include(u => u.UserDepartments).ThenInclude(ud => ud.Department)
            .FirstOrDefaultAsync(u => u.Id == id, ct);
        return user is null ? null : Map(user);
    }

    public async Task<UserDto> CreateAsync(CreateUserRequest request, Guid actorId, CancellationToken ct = default)
    {
        if (await _db.Users.AnyAsync(u => u.Username == request.Username, ct))
            throw new InvalidOperationException("Username already exists.");
        if (await _db.Users.AnyAsync(u => u.Email == request.Email, ct))
            throw new InvalidOperationException("Email already exists.");

        var user = new User
        {
            Username = request.Username.Trim(),
            Email = request.Email.Trim(),
            PasswordHash = PasswordHasher.Hash(request.Password),
            FullName = request.FullName.Trim(),
            Phone = request.Phone,
            EmployeeCode = request.EmployeeCode,
            Designation = request.Designation,
            RoleId = request.RoleId,
            IsSuperAdmin = request.IsSuperAdmin,
            Status = UserStatus.Active,
            CreatedBy = actorId
        };

        _db.Users.Add(user);
        await _db.SaveChangesAsync(ct);
        await SyncDepartmentsAsync(user.Id, request.DepartmentIds, request.PrimaryDepartmentId, ct);
        await _audit.LogAsync(actorId, null, "Users", "Create", "User", user.Id, newValue: user.Username, ct: ct);

        return (await GetByIdAsync(user.Id, ct))!;
    }

    public async Task<UserDto> UpdateAsync(UpdateUserRequest request, Guid actorId, CancellationToken ct = default)
    {
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Id == request.Id, ct)
            ?? throw new InvalidOperationException("User not found.");

        user.Email = request.Email.Trim();
        user.FullName = request.FullName.Trim();
        user.Phone = request.Phone;
        user.EmployeeCode = request.EmployeeCode;
        user.Designation = request.Designation;
        user.RoleId = request.RoleId;
        user.Status = request.Status;
        user.TwoFactorEnabled = request.TwoFactorEnabled;
        user.UpdatedBy = actorId;

        await _db.SaveChangesAsync(ct);
        await SyncDepartmentsAsync(user.Id, request.DepartmentIds, request.PrimaryDepartmentId, ct);
        await _audit.LogAsync(actorId, null, "Users", "Update", "User", user.Id, newValue: user.Username, ct: ct);
        return (await GetByIdAsync(user.Id, ct))!;
    }

    public async Task ResetPasswordAsync(Guid userId, string newPassword, Guid actorId, CancellationToken ct = default)
    {
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Id == userId, ct)
            ?? throw new InvalidOperationException("User not found.");
        user.PasswordHash = PasswordHasher.Hash(newPassword);
        user.FailedLoginAttempts = 0;
        user.LockoutEnd = null;
        if (user.Status == UserStatus.Locked) user.Status = UserStatus.Active;
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, null, "Users", "ResetPassword", "User", userId, ct: ct);
    }

    public async Task SetStatusAsync(Guid userId, UserStatus status, Guid actorId, CancellationToken ct = default)
    {
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Id == userId, ct)
            ?? throw new InvalidOperationException("User not found.");
        var old = user.Status.ToString();
        user.Status = status;
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, null, "Users", "SetStatus", "User", userId, old, status.ToString(), ct: ct);
    }

    private async Task SyncDepartmentsAsync(Guid userId, IReadOnlyList<Guid> departmentIds, Guid? primaryId, CancellationToken ct)
    {
        var existing = await _db.UserDepartments.Where(ud => ud.UserId == userId).ToListAsync(ct);
        _db.UserDepartments.RemoveRange(existing);

        foreach (var deptId in departmentIds.Distinct())
        {
            _db.UserDepartments.Add(new UserDepartment
            {
                UserId = userId,
                DepartmentId = deptId,
                IsPrimary = primaryId == deptId || (!primaryId.HasValue && deptId == departmentIds.First()),
                IsActive = true
            });
        }

        await _db.SaveChangesAsync(ct);
    }

    private static UserDto Map(User u)
        => new(
            u.Id, u.Username, u.Email, u.FullName, u.Phone, u.EmployeeCode, u.Designation,
            u.RoleId, u.Role?.Name, u.Status, u.IsSuperAdmin, u.TwoFactorEnabled,
            u.UserDepartments.Where(ud => ud.IsActive).Select(ud =>
                new DepartmentDto(ud.Department.Id, ud.Department.Code, ud.Department.Name,
                    ud.Department.Description, ud.Department.HeadName, ud.Department.Status,
                    ud.Department.AnnualBudget, ud.IsPrimary)).ToList(),
            u.LastLoginAt);
}
