using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;

namespace COMSPECExtension;

/// <summary>
/// COMSPEC IDENTITY (2.0.127) : photo du papier présenté lors d'un contrôle d'identité, jointe au contrôle sur Athena.
/// Même canal que les autres photos du mod : le jeu écrit une capture (commande screenshot) dans le dossier Screenshots
/// du profil, la DLL la lit, la recadre sur le papier, la compresse en JPEG et l'envoie en multipart.
/// <list type="bullet">
/// <item>UploadIdCheckPhoto [clé du contrôle, nom du fichier, x, y, largeur, hauteur] → « OK|queued » ; le cadre du papier
/// est donné en dix-millièmes de l'écran (0 à 10000), 0 0 0 0 = image entière. Envoi en tâche de fond vers
/// POST /api/sse/identity/checks/image (champ « image », clé « idempotency_key »).</item>
/// <item>IdCheckPhotoStatus [clé du contrôle] → « OK|none », « OK|pending », « OK|sent » ou « OK|failed|motif ».</item>
/// </list>
/// Garde-fous : nom de fichier strict (COMSPEC_SSE_Paper_….png, jamais de dossier), fichier cherché seulement dans les
/// dossiers de captures (ScreenshotDirs, sans sous-dossier ni lien), signature PNG vérifiée, source bornée, JPEG de
/// 1,5 Mo au plus (côté ramené à 1600 px, qualité abaissée au besoin), débit borné, rien n'est écrit au journal.
/// </summary>
public static partial class Extension
{
    internal const int IdPaperMaxJpegBytes = 1_500_000;
    internal const long IdPaperMaxSourceBytes = 96L * 1024 * 1024;
    internal const int IdPaperMaxSide = 1600;
    private const int IdPaperMinSide = 64;
    private const int IdPaperMaxPending = 4;
    private const int IdPaperMinIntervalMs = 2000;
    private const int IdPaperWindowMax = 12;
    private const long IdPaperWindowMs = 10 * 60 * 1000;
    private const string IdPaperApiPath = "/api/sse/identity/checks/image";

    private static readonly Regex IdPaperFileName = new(
        @"^COMSPEC_SSE_Paper_[A-Za-z0-9_\-]{1,64}\.png$", RegexOptions.IgnoreCase | RegexOptions.CultureInvariant);
    private static readonly Regex IdPaperKey = new(@"^[A-Za-z0-9_.:\-]{4,80}$", RegexOptions.CultureInvariant);

    private static readonly ConcurrentDictionary<string, string> IdPaperState = new(StringComparer.Ordinal);
    private static readonly object IdPaperRateLock = new();
    private static readonly Queue<long> IdPaperRecent = new();
    private static long _idPaperLastMs = long.MinValue / 2;
    private static int _idPaperPending;

    /// <summary>Capture du papier d'un contrôle : jamais envoyée par le canal reconnaissance (photothèque).</summary>
    private static bool IsIdPaperCaptureName(string? pathOrName)
    {
        try
        {
            var n = Path.GetFileName(pathOrName ?? "") ?? "";
            return n.StartsWith("COMSPEC_SSE_Paper", StringComparison.OrdinalIgnoreCase);
        }
        catch
        {
            return false;
        }
    }

    private static string IdCheckPhotoStatus(string?[] args)
    {
        var key = args.Length > 0 ? (args[0] ?? "").Trim() : "";
        if (!IdPaperKey.IsMatch(key)) return "ERR|invalid_key";
        return IdPaperState.TryGetValue(key, out var state) ? "OK|" + state : "OK|none";
    }

    private static string BeginUploadIdCheckPhoto(string?[] args)
    {
        if (string.IsNullOrEmpty(_baseUrl) || !HasPortalAuth()) return "ERR|not_connected";
        var key = args.Length > 0 ? (args[0] ?? "").Trim() : "";
        if (!IdPaperKey.IsMatch(key)) return "ERR|invalid_key";
        var leaf = args.Length > 1 ? (args[1] ?? "").Trim().Trim('"') : "";
        // Nom seul, forme stricte : aucun séparateur, aucun « .. », aucune lettre de lecteur.
        if (!IdPaperFileName.IsMatch(leaf)) return "ERR|invalid_file";
        var crop = new int[4];
        for (var i = 0; i < 4; i++)
        {
            var raw = args.Length > 2 + i ? (args[2 + i] ?? "").Trim() : "0";
            if (!int.TryParse(raw, NumberStyles.Integer, CultureInfo.InvariantCulture, out crop[i]) || crop[i] < 0 || crop[i] > 10000)
                return "ERR|invalid_crop";
        }
        if (IdPaperState.TryGetValue(key, out var current) && current == "pending") return "OK|queued";

        var now = Environment.TickCount64;
        lock (IdPaperRateLock)
        {
            if (Volatile.Read(ref _idPaperPending) >= IdPaperMaxPending) return "ERR|queue_full";
            if (now - _idPaperLastMs < IdPaperMinIntervalMs) return "ERR|rate_limited";
            while (IdPaperRecent.Count > 0 && now - IdPaperRecent.Peek() > IdPaperWindowMs) IdPaperRecent.Dequeue();
            if (IdPaperRecent.Count >= IdPaperWindowMax) return "ERR|rate_limited";
            IdPaperRecent.Enqueue(now);
            _idPaperLastMs = now;
            Interlocked.Increment(ref _idPaperPending);
        }
        if (IdPaperState.Count > 200) IdPaperState.Clear();
        IdPaperState[key] = "pending";
        _ = Task.Run(async () =>
        {
            try
            {
                var outcome = await IdPaperRunAsync(key, leaf, crop).ConfigureAwait(false);
                IdPaperState[key] = outcome;
            }
            catch
            {
                IdPaperState[key] = "failed|error";
            }
            finally
            {
                Interlocked.Decrement(ref _idPaperPending);
            }
        });
        return "OK|queued";
    }

