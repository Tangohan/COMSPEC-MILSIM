using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;

namespace COMSPECExtension;

/// <summary>
/// Capture de la carte (fn_mapCapture de l'ATAK natif, 2.0.91) : chaque tuile cmap_*.png écrite par le moteur dans
/// Screenshots est vérifiée, corrigée (voile de l'air retiré, couleurs, MapTileImage), compressée en JPEG (1 à 2 Mo
/// au lieu de 20 Mo de PNG 16 bits) et rangée dans Documents\COMSPEC_MapCapture\&lt;carte&gt;\&lt;rendu&gt;_h&lt;hauteur&gt;\.
/// Réglage de la correction : médiane des mesures des 15 premières tuiles d'une hauteur, puis figé et gardé à côté des
/// tuiles (correction.txt) : toutes les tuiles d'une hauteur reçoivent la même, d'une séance à l'autre.
///   - le moteur n'écrit plus rien une fois le dossier Screenshots plein (maxScreenShotFolderSizeMB) et ne le dit pas :
///     une case n'est comptée prise que si son image existe vraiment (avant : 3945 cases « prises », 175 images) ;
///   - rangées hors de Screenshots, les tuiles ne remplissent plus ce dossier (une tuile 2560 × 1440 pèse 15 à 25 Mo).
/// Commandes :
///   MapCap.Collect [fichier, carte, rendu, hauteur, case, garder le PNG (0 | 1)] → OK|queued (vérification, correction
///     et rangement en tâche de fond ; vue plan : PNG gardé tel quel, sans correction)
///   MapCap.Poll → OK|&lt;cases rangées, virgules, ou -&gt;|&lt;cases sans image, ou -&gt;|&lt;en attente&gt;|&lt;Go libres&gt;
///   MapCap.Scan [carte] → OK|&lt;nombre de morceaux&gt; : relit les tuiles de la carte sur le disque (dossier de capture et
///     Screenshots, sous-dossiers compris ; celles de Screenshots sont rangées au passage)
///   MapCap.Chunk [n] → morceau n du relevé : lignes « rendu|hauteur|pas|case,case… »
///   MapCap.Dir → OK|&lt;dossier de capture&gt;
/// </summary>
public static partial class Extension
{
    private static readonly Regex MapCapName = new(
        @"^cmap_(?<world>.+?)_(?<mode>sat|3d|plan)_h(?<h>\d+)_s(?<step>\d+)_w(?<w>\d+)_o(?<ox>-?\d+)-(?<oy>-?\d+)_x(?<ix>\d+)_y(?<iy>\d+)\.(png|jpg)$",
        RegexOptions.IgnoreCase | RegexOptions.Compiled);

    private static readonly ConcurrentQueue<string> MapCapDone = new();
    private static readonly ConcurrentQueue<string> MapCapFailed = new();
    private static int _mapCapPending;
    private static List<string> _mapCapChunks = new();
    private static string? _mapCapShotDir;
    private static readonly Dictionary<string, List<float[]>> MapCapGrades = new();
    private const int MapCapGradeSamples = 15;
    // Deux tuiles traitées à la fois au plus (environ 60 Mo de mémoire chacune) : le jeu garde le processeur.
    private static readonly SemaphoreSlim MapCapWork = new(2, 2);

    /// <summary>Réglage de correction d'une couche : figé (fichier) ou médiane des tuiles déjà vues, la tuile comprise.</summary>
    private static float[] MapCapGrade(string dir, float[] tile)
    {
        lock (MapCapGrades)
        {
            if (!MapCapGrades.TryGetValue(dir, out var list))
            {
                list = new List<float[]>();
                try
                {
                    var f = Path.Combine(dir, "correction.txt");
                    if (File.Exists(f))
                    {
                        var v = File.ReadAllText(f).Split(';').Select(x => float.Parse(x, System.Globalization.CultureInfo.InvariantCulture)).ToArray();
                        if (v.Length == 5) for (var i = 0; i < MapCapGradeSamples; i++) list.Add(v);
                    }
                }
                catch { list.Clear(); }
                MapCapGrades[dir] = list;
            }
            if (list.Count < MapCapGradeSamples)
            {
                list.Add(tile);
                if (list.Count == MapCapGradeSamples)
                {
                    try
                    {
                        Directory.CreateDirectory(dir);
                        File.WriteAllText(Path.Combine(dir, "correction.txt"), string.Join(";", Median(list).Select(x => x.ToString(System.Globalization.CultureInfo.InvariantCulture))));
                    }
                    catch { /* gardé en mémoire */ }
                }
            }
            return Median(list);
        }
        static float[] Median(List<float[]> l)
        {
            var m = new float[5];
            for (var k = 0; k < 5; k++)
            {
                var v = l.Select(a => a[k]).OrderBy(z => z).ToList();
                m[k] = v[v.Count / 2];
            }
            return m;
        }
    }

