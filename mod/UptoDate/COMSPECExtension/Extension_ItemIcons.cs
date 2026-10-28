using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

/// <summary>
/// Icônes des objets portés (« Item.Icons », 2.0.88) pour Équipement des joueurs et Kits par rôle d'Athena.
/// Le SQF donne [classe, chemin picture] ; la DLL demande au site les classes sans icône
/// (POST /api/atak/item-icons/missing), retrouve le .paa dans le PBO qui porte ce préfixe (Arma 3, DLC,
/// mods locaux, !Workshop, Workshop Steam, -mod=), garde le seul mipmap utile (512 px au plus) et l'envoie
/// (POST /api/atak/item-icons) ; le site le convertit en PNG. Une classe n'est traitée qu'une fois par session,
/// un envoi à la fois, 300 icônes au plus par session : rien ne repart en boucle.
/// </summary>
public static partial class Extension
{
    private const int ItemIconMaxSide = 512;
    private const int ItemIconMaxBytes = 1024 * 1024;
    private const int ItemIconMaxPerSession = 300;

    private static readonly ConcurrentQueue<(string Class, string Picture)> ItemIconQueue = new();
    private static readonly ConcurrentDictionary<string, byte> ItemIconSeen = new(StringComparer.OrdinalIgnoreCase);
    private static int _itemIconWorker;
    private static int _itemIconSent;
    private static int _itemIconNotFound;
    private static int _itemIconFailed;
    private static Dictionary<string, string>? _pboByPrefix;

