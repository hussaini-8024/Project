using EnterpriseProcurement.Core.DTOs;

namespace EnterpriseProcurement.Desktop.Services;

public interface ISessionContext
{
    UserSessionDto? Current { get; set; }
    bool IsAuthenticated => Current is not null;
    bool IsAdmin => Current?.IsSuperAdmin == true;
    Guid? ActiveDepartmentId => Current?.ActiveDepartmentId;
    event EventHandler? SessionChanged;
    void Clear();
}

public class SessionContext : ISessionContext
{
    private UserSessionDto? _current;

    public UserSessionDto? Current
    {
        get => _current;
        set
        {
            _current = value;
            SessionChanged?.Invoke(this, EventArgs.Empty);
        }
    }

    public event EventHandler? SessionChanged;

    public void Clear() => Current = null;
}
