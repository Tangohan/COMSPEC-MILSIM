using System.Globalization;
using System.Net.Http.Headers;
using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;

namespace COMSPECExtension;

public static partial class Extension
{
    private static string _gameAccessToken = "";
    private static string _gameAuthState = "INITIALIZING";
    private static string _gameAuthError = "";
    private static string _gameAuthStep = "INITIALIZING";
    private static int _gameAuthProgress;
    private static string _gameProfileName = "";
    private static string _gameProfileCallsign = "";
    private static string _gameProfileGrade = "";
    private static string _gameProfileUnit = "";
    private static string _gameProfileRole = "";
    private static string _gameProfileFunction = "";
    private static string _gameProfileAvatar = "";
    private static string _gameTenantName = "";
    private static string _gameTenantSlug = "";
    private static string _gameBrandingUrl = "";
    private static string _gameSteamLinked = "";
    private static string _gameSteamNotice = "";
    private static int _gameProfileRevision;
    private static string _gameDeviceId = "";
    private static string _minModRequired = "";
    private static readonly object GameAuthLock = new();

    private static string HandleGameAuth(string function, string[] args)
    {
        if (function == "Init")
        {
            var url = args.Length > 0 ? NormalizeBaseUrl(args[0]) : "";
            if (url.Length == 0)
                url = NormalizeBaseUrl("https://athena.ttrd.fr/public");
            if (url.Length == 0)
                return FailGameAuth("NETWORK_ERROR");
            _baseUrl = url;
            SetGameAuth("EXTENSION_READY", 8, "");
            EnsureGameDeviceId();
            SetGameAuth("CONTACTING_ATHENA", 16, "");
            return "OK|" + _gameAuthState;
        }

        if (function == "GetAuthState")
            return FormatGameAuthState();

        if (function == "GetAuthProgress")
            return $"OK|{_gameAuthProgress}|{_gameAuthStep}|{_gameAuthError}";

        if (function == "RestoreSession")
        {
            var url = args.Length > 0 ? NormalizeBaseUrl(args[0]) : _baseUrl;
            if (url.Length > 0)
                _baseUrl = url;
            var modVer = args.Length > 1 ? ArmaString(args[1]) : "";
            RememberDetectedMod(modVer);
            return RestoreGameSession(modVer);
        }

        if (function == "AuthPassword" && args.Length >= 2)
        {
            var url = NormalizeBaseUrl(args[0]);
            if (url.Length > 0)
                _baseUrl = url;
            var modVer = args.Length > 3 ? ArmaString(args[3]) : "";
            RememberDetectedMod(modVer);
            return GameAuthPassword(args[1], args.Length > 2 ? args[2] : "", modVer);
        }

        if (function == "RequestOtp" && args.Length >= 2)
        {
            var url = NormalizeBaseUrl(args[0]);
            if (url.Length > 0)
                _baseUrl = url;
            return GameRequestOtp(args[1]);
        }

        if (function == "VerifyOtp" && args.Length >= 3)
        {
            var url = NormalizeBaseUrl(args[0]);
            if (url.Length > 0)
                _baseUrl = url;
            var modVer = args.Length > 3 ? ArmaString(args[3]) : "";
            RememberDetectedMod(modVer);
            return GameVerifyOtp(args[1], args[2], modVer);
        }

        if (function == "AuthSteam" && args.Length >= 2)
        {
            var url = NormalizeBaseUrl(args[0]);
            if (url.Length > 0)
                _baseUrl = url;
            var steamMod = args.Length > 2 ? ArmaString(args[2]) : "";
            RememberDetectedMod(steamMod);
            return GameAuthSteam(args[1], steamMod);
        }

        if (function == "GetBootstrap")
            return GameGetBootstrap();

        if (function == "SyncProfile")
            return GameSyncProfile(args.Length > 0 ? args[0] : "");

        if (function == "ConnectC2")
            return GameConnectC2();

        if (function == "Logout")
            return GameLogout();

        return "";
    }

    private static string FailGameAuth(string code)
    {
        SetGameAuth(code, _gameAuthProgress, code);
        return "ERR|" + code;
    }

