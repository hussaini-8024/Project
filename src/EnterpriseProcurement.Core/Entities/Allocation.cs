using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Entities;

public class Allocation : BaseEntity
{
    public string AllocationNumber { get; set; } = string.Empty;
    public Guid InventoryItemId { get; set; }
    public InventoryItem InventoryItem { get; set; } = null!;
    public Guid DepartmentId { get; set; }
    public Department Department { get; set; } = null!;
    public Guid? EmployeeId { get; set; }
    public User? Employee { get; set; }
    public string? EmployeeName { get; set; }
    public string? Office { get; set; }
    public string? Location { get; set; }
    public decimal Quantity { get; set; } = 1;
    public DateTime IssueDate { get; set; } = DateTime.UtcNow;
    public DateTime? ExpectedReturnDate { get; set; }
    public DateTime? ActualReturnDate { get; set; }
    public ItemCondition IssueCondition { get; set; } = ItemCondition.Good;
    public ItemCondition? ReturnCondition { get; set; }
    public AllocationStatus Status { get; set; } = AllocationStatus.Pending;
    public Guid IssuedById { get; set; }
    public User IssuedBy { get; set; } = null!;
    public Guid? ApprovedById { get; set; }
    public User? ApprovedBy { get; set; }
    public DateTime? ApprovedAt { get; set; }
    public bool IsAcknowledged { get; set; }
    public DateTime? AcknowledgedAt { get; set; }
    public string? DigitalSignaturePath { get; set; }
    public string? Notes { get; set; }
    public string? ReturnNotes { get; set; }
}
