using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Entities;

public class InventoryItem : BaseEntity
{
    public string ItemCode { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string? Description { get; set; }
    public InventoryItemType ItemType { get; set; } = InventoryItemType.Asset;
    public string? Category { get; set; }
    public string? Brand { get; set; }
    public string? Model { get; set; }
    public string? Barcode { get; set; }
    public string? QrCode { get; set; }
    public string? SerialNumber { get; set; }
    public string? AssetTag { get; set; }
    public Guid? DepartmentId { get; set; }
    public Department? Department { get; set; }
    public string? Location { get; set; }
    public decimal StockQuantity { get; set; }
    public decimal MinimumStock { get; set; }
    public decimal MaximumStock { get; set; }
    public string Unit { get; set; } = "Pcs";
    public DateTime? PurchaseDate { get; set; }
    public DateTime? WarrantyExpiry { get; set; }
    public DateTime? ExpiryDate { get; set; }
    public Guid? SupplierId { get; set; }
    public Vendor? Supplier { get; set; }
    public decimal Cost { get; set; }
    public ItemStatus Status { get; set; } = ItemStatus.Available;
    public ItemCondition Condition { get; set; } = ItemCondition.New;
    public string? ImagePath { get; set; }
    public string? Notes { get; set; }
    public Guid? PurchaseRequestId { get; set; }

    public bool IsLowStock => StockQuantity <= MinimumStock;

    public ICollection<StockMovement> StockMovements { get; set; } = new List<StockMovement>();
    public ICollection<Allocation> Allocations { get; set; } = new List<Allocation>();
}