    /// <summary>Tuile du moteur → JPEG corrigé dans le dossier de capture (PNG d'origine gardé à côté sur demande).
    /// Format non lu (ou vue plan) : le PNG est rangé tel quel.</summary>
    private static bool MapCapStore(string src, string world, string mode, string h, string file, bool keepPng)
    {
        var dst = MapCapTarget(world, mode, h, file);
        if (mode.Equals("plan", StringComparison.OrdinalIgnoreCase)) return MapCapMove(src, dst);
        MapCapWork.Wait();
        try
        {
            var rgb = MapTileImage.ReadPng(src, out var w, out var hh);
            if (rgb == null) return MapCapMove(src, dst);
            var grade = MapCapGrade(Path.GetDirectoryName(dst)!, MapTileImage.Stats(rgb));
            MapTileImage.Grade(rgb, grade, 1.12f);
            var jpg = Path.ChangeExtension(dst, ".jpg");
            Directory.CreateDirectory(Path.GetDirectoryName(jpg)!);
            var tmp = jpg + ".part";
            MapTileImage.WriteJpeg(tmp, rgb, w, hh, 88);
            if (File.Exists(jpg)) File.Delete(jpg);
            File.Move(tmp, jpg);
            if (keepPng) MapCapMove(src, Path.Combine(Path.GetDirectoryName(dst)!, "png", file));
            else File.Delete(src);
            return true;
        }
        finally { MapCapWork.Release(); }
    }

    private static bool IsMapCaptureName(string? pathOrName)
    {
        try { return (Path.GetFileName(pathOrName ?? "") ?? "").StartsWith("cmap_", StringComparison.OrdinalIgnoreCase); }
        catch { return false; }
    }

