using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Helpers;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class InventoryService : IInventoryService
{
    private readonly AppDbContext _db;
    private readonly IAuditService _audit;
    private readonly INotificationService _notifications;

    public InventoryService(AppDbContext db, IAuditService audit, INotificationService notifications)
    {
        _db = db;
        _audit = audit;
        _notifications = notifications;
    }

    public async Task<PagedResult<InventoryItemDto>> GetPagedAsync(
        Guid? departmentId,
        string? search,
        bool lowStockOnly,
        int page,
        int pageSize,
        CancellationToken ct = default)
    {
        var query = _db.InventoryItems.AsNoTracking().Include(i => i.Department).AsQueryable();
        if (departmentId.HasValue) query = query.Where(i => i.DepartmentId == departmentId);
        if (lowStockOnly) query = query.Where(i => i.StockQuantity <= i.MinimumStock);
        if (!string.IsNullOrWhiteSpace(search))
        {
            var s = search.Trim().ToLowerInvariant();
            query = query.Where(i =>
                i.Name.ToLower().Contains(s) ||
                i.ItemCode.ToLower().Contains(s) ||
                (i.Barcode != null && i.Barcode.ToLower().Contains(s)) ||
                (i.AssetTag != null && i.AssetTag.ToLower().Contains(s)) ||
                (i.SerialNumber != null && i.SerialNumber.ToLower().Contains(s)));
        }

        var total = await query.CountAsync(ct);
        var items = await query.OrderBy(i => i.Name)
            .Skip((page - 1) * pageSize).Take(pageSize)
            .ToListAsync(ct);

        return new PagedResult<InventoryItemDto>(items.Select(Map).ToList(), total, page, pageSize);
    }

    public async Task<InventoryItemDto?> GetByIdAsync(Guid id, CancellationToken ct = default)
    {
        var item = await _db.InventoryItems.AsNoTracking().Include(i => i.Department)
            .FirstOrDefaultAsync(i => i.Id == id, ct);
        return item is null ? null : Map(item);
    }

    public async Task<InventoryItemDto?> GetByBarcodeAsync(string barcode, CancellationToken ct = default)
    {
        var item = await _db.InventoryItems.AsNoTracking().Include(i => i.Department)
            .FirstOrDefaultAsync(i => i.Barcode == barcode || i.QrCode == barcode || i.AssetTag == barcode, ct);
        return item is null ? null : Map(item);
    }

    public async Task<InventoryItemDto> CreateAsync(CreateInventoryItemRequest request, Guid actorId, CancellationToken ct = default)
    {
        var count = await _db.InventoryItems.IgnoreQueryFilters().CountAsync(ct) + 1;
        var code = CodeGenerator.Generate("ITM", count);
        var item = new InventoryItem
        {
            ItemCode = code,
            Name = request.Name.Trim(),
            ItemType = request.ItemType,
            Category = request.Category,
            Brand = request.Brand,
            Model = request.Model,
            SerialNumber = request.SerialNumber,
            DepartmentId = request.DepartmentId,
            Location = request.Location,
            StockQuantity = request.StockQuantity,
            MinimumStock = request.MinimumStock,
            MaximumStock = request.MaximumStock,
            Unit = request.Unit,
            PurchaseDate = request.PurchaseDate,
            WarrantyExpiry = request.WarrantyExpiry,
            ExpiryDate = request.ExpiryDate,
            SupplierId = request.SupplierId,
            Cost = request.Cost,
            Condition = request.Condition,
            Notes = request.Notes,
            Barcode = CodeGenerator.GenerateBarcode(code),
            AssetTag = $"AST-{count:D5}",
            CreatedBy = actorId
        };
        item.QrCode = CodeGenerator.GenerateQrPayload(item.Id, item.ItemCode);

        _db.InventoryItems.Add(item);
        await _db.SaveChangesAsync(ct);

        item.QrCode = CodeGenerator.GenerateQrPayload(item.Id, item.ItemCode);
        await RecordMovementAsync(item, StockMovementType.Receive, request.StockQuantity, 0, request.StockQuantity,
            actorId, null, request.DepartmentId, $"Initial stock for {item.ItemCode}", ct);

        await _db.SaveChangesAsync(ct);
        await CheckLowStockAsync(item, ct);
        await _audit.LogAsync(actorId, request.DepartmentId, "Inventory", "Create", "InventoryItem", item.Id,
            newValue: item.ItemCode, ct: ct);

        return (await GetByIdAsync(item.Id, ct))!;
    }

    public async Task<InventoryItemDto> UpdateAsync(Guid id, CreateInventoryItemRequest request, Guid actorId, CancellationToken ct = default)
    {
        var item = await _db.InventoryItems.FirstOrDefaultAsync(i => i.Id == id, ct)
            ?? throw new InvalidOperationException("Inventory item not found.");

        item.Name = request.Name.Trim();
        item.ItemType = request.ItemType;
        item.Category = request.Category;
        item.Brand = request.Brand;
        item.Model = request.Model;
        item.SerialNumber = request.SerialNumber;
        item.DepartmentId = request.DepartmentId;
        item.Location = request.Location;
        item.MinimumStock = request.MinimumStock;
        item.MaximumStock = request.MaximumStock;
        item.Unit = request.Unit;
        item.PurchaseDate = request.PurchaseDate;
        item.WarrantyExpiry = request.WarrantyExpiry;
        item.ExpiryDate = request.ExpiryDate;
        item.SupplierId = request.SupplierId;
        item.Cost = request.Cost;
        item.Condition = request.Condition;
        item.Notes = request.Notes;
        item.UpdatedBy = actorId;

        await _db.SaveChangesAsync(ct);
        await CheckLowStockAsync(item, ct);
        await _audit.LogAsync(actorId, item.DepartmentId, "Inventory", "Update", "InventoryItem", id, ct: ct);
        return (await GetByIdAsync(id, ct))!;
    }

    public async Task AdjustStockAsync(
        Guid itemId,
        decimal quantityDelta,
        StockMovementType type,
        Guid actorId,
        string? reason,
        CancellationToken ct = default)
    {
        var item = await _db.InventoryItems.FirstOrDefaultAsync(i => i.Id == itemId, ct)
            ?? throw new InvalidOperationException("Inventory item not found.");

        var before = item.StockQuantity;
        var after = before + quantityDelta;
        if (after < 0) throw new InvalidOperationException("Insufficient stock.");

        item.StockQuantity = after;
        if (type == StockMovementType.Disposed) item.Status = ItemStatus.Disposed;
        if (type == StockMovementType.Lost) item.Status = ItemStatus.Lost;
        if (type == StockMovementType.Damaged) item.Status = ItemStatus.Damaged;
        if (type == StockMovementType.Repair) item.Status = ItemStatus.UnderRepair;

        await RecordMovementAsync(item, type, Math.Abs(quantityDelta), before, after, actorId,
            item.DepartmentId, item.DepartmentId, reason, ct);
        await _db.SaveChangesAsync(ct);
        await CheckLowStockAsync(item, ct);
        await _audit.LogAsync(actorId, item.DepartmentId, "Inventory", type.ToString(), "InventoryItem", itemId,
            before.ToString(), after.ToString(), details: reason, ct: ct);
    }

    public async Task TransferAsync(
        Guid itemId,
        Guid toDepartmentId,
        decimal quantity,
        Guid actorId,
        string? reason,
        CancellationToken ct = default)
    {
        var item = await _db.InventoryItems.FirstOrDefaultAsync(i => i.Id == itemId, ct)
            ?? throw new InvalidOperationException("Inventory item not found.");

        if (item.StockQuantity < quantity)
            throw new InvalidOperationException("Insufficient stock for transfer.");

        var before = item.StockQuantity;
        item.StockQuantity -= quantity;
        var fromDept = item.DepartmentId;

        await RecordMovementAsync(item, StockMovementType.Transfer, quantity, before, item.StockQuantity,
            actorId, fromDept, toDepartmentId, reason, ct);

        // Create/increase destination department stock mirror for transferable consumables
        var destItem = await _db.InventoryItems.FirstOrDefaultAsync(i =>
            i.Name == item.Name && i.DepartmentId == toDepartmentId && i.ItemType == item.ItemType, ct);

        if (destItem is null)
        {
            var count = await _db.InventoryItems.IgnoreQueryFilters().CountAsync(ct) + 1;
            destItem = new InventoryItem
            {
                ItemCode = CodeGenerator.Generate("ITM", count),
                Name = item.Name,
                ItemType = item.ItemType,
                Category = item.Category,
                Brand = item.Brand,
                Model = item.Model,
                DepartmentId = toDepartmentId,
                StockQuantity = quantity,
                MinimumStock = item.MinimumStock,
                MaximumStock = item.MaximumStock,
                Unit = item.Unit,
                Cost = item.Cost,
                Condition = item.Condition,
                Status = ItemStatus.Available,
                CreatedBy = actorId
            };
            destItem.Barcode = CodeGenerator.GenerateBarcode(destItem.ItemCode);
            destItem.QrCode = CodeGenerator.GenerateQrPayload(destItem.Id, destItem.ItemCode);
            _db.InventoryItems.Add(destItem);
        }
        else
        {
            var destBefore = destItem.StockQuantity;
            destItem.StockQuantity += quantity;
            await RecordMovementAsync(destItem, StockMovementType.Receive, quantity, destBefore, destItem.StockQuantity,
                actorId, fromDept, toDepartmentId, reason, ct);
        }

        await _db.SaveChangesAsync(ct);
        await _audit.LogAsync(actorId, fromDept, "Inventory", "Transfer", "InventoryItem", itemId, ct: ct);
    }

    public async Task<IReadOnlyList<StockMovementDto>> GetMovementsAsync(
        Guid? itemId,
        Guid? departmentId,
        int take = 100,
        CancellationToken ct = default)
    {
        var query = _db.StockMovements.AsNoTracking()
            .Include(m => m.InventoryItem)
            .Include(m => m.FromDepartment)
            .Include(m => m.ToDepartment)
            .Include(m => m.PerformedBy)
            .AsQueryable();

        if (itemId.HasValue) query = query.Where(m => m.InventoryItemId == itemId);
        if (departmentId.HasValue)
            query = query.Where(m => m.FromDepartmentId == departmentId || m.ToDepartmentId == departmentId);

        return await query.OrderByDescending(m => m.MovementDate).Take(take)
            .Select(m => new StockMovementDto(
                m.Id, m.InventoryItemId, m.InventoryItem.Name, m.MovementType, m.Quantity,
                m.QuantityBefore, m.QuantityAfter,
                m.FromDepartment != null ? m.FromDepartment.Name : null,
                m.ToDepartment != null ? m.ToDepartment.Name : null,
                m.ReferenceNumber, m.PerformedBy.FullName, m.MovementDate, m.Reason))
            .ToListAsync(ct);
    }

    public async Task<IReadOnlyList<InventoryItemDto>> GetLowStockAsync(Guid? departmentId = null, CancellationToken ct = default)
    {
        var query = _db.InventoryItems.AsNoTracking().Include(i => i.Department)
            .Where(i => i.StockQuantity <= i.MinimumStock);
        if (departmentId.HasValue) query = query.Where(i => i.DepartmentId == departmentId);
        var items = await query.OrderBy(i => i.StockQuantity).ToListAsync(ct);
        return items.Select(Map).ToList();
    }

    private async Task RecordMovementAsync(
        InventoryItem item,
        StockMovementType type,
        decimal qty,
        decimal before,
        decimal after,
        Guid actorId,
        Guid? fromDept,
        Guid? toDept,
        string? reason,
        CancellationToken ct)
    {
        _db.StockMovements.Add(new StockMovement
        {
            InventoryItemId = item.Id,
            MovementType = type,
            Quantity = qty,
            QuantityBefore = before,
            QuantityAfter = after,
            FromDepartmentId = fromDept,
            ToDepartmentId = toDept,
            Reason = reason,
            PerformedById = actorId,
            MovementDate = DateTime.UtcNow
        });
        await Task.CompletedTask;
    }

    private async Task CheckLowStockAsync(InventoryItem item, CancellationToken ct)
    {
        if (!item.IsLowStock) return;

        var admins = await _db.Users.Where(u => u.IsSuperAdmin && u.Status == UserStatus.Active)
            .Select(u => u.Id).ToListAsync(ct);

        foreach (var adminId in admins)
        {
            await _notifications.NotifyAsync(adminId, NotificationType.LowStock, "Low Stock Alert",
                $"{item.Name} ({item.ItemCode}) is low: {item.StockQuantity} {item.Unit}",
                item.DepartmentId, "inventory", item.Id, ct);
        }
    }

    private static InventoryItemDto Map(InventoryItem i)
        => new(
            i.Id, i.ItemCode, i.Name, i.ItemType, i.Category, i.Brand, i.Model, i.Barcode,
            i.SerialNumber, i.AssetTag, i.DepartmentId, i.Department?.Name, i.Location,
            i.StockQuantity, i.MinimumStock, i.StockQuantity <= i.MinimumStock, i.Status,
            i.Condition, i.Cost, i.WarrantyExpiry, i.ImagePath);
}
