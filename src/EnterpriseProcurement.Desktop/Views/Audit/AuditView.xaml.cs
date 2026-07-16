using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Audit;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Audit;

public partial class AuditView : UserControl
{
    public AuditView()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<AuditViewModel>();
    }
}
