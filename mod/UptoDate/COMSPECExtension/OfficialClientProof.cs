using System.Security.Cryptography;
using System.Text;

namespace COMSPECExtension;

public static partial class Extension
{
    /// <summary>
    /// Empreinte de requête du module officiel. Les secrets ne sont pas dans ce fichier :
    /// ClientAttestMaterial.Local.cs est produit par tools/embed-client-attest.php et ignoré par Git.
    /// Sans ce fichier, la méthode ne pose rien (développement). Le texte signé doit rester
    /// identique à OfficialClientAttestation::canonical (PHP).
    /// Deux preuves sur la même requête (même heure, même nonce) :
    ///  - « v2:&lt;version&gt; » (Key2 / Proof2) : secret propre à cette version de la DLL, que le serveur recalcule
    ///    depuis un maître qui n'est jamais publié ; relever le plancher de version côté serveur le périme ;
    ///  - « ow1 » (Key / Proof) : ancien secret unique, tant qu'il est encore fourni à la compilation, pour un
    ///    serveur qui n'a pas encore le maître.
    /// </summary>
    private static void StampOfficialClient(HttpRequestMessage req)
    {
#if COMSPEC_CLIENT_ATTEST
        if (!IsSecretSafeUri(req.RequestUri)) return;
        foreach (var name in ClientAttestHeaders)
            req.Headers.Remove(name);
        var ts = DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString(System.Globalization.CultureInfo.InvariantCulture);
        var nonce = Convert.ToHexString(RandomNumberGenerator.GetBytes(16)).ToLowerInvariant();
        var method = req.Method.Method.ToUpperInvariant();
        var path = CanonicalClientPath(req.RequestUri!);
        var legacy = AddClientProof(req, ClientAttestMaterialLocal.LegacyPacked, "ow1",
            "X-Comspec-Client-Key", "X-Comspec-Client-Proof", method, path, ts, nonce);
        var versioned = AddClientProof(req, ClientAttestMaterialLocal.Packed, "v2:" + ClientAttestMaterialLocal.Version,
            "X-Comspec-Client-Key2", "X-Comspec-Client-Proof2", method, path, ts, nonce);
        if (!legacy && !versioned) return;
        req.Headers.TryAddWithoutValidation("X-Comspec-Client-Ts", ts);
        req.Headers.TryAddWithoutValidation("X-Comspec-Client-Nonce", nonce);
#endif
    }

#if COMSPEC_CLIENT_ATTEST
    // Le matériel doit être celui de CETTE version : sinon le serveur refuserait tous les joueurs. Erreur de
    // compilation ici (CS0031 ou CS0117) = relancer tools/embed-client-attest.php puis recompiler.
    private const uint ClientAttestVersionGuard = ClientAttestMaterialLocal.Version == ExtensionVersion ? 0 : -1;

    private static readonly string[] ClientAttestHeaders =
    {
        "X-Comspec-Client-Key", "X-Comspec-Client-Proof", "X-Comspec-Client-Key2", "X-Comspec-Client-Proof2",
        "X-Comspec-Client-Ts", "X-Comspec-Client-Nonce",
    };

    private static bool AddClientProof(HttpRequestMessage req, byte[] packed, string keyId, string keyHeader,
        string proofHeader, string method, string path, string ts, string nonce)
    {
        var secret = UnmaskClientAttest(packed);
        if (secret.Length == 0) return false;
        try
        {
            var canonical = "COMSPEC-CLIENT-V1\n" + keyId + "\n" + method + "\n" + path + "\n" + ts + "\n" + nonce + "\n";
            using var hmac = new HMACSHA256(secret);
            var mac = Convert.ToHexString(hmac.ComputeHash(Encoding.UTF8.GetBytes(canonical))).ToLowerInvariant();
            req.Headers.TryAddWithoutValidation(keyHeader, keyId);
            req.Headers.TryAddWithoutValidation(proofHeader, mac);
            return true;
        }
        finally
        {
            CryptographicOperations.ZeroMemory(secret);
        }
    }

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
