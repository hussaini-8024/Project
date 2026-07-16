using EnterpriseProcurement.Business.Services;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Data.Context;
using EnterpriseProcurement.Data.Seed;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Tests;

public class InventoryAndProcurementTests : IAsyncLifetime
{
    private SqliteConnection _connection = null!;
    private AppDbContext _db = null!;
    private InventoryService _inventory = null!;
    private ProcurementService _procurement = null!;
    private AllocationService _allocations = null!;
    private Guid _adminId;
    private Guid _itDeptId;

    public async Task InitializeAsync()
    {
        _connection = new SqliteConnection("DataSource=:memory:");
        await _connection.OpenAsync();
        var options = new DbContextOptionsBuilder<AppDbContext>().UseSqlite(_connection).Options;
        _db = new AppDbContext(options);
        await DbSeeder.SeedAsync(_db);

        var audit = new AuditService(_db);
        var notifications = new NotificationService(_db);
        _inventory = new InventoryService(_db, audit, notifications);
        _procurement = new ProcurementService(_db, audit, notifications, _inventory);
        _allocations = new AllocationService(_db, audit, notifications, _inventory);

        _adminId = _db.Users.First(u => u.Username == "admin").Id;
        _itDeptId = _db.Departments.First(d => d.Code == "IT").Id;
    }

    public async Task DisposeAsync()
    {
        await _db.DisposeAsync();
        await _connection.DisposeAsync();
    }

    [Fact]
    public async Task CreateInventoryItem_GeneratesBarcodeAndTracksStock()
    {
        var item = await _inventory.CreateAsync(new CreateInventoryItemRequest(
            "Test Monitor", InventoryItemType.Electronics, "Displays", "Dell", "P2422H", "SN-1",
            _itDeptId, "Store", 5, 2, 20, "Pcs", DateTime.UtcNow, null, null, null, 45000,
            ItemCondition.New, null), _adminId);

        Assert.False(string.IsNullOrWhiteSpace(item.Barcode));
        Assert.Equal(5, item.StockQuantity);
        Assert.False(item.IsLowStock);

        await _inventory.AdjustStockAsync(item.Id, -4, StockMovementType.Allocate, _adminId, "Issue");
        var updated = await _inventory.GetByIdAsync(item.Id);
        Assert.NotNull(updated);
        Assert.Equal(1, updated!.StockQuantity);
        Assert.True(updated.IsLowStock);
    }

    [Fact]
    public async Task ProcurementWorkflow_DraftToReceive_UpdatesInventory()
    {
        var beforeCount = await _db.InventoryItems.CountAsync();
        var pr = await _procurement.CreateAsync(new CreatePurchaseRequestRequest(
            _itDeptId, _adminId, "Office Chairs", "Ergonomic chairs", "Staff expansion",
            50000, DateTime.UtcNow.AddDays(14),
            new[]
            {
                new PurchaseRequestItemDto(Guid.Empty, "Office Chair", null, "Mesh back", "Pcs", 2, 25000, 50000, "Furniture")
            }));

        Assert.Equal(ProcurementStatus.Draft, pr.Status);

        pr = await _procurement.SubmitAsync(pr.Id, _adminId);
        Assert.Equal(ProcurementStatus.PendingApproval, pr.Status);

        // Advance through approval levels
        for (var i = 0; i < 5; i++)
        {
            pr = await _procurement.ApproveAsync(pr.Id, _adminId, "ok");
            if (pr.Status == ProcurementStatus.Approved) break;
        }

        Assert.Equal(ProcurementStatus.Approved, pr.Status);

        var vendorId = _db.Vendors.First().Id;
        pr = await _procurement.GeneratePurchaseOrderAsync(pr.Id, vendorId, _adminId);
        Assert.Equal(ProcurementStatus.PurchaseOrder, pr.Status);
        Assert.False(string.IsNullOrWhiteSpace(pr.PurchaseOrderNumber));

        pr = await _procurement.ReceiveGoodsAsync(pr.Id, _adminId);
        Assert.Equal(ProcurementStatus.Received, pr.Status);

        var afterCount = await _db.InventoryItems.CountAsync();
        Assert.True(afterCount > beforeCount);
    }

    [Fact]
    public async Task Allocation_ApproveAndReturn_RestoresStock()
    {
        var item = await _inventory.CreateAsync(new CreateInventoryItemRequest(
            "Laptop Bag", InventoryItemType.Consumable, "Accessories", null, null, null,
            _itDeptId, "Store", 10, 1, 50, "Pcs", null, null, null, null, 1500,
            ItemCondition.New, null), _adminId);

        var allocation = await _allocations.CreateAsync(new CreateAllocationRequest(
            item.Id, _itDeptId, _adminId, null, "Qasim", "IT Office", "Floor 2",
            2, DateTime.UtcNow.AddDays(30), ItemCondition.Good, null));

        Assert.Equal(AllocationStatus.Pending, allocation.Status);

        allocation = await _allocations.ApproveAsync(allocation.Id, _adminId);
        Assert.Equal(AllocationStatus.Issued, allocation.Status);

        var afterIssue = await _inventory.GetByIdAsync(item.Id);
        Assert.Equal(8, afterIssue!.StockQuantity);

        allocation = await _allocations.ReturnAsync(allocation.Id, ItemCondition.Good, "OK", _adminId);
        Assert.Equal(AllocationStatus.Returned, allocation.Status);

        var afterReturn = await _inventory.GetByIdAsync(item.Id);
        Assert.Equal(10, afterReturn!.StockQuantity);
    }
}
