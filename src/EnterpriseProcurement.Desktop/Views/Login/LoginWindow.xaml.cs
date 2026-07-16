using System.Windows;
using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Login;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Login;

public partial class LoginWindow : Window
{
    public LoginWindow()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<LoginViewModel>();
    }

    private void PasswordBox_OnPasswordChanged(object sender, RoutedEventArgs e)
    {
        if (DataContext is LoginViewModel vm && sender is PasswordBox box)
            vm.Password = box.Password;
    }
}
