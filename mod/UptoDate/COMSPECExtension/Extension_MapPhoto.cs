using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace COMSPECExtension;

/// <summary>
/// Fond PHOTO du téléphone ATAK téléchargé en jeu depuis Athena (2.0.92), sans mod Steam ni redémarrage : les tuiles
/// JPEG publiques du site (/map-photo/&lt;carte&gt;/&lt;rendu&gt;/z/x/y.jpg, envoyées par tools/map_stitch.py --upload) sont
/// gardées dans Arma 3\COMSPEC_cache\map-photo\&lt;carte&gt;\&lt;rendu&gt;\z\x\y.jpg (voir MapPhotoCacheRoot) (écriture .part puis renommage),
/// avec meta.json et index.txt (tuiles qui existent sur le site). Le téléphone les dessine par leur chemin absolu à
/// barres obliques (C:/…/y.jpg), comme les images de briefing téléchargées (DownloadBriefingSlideImage).
/// Jamais bloquant : chaque commande répond tout de suite, le réseau et le disque travaillent en tâche de fond
/// (4 tuiles à la fois au plus, débit limité à 3 Mo/s environ, les tuiles demandées par l'écran passent devant le
/// téléchargement hors ligne). Le mod @COMSPEC_ATAK_Photo, s'il est chargé, reste prioritaire (côté SQF).
/// Commandes (champ vide : « - », le SQF découpe par splitString qui saute les champs vides) :
///   MapPhoto.List → OK|pending, ou OK|&lt;n&gt;|carte,rendu,maxZoom,tuiles JPEG,octets JPEG,date;… (liste du site, 5 min en cache)
///   MapPhoto.Meta [carte, rendu] → OK|pending, ERR|none (aucun fond JPEG sur Athena), ERR|not_connected, ou
///     OK|extent|tileSize|minZoom|maxZoom|.jpg|dossier du cache (C:/…/, barres obliques)|tuiles du site|octets|tuiles en cache|date
///   MapPhoto.Want [carte, rendu, "z/x/y,z/x/y…"] → OK|&lt;en cache&gt;|&lt;absentes (pas sur le site, 404 ou erreur récente)&gt;|&lt;mises en file&gt;
///   MapPhoto.Status [carte, rendu] → OK|en file|téléchargées|octets|erreurs|en cours|hors ligne : état|faites|total|
///     tuiles en cache|octets en cache|&lt;arrivées z/x/y,…&gt;|&lt;manquées z/x/y,…&gt;   (arrivées et manquées de ce fond depuis le
///     dernier appel ; manquées = 404 ou erreur, à redemander plus tard)
///   MapPhoto.Download [carte, rendu, zMax] → OK|&lt;à télécharger&gt;|&lt;total&gt; : toute la pyramide jusqu'à zMax, en tâche de
///     fond ; les tuiles déjà en cache sont sautées (reprend où il s'était arrêté)
///   MapPhoto.Cancel → OK : vide la file et arrête le téléchargement hors ligne
///   MapPhoto.Clear [carte] → OK|clearing : efface le cache de la carte (tous rendus)
/// </summary>
public static partial class Extension
{
    private sealed class MapPhotoSet
    {
        public string World = "", Mode = "", Dir = "";
        public double Extent;
        public int TileSize = 256, MinZoom, MaxZoom;
        public long JpgTiles, JpgBytes;
        public string Created = "";
        public bool MetaReady, MetaLoading, Refreshed, None;
        public DateTime MetaTried = DateTime.MinValue;
        /// <summary>Tuiles JPEG du site (index-jpg.txt) ; null si inconnu (tout est demandé).</summary>
        public HashSet<long>? Index;
        public readonly HashSet<long> Cached = new();
        public long CachedBytes;
        public bool Scanned, ScanDone;
        /// <summary>404 ou erreur : pas redemandée avant cette heure.</summary>
        public readonly Dictionary<long, DateTime> Backoff = new();
        public readonly Queue<string> Arrived = new();
        public readonly Queue<string> Missing = new();
        /// <summary>Incrémenté par Clear : les tuiles encore en route ne sont pas comptées en cache.</summary>
        public int Generation;
        /// <summary>Début du dernier effacement : un fichier plus ancien sur le disque est une tuile périmée.</summary>
        public DateTime WipedAt = DateTime.MinValue;
    }