    /// <summary>
    /// Fichier de capture d'un nom strict, directement dans l'un des dossiers de captures : null si le nom n'a pas la
    /// forme attendue, si le chemin résolu sort du dossier, ou si l'entrée est un lien (point d'analyse).
    /// </summary>
    internal static string? IdPaperResolve(string leaf, IEnumerable<string> dirs)
    {
        if (!IdPaperFileName.IsMatch(leaf)) return null;
        foreach (var dir in dirs)
        {
            try
            {
                if (string.IsNullOrWhiteSpace(dir)) continue;
                var root = Path.GetFullPath(dir).TrimEnd('\\', '/');
                var full = Path.GetFullPath(Path.Combine(root, leaf));
                if (!string.Equals(Path.GetDirectoryName(full), root, StringComparison.OrdinalIgnoreCase)) continue;
                if (!File.Exists(full)) continue;
                if ((File.GetAttributes(full) & FileAttributes.ReparsePoint) != 0) continue;
                return full;
            }
            catch
            {
                // dossier illisible : suivant
            }
        }
        return null;
    }

    private static bool IdPaperIsPng(string path)
    {
        try
        {
            using var fs = new FileStream(path, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete);
            Span<byte> head = stackalloc byte[8];
            if (fs.Read(head) != 8) return false;
            return head[0] == 0x89 && head[1] == 0x50 && head[2] == 0x4E && head[3] == 0x47
                && head[4] == 0x0D && head[5] == 0x0A && head[6] == 0x1A && head[7] == 0x0A;
        }
        catch
        {
            return false;
        }
    }

    private static async Task<string> IdPaperRunAsync(string key, string leaf, int[] crop)
    {
        // Le moteur écrit la capture à l'image suivante : courte attente du fichier (10 s au plus).
        string? path = null;
        for (var i = 0; i < 40 && path == null; i++)
        {
            path = IdPaperResolve(leaf, ScreenshotDirs());
            if (path == null) await Task.Delay(250).ConfigureAwait(false);
        }
        if (path == null) return "failed|file_not_found";
        if (!WaitUntilImageFileStable(path, TimeSpan.FromSeconds(8), 64)) return "failed|file_not_ready";
        long size;
        try { size = new FileInfo(path).Length; } catch { return "failed|file_not_found"; }
        if (size > IdPaperMaxSourceBytes) return "failed|too_large";
        if (!IdPaperIsPng(path)) return "failed|invalid_type";

        byte[]? jpeg;
        try
        {
            var rgb = MapTileImage.ReadPng(path, out var w, out var h);
            if (rgb == null) return "failed|invalid_type";
            jpeg = IdPaperEncode(rgb, w, h, crop);
        }
        catch
        {
            return "failed|invalid_type";
        }
        if (jpeg == null) return "failed|too_large";

        if (!TryBuildRequestUri(_baseUrl, IdPaperApiPath, out var uri, out var err) || uri is null)
            return "failed|" + SqfField(err);
        using var multipart = new MultipartFormDataContent();
        multipart.Add(new StringContent(key), "idempotency_key");
        if (_steamUid.Length > 0) multipart.Add(new StringContent(_steamUid), "steam_uid");
        if (_sessionToken.Length > 0) multipart.Add(new StringContent(_sessionToken), "session_token");
        var file = new ByteArrayContent(jpeg);
        file.Headers.ContentType = new MediaTypeHeaderValue("image/jpeg");
        multipart.Add(file, "image", "papier.jpg");
        using var req = CreateUploadPost(uri, multipart);
        AttachApiKeyHeader(req);
        int code;
        try
        {
            using var resp = await UploadHttpClient.SendAsync(req).ConfigureAwait(false);
            code = (int)resp.StatusCode;
            if (resp.IsSuccessStatusCode)
            {
                NoteEndpointOk(uri.AbsoluteUri);
                // Capture du mod (nom COMSPEC_SSE_Paper_…) : retirée de Screenshots une fois sur Athena.
                try { File.Delete(path); } catch { /* encore ouverte : le ménage du dossier s'en charge */ }
                return "sent";
            }
        }
        catch
        {
            NoteNetworkHiccup();
            return "failed|network";
        }
        return code switch
        {
            401 or 403 => "failed|unauthorized",
            404 => "failed|not_found",
            413 => "failed|too_large",
            415 or 422 => "failed|refused",
            429 => "failed|rate_limited",
            503 => "failed|unavailable",
            _ => "failed|http_" + code.ToString(CultureInfo.InvariantCulture),
        };
    }

