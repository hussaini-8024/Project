using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Users;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Users;

public partial class UsersView : UserControl
{
    public UsersView()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<UsersViewModel>();
    }
}
