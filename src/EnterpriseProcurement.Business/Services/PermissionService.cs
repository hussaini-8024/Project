using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class PermissionService : IPermissionService
{
    private readonly AppDbContext _db;

    public PermissionService(AppDbContext db) => _db = db;

    public async Task<IReadOnlyList<MenuPermissionDto>> GetEffectivePermissionsAsync(
        Guid userId,
        Guid? departmentId = null,
        CancellationToken ct = default)
    {
        var user = await _db.Users.AsNoTracking().FirstOrDefaultAsync(u => u.Id == userId, ct)
            ?? throw new InvalidOperationException("User not found.");

        var menus = await _db.Menus.AsNoTracking()
            .Where(m => m.IsActive)
            .OrderBy(m => m.SortOrder)
            .ToListAsync(ct);

        if (user.IsSuperAdmin)
        {
            return menus.Select(m => new MenuPermissionDto(
                m.Id, m.Key, m.Title, m.Icon, m.Route, m.ParentId, m.SortOrder,
                PermissionAction.All, m.RequiresDepartment)).ToList();
        }

        var rolePerms = user.RoleId.HasValue
            ? await _db.RoleMenuPermissions.AsNoTracking()
                .Where(p => p.RoleId == user.RoleId)
                .ToDictionaryAsync(p => p.MenuId, p => p.Actions, ct)
            : new Dictionary<Guid, PermissionAction>();

        var userPerms = await _db.UserMenuPermissions.AsNoTracking()
            .Where(p => p.UserId == userId && (p.DepartmentId == null || p.DepartmentId == departmentId))
            .ToListAsync(ct);

        var result = new List<MenuPermissionDto>();
        foreach (var menu in menus)
        {
            rolePerms.TryGetValue(menu.Id, out var actions);
            var overrides = userPerms.Where(p => p.MenuId == menu.Id).ToList();

            if (overrides.Any(o => o.IsDenied))
                continue;

            foreach (var ov in overrides.Where(o => !o.IsDenied))
                actions |= ov.Actions;

            if (actions == PermissionAction.None)
                continue;

            result.Add(new MenuPermissionDto(
                menu.Id, menu.Key, menu.Title, menu.Icon, menu.Route, menu.ParentId,
                menu.SortOrder, actions, menu.RequiresDepartment));
        }

        return result;
    }

    public async Task<bool> HasPermissionAsync(
        Guid userId,
        string menuKey,
        PermissionAction action,
        Guid? departmentId = null,
        CancellationToken ct = default)
    {
        var perms = await GetEffectivePermissionsAsync(userId, departmentId, ct);
        var menu = perms.FirstOrDefault(p => p.MenuKey == menuKey);
        return menu is not null && menu.Actions.HasFlag(action);
    }

    public async Task AssignRolePermissionsAsync(
        Guid roleId,
        IReadOnlyDictionary<Guid, PermissionAction> permissions,
        CancellationToken ct = default)
    {
        var existing = await _db.RoleMenuPermissions.Where(p => p.RoleId == roleId).ToListAsync(ct);
        _db.RoleMenuPermissions.RemoveRange(existing);

        foreach (var (menuId, actions) in permissions)
        {
            _db.RoleMenuPermissions.Add(new RoleMenuPermission
            {
                RoleId = roleId,
                MenuId = menuId,
                Actions = actions
            });
        }

        await _db.SaveChangesAsync(ct);
    }

    public async Task AssignUserPermissionsAsync(
        Guid userId,
        Guid? departmentId,
        IReadOnlyDictionary<Guid, PermissionAction> permissions,
        CancellationToken ct = default)
    {
        var existing = await _db.UserMenuPermissions
            .Where(p => p.UserId == userId && p.DepartmentId == departmentId)
            .ToListAsync(ct);
        _db.UserMenuPermissions.RemoveRange(existing);

        foreach (var (menuId, actions) in permissions)
        {
            _db.UserMenuPermissions.Add(new UserMenuPermission
            {
                UserId = userId,
                MenuId = menuId,
                DepartmentId = departmentId,
                Actions = actions
            });
        }

        await _db.SaveChangesAsync(ct);
    }
}
