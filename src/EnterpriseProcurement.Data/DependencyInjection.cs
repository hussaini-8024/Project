using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Data;

public static class DependencyInjection
{
    public static IServiceCollection AddDataLayer(
        this IServiceCollection services,
        string connectionString,
        string provider = "Sqlite",
        ServiceLifetime contextLifetime = ServiceLifetime.Scoped)
    {
        services.AddDbContext<AppDbContext>(options =>
        {
            if (string.Equals(provider, "SqlServer", StringComparison.OrdinalIgnoreCase))
                options.UseSqlServer(connectionString);
            else if (string.Equals(provider, "PostgreSQL", StringComparison.OrdinalIgnoreCase))
                throw new NotSupportedException("PostgreSQL provider package not configured. Use SqlServer or Sqlite.");
            else
                options.UseSqlite(connectionString);
        }, contextLifetime);

        return services;
    }
}