    private static void SetGameAuth(string state, int progress, string error)
    {
        lock (GameAuthLock)
        {
            _gameAuthState = state;
            _gameAuthStep = state;
            _gameAuthProgress = Math.Clamp(progress, 0, 100);
            _gameAuthError = error;
        }
    }

    private static string FormatGameAuthState()
    {
        lock (GameAuthLock)
        {
            // TabCell("-") pour les vides : splitString d’Arma ignore les cellules vides
            // et décalerait indicatif / communauté / unité / versions.
            return string.Join("\t",
                "OK",
                TabCell(_gameAuthState),
                TabCell(_gameAuthProgress.ToString(CultureInfo.InvariantCulture)),
                TabCell(_gameAuthStep),
                TabCell(_gameAuthError),
                IdentityCell(_gameProfileName),
                CallsignCell(_gameProfileCallsign),
                IdentityCell(_gameTenantName),
                IdentityCell(_gameProfileUnit),
                IdentityCell(_gameProfileGrade),
                TabCell(_gameBrandingUrl),
                IdentityCell(_gameTenantSlug),
                TabCell(_modVersion),
                TabCell(ExtensionVersion),
                TabCell(_minModRequired),
                TabCell(_gameProfileAvatar),
                IdentityCell(_gameProfileRole),
                IdentityCell(_gameProfileFunction),
                TabCell(_gameSteamLinked),
                TabCell(_gameSteamNotice));
        }
    }

    private static string TabCell(string s)
    {
        if (string.IsNullOrEmpty(s)) return "-";
        return s.Replace('\t', ' ').Replace('\n', ' ');
    }

    private static string IdentityCell(string s)
    {
        return LooksLikeInternalUrl(s) ? "-" : TabCell(s);
    }

    private static string CallsignCell(string s)
    {
        if (LooksLikeInternalUrl(s)) return "-";
        var t = (s ?? "").Trim();
        if (t.Length > 40) return "-";
        if (_gameTenantName.Length > 0 && t.Equals(_gameTenantName.Trim(), StringComparison.OrdinalIgnoreCase))
            return "-";
        return TabCell(t);
    }

