using System.Collections.Concurrent;
using System.Globalization;

namespace COMSPECExtension;

/// <summary>
/// Transfert de fichiers par Bluetooth entre deux téléphones ATAK natifs (app Bluetooth) : photos et musiques.
/// Le fichier est lu par morceaux chez l'émetteur, passe par le réseau d'Arma (événements CBA), puis la DLL du
/// destinataire le réécrit dans son propre dossier. Aucune session Athena n'est nécessaire.
/// Commandes SQF (retour "OK|..." ou "ERR|...") :
///   BtFileOpen [kind, ref]          kind = photo (chemin complet d'une capture du poste) | music (nom dans le dossier
///                                   musique) → OK|poignée|nom|taille|morceaux
///   BtFileRead [poignée, n]         → OK|morceau n en base64 (3000 octets, 4000 caractères : l'appel Arma est borné)
///   BtFileClose [poignée]
///   BtRecvOpen [id, kind, nom, taille] → prépare la réception (fichier temporaire)
///   BtRecvWrite [id, n, base64]     → OK|octets reçus
///   BtRecvEnd [id]                  → OK|chemin final|nom final (photo : dossier COMSPEC des captures ;
///                                   musique : dossier COMSPEC_Music, sous-dossier Bluetooth)
///   BtRecvAbort [id]
/// </summary>
public static partial class Extension
{
    private const int BtChunk = 3000;
    private const long BtPhotoMax = 8L * 1024 * 1024;
    private const long BtMusicMax = 30L * 1024 * 1024;
    private static readonly string[] BtPhotoExtensions = [".jpg", ".jpeg", ".png"];

    private sealed class BtSend { public string Path = ""; public long Size; }
    private sealed class BtRecv { public string Temp = ""; public string Kind = ""; public string Name = ""; public long Size; public long Got; }

    private static readonly ConcurrentDictionary<string, BtSend> BtSends = new();
    private static readonly ConcurrentDictionary<string, BtRecv> BtRecvs = new();
    private static int _btSeq;

    private static string BtTempFolder()
    {
        var dir = Path.Combine(Path.GetTempPath(), "COMSPEC_BT");
        Directory.CreateDirectory(dir);
        // Réceptions abandonnées (jeu fermé pendant un transfert, erreur) : fichiers .part préalloués (jusqu'à 30 Mo)
        // de plus d'une heure supprimés, sauf ceux d'une réception en cours.
        try
        {
            var active = BtRecvs.Values.Select(v => v.Temp).ToHashSet(StringComparer.OrdinalIgnoreCase);
            foreach (var f in Directory.EnumerateFiles(dir, "*.part"))
            {
                if (active.Contains(f)) continue;
                try { if (DateTime.UtcNow - File.GetLastWriteTimeUtc(f) > TimeSpan.FromHours(1)) File.Delete(f); } catch { /* ignoré */ }
            }
        }
        catch { /* nettoyage facultatif */ }
        return dir;
    }

    // Nom de fichier reçu d'un autre joueur : jamais de dossier ni de caractère interdit.
    private static string BtSafeName(string name)
    {
        var n = Path.GetFileName(name.Replace('\\', '/').Split('/').Last());
        foreach (var c in Path.GetInvalidFileNameChars()) n = n.Replace(c, '_');
        n = n.Trim().TrimStart('.');
        if (n.Length > 120) n = n.Substring(n.Length - 120);
        return n.Length == 0 ? "fichier" : n;
    }

    private static bool BtUnder(string path, string dir)
    {
        var full = Path.GetFullPath(path);
        var root = Path.GetFullPath(dir).TrimEnd(Path.DirectorySeparatorChar) + Path.DirectorySeparatorChar;
        return full.StartsWith(root, StringComparison.OrdinalIgnoreCase);
    }

