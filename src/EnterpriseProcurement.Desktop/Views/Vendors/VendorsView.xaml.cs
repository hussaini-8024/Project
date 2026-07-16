using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Vendors;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Vendors;

public partial class VendorsView : UserControl
{
    public VendorsView()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<VendorsViewModel>();
    }
}
