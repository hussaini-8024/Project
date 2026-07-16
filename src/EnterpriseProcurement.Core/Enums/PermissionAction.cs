namespace EnterpriseProcurement.Core.Enums;

/// <summary>
/// Fine-grained menu-level permission actions.
/// </summary>
[Flags]
public enum PermissionAction
{
    None = 0,
    View = 1,
    Add = 2,
    Edit = 4,
    Delete = 8,
    Print = 16,
    Export = 32,
    Approve = 64,
    All = View | Add | Edit | Delete | Print | Export | Approve
}