    // Une photo ne peut partir que d'un dossier de captures du poste (jamais un fichier quelconque du disque).
    private static bool BtPhotoAllowed(string path)
    {
        if (!BtPhotoExtensions.Contains(Path.GetExtension(path).ToLowerInvariant())) return false;
        var dirs = new List<string>();
        var primary = ComspecCaptureDir();
        if (!string.IsNullOrWhiteSpace(primary)) dirs.Add(primary);
        dirs.AddRange(EnumerateScreenshotDirs());
        return dirs.Any(d => BtUnder(path, d));
    }

    private static string BtUnique(string dir, string name)
    {
        var path = Path.Combine(dir, name);
        if (!File.Exists(path)) return path;
        var stem = Path.GetFileNameWithoutExtension(name);
        var ext = Path.GetExtension(name);
        for (var i = 2; i < 1000; i++)
        {
            var p = Path.Combine(dir, $"{stem} ({i}){ext}");
            if (!File.Exists(p)) return p;
        }
        return Path.Combine(dir, $"{stem}_{DateTime.UtcNow.Ticks}{ext}");
    }

    private static string BtCommand(string function, string?[] args)
    {
        string Arg(int i) => args.Length > i ? (args[i] ?? "").Trim().Trim('"') : "";
        long Num(int i) => long.TryParse(Arg(i), NumberStyles.Integer, CultureInfo.InvariantCulture, out var v) ? v : -1;
        try
        {
            switch (function)
            {
                case "BtFileOpen":
                {
                    var kind = Arg(0).ToLowerInvariant();
                    var reference = Arg(1);
                    if (reference.Length == 0) return "ERR|fichier vide";
                    string path;
                    long max;
                    if (kind == "photo")
                    {
                        path = Path.GetFullPath(reference);
                        if (!BtPhotoAllowed(path)) return "ERR|photo hors des dossiers de captures";
                        max = BtPhotoMax;
                    }
                    else if (kind == "music")
                    {
                        var dir = MusicFolder();
                        path = Path.GetFullPath(Path.IsPathRooted(reference) ? reference : Path.Combine(dir, reference));
                        if (!BtUnder(path, dir) || !MusicExtensions.Contains(Path.GetExtension(path).ToLowerInvariant()))
                            return "ERR|morceau hors du dossier musique";
                        max = BtMusicMax;
                    }
                    else return "ERR|type inconnu";
                    var info = new FileInfo(path);
                    if (!info.Exists) return "ERR|fichier introuvable";
                    if (info.Length <= 0) return "ERR|fichier vide";
                    if (info.Length > max) return "ERR|trop lourd (" + (max / (1024 * 1024)).ToString(CultureInfo.InvariantCulture) + " Mo au plus)";
                    var handle = "S" + Interlocked.Increment(ref _btSeq).ToString(CultureInfo.InvariantCulture);
                    BtSends[handle] = new BtSend { Path = path, Size = info.Length };
                    var chunks = (info.Length + BtChunk - 1) / BtChunk;
                    return string.Join("|", "OK", handle, info.Name.Replace("|", "_"), info.Length.ToString(CultureInfo.InvariantCulture), chunks.ToString(CultureInfo.InvariantCulture));
                }
                case "BtFileRead":
                {
                    if (!BtSends.TryGetValue(Arg(0), out var s)) return "ERR|transfert inconnu";
                    var n = Num(1);
                    var offset = n * BtChunk;
                    if (n < 0 || offset >= s.Size) return "ERR|morceau hors du fichier";
                    var len = (int)Math.Min(BtChunk, s.Size - offset);
                    var buf = new byte[len];
                    using (var fs = new FileStream(s.Path, FileMode.Open, FileAccess.Read, FileShare.ReadWrite))
                    {
                        fs.Seek(offset, SeekOrigin.Begin);
                        var read = 0;
                        while (read < len)
                        {
                            var r = fs.Read(buf, read, len - read);
                            if (r <= 0) break;
                            read += r;
                        }
                        if (read != len) return "ERR|lecture incomplète";
                    }
                    return "OK|" + Convert.ToBase64String(buf);
                }
                case "BtFileClose":
                    BtSends.TryRemove(Arg(0), out _);
                    return "OK|0";
                case "BtRecvOpen":
                {
                    var id = BtSafeName(Arg(0));
                    var kind = Arg(1).ToLowerInvariant();
                    var name = BtSafeName(Arg(2));
                    var size = Num(3);
                    if (kind != "photo" && kind != "music") return "ERR|type inconnu";
                    var max = kind == "photo" ? BtPhotoMax : BtMusicMax;
                    if (size <= 0 || size > max) return "ERR|taille refusée";
                    var ext = Path.GetExtension(name).ToLowerInvariant();
                    if (kind == "photo" ? !BtPhotoExtensions.Contains(ext) : !MusicExtensions.Contains(ext)) return "ERR|extension refusée";
                    var temp = Path.Combine(BtTempFolder(), id + ".part");
                    using (var fs = new FileStream(temp, FileMode.Create, FileAccess.Write, FileShare.None)) fs.SetLength(size);
                    BtRecvs[id] = new BtRecv { Temp = temp, Kind = kind, Name = name, Size = size };
                    return "OK|" + id;
                }
                case "BtRecvWrite":
                {
                    if (!BtRecvs.TryGetValue(BtSafeName(Arg(0)), out var r)) return "ERR|réception inconnue";
                    var n = Num(1);
                    var data = Convert.FromBase64String(Arg(2));
                    var offset = n * BtChunk;
                    if (n < 0 || offset + data.Length > r.Size) return "ERR|morceau hors du fichier";
                    using (var fs = new FileStream(r.Temp, FileMode.Open, FileAccess.Write, FileShare.None))
                    {
                        fs.Seek(offset, SeekOrigin.Begin);
                        fs.Write(data, 0, data.Length);
                    }
                    lock (r) r.Got += data.Length;
                    return "OK|" + r.Got.ToString(CultureInfo.InvariantCulture);
                }
                case "BtRecvEnd":
                {
                    var id = BtSafeName(Arg(0));
                    if (!BtRecvs.TryRemove(id, out var r)) return "ERR|réception inconnue";
                    if (r.Got < r.Size)
                    {
                        try { File.Delete(r.Temp); } catch { /* fichier encore ouvert : supprimé par BtTempFolder après une heure */ }
                        return "ERR|fichier incomplet";
                    }
                    string dir;
                    if (r.Kind == "photo")
                    {
                        dir = ComspecCaptureDir() ?? "";
                        if (dir.Length == 0)
                        {
                            try { File.Delete(r.Temp); } catch { /* ignoré */ }
                            return "ERR|dossier des captures introuvable";
                        }
                    }
                    else
                    {
                        dir = Path.Combine(MusicFolder(), "Bluetooth");
                        try { Directory.CreateDirectory(dir); }
                        catch
                        {
                            try { File.Delete(r.Temp); } catch { /* ignoré */ }
                            throw;
                        }
                    }
                    var final = BtUnique(dir, r.Name);
                    try { File.Move(r.Temp, final); }
                    catch
                    {
                        try { File.Delete(r.Temp); } catch { /* ignoré */ }
                        throw;
                    }
                    var shown = r.Kind == "music" ? Path.GetRelativePath(MusicFolder(), final) : Path.GetFileName(final);
                    return "OK|" + final.Replace('\\', '/') + "|" + shown.Replace("|", "_");
                }
                case "BtRecvAbort":
                {
                    if (BtRecvs.TryRemove(BtSafeName(Arg(0)), out var r))
                    {
                        try { File.Delete(r.Temp); } catch { /* ignoré */ }
                    }
                    return "OK|0";
                }
            }
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.Message.Replace("|", "/");
        }
        return "ERR|unknown_command";
    }
}
