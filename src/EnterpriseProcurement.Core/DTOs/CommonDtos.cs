using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.DTOs;

public record DepartmentDto(
    Guid Id,
    string Code,
    string Name,
    string? Description,
    string? HeadName,
    DepartmentStatus Status,
    decimal? AnnualBudget,
    bool IsPrimary = false);

public record MenuPermissionDto(
    Guid MenuId,
    string MenuKey,
    string Title,
    string? Icon,
    string? Route,
    Guid? ParentId,
    int SortOrder,
    PermissionAction Actions,
    bool RequiresDepartment);

public record UserDto(
    Guid Id,
    string Username,
    string Email,
    string FullName,
    string? Phone,
    string? EmployeeCode,
    string? Designation,
    Guid? RoleId,
    string? RoleName,
    UserStatus Status,
    bool IsSuperAdmin,
    bool TwoFactorEnabled,
    IReadOnlyList<DepartmentDto> Departments,
    DateTime? LastLoginAt);

public record PagedResult<T>(
    IReadOnlyList<T> Items,
    int TotalCount,
    int Page,
    int PageSize)
{
    public int TotalPages => PageSize <= 0 ? 0 : (int)Math.Ceiling(TotalCount / (double)PageSize);
    public bool HasNext => Page < TotalPages;
    public bool HasPrevious => Page > 1;
}

public record DashboardStatsDto(
    int TotalDepartments,
    int TotalUsers,
    int TotalProcurementRequests,
    int PendingProcurement,
    int ApprovedProcurement,
    int TotalInventory,
    int AllocatedItems,
    int ReturnedItems,
    int LowStockAlerts,
    IReadOnlyList<ChartPointDto> MonthlyProcurement,
    IReadOnlyList<ChartPointDto> InventoryByCategory,
    IReadOnlyList<ActivityItemDto> RecentActivities,
    IReadOnlyList<InventoryItemDto> RecentlyAddedAssets);

public record ChartPointDto(string Label, decimal Value);

public record ActivityItemDto(
    Guid Id,
    string Module,
    string Action,
    string? Username,
    string? DepartmentName,
    DateTime ActionAt,
    string? Details);

public record SearchResultDto(
    string EntityType,
    Guid EntityId,
    string Title,
    string? Subtitle,
    string Module);
