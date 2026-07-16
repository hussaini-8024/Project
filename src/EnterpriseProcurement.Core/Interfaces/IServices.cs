using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.Interfaces;

public interface IAuthService
{
    Task<LoginResult> LoginAsync(LoginRequest request, string? computerName = null, string? ipAddress = null, CancellationToken ct = default);
    Task LogoutAsync(Guid userId, string? sessionToken = null, CancellationToken ct = default);
    Task<bool> ForgotPasswordAsync(ForgotPasswordRequest request, CancellationToken ct = default);
    Task<bool> ResetPasswordAsync(ResetPasswordRequest request, CancellationToken ct = default);
    Task<bool> ChangePasswordAsync(ChangePasswordRequest request, CancellationToken ct = default);
    Task<UserSessionDto?> ValidateSessionAsync(string sessionToken, CancellationToken ct = default);
    Task<UserSessionDto?> SwitchDepartmentAsync(Guid userId, Guid departmentId, string sessionToken, CancellationToken ct = default);
}

public interface IPermissionService
{
    Task<IReadOnlyList<MenuPermissionDto>> GetEffectivePermissionsAsync(Guid userId, Guid? departmentId = null, CancellationToken ct = default);
    Task<bool> HasPermissionAsync(Guid userId, string menuKey, PermissionAction action, Guid? departmentId = null, CancellationToken ct = default);
    Task AssignRolePermissionsAsync(Guid roleId, IReadOnlyDictionary<Guid, PermissionAction> permissions, CancellationToken ct = default);
    Task AssignUserPermissionsAsync(Guid userId, Guid? departmentId, IReadOnlyDictionary<Guid, PermissionAction> permissions, CancellationToken ct = default);
}

public interface IDepartmentService
{
    Task<PagedResult<DepartmentDto>> GetPagedAsync(string? search, int page, int pageSize, CancellationToken ct = default);
    Task<IReadOnlyList<DepartmentDto>> GetAllActiveAsync(CancellationToken ct = default);
    Task<DepartmentDto?> GetByIdAsync(Guid id, CancellationToken ct = default);
    Task<DepartmentDto> CreateAsync(CreateDepartmentRequest request, Guid actorId, CancellationToken ct = default);
    Task<DepartmentDto> UpdateAsync(UpdateDepartmentRequest request, Guid actorId, CancellationToken ct = default);
    Task DeactivateAsync(Guid id, Guid actorId, CancellationToken ct = default);
    Task DeleteAsync(Guid id, Guid actorId, CancellationToken ct = default);
}

public interface IUserService
{
    Task<PagedResult<UserDto>> GetPagedAsync(string? search, Guid? departmentId, int page, int pageSize, CancellationToken ct = default);
    Task<UserDto?> GetByIdAsync(Guid id, CancellationToken ct = default);
    Task<UserDto> CreateAsync(CreateUserRequest request, Guid actorId, CancellationToken ct = default);
    Task<UserDto> UpdateAsync(UpdateUserRequest request, Guid actorId, CancellationToken ct = default);
    Task ResetPasswordAsync(Guid userId, string newPassword, Guid actorId, CancellationToken ct = default);
    Task SetStatusAsync(Guid userId, UserStatus status, Guid actorId, CancellationToken ct = default);
}

public interface IProcurementService
{
    Task<PagedResult<PurchaseRequestDto>> GetPagedAsync(Guid? departmentId, ProcurementStatus? status, string? search, int page, int pageSize, CancellationToken ct = default);
    Task<PurchaseRequestDto?> GetByIdAsync(Guid id, CancellationToken ct = default);
    Task<PurchaseRequestDto> CreateAsync(CreatePurchaseRequestRequest request, CancellationToken ct = default);
    Task<PurchaseRequestDto> SubmitAsync(Guid id, Guid actorId, CancellationToken ct = default);
    Task<PurchaseRequestDto> ApproveAsync(Guid id, Guid actorId, string? comments, CancellationToken ct = default);
    Task<PurchaseRequestDto> RejectAsync(Guid id, Guid actorId, string reason, CancellationToken ct = default);
    Task<PurchaseRequestDto> GeneratePurchaseOrderAsync(Guid id, Guid vendorId, Guid actorId, CancellationToken ct = default);
    Task<PurchaseRequestDto> ReceiveGoodsAsync(Guid id, Guid actorId, CancellationToken ct = default);
    Task AddQuotationAsync(Guid requestId, Guid vendorId, decimal totalAmount, int deliveryDays, string? notes, CancellationToken ct = default);
}

public interface IInventoryService
{
    Task<PagedResult<InventoryItemDto>> GetPagedAsync(Guid? departmentId, string? search, bool lowStockOnly, int page, int pageSize, CancellationToken ct = default);
    Task<InventoryItemDto?> GetByIdAsync(Guid id, CancellationToken ct = default);
    Task<InventoryItemDto?> GetByBarcodeAsync(string barcode, CancellationToken ct = default);
    Task<InventoryItemDto> CreateAsync(CreateInventoryItemRequest request, Guid actorId, CancellationToken ct = default);
    Task<InventoryItemDto> UpdateAsync(Guid id, CreateInventoryItemRequest request, Guid actorId, CancellationToken ct = default);
    Task AdjustStockAsync(Guid itemId, decimal quantityDelta, StockMovementType type, Guid actorId, string? reason, CancellationToken ct = default);
    Task TransferAsync(Guid itemId, Guid toDepartmentId, decimal quantity, Guid actorId, string? reason, CancellationToken ct = default);
    Task<IReadOnlyList<StockMovementDto>> GetMovementsAsync(Guid? itemId, Guid? departmentId, int take = 100, CancellationToken ct = default);
    Task<IReadOnlyList<InventoryItemDto>> GetLowStockAsync(Guid? departmentId = null, CancellationToken ct = default);
}

