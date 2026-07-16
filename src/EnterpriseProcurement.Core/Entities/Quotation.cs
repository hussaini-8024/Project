namespace EnterpriseProcurement.Core.Entities;

public class Quotation : BaseEntity
{
    public Guid PurchaseRequestId { get; set; }
    public PurchaseRequest PurchaseRequest { get; set; } = null!;
    public Guid VendorId { get; set; }
    public Vendor Vendor { get; set; } = null!;
    public string QuotationNumber { get; set; } = string.Empty;
    public DateTime QuotationDate { get; set; } = DateTime.UtcNow;
    public DateTime? ValidUntil { get; set; }
    public decimal TotalAmount { get; set; }
    public decimal? TaxAmount { get; set; }
    public int DeliveryDays { get; set; }
    public string? PaymentTerms { get; set; }
    public string? Notes { get; set; }
    public bool IsSelected { get; set; }
    public string? AttachmentPath { get; set; }
}
