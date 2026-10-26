using System.Collections.Concurrent;
using System.Globalization;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;

namespace COMSPECExtension;

/// <summary>
/// Lecteur audio de l'app Musique du téléphone ATAK natif (Windows MCI, sans dépendance).
/// Toutes les commandes MCI passent par un seul thread dédié (un alias MCI appartient au thread qui l'ouvre).
/// Commandes SQF (retour "OK|..." ou "ERR|...") :
///   MusicList                     → OK|dossier|fichier1|fichier2...   (Documents\Arma 3\COMSPEC_Music)
///   MusicPlay [kind, ref, offsetMs, volume]   kind = file (nom dans le dossier ou chemin complet) | url (http/https)
///                                             | lib (bibliothèque Athena : URL signée, cache par piste sans la signature)
///   MusicPause / MusicResume / MusicStop
///   MusicVolume [0-100]
///   MusicStatus                   → OK|état|positionMs|duréeMs|titre|erreur   (idle, loading, playing, paused, ended, error)
///   MusicOpenFolder               → ouvre le dossier musique dans l'explorateur
/// </summary>
public static partial class Extension
{
    [DllImport("winmm.dll", CharSet = CharSet.Unicode, EntryPoint = "mciSendStringW")]
    private static extern int MciSendString(string command, [Out] char[]? returnString, int returnLength, nint callback);

    private static readonly string[] MusicExtensions = [".mp3", ".wav", ".wma", ".m4a", ".aac", ".mp2"];
    private const string MusicAlias = "comspecmusic";
    private const long MusicMaxDownloadBytes = 80L * 1024 * 1024;

    // Client à part : aucun en-tête Athena ne doit partir vers une URL saisie par le joueur.
    private static HttpClient? _musicHttp;
    private static HttpClient MusicHttp => _musicHttp ??= CreateMusicHttpClient();

    // L'URL peut venir d'un autre joueur (haut-parleur public) : jamais de requête vers le PC lui-même ni le réseau
    // local (box, NAS, services en écoute sur 127.0.0.1). Contrôlé sur l'adresse réellement connectée, donc aussi
    // après résolution DNS et redirection. Exception : l'hôte Athena configuré (bibliothèque, éventuel serveur local).
    private static HttpClient CreateMusicHttpClient()
    {
        var handler = new SocketsHttpHandler
        {
            // Redirections suivies à la main (MusicSendAsync) : chaque cible est contrôlée avant la requête, même quand
            // un proxy système fait la connexion (le contrôle ci-dessous ne voit alors que le proxy).
            AllowAutoRedirect = false,
            ConnectCallback = async (ctx, ct) =>
            {
                var target = ctx.InitialRequestMessage.RequestUri;
                // Proxy système : la connexion part vers le proxy choisi par le joueur, l'hôte cible (et chaque
                // redirection, MusicSendAsync) a été vérifié avant.
                // Forme comparée : punycode et IPv6 sans crochets ("[::1]" vs "::1"), sinon une redirection vers une IP
                // littérale IPv6 ou un nom IDN passerait pour un proxy et échapperait au contrôle.
                var connHost = ctx.DnsEndPoint.Host.Trim('[', ']');
                var viaProxy = target != null
                    && !string.Equals(connHost, target.IdnHost.Trim('[', ']'), StringComparison.OrdinalIgnoreCase)
                    && !string.Equals(connHost, target.Host.Trim('[', ']'), StringComparison.OrdinalIgnoreCase);
                var trusted = viaProxy || MusicIsAthenaHost(ctx.DnsEndPoint.Host);
                var addrs = await System.Net.Dns.GetHostAddressesAsync(ctx.DnsEndPoint.Host, ct).ConfigureAwait(false);
                Exception? last = null;
                foreach (var ip in addrs)
                {
                    if (!trusted && MusicIsPrivateAddress(ip)) { last ??= new InvalidOperationException("adresse locale ou privée refusée"); continue; }
                    var sock = new System.Net.Sockets.Socket(ip.AddressFamily, System.Net.Sockets.SocketType.Stream, System.Net.Sockets.ProtocolType.Tcp) { NoDelay = true };
                    try
                    {
                        await sock.ConnectAsync(new System.Net.IPEndPoint(ip, ctx.DnsEndPoint.Port), ct).ConfigureAwait(false);
                        return new System.Net.Sockets.NetworkStream(sock, ownsSocket: true);
                    }
                    catch (Exception ex) { sock.Dispose(); last = ex; }
                }
                throw last ?? new InvalidOperationException("hôte introuvable");
            }
        };
        var client = new HttpClient(handler)
        {
            Timeout = TimeSpan.FromSeconds(60),
            DefaultRequestVersion = System.Net.HttpVersion.Version11,
            DefaultVersionPolicy = HttpVersionPolicy.RequestVersionOrLower
        };
        try
        {
            client.DefaultRequestHeaders.ExpectContinue = false;
            client.DefaultRequestHeaders.TryAddWithoutValidation("User-Agent", ExtensionProductName + "/" + CurrentExtensionVersion());
        }
        catch { /* en-têtes facultatifs */ }
        return client;
    }