    private static readonly object MapPhotoLock = new();
    private static readonly Dictionary<string, MapPhotoSet> MapPhotoSets = new(StringComparer.OrdinalIgnoreCase);
    private static readonly LinkedList<(MapPhotoSet Set, long Key)> MapPhotoWantQ = new();
    private static readonly HashSet<(MapPhotoSet, long)> MapPhotoQueued = new();
    /// <summary>Tuiles en cours de téléchargement : ni remises en file ni téléchargées deux fois.</summary>
    private static readonly HashSet<(MapPhotoSet, long)> MapPhotoInFlight = new();
    private static MapPhotoSet? _mpDlSet;
    private static List<long> _mpDlTodo = new();
    private static int _mpDlPos, _mpDlTotal, _mpDlDone;
    private static string _mpDlState = "idle";
    private static bool _mpPump;
    private static long _mpDownloaded, _mpBytes, _mpErrors;
    private static int _mpActive;
    private static DateTime _mpNextSlot = DateTime.MinValue;
    private static string _mpList = "";
    private static DateTime _mpListAt = DateTime.MinValue;
    private static bool _mpListLoading;
    private const double MapPhotoBytesPerSec = 3.0 * 1024 * 1024;
    private const int MapPhotoParallel = 4;
    // Client à part : délai court, sans clé (tuiles publiques), jamais en concurrence avec la synchro.
    // Aucun secret n'y est jamais attaché (pas d'AttachApiKeyHeader) : il suit donc les redirections (SEC-001),
    // par exemple une 308 de l'ancien domaine vers athena-comspec.eu (docs/migration-domaine, étape 6).
    private static readonly Lazy<HttpClient> MapPhotoHttp = new(() => CreateHttpClient(30, followRedirects: true));

    private static long MapPhotoKey(int z, int x, int y) => ((long)z << 48) | ((long)x << 24) | (uint)y;

    private static string MapPhotoName(long k) =>
        ((int)(k >> 48)).ToString(CultureInfo.InvariantCulture) + "/" + ((int)((k >> 24) & 0xFFFFFF)).ToString(CultureInfo.InvariantCulture)
        + "/" + ((int)(k & 0xFFFFFF)).ToString(CultureInfo.InvariantCulture);

    private static bool MapPhotoParse(string s, out long key)
    {
        key = 0;
        var p = s.Trim().Split('/');
        if (p.Length != 3
            || !int.TryParse(p[0], NumberStyles.None, CultureInfo.InvariantCulture, out var z)
            || !int.TryParse(p[1], NumberStyles.None, CultureInfo.InvariantCulture, out var x)
            || !int.TryParse(p[2], NumberStyles.None, CultureInfo.InvariantCulture, out var y)) return false;
        if (z > 22 || x >= (1 << z) || y >= (1 << z)) return false;
        key = MapPhotoKey(z, x, y);
        return true;
    }

    /// <summary>Nom de carte du site (minuscules, a-z 0-9 _ -), ou "".</summary>
    private static string MapPhotoSlug(string? raw)
    {
        var s = (raw ?? "").Trim().ToLowerInvariant();
        if (s.Length == 0 || s.Length > 64 || s.Any(c => !(c is >= 'a' and <= 'z' or >= '0' and <= '9' or '_' or '-'))) return "";
        return s;
    }

    /// <summary>Dossier du cache : Arma ne charge une image par chemin absolu que sous son propre dossier ou celui d'un
    /// mod (« Cannot load texture c:/users/…/documents/… » : rien ne s'affichait, TTRD 20/10). Dossier d'Arma
    /// (Arma 3\COMSPEC_cache\map-photo), gardé d'une mise à jour Steam à l'autre ; sinon celui de la DLL (le mod,
    /// comme les images de briefing) ; Documents en dernier recours.</summary>
    private static string MapPhotoCacheRoot()
    {
        var c = _mapPhotoCacheRoot;
        if (c != null) return c;
        var tries = new List<string>();
        try
        {
            var exe = Environment.ProcessPath;
            if (!string.IsNullOrEmpty(exe) && Path.GetFileName(exe).StartsWith("arma3", StringComparison.OrdinalIgnoreCase))
                tries.Add(Path.Combine(Path.GetDirectoryName(exe)!, "COMSPEC_cache", "map-photo"));
        }
        catch { /* chemin du jeu inconnu */ }
        tries.Add(Path.Combine(AppContext.BaseDirectory, "comspec_cache", "map-photo"));
        tries.Add(Path.Combine(MapCapRoot(), "cache"));
        foreach (var dir in tries)
        {
            try
            {
                Directory.CreateDirectory(dir);
                var probe = Path.Combine(dir, ".write_test");
                File.WriteAllText(probe, "ok");
                File.Delete(probe);
                return _mapPhotoCacheRoot = dir;
            }
            catch { /* dossier suivant */ }
        }
        return _mapPhotoCacheRoot = tries[^1];
    }
    private static string? _mapPhotoCacheRoot;

