using System;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

public static partial class Extension
{
    // Invoked through existing Async/extAwait worker. No second login, URL argument or user filesystem path.
    private static readonly SemaphoreSlim PatchDownloads = new(1, 1);
    private static string HandleAthenaPatches(string?[] args, CancellationToken token)
    {
        if (!HasPortalAuth() || !GameAccessTokenUsableNow()) return "ERR|linked_account_required";
        string A(int i) => args.Length > i ? (args[i] ?? "").Trim() : "";
        static bool Hex(string s, int n) => s.Length == n && s.All(c => c is >= '0' and <= '9' or >= 'a' and <= 'f');
        if (A(0) == "list")
        {
            var offset = int.TryParse(A(1), out var n) ? Math.Clamp(n, 0, 10000) : 0;
            using var response = SendGet(_baseUrl.TrimEnd('/') + "/api/atak/patches?offset=" + offset, token);
            if (!response.IsSuccessStatusCode) return "ERR|http_" + (int)response.StatusCode;
            using var doc = JsonDocument.Parse(ReadContentUtf8(response, token));
            var root = doc.RootElement;
            var lines = new StringBuilder("OK|");
            var next = root.GetProperty("nextOffset");
            lines.Append("M\t").Append(next.ValueKind == JsonValueKind.Number ? next.GetInt32() : -1).Append('\n');
            foreach (var p in root.GetProperty("patches").EnumerateArray())
            {
                string S(string key) => p.GetProperty(key).GetString() ?? "";
                if (!Hex(S("id"),32) || !Hex(S("hash"),64) || !Hex(S("pngHash"),64)) continue;
                var name = new string(S("name").Select(c => char.IsControl(c) || c == '|' ? ' ' : c).Take(80).ToArray());
                lines.Append("P\t").Append(S("id")).Append('\t').Append(S("variant")).Append('\t')
                    .Append(p.GetProperty("revision").GetInt32()).Append('\t').Append(S("hash")).Append('\t')
                    .Append(S("pngHash")).Append('\t').Append(S("class")).Append('\t')
                    .Append(p.GetProperty("width").GetInt32()).Append('\t').Append(p.GetProperty("height").GetInt32())
                    .Append('\t').Append(name).Append('\n');
            }
            return lines.Length <= 8000 ? lines.ToString() : "ERR|catalog_too_large";
        }
        if (A(0) != "download" || !Hex(A(1),32) || !int.TryParse(A(2),out var revision) || revision < 1
            || (A(3) != "standard" && A(3) != "night") || (A(4) != "paa" && A(4) != "png") || !Hex(A(5),64)) return "ERR|bad_args";
        if (!PatchDownloads.Wait(0, token)) return "ERR|busy";
        try
        {
            // Authorization rechecked even on a cache hit. Never trust a caller-supplied hash as access proof.
            using var response = SendGet(_baseUrl.TrimEnd('/') + "/api/atak/patches/" + A(1) + "/" + revision + "/" + A(3) + "/" + A(4), token);
            if (!response.IsSuccessStatusCode) return "ERR|http_" + (int)response.StatusCode;
            var dir = GetWritableCacheDir();
            if (dir == null) return "ERR|no_writable_cache_dir";
            var target = Path.Combine(dir, "patch_" + A(5) + "." + A(4));
            if (File.Exists(target) && new FileInfo(target).Length <= 8_388_608
                && Convert.ToHexString(SHA256.HashData(File.ReadAllBytes(target))).Equals(A(5), StringComparison.OrdinalIgnoreCase))
                return "OK|" + target.Replace('\\','/');
            if (response.Content.Headers.ContentLength is > 8_388_608) return "ERR|too_large";
            using var input = response.Content.ReadAsStream(token);
            using var output = new MemoryStream();
            var buffer = new byte[32768];
            int got;
            while ((got = input.Read(buffer,0,buffer.Length)) > 0)
            {
                token.ThrowIfCancellationRequested();
                if (output.Length + got > 8_388_608) return "ERR|too_large";
                output.Write(buffer,0,got);
            }
            var bytes = output.ToArray();
            if (!Convert.ToHexString(SHA256.HashData(bytes)).Equals(A(5),StringComparison.OrdinalIgnoreCase)) return "ERR|integrity";
            if (A(4) == "paa" && (bytes.Length < 16 || bytes[1] != 255 || (bytes[0] != 1 && bytes[0] != 5))) return "ERR|invalid_paa";
            if (A(4) == "png" && (bytes.Length < 8 || bytes[0] != 137 || bytes[1] != 80 || bytes[2] != 78 || bytes[3] != 71)) return "ERR|invalid_png";
            var temp = target + "." + Guid.NewGuid().ToString("N") + ".part";
            try { File.WriteAllBytes(temp,bytes); File.Move(temp,target,true); }
            finally { if (File.Exists(temp)) File.Delete(temp); }
            // Only this feature's own files, with a bounded cache; partial files never become final resources.
            var entries = new DirectoryInfo(dir).GetFiles("patch_*").OrderByDescending(f => f.LastWriteTimeUtc).ToArray();
            long retained = 0;
            foreach (var file in entries) { retained += file.Length; if (retained > 134_217_728 && file.FullName != target) { try { file.Delete(); } catch { } } }
            return "OK|" + target.Replace('\\','/');
        }
        catch (OperationCanceledException) { return "ERR|timeout"; }
        catch (HttpRequestException) { return "ERR|network"; }
        catch (IOException) { return "ERR|cache_io"; }
        finally { PatchDownloads.Release(); }
    }
}
