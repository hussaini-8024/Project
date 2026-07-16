using EnterpriseProcurement.Business.Services;
using EnterpriseProcurement.Core.Constants;
using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Data.Context;
using EnterpriseProcurement.Data.Seed;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Tests;

public class AuthServiceTests : IAsyncLifetime
{
    private SqliteConnection _connection = null!;
    private AppDbContext _db = null!;
    private AuthService _auth = null!;

    public async Task InitializeAsync()
    {
        _connection = new SqliteConnection("DataSource=:memory:");
        await _connection.OpenAsync();

        var options = new DbContextOptionsBuilder<AppDbContext>()
            .UseSqlite(_connection)
            .Options;

        _db = new AppDbContext(options);
        await DbSeeder.SeedAsync(_db);

        var audit = new AuditService(_db);
        var permissions = new PermissionService(_db);
        _auth = new AuthService(_db, permissions, audit);
    }

    public async Task DisposeAsync()
    {
        await _db.DisposeAsync();
        await _connection.DisposeAsync();
    }

    [Fact]
    public async Task Login_WithDefaultAdmin_Succeeds()
    {
        var result = await _auth.LoginAsync(new LoginRequest(
            AppConstants.DefaultAdminUsername,
            AppConstants.DefaultAdminPassword));

        Assert.True(result.Success);
        Assert.NotNull(result.Session);
        Assert.True(result.Session!.IsSuperAdmin);
        Assert.Contains(result.Session.Permissions, p => p.MenuKey == MenuKeys.Departments);
    }

    [Fact]
    public async Task Login_WithInvalidPassword_Fails()
    {
        var result = await _auth.LoginAsync(new LoginRequest("admin", "wrong-password"));
        Assert.False(result.Success);
        Assert.Null(result.Session);
    }

    [Fact]
    public async Task Qasim_CanAccessAssignedDepartments_Only()
    {
        var result = await _auth.LoginAsync(new LoginRequest("qasim", "Qasim@123"));
        Assert.True(result.Success);
        Assert.NotNull(result.Session);
        Assert.False(result.Session!.IsSuperAdmin);

        var codes = result.Session.Departments.Select(d => d.Code).ToHashSet();
        Assert.Contains("IT", codes);
        Assert.Contains("PRC", codes);
        Assert.DoesNotContain("HR", codes);
        Assert.DoesNotContain("FIN", codes);
    }

    [Fact]
    public async Task SwitchDepartment_DeniedForUnassignedDepartment()
    {
        var login = await _auth.LoginAsync(new LoginRequest("qasim", "Qasim@123"));
        var hr = _db.Departments.First(d => d.Code == "HR");

        await Assert.ThrowsAsync<UnauthorizedAccessException>(() =>
            _auth.SwitchDepartmentAsync(login.Session!.UserId, hr.Id, login.Session.SessionToken));
    }

    [Fact]
    public async Task AccountLocks_AfterMaxFailedAttempts()
    {
        for (var i = 0; i < AppConstants.MaxFailedLoginAttempts; i++)
            await _auth.LoginAsync(new LoginRequest("qasim", "bad"));

        var locked = await _auth.LoginAsync(new LoginRequest("qasim", "Qasim@123"));
        Assert.False(locked.Success);
        Assert.Contains("locked", locked.Message!, StringComparison.OrdinalIgnoreCase);
    }
}