    private static bool MusicIsAthenaHost(string host)
    {
        var b = NormalizeBaseUrl(_baseUrl);
        if (b.Length == 0) b = "https://athena.ttrd.fr";
        return Uri.TryCreate(b, UriKind.Absolute, out var u) && string.Equals(u.Host, host, StringComparison.OrdinalIgnoreCase);
    }

    private static bool MusicIsPrivateAddress(System.Net.IPAddress ip)
    {
        if (ip.IsIPv4MappedToIPv6) ip = ip.MapToIPv4();
        if (System.Net.IPAddress.IsLoopback(ip) || ip.Equals(System.Net.IPAddress.Any) || ip.Equals(System.Net.IPAddress.IPv6Any)) return true;
        if (ip.AddressFamily == System.Net.Sockets.AddressFamily.InterNetwork)
        {
            var b = ip.GetAddressBytes();
            return b[0] == 0 || b[0] == 10 || b[0] == 127 || b[0] >= 224
                || (b[0] == 169 && b[1] == 254)
                || (b[0] == 172 && b[1] >= 16 && b[1] <= 31)
                || (b[0] == 192 && b[1] == 168)
                || (b[0] == 100 && b[1] >= 64 && b[1] <= 127);
        }
        if (ip.IsIPv6LinkLocal || ip.IsIPv6SiteLocal || ip.IsIPv6Multicast || ip.IsIPv6UniqueLocal) return true;
        return false;
    }

    // Vérification préalable (sans connexion), en plus du contrôle ConnectCallback de chaque connexion MusicHttp.
    private static async Task MusicCheckHostAsync(Uri uri)
    {
        if (MusicIsAthenaHost(uri.Host)) return;
        var addrs = System.Net.IPAddress.TryParse(uri.Host.Trim('[', ']'), out var lit)
            ? new[] { lit! }
            : await System.Net.Dns.GetHostAddressesAsync(uri.IdnHost).ConfigureAwait(false);
        if (addrs.Length == 0 || addrs.Any(MusicIsPrivateAddress))
            throw new InvalidOperationException("adresse locale ou privée refusée");
    }

    /// <summary>
    /// GET sur MusicHttp en suivant les redirections (5 au plus) : chaque cible passe par MusicCheckHostAsync avant
    /// d'être demandée. Comme le suivi automatique : pas de passage https -> http ni vers un autre schéma (la réponse 3xx
    /// est alors renvoyée telle quelle et EnsureSuccessStatusCode échoue).
    /// </summary>
    private static async Task<HttpResponseMessage> MusicSendAsync(Uri uri, CancellationToken ct = default)
    {
        var current = uri;
        for (var hop = 0; ; hop++)
        {
            var resp = await MusicHttp.SendAsync(new HttpRequestMessage(HttpMethod.Get, current), HttpCompletionOption.ResponseHeadersRead, ct).ConfigureAwait(false);
            var code = (int)resp.StatusCode;
            var loc = resp.Headers.Location;
            if (hop >= 5 || loc == null || (code != 301 && code != 302 && code != 303 && code != 307 && code != 308)) return resp;
            var next = loc.IsAbsoluteUri ? loc : new Uri(current, loc);
            if ((next.Scheme != "http" && next.Scheme != "https") || (current.Scheme == "https" && next.Scheme == "http")) return resp;
            resp.Dispose();
            await MusicCheckHostAsync(next).ConfigureAwait(false);
            current = next;
        }
    }
    private static readonly BlockingCollection<Action> MusicQueue = new();
    private static Thread? _musicThread;
    private static readonly object MusicLock = new();
    private static int _musicGen;
    private static volatile string _musicState = "idle";
    private static string _musicTitle = "";
    private static string _musicError = "";
    private static int _musicVolume = 70;
    private static bool _musicOpen;
    private static long _musicLength;
    private static long _musicPosition;

