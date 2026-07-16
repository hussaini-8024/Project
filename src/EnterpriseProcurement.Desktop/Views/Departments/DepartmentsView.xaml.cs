using System.Windows.Controls;
using EnterpriseProcurement.Desktop.ViewModels.Departments;
using Microsoft.Extensions.DependencyInjection;

namespace EnterpriseProcurement.Desktop.Views.Departments;

public partial class DepartmentsView : UserControl
{
    public DepartmentsView()
    {
        InitializeComponent();
        DataContext = App.Services.GetRequiredService<DepartmentsViewModel>();
    }
}