public interface IAllocationService
{
    Task<PagedResult<AllocationDto>> GetPagedAsync(Guid? departmentId, AllocationStatus? status, int page, int pageSize, CancellationToken ct = default);
    Task<AllocationDto> CreateAsync(CreateAllocationRequest request, CancellationToken ct = default);
    Task<AllocationDto> ApproveAsync(Guid id, Guid actorId, CancellationToken ct = default);
    Task<AllocationDto> AcknowledgeAsync(Guid id, string? signaturePath, CancellationToken ct = default);
    Task<AllocationDto> ReturnAsync(Guid id, ItemCondition returnCondition, string? notes, Guid actorId, CancellationToken ct = default);
}

public interface IVendorService
{
    Task<PagedResult<VendorDto>> GetPagedAsync(string? search, int page, int pageSize, CancellationToken ct = default);
    Task<VendorDto?> GetByIdAsync(Guid id, CancellationToken ct = default);
    Task<VendorDto> CreateAsync(CreateVendorRequest request, Guid actorId, CancellationToken ct = default);
    Task<VendorDto> UpdateAsync(Guid id, CreateVendorRequest request, Guid actorId, CancellationToken ct = default);
    Task SetActiveAsync(Guid id, bool isActive, Guid actorId, CancellationToken ct = default);
}

public interface IDashboardService
{
    Task<DashboardStatsDto> GetAdminDashboardAsync(CancellationToken ct = default);
    Task<DashboardStatsDto> GetUserDashboardAsync(Guid userId, Guid? departmentId, CancellationToken ct = default);
}

public interface IAuditService
{
    Task LogAsync(Guid? userId, Guid? departmentId, string module, string action, string? entityType = null, Guid? entityId = null, string? oldValue = null, string? newValue = null, string? computerName = null, string? ipAddress = null, string? details = null, CancellationToken ct = default);
    Task<PagedResult<AuditLogDto>> GetPagedAsync(string? search, Guid? userId, Guid? departmentId, DateTime? from, DateTime? to, int page, int pageSize, CancellationToken ct = default);
}

public interface INotificationService
{
    Task NotifyAsync(Guid userId, NotificationType type, string title, string message, Guid? departmentId = null, string? linkModule = null, Guid? linkEntityId = null, CancellationToken ct = default);
    Task<IReadOnlyList<NotificationDto>> GetUnreadAsync(Guid userId, CancellationToken ct = default);
    Task<IReadOnlyList<NotificationDto>> GetRecentAsync(Guid userId, int take = 50, CancellationToken ct = default);
    Task MarkReadAsync(Guid notificationId, CancellationToken ct = default);
    Task MarkAllReadAsync(Guid userId, CancellationToken ct = default);
}

public interface ISearchService
{
    Task<IReadOnlyList<SearchResultDto>> SearchAsync(string query, Guid? departmentId, bool isAdmin, int take = 25, CancellationToken ct = default);
}

public interface IBackupService
{
    Task<string> BackupAsync(string? destinationPath = null, CancellationToken ct = default);
    Task RestoreAsync(string backupPath, CancellationToken ct = default);
    Task<IReadOnlyList<string>> ListBackupsAsync(CancellationToken ct = default);
}

public interface ISettingsService
{
    Task<string?> GetAsync(string key, CancellationToken ct = default);
    Task SetAsync(string key, string value, string? category = null, CancellationToken ct = default);
    Task<CompanyProfileDto?> GetCompanyProfileAsync(CancellationToken ct = default);
    Task SaveCompanyProfileAsync(CompanyProfileDto profile, CancellationToken ct = default);
}

public record CompanyProfileDto(
    Guid Id,
    string OrganizationName,
    string? LegalName,
    string? Address,
    string? City,
    string? Country,
    string? Phone,
    string? Email,
    string? Website,
    string? NTN,
    string? STRN,
    string? LogoPath,
    string? PrimaryColor,
    string? SecondaryColor,
    string? AccentColor,
    int FiscalYearStartMonth,
    string? DefaultLanguage);

public interface IReportService
{
    Task<byte[]> ExportProcurementCsvAsync(Guid? departmentId, DateTime? from, DateTime? to, CancellationToken ct = default);
    Task<byte[]> ExportInventoryCsvAsync(Guid? departmentId, CancellationToken ct = default);
    Task<byte[]> ExportAllocationCsvAsync(Guid? departmentId, CancellationToken ct = default);
    Task<byte[]> ExportAuditCsvAsync(DateTime? from, DateTime? to, CancellationToken ct = default);
}