    private static bool LooksLikeInternalUrl(string s)
    {
        if (string.IsNullOrEmpty(s)) return false;
        var t = s.Trim();
        if (t.StartsWith("http://", StringComparison.OrdinalIgnoreCase)
            || t.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
            return true;
        return t.Contains("/api/", StringComparison.OrdinalIgnoreCase);
    }

    private static void RememberDetectedMod(string modVersion)
    {
        var v = ArmaString(modVersion);
        if (v.Length == 0) return;
        _modVersion = v;
    }

    private static void CaptureVersionHints(string body)
    {
        if (string.IsNullOrEmpty(body) || body[0] != '{') return;
        try
        {
            using var doc = JsonDocument.Parse(body);
            var root = doc.RootElement;
            if (root.TryGetProperty("min_mod_version", out var minEl))
            {
                var min = minEl.GetString() ?? "";
                if (min.Length > 0) _minModRequired = min;
            }
            if (root.TryGetProperty("detected_mod_version", out var detEl))
            {
                var det = detEl.GetString() ?? "";
                if (det.Length > 0) _modVersion = det;
            }
        }
        catch
        {
            // corps non JSON
        }
    }

    private static void EnsureGameDeviceId()
    {
        var store = DpapiGameStore.Load();
        if (store != null && store.DeviceId.Length >= 16)
        {
            _gameDeviceId = store.DeviceId;
            return;
        }
        _gameDeviceId = Guid.NewGuid().ToString("N");
        var next = store ?? new DpapiGameStore.Payload();
        next.DeviceId = _gameDeviceId;
        DpapiGameStore.Save(next);
    }

    private static string RestoreGameSession(string modVersion)
    {
        SetGameAuth("RESTORING_SESSION", 24, "");
        EnsureGameDeviceId();
        var store = DpapiGameStore.Load();
        if (store == null || string.IsNullOrEmpty(store.RefreshToken))
            return FailGameAuth("SESSION_EXPIRED");
        if (!string.IsNullOrEmpty(store.BaseUrl))
            _baseUrl = NormalizeBaseUrl(store.BaseUrl);
        var json = GamePostJson("/api/game/v1/session/restore",
            $"{{\"refresh_token\":\"{EscapeJson(store.RefreshToken)}\",\"device_id\":\"{EscapeJson(_gameDeviceId)}\",\"mod_version\":\"{EscapeJson(modVersion)}\",\"extension_version\":\"{EscapeJson(ExtensionVersion)}\"}}",
            withBearer: false);
        return ApplyGameAuthResponse(json, "RestoreSession");
    }

    private static string GameAuthPassword(string email, string password, string modVersion)
    {
        SetGameAuth("AUTHENTICATING", 32, "");
        EnsureGameDeviceId();
        var steam = _steamUid;
        var json = GamePostJson("/api/game/v1/auth/password",
            $"{{\"email\":\"{EscapeJson(email.Trim())}\",\"password\":\"{EscapeJson(password)}\",\"device_id\":\"{EscapeJson(_gameDeviceId)}\",\"steam_id\":\"{EscapeJson(steam)}\",\"mod_version\":\"{EscapeJson(modVersion)}\",\"extension_version\":\"{EscapeJson(ExtensionVersion)}\"}}",
            withBearer: false);
        return ApplyGameAuthResponse(json, "AuthPassword");
    }

    private static string GameRequestOtp(string email)
    {
        SetGameAuth("AUTHENTICATING", 28, "");
        var json = GamePostJson("/api/game/v1/auth/otp/request",
            $"{{\"email\":\"{EscapeJson(email.Trim())}\"}}",
            withBearer: false);
        if (json.StartsWith("ERR|", StringComparison.Ordinal))
            return json;
        return "OK|sent";
    }

    private static string GameVerifyOtp(string email, string code, string modVersion)
    {
        SetGameAuth("AUTHENTICATING", 36, "");
        EnsureGameDeviceId();
        var json = GamePostJson("/api/game/v1/auth/otp/verify",
            $"{{\"email\":\"{EscapeJson(email.Trim())}\",\"code\":\"{EscapeJson(code.Trim())}\",\"device_id\":\"{EscapeJson(_gameDeviceId)}\",\"steam_id\":\"{EscapeJson(_steamUid)}\",\"mod_version\":\"{EscapeJson(modVersion)}\",\"extension_version\":\"{EscapeJson(ExtensionVersion)}\"}}",
            withBearer: false);
        return ApplyGameAuthResponse(json, "VerifyOtp");
    }

    private static string GameAuthSteam(string steamId, string modVersion)
    {
        SetGameAuth("AUTHENTICATING", 32, "");
        EnsureGameDeviceId();
        ApplySteamUid(steamId);
        var store = DpapiGameStore.Load();
        var pairing = store?.PairingToken ?? "";
        if (pairing.Length < 32)
            return FailGameAuth("STEAM_NOT_LINKED");
        var json = GamePostJson("/api/game/v1/auth/steam/exchange",
            $"{{\"steam_id\":\"{EscapeJson(_steamUid)}\",\"device_id\":\"{EscapeJson(_gameDeviceId)}\",\"pairing_token\":\"{EscapeJson(pairing)}\",\"mod_version\":\"{EscapeJson(modVersion)}\",\"extension_version\":\"{EscapeJson(ExtensionVersion)}\"}}",
            withBearer: false);
        return ApplyGameAuthResponse(json, "AuthSteam");
    }

    private static string GameGetBootstrap()
    {
        if (_gameAccessToken.Length == 0)
            return FailGameAuth("SESSION_EXPIRED");
        SetGameAuth("LOADING_CONFIGURATION", 78, "");
        var json = GameGetJson("/api/game/v1/bootstrap");
        return ApplyGameAuthResponse(json, "GetBootstrap", persistTokens: false);
    }

    private static string GameSyncProfile(string knownRev)
    {
        if (_gameAccessToken.Length == 0)
            return FailGameAuth("SESSION_EXPIRED");
        var json = GameGetJson("/api/game/v1/profile?revision=" + Uri.EscapeDataString(knownRev));
        if (json.StartsWith("ERR|", StringComparison.Ordinal))
            return json;
        try
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            var changed = root.TryGetProperty("changed", out var ch) && ch.ValueKind == JsonValueKind.True;
            if (changed && root.TryGetProperty("profile", out var prof))
                ApplyGameProfile(prof);
            var rev = root.TryGetProperty("revision", out var rv) && rv.TryGetInt32(out var n) ? n : _gameProfileRevision;
            _gameProfileRevision = rev;
            return changed ? "OK|changed|" + rev : "OK|same|" + rev;
        }
        catch
        {
            return FailGameAuth("NETWORK_ERROR");
        }
    }