    private static string MapPhotoFile(MapPhotoSet set, long k) =>
        Path.Combine(set.Dir, ((int)(k >> 48)).ToString(CultureInfo.InvariantCulture), ((int)((k >> 24) & 0xFFFFFF)).ToString(CultureInfo.InvariantCulture),
            ((int)(k & 0xFFFFFF)).ToString(CultureInfo.InvariantCulture) + ".jpg");

    private static string MapPhotoNum(double v) => v.ToString("0.###", CultureInfo.InvariantCulture);

    private static MapPhotoSet? MapPhotoGet(string? world, string? mode)
    {
        var w = MapPhotoSlug(world);
        var m = MapPhotoSlug(string.IsNullOrWhiteSpace(mode) ? "sat" : mode);
        if (w.Length == 0 || (m != "sat" && m != "plan")) return null;
        lock (MapPhotoLock)
        {
            if (!MapPhotoSets.TryGetValue(w + "|" + m, out var set))
            {
                set = new MapPhotoSet { World = w, Mode = m, Dir = Path.Combine(MapPhotoCacheRoot(), w, m) };
                MapPhotoSets[w + "|" + m] = set;
            }
            if (!set.Scanned)
            {
                set.Scanned = true;
                _ = Task.Run(() => MapPhotoScan(set));
            }
            return set;
        }
    }

    /// <summary>Relève les tuiles déjà sur le disque (séance précédente), meta.json et index.txt compris.</summary>
    private static void MapPhotoScan(MapPhotoSet set)
    {
        try
        {
            var meta = Path.Combine(set.Dir, "meta.json");
            if (File.Exists(meta)) MapPhotoApplyMeta(set, File.ReadAllText(meta), false);
            var idx = Path.Combine(set.Dir, "index.txt");
            if (File.Exists(idx)) MapPhotoApplyIndex(set, File.ReadAllText(idx));
            if (!Directory.Exists(set.Dir)) return;
            var found = new List<(long, long)>();
            foreach (var zd in Directory.EnumerateDirectories(set.Dir))
            {
                if (!int.TryParse(Path.GetFileName(zd), NumberStyles.None, CultureInfo.InvariantCulture, out var z) || z > 22) continue;
                foreach (var xd in Directory.EnumerateDirectories(zd))
                {
                    if (!int.TryParse(Path.GetFileName(xd), NumberStyles.None, CultureInfo.InvariantCulture, out var x)) continue;
                    foreach (var f in Directory.EnumerateFiles(xd, "*.jpg"))
                    {
                        if (!int.TryParse(Path.GetFileNameWithoutExtension(f), NumberStyles.None, CultureInfo.InvariantCulture, out var y)) continue;
                        try { found.Add((MapPhotoKey(z, x, y), new FileInfo(f).Length)); } catch { /* fichier disparu */ }
                    }
                }
            }
            lock (MapPhotoLock)
            {
                foreach (var (k, len) in found)
                    if (set.Cached.Add(k)) set.CachedBytes += len;
            }
        }
        catch { /* cache illisible : retéléchargé à la demande */ }
        finally { lock (MapPhotoLock) set.ScanDone = true; }
    }

