using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Reports;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Reports;

public partial class ReportsView : UserControl
{
    public ReportsView()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<ReportsViewModel>();
    }
}