    private static string GameConnectC2()
    {
        SetGameAuth("CONNECTING_C2", 90, "");
        if (_gameAccessToken.Length == 0)
            return FailGameAuth("SESSION_EXPIRED");
        if (string.IsNullOrEmpty(_baseUrl))
            return FailGameAuth("NETWORK_ERROR");
        var verify = VerifyClientInitSync();
        if (verify.StartsWith("OK|", StringComparison.Ordinal))
        {
            SetGameAuth("READY", 100, "");
            return "OK|READY";
        }
        SetGameAuth("C2_UNAVAILABLE", 90, "C2_UNAVAILABLE");
        return "ERR|C2_UNAVAILABLE";
    }

    private static string GameLogout()
    {
        try
        {
            if (_gameAccessToken.Length > 0)
                GamePostJson("/api/game/v1/session/logout", "{}", withBearer: true);
        }
        catch
        {
            // best-effort
        }
        _gameAccessToken = "";
        var store = DpapiGameStore.Load() ?? new DpapiGameStore.Payload();
        store.RefreshToken = "";
        store.PairingToken = "";
        DpapiGameStore.Save(store);
        SetGameAuth("INITIALIZING", 0, "");
        _gameProfileName = "";
        _gameProfileCallsign = "";
        _gameProfileGrade = "";
        _gameProfileUnit = "";
        _gameProfileRole = "";
        _gameProfileFunction = "";
        _gameProfileAvatar = "";
        _gameTenantName = "";
        _gameTenantSlug = "";
        _gameBrandingUrl = "";
        _gameSteamLinked = "";
        _gameSteamNotice = "";
        _minModRequired = "";
        return "OK|logged_out";
    }

