namespace EnterpriseProcurement.Core.Entities;

public class UserDepartment : BaseEntity
{
    public Guid UserId { get; set; }
    public User User { get; set; } = null!;
    public Guid DepartmentId { get; set; }
    public Department Department { get; set; } = null!;
    public bool IsPrimary { get; set; }
    public bool IsActive { get; set; } = true;
}
