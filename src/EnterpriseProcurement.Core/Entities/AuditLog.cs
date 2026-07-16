namespace EnterpriseProcurement.Core.Entities;

public class AuditLog : BaseEntity
{
    public Guid? UserId { get; set; }
    public User? User { get; set; }
    public string? Username { get; set; }
    public Guid? DepartmentId { get; set; }
    public Department? Department { get; set; }
    public string? ComputerName { get; set; }
    public string? IpAddress { get; set; }
    public string Module { get; set; } = string.Empty;
    public string Action { get; set; } = string.Empty;
    public string? EntityType { get; set; }
    public Guid? EntityId { get; set; }
    public string? OldValue { get; set; }
    public string? NewValue { get; set; }
    public DateTime ActionAt { get; set; } = DateTime.UtcNow;
    public string? Details { get; set; }
}
