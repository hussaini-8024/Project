using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Entities;

public class StockMovement : BaseEntity
{
    public Guid InventoryItemId { get; set; }
    public InventoryItem InventoryItem { get; set; } = null!;
    public StockMovementType MovementType { get; set; }
    public decimal Quantity { get; set; }
    public decimal QuantityBefore { get; set; }
    public decimal QuantityAfter { get; set; }
    public Guid? FromDepartmentId { get; set; }
    public Department? FromDepartment { get; set; }
    public Guid? ToDepartmentId { get; set; }
    public Department? ToDepartment { get; set; }
    public string? FromLocation { get; set; }
    public string? ToLocation { get; set; }
    public string? ReferenceNumber { get; set; }
    public string? Reason { get; set; }
    public Guid PerformedById { get; set; }
    public User PerformedBy { get; set; } = null!;
    public DateTime MovementDate { get; set; } = DateTime.UtcNow;
}
