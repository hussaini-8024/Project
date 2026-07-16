using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Settings;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Settings;

public partial class SettingsView : UserControl
{
    public SettingsView()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<SettingsViewModel>();
    }
}
