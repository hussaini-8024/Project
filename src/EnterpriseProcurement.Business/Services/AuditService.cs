using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class AuditService : IAuditService
{
    private readonly AppDbContext _db;

    public AuditService(AppDbContext db) => _db = db;

    public async Task LogAsync(
        Guid? userId,
        Guid? departmentId,
        string module,
        string action,
        string? entityType = null,
        Guid? entityId = null,
        string? oldValue = null,
        string? newValue = null,
        string? computerName = null,
        string? ipAddress = null,
        string? details = null,
        CancellationToken ct = default)
    {
        string? username = null;
        if (userId.HasValue)
            username = await _db.Users.Where(u => u.Id == userId).Select(u => u.Username).FirstOrDefaultAsync(ct);

        _db.AuditLogs.Add(new AuditLog
        {
            UserId = userId,
            Username = username,
            DepartmentId = departmentId,
            ComputerName = computerName ?? Environment.MachineName,
            IpAddress = ipAddress,
            Module = module,
            Action = action,
            EntityType = entityType,
            EntityId = entityId,
            OldValue = oldValue,
            NewValue = newValue,
            Details = details,
            ActionAt = DateTime.UtcNow
        });

        await _db.SaveChangesAsync(ct);
    }

    public async Task<PagedResult<AuditLogDto>> GetPagedAsync(
        string? search,
        Guid? userId,
        Guid? departmentId,
        DateTime? from,
        DateTime? to,
        int page,
        int pageSize,
        CancellationToken ct = default)
    {
        var query = _db.AuditLogs.AsNoTracking().Include(a => a.Department).AsQueryable();

        if (userId.HasValue) query = query.Where(a => a.UserId == userId);
        if (departmentId.HasValue) query = query.Where(a => a.DepartmentId == departmentId);
        if (from.HasValue) query = query.Where(a => a.ActionAt >= from);
        if (to.HasValue) query = query.Where(a => a.ActionAt <= to);
        if (!string.IsNullOrWhiteSpace(search))
        {
            var s = search.Trim().ToLowerInvariant();
            query = query.Where(a =>
                (a.Username != null && a.Username.ToLower().Contains(s)) ||
                a.Module.ToLower().Contains(s) ||
                a.Action.ToLower().Contains(s) ||
                (a.Details != null && a.Details.ToLower().Contains(s)));
        }

        var total = await query.CountAsync(ct);
        var items = await query
            .OrderByDescending(a => a.ActionAt)
            .Skip((page - 1) * pageSize)
            .Take(pageSize)
            .Select(a => new AuditLogDto(
                a.Id, a.Username, a.Department != null ? a.Department.Name : null,
                a.ComputerName, a.IpAddress, a.Module, a.Action, a.EntityType,
                a.OldValue, a.NewValue, a.ActionAt, a.Details))
            .ToListAsync(ct);

        return new PagedResult<AuditLogDto>(items, total, page, pageSize);
    }
}
