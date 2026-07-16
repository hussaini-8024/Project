namespace EnterpriseProcurement.Core.Entities;

public class UserSession : BaseEntity
{
    public Guid UserId { get; set; }
    public User User { get; set; } = null!;
    public Guid? ActiveDepartmentId { get; set; }
    public Department? ActiveDepartment { get; set; }
    public string? ComputerName { get; set; }
    public string? IpAddress { get; set; }
    public DateTime LoginAt { get; set; } = DateTime.UtcNow;
    public DateTime? LogoutAt { get; set; }
    public DateTime LastActivityAt { get; set; } = DateTime.UtcNow;
    public bool IsActive { get; set; } = true;
    public string? SessionToken { get; set; }
}
