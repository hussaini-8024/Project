using EnterpriseProcurement.Core.DTOs;
using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Enums;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class NotificationService : INotificationService
{
    private readonly AppDbContext _db;

    public NotificationService(AppDbContext db) => _db = db;

    public async Task NotifyAsync(
        Guid userId,
        NotificationType type,
        string title,
        string message,
        Guid? departmentId = null,
        string? linkModule = null,
        Guid? linkEntityId = null,
        CancellationToken ct = default)
    {
        _db.Notifications.Add(new Notification
        {
            UserId = userId,
            DepartmentId = departmentId,
            Type = type,
            Title = title,
            Message = message,
            LinkModule = linkModule,
            LinkEntityId = linkEntityId
        });
        await _db.SaveChangesAsync(ct);
    }

    public async Task<IReadOnlyList<NotificationDto>> GetUnreadAsync(Guid userId, CancellationToken ct = default)
        => await MapAsync(_db.Notifications.Where(n => n.UserId == userId && !n.IsRead), 100, ct);

    public async Task<IReadOnlyList<NotificationDto>> GetRecentAsync(Guid userId, int take = 50, CancellationToken ct = default)
        => await MapAsync(_db.Notifications.Where(n => n.UserId == userId), take, ct);

    public async Task MarkReadAsync(Guid notificationId, CancellationToken ct = default)
    {
        var n = await _db.Notifications.FirstOrDefaultAsync(x => x.Id == notificationId, ct);
        if (n is null) return;
        n.IsRead = true;
        n.ReadAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);
    }

    public async Task MarkAllReadAsync(Guid userId, CancellationToken ct = default)
    {
        var items = await _db.Notifications.Where(n => n.UserId == userId && !n.IsRead).ToListAsync(ct);
        foreach (var n in items)
        {
            n.IsRead = true;
            n.ReadAt = DateTime.UtcNow;
        }
        await _db.SaveChangesAsync(ct);
    }

    private static async Task<IReadOnlyList<NotificationDto>> MapAsync(IQueryable<Notification> query, int take, CancellationToken ct)
        => await query.AsNoTracking()
            .OrderByDescending(n => n.CreatedAt)
            .Take(take)
            .Select(n => new NotificationDto(n.Id, n.Type, n.Title, n.Message, n.IsRead, n.CreatedAt, n.LinkModule, n.LinkEntityId))
            .ToListAsync(ct);
}
