using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class SearchService : ISearchService
{
    private readonly AppDbContext _db;

    public SearchService(AppDbContext db) => _db = db;

    public async Task<IReadOnlyList<SearchResultDto>> SearchAsync(
        string query,
        Guid? departmentId,
        bool isAdmin,
        int take = 25,
        CancellationToken ct = default)
    {
        if (string.IsNullOrWhiteSpace(query))
            return Array.Empty<SearchResultDto>();

        var s = query.Trim().ToLowerInvariant();
        var results = new List<SearchResultDto>();

        var items = await _db.InventoryItems.AsNoTracking()
            .Where(i => (isAdmin || departmentId == null || i.DepartmentId == departmentId) &&
                        (i.Name.ToLower().Contains(s) || i.ItemCode.ToLower().Contains(s) ||
                         (i.Barcode != null && i.Barcode.ToLower().Contains(s)) ||
                         (i.AssetTag != null && i.AssetTag.ToLower().Contains(s)) ||
                         (i.SerialNumber != null && i.SerialNumber.ToLower().Contains(s))))
            .Take(take)
            .Select(i => new SearchResultDto("InventoryItem", i.Id, i.Name, i.ItemCode, "inventory"))
            .ToListAsync(ct);
        results.AddRange(items);

        var vendors = await _db.Vendors.AsNoTracking()
            .Where(v => v.CompanyName.ToLower().Contains(s) || v.Code.ToLower().Contains(s))
            .Take(take)
            .Select(v => new SearchResultDto("Vendor", v.Id, v.CompanyName, v.Code, "vendors"))
            .ToListAsync(ct);
        results.AddRange(vendors);

        var prs = await _db.PurchaseRequests.AsNoTracking()
            .Where(p => (isAdmin || departmentId == null || p.DepartmentId == departmentId) &&
                        (p.RequestNumber.ToLower().Contains(s) || p.Title.ToLower().Contains(s) ||
                         (p.PurchaseOrderNumber != null && p.PurchaseOrderNumber.ToLower().Contains(s)) ||
                         (p.InvoiceNumber != null && p.InvoiceNumber.ToLower().Contains(s))))
            .Take(take)
            .Select(p => new SearchResultDto("PurchaseRequest", p.Id, p.Title, p.RequestNumber, "procurement"))
            .ToListAsync(ct);
        results.AddRange(prs);

        if (isAdmin)
        {
            var users = await _db.Users.AsNoTracking()
                .Where(u => u.Username.ToLower().Contains(s) || u.FullName.ToLower().Contains(s))
                .Take(take)
                .Select(u => new SearchResultDto("User", u.Id, u.FullName, u.Username, "users"))
                .ToListAsync(ct);
            results.AddRange(users);

            var depts = await _db.Departments.AsNoTracking()
                .Where(d => d.Name.ToLower().Contains(s) || d.Code.ToLower().Contains(s))
                .Take(take)
                .Select(d => new SearchResultDto("Department", d.Id, d.Name, d.Code, "departments"))
                .ToListAsync(ct);
            results.AddRange(depts);
        }

        return results.Take(take).ToList();
    }
}