    private static string HandleItemIcons(string?[] args)
    {
        if (args.Length < 1 || string.IsNullOrWhiteSpace(args[0])) return "ERR|payload_empty";
        if (args[0] == "status")
            return "OK|sent=" + _itemIconSent + ",not_found=" + _itemIconNotFound + ",failed=" + _itemIconFailed
                + ",queued=" + ItemIconQueue.Count + ",pbo=" + (_pboByPrefix?.Count ?? -1);
        if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
        if (!HasPortalAuth()) return "ERR|unauthorized";
        var json = NormalizeArmaJson(args[0]);
        if (json.Length > 60000) return "ERR|payload_too_large";
        var added = 0;
        try
        {
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("items", out var items) || items.ValueKind != JsonValueKind.Array)
                return "ERR|payload_invalid";
            foreach (var it in items.EnumerateArray())
            {
                if (it.ValueKind != JsonValueKind.Array || it.GetArrayLength() < 2) continue;
                var cls = it[0].GetString() ?? "";
                var pic = it[1].GetString() ?? "";
                if (cls.Length == 0 || cls.Length > 128 || pic.Length == 0 || pic.Length > 255) continue;
                if (!ItemIconSeen.TryAdd(cls, 0)) continue;
                if (ItemIconSeen.Count > 2000) break;
                ItemIconQueue.Enqueue((cls, pic));
                added++;
            }
        }
        catch
        {
            return "ERR|payload_invalid";
        }
        if (added > 0 && Interlocked.CompareExchange(ref _itemIconWorker, 1, 0) == 0)
        {
            var t = new Thread(ItemIconWorker) { IsBackground = true, Name = "COMSPEC item icons", Priority = ThreadPriority.BelowNormal };
            t.Start();
        }
        return "OK|queued";
    }

    private static void ItemIconWorker()
    {
        try
        {
            while (!ItemIconQueue.IsEmpty)
            {
                var batch = new List<(string Class, string Picture)>();
                while (batch.Count < 100 && ItemIconQueue.TryDequeue(out var e)) batch.Add(e);
                if (batch.Count == 0) break;
                if (_itemIconSent + _itemIconNotFound + _itemIconFailed >= ItemIconMaxPerSession) continue;
                // Le site a la priorité : en surcharge ou hors ligne, ces icônes attendront une prochaine partie.
                if (IsRateLimitedNow() || IsNetworkBackoffNow()) continue;

                var missing = ItemIconAskMissing(batch);
                if (missing.Count == 0) continue;
                _pboByPrefix ??= BuildPboPrefixIndex();
                foreach (var (cls, pic) in batch)
                {
                    if (!missing.Contains(cls)) continue;
                    if (_itemIconSent + _itemIconNotFound + _itemIconFailed >= ItemIconMaxPerSession) break;
                    byte[]? paa = null;
                    try { paa = ReadPictureFromPbos(pic); } catch { /* fichier illisible : signalé introuvable */ }
                    if (paa != null) paa = TrimPaaToOneMip(paa, ItemIconMaxSide);
                    var ok = ItemIconUpload(cls, pic, paa);
                    if (!ok) _itemIconFailed++;
                    else if (paa == null) _itemIconNotFound++;
                    else _itemIconSent++;
                    Thread.Sleep(250);
                }
            }
        }
        catch
        {
            _itemIconFailed++;
        }
        finally
        {
            Interlocked.Exchange(ref _itemIconWorker, 0);
            // Une entrée arrivée pendant la sortie relance le fil.
            if (!ItemIconQueue.IsEmpty && Interlocked.CompareExchange(ref _itemIconWorker, 1, 0) == 0)
                new Thread(ItemIconWorker) { IsBackground = true, Name = "COMSPEC item icons", Priority = ThreadPriority.BelowNormal }.Start();
        }
    }

    private static HashSet<string> ItemIconAskMissing(List<(string Class, string Picture)> batch)
    {
        var set = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        using var ms = new MemoryStream();
        using (var w = new Utf8JsonWriter(ms))
        {
            w.WriteStartObject();
            w.WriteStartArray("classes");
            foreach (var (cls, _) in batch) w.WriteStringValue(cls);
            w.WriteEndArray();
            w.WriteEndObject();
        }
        var body = ItemIconPost("/api/atak/item-icons/missing", Encoding.UTF8.GetString(ms.ToArray()));
        if (body == null) return set;
        try
        {
            using var doc = JsonDocument.Parse(body);
            if (doc.RootElement.TryGetProperty("missing", out var arr) && arr.ValueKind == JsonValueKind.Array)
                foreach (var m in arr.EnumerateArray())
                    if (m.ValueKind == JsonValueKind.String) set.Add(m.GetString() ?? "");
        }
        catch { /* réponse illisible : rien à envoyer */ }
        return set;
    }

    private static bool ItemIconUpload(string cls, string picture, byte[]? paa)
    {
        using var ms = new MemoryStream();
        using (var w = new Utf8JsonWriter(ms))
        {
            w.WriteStartObject();
            w.WriteString("class", cls);
            w.WriteString("picture", picture);
            if (paa != null) w.WriteString("paa", Convert.ToBase64String(paa));
            else w.WriteString("status", "not_found");
            w.WriteEndObject();
        }
        return ItemIconPost("/api/atak/item-icons", Encoding.UTF8.GetString(ms.ToArray())) != null;
    }

    /// <summary>POST synchrone (fil d'arrière-plan, jamais le fil du jeu) ; corps de réponse si 2xx, sinon null.</summary>
    private static string? ItemIconPost(string path, string json)
    {
        var baseUrl = _baseUrl;
        if (string.IsNullOrEmpty(baseUrl)) return null;
        try
        {
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(30));
            using var req = new HttpRequestMessage(HttpMethod.Post, baseUrl + path) { Content = JsonContent(EnrichAtakPayload(json)) };
            AttachApiKeyHeader(req);
            using var resp = SendTimed(req, cts.Token);
            if (!resp.IsSuccessStatusCode) return null;
            return ReadContentUtf8(resp, cts.Token);
        }
        catch
        {
            return null;
        }
    }

    // ── PBO ────────────────────────────────────────────────────────────────

    /// <summary>Préfixe (minuscules, « \ ») → chemin du PBO, pour tous les dossiers d'addons connus de ce PC.</summary>
    private static Dictionary<string, string> BuildPboPrefixIndex()
    {
        var index = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        var addonDirs = new List<string>();
        void AddModRoot(string root)
        {
            foreach (var sub in new[] { "addons", "Addons" })
            {
                var d = Path.Combine(root, sub);
                if (Directory.Exists(d) && !addonDirs.Contains(d, StringComparer.OrdinalIgnoreCase)) { addonDirs.Add(d); return; }
            }
        }
        void AddChildren(string parent)
        {
            if (!Directory.Exists(parent)) return;
            try { foreach (var d in Directory.EnumerateDirectories(parent)) AddModRoot(d); } catch { /* droits */ }
        }

        string armaDir = "";
        try { armaDir = Path.GetDirectoryName(Process.GetCurrentProcess().MainModule?.FileName ?? "") ?? ""; } catch { /* ignore */ }
        if (armaDir.Length > 0)
        {
            AddModRoot(armaDir);
            AddChildren(armaDir);                                   // DLC officiels, @mods locaux
            AddChildren(Path.Combine(armaDir, "!Workshop"));        // liens du lanceur
            AddChildren(Path.GetFullPath(Path.Combine(armaDir, "..", "..", "workshop", "content", "107410")));
        }
        try
        {
            foreach (var arg in Environment.GetCommandLineArgs())
            {
                var a = arg.Trim('"');
                var eq = a.IndexOf('=');
                if (eq < 0) continue;
                var key = a[..eq].ToLowerInvariant();
                if (key != "-mod" && key != "-servermod") continue;
                foreach (var part in a[(eq + 1)..].Split(';', StringSplitOptions.RemoveEmptyEntries))
                {
                    var p = part.Trim().Trim('"');
                    if (p.Length == 0) continue;
                    AddModRoot(Path.IsPathRooted(p) || armaDir.Length == 0 ? p : Path.Combine(armaDir, p));
                }
            }
        }
        catch { /* ignore */ }

        foreach (var dir in addonDirs)
        {
            IEnumerable<string> pbos;
            try { pbos = Directory.EnumerateFiles(dir, "*.pbo"); } catch { continue; }
            foreach (var pbo in pbos)
            {
                var prefix = ReadPboPrefix(pbo) ?? Path.GetFileNameWithoutExtension(pbo);
                prefix = NormalizeGamePath(prefix);
                if (prefix.Length > 0) index.TryAdd(prefix, pbo);
            }
        }
        return index;
    }

    private static string NormalizeGamePath(string p) => p.Replace('/', '\\').Trim().Trim('\\').ToLowerInvariant();

    private static string ReadCString(BinaryReader r, int max = 1024)
    {
        var sb = new StringBuilder();
        for (var i = 0; i < max; i++)
        {
            var b = r.ReadByte();
            if (b == 0) return sb.ToString();
            sb.Append((char)b);
        }
        throw new InvalidDataException("pbo_string");
    }

    /// <summary>Lit seulement l'en-tête d'extension (« Vers ») du PBO : quelques centaines d'octets.</summary>
    private static string? ReadPboPrefix(string pbo)
    {
        try
        {
            using var fs = new FileStream(pbo, FileMode.Open, FileAccess.Read, FileShare.ReadWrite, 4096);
            using var r = new BinaryReader(fs);
            var name = ReadCString(r);
            var mime = r.ReadUInt32();
            r.ReadBytes(16);
            if (name.Length != 0 || mime != 0x56657273) return null;
            for (var i = 0; i < 64; i++)
            {
                var k = ReadCString(r);
                if (k.Length == 0) break;
                var v = ReadCString(r);
                if (k.Equals("prefix", StringComparison.OrdinalIgnoreCase)) return v;
            }
        }
        catch { /* PBO illisible ou protégé */ }
        return null;
    }

    /// <summary>Contenu d'un fichier du jeu (« \a3\…\x.paa ») lu dans le PBO dont le préfixe est le plus long.</summary>
    private static byte[]? ReadPictureFromPbos(string picture)
    {
        var path = NormalizeGamePath(picture);
        if (Path.GetExtension(path).Length == 0) path += ".paa";
        if (!path.EndsWith(".paa", StringComparison.Ordinal)) return null;
        var index = _pboByPrefix;
        if (index == null) return null;
        var cut = path.Length;
        while ((cut = path.LastIndexOf('\\', cut - 1)) > 0)
        {
            if (index.TryGetValue(path[..cut], out var pbo))
            {
                var data = ReadPboEntry(pbo, path[(cut + 1)..]);
                if (data != null) return data;
            }
        }
        return null;
    }

    private static byte[]? ReadPboEntry(string pbo, string entry)
    {
        using var fs = new FileStream(pbo, FileMode.Open, FileAccess.Read, FileShare.ReadWrite, 65536);
        using var r = new BinaryReader(fs);
        long dataOffset = 0;
        long found = -1;
        uint foundSize = 0, foundMethod = 0;
        for (var i = 0; i < 200000; i++)
        {
            var name = ReadCString(r);
            var method = r.ReadUInt32();
            r.ReadUInt32(); // taille d'origine
            r.ReadUInt32();
            r.ReadUInt32(); // horodatage
            var size = r.ReadUInt32();
            if (name.Length == 0)
            {
                if (method == 0x56657273)
                {
                    for (var k = 0; k < 64 && ReadCString(r).Length > 0; k++) ReadCString(r);
                    continue;
                }
                break; // fin de l'en-tête
            }
            if (found < 0 && name.Replace('/', '\\').Equals(entry, StringComparison.OrdinalIgnoreCase))
            {
                found = dataOffset;
                foundSize = size;
                foundMethod = method;
            }
            dataOffset += size;
        }
        if (found < 0 || foundSize == 0 || foundSize > 64 * 1024 * 1024) return null;
        if (foundMethod == 0x43707273) return null; // compressé (rare pour un .paa) : non géré
        fs.Seek(fs.Position + found, SeekOrigin.Begin);
        var buf = r.ReadBytes((int)foundSize);
        return buf.Length == foundSize ? buf : null;
    }

    /// <summary>
    /// PAA réduit à son premier mipmap tenant dans maxSide (sinon le plus petit) : en-tête de type, palette vide,
    /// ce mipmap tel quel (LZO compris) et la fin de liste. Le site décode ce format ; balises TAGG non utiles.
    /// </summary>
    private static byte[]? TrimPaaToOneMip(byte[] paa, int maxSide)
    {
        try
        {
            var p = 2;
            for (var g = 0; g < 32 && p + 12 <= paa.Length && paa[p] == 'G' && paa[p + 1] == 'G' && paa[p + 2] == 'A' && paa[p + 3] == 'T'; g++)
                p += 12 + (int)BitConverter.ToUInt32(paa, p + 8);
            if (p + 2 > paa.Length) return null;
            p += 2 + BitConverter.ToUInt16(paa, p) * 3;
            int pick = -1, pickLen = 0;
            for (var g = 0; g < 16 && p + 7 <= paa.Length; g++)
            {
                var w = BitConverter.ToUInt16(paa, p) & 0x7FFF;
                var h = BitConverter.ToUInt16(paa, p + 2);
                if (w == 0 || h == 0) break;
                var size = paa[p + 4] | (paa[p + 5] << 8) | (paa[p + 6] << 16);
                if (p + 7 + size > paa.Length) break;
                pick = p;
                pickLen = 7 + size;
                if (w <= maxSide && h <= maxSide) break;
                p += 7 + size;
            }
            if (pick < 0 || pickLen + 10 > ItemIconMaxBytes) return null;
            var outBuf = new byte[2 + 2 + pickLen + 6];
            outBuf[0] = paa[0];
            outBuf[1] = paa[1];
            Buffer.BlockCopy(paa, pick, outBuf, 4, pickLen);
            return outBuf;
        }
        catch
        {
            return null;
        }
    }
}
