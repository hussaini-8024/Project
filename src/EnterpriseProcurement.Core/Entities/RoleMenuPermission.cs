using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Entities;

public class RoleMenuPermission : BaseEntity
{
    public Guid RoleId { get; set; }
    public Role Role { get; set; } = null!;
    public Guid MenuId { get; set; }
    public Menu Menu { get; set; } = null!;
    public PermissionAction Actions { get; set; } = PermissionAction.View;
}
