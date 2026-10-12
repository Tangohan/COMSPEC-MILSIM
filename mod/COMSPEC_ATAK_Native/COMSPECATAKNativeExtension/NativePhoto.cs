using System.Collections.Concurrent;
using System.Globalization;
using System.Net.Http.Headers;

namespace COMSPECExtension;

/// <summary>
/// Clichés du téléphone ATAK natif (drone) envoyés à Athena avec leur fiche : titre, description, SSE (+ dossier),
/// confidentiel, source et nom du drone, en champs réels de POST /api/recon/images (la DLL d'Overwatch,
/// NotifyNewPhoto, ne porte que la légende). Branché dans le bloc COMSPEC_ATAK_NATIVE de TryGetSyncResponse.
///
/// Commande SQF « NativePhoto » (retour "OK|..." ou "ERR|..." ; une DLL plus ancienne répond vide) :
///   ["probe", url, clé, tenant, steam, carte]  → OK|ready si un envoi est possible, sinon ERR|not_connected ;
///   ["send", url, clé, tenant, steam, carte,
///    fichier, auteur, x, y, z, grille, cap, altitude, légende, unité, camp, mission, appareil, heure Unix, flux,
///    titre, description, SSE (0/1), dossier SSE, confidentiel (0/1), source, drone]   → OK|queued ;
///   ["status", fichier]                          → OK|queued | OK|uploaded | ERR|motif (dernier état connu).
/// url / clé / tenant / steam / carte servent seulement si la DLL native n'a pas sa propre session Athena
/// (avec COMSPEC Link, c'est la DLL d'Overwatch qui la porte) ; vides, ils sont ignorés.
/// </summary>
public static partial class Extension
{
    private static readonly ConcurrentDictionary<string, string> NativePhotoStatus = new(StringComparer.OrdinalIgnoreCase);

    private static string NativePhotoCommand(string?[] args)
    {
        var mode = args.Length > 0 ? (args[0] ?? "").Trim().ToLowerInvariant() : "";
        if (mode == "status")
        {
            var leaf = NativePhotoLeaf(args.Length > 1 ? args[1] : "");
            return leaf.Length > 0 && NativePhotoStatus.TryGetValue(leaf, out var st) ? st : "ERR|unknown_photo";
        }
        if (mode != "probe" && mode != "send")
            return "ERR|bad_mode";

        NativePhotoApplyAuth(args);
        if (string.IsNullOrEmpty(_baseUrl) || !HasPortalAuth())
            return "ERR|not_connected";
        if (mode == "probe")
            return "OK|ready";

        var file = args.Length > 6 ? (args[6] ?? "").Trim().Trim('"').Trim('\'') : "";
        var key = NativePhotoLeaf(file);
        if (key.Length == 0)
            return "ERR|empty_path";
        if (NativePhotoStatus.TryGetValue(key, out var prev) && prev == "OK|queued")
            return "OK|duplicate";
        NativePhotoStatus[key] = "OK|queued";
        var copy = (string?[])args.Clone();
        _ = Task.Run(async () =>
        {
            string result;
            try { result = await NativePhotoUploadAsync(file, copy).ConfigureAwait(false); }
            catch { result = "ERR|network"; }
            NativePhotoStatus[key] = result;
            InvokeCallback("NativePhoto", result + "|" + key);
        });
        return "OK|queued";
    }

    private static string NativePhotoLeaf(string? path)
    {
        try { return Path.GetFileName((path ?? "").Trim().Replace('/', '\\')) ?? ""; }
        catch { return ""; }
    }

    private static void NativePhotoApplyAuth(string?[] args)
    {
        if (string.IsNullOrEmpty(_baseUrl) && args.Length > 1)
        {
            var url = NormalizeBaseUrl(args[1]);
            if (url.Length > 0) _baseUrl = url;
        }
        if (!HasPortalAuth() && args.Length > 2 && !string.IsNullOrWhiteSpace(args[2]))
        {
            ApplyApiKeyHeaders(args[2]);
            if (args.Length > 3) ApplyTenantId(args[3]);
        }
        if (_steamUid.Length == 0 && args.Length > 4 && !string.IsNullOrWhiteSpace(args[4]))
            ApplySteamUid(args[4]);
        if (args.Length > 5 && !string.IsNullOrWhiteSpace(args[5]))
            ApplyMapId(args[5]);
    }

