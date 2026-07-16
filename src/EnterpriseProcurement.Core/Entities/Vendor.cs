namespace EnterpriseProcurement.Core.Entities;

public class Vendor : BaseEntity
{
    public string Code { get; set; } = string.Empty;
    public string CompanyName { get; set; } = string.Empty;
    public string? ContactPerson { get; set; }
    public string? Address { get; set; }
    public string? City { get; set; }
    public string? Country { get; set; }
    public string? Phone { get; set; }
    public string? Email { get; set; }
    public string? Website { get; set; }
    public string? NTN { get; set; }
    public string? STRN { get; set; }
    public string? BankName { get; set; }
    public string? BankAccountNumber { get; set; }
    public string? BankIBAN { get; set; }
    public decimal Rating { get; set; }
    public string? PerformanceNotes { get; set; }
    public bool IsActive { get; set; } = true;
    public string? Notes { get; set; }

    public ICollection<VendorDocument> Documents { get; set; } = new List<VendorDocument>();
    public ICollection<PurchaseRequest> PurchaseRequests { get; set; } = new List<PurchaseRequest>();
    public ICollection<Quotation> Quotations { get; set; } = new List<Quotation>();
}
