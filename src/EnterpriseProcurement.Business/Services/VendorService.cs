using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Helpers;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class VendorService : IVendorService
{
    private readonly AppDbContext _db;
    private readonly IAuditService _audit;

    public VendorService(AppDbContext db, IAuditService audit)
    {
        _db = db;
        _audit = audit;
    }

    public async Task<PagedResult<VendorDto>> GetPagedAsync(string? search, int page, int pageSize, CancellationToken ct = default)
    {
        var query = _db.Vendors.AsNoTracking().AsQueryable();
        if (!string.IsNullOrWhiteSpace(search))
        {
            var s = search.Trim().ToLowerInvariant();
            query = query.Where(v =>
                v.CompanyName.ToLower().Contains(s) ||
                v.Code.ToLower().Contains(s) ||
                (v.NTN != null && v.NTN.Contains(s)));
        }

        var total = await query.CountAsync(ct);
        var items = await query.OrderBy(v => v.CompanyName)
            .Skip((page - 1) * pageSize).Take(pageSize)
            .Select(v => Map(v))
            .ToListAsync(ct);

        return new PagedResult<VendorDto>(items, total, page, pageSize);
    }

    public async Task<VendorDto?> GetByIdAsync(Guid id, CancellationToken ct = default)
    {
        var v = await _db.Vendors.AsNoTracking().FirstOrDefaultAsync(x => x.Id == id, ct);
        return v is null ? null : Map(v);
    }

    public async Task<VendorDto> CreateAsync(CreateVendorRequest request, Guid actorId, CancellationToken ct = default)
    {
        var count = await _db.Vendors.IgnoreQueryFilters().CountAsync(ct) + 1;
        var vendor = new Vendor
        {
            Code = CodeGenerator.Generate("VND", count),
            CompanyName = request.CompanyName.Trim(),
            ContactPerson = request.ContactPerson,
            Address = request.Address,
            City = request.City,
            Country = request.Country,
            Phone = request.Phone,
            Email = request.Email,
            NTN = request.NTN,
            STRN = request.STRN,
            BankName = request.BankName,
            BankAccountNumber = request.BankAccountNumber,
            BankIBAN = request.BankIBAN,
            Notes = request.Notes,
            CreatedBy = actorId
        };

        _db.Vendors.Add(vendor);
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, null, "Vendors", "Create", "Vendor", vendor.Id, newValue: vendor.CompanyName, ct: ct);
        return Map(vendor);
    }

    public async Task<VendorDto> UpdateAsync(Guid id, CreateVendorRequest request, Guid actorId, CancellationToken ct = default)
    {
        var vendor = await _db.Vendors.FirstOrDefaultAsync(v => v.Id == id, ct)
            ?? throw new InvalidOperationException("Vendor not found.");

        vendor.CompanyName = request.CompanyName.Trim();
        vendor.ContactPerson = request.ContactPerson;
        vendor.Address = request.Address;
        vendor.City = request.City;
        vendor.Country = request.Country;
        vendor.Phone = request.Phone;
        vendor.Email = request.Email;
        vendor.NTN = request.NTN;
        vendor.STRN = request.STRN;
        vendor.BankName = request.BankName;
        vendor.BankAccountNumber = request.BankAccountNumber;
        vendor.BankIBAN = request.BankIBAN;
        vendor.Notes = request.Notes;
        vendor.UpdatedBy = actorId;

        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, null, "Vendors", "Update", "Vendor", id, newValue: vendor.CompanyName, ct: ct);
        return Map(vendor);
    }

    public async Task SetActiveAsync(Guid id, bool isActive, Guid actorId, CancellationToken ct = default)
    {
        var vendor = await _db.Vendors.FirstOrDefaultAsync(v => v.Id == id, ct)
            ?? throw new InvalidOperationException("Vendor not found.");
        vendor.IsActive = isActive;
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, null, "Vendors", isActive ? "Activate" : "Deactivate", "Vendor", id, ct: ct);
    }

    private static VendorDto Map(Vendor v)
        => new(v.Id, v.Code, v.CompanyName, v.ContactPerson, v.Phone, v.Email, v.NTN, v.STRN, v.Rating, v.IsActive, v.City, v.Address);
}
