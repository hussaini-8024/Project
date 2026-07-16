using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Entities;

public class PurchaseRequest : BaseEntity
{
    public string RequestNumber { get; set; } = string.Empty;
    public Guid DepartmentId { get; set; }
    public Department Department { get; set; } = null!;
    public Guid RequestedById { get; set; }
    public User RequestedBy { get; set; } = null!;
    public string Title { get; set; } = string.Empty;
    public string? Description { get; set; }
    public string? Justification { get; set; }
    public ProcurementStatus Status { get; set; } = ProcurementStatus.Draft;
    public ApprovalLevel CurrentApprovalLevel { get; set; } = ApprovalLevel.Employee;
    public decimal EstimatedBudget { get; set; }
    public decimal? ApprovedBudget { get; set; }
    public Guid? SelectedVendorId { get; set; }
    public Vendor? SelectedVendor { get; set; }
    public string? PurchaseOrderNumber { get; set; }
    public DateTime? RequiredByDate { get; set; }
    public DateTime? SubmittedAt { get; set; }
    public DateTime? ApprovedAt { get; set; }
    public DateTime? ReceivedAt { get; set; }
    public DateTime? CompletedAt { get; set; }
    public string? InvoiceNumber { get; set; }
    public decimal? InvoiceAmount { get; set; }
    public string? Notes { get; set; }
    public string? RejectionReason { get; set; }

    public ICollection<PurchaseRequestItem> Items { get; set; } = new List<PurchaseRequestItem>();
    public ICollection<Quotation> Quotations { get; set; } = new List<Quotation>();
    public ICollection<ApprovalHistory> ApprovalHistory { get; set; } = new List<ApprovalHistory>();
    public ICollection<Attachment> Attachments { get; set; } = new List<Attachment>();
}
