using System.Text;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class ReportService : IReportService
{
    private readonly AppDbContext _db;

    public ReportService(AppDbContext db) => _db = db;

    public async Task<byte[]> ExportProcurementCsvAsync(Guid? departmentId, DateTime? from, DateTime? to, CancellationToken ct = default)
    {
        var query = _db.PurchaseRequests.AsNoTracking().Include(p => p.Department).Include(p => p.RequestedBy).AsQueryable();
        if (departmentId.HasValue) query = query.Where(p => p.DepartmentId == departmentId);
        if (from.HasValue) query = query.Where(p => p.CreatedAt >= from);
        if (to.HasValue) query = query.Where(p => p.CreatedAt <= to);

        var rows = await query.OrderByDescending(p => p.CreatedAt).ToListAsync(ct);
        var sb = new StringBuilder();
        sb.AppendLine("RequestNumber,Title,Department,RequestedBy,Status,EstimatedBudget,PONumber,CreatedAt");
        foreach (var p in rows)
        {
            sb.AppendLine(string.Join(',',
                Csv(p.RequestNumber), Csv(p.Title), Csv(p.Department.Name), Csv(p.RequestedBy.FullName),
                p.Status, p.EstimatedBudget, Csv(p.PurchaseOrderNumber), p.CreatedAt.ToString("u")));
        }
        return Encoding.UTF8.GetBytes(sb.ToString());
    }

    public async Task<byte[]> ExportInventoryCsvAsync(Guid? departmentId, CancellationToken ct = default)
    {
        var query = _db.InventoryItems.AsNoTracking().Include(i => i.Department).AsQueryable();
        if (departmentId.HasValue) query = query.Where(i => i.DepartmentId == departmentId);
        var rows = await query.OrderBy(i => i.Name).ToListAsync(ct);
        var sb = new StringBuilder();
        sb.AppendLine("ItemCode,Name,Category,Department,Stock,MinStock,Status,Condition,Cost,Barcode,AssetTag");
        foreach (var i in rows)
        {
            sb.AppendLine(string.Join(',',
                Csv(i.ItemCode), Csv(i.Name), Csv(i.Category), Csv(i.Department?.Name),
                i.StockQuantity, i.MinimumStock, i.Status, i.Condition, i.Cost,
                Csv(i.Barcode), Csv(i.AssetTag)));
        }
        return Encoding.UTF8.GetBytes(sb.ToString());
    }

    public async Task<byte[]> ExportAllocationCsvAsync(Guid? departmentId, CancellationToken ct = default)
    {
        var query = _db.Allocations.AsNoTracking()
            .Include(a => a.Department).Include(a => a.InventoryItem).AsQueryable();
        if (departmentId.HasValue) query = query.Where(a => a.DepartmentId == departmentId);
        var rows = await query.OrderByDescending(a => a.IssueDate).ToListAsync(ct);
        var sb = new StringBuilder();
        sb.AppendLine("AllocationNumber,Item,Department,Employee,Quantity,Status,IssueDate,ReturnDate");
        foreach (var a in rows)
        {
            sb.AppendLine(string.Join(',',
                Csv(a.AllocationNumber), Csv(a.InventoryItem.Name), Csv(a.Department.Name),
                Csv(a.EmployeeName), a.Quantity, a.Status, a.IssueDate.ToString("u"),
                a.ActualReturnDate?.ToString("u")));
        }
        return Encoding.UTF8.GetBytes(sb.ToString());
    }

    public async Task<byte[]> ExportAuditCsvAsync(DateTime? from, DateTime? to, CancellationToken ct = default)
    {
        var query = _db.AuditLogs.AsNoTracking().AsQueryable();
        if (from.HasValue) query = query.Where(a => a.ActionAt >= from);
        if (to.HasValue) query = query.Where(a => a.ActionAt <= to);
        var rows = await query.OrderByDescending(a => a.ActionAt).Take(10000).ToListAsync(ct);
        var sb = new StringBuilder();
        sb.AppendLine("ActionAt,Username,Module,Action,EntityType,ComputerName,IpAddress,Details");
        foreach (var a in rows)
        {
            sb.AppendLine(string.Join(',',
                a.ActionAt.ToString("u"), Csv(a.Username), Csv(a.Module), Csv(a.Action),
                Csv(a.EntityType), Csv(a.ComputerName), Csv(a.IpAddress), Csv(a.Details)));
        }
        return Encoding.UTF8.GetBytes(sb.ToString());
    }

    private static string Csv(string? value)
    {
        value ??= string.Empty;
        if (value.Contains(',') || value.Contains('"') || value.Contains('\n'))
            return $"\"{value.Replace("\"", "\"\"")}\"";
        return value;
    }
}
