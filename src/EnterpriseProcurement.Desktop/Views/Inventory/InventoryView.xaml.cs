using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Inventory;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Inventory;

public partial class InventoryView : UserControl
{
    public InventoryView()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<InventoryViewModel>();
    }
}