    private static string MapCapRoot()
    {
        var docs = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);
        if (string.IsNullOrWhiteSpace(docs)) docs = AppContext.BaseDirectory;
        return Path.Combine(docs, "COMSPEC_MapCapture");
    }

    private static string MapCapSafe(string s)
    {
        var bad = Path.GetInvalidFileNameChars();
        var sb = new StringBuilder();
        foreach (var c in s) sb.Append(bad.Contains(c) ? '_' : c);
        return sb.Length == 0 ? "_" : sb.ToString();
    }

    private static string MapCapTarget(string world, string mode, string h, string fileName) =>
        Path.Combine(MapCapRoot(), MapCapSafe(world), MapCapSafe(mode.ToLowerInvariant() + "_h" + h), fileName);

    private static double MapCapFreeGb()
    {
        try
        {
            var root = Path.GetPathRoot(MapCapRoot());
            if (string.IsNullOrEmpty(root)) return -1;
            return Math.Round(new DriveInfo(root).AvailableFreeSpace / 1073741824.0, 1);
        }
        catch { return -1; }
    }

    /// <summary>Fichier écrit par le moteur : dans le dernier dossier Screenshots où une tuile a été trouvée, sinon partout.</summary>
    private static string? MapCapFind(string fileName)
    {
        var last = _mapCapShotDir;
        if (last != null)
        {
            var p = Path.Combine(last, fileName);
            if (File.Exists(p)) return p;
        }
        foreach (var dir in EnumerateScreenshotDirs())
        {
            try
            {
                var p = Path.Combine(dir, fileName);
                if (File.Exists(p)) { _mapCapShotDir = dir; return p; }
            }
            catch { /* dossier inaccessible */ }
        }
        return null;
    }

    private static bool MapCapMove(string src, string dst)
    {
        Directory.CreateDirectory(Path.GetDirectoryName(dst)!);
        if (File.Exists(dst)) File.Delete(dst);
        File.Move(src, dst);
        return true;
    }

    private static string HandleMapCapture(string function, string?[] args)
    {
        switch (function)
        {
            case "MapCap.Dir":
                return "OK|" + MapCapRoot();

            case "MapCap.Collect":
            {
                if (args.Length < 5) return "ERR|args";
                var file = Path.GetFileName(args[0] ?? "");
                var world = args[1] ?? "";
                var mode = args[2] ?? "";
                var h = args[3] ?? "";
                var id = args[4] ?? "";
                var keepPng = args.Length > 5 && args[5] == "1";
                if (!IsMapCaptureName(file) || world.Length == 0) return "ERR|args";
                Interlocked.Increment(ref _mapCapPending);
                _ = Task.Run(async () =>
                {
                    var ok = false;
                    try
                    {
                        // Le moteur écrit l'image en fin d'image (PNG de 15 à 25 Mo) : jusqu'à 30 s pour la voir, stable.
                        var until = DateTime.UtcNow.AddSeconds(30);
                        string? src = null;
                        while (DateTime.UtcNow < until)
                        {
                            src ??= MapCapFind(file);
                            if (src != null && await WaitFileStableAsync(src, TimeSpan.FromSeconds(4)).ConfigureAwait(false))
                            {
                                var fi = new FileInfo(src);
                                if (fi.Exists && fi.Length > 1024)
                                {
                                    ok = MapCapStore(src, world, mode, h, file, keepPng);
                                    break;
                                }
                            }
                            await Task.Delay(400).ConfigureAwait(false);
                        }
                    }
                    catch { ok = false; }
                    finally
                    {
                        (ok ? MapCapDone : MapCapFailed).Enqueue(id);
                        Interlocked.Decrement(ref _mapCapPending);
                    }
                });
                return "OK|queued";
            }

            case "MapCap.Poll":
            {
                var done = new List<string>();
                var failed = new List<string>();
                var len = 0;
                while (len < 3000 && MapCapDone.TryDequeue(out var d)) { done.Add(d); len += d.Length + 1; }
                while (len < 6000 && MapCapFailed.TryDequeue(out var f)) { failed.Add(f); len += f.Length + 1; }
                // Champ vide : « - » (le SQF découpe par splitString, qui saute les champs vides).
                return "OK|" + (done.Count > 0 ? string.Join(",", done) : "-") + "|" + (failed.Count > 0 ? string.Join(",", failed) : "-") + "|"
                    + Volatile.Read(ref _mapCapPending) + "|"
                    + MapCapFreeGb().ToString(System.Globalization.CultureInfo.InvariantCulture);
            }

            case "MapCap.Scan":
            {
                if (args.Length < 1 || string.IsNullOrWhiteSpace(args[0])) return "ERR|args";
                var world = args[0]!;
                // rendu|hauteur|pas -> cases (colonne × 10000 + ligne dans la grille calée sur les multiples du pas)
                var layers = new Dictionary<string, HashSet<long>>(StringComparer.OrdinalIgnoreCase);
                void Add(string path, bool fromShots)
                {
                    var name = Path.GetFileName(path);
                    var m = MapCapName.Match(name);
                    if (!m.Success || !string.Equals(m.Groups["world"].Value, world, StringComparison.OrdinalIgnoreCase)) return;
                    try { if (new FileInfo(path).Length <= 1024) return; } catch { return; }
                    var mode = m.Groups["mode"].Value.ToLowerInvariant();
                    var h = m.Groups["h"].Value;
                    var step = long.Parse(m.Groups["step"].Value);
                    if (step <= 0) return;
                    var gx = (long)Math.Round(long.Parse(m.Groups["ox"].Value) / (double)step) + long.Parse(m.Groups["ix"].Value);
                    var gy = (long)Math.Round(long.Parse(m.Groups["oy"].Value) / (double)step) + long.Parse(m.Groups["iy"].Value);
                    var key = mode + "|" + h + "|" + step;
                    if (!layers.TryGetValue(key, out var set)) layers[key] = set = new HashSet<long>();
                    set.Add(gx * 10000 + gy);
                    // Tuile restée dans Screenshots (ancienne capture, ou triée à la main) : rangée (corrigée et compressée)
                    // en tâche de fond, sans bloquer le jeu ; le dossier se vide.
                    if (fromShots)
                    {
                        _ = Task.Run(() => { try { MapCapStore(path, world, mode, h, name, false); } catch { /* fichier ouvert : laissé */ } });
                    }
                }
                var root = Path.Combine(MapCapRoot(), MapCapSafe(world));
                if (Directory.Exists(root))
                    foreach (var f in Directory.EnumerateFiles(root, "cmap_*", SearchOption.AllDirectories))
                        if (!f.EndsWith(".part", StringComparison.OrdinalIgnoreCase)) Add(f, false);
                foreach (var dir in EnumerateScreenshotDirs())
                {
                    try
                    {
                        // Sous-dossiers sur deux niveaux (tri à la main, ex. Screenshots\300) ; jamais plus profond.
                        var dirs = new List<string> { dir };
                        foreach (var s1 in Directory.EnumerateDirectories(dir))
                        {
                            dirs.Add(s1);
                            foreach (var s2 in Directory.EnumerateDirectories(s1)) dirs.Add(s2);
                        }
                        foreach (var d in dirs)
                            foreach (var f in Directory.EnumerateFiles(d, "cmap_" + world + "_*")) Add(f, true);
                    }
                    catch { /* dossier inaccessible */ }
                }
                var chunks = new List<string>();
                var sb = new StringBuilder();
                foreach (var (key, set) in layers)
                {
                    var ids = set.OrderBy(v => v).Select(v => v.ToString(System.Globalization.CultureInfo.InvariantCulture)).ToList();
                    var i = 0;
                    while (i < ids.Count)
                    {
                        var line = new StringBuilder(key + "|");
                        var first = true;
                        while (i < ids.Count && line.Length + ids[i].Length < 7000)
                        {
                            if (!first) line.Append(',');
                            line.Append(ids[i++]);
                            first = false;
                        }
                        if (sb.Length + line.Length + 1 > 7500) { chunks.Add(sb.ToString()); sb.Clear(); }
                        sb.Append(line).Append('\n');
                    }
                }
                if (sb.Length > 0) chunks.Add(sb.ToString());
                _mapCapChunks = chunks;
                return "OK|" + chunks.Count;
            }

            case "MapCap.Chunk":
            {
                var chunks = _mapCapChunks;
                if (args.Length < 1 || !int.TryParse(args[0], out var n) || n < 0 || n >= chunks.Count) return "ERR|range";
                return "OK|" + chunks[n];
            }
        }
        return "ERR|unknown";
    }
}