    /// <summary>meta.json du site → fond prêt. Une autre date de capture que celle du cache : l'ancien cache est effacé.</summary>
    private static bool MapPhotoApplyMeta(MapPhotoSet set, string json, bool fromServer)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            var r = doc.RootElement;
            double Num(string n, double d) => r.TryGetProperty(n, out var v) && v.ValueKind == JsonValueKind.Number ? v.GetDouble() : d;
            var extent = Num("extent", 0);
            if (extent <= 0) return false;
            var created = r.TryGetProperty("created", out var c) && c.ValueKind == JsonValueKind.String ? c.GetString() ?? "" : "";
            var jpgTiles = (long)Num("jpgTiles", 0);
            bool stale;
            lock (MapPhotoLock)
            {
                stale = fromServer && set.MetaReady && set.Created.Length > 0 && created != set.Created;
                set.Extent = extent;
                set.TileSize = Math.Max(1, (int)Num("tileSize", 256));
                set.MinZoom = (int)Num("minZoom", 0);
                set.MaxZoom = Math.Min(22, (int)Num("maxZoom", 0));
                set.JpgTiles = jpgTiles;
                set.JpgBytes = (long)Num("jpgBytes", 0);
                set.Created = created;
                set.MetaReady = !fromServer || jpgTiles > 0 || r.TryGetProperty("jpg", out var j) && j.ValueKind == JsonValueKind.True;
                set.None = fromServer && !set.MetaReady;
            }
            if (stale) MapPhotoWipe(set, false);
            return set.MetaReady;
        }
        catch { return false; }
    }

    private static void MapPhotoApplyIndex(MapPhotoSet set, string txt)
    {
        var idx = new HashSet<long>();
        foreach (var line in txt.Split('\n'))
            if (MapPhotoParse(line, out var k)) idx.Add(k);
        lock (MapPhotoLock) set.Index = idx.Count > 0 ? idx : null;
    }

    /// <summary>meta.json puis index-jpg.txt du site, gardés dans le cache (fond utilisable hors ligne ensuite).</summary>
    private static async Task MapPhotoLoadMeta(MapPhotoSet set)
    {
        try
        {
            var root = _baseUrl;
            if (string.IsNullOrEmpty(root)) return;
            var path = "/map-photo/" + set.World + "/" + set.Mode + "/";
            if (!TryBuildRequestUri(root, path + "meta.json", out var uri, out _) || uri is null) return;
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(20));
            using var resp = await MapPhotoHttp.Value.GetAsync(uri, cts.Token).ConfigureAwait(false);
            if (resp.StatusCode == HttpStatusCode.NotFound)
            {
                lock (MapPhotoLock) { set.None = !set.MetaReady; set.Refreshed = true; }
                return;
            }
            if (!resp.IsSuccessStatusCode) return;
            var json = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
            if (!MapPhotoApplyMeta(set, json, true))
            {
                lock (MapPhotoLock) set.Refreshed = true;
                return;
            }
            Directory.CreateDirectory(set.Dir);
            MapPhotoWriteAtomic(Path.Combine(set.Dir, "meta.json"), Encoding.UTF8.GetBytes(json));
            if (TryBuildRequestUri(root, path + "index-jpg.txt", out var iuri, out _) && iuri is not null)
            {
                using var ir = await MapPhotoHttp.Value.GetAsync(iuri, cts.Token).ConfigureAwait(false);
                if (ir.IsSuccessStatusCode)
                {
                    var txt = await ir.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                    MapPhotoApplyIndex(set, txt);
                    MapPhotoWriteAtomic(Path.Combine(set.Dir, "index.txt"), Encoding.UTF8.GetBytes(txt));
                }
            }
            lock (MapPhotoLock) set.Refreshed = true;
        }
        catch { /* réseau : redemandé au prochain MapPhoto.Meta */ }
        finally
        {
            lock (MapPhotoLock) set.MetaLoading = false;
        }
    }

    private static void MapPhotoWriteAtomic(string file, byte[] data)
    {
        Directory.CreateDirectory(Path.GetDirectoryName(file)!);
        // Nom temporaire unique : deux écritures du même fichier ne se disputent pas le même .part.
        var tmp = file + "." + Guid.NewGuid().ToString("N") + ".part";
        try
        {
            File.WriteAllBytes(tmp, data);
            File.Move(tmp, file, true);
        }
        catch
        {
            try { File.Delete(tmp); } catch { /* ignore */ }
            throw;
        }
    }

    /// <summary>Efface les tuiles d'un fond (et ses fichiers d'état si complet), en tâche de fond.</summary>
    private static void MapPhotoWipe(MapPhotoSet set, bool all)
    {
        lock (MapPhotoLock)
        {
            set.Generation++;
            set.WipedAt = DateTime.UtcNow;
            set.Cached.Clear();
            set.CachedBytes = 0;
            set.Backoff.Clear();
            set.Arrived.Clear();
            set.Missing.Clear();
            if (all) { set.MetaReady = false; set.Refreshed = false; set.None = false; set.Index = null; set.MetaTried = DateTime.MinValue; }
        }
        var dir = set.Dir;
        _ = Task.Run(() =>
        {
            try
            {
                if (!Directory.Exists(dir)) return;
                foreach (var d in Directory.EnumerateDirectories(dir))
                    if (int.TryParse(Path.GetFileName(d), NumberStyles.None, CultureInfo.InvariantCulture, out _) || all) Directory.Delete(d, true);
                if (all) Directory.Delete(dir, true);
            }
            catch { /* fichier ouvert par le jeu : laissé */ }
            finally
            {
                // Des tuiles arrivées pendant l'effacement ont pu être supprimées : le cache en mémoire repart de zéro
                // (les fichiers restés et plus récents que WipedAt sont repris par MapPhotoFetch), et les
                // téléchargements encore en route n'écrivent plus (génération changée).
                lock (MapPhotoLock)
                {
                    set.Generation++;
                    set.Cached.Clear();
                    set.CachedBytes = 0;
                    set.Arrived.Clear();
                }
            }
        });
    }

    private static void MapPhotoStartPump()
    {
        // Sous MapPhotoLock.
        if (_mpPump) return;
        _mpPump = true;
        _ = Task.Run(MapPhotoPumpLoop);
    }

    /// <summary>Prochaine tuile : demandes de l'écran d'abord (les plus récentes devant), puis le téléchargement hors ligne.</summary>
    private static bool MapPhotoNext(out MapPhotoSet set, out long key, out bool offline)
    {
        // Sous MapPhotoLock.
        while (MapPhotoWantQ.First is { } node)
        {
            MapPhotoWantQ.RemoveFirst();
            MapPhotoQueued.Remove(node.Value);
            if (node.Value.Set.Cached.Contains(node.Value.Key) || !MapPhotoInFlight.Add(node.Value)) continue;
            (set, key, offline) = (node.Value.Set, node.Value.Key, false);
            return true;
        }
        if (_mpDlSet is { } ds && _mpDlState == "running")
        {
            while (_mpDlPos < _mpDlTodo.Count)
            {
                var k = _mpDlTodo[_mpDlPos++];
                if (ds.Cached.Contains(k) || !MapPhotoInFlight.Add((ds, k))) { _mpDlDone++; continue; }
                (set, key, offline) = (ds, k, true);
                return true;
            }
        }
        set = null!;
        key = 0;
        offline = false;
        return false;
    }

    private static async Task MapPhotoPumpLoop()
    {
        var running = new List<Task>();
        while (true)
        {
            running.RemoveAll(t => t.IsCompleted);
            MapPhotoSet set;
            long key;
            bool offline;
            if (running.Count >= MapPhotoParallel)
            {
                await Task.WhenAny(running).ConfigureAwait(false);
                continue;
            }
            lock (MapPhotoLock)
            {
                if (!MapPhotoNext(out set, out key, out offline))
                {
                    if (running.Count == 0)
                    {
                        if (_mpDlState == "running" && _mpDlPos >= _mpDlTodo.Count) _mpDlState = "done";
                        _mpPump = false;
                        return;
                    }
                    set = null!;
                }
            }
            if (set is null)
            {
                // Rien de neuf : on attend qu'une tuile finisse, ou une nouvelle demande (relu toutes les 200 ms).
                await Task.WhenAny(Task.WhenAny(running), Task.Delay(200)).ConfigureAwait(false);
                continue;
            }
            // Débit : chaque tuile reçue repousse le départ suivant de sa taille / 3 Mo/s.
            TimeSpan wait;
            lock (MapPhotoLock) wait = _mpNextSlot - DateTime.UtcNow;
            if (wait > TimeSpan.Zero) await Task.Delay(wait > TimeSpan.FromSeconds(2) ? TimeSpan.FromSeconds(2) : wait).ConfigureAwait(false);
            var s = set; var k = key; var off = offline;
            running.Add(Task.Run(() => MapPhotoFetch(s, k, off)));
        }
    }

    private static async Task MapPhotoFetch(MapPhotoSet set, long k, bool offline)
    {
        Interlocked.Increment(ref _mpActive);
        int gen;
        lock (MapPhotoLock) gen = set.Generation;
        var ok = false;
        var notFound = false;
        try
        {
            var file = MapPhotoFile(set, k);
            // Déjà sur le disque (relevé du cache pas encore fini) : rien à télécharger. Plus ancien que le dernier
            // effacement (autre date de capture) : tuile périmée, retéléchargée.
            DateTime wipedAt;
            lock (MapPhotoLock) wipedAt = set.WipedAt;
            if (File.Exists(file) && wipedAt != DateTime.MinValue && File.GetLastWriteTimeUtc(file) < wipedAt)
            {
                try { File.Delete(file); } catch { /* ouvert par le jeu : écrasé plus bas */ }
            }
            if (File.Exists(file) && (wipedAt == DateTime.MinValue || File.GetLastWriteTimeUtc(file) >= wipedAt))
            {
                var len = new FileInfo(file).Length;
                lock (MapPhotoLock)
                {
                    if (gen == set.Generation && set.Cached.Add(k)) set.CachedBytes += len;
                    if (offline) _mpDlDone++;
                    if (set.Arrived.Count < 4000) set.Arrived.Enqueue(MapPhotoName(k));
                }
                ok = true;
                return;
            }
            var root = _baseUrl;
            if (string.IsNullOrEmpty(root)
                || !TryBuildRequestUri(root, "/map-photo/" + set.World + "/" + set.Mode + "/" + MapPhotoName(k) + ".jpg", out var uri, out _)
                || uri is null) return;
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(30));
            using var resp = await MapPhotoHttp.Value.GetAsync(uri, cts.Token).ConfigureAwait(false);
            if (resp.StatusCode == HttpStatusCode.NotFound) { notFound = true; return; }
            if (!resp.IsSuccessStatusCode) return;
            var bytes = await resp.Content.ReadAsByteArrayAsync(cts.Token).ConfigureAwait(false);
            lock (MapPhotoLock)
            {
                var now = DateTime.UtcNow;
                _mpNextSlot = (_mpNextSlot > now ? _mpNextSlot : now) + TimeSpan.FromSeconds(bytes.Length / MapPhotoBytesPerSec);
            }
            if (bytes.Length < 4 || bytes[0] != 0xFF || bytes[1] != 0xD8 || bytes[2] != 0xFF) return;
            // Effacé pendant le téléchargement : l'ancienne imagerie n'est pas écrite (elle serait reprise ensuite).
            lock (MapPhotoLock)
            {
                if (gen != set.Generation) { ok = true; return; }
            }
            MapPhotoWriteAtomic(file, bytes);
            lock (MapPhotoLock)
            {
                if (gen == set.Generation && set.Cached.Add(k)) set.CachedBytes += bytes.Length;
                if (offline) _mpDlDone++;
                if (set.Arrived.Count < 4000) set.Arrived.Enqueue(MapPhotoName(k));
                _mpDownloaded++;
                _mpBytes += bytes.Length;
            }
            ok = true;
        }
        catch { ok = false; }
        finally
        {
            Interlocked.Decrement(ref _mpActive);
            lock (MapPhotoLock) MapPhotoInFlight.Remove((set, k));
            if (!ok)
            {
                lock (MapPhotoLock)
                {
                    // Absente du site : 30 min ; erreur (réseau, disque) : 1 min, et comptée.
                    set.Backoff[k] = DateTime.UtcNow + (notFound ? TimeSpan.FromMinutes(30) : TimeSpan.FromMinutes(1));
                    if (!notFound) _mpErrors++;
                    if (set.Missing.Count < 4000) set.Missing.Enqueue(MapPhotoName(k));
                }
            }
        }
    }

    /// <summary>Champ de liste borné (sortie de la DLL : 8000 caractères au plus), « - » si vide.</summary>
    private static string MapPhotoDrain(Queue<string> q, int maxChars)
    {
        var sb = new StringBuilder();
        while (q.Count > 0 && sb.Length + q.Peek().Length + 1 < maxChars)
        {
            if (sb.Length > 0) sb.Append(',');
            sb.Append(q.Dequeue());
        }
        return sb.Length > 0 ? sb.ToString() : "-";
    }

    private static string HandleMapPhoto(string function, string?[] args)
    {
        var inv = CultureInfo.InvariantCulture;
        switch (function)
        {
            case "MapPhoto.List":
            {
                lock (MapPhotoLock)
                {
                    var fresh = _mpList.Length > 0 && DateTime.UtcNow - _mpListAt < TimeSpan.FromMinutes(5);
                    if (!fresh && !_mpListLoading && !string.IsNullOrEmpty(_baseUrl))
                    {
                        _mpListLoading = true;
                        _ = Task.Run(async () =>
                        {
                            try
                            {
                                if (!TryBuildRequestUri(_baseUrl, "/api/atak/map-photo/list", out var uri, out _) || uri is null) return;
                                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(20));
                                var json = await MapPhotoHttp.Value.GetStringAsync(uri, cts.Token).ConfigureAwait(false);
                                using var doc = JsonDocument.Parse(json);
                                var rows = new List<string>();
                                foreach (var e in doc.RootElement.EnumerateArray())
                                {
                                    string S(string n) => e.TryGetProperty(n, out var v) ? (v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : v.GetRawText()) : "";
                                    rows.Add(string.Join(",", new[] { S("world"), S("mode"), S("maxZoom"), S("jpgTiles"), S("jpgBytes"), S("created") }
                                        .Select(x => x.Replace(",", " ").Replace(";", " ").Replace("|", " "))));
                                }
                                lock (MapPhotoLock)
                                {
                                    _mpList = rows.Count.ToString(inv) + "|" + (rows.Count > 0 ? string.Join(";", rows) : "-");
                                    _mpListAt = DateTime.UtcNow;
                                }
                            }
                            catch { /* hors ligne : liste précédente gardée */ }
                            finally { lock (MapPhotoLock) _mpListLoading = false; }
                        });
                    }
                    if (_mpList.Length > 0) return "OK|" + (_mpList.Length > MaxOutputBytes - 4 ? _mpList.Substring(0, MaxOutputBytes - 4) : _mpList);
                    return string.IsNullOrEmpty(_baseUrl) ? "ERR|not_connected" : "OK|pending";
                }
            }

            case "MapPhoto.Meta":
            {
                var set = MapPhotoGet(args.Length > 0 ? args[0] : "", args.Length > 1 ? args[1] : "sat");
                if (set is null) return "ERR|args";
                lock (MapPhotoLock)
                {
                    // Une fois par séance (puis toutes les 30 s tant qu'il échoue) : meta.json et index du site.
                    var connected = !string.IsNullOrEmpty(_baseUrl);
                    if (connected && !set.Refreshed && !set.MetaLoading && DateTime.UtcNow - set.MetaTried > TimeSpan.FromSeconds(30))
                    {
                        set.MetaLoading = true;
                        set.MetaTried = DateTime.UtcNow;
                        _ = Task.Run(() => MapPhotoLoadMeta(set));
                    }
                    if (set.MetaReady)
                    {
                        return "OK|" + MapPhotoNum(set.Extent) + "|" + set.TileSize.ToString(inv) + "|" + set.MinZoom.ToString(inv) + "|"
                            + set.MaxZoom.ToString(inv) + "|.jpg|" + set.Dir.Replace('\\', '/').TrimEnd('/') + "/|"
                            + set.JpgTiles.ToString(inv) + "|" + set.JpgBytes.ToString(inv) + "|" + set.Cached.Count.ToString(inv) + "|"
                            + (set.Created.Length > 0 ? set.Created.Replace("|", " ") : "-");
                    }
                    // Cache du disque pas encore relu (fond peut-être déjà là, hors ligne compris) : patienter.
                    if (!set.ScanDone) return "OK|pending";
                    if (set.None) return "ERR|none";
                    if (!connected) return "ERR|not_connected";
                    return "OK|pending";
                }
            }

            case "MapPhoto.Want":
            {
                var set = MapPhotoGet(args.Length > 0 ? args[0] : "", args.Length > 1 ? args[1] : "sat");
                if (set is null || args.Length < 3) return "ERR|args";
                var have = new List<string>();
                var absent = new List<string>();
                var queued = 0;
                var connected = !string.IsNullOrEmpty(_baseUrl);
                lock (MapPhotoLock)
                {
                    var now = DateTime.UtcNow;
                    foreach (var item in (args[2] ?? "").Split(','))
                    {
                        if (!MapPhotoParse(item, out var k)) continue;
                        var name = MapPhotoName(k);
                        if (set.Cached.Contains(k)) { have.Add(name); continue; }
                        if (!connected || (int)(k >> 48) > set.MaxZoom || (set.Index != null && !set.Index.Contains(k))
                            || (set.Backoff.TryGetValue(k, out var until) && until > now))
                        {
                            absent.Add(name);
                            continue;
                        }
                        // En cours de téléchargement : arrivera par MapPhoto.Status.
                        if (MapPhotoInFlight.Contains((set, k))) continue;
                        // Déjà en file : remonte devant (l'écran regarde là maintenant).
                        if (MapPhotoQueued.Contains((set, k)))
                        {
                            var node = MapPhotoWantQ.Find((set, k));
                            if (node != null) { MapPhotoWantQ.Remove(node); MapPhotoWantQ.AddFirst(node); }
                            continue;
                        }
                        MapPhotoQueued.Add((set, k));
                        MapPhotoWantQ.AddFirst((set, k));
                        queued++;
                    }
                    // File bornée : les plus vieilles demandes (écran parti ailleurs) sont oubliées.
                    while (MapPhotoWantQ.Count > 400 && MapPhotoWantQ.Last is { } last)
                    {
                        MapPhotoWantQ.RemoveLast();
                        MapPhotoQueued.Remove(last.Value);
                    }
                    if (queued > 0) MapPhotoStartPump();
                }
                var hs = string.Join(",", have);
                var abs = string.Join(",", absent);
                if (hs.Length > 3800) hs = hs.Substring(0, hs.LastIndexOf(',', 3800));
                if (abs.Length > 3800) abs = abs.Substring(0, abs.LastIndexOf(',', 3800));
                return "OK|" + (hs.Length > 0 ? hs : "-") + "|" + (abs.Length > 0 ? abs : "-") + "|" + queued.ToString(inv);
            }

            case "MapPhoto.Status":
            {
                var set = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? MapPhotoGet(args[0], args.Length > 1 ? args[1] : "sat") : null;
                lock (MapPhotoLock)
                {
                    if (_mpDlState == "running" && _mpDlPos >= _mpDlTodo.Count && Volatile.Read(ref _mpActive) == 0) _mpDlState = "done";
                    var head = "OK|" + MapPhotoWantQ.Count.ToString(inv) + "|" + _mpDownloaded.ToString(inv) + "|" + _mpBytes.ToString(inv) + "|"
                        + _mpErrors.ToString(inv) + "|" + Volatile.Read(ref _mpActive).ToString(inv) + "|"
                        + (_mpDlSet != null && (set == null || _mpDlSet == set) ? _mpDlState : "idle") + "|"
                        + _mpDlDone.ToString(inv) + "|" + _mpDlTotal.ToString(inv) + "|"
                        + (set?.Cached.Count ?? 0).ToString(inv) + "|" + (set?.CachedBytes ?? 0).ToString(inv) + "|";
                    if (set == null) return head + "-|-";
                    var room = MaxOutputBytes - head.Length - 16;
                    var arrived = MapPhotoDrain(set.Arrived, room * 2 / 3);
                    var missing = MapPhotoDrain(set.Missing, room - arrived.Length);
                    return head + arrived + "|" + missing;
                }
            }

            case "MapPhoto.Download":
            {
                var set = MapPhotoGet(args.Length > 0 ? args[0] : "", args.Length > 1 ? args[1] : "sat");
                if (set is null) return "ERR|args";
                if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
                lock (MapPhotoLock)
                {
                    if (!set.MetaReady) return "ERR|no_meta";
                    var zMax = set.MaxZoom;
                    if (args.Length > 2 && int.TryParse(args[2], NumberStyles.Integer, inv, out var zq)) zMax = Math.Clamp(zq, set.MinZoom, set.MaxZoom);
                    var all = new List<long>();
                    if (set.Index != null)
                    {
                        all.AddRange(set.Index.Where(k => (int)(k >> 48) <= zMax));
                    }
                    else
                    {
                        // Sans index (site plus ancien) : toute la grille, bornée à 350 000 tuiles (niveau 9).
                        zMax = Math.Min(zMax, 9);
                        for (var z = set.MinZoom; z <= zMax; z++)
                            for (var x = 0; x < (1 << z); x++)
                                for (var y = 0; y < (1 << z); y++) all.Add(MapPhotoKey(z, x, y));
                    }
                    all.Sort(); // niveau par niveau : la vue d'ensemble arrive d'abord
                    _mpDlSet = set;
                    _mpDlTotal = all.Count;
                    _mpDlTodo = all.Where(k => !set.Cached.Contains(k)).ToList();
                    _mpDlDone = _mpDlTotal - _mpDlTodo.Count;
                    _mpDlPos = 0;
                    _mpDlState = _mpDlTodo.Count > 0 ? "running" : "done";
                    // Nouvelle passe : les erreurs récentes sont retentées.
                    set.Backoff.Clear();
                    if (_mpDlTodo.Count > 0) MapPhotoStartPump();
                    return "OK|" + _mpDlTodo.Count.ToString(inv) + "|" + _mpDlTotal.ToString(inv);
                }
            }

            case "MapPhoto.Cancel":
            {
                lock (MapPhotoLock)
                {
                    MapPhotoWantQ.Clear();
                    MapPhotoQueued.Clear();
                    if (_mpDlState == "running") _mpDlState = "cancelled";
                    _mpDlTodo = new List<long>();
                    _mpDlPos = 0;
                }
                return "OK";
            }

            case "MapPhoto.Clear":
            {
                var w = MapPhotoSlug(args.Length > 0 ? args[0] : "");
                if (w.Length == 0) return "ERR|args";
                lock (MapPhotoLock)
                {
                    foreach (var set in MapPhotoSets.Values.Where(s => s.World == w).ToList())
                    {
                        if (_mpDlSet == set)
                        {
                            if (_mpDlState == "running") _mpDlState = "cancelled";
                            _mpDlTodo = new List<long>();
                            _mpDlPos = 0;
                            _mpDlDone = 0;
                        }
                        for (var n = MapPhotoWantQ.First; n != null;)
                        {
                            var next = n.Next;
                            if (n.Value.Set == set) { MapPhotoWantQ.Remove(n); MapPhotoQueued.Remove(n.Value); }
                            n = next;
                        }
                    }
                }
                // Tuiles effacées, meta.json et index gardés en mémoire (le fond reste affichable, retéléchargé à la demande).
                foreach (var mode in new[] { "sat", "plan" })
                {
                    var set = MapPhotoGet(w, mode);
                    if (set != null) MapPhotoWipe(set, false);
                }
                return "OK|clearing";
            }
        }
        return "ERR|unknown";
    }
}
