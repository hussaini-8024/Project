using EnterpriseProcurement.Core.Entities;
using EnterpriseProcurement.Core.Interfaces;
using EnterpriseProcurement.Data.Context;
using Microsoft.EntityFrameworkCore;

namespace EnterpriseProcurement.Business.Services;

public class SettingsService : ISettingsService
{
    private readonly AppDbContext _db;

    public SettingsService(AppDbContext db) => _db = db;

    public async Task<string?> GetAsync(string key, CancellationToken ct = default)
        => await _db.AppSettings.AsNoTracking()
            .Where(s => s.Key == key)
            .Select(s => s.Value)
            .FirstOrDefaultAsync(ct);

    public async Task SetAsync(string key, string value, string? category = null, CancellationToken ct = default)
    {
        var setting = await _db.AppSettings.FirstOrDefaultAsync(s => s.Key == key, ct);
        if (setting is null)
        {
            _db.AppSettings.Add(new AppSetting { Key = key, Value = value, Category = category });
        }
        else
        {
            setting.Value = value;
            if (category is not null) setting.Category = category;
        }

        await _db.SaveChangesAsync(ct);
    }

    public async Task<CompanyProfileDto?> GetCompanyProfileAsync(CancellationToken ct = default)
    {
        var p = await _db.CompanyProfiles.AsNoTracking().FirstOrDefaultAsync(ct);
        return p is null ? null : new CompanyProfileDto(
            p.Id, p.OrganizationName, p.LegalName, p.Address, p.City, p.Country, p.Phone, p.Email,
            p.Website, p.NTN, p.STRN, p.LogoPath, p.PrimaryColor, p.SecondaryColor, p.AccentColor,
            p.FiscalYearStartMonth, p.DefaultLanguage);
    }

    public async Task SaveCompanyProfileAsync(CompanyProfileDto profile, CancellationToken ct = default)
    {
        var p = await _db.CompanyProfiles.FirstOrDefaultAsync(x => x.Id == profile.Id, ct)
            ?? await _db.CompanyProfiles.FirstOrDefaultAsync(ct);

        if (p is null)
        {
            p = new CompanyProfile();
            _db.CompanyProfiles.Add(p);
        }

        p.OrganizationName = profile.OrganizationName;
        p.LegalName = profile.LegalName;
        p.Address = profile.Address;
        p.City = profile.City;
        p.Country = profile.Country;
        p.Phone = profile.Phone;
        p.Email = profile.Email;
        p.Website = profile.Website;
        p.NTN = profile.NTN;
        p.STRN = profile.STRN;
        p.LogoPath = profile.LogoPath;
        p.PrimaryColor = profile.PrimaryColor;
        p.SecondaryColor = profile.SecondaryColor;
        p.AccentColor = profile.AccentColor;
        p.FiscalYearStartMonth = profile.FiscalYearStartMonth;
        p.DefaultLanguage = profile.DefaultLanguage;

        await _db.SaveChangesAsync(ct);
    }
}
