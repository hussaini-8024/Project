namespace EnterpriseProcurement.Core.Entities;

public class PurchaseRequestItem : BaseEntity
{
    public Guid PurchaseRequestId { get; set; }
    public PurchaseRequest PurchaseRequest { get; set; } = null!;
    public string ItemName { get; set; } = string.Empty;
    public string? Description { get; set; }
    public string? Specification { get; set; }
    public string Unit { get; set; } = "Pcs";
    public decimal Quantity { get; set; }
    public decimal UnitPrice { get; set; }
    public decimal TotalPrice => Quantity * UnitPrice;
    public decimal? ReceivedQuantity { get; set; }
    public string? Category { get; set; }
    public int SortOrder { get; set; }
}
