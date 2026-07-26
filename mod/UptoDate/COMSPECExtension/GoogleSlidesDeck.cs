using System.Collections.Concurrent;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Text.RegularExpressions;

namespace COMSPECExtension;

/// <summary>
/// Charge une présentation Google Slides publique (lien partagé) vers des PNG locaux.
/// Fragile : s'appuie sur des URLs / HTML non documentés de Google (ToS / changements possibles).
/// </summary>
internal static class GoogleSlidesDeck
{
    private static readonly HttpClient Http = CreateClient();
    private static readonly ConcurrentDictionary<string, IReadOnlyList<string>> PageIdCache = new(StringComparer.Ordinal);
    private static readonly object Gate = new();
    private static CancellationTokenSource? _cts;
    private static string _activeRequestId = "";

    private static readonly Regex PresentationIdRegex = new(
        @"docs\.google\.com/presentation/d/(?:e/)?([a-zA-Z0-9_-]+)",
        RegexOptions.IgnoreCase | RegexOptions.Compiled);

    private static readonly Regex[] PageIdPatterns =
    [
        new(@"[?&]pageid=([a-zA-Z0-9_-]+)", RegexOptions.IgnoreCase | RegexOptions.Compiled),
        new(@"#slide=id\.([a-zA-Z0-9_-]+)", RegexOptions.IgnoreCase | RegexOptions.Compiled),
        new(@"""pageid""\s*:\s*""([a-zA-Z0-9_-]+)""", RegexOptions.IgnoreCase | RegexOptions.Compiled),
        new(@"""pageId""\s*:\s*""([a-zA-Z0-9_-]+)""", RegexOptions.IgnoreCase | RegexOptions.Compiled),
        new(@"data-page-id=[""']([a-zA-Z0-9_-]+)[""']", RegexOptions.IgnoreCase | RegexOptions.Compiled),
        // IDs typiques Google Slides (g…_…_…)
        new(@"""id""\s*:\s*""(g[a-f0-9]+_\d+_\d+)""", RegexOptions.IgnoreCase | RegexOptions.Compiled),
    ];

