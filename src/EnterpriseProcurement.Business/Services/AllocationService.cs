using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Helpers;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class AllocationService : IAllocationService
{
    private readonly AppDbContext _db;
    private readonly IAuditService _audit;
    private readonly INotificationService _notifications;
    private readonly IInventoryService _inventory;

    public AllocationService(
        AppDbContext db,
        IAuditService audit,
        INotificationService notifications,
        IInventoryService inventory)
    {
        _db = db;
        _audit = audit;
        _notifications = notifications;
        _inventory = inventory;
    }

    public async Task<PagedResult<AllocationDto>> GetPagedAsync(
        Guid? departmentId,
        AllocationStatus? status,
        int page,
        int pageSize,
        CancellationToken ct = default)
    {
        var query = _db.Allocations.AsNoTracking()
            .Include(a => a.InventoryItem)
            .Include(a => a.Department)
            .AsQueryable();

        if (departmentId.HasValue) query = query.Where(a => a.DepartmentId == departmentId);
        if (status.HasValue) query = query.Where(a => a.Status == status);

        var total = await query.CountAsync(ct);
        var items = await query.OrderByDescending(a => a.IssueDate)
            .Skip((page - 1) * pageSize).Take(pageSize)
            .ToListAsync(ct);

        return new PagedResult<AllocationDto>(items.Select(Map).ToList(), total, page, pageSize);
    }

    public async Task<AllocationDto> CreateAsync(CreateAllocationRequest request, CancellationToken ct = default)
    {
        var item = await _db.InventoryItems.FirstOrDefaultAsync(i => i.Id == request.InventoryItemId, ct)
            ?? throw new InvalidOperationException("Inventory item not found.");

        if (item.StockQuantity < request.Quantity)
            throw new InvalidOperationException("Insufficient stock for allocation.");

        var count = await _db.Allocations.IgnoreQueryFilters().CountAsync(ct) + 1;
        var allocation = new Allocation
        {
            AllocationNumber = CodeGenerator.Generate("AL", count),
            InventoryItemId = request.InventoryItemId,
            DepartmentId = request.DepartmentId,
            IssuedById = request.IssuedById,
            EmployeeId = request.EmployeeId,
            EmployeeName = request.EmployeeName,
            Office = request.Office,
            Location = request.Location,
            Quantity = request.Quantity,
            ExpectedReturnDate = request.ExpectedReturnDate,
            IssueCondition = request.IssueCondition,
            Notes = request.Notes,
            Status = AllocationStatus.Pending,
            CreatedBy = request.IssuedById
        };

        _db.Allocations.Add(allocation);
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(request.IssuedById, request.DepartmentId, "Allocations", "Create",
            "Allocation", allocation.Id, newValue: allocation.AllocationNumber, ct: ct);

        return Map(await LoadAsync(allocation.Id, ct));
    }

    public async Task<AllocationDto> ApproveAsync(Guid id, Guid actorId, CancellationToken ct = default)
    {
        var allocation = await _db.Allocations.Include(a => a.InventoryItem)
            .FirstOrDefaultAsync(a => a.Id == id, ct)
            ?? throw new InvalidOperationException("Allocation not found.");

        if (allocation.Status != AllocationStatus.Pending)
            throw new InvalidOperationException("Only pending allocations can be approved.");

        await _inventory.AdjustStockAsync(allocation.InventoryItemId, -allocation.Quantity,
            StockMovementType.Allocate, actorId, $"Allocated via {allocation.AllocationNumber}", ct);

        allocation.Status = AllocationStatus.Issued;
        allocation.ApprovedById = actorId;
        allocation.ApprovedAt = DateTime.UtcNow;
        allocation.InventoryItem.Status = ItemStatus.Allocated;

        await _db.SaveChangesAsync(ct);

        if (allocation.EmployeeId.HasValue)
        {
            await _notifications.NotifyAsync(allocation.EmployeeId.Value, NotificationType.AllocationCompleted,
                "Item Allocated", $"{allocation.AllocationNumber} has been issued to you.",
                allocation.DepartmentId, "allocations", allocation.Id, ct);
        }

        await _audit.LogAsync(actorId, allocation.DepartmentId, "Allocations", "Approve", "Allocation", id, ct: ct);
        return Map(await LoadAsync(id, ct));
    }

    public async Task<AllocationDto> AcknowledgeAsync(Guid id, string? signaturePath, CancellationToken ct = default)
    {
        var allocation = await _db.Allocations.FirstOrDefaultAsync(a => a.Id == id, ct)
            ?? throw new InvalidOperationException("Allocation not found.");

        allocation.IsAcknowledged = true;
        allocation.AcknowledgedAt = DateTime.UtcNow;
        allocation.DigitalSignaturePath = signaturePath;
        allocation.Status = AllocationStatus.Acknowledged;
        await _db.SaveChangesAsync(ct);
        return Map(await LoadAsync(id, ct));
    }

    public async Task<AllocationDto> ReturnAsync(
        Guid id,
        ItemCondition returnCondition,
        string? notes,
        Guid actorId,
        CancellationToken ct = default)
    {
        var allocation = await _db.Allocations.Include(a => a.InventoryItem)
            .FirstOrDefaultAsync(a => a.Id == id, ct)
            ?? throw new InvalidOperationException("Allocation not found.");

        if (allocation.Status is not (AllocationStatus.Issued or AllocationStatus.Acknowledged or AllocationStatus.Overdue))
            throw new InvalidOperationException("Allocation cannot be returned in its current status.");

        await _inventory.AdjustStockAsync(allocation.InventoryItemId, allocation.Quantity,
            StockMovementType.Return, actorId, $"Returned via {allocation.AllocationNumber}", ct);

        allocation.Status = AllocationStatus.Returned;
        allocation.ActualReturnDate = DateTime.UtcNow;
        allocation.ReturnCondition = returnCondition;
        allocation.ReturnNotes = notes;
        allocation.InventoryItem.Status = ItemStatus.Available;
        allocation.InventoryItem.Condition = returnCondition;

        await _db.SaveChangesAsync(ct);
        await _notifications.NotifyAsync(allocation.IssuedById, NotificationType.ReturnedItems,
            "Item Returned", $"{allocation.AllocationNumber} has been returned.",
            allocation.DepartmentId, "allocations", allocation.Id, ct);
        await _audit.LogAsync(actorId, allocation.DepartmentId, "Allocations", "Return", "Allocation", id, ct: ct);

        return Map(await LoadAsync(id, ct));
    }

    private async Task<Allocation> LoadAsync(Guid id, CancellationToken ct)
        => await _db.Allocations.AsNoTracking()
            .Include(a => a.InventoryItem)
            .Include(a => a.Department)
            .FirstAsync(a => a.Id == id, ct);

    private static AllocationDto Map(Allocation a)
        => new(
            a.Id, a.AllocationNumber, a.InventoryItemId, a.InventoryItem.Name, a.InventoryItem.ItemCode,
            a.DepartmentId, a.Department.Name, a.EmployeeName, a.Office, a.Location, a.Quantity,
            a.IssueDate, a.ExpectedReturnDate, a.ActualReturnDate, a.Status, a.IsAcknowledged);
}
