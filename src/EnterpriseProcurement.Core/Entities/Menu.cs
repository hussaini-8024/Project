namespace EnterpriseProcurement.Core.Entities;

public class Menu : BaseEntity
{
    public string Key { get; set; } = string.Empty;
    public string Title { get; set; } = string.Empty;
    public string? Icon { get; set; }
    public string? Route { get; set; }
    public Guid? ParentId { get; set; }
    public Menu? Parent { get; set; }
    public int SortOrder { get; set; }
    public bool IsActive { get; set; } = true;
    public bool RequiresDepartment { get; set; } = true;

    public ICollection<Menu> Children { get; set; } = new List<Menu>();
    public ICollection<RoleMenuPermission> RolePermissions { get; set; } = new List<RoleMenuPermission>();
    public ICollection<UserMenuPermission> UserPermissions { get; set; } = new List<UserMenuPermission>();
}
