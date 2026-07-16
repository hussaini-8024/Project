using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Entities;

public class ApprovalHistory : BaseEntity
{
    public Guid PurchaseRequestId { get; set; }
    public PurchaseRequest PurchaseRequest { get; set; } = null!;
    public Guid ApproverId { get; set; }
    public User Approver { get; set; } = null!;
    public ApprovalLevel Level { get; set; }
    public bool IsApproved { get; set; }
    public string? Comments { get; set; }
    public DateTime ActionAt { get; set; } = DateTime.UtcNow;
}
