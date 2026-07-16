using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Procurement;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Procurement;

public partial class ProcurementView : UserControl
{
    public ProcurementView()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<ProcurementViewModel>();
    }
}