    private static HttpClient CreateClient()
    {
        var handler = new HttpClientHandler
        {
            AllowAutoRedirect = true,
            AutomaticDecompression = System.Net.DecompressionMethods.All,
        };
        var client = new HttpClient(handler)
        {
            Timeout = TimeSpan.FromSeconds(45),
        };
        client.DefaultRequestHeaders.TryAddWithoutValidation(
            "User-Agent",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36");
        client.DefaultRequestHeaders.TryAddWithoutValidation("Accept", "text/html,application/xhtml+xml,image/png,image/*,*/*;q=0.8");
        client.DefaultRequestHeaders.TryAddWithoutValidation("Accept-Language", "fr-FR,fr;q=0.9,en;q=0.8");
        return client;
    }

    /// <summary>
    /// Valide l'URL et démarre le chargement async. Retour sync pour callExtension :
    /// ["accepted"] ou ["rejected","code"].
    /// </summary>
    public static string StartLoad(
        string url,
        int index,
        string requestId,
        Action<string, string> invokeCallback)
    {
        url = (url ?? "").Trim();
        requestId = (requestId ?? "").Trim();
        if (requestId.Length == 0 || requestId.Length > 96)
            return "[\"rejected\",\"invalid_request\"]";
        if (!TryParsePresentationId(url, out var presentationId))
            return "[\"rejected\",\"invalid_url\"]";

        CancellationTokenSource cts;
        lock (Gate)
        {
            _cts?.Cancel();
            _cts?.Dispose();
            _cts = new CancellationTokenSource();
            cts = _cts;
            _activeRequestId = requestId;
        }

        var token = cts.Token;
        _ = Task.Run(async () =>
        {
            try
            {
                await LoadAsync(url, presentationId, index, requestId, token, invokeCallback).ConfigureAwait(false);
            }
            catch (OperationCanceledException)
            {
                if (IsActive(requestId))
                    invokeCallback("google_deck_error", FormatErrorPayload(requestId, "cancelled", "Chargement annulé."));
            }
            catch (HttpRequestException)
            {
                if (IsActive(requestId))
                    invokeCallback("google_deck_error", FormatErrorPayload(requestId, "network", "Impossible de joindre la présentation."));
            }
            catch (Exception)
            {
                if (IsActive(requestId))
                    invokeCallback("google_deck_error", FormatErrorPayload(requestId, "parse_failed", "Échec du chargement de la présentation."));
            }
        }, token);

        return "[\"accepted\"]";
    }

    public static string Cancel()
    {
        lock (Gate)
        {
            _cts?.Cancel();
            _activeRequestId = "";
        }
        return "[\"ok\"]";
    }

    private static bool IsActive(string requestId)
    {
        lock (Gate) return _activeRequestId == requestId;
    }

    internal static bool TryParsePresentationId(string url, out string presentationId)
    {
        presentationId = "";
        if (string.IsNullOrWhiteSpace(url)) return false;
        if (!url.Contains("docs.google.com/presentation/", StringComparison.OrdinalIgnoreCase))
            return false;
        var m = PresentationIdRegex.Match(url);
        if (!m.Success) return false;
        presentationId = m.Groups[1].Value;
        return presentationId.Length >= 10;
    }

    private static async Task LoadAsync(
        string sourceUrl,
        string presentationId,
        int index,
        string requestId,
        CancellationToken token,
        Action<string, string> invokeCallback)
    {
        var pageIds = await ResolvePageIdsAsync(sourceUrl, presentationId, token).ConfigureAwait(false);
        token.ThrowIfCancellationRequested();
        if (!IsActive(requestId)) return;

        if (pageIds.Count == 0)
        {
            // Dernier recours : export de la première page sans pageid.
            pageIds = [""];
        }

        var manifestComplete = pageIds.Count > 1 || (pageIds.Count == 1 && pageIds[0].Length > 0);
        if (index < 0) index = 0;
        if (index >= pageIds.Count) index = pageIds.Count - 1;

        var pageId = pageIds[index];
        var localPath = await DownloadSlidePngAsync(presentationId, pageId, index, token).ConfigureAwait(false);
        token.ThrowIfCancellationRequested();
        if (!IsActive(requestId)) return;

        if (string.IsNullOrEmpty(localPath))
        {
            invokeCallback("google_deck_error", FormatErrorPayload(
                requestId,
                "private",
                "Présentation inaccessible ou non publique."));
            return;
        }

        var armaPath = localPath.Replace('\\', '/');
        var payload =
            $"[\"{Escape(requestId)}\",\"{Escape(presentationId)}\",{index},{pageIds.Count},\"{Escape(armaPath)}\",\"{Escape(pageId)}\",{(manifestComplete ? "true" : "false")}]";
        invokeCallback("google_deck_ready", payload);

        // Précharge les autres pages en arrière-plan (best-effort).
        _ = Task.Run(async () =>
        {
            for (var i = 0; i < pageIds.Count; i++)
            {
                if (i == index) continue;
                if (!IsActive(requestId) || token.IsCancellationRequested) return;
                try
                {
                    await DownloadSlidePngAsync(presentationId, pageIds[i], i, token).ConfigureAwait(false);
                }
                catch
                {
                    // ignore preload errors
                }
            }
        }, token);
    }

    /// <summary>Télécharge une diapo déjà connue (navigation). Callback google_slide_ready.</summary>
    public static string StartSlide(
        string presentationId,
        int index,
        string requestId,
        Action<string, string> invokeCallback)
    {
        presentationId = (presentationId ?? "").Trim();
        requestId = (requestId ?? "").Trim();
        if (presentationId.Length < 10 || requestId.Length == 0)
            return "[\"rejected\",\"invalid_request\"]";

        if (!PageIdCache.TryGetValue(presentationId, out var pageIds) || pageIds.Count == 0)
            return "[\"rejected\",\"no_manifest\"]";

        if (index < 0) index = 0;
        if (index >= pageIds.Count) index = pageIds.Count - 1;

        CancellationTokenSource cts;
        lock (Gate)
        {
            _cts?.Cancel();
            _cts?.Dispose();
            _cts = new CancellationTokenSource();
            cts = _cts;
            _activeRequestId = requestId;
        }

        var token = cts.Token;
        var pageId = pageIds[index];
        _ = Task.Run(async () =>
        {
            try
            {
                var localPath = await DownloadSlidePngAsync(presentationId, pageId, index, token).ConfigureAwait(false);
                if (!IsActive(requestId)) return;
                if (string.IsNullOrEmpty(localPath))
                {
                    invokeCallback("google_deck_error", FormatErrorPayload(requestId, "not_found", "Diapositive introuvable."));
                    return;
                }
                var armaPath = localPath.Replace('\\', '/');
                var payload =
                    $"[\"{Escape(requestId)}\",\"{Escape(armaPath)}\",{index},{pageIds.Count},\"{Escape(pageId)}\"]";
                invokeCallback("google_slide_ready", payload);
            }
            catch (OperationCanceledException)
            {
                if (IsActive(requestId))
                    invokeCallback("google_deck_error", FormatErrorPayload(requestId, "cancelled", "Chargement annulé."));
            }
            catch
            {
                if (IsActive(requestId))
                    invokeCallback("google_deck_error", FormatErrorPayload(requestId, "network", "Impossible de charger cette diapositive."));
            }
        }, token);

        return "[\"accepted\"]";
    }

    private static async Task<IReadOnlyList<string>> ResolvePageIdsAsync(
        string sourceUrl,
        string presentationId,
        CancellationToken token)
    {
        if (PageIdCache.TryGetValue(presentationId, out var cached) && cached.Count > 0)
            return cached;

        var candidates = new List<string>
        {
            $"https://docs.google.com/presentation/d/{presentationId}/embed?start=false&loop=false&delayms=3000",
            $"https://docs.google.com/presentation/d/{presentationId}/pub?start=false&loop=false&delayms=3000",
            $"https://docs.google.com/presentation/d/{presentationId}/htmlpresent",
            sourceUrl,
        };

        // Liens /d/e/{publishId}/
        if (sourceUrl.Contains("/presentation/d/e/", StringComparison.OrdinalIgnoreCase))
        {
            candidates.Insert(0, sourceUrl);
            candidates.Insert(1, sourceUrl.Contains('?') ? sourceUrl : sourceUrl.TrimEnd('/') + "/pub?start=false");
        }

        var found = new List<string>();
        foreach (var url in candidates.Distinct(StringComparer.OrdinalIgnoreCase))
        {
            token.ThrowIfCancellationRequested();
            try
            {
                using var resp = await Http.GetAsync(url, token).ConfigureAwait(false);
                if (!resp.IsSuccessStatusCode) continue;
                var html = await resp.Content.ReadAsStringAsync(token).ConfigureAwait(false);
                if (string.IsNullOrEmpty(html)) continue;
                var ids = ExtractPageIds(html);
                if (ids.Count > found.Count)
                    found = ids;
                if (found.Count >= 2)
                    break;
            }
            catch (OperationCanceledException) { throw; }
            catch { /* try next */ }
        }

        if (found.Count == 0)
            found.Add(""); // first-slide export without pageid

        PageIdCache[presentationId] = found;
        return found;
    }

    private static List<string> ExtractPageIds(string html)
    {
        var ordered = new List<string>();
        var seen = new HashSet<string>(StringComparer.Ordinal);
        foreach (var pattern in PageIdPatterns)
        {
            foreach (Match m in pattern.Matches(html))
            {
                var id = m.Groups[1].Value;
                if (id.Length < 3) continue;
                // Filtre bruit JSON générique
                if (id is "null" or "undefined" or "true" or "false") continue;
                if (!seen.Add(id)) continue;
                ordered.Add(id);
            }
        }
        return ordered;
    }

    private static async Task<string?> DownloadSlidePngAsync(
        string presentationId,
        string pageId,
        int index,
        CancellationToken token)
    {
        var cacheDir = GetDeckCacheDir(presentationId);
        if (cacheDir == null) return null;

        var safePage = string.IsNullOrEmpty(pageId) ? "first" : new string(pageId.Where(c => char.IsLetterOrDigit(c) || c is '_' or '-').ToArray());
        if (safePage.Length == 0) safePage = "first";
        var destPath = Path.Combine(cacheDir, $"slide_{index}_{safePage}.png");
        if (File.Exists(destPath) && new FileInfo(destPath).Length > 64)
            return destPath;

        var urls = new List<string>();
        if (!string.IsNullOrEmpty(pageId))
        {
            urls.Add($"https://docs.google.com/presentation/d/{presentationId}/export/png?id={Uri.EscapeDataString(presentationId)}&pageid={Uri.EscapeDataString(pageId)}");
            urls.Add($"https://docs.google.com/presentation/d/{presentationId}/export/jpeg?id={Uri.EscapeDataString(presentationId)}&pageid={Uri.EscapeDataString(pageId)}");
            urls.Add($"https://docs.google.com/presentation/d/{presentationId}/export?format=png&pageid={Uri.EscapeDataString(pageId)}");
        }
        else
        {
            urls.Add($"https://docs.google.com/presentation/d/{presentationId}/export/png");
            urls.Add($"https://docs.google.com/presentation/d/{presentationId}/export?format=png");
        }

        foreach (var url in urls)
        {
            token.ThrowIfCancellationRequested();
            try
            {
                using var resp = await Http.GetAsync(url, token).ConfigureAwait(false);
                if (!resp.IsSuccessStatusCode) continue;
                var bytes = await resp.Content.ReadAsByteArrayAsync(token).ConfigureAwait(false);
                if (!IsImageBytes(bytes)) continue;
                await File.WriteAllBytesAsync(destPath, bytes, token).ConfigureAwait(false);
                return destPath;
            }
            catch (OperationCanceledException) { throw; }
            catch { /* try next url */ }
        }

        return null;
    }

    private static bool IsImageBytes(byte[] bytes)
    {
        if (bytes.Length < 8) return false;
        var isPng = bytes[0] == 0x89 && bytes[1] == (byte)'P' && bytes[2] == (byte)'N' && bytes[3] == (byte)'G';
        var isJpeg = bytes[0] == 0xFF && bytes[1] == 0xD8 && bytes[2] == 0xFF;
        return isPng || isJpeg;
    }

    private static string? GetDeckCacheDir(string presentationId)
    {
        var hash = Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(presentationId)))[..16].ToLowerInvariant();
        var candidates = new[]
        {
            Path.Combine(AppContext.BaseDirectory, "comspec_cache", "google-deck", hash),
            Path.Combine(Path.GetTempPath(), "comspec_cache", "google-deck", hash),
        };
        foreach (var dir in candidates)
        {
            try
            {
                Directory.CreateDirectory(dir);
                var probe = Path.Combine(dir, ".write_test");
                File.WriteAllText(probe, "ok");
                File.Delete(probe);
                return dir;
            }
            catch { }
        }
        return null;
    }

    private static string FormatErrorPayload(string requestId, string code, string message) =>
        $"[\"{Escape(requestId)}\",\"{Escape(code)}\",\"{Escape(message)}\"]";

    private static string Escape(string s) =>
        (s ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"").Replace("\n", " ").Replace("\r", "");
}
