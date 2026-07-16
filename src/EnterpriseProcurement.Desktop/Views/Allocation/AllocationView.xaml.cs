using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Allocation;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Allocation;

public partial class AllocationView : UserControl
{
    public AllocationView()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<AllocationViewModel>();
    }
}