    private static async Task<string> NativePhotoUploadAsync(string file, string?[] a)
    {
        var leaf = NativePhotoLeaf(file);
        string? resolved = null;
        // Le PNG d'Arma est écrit en fin d'image : attendre qu'il apparaisse (20 s au plus).
        for (var attempt = 0; attempt < 50 && resolved == null; attempt++)
        {
            resolved = ResolveLocalImagePath(file, null);
            if (resolved != null && !SamePhotoStem(file, resolved)) resolved = null;
            if (resolved == null && leaf.Length > 0) resolved = FindScreenshotByFileName(leaf, allowUnstable: true);
            if (resolved == null) await Task.Delay(400).ConfigureAwait(false);
        }
        if (resolved == null)
            return "ERR|file_not_found|" + DescribeImageLookupFailure(file);
        if (!await WaitFileStableAsync(resolved, TimeSpan.FromSeconds(8)).ConfigureAwait(false)
            || !WaitUntilImageFileStable(resolved, TimeSpan.FromSeconds(4), 32))
            return "ERR|file_empty";
        if (!TryBuildRequestUri(_baseUrl, "/api/recon/images", out var uri, out var err) || uri is null)
            return "ERR|" + err;

        while (IsRateLimitedNow())
            await Task.Delay(500).ConfigureAwait(false);

        for (var tryNo = 0; tryNo < 3; tryNo++)
        {
            var multipart = new MultipartFormDataContent();
            HttpRequestMessage? req = null;
            try
            {
                var author = a.Length > 7 && !string.IsNullOrWhiteSpace(a[7]) ? a[7]!.Trim() : (_callSign.Length > 0 ? _callSign : "Unknown");
                multipart.Add(new StringContent(CurrentMapId().ToString(CultureInfo.InvariantCulture)), "mapId");
                multipart.Add(new StringContent(author), "author");
                AddOptionalForm(multipart, "pos_x", a, 8);
                AddOptionalForm(multipart, "pos_y", a, 9);
                AddOptionalForm(multipart, "pos_z", a, 10);
                AddOptionalForm(multipart, "grid_ref", a, 11);
                AddOptionalForm(multipart, "heading", a, 12);
                AddOptionalForm(multipart, "altitude", a, 13);
                AddOptionalForm(multipart, "caption", a, 14);
                AddOptionalForm(multipart, "unit_name", a, 15);
                multipart.Add(new StringContent(a.Length > 16 && !string.IsNullOrEmpty(a[16]) ? a[16]! : "WEST"), "side");
                AddOptionalForm(multipart, "mission_id", a, 17);
                multipart.Add(new StringContent(a.Length > 18 && !string.IsNullOrEmpty(a[18]) ? a[18]! : "UAV"), "device_type");
                var captured = a.Length > 19 ? (a[19] ?? "").Trim() : "";
                if (captured.Length == 0 || captured == "0")
                    captured = DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString(CultureInfo.InvariantCulture);
                multipart.Add(new StringContent(captured), "capturedAt");
                AddOptionalForm(multipart, "feed_id", a, 20);
                AddOptionalForm(multipart, "title", a, 21);
                AddOptionalForm(multipart, "description", a, 22);
                AddOptionalForm(multipart, "is_sse", a, 23);
                AddOptionalForm(multipart, "sse_case_ref", a, 24);
                AddOptionalForm(multipart, "is_confidential", a, 25);
                AddOptionalForm(multipart, "photo_source", a, 26);
                AddOptionalForm(multipart, "drone_name", a, 27);
                if (_steamUid.Length > 0) multipart.Add(new StringContent(_steamUid), "steam_uid");
                if (_sessionToken.Length > 0) multipart.Add(new StringContent(_sessionToken), "session_token");
                var fileName = Path.GetFileName(resolved) ?? "recon.png";
                var stream = new FileStream(resolved, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete, 128 * 1024, FileOptions.Asynchronous | FileOptions.SequentialScan);
                var content = new StreamContent(stream);
                content.Headers.ContentType = new MediaTypeHeaderValue(GuessImageMediaType(resolved));
                multipart.Add(content, "image", fileName);

                var body = await ToKnownLengthMultipartAsync(multipart).ConfigureAwait(false);
                req = CreateUploadPost(uri, body);
                AttachApiKeyHeader(req);
                using var resp = await UploadHttpClient.SendAsync(req).ConfigureAwait(false);
                var code = (int)resp.StatusCode;
                if (resp.IsSuccessStatusCode)
                {
                    NoteRateLimitCleared();
                    TryDeleteUploadedPhoto(resolved);
                    return "OK|uploaded|" + fileName;
                }
                var apiErr = JsonErrorToken(await ReadUtf8SnippetAsync(resp).ConfigureAwait(false));
                NotePostError(code, uri.AbsoluteUri);
                if (code == 503 || code == 429)
                {
                    NoteRateLimited(resp);
                    await Task.Delay(3000 * (tryNo + 1)).ConfigureAwait(false);
                    continue;
                }
                return $"ERR|http_{code}" + (apiErr.Length > 0 ? "|" + apiErr : "");
            }
            catch
            {
                NoteNetworkHiccup();
                await Task.Delay(2000 * (tryNo + 1)).ConfigureAwait(false);
            }
            finally
            {
                try { req?.Dispose(); } catch { /* ignore */ }
                try { multipart.Dispose(); } catch { /* ignore */ }
            }
        }
        return "ERR|network";
    }
}