    /// <summary>
    /// Recadre (cadre en dix-millièmes), ramène le plus grand côté à 1600 px et compresse en JPEG de 1,5 Mo au plus
    /// (qualité abaissée, puis image réduite de moitié). Null si l'image ne tient pas dans la limite.
    /// </summary>
    internal static byte[]? IdPaperEncode(byte[] rgb, int w, int h, int[] crop)
    {
        if (w <= 0 || h <= 0 || rgb.Length < (long)w * h * 3) return null;
        int cx = 0, cy = 0, cw = w, ch = h;
        if (crop.Length >= 4 && crop[2] > 0 && crop[3] > 0)
        {
            cx = Math.Clamp((int)((long)crop[0] * w / 10000), 0, w - 1);
            cy = Math.Clamp((int)((long)crop[1] * h / 10000), 0, h - 1);
            cw = Math.Clamp((int)((long)crop[2] * w / 10000), 1, w - cx);
            ch = Math.Clamp((int)((long)crop[3] * h / 10000), 1, h - cy);
            // Cadre trop petit pour être un papier lisible : image entière.
            if (cw < IdPaperMinSide || ch < IdPaperMinSide) { cx = 0; cy = 0; cw = w; ch = h; }
        }
        var img = new byte[cw * ch * 3];
        for (var y = 0; y < ch; y++)
            Buffer.BlockCopy(rgb, ((cy + y) * w + cx) * 3, img, y * cw * 3, cw * 3);

        var side = Math.Max(cw, ch);
        if (side > IdPaperMaxSide)
        {
            var nw = Math.Max(1, cw * IdPaperMaxSide / side);
            var nh = Math.Max(1, ch * IdPaperMaxSide / side);
            img = IdPaperScale(img, cw, ch, nw, nh);
            cw = nw; ch = nh;
        }
        for (var pass = 0; pass < 4; pass++)
        {
            foreach (var quality in new[] { 85, 72, 60, 45 })
            {
                var bytes = IdPaperJpeg(img, cw, ch, quality);
                if (bytes != null && bytes.Length <= IdPaperMaxJpegBytes) return bytes;
            }
            if (cw < IdPaperMinSide * 2 || ch < IdPaperMinSide * 2) break;
            img = IdPaperScale(img, cw, ch, cw / 2, ch / 2);
            cw /= 2; ch /= 2;
        }
        return null;
    }

    private static byte[]? IdPaperJpeg(byte[] rgb, int w, int h, int quality)
    {
        var tmp = Path.Combine(Path.GetTempPath(), "comspec-idp-" + Guid.NewGuid().ToString("N") + ".jpg");
        try
        {
            MapTileImage.WriteJpeg(tmp, rgb, w, h, quality);
            return File.ReadAllBytes(tmp);
        }
        catch
        {
            return null;
        }
        finally
        {
            try { if (File.Exists(tmp)) File.Delete(tmp); } catch { /* ignore */ }
        }
    }

    /// <summary>Réduction par moyenne des points couverts (RGB, 3 octets par point).</summary>
    internal static byte[] IdPaperScale(byte[] src, int w, int h, int nw, int nh)
    {
        var dst = new byte[nw * nh * 3];
        for (var y = 0; y < nh; y++)
        {
            var y0 = (int)((long)y * h / nh);
            var y1 = Math.Max(y0 + 1, (int)((long)(y + 1) * h / nh));
            for (var x = 0; x < nw; x++)
            {
                var x0 = (int)((long)x * w / nw);
                var x1 = Math.Max(x0 + 1, (int)((long)(x + 1) * w / nw));
                long r = 0, g = 0, b = 0;
                var n = 0;
                for (var yy = y0; yy < y1 && yy < h; yy++)
                    for (var xx = x0; xx < x1 && xx < w; xx++)
                    {
                        var o = (yy * w + xx) * 3;
                        r += src[o]; g += src[o + 1]; b += src[o + 2];
                        n++;
                    }
                if (n == 0) n = 1;
                var d = (y * nw + x) * 3;
                dst[d] = (byte)(r / n); dst[d + 1] = (byte)(g / n); dst[d + 2] = (byte)(b / n);
            }
        }
        return dst;
    }
}
