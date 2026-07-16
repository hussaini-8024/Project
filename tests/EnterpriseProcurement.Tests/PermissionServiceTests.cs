using EnterpriseProcurement.Business.Services;
using EnterpriseProcurement.Core.Constants;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Data.Context;
using EnterpriseProcurement.Data.Seed;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Tests;

public class PermissionServiceTests : IAsyncLifetime
{
    private SqliteConnection _connection = null!;
    private AppDbContext _db = null!;
    private PermissionService _permissions = null!;

    public async Task InitializeAsync()
    {
        _connection = new SqliteConnection("DataSource=:memory:");
        await _connection.OpenAsync();
        _db = new AppDbContext(new DbContextOptionsBuilder<AppDbContext>().UseSqlite(_connection).Options);
        await DbSeeder.SeedAsync(_db);
        _permissions = new PermissionService(_db);
    }

    public async Task DisposeAsync()
    {
        await _db.DisposeAsync();
        await _connection.DisposeAsync();
    }

    [Fact]
    public async Task Admin_HasAllMenuPermissions()
    {
        var admin = _db.Users.First(u => u.IsSuperAdmin);
        var perms = await _permissions.GetEffectivePermissionsAsync(admin.Id);
        Assert.Contains(perms, p => p.MenuKey == MenuKeys.Backup && p.Actions.HasFlag(PermissionAction.All));
        Assert.True(await _permissions.HasPermissionAsync(admin.Id, MenuKeys.Users, PermissionAction.Delete));
    }

    [Fact]
    public async Task DepartmentUser_CannotAccessAdminMenus()
    {
        var qasim = _db.Users.First(u => u.Username == "qasim");
        Assert.False(await _permissions.HasPermissionAsync(qasim.Id, MenuKeys.Backup, PermissionAction.View));
        Assert.False(await _permissions.HasPermissionAsync(qasim.Id, MenuKeys.Departments, PermissionAction.Add));
        Assert.True(await _permissions.HasPermissionAsync(qasim.Id, MenuKeys.ProcurementRequests, PermissionAction.View));
    }
}
