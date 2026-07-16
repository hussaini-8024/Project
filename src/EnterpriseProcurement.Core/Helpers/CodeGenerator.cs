namespace EnterpriseProcurement.Core.Helpers;

public static class CodeGenerator
{
    public static string Generate(string prefix, int sequence, int padLength = 5)
        => $"{prefix}-{DateTime.UtcNow:yyyyMM}-{sequence.ToString().PadLeft(padLength, '0')}";

    public static string GenerateBarcode(string itemCode)
        => $"BC{itemCode.Replace("-", "")}{DateTime.UtcNow:yyMMddHHmm}";

    public static string GenerateQrPayload(Guid itemId, string itemCode)
        => $"EPIMS|{itemId:N}|{itemCode}";

    public static string GenerateToken(int bytes = 32)
    {
        var buffer = new byte[bytes];
        using var rng = System.Security.Cryptography.RandomNumberGenerator.Create();
        rng.GetBytes(buffer);
        return Convert.ToBase64String(buffer)
            .Replace("+", "-")
            .Replace("/", "_")
            .TrimEnd('=');
    }
}
