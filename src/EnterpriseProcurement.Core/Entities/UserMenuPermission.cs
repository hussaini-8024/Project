using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Entities;

/// <summary>
/// Per-user menu override. Can grant or restrict beyond role defaults.
/// Optionally scoped to a specific department.
/// </summary>
public class UserMenuPermission : BaseEntity
{
    public Guid UserId { get; set; }
    public User User { get; set; } = null!;
    public Guid MenuId { get; set; }
    public Menu Menu { get; set; } = null!;
    public Guid? DepartmentId { get; set; }
    public Department? Department { get; set; }
    public PermissionAction Actions { get; set; } = PermissionAction.View;
    public bool IsDenied { get; set; }
}
