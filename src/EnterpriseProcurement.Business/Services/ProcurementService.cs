using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Helpers;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class ProcurementService : IProcurementService
{
    private readonly AppDbContext _db;
    private readonly IAuditService _audit;
    private readonly INotificationService _notifications;
    private readonly IInventoryService _inventory;

    public ProcurementService(
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

    public async Task<PagedResult<PurchaseRequestDto>> GetPagedAsync(
        Guid? departmentId,
        ProcurementStatus? status,
        string? search,
        int page,
        int pageSize,
        CancellationToken ct = default)
    {
        var query = _db.PurchaseRequests.AsNoTracking()
            .Include(p => p.Department)
            .Include(p => p.RequestedBy)
            .Include(p => p.SelectedVendor)
            .Include(p => p.Items)
            .AsQueryable();

        if (departmentId.HasValue) query = query.Where(p => p.DepartmentId == departmentId);
        if (status.HasValue) query = query.Where(p => p.Status == status);
        if (!string.IsNullOrWhiteSpace(search))
        {
            var s = search.Trim().ToLowerInvariant();
            query = query.Where(p =>
                p.RequestNumber.ToLower().Contains(s) ||
                p.Title.ToLower().Contains(s) ||
                (p.PurchaseOrderNumber != null && p.PurchaseOrderNumber.ToLower().Contains(s)));
        }

        var total = await query.CountAsync(ct);
        var items = await query.OrderByDescending(p => p.CreatedAt)
            .Skip((page - 1) * pageSize).Take(pageSize)
            .ToListAsync(ct);

        return new PagedResult<PurchaseRequestDto>(items.Select(Map).ToList(), total, page, pageSize);
    }

    public async Task<PurchaseRequestDto?> GetByIdAsync(Guid id, CancellationToken ct = default)
    {
        var p = await _db.PurchaseRequests.AsNoTracking()
            .Include(x => x.Department)
            .Include(x => x.RequestedBy)
            .Include(x => x.SelectedVendor)
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);
        return p is null ? null : Map(p);
    }

    public async Task<PurchaseRequestDto> CreateAsync(CreatePurchaseRequestRequest request, CancellationToken ct = default)
    {
        var count = await _db.PurchaseRequests.IgnoreQueryFilters().CountAsync(ct) + 1;
        var entity = new PurchaseRequest
        {
            RequestNumber = CodeGenerator.Generate("PR", count),
            DepartmentId = request.DepartmentId,
            RequestedById = request.RequestedById,
            Title = request.Title.Trim(),
            Description = request.Description,
            Justification = request.Justification,
            EstimatedBudget = request.EstimatedBudget,
            RequiredByDate = request.RequiredByDate,
            Status = ProcurementStatus.Draft,
            CreatedBy = request.RequestedById
        };

        foreach (var (item, index) in request.Items.Select((x, i) => (x, i)))
        {
            entity.Items.Add(new PurchaseRequestItem
            {
                ItemName = item.ItemName,
                Description = item.Description,
                Specification = item.Specification,
                Unit = item.Unit,
                Quantity = item.Quantity,
                UnitPrice = item.UnitPrice,
                Category = item.Category,
                SortOrder = index
            });
        }

        _db.PurchaseRequests.Add(entity);
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(request.RequestedById, request.DepartmentId, "Procurement", "Create",
            "PurchaseRequest", entity.Id, newValue: entity.RequestNumber, ct: ct);

        return (await GetByIdAsync(entity.Id, ct))!;
    }

    public async Task<PurchaseRequestDto> SubmitAsync(Guid id, Guid actorId, CancellationToken ct = default)
    {
        var entity = await LoadAsync(id, ct);
        if (entity.Status != ProcurementStatus.Draft)
            throw new InvalidOperationException("Only draft requests can be submitted.");

        entity.Status = ProcurementStatus.PendingApproval;
        entity.CurrentApprovalLevel = ApprovalLevel.DepartmentHead;
        entity.SubmittedAt = DateTime.UtcNow;
        entity.UpdatedBy = actorId;
        await _db.SaveChangesAsync(ct);

        await NotifyApproversAsync(entity, "Procurement request pending approval", ct);
        await _audit.LogAsync(actorId, entity.DepartmentId, "Procurement", "Submit", "PurchaseRequest", id, ct: ct);
        return (await GetByIdAsync(id, ct))!;
    }

    public async Task<PurchaseRequestDto> ApproveAsync(Guid id, Guid actorId, string? comments, CancellationToken ct = default)
    {
        var entity = await LoadAsync(id, ct);
        if (entity.Status is not (ProcurementStatus.PendingApproval or ProcurementStatus.Submitted))
            throw new InvalidOperationException("Request is not awaiting approval.");

        _db.ApprovalHistories.Add(new ApprovalHistory
        {
            PurchaseRequestId = id,
            ApproverId = actorId,
            Level = entity.CurrentApprovalLevel,
            IsApproved = true,
            Comments = comments
        });

        entity.CurrentApprovalLevel = entity.CurrentApprovalLevel switch
        {
            ApprovalLevel.Employee => ApprovalLevel.DepartmentHead,
            ApprovalLevel.DepartmentHead => ApprovalLevel.ProcurementManager,
            ApprovalLevel.ProcurementManager => ApprovalLevel.Finance,
            ApprovalLevel.Finance => ApprovalLevel.Administrator,
            _ => entity.CurrentApprovalLevel
        };

        if (entity.CurrentApprovalLevel == ApprovalLevel.Administrator ||
            entity.ApprovalHistory.Count(h => h.IsApproved) >= 4)
        {
            entity.Status = ProcurementStatus.Approved;
            entity.ApprovedAt = DateTime.UtcNow;
            entity.ApprovedBudget = entity.EstimatedBudget;
            await _notifications.NotifyAsync(entity.RequestedById, NotificationType.PendingApproval,
                "Request Approved", $"Purchase request {entity.RequestNumber} has been approved.",
                entity.DepartmentId, "procurement", entity.Id, ct);
        }

        entity.UpdatedBy = actorId;
        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, entity.DepartmentId, "Procurement", "Approve", "PurchaseRequest", id, ct: ct);
        return (await GetByIdAsync(id, ct))!;
    }

    public async Task<PurchaseRequestDto> RejectAsync(Guid id, Guid actorId, string reason, CancellationToken ct = default)
    {
        var entity = await LoadAsync(id, ct);
        entity.Status = ProcurementStatus.Rejected;
        entity.RejectionReason = reason;
        entity.UpdatedBy = actorId;

        _db.ApprovalHistories.Add(new ApprovalHistory
        {
            PurchaseRequestId = id,
            ApproverId = actorId,
            Level = entity.CurrentApprovalLevel,
            IsApproved = false,
            Comments = reason
        });

        await _db.SaveChangesAsync(ct);
        await _notifications.NotifyAsync(entity.RequestedById, NotificationType.PendingApproval,
            "Request Rejected", $"Purchase request {entity.RequestNumber} was rejected: {reason}",
            entity.DepartmentId, "procurement", entity.Id, ct);
        await _audit.LogAsync(actorId, entity.DepartmentId, "Procurement", "Reject", "PurchaseRequest", id, newValue: reason, ct: ct);
        return (await GetByIdAsync(id, ct))!;
    }

    public async Task<PurchaseRequestDto> GeneratePurchaseOrderAsync(Guid id, Guid vendorId, Guid actorId, CancellationToken ct = default)
    {
        var entity = await LoadAsync(id, ct);
        if (entity.Status != ProcurementStatus.Approved && entity.Status != ProcurementStatus.Quotation)
            throw new InvalidOperationException("Request must be approved before generating a PO.");

        var count = await _db.PurchaseRequests.CountAsync(p => p.PurchaseOrderNumber != null, ct) + 1;
        entity.SelectedVendorId = vendorId;
        entity.PurchaseOrderNumber = CodeGenerator.Generate("PO", count);
        entity.Status = ProcurementStatus.PurchaseOrder;
        entity.UpdatedBy = actorId;

        var quotations = await _db.Quotations.Where(q => q.PurchaseRequestId == id).ToListAsync(ct);
        foreach (var q in quotations) q.IsSelected = q.VendorId == vendorId;

        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, entity.DepartmentId, "Procurement", "GeneratePO", "PurchaseRequest", id,
            newValue: entity.PurchaseOrderNumber, ct: ct);
        return (await GetByIdAsync(id, ct))!;
    }

    public async Task<PurchaseRequestDto> ReceiveGoodsAsync(Guid id, Guid actorId, CancellationToken ct = default)
    {
        var entity = await _db.PurchaseRequests
            .Include(p => p.Items)
            .FirstOrDefaultAsync(p => p.Id == id, ct)
            ?? throw new InvalidOperationException("Purchase request not found.");

        if (entity.Status is not (ProcurementStatus.PurchaseOrder or ProcurementStatus.PartiallyReceived))
            throw new InvalidOperationException("Goods can only be received against a purchase order.");

        foreach (var item in entity.Items)
        {
            await _inventory.CreateAsync(new CreateInventoryItemRequest(
                item.ItemName,
                InventoryItemType.Asset,
                item.Category,
                null, null, null,
                entity.DepartmentId,
                null,
                item.Quantity,
                1,
                item.Quantity * 2,
                item.Unit,
                DateTime.UtcNow,
                null, null,
                entity.SelectedVendorId,
                item.UnitPrice,
                ItemCondition.New,
                $"Received from {entity.PurchaseOrderNumber}"), actorId, ct);

            item.ReceivedQuantity = item.Quantity;
        }

        entity.Status = ProcurementStatus.Received;
        entity.ReceivedAt = DateTime.UtcNow;
        entity.CompletedAt = DateTime.UtcNow;
        entity.UpdatedBy = actorId;
        await _db.SaveChangesAsync(ct);

        await _notifications.NotifyAsync(entity.RequestedById, NotificationType.PurchaseCompleted,
            "Goods Received", $"Goods for {entity.RequestNumber} have been received into inventory.",
            entity.DepartmentId, "procurement", entity.Id, ct);
        await _audit.LogAsync(actorId, entity.DepartmentId, "Procurement", "ReceiveGoods", "PurchaseRequest", id, ct: ct);
        return (await GetByIdAsync(id, ct))!;
    }

    public async Task AddQuotationAsync(Guid requestId, Guid vendorId, decimal totalAmount, int deliveryDays, string? notes, CancellationToken ct = default)
    {
        var count = await _db.Quotations.CountAsync(ct) + 1;
        _db.Quotations.Add(new Quotation
        {
            PurchaseRequestId = requestId,
            VendorId = vendorId,
            QuotationNumber = CodeGenerator.Generate("QT", count),
            TotalAmount = totalAmount,
            DeliveryDays = deliveryDays,
            Notes = notes
        });

        var pr = await _db.PurchaseRequests.FirstAsync(p => p.Id == requestId, ct);
        if (pr.Status == ProcurementStatus.Approved)
            pr.Status = ProcurementStatus.Quotation;

        await _db.SaveChangesAsync(ct);
    }

    private async Task<PurchaseRequest> LoadAsync(Guid id, CancellationToken ct)
        => await _db.PurchaseRequests
            .Include(p => p.ApprovalHistory)
            .FirstOrDefaultAsync(p => p.Id == id, ct)
            ?? throw new InvalidOperationException("Purchase request not found.");

    private async Task NotifyApproversAsync(PurchaseRequest entity, string title, CancellationToken ct)
    {
        var admins = await _db.Users.Where(u => u.IsSuperAdmin && u.Status == UserStatus.Active).Select(u => u.Id).ToListAsync(ct);
        foreach (var adminId in admins)
        {
            await _notifications.NotifyAsync(adminId, NotificationType.PendingApproval, title,
                $"{entity.RequestNumber}: {entity.Title}", entity.DepartmentId, "procurement", entity.Id, ct);
        }
    }

    private static PurchaseRequestDto Map(PurchaseRequest p)
        => new(
            p.Id, p.RequestNumber, p.DepartmentId, p.Department?.Name ?? "",
            p.RequestedById, p.RequestedBy?.FullName ?? "",
            p.Title, p.Description, p.Status, p.CurrentApprovalLevel,
            p.EstimatedBudget, p.ApprovedBudget, p.SelectedVendor?.CompanyName,
            p.PurchaseOrderNumber, p.RequiredByDate, p.CreatedAt, p.Items?.Count ?? 0);
}