    private static string ApplyGameAuthResponse(string json, string source, bool persistTokens = true)
    {
        if (json.StartsWith("ERR|", StringComparison.Ordinal))
        {
            var code = json.Length > 4 ? json[4..] : "NETWORK_ERROR";
            return FailGameAuth(MapGameError(code, json));
        }
        try
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            if (root.TryGetProperty("error", out var errEl))
            {
                CaptureVersionHints(json);
                var code = errEl.GetString() ?? "NETWORK_ERROR";
                return FailGameAuth(code);
            }
            SetGameAuth("RESOLVING_ACCOUNT", 48, "");
            if (persistTokens && root.TryGetProperty("tokens", out var tokens))
            {
                var access = tokens.TryGetProperty("access_token", out var at) ? (at.GetString() ?? "") : "";
                var refresh = tokens.TryGetProperty("refresh_token", out var rt) ? (rt.GetString() ?? "") : "";
                var pairing = tokens.TryGetProperty("pairing_token", out var pt) ? (pt.GetString() ?? "") : "";
                var device = tokens.TryGetProperty("device_id", out var di) ? (di.GetString() ?? "") : "";
                if (access.Length > 0)
                    _gameAccessToken = access;
                if (device.Length >= 16)
                    _gameDeviceId = device;
                var store = DpapiGameStore.Load() ?? new DpapiGameStore.Payload();
                store.DeviceId = _gameDeviceId;
                store.BaseUrl = _baseUrl;
                if (refresh.Length > 0)
                    store.RefreshToken = refresh;
                if (pairing.Length > 0)
                    store.PairingToken = pairing;
                DpapiGameStore.Save(store);
            }
            SetGameAuth("RESOLVING_TENANT", 58, "");
            if (root.TryGetProperty("tenant", out var tenant))
            {
                _gameTenantName = tenant.TryGetProperty("short_name", out var sn) ? (sn.GetString() ?? "") : "";
                if (_gameTenantName.Length == 0)
                    _gameTenantName = tenant.TryGetProperty("name", out var tn) ? (tn.GetString() ?? "") : "";
                _gameTenantSlug = tenant.TryGetProperty("slug", out var sl) ? (sl.GetString() ?? "") : "";
                var tid = tenant.TryGetProperty("id", out var idEl)
                    ? (idEl.ValueKind == JsonValueKind.Number ? idEl.GetRawText() : (idEl.GetString() ?? ""))
                    : "";
                if (tid.Length > 0)
                    ApplyTenantId(tid);
            }
            SetGameAuth("SYNCING_PROFILE", 68, "");
            if (root.TryGetProperty("profile", out var prof))
                ApplyGameProfile(prof);
            if (root.TryGetProperty("notices", out var notices))
            {
                var linked = false;
                if (notices.TryGetProperty("steam_linked", out var slEl))
                    linked = slEl.ValueKind == JsonValueKind.True
                        || (slEl.ValueKind == JsonValueKind.String && slEl.GetString() == "1");
                _gameSteamLinked = linked ? "1" : "0";
                _gameSteamNotice = notices.TryGetProperty("steam_message", out var smEl)
                    ? (smEl.GetString() ?? "")
                    : "";
            }
            SetGameAuth("LOADING_BRANDING", 74, "");
            if (root.TryGetProperty("branding", out var brand))
            {
                _gameBrandingUrl = brand.TryGetProperty("render_url", out var ru) ? (ru.GetString() ?? "") : "";
                if (_gameTenantName.Length == 0)
                    _gameTenantName = brand.TryGetProperty("name", out var bn) ? (bn.GetString() ?? "") : "";
            }
            SetGameAuth("LOADING_CONFIGURATION", 82, "");
            if (source != "GetBootstrap" && _gameAccessToken.Length > 0)
            {
                var boot = GameGetJson("/api/game/v1/bootstrap");
                if (!boot.StartsWith("ERR|", StringComparison.Ordinal))
                    ApplyGameAuthResponse(boot, "GetBootstrap", persistTokens: false);
            }
            SetGameAuth("CONNECTING_C2", 90, "");
            if (_gameAccessToken.Length > 0 && !string.IsNullOrEmpty(_baseUrl))
            {
                var verify = VerifyClientInitSync();
                if (verify.StartsWith("OK|", StringComparison.Ordinal))
                {
                    SetGameAuth("READY", 100, "");
                    return "OK|READY";
                }
                SetGameAuth("C2_UNAVAILABLE", 90, "C2_UNAVAILABLE");
                return "ERR|C2_UNAVAILABLE";
            }
            SetGameAuth("READY", 100, "");
            return "OK|READY";
        }
        catch
        {
            return FailGameAuth("NETWORK_ERROR");
        }
    }

    private static void ApplyGameProfile(JsonElement prof)
    {
        var first = prof.TryGetProperty("first_name", out var fn) ? (fn.GetString() ?? "") : "";
        var last = prof.TryGetProperty("last_name", out var ln) ? (ln.GetString() ?? "") : "";
        _gameProfileName = (first + " " + last).Trim();
        _gameProfileCallsign = ReadProfileText(prof, "callsign");
        _gameProfileGrade = ReadProfileText(prof, "grade");
        _gameProfileUnit = ReadProfileText(prof, "unit");
        _gameProfileRole = ReadProfileText(prof, "role");
        _gameProfileFunction = ReadProfileText(prof, "function");
        _gameProfileAvatar = prof.TryGetProperty("avatar", out var av) ? (av.GetString() ?? "") : "";
        if (LooksLikeInternalUrl(_gameProfileUnit))
            _gameProfileUnit = "";
        if (_gameProfileCallsign.Length > 40
            || (_gameTenantName.Length > 0
                && _gameProfileCallsign.Equals(_gameTenantName, StringComparison.OrdinalIgnoreCase)))
            _gameProfileCallsign = "";
        if (prof.TryGetProperty("revision", out var rv) && rv.TryGetInt32(out var n))
            _gameProfileRevision = n;
        if (_gameProfileCallsign.Length > 0)
            _callSign = _gameProfileCallsign;
    }

    private static string ReadProfileText(JsonElement prof, string key)
    {
        if (!prof.TryGetProperty(key, out var el)) return "";
        var s = el.GetString() ?? "";
        return LooksLikeInternalUrl(s) ? "" : s;
    }

    private static string MapGameError(string code, string raw)
    {
        if (raw.Contains("INVALID_CREDENTIALS", StringComparison.Ordinal)) return "INVALID_CREDENTIALS";
        if (raw.Contains("OTP_EXPIRED", StringComparison.Ordinal)) return "OTP_EXPIRED";
        if (raw.Contains("STEAM_NOT_LINKED", StringComparison.Ordinal)) return "STEAM_NOT_LINKED";
        if (raw.Contains("ACCOUNT_DISABLED", StringComparison.Ordinal)) return "ACCOUNT_DISABLED";
        if (raw.Contains("TENANT_DISABLED", StringComparison.Ordinal)) return "TENANT_DISABLED";
        if (raw.Contains("NO_TENANT", StringComparison.Ordinal)) return "NO_TENANT";
        if (raw.Contains("MOD_OUTDATED", StringComparison.Ordinal)) return "MOD_OUTDATED";
        if (raw.Contains("SESSION_EXPIRED", StringComparison.Ordinal)) return "SESSION_EXPIRED";
        if (code.StartsWith("http_", StringComparison.Ordinal) || code == "timeout" || code == "invalid_url")
            return "NETWORK_ERROR";
        return code.Length > 0 ? code : "NETWORK_ERROR";
    }

    private static string GamePostJson(string path, string payload, bool withBearer)
    {
        if (!TryBuildRequestUri(_baseUrl, path, out var uri, out var uriErr) || uri is null)
            return "ERR|" + uriErr;
        try
        {
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
            using var content = new StringContent(payload, Encoding.UTF8);
            content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
            using var req = new HttpRequestMessage(HttpMethod.Post, uri) { Content = content };
            if (withBearer && _gameAccessToken.Length > 0)
                req.Headers.TryAddWithoutValidation("Authorization", "Bearer " + _gameAccessToken);
            var resp = HttpClient.SendAsync(req, cts.Token).GetAwaiter().GetResult();
            var body = ReadContentUtf8(resp, cts.Token);
            if (!resp.IsSuccessStatusCode)
            {
                CaptureVersionHints(body);
                var mapped = MapGameError("", body);
                if (mapped != "NETWORK_ERROR")
                    return "ERR|" + mapped;
                return "ERR|http_" + ((int)resp.StatusCode).ToString(CultureInfo.InvariantCulture);
            }
            return body;
        }
        catch
        {
            return "ERR|NETWORK_ERROR";
        }
    }

    private static string GameGetJson(string path)
    {
        if (!TryBuildRequestUri(_baseUrl, path, out var uri, out var uriErr) || uri is null)
            return "ERR|" + uriErr;
        try
        {
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
            using var req = new HttpRequestMessage(HttpMethod.Get, uri);
            if (_gameAccessToken.Length > 0)
                req.Headers.TryAddWithoutValidation("Authorization", "Bearer " + _gameAccessToken);
            var resp = HttpClient.SendAsync(req, cts.Token).GetAwaiter().GetResult();
            var body = ReadContentUtf8(resp, cts.Token);
            if (!resp.IsSuccessStatusCode)
            {
                CaptureVersionHints(body);
                return "ERR|http_" + ((int)resp.StatusCode).ToString(CultureInfo.InvariantCulture);
            }
            return body;
        }
        catch
        {
            return "ERR|NETWORK_ERROR";
        }
    }
}

