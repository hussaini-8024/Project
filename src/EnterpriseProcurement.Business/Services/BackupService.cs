using System.IO.Compression;
using System.Security.Cryptography;
using System.Text;
using EnterpriseProcurement.Core.Interfaces;
using Microsoft.Extensions.Configuration;

namespace EnterpriseProcurement.Business.Services;

public class BackupService : IBackupService
{
    private readonly string _backupDirectory;
    private readonly string _databasePath;
    private readonly byte[] _encryptionKey;

    public BackupService(IConfiguration configuration)
    {
        _backupDirectory = configuration["Backup:Path"]
            ?? Path.Combine(AppContext.BaseDirectory, "Backups");
        _databasePath = configuration["Database:SqlitePath"]
            ?? Path.Combine(AppContext.BaseDirectory, "epims.db");

        var keyText = configuration["Backup:EncryptionKey"] ?? "EPIMS-Default-Backup-Key-32b!";
        _encryptionKey = SHA256.HashData(Encoding.UTF8.GetBytes(keyText));
        Directory.CreateDirectory(_backupDirectory);
    }

    public async Task<string> BackupAsync(string? destinationPath = null, CancellationToken ct = default)
    {
        Directory.CreateDirectory(_backupDirectory);
        var fileName = $"epims-backup-{DateTime.UtcNow:yyyyMMdd-HHmmss}.bak";
        var target = destinationPath ?? Path.Combine(_backupDirectory, fileName);

        if (!File.Exists(_databasePath))
            throw new FileNotFoundException("Database file not found.", _databasePath);

        var tempZip = Path.Combine(Path.GetTempPath(), $"{Guid.NewGuid():N}.zip");
        try
        {
            await using (var zipStream = File.Create(tempZip))
            using (var archive = new ZipArchive(zipStream, ZipArchiveMode.Create))
            {
                archive.CreateEntryFromFile(_databasePath, "epims.db");
            }

            var plain = await File.ReadAllBytesAsync(tempZip, ct);
            var encrypted = Encrypt(plain);
            await File.WriteAllBytesAsync(target, encrypted, ct);
            return target;
        }
        finally
        {
            if (File.Exists(tempZip)) File.Delete(tempZip);
        }
    }

    public async Task RestoreAsync(string backupPath, CancellationToken ct = default)
    {
        if (!File.Exists(backupPath))
            throw new FileNotFoundException("Backup file not found.", backupPath);

        var encrypted = await File.ReadAllBytesAsync(backupPath, ct);
        var plain = Decrypt(encrypted);
        var tempZip = Path.Combine(Path.GetTempPath(), $"{Guid.NewGuid():N}.zip");
        var extractDir = Path.Combine(Path.GetTempPath(), Guid.NewGuid().ToString("N"));

        try
        {
            await File.WriteAllBytesAsync(tempZip, plain, ct);
            Directory.CreateDirectory(extractDir);
            ZipFile.ExtractToDirectory(tempZip, extractDir);
            var dbFile = Path.Combine(extractDir, "epims.db");
            if (!File.Exists(dbFile))
                throw new InvalidOperationException("Backup archive does not contain epims.db.");

            var restoreTarget = _databasePath + ".restore";
            File.Copy(dbFile, restoreTarget, true);
            File.Copy(restoreTarget, _databasePath, true);
            File.Delete(restoreTarget);
        }
        finally
        {
            if (File.Exists(tempZip)) File.Delete(tempZip);
            if (Directory.Exists(extractDir)) Directory.Delete(extractDir, true);
        }
    }

    public Task<IReadOnlyList<string>> ListBackupsAsync(CancellationToken ct = default)
    {
        if (!Directory.Exists(_backupDirectory))
            return Task.FromResult<IReadOnlyList<string>>(Array.Empty<string>());

        var files = Directory.GetFiles(_backupDirectory, "*.bak")
            .OrderByDescending(File.GetCreationTimeUtc)
            .ToList();
        return Task.FromResult<IReadOnlyList<string>>(files);
    }

    private byte[] Encrypt(byte[] data)
    {
        using var aes = Aes.Create();
        aes.Key = _encryptionKey;
        aes.GenerateIV();
        using var encryptor = aes.CreateEncryptor();
        var cipher = encryptor.TransformFinalBlock(data, 0, data.Length);
        var result = new byte[aes.IV.Length + cipher.Length];
        Buffer.BlockCopy(aes.IV, 0, result, 0, aes.IV.Length);
        Buffer.BlockCopy(cipher, 0, result, aes.IV.Length, cipher.Length);
        return result;
    }

    private byte[] Decrypt(byte[] data)
    {
        using var aes = Aes.Create();
        aes.Key = _encryptionKey;
        var iv = new byte[aes.BlockSize / 8];
        var cipher = new byte[data.Length - iv.Length];
        Buffer.BlockCopy(data, 0, iv, 0, iv.Length);
        Buffer.BlockCopy(data, iv.Length, cipher, 0, cipher.Length);
        aes.IV = iv;
        using var decryptor = aes.CreateDecryptor();
        return decryptor.TransformFinalBlock(cipher, 0, cipher.Length);
    }
}
