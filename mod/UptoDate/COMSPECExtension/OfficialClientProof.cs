using System.Security.Cryptography;
using System.Text;

namespace COMSPECExtension;

public static partial class Extension
{
    /// <summary>
    /// Empreinte de requête du module officiel. Le secret n'est pas dans ce fichier :
    /// ClientAttestMaterial.Local.cs est produit par tools/embed-client-attest.php et ignoré par Git.
    /// Sans ce fichier, la méthode ne pose rien (développement). Le texte signé doit rester
    /// identique à OfficialClientAttestation::canonical (PHP).
    /// </summary>
    private static void StampOfficialClient(HttpRequestMessage req)
    {
#if COMSPEC_CLIENT_ATTEST
        if (!IsSecretSafeUri(req.RequestUri)) return;
        var secret = UnmaskClientAttest(ClientAttestMaterialLocal.Packed);
        if (secret.Length == 0) return;
        try
        {
            var ts = DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString(System.Globalization.CultureInfo.InvariantCulture);
            var nonceBytes = RandomNumberGenerator.GetBytes(16);
            var nonce = Convert.ToHexString(nonceBytes).ToLowerInvariant();
            var canonical = "COMSPEC-CLIENT-V1\n"
                + "ow1\n"
                + req.Method.Method.ToUpperInvariant() + "\n"
                + CanonicalClientPath(req.RequestUri!) + "\n"
                + ts + "\n"
                + nonce + "\n";
            using var hmac = new HMACSHA256(secret);
            var mac = Convert.ToHexString(hmac.ComputeHash(Encoding.UTF8.GetBytes(canonical))).ToLowerInvariant();
            req.Headers.Remove("X-Comspec-Client-Key");
            req.Headers.Remove("X-Comspec-Client-Ts");
            req.Headers.Remove("X-Comspec-Client-Nonce");
            req.Headers.Remove("X-Comspec-Client-Proof");
            req.Headers.TryAddWithoutValidation("X-Comspec-Client-Key", "ow1");
            req.Headers.TryAddWithoutValidation("X-Comspec-Client-Ts", ts);
            req.Headers.TryAddWithoutValidation("X-Comspec-Client-Nonce", nonce);
            req.Headers.TryAddWithoutValidation("X-Comspec-Client-Proof", mac);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(secret);
        }
#endif
    }

#if COMSPEC_CLIENT_ATTEST
    /// <summary>Même masque que OfficialClientAttestation::clientMask.</summary>
    private static readonly byte[] ClientAttestMask =
    {
        0xC3, 0x17, 0x9A, 0x4E, 0x62, 0xD1, 0x08, 0xB7,
        0x55, 0xE4, 0x2C, 0x91, 0x7F, 0x0A, 0xD8, 0x36,
        0xAB, 0x49, 0x13, 0xF0, 0x6C, 0x88, 0x21, 0xDE,
        0x04, 0x5B, 0xC9, 0x77, 0xA2, 0x1E, 0x90, 0x3D,
    };

    private static byte[] UnmaskClientAttest(byte[] packed)
    {
        if (packed.Length == 0) return [];
        var raw = new byte[packed.Length];
        for (var i = 0; i < packed.Length; i++)
            raw[i] = (byte)(packed[i] ^ ClientAttestMask[i % ClientAttestMask.Length]);
        return raw;
    }

    /// <summary>Chemin tel que le portail le route (/public retiré, slash final retiré) + query brute.</summary>
    private static string CanonicalClientPath(Uri uri)
    {
        var path = uri.AbsolutePath;
        if (path.StartsWith("/public/", StringComparison.Ordinal))
            path = path[7..];
        else if (string.Equals(path, "/public", StringComparison.Ordinal))
            path = "/";
        if (path.Length > 1 && path.EndsWith('/'))
            path = path.TrimEnd('/');
        if (path.Length == 0) path = "/";
        return path + uri.Query;
    }
#endif
}