internal static class DpapiGameStore
{
    internal sealed class Payload
    {
        public string DeviceId { get; set; } = "";
        public string RefreshToken { get; set; } = "";
        public string PairingToken { get; set; } = "";
        public string BaseUrl { get; set; } = "";
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct DataBlob
    {
        public int cbData;
        public IntPtr pbData;
    }

    [DllImport("crypt32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool CryptProtectData(ref DataBlob dataIn, string? descr, IntPtr entropy, IntPtr reserved, IntPtr prompt, int flags, out DataBlob dataOut);

    [DllImport("crypt32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool CryptUnprotectData(ref DataBlob dataIn, IntPtr descr, IntPtr entropy, IntPtr reserved, IntPtr prompt, int flags, out DataBlob dataOut);

    [DllImport("kernel32.dll")]
    private static extern IntPtr LocalFree(IntPtr hMem);

    private const int UiForbidden = 0x1;

    private static string FilePath()
    {
        var dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "COMSPEC", "Overwatch");
        Directory.CreateDirectory(dir);
        return Path.Combine(dir, "session.bin");
    }

    internal static Payload? Load()
    {
        try
        {
            var path = FilePath();
            if (!File.Exists(path))
                return null;
            var protectedBytes = File.ReadAllBytes(path);
            var plain = Unprotect(protectedBytes);
            if (plain.Length == 0)
                return null;
            var text = Encoding.UTF8.GetString(plain);
            using var doc = JsonDocument.Parse(text);
            var root = doc.RootElement;
            return new Payload
            {
                DeviceId = root.TryGetProperty("d", out var d) ? (d.GetString() ?? "") : "",
                RefreshToken = root.TryGetProperty("r", out var r) ? (r.GetString() ?? "") : "",
                PairingToken = root.TryGetProperty("p", out var p) ? (p.GetString() ?? "") : "",
                BaseUrl = root.TryGetProperty("u", out var u) ? (u.GetString() ?? "") : "",
            };
        }
        catch
        {
            return null;
        }
    }

    internal static void Save(Payload payload)
    {
        try
        {
            var json = "{\"d\":\"" + EscapeLocal(payload.DeviceId)
                + "\",\"r\":\"" + EscapeLocal(payload.RefreshToken)
                + "\",\"p\":\"" + EscapeLocal(payload.PairingToken)
                + "\",\"u\":\"" + EscapeLocal(payload.BaseUrl) + "\"}";
            var bytes = Encoding.UTF8.GetBytes(json);
            var protectedBytes = Protect(bytes);
            if (protectedBytes.Length == 0)
                return;
            File.WriteAllBytes(FilePath(), protectedBytes);
        }
        catch
        {
            // best-effort local store
        }
    }

    private static string EscapeLocal(string s)
    {
        return (s ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"");
    }

    private static byte[] Protect(byte[] data)
    {
        var pin = GCHandle.Alloc(data, GCHandleType.Pinned);
        try
        {
            var blobIn = new DataBlob { cbData = data.Length, pbData = pin.AddrOfPinnedObject() };
            if (!CryptProtectData(ref blobIn, "COMSPEC Overwatch", IntPtr.Zero, IntPtr.Zero, IntPtr.Zero, UiForbidden, out var blobOut))
                return Array.Empty<byte>();
            try
            {
                var result = new byte[blobOut.cbData];
                Marshal.Copy(blobOut.pbData, result, 0, blobOut.cbData);
                return result;
            }
            finally
            {
                if (blobOut.pbData != IntPtr.Zero)
                    LocalFree(blobOut.pbData);
            }
        }
        finally
        {
            pin.Free();
        }
    }

    private static byte[] Unprotect(byte[] data)
    {
        var pin = GCHandle.Alloc(data, GCHandleType.Pinned);
        try
        {
            var blobIn = new DataBlob { cbData = data.Length, pbData = pin.AddrOfPinnedObject() };
            if (!CryptUnprotectData(ref blobIn, IntPtr.Zero, IntPtr.Zero, IntPtr.Zero, IntPtr.Zero, UiForbidden, out var blobOut))
                return Array.Empty<byte>();
            try
            {
                var result = new byte[blobOut.cbData];
                Marshal.Copy(blobOut.pbData, result, 0, blobOut.cbData);
                return result;
            }
            finally
            {
                if (blobOut.pbData != IntPtr.Zero)
                    LocalFree(blobOut.pbData);
            }
        }
        finally
        {
            pin.Free();
        }
    }
}
