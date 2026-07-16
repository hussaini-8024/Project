namespace EnterpriseProcurement.Core.Entities;

public class CompanyProfile : BaseEntity
{
    public string OrganizationName { get; set; } = string.Empty;
    public string? LegalName { get; set; }
    public string? Address { get; set; }
    public string? City { get; set; }
    public string? Country { get; set; }
    public string? Phone { get; set; }
    public string? Email { get; set; }
    public string? Website { get; set; }
    public string? NTN { get; set; }
    public string? STRN { get; set; }
    public string? LogoPath { get; set; }
    public string? PrimaryColor { get; set; } = "#0B3D5C";
    public string? SecondaryColor { get; set; } = "#1A6B8A";
    public string? AccentColor { get; set; } = "#C45C26";
    public int FiscalYearStartMonth { get; set; } = 7;
    public string? DefaultLanguage { get; set; } = "en";
}
