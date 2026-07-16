using EnterpriseProcurement.Core.Enums;

namespace EnterpriseProcurement.Core.DTOs;

public record VendorDto(
    Guid Id,
    string Code,
    string CompanyName,
    string? ContactPerson,
    string? Phone,
    string? Email,
    string? NTN,
    string? STRN,
    decimal Rating,
    bool IsActive,
    string? City,
    string? Address);

public record PurchaseRequestDto(
    Guid Id,
    string RequestNumber,
    Guid DepartmentId,
    string DepartmentName,
    Guid RequestedById,
    string RequestedByName,
    string Title,
    string? Description,
    ProcurementStatus Status,
    ApprovalLevel CurrentApprovalLevel,
    decimal EstimatedBudget,
    decimal? ApprovedBudget,
    string? SelectedVendorName,
    string? PurchaseOrderNumber,
    DateTime? RequiredByDate,
    DateTime CreatedAt,
    int ItemCount);

public record PurchaseRequestItemDto(
    Guid Id,
    string ItemName,
    string? Description,
    string? Specification,
    string Unit,
    decimal Quantity,
    decimal UnitPrice,
    decimal TotalPrice,
    string? Category);

public record InventoryItemDto(
    Guid Id,
    string ItemCode,
    string Name,
    InventoryItemType ItemType,
    string? Category,
    string? Brand,
    string? Model,
    string? Barcode,
    string? SerialNumber,
    string? AssetTag,
    Guid? DepartmentId,
    string? DepartmentName,
    string? Location,
    decimal StockQuantity,
    decimal MinimumStock,
    bool IsLowStock,
    ItemStatus Status,
    ItemCondition Condition,
    decimal Cost,
    DateTime? WarrantyExpiry,
    string? ImagePath);

public record AllocationDto(
    Guid Id,
    string AllocationNumber,
    Guid InventoryItemId,
    string ItemName,
    string ItemCode,
    Guid DepartmentId,
    string DepartmentName,
    string? EmployeeName,
    string? Office,
    string? Location,
    decimal Quantity,
    DateTime IssueDate,
    DateTime? ExpectedReturnDate,
    DateTime? ActualReturnDate,
    AllocationStatus Status,
    bool IsAcknowledged);

public record StockMovementDto(
    Guid Id,
    Guid InventoryItemId,
    string ItemName,
    StockMovementType MovementType,
    decimal Quantity,
    decimal QuantityBefore,
    decimal QuantityAfter,
    string? FromDepartment,
    string? ToDepartment,
    string? ReferenceNumber,
    string PerformedBy,
    DateTime MovementDate,
    string? Reason);

public record AuditLogDto(
    Guid Id,
    string? Username,
    string? DepartmentName,
    string? ComputerName,
    string? IpAddress,
    string Module,
    string Action,
    string? EntityType,
    string? OldValue,
    string? NewValue,
    DateTime ActionAt,
    string? Details);

public record NotificationDto(
    Guid Id,
    NotificationType Type,
    string Title,
    string Message,
    bool IsRead,
    DateTime CreatedAt,
    string? LinkModule,
    Guid? LinkEntityId);

public record CreateDepartmentRequest(
    string Code,
    string Name,
    string? Description,
    string? HeadName,
    string? Email,
    string? Phone,
    string? Location,
    decimal? AnnualBudget);

public record UpdateDepartmentRequest(
    Guid Id,
    string Code,
    string Name,
    string? Description,
    string? HeadName,
    string? Email,
    string? Phone,
    string? Location,
    decimal? AnnualBudget,
    DepartmentStatus Status);

public record CreateUserRequest(
    string Username,
    string Email,
    string Password,
    string FullName,
    string? Phone,
    string? EmployeeCode,
    string? Designation,
    Guid? RoleId,
    bool IsSuperAdmin,
    IReadOnlyList<Guid> DepartmentIds,
    Guid? PrimaryDepartmentId);

public record UpdateUserRequest(
    Guid Id,
    string Email,
    string FullName,
    string? Phone,
    string? EmployeeCode,
    string? Designation,
    Guid? RoleId,
    UserStatus Status,
    bool TwoFactorEnabled,
    IReadOnlyList<Guid> DepartmentIds,
    Guid? PrimaryDepartmentId);

public record CreatePurchaseRequestRequest(
    Guid DepartmentId,
    Guid RequestedById,
    string Title,
    string? Description,
    string? Justification,
    decimal EstimatedBudget,
    DateTime? RequiredByDate,
    IReadOnlyList<PurchaseRequestItemDto> Items);

public record CreateInventoryItemRequest(
    string Name,
    InventoryItemType ItemType,
    string? Category,
    string? Brand,
    string? Model,
    string? SerialNumber,
    Guid? DepartmentId,
    string? Location,
    decimal StockQuantity,
    decimal MinimumStock,
    decimal MaximumStock,
    string Unit,
    DateTime? PurchaseDate,
    DateTime? WarrantyExpiry,
    DateTime? ExpiryDate,
    Guid? SupplierId,
    decimal Cost,
    ItemCondition Condition,
    string? Notes);

public record CreateAllocationRequest(
    Guid InventoryItemId,
    Guid DepartmentId,
    Guid IssuedById,
    Guid? EmployeeId,
    string? EmployeeName,
    string? Office,
    string? Location,
    decimal Quantity,
    DateTime? ExpectedReturnDate,
    ItemCondition IssueCondition,
    string? Notes);

public record CreateVendorRequest(
    string CompanyName,
    string? ContactPerson,
    string? Address,
    string? City,
    string? Country,
    string? Phone,
    string? Email,
    string? NTN,
    string? STRN,
    string? BankName,
    string? BankAccountNumber,
    string? BankIBAN,
    string? Notes);
