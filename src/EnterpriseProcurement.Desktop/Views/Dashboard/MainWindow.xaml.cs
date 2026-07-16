using System.Windows;
using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Dashboard;

namespace EnterpriseProcurement.Desktop.Views.Dashboard;

public partial class MainWindow : Window
{
    public MainWindow()
    {
        InitializeComponent();
    }

    private void Department_OnSelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        // Selection bound; switch is explicit via command for audit clarity.
    }
}
