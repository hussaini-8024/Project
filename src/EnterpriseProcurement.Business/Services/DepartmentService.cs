using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class DepartmentService : IDepartmentService
{
    private readonly AppDbContext _db;
    private readonly IAuditService _audit;

    public DepartmentService(AppDbContext db, IAuditService audit)
    {
        _db = db;
        _audit = audit;
    }

    public async Task<PagedResult<DepartmentDto>> GetPagedAsync(string? search, int page, int pageSize, CancellationToken ct = default)
    {
        var query = _db.Departments.AsNoTracking().AsQueryable();
        if (!string.IsNullOrWhiteSpace(search))
        {
            var s = search.Trim().ToLowerInvariant();
            query = query.Where(d => d.Name.ToLower().Contains(s) || d.Code.ToLower().Contains(s));
        }

        var total = await query.CountAsync(ct);
        var items = await query.OrderBy(d => d.Name)
            .Skip((page - 1) * pageSize).Take(pageSize)
            .Select(d => Map(d))
            .ToListAsync(ct);

        return new PagedResult<DepartmentDto>(items, total, page, pageSize);
    }

    public async Task<IReadOnlyList<DepartmentDto>> GetAllActiveAsync(CancellationToken ct = default)
        => await _db.Departments.AsNoTracking()
            .Where(d => d.Status == DepartmentStatus.Active)
            .OrderBy(d => d.Name)
            .Select(d => Map(d))
            .ToListAsync(ct);

    public async Task<DepartmentDto?> GetByIdAsync(Guid id, CancellationToken ct = default)
    {
        var d = await _db.Departments.AsNoTracking().FirstOrDefaultAsync(x => x.Id == id, ct);
        return d is null ? null : Map(d);
    }

    public async Task<DepartmentDto> CreateAsync(CreateDepartmentRequest request, Guid actorId, CancellationToken ct = default)
    {
        if (await _db.Departments.AnyAsync(d => d.Code == request.Code, ct))
            throw new InvalidOperationException($"Department code '{request.Code}' already exists.");

        var entity = new Department
        {
            Code = request.Code.Trim().ToUpperInvariant(),
            Name = request.Name.Trim(),
            Description = request.Description,
            HeadName = request.HeadName,
            Email = request.Email,
            Phone = request.Phone,
            Location = request.Location,
            AnnualBudget = request.AnnualBudget,
            CreatedBy = actorId
        };

        _db.Departments.Add(entity);
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, entity.Id, "Departments", "Create", "Department", entity.Id, newValue: entity.Name, ct: ct);
        return Map(entity);
    }

    public async Task<DepartmentDto> UpdateAsync(UpdateDepartmentRequest request, Guid actorId, CancellationToken ct = default)
    {
        var entity = await _db.Departments.FirstOrDefaultAsync(d => d.Id == request.Id, ct)
            ?? throw new InvalidOperationException("Department not found.");

        var old = entity.Name;
        entity.Code = request.Code.Trim().ToUpperInvariant();
        entity.Name = request.Name.Trim();
        entity.Description = request.Description;
        entity.HeadName = request.HeadName;
        entity.Email = request.Email;
        entity.Phone = request.Phone;
        entity.Location = request.Location;
        entity.AnnualBudget = request.AnnualBudget;
        entity.Status = request.Status;
        entity.UpdatedBy = actorId;

        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, entity.Id, "Departments", "Update", "Department", entity.Id, old, entity.Name, ct: ct);
        return Map(entity);
    }

    public async Task DeactivateAsync(Guid id, Guid actorId, CancellationToken ct = default)
    {
        var entity = await _db.Departments.FirstOrDefaultAsync(d => d.Id == id, ct)
            ?? throw new InvalidOperationException("Department not found.");
        entity.Status = DepartmentStatus.Disabled;
        entity.UpdatedBy = actorId;
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, id, "Departments", "Deactivate", "Department", id, ct: ct);
    }

    public async Task DeleteAsync(Guid id, Guid actorId, CancellationToken ct = default)
    {
        var entity = await _db.Departments.FirstOrDefaultAsync(d => d.Id == id, ct)
            ?? throw new InvalidOperationException("Department not found.");
        entity.IsDeleted = true;
        entity.UpdatedBy = actorId;
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, id, "Departments", "Delete", "Department", id, ct: ct);
    }

    private static DepartmentDto Map(Department d)
        => new(d.Id, d.Code, d.Name, d.Description, d.HeadName, d.Status, d.AnnualBudget);
}
