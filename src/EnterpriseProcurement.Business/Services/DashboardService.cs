using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class DashboardService : IDashboardService
{
    private readonly AppDbContext _db;

    public DashboardService(AppDbContext db) => _db = db;

    public async Task<DashboardStatsDto> GetAdminDashboardAsync(CancellationToken ct = default)
        => await BuildAsync(null, ct);

    public async Task<DashboardStatsDto> GetUserDashboardAsync(Guid userId, Guid? departmentId, CancellationToken ct = default)
        => await BuildAsync(departmentId, ct);

    private async Task<DashboardStatsDto> BuildAsync(Guid? departmentId, CancellationToken ct)
    {
        var deptQuery = _db.Departments.AsNoTracking();
        var userQuery = _db.Users.AsNoTracking();
        var prQuery = _db.PurchaseRequests.AsNoTracking();
        var invQuery = _db.InventoryItems.AsNoTracking();
        var allocQuery = _db.Allocations.AsNoTracking();

        if (departmentId.HasValue)
        {
            prQuery = prQuery.Where(p => p.DepartmentId == departmentId);
            invQuery = invQuery.Where(i => i.DepartmentId == departmentId);
            allocQuery = allocQuery.Where(a => a.DepartmentId == departmentId);
        }

        var totalDepartments = departmentId.HasValue ? 1 : await deptQuery.CountAsync(ct);
        var totalUsers = departmentId.HasValue
            ? await _db.UserDepartments.CountAsync(ud => ud.DepartmentId == departmentId && ud.IsActive, ct)
            : await userQuery.CountAsync(ct);

        var totalPr = await prQuery.CountAsync(ct);
        var pending = await prQuery.CountAsync(p => p.Status == ProcurementStatus.PendingApproval, ct);
        var approved = await prQuery.CountAsync(p => p.Status == ProcurementStatus.Approved || p.Status == ProcurementStatus.Completed || p.Status == ProcurementStatus.Received, ct);
        var totalInv = await invQuery.CountAsync(ct);
        var allocated = await allocQuery.CountAsync(a => a.Status == AllocationStatus.Issued || a.Status == AllocationStatus.Acknowledged, ct);
        var returned = await allocQuery.CountAsync(a => a.Status == AllocationStatus.Returned, ct);
        var lowStock = await invQuery.CountAsync(i => i.StockQuantity <= i.MinimumStock, ct);

        var start = DateTime.UtcNow.AddMonths(-11);
        var monthlyRaw = await prQuery
            .Where(p => p.CreatedAt >= start)
            .GroupBy(p => new { p.CreatedAt.Year, p.CreatedAt.Month })
            .Select(g => new { g.Key.Year, g.Key.Month, Count = g.Count() })
            .ToListAsync(ct);

        var monthly = Enumerable.Range(0, 12)
            .Select(i => DateTime.UtcNow.AddMonths(-11 + i))
            .Select(d => new ChartPointDto(
                d.ToString("MMM yy"),
                monthlyRaw.FirstOrDefault(x => x.Year == d.Year && x.Month == d.Month)?.Count ?? 0))
            .ToList();

        var byCategory = await invQuery
            .GroupBy(i => i.Category ?? i.ItemType.ToString())
            .Select(g => new ChartPointDto(g.Key, g.Count()))
            .OrderByDescending(c => c.Value)
            .Take(8)
            .ToListAsync(ct);

        var activities = await _db.AuditLogs.AsNoTracking()
            .Include(a => a.Department)
            .Where(a => departmentId == null || a.DepartmentId == departmentId)
            .OrderByDescending(a => a.ActionAt)
            .Take(15)
            .Select(a => new ActivityItemDto(
                a.Id, a.Module, a.Action, a.Username,
                a.Department != null ? a.Department.Name : null,
                a.ActionAt, a.Details))
            .ToListAsync(ct);

        var recentAssets = await invQuery
            .Include(i => i.Department)
            .OrderByDescending(i => i.CreatedAt)
            .Take(8)
            .ToListAsync(ct);

        var recentDtos = recentAssets.Select(i => new InventoryItemDto(
            i.Id, i.ItemCode, i.Name, i.ItemType, i.Category, i.Brand, i.Model, i.Barcode,
            i.SerialNumber, i.AssetTag, i.DepartmentId, i.Department?.Name, i.Location,
            i.StockQuantity, i.MinimumStock, i.StockQuantity <= i.MinimumStock, i.Status,
            i.Condition, i.Cost, i.WarrantyExpiry, i.ImagePath)).ToList();

        return new DashboardStatsDto(
            totalDepartments, totalUsers, totalPr, pending, approved, totalInv,
            allocated, returned, lowStock, monthly, byCategory, activities, recentDtos);
    }
}