    private static string MusicFolder()
    {
        var docs = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);
        var dir = Path.Combine(docs, "Arma 3", "COMSPEC_Music");
        try { Directory.CreateDirectory(dir); } catch { /* lecture seule : on renvoie quand même le chemin */ }
        return dir;
    }

    private static string MusicCacheFolder()
    {
        var dir = Path.Combine(Path.GetTempPath(), "COMSPEC_Music_cache");
        Directory.CreateDirectory(dir);
        return dir;
    }

    private static void MusicEnsureThread()
    {
        lock (MusicLock)
        {
            if (_musicThread != null) return;
            _musicThread = new Thread(() =>
            {
                while (true)
                {
                    // Une commande en attente, sinon toutes les 250 ms : mise à jour de la position (lue sans attente par MusicStatus).
                    if (MusicQueue.TryTake(out var job, 250))
                    {
                        try { job(); } catch (Exception ex) { _musicError = ex.Message; }
                    }
                    try { MusicRefreshStatus(); } catch { /* alias fermé entre-temps */ }
                }
            })
            { IsBackground = true, Name = "COMSPEC music" };
            _musicThread.SetApartmentState(ApartmentState.STA);
            _musicThread.Start();
        }
    }

    /// <summary>Met une commande dans la file du thread MCI, sans attendre (Arma ne doit jamais bloquer).</summary>
    private static string MusicPost(Action job, string reply)
    {
        MusicEnsureThread();
        MusicQueue.Add(job);
        return reply;
    }

    private static void MusicRefreshStatus()
    {
        if (!_musicOpen) return;
        if (long.TryParse(Mci("status " + MusicAlias + " position"), NumberStyles.Integer, CultureInfo.InvariantCulture, out var pos)) _musicPosition = pos;
        if (_musicLength <= 0 && long.TryParse(Mci("status " + MusicAlias + " length"), NumberStyles.Integer, CultureInfo.InvariantCulture, out var len)) _musicLength = len;
        if (_musicState == "playing" && Mci("status " + MusicAlias + " mode") == "stopped") _musicState = "ended";
    }

    private static string Mci(string cmd)
    {
        var buf = new char[512];
        var rc = MciSendString(cmd, buf, buf.Length, 0);
        if (rc != 0) return "#ERR" + rc.ToString(CultureInfo.InvariantCulture);
        var end = Array.IndexOf(buf, '\0');
        return new string(buf, 0, end < 0 ? buf.Length : end);
    }

    private static void MusicClose()
    {
        if (!_musicOpen) return;
        Mci("stop " + MusicAlias);
        Mci("close " + MusicAlias);
        _musicOpen = false;
    }

    private static void MusicApplyVolume()
    {
        if (_musicOpen) Mci("setaudio " + MusicAlias + " volume to " + (_musicVolume * 10).ToString(CultureInfo.InvariantCulture));
    }

    /// <summary>Ouvre et lance une source (fichier ou URL directe) sur le thread MCI.</summary>
    private static void MusicStart(int gen, string source, string title, long offsetMs)
    {
        if (gen != _musicGen) return;
        MusicClose();
        _musicLength = 0;
        _musicPosition = 0;
        var open = Mci("open \"" + source + "\" type mpegvideo alias " + MusicAlias);
        if (open.StartsWith("#ERR", StringComparison.Ordinal) && File.Exists(source))
        {
            // Second essai sur une copie nettoyée (balise ID3v2 et octets parasites retirés avant la première trame).
            string? clean = null;
            try { clean = MusicCleanCopy(source); } catch { /* fichier illisible : on garde la première erreur */ }
            if (clean != null)
            {
                var retry = Mci("open \"" + clean + "\" type mpegvideo alias " + MusicAlias);
                if (!retry.StartsWith("#ERR", StringComparison.Ordinal)) open = retry;
            }
        }
        if (open.StartsWith("#ERR", StringComparison.Ordinal))
        {
            _musicState = "error";
            var real = File.Exists(source) ? MusicSniff(source) : "";
            _musicError = real.Length > 0
                ? "pas un vrai MP3 (" + real + " renommé) : reconvertir en MP3"
                : "format non lu (" + open.Substring(1) + ")";
            return;
        }
        _musicOpen = true;
        Mci("set " + MusicAlias + " time format milliseconds");
        long.TryParse(Mci("status " + MusicAlias + " length"), NumberStyles.Integer, CultureInfo.InvariantCulture, out _musicLength);
        MusicApplyVolume();
        var from = offsetMs > 0 && _musicLength > 0 && offsetMs < _musicLength - 500 ? offsetMs : 0;
        var play = Mci("play " + MusicAlias + (from > 0 ? " from " + from.ToString(CultureInfo.InvariantCulture) : ""));
        if (play.StartsWith("#ERR", StringComparison.Ordinal))
        {
            _musicState = "error";
            _musicError = "lecture refusée (" + play.Substring(1) + ")";
            MusicClose();
            return;
        }
        _musicTitle = title;
        _musicError = "";
        _musicState = "playing";
    }

    /// <summary>
    /// MCI (DirectShow) ne cherche le début du son que dans les premiers Ko du fichier : derrière une grosse balise ID3v2
    /// (pochette de plusieurs centaines de Ko, courante sur les MP3 tirés d'une vidéo YouTube) ou des octets parasites,
    /// il ne trouve aucune trame et l'ouverture échoue (erreur MCI 277) alors que le fichier est un MP3 valide.
    /// Écrit dans le cache une copie qui commence à la première trame MPEG, ou renvoie null s'il n'y a rien à retirer.
    /// </summary>
    private static string? MusicCleanCopy(string path)
    {
        var info = new FileInfo(path);
        if (!info.Exists || info.Length < 64 || info.Length > MusicMaxDownloadBytes) return null;
        var key = path + "|" + info.Length.ToString(CultureInfo.InvariantCulture) + "|" + info.LastWriteTimeUtc.Ticks.ToString(CultureInfo.InvariantCulture);
        var target = Path.Combine(MusicCacheFolder(), "clean_" + Convert.ToHexString(SHA1.HashData(Encoding.UTF8.GetBytes(key))).Substring(0, 16).ToLowerInvariant() + ".mp3");
        if (File.Exists(target) && new FileInfo(target).Length > 0) return target;
        var data = File.ReadAllBytes(path);
        var start = 0;
        // Balises ID3v2 (parfois plusieurs à la suite) : taille « syncsafe » sur 4 octets, + 10 si pied de balise.
        while (data.Length - start > 10 && data[start] == (byte)'I' && data[start + 1] == (byte)'D' && data[start + 2] == (byte)'3')
        {
            var size = (data[start + 6] & 0x7F) << 21 | (data[start + 7] & 0x7F) << 14 | (data[start + 8] & 0x7F) << 7 | (data[start + 9] & 0x7F);
            start += 10 + size + ((data[start + 5] & 0x10) != 0 ? 10 : 0);
        }
        var frame = MusicFindFrame(data, Math.Min(start, data.Length));
        if (frame <= 0) return null;
        var tmp = target + ".part";
        using (var dst = File.Create(tmp)) dst.Write(data, frame, data.Length - frame);
        File.Move(tmp, target, true);
        MusicTrimCache(MusicCacheFolder());
        return target;
    }

    private static readonly int[] Mp3Rates1 = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320];
    private static readonly int[] Mp3Rates2 = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
    private static readonly int[] Mp3Samples = [44100, 48000, 32000];

    /// <summary>Longueur d'une trame MPEG audio Layer III commençant à i, ou 0 si ce n'est pas un en-tête valide.</summary>
    private static int MusicFrameLength(byte[] d, int i)
    {
        if (i + 4 > d.Length || d[i] != 0xFF || (d[i + 1] & 0xE0) != 0xE0) return 0;
        var version = (d[i + 1] >> 3) & 3;   // 3 = MPEG-1, 2 = MPEG-2, 0 = MPEG-2.5, 1 = réservé
        var layer = (d[i + 1] >> 1) & 3;     // 1 = Layer III
        var rateIdx = (d[i + 2] >> 4) & 15;
        var srIdx = (d[i + 2] >> 2) & 3;
        if (version == 1 || layer != 1 || rateIdx == 0 || rateIdx == 15 || srIdx == 3) return 0;
        var sr = Mp3Samples[srIdx] >> (version == 3 ? 0 : version == 2 ? 1 : 2);
        var kbps = (version == 3 ? Mp3Rates1 : Mp3Rates2)[rateIdx];
        var pad = (d[i + 2] >> 1) & 1;
        return (version == 3 ? 144000 : 72000) * kbps / sr + pad;
    }

    /// <summary>Première trame MPEG suivie d'une deuxième trame valide (évite les faux en-têtes dans les données d'image).</summary>
    private static int MusicFindFrame(byte[] d, int from)
    {
        var limit = Math.Min(d.Length - 4, from + 4 * 1024 * 1024);
        for (var i = Math.Max(0, from); i < limit; i++)
        {
            var len = MusicFrameLength(d, i);
            if (len > 0 && (i + len + 4 > d.Length || MusicFrameLength(d, i + len) > 0)) return i;
        }
        return -1;
    }

    /// <summary>Vrai format d'un fichier d'après ses premiers octets, quand ce n'est pas du MPEG audio ("" sinon).</summary>
    private static string MusicSniff(string path)
    {
        var head = new byte[12];
        using (var f = File.OpenRead(path)) { if (f.Read(head, 0, head.Length) < head.Length) return ""; }
        var ascii = Encoding.ASCII.GetString(head);
        if (ascii.Substring(4, 4) == "ftyp") return "M4A/MP4";
        if (head[0] == 0x1A && head[1] == 0x45 && head[2] == 0xDF && head[3] == 0xA3) return "WebM";
        if (ascii.StartsWith("OggS", StringComparison.Ordinal)) return "Ogg/Opus";
        if (ascii.StartsWith("fLaC", StringComparison.Ordinal)) return "FLAC";
        if (ascii.StartsWith("<", StringComparison.Ordinal) || ascii.StartsWith("{", StringComparison.Ordinal)) return "page web";
        return "";
    }

    // La référence peut venir d'un autre joueur (haut-parleur public) : jamais de chemin absolu, UNC (fuite NTLM via SMB)
    // ni "..", seulement un morceau du dossier musique, comme BtFileOpen. "" = refusé.
    private static string MusicResolveFile(string reference)
    {
        if (Path.IsPathRooted(reference) || reference.StartsWith(@"\\", StringComparison.Ordinal) || reference.StartsWith("//", StringComparison.Ordinal)) return "";
        if (reference.Replace('\\', '/').Split('/').Any(p => p.Trim() == "..")) return "";
        var dir = MusicFolder();
        string path;
        try { path = Path.GetFullPath(Path.Combine(dir, reference)); } catch { return ""; }
        if (!BtUnder(path, dir) || !MusicExtensions.Contains(Path.GetExtension(path).ToLowerInvariant())) return "";
        return path;
    }

    private static string MusicCommand(string function, string?[] args)
    {
        string Arg(int i) => args.Length > i ? (args[i] ?? "").Trim() : "";
        switch (function)
        {
            case "MusicList":
            {
                var dir = MusicFolder();
                var sb = new StringBuilder("OK|").Append(dir);
                // Output coupe à MaxOutputBytes octets UTF-8 (accents, CJK : jusqu'à 3 octets par caractère).
                var bytes = Encoding.UTF8.GetByteCount(sb.ToString());
                try
                {
                    var files = Directory.EnumerateFiles(dir, "*", SearchOption.AllDirectories)
                        .Where(f => MusicExtensions.Contains(Path.GetExtension(f).ToLowerInvariant()))
                        .Select(f => Path.GetRelativePath(dir, f))
                        .OrderBy(f => f, StringComparer.OrdinalIgnoreCase)
                        .Take(300);
                    foreach (var f in files)
                    {
                        var fb = Encoding.UTF8.GetByteCount(f) + 1;
                        if (bytes + fb > MaxOutputBytes - 100) break;
                        bytes += fb;
                        sb.Append('|').Append(f);
                    }
                }
                catch (Exception ex) { return "ERR|" + ex.Message; }
                return sb.ToString();
            }
            case "MusicOpenFolder":
            {
                try
                {
                    System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo("explorer.exe", "\"" + MusicFolder() + "\"") { UseShellExecute = false });
                    return "OK|" + MusicFolder();
                }
                catch (Exception ex) { return "ERR|" + ex.Message; }
            }
            case "MusicPlay":
            {
                var kind = Arg(0).ToLowerInvariant();
                var reference = Arg(1);
                var offsetMs = double.TryParse(Arg(2), NumberStyles.Float, CultureInfo.InvariantCulture, out var off) ? (long)Math.Max(0, off) : 0L;
                if (double.TryParse(Arg(3), NumberStyles.Float, CultureInfo.InvariantCulture, out var vol)) _musicVolume = (int)Math.Clamp(vol, 0, 100);
                if (reference.Length == 0) return "ERR|source vide";
                var gen = Interlocked.Increment(ref _musicGen);
                if (kind == "file")
                {
                    var path = MusicResolveFile(reference);
                    if (path.Length == 0 || !File.Exists(path)) return "ERR|fichier introuvable";
                    var title = Path.GetFileNameWithoutExtension(path);
                    _musicState = "loading";
                    _musicTitle = title;
                    _musicError = "";
                    _musicPosition = 0;
                    return MusicPost(() => MusicStart(gen, path, title, offsetMs), "OK|loading");
                }
                if (kind == "url" || kind == "lib")
                {
                    if (!Uri.TryCreate(reference, UriKind.Absolute, out var uri) || (uri.Scheme != "http" && uri.Scheme != "https"))
                        return "ERR|URL invalide (http ou https)";
                    var title = Uri.UnescapeDataString(Path.GetFileNameWithoutExtension(uri.AbsolutePath));
                    if (title.Length == 0) title = uri.Host;
                    _musicState = "loading";
                    _musicTitle = title;
                    _musicError = "";
                    _musicPosition = 0;
                    var asked = Environment.TickCount64;
                    MusicPost(MusicClose, "");
                    _ = Task.Run(async () =>
                    {
                        string source;
                        try { source = await MusicFetchAsync(uri, kind == "lib", gen).ConfigureAwait(false); }
                        catch (Exception ex)
                        {
                            if (gen == _musicGen) { _musicState = "error"; _musicError = "téléchargement : " + ex.Message; }
                            return;
                        }
                        // Le temps de téléchargement compte : on reste calé sur les autres auditeurs.
                        var late = offsetMs > 0 ? offsetMs + (Environment.TickCount64 - asked) : 0;
                        MusicPost(() => MusicStart(gen, source, title, late), "");
                    });
                    return "OK|loading";
                }
                return "ERR|type inconnu";
            }
            case "MusicPause":
                return MusicPost(() =>
                {
                    if (_musicOpen && _musicState == "playing") { Mci("pause " + MusicAlias); _musicState = "paused"; }
                }, "OK|paused");
            case "MusicResume":
                return MusicPost(() =>
                {
                    if (!_musicOpen || _musicState != "paused") return;
                    if (Mci("resume " + MusicAlias).StartsWith("#ERR", StringComparison.Ordinal)) Mci("play " + MusicAlias);
                    _musicState = "playing";
                }, "OK|playing");
            case "MusicStop":
                Interlocked.Increment(ref _musicGen);
                return MusicPost(() => { MusicClose(); _musicState = "idle"; _musicTitle = ""; _musicPosition = 0; _musicLength = 0; }, "OK|idle");
            case "MusicVolume":
                if (double.TryParse(Arg(0), NumberStyles.Float, CultureInfo.InvariantCulture, out var v)) _musicVolume = (int)Math.Clamp(v, 0, 100);
                return MusicPost(MusicApplyVolume, "OK|" + _musicVolume.ToString(CultureInfo.InvariantCulture));
            case "MusicStatus":
                return string.Join('|', "OK", _musicState, _musicPosition.ToString(CultureInfo.InvariantCulture),
                    _musicLength.ToString(CultureInfo.InvariantCulture), _musicTitle.Replace('|', '/'), _musicError.Replace('|', '/'));
        }
        return "ERR|commande inconnue";
    }

    /// <summary>
    /// Télécharge un fichier audio (mis en cache) ; un flux sans taille connue (webradio) est relayé à MCI via 127.0.0.1.
    /// </summary>
    private static async Task<string> MusicFetchAsync(Uri uri, bool library = false, int gen = 0)
    {
        var cache = MusicCacheFolder();
        // Bibliothèque Athena : la signature (exp, sig) change à chaque liste ; la piste, elle, est fixée par le chemin.
        var cacheKey = library ? uri.Host + uri.AbsolutePath : uri.AbsoluteUri;
        var hash = (library ? "lib_" : "") + Convert.ToHexString(SHA1.HashData(Encoding.UTF8.GetBytes(cacheKey))).Substring(0, 16).ToLowerInvariant();
        var ext = Path.GetExtension(uri.AbsolutePath).ToLowerInvariant();
        if (!MusicExtensions.Contains(ext)) ext = ".mp3";
        var target = Path.Combine(cache, hash + ext);
        if (File.Exists(target) && new FileInfo(target).Length > 0)
        {
            try { File.SetLastWriteTimeUtc(target, DateTime.UtcNow); } catch { /* lecture seule */ }
            return target;
        }

        await MusicCheckHostAsync(uri).ConfigureAwait(false);
        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(90));
        using var resp = await MusicSendAsync(uri, cts.Token).ConfigureAwait(false);
        resp.EnsureSuccessStatusCode();
        var type = resp.Content.Headers.ContentType?.MediaType ?? "";
        if (type.StartsWith("text/html", StringComparison.OrdinalIgnoreCase))
            throw new InvalidOperationException("page web, pas un fichier audio (YouTube, Spotify… ne sont pas lisibles)");
        var len = resp.Content.Headers.ContentLength;
        if (len == null && !library)
        {
            // Flux continu : MCI ne reçoit jamais l'URL distante (il rouvrirait sa propre connexion, avec sa propre
            // résolution DNS et ses redirections, hors du contrôle ConnectCallback). Il lit un relais 127.0.0.1.
            var final = resp.RequestMessage?.RequestUri ?? uri;
            if (final != uri) await MusicCheckHostAsync(final).ConfigureAwait(false);
            return MusicStartRelay(final, gen);
        }
        if (len > MusicMaxDownloadBytes) throw new InvalidOperationException("fichier trop lourd (80 Mo max)");

        // Nom propre à cette demande : deux lectures du même morceau (double appui, nouvel essai) ne se disputent pas
        // le même fichier ; la dernière arrivée remplace l'autre (File.Move écrase).
        var tmp = target + "." + gen.ToString(CultureInfo.InvariantCulture) + "." + Environment.TickCount64.ToString(CultureInfo.InvariantCulture) + ".part";
        try
        {
            await using (var src = await resp.Content.ReadAsStreamAsync(cts.Token).ConfigureAwait(false))
            await using (var dst = File.Create(tmp))
            {
                // Taille annoncée absente ou fausse : plafond aussi pendant la copie.
                var buf = new byte[81920];
                long total = 0;
                int n;
                while ((n = await src.ReadAsync(buf, cts.Token).ConfigureAwait(false)) > 0)
                {
                    total += n;
                    if (total > MusicMaxDownloadBytes) throw new InvalidOperationException("fichier trop lourd (80 Mo max)");
                    await dst.WriteAsync(buf.AsMemory(0, n), cts.Token).ConfigureAwait(false);
                }
            }
            File.Move(tmp, target, true);
        }
        catch
        {
            // Plafond dépassé, délai, coupure : pas de fichier partiel laissé dans %TEMP%.
            try { File.Delete(tmp); } catch { /* encore ouvert */ }
            throw;
        }
        MusicTrimCache(cache);
        return target;
    }

    private static int _musicRelayClients;

    /// <summary>
    /// Relais local d'un flux continu : écoute sur 127.0.0.1 (port libre, chemin à jeton aléatoire) et recopie vers MCI
    /// le flux lu par MusicHttp, dont chaque connexion passe par le contrôle d'adresse. Arrêté dès que la lecture change.
    /// </summary>
    private static string MusicStartRelay(Uri remote, int gen)
    {
        var listener = new System.Net.Sockets.TcpListener(System.Net.IPAddress.Loopback, 0);
        listener.Start();
        var port = ((System.Net.IPEndPoint)listener.LocalEndpoint).Port;
        var token = Convert.ToHexString(RandomNumberGenerator.GetBytes(16)).ToLowerInvariant();
        var ext = Path.GetExtension(remote.AbsolutePath).ToLowerInvariant();
        if (!MusicExtensions.Contains(ext)) ext = ".mp3";
        _ = Task.Run(async () =>
        {
            try
            {
                while (gen == _musicGen)
                {
                    System.Net.Sockets.TcpClient client;
                    using (var wait = new CancellationTokenSource(TimeSpan.FromSeconds(1)))
                    {
                        try { client = await listener.AcceptTcpClientAsync(wait.Token).ConfigureAwait(false); }
                        catch (OperationCanceledException) { continue; }
                    }
                    if (gen != _musicGen) { client.Dispose(); break; }
                    if (Interlocked.Increment(ref _musicRelayClients) > 4)
                    {
                        Interlocked.Decrement(ref _musicRelayClients);
                        client.Dispose();
                        continue;
                    }
                    _ = Task.Run(async () =>
                    {
                        try { await MusicRelayServeAsync(client, remote, token, gen).ConfigureAwait(false); }
                        finally { Interlocked.Decrement(ref _musicRelayClients); }
                    });
                }
            }
            catch { /* relais arrêté */ }
            finally { try { listener.Stop(); } catch { /* déjà fermé */ } }
        });
        return "http://127.0.0.1:" + port.ToString(CultureInfo.InvariantCulture) + "/" + token + ext;
    }

    private static async Task MusicRelayServeAsync(System.Net.Sockets.TcpClient client, Uri remote, string token, int gen)
    {
        using (client)
        {
            try
            {
                client.NoDelay = true;
                var net = client.GetStream();
                // Requête du lecteur : seule la ligne de requête compte (méthode + chemin à jeton), en-têtes ignorés.
                var head = new StringBuilder();
                var one = new byte[1];
                while (head.Length < 8192 && await net.ReadAsync(one.AsMemory(0, 1)).ConfigureAwait(false) == 1)
                {
                    head.Append((char)one[0]);
                    if (head.Length >= 4 && head[^1] == '\n' && head[^2] == '\r' && head[^3] == '\n' && head[^4] == '\r') break;
                }
                var parts = head.ToString().Split("\r\n")[0].Split(' ');
                if (parts.Length < 2 || !parts[1].StartsWith("/" + token, StringComparison.Ordinal)) return;
                using var resp = await MusicSendAsync(remote).ConfigureAwait(false);
                var type = resp.Content.Headers.ContentType?.MediaType ?? "audio/mpeg";
                if (!resp.IsSuccessStatusCode || type.StartsWith("text/html", StringComparison.OrdinalIgnoreCase))
                {
                    await net.WriteAsync(Encoding.ASCII.GetBytes("HTTP/1.0 502 Bad Gateway\r\nConnection: close\r\n\r\n")).ConfigureAwait(false);
                    return;
                }
                await net.WriteAsync(Encoding.ASCII.GetBytes("HTTP/1.0 200 OK\r\nContent-Type: " + type + "\r\nConnection: close\r\n\r\n")).ConfigureAwait(false);
                if (parts[0] == "HEAD") return;
                await using var src = await resp.Content.ReadAsStreamAsync().ConfigureAwait(false);
                var buf = new byte[16384];
                int n;
                while (gen == _musicGen && (n = await src.ReadAsync(buf).ConfigureAwait(false)) > 0)
                    await net.WriteAsync(buf.AsMemory(0, n)).ConfigureAwait(false);
            }
            catch { /* lecteur ou source coupé */ }
        }
    }

    private static void MusicTrimCache(string cache)
    {
        try
        {
            // Les morceaux de la bibliothèque Athena (réécoutés souvent) ont leur propre quota.
            var all = new DirectoryInfo(cache).GetFiles();
            // Fichiers partiels (.part) : hors quota ; ceux de plus d'une heure (téléchargement interrompu, jeu fermé) sont effacés.
            foreach (var p in all.Where(f => f.Name.EndsWith(".part", StringComparison.Ordinal) && f.LastWriteTimeUtc < DateTime.UtcNow.AddHours(-1)))
            {
                try { p.Delete(); } catch { /* en cours d'écriture */ }
            }
            all = all.Where(f => !f.Name.EndsWith(".part", StringComparison.Ordinal)).ToArray();
            var files = all.Where(f => !f.Name.StartsWith("lib_", StringComparison.Ordinal)).OrderByDescending(f => f.LastWriteTimeUtc).Skip(25)
                .Concat(all.Where(f => f.Name.StartsWith("lib_", StringComparison.Ordinal)).OrderByDescending(f => f.LastWriteTimeUtc).Skip(60));
            foreach (var f in files) { try { f.Delete(); } catch { /* fichier en lecture */ } }
        }
        catch { /* best effort */ }
    }
}
