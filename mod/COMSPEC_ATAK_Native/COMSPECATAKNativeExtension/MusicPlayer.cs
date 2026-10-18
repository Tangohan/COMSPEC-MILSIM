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
    private static HttpClient MusicHttp => _musicHttp ??= CreateHttpClient(60);
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

    private static string MusicResolveFile(string reference)
    {
        return Path.IsPathRooted(reference) ? reference : Path.GetFullPath(Path.Combine(MusicFolder(), reference));
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
                try
                {
                    var files = Directory.EnumerateFiles(dir, "*", SearchOption.AllDirectories)
                        .Where(f => MusicExtensions.Contains(Path.GetExtension(f).ToLowerInvariant()))
                        .Select(f => Path.GetRelativePath(dir, f))
                        .OrderBy(f => f, StringComparer.OrdinalIgnoreCase)
                        .Take(300);
                    foreach (var f in files)
                    {
                        if (sb.Length + f.Length + 1 > MaxOutputBytes - 100) break;
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
                    if (!File.Exists(path)) return "ERR|fichier introuvable";
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
                        try { source = await MusicFetchAsync(uri, kind == "lib").ConfigureAwait(false); }
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
    /// Télécharge un fichier audio (mis en cache) ; un flux sans taille connue (webradio) est ouvert directement par MCI.
    /// </summary>
    private static async Task<string> MusicFetchAsync(Uri uri, bool library = false)
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

        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(90));
        using var req = new HttpRequestMessage(HttpMethod.Get, uri);
        using var resp = await MusicHttp.SendAsync(req, HttpCompletionOption.ResponseHeadersRead, cts.Token).ConfigureAwait(false);
        resp.EnsureSuccessStatusCode();
        var type = resp.Content.Headers.ContentType?.MediaType ?? "";
        if (type.StartsWith("text/html", StringComparison.OrdinalIgnoreCase))
            throw new InvalidOperationException("page web, pas un fichier audio (YouTube, Spotify… ne sont pas lisibles)");
        var len = resp.Content.Headers.ContentLength;
        if (len == null && !library) return uri.AbsoluteUri; // flux continu : lecture directe
        if (len > MusicMaxDownloadBytes) throw new InvalidOperationException("fichier trop lourd (80 Mo max)");

        var tmp = target + ".part";
        await using (var src = await resp.Content.ReadAsStreamAsync(cts.Token).ConfigureAwait(false))
        await using (var dst = File.Create(tmp))
        {
            await src.CopyToAsync(dst, cts.Token).ConfigureAwait(false);
        }
        File.Move(tmp, target, true);
        MusicTrimCache(cache);
        return target;
    }

    private static void MusicTrimCache(string cache)
    {
        try
        {
            // Les morceaux de la bibliothèque Athena (réécoutés souvent) ont leur propre quota.
            var all = new DirectoryInfo(cache).GetFiles();
            var files = all.Where(f => !f.Name.StartsWith("lib_", StringComparison.Ordinal)).OrderByDescending(f => f.LastWriteTimeUtc).Skip(25)
                .Concat(all.Where(f => f.Name.StartsWith("lib_", StringComparison.Ordinal)).OrderByDescending(f => f.LastWriteTimeUtc).Skip(60));
            foreach (var f in files) { try { f.Delete(); } catch { /* fichier en lecture */ } }
        }
        catch { /* best effort */ }
    }
}
