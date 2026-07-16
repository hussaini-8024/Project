using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Entities;

public class Department : BaseEntity
{
    public string Code { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string? Description { get; set; }
    public string? HeadName { get; set; }
    public string? Email { get; set; }
    public string? Phone { get; set; }
    public string? Location { get; set; }
    public decimal? AnnualBudget { get; set; }
    public DepartmentStatus Status { get; set; } = DepartmentStatus.Active;

    public ICollection<UserDepartment> UserDepartments { get; set; } = new List<UserDepartment>();
    public ICollection<PurchaseRequest> PurchaseRequests { get; set; } = new List<PurchaseRequest>();
    public ICollection<InventoryItem> InventoryItems { get; set; } = new List<InventoryItem>();
    public ICollection<Allocation> Allocations { get; set; } = new List<Allocation>();
}
