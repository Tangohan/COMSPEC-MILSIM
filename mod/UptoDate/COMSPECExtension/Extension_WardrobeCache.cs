using System;
using System.Collections.Generic;
using System.IO;
using System.Text;
using System.Text.Json;

namespace COMSPECExtension;

/// <summary>
/// Liste des tenues de l'organisation (ACE Arsenal) gardée sur ce PC, dans Documents\Arma 3 - COMSPEC\Sync\wardrobes.json :
/// le panneau « Athena · Tenues » ne recharge plus toute la collection du site à chaque ouverture, seulement sur
/// le bouton Rafraîchir (ou s'il n'a encore rien). Le détail d'une tenue est gardé avec l'empreinte (sha256) que
/// donne la liste : tant qu'elle ne change pas sur le site, la tenue n'est pas retéléchargée.
/// Le fichier est propre à l'adresse du site, à la communauté et au joueur ; un autre compte repart de zéro.
/// Rien n'est jamais effacé côté site par ce cache, il ne fait que lire.
/// </summary>
public static partial class Extension
{
    private const int WardrobeCacheMaxDetails = 400;
    private static readonly object WardrobeCacheLock = new();
    private static string _wardrobeCacheSavedAt = "";
    private static Dictionary<string, (string Sha, string Body)>? _wardrobeDetailStore;
    // Import de toute la collection : le fichier n'est pas réécrit à chaque tenue, au plus toutes les 5 s
    // (et à la fin, quand le panneau relit WardrobesCacheInfo).
    private static bool _wardrobeCacheDirty;
    private static DateTime _wardrobeCacheLastSave = DateTime.MinValue;

    private static string WardrobeCacheOwner() => (_baseUrl ?? "") + "|" + _tenantId + "|" + _steamUid;

    private static string? WardrobeCachePath()
    {
        var dir = SyncStoreDir();
        return dir is null ? null : Path.Combine(dir, "wardrobes.json");
    }

    /// <summary>Charge le fichier (liste + détails) s'il est à ce compte. Retour : la liste simplifiée, ou "".</summary>
    private static string WardrobeCacheLoad()
    {
        lock (WardrobeCacheLock)
        {
            _wardrobeDetailStore ??= new Dictionary<string, (string, string)>(StringComparer.Ordinal);
            try
            {
                var path = WardrobeCachePath();
                if (path is null || !File.Exists(path)) return "";
                using var doc = JsonDocument.Parse(File.ReadAllText(path, Encoding.UTF8));
                var root = doc.RootElement;
                if (!root.TryGetProperty("owner", out var o) || o.GetString() != WardrobeCacheOwner()) return "";
                _wardrobeCacheSavedAt = root.TryGetProperty("saved_at", out var s) ? s.GetString() ?? "" : "";
                if (root.TryGetProperty("details", out var det) && det.ValueKind == JsonValueKind.Object)
                {
                    foreach (var p in det.EnumerateObject())
                    {
                        var sha = p.Value.TryGetProperty("sha", out var sh) ? sh.GetString() ?? "" : "";
                        var body = p.Value.TryGetProperty("body", out var b) ? b.GetString() ?? "" : "";
                        if (sha.Length > 0 && body.Length > 0) _wardrobeDetailStore[p.Name] = (sha, body);
                    }
                }
                return root.TryGetProperty("list", out var l) ? l.GetString() ?? "" : "";
            }
            catch
            {
                // Fichier abîmé : on repart du site.
                return "";
            }
        }
    }

    private static void WardrobeCacheSave()
    {
        lock (WardrobeCacheLock)
        {
            try
            {
                var path = WardrobeCachePath();
                if (path is null) return;
                // Seuls les détails des tenues encore dans la liste sont gardés.
                var live = WardrobeListShas(_wardrobesListCache);
                using var ms = new MemoryStream();
                using (var w = new Utf8JsonWriter(ms, new JsonWriterOptions { Indented = false }))
                {
                    w.WriteStartObject();
                    w.WriteString("owner", WardrobeCacheOwner());
                    w.WriteString("saved_at", _wardrobeCacheSavedAt);
                    w.WriteString("list", _wardrobesListCache ?? "");
                    w.WriteStartObject("details");
                    var n = 0;
                    if (_wardrobeDetailStore != null)
                    {
                        foreach (var kv in _wardrobeDetailStore)
                        {
                            if (n >= WardrobeCacheMaxDetails) break;
                            if (!live.TryGetValue(kv.Key, out var sha) || sha != kv.Value.Sha) continue;
                            w.WriteStartObject(kv.Key);
                            w.WriteString("sha", kv.Value.Sha);
                            w.WriteString("body", kv.Value.Body);
                            w.WriteEndObject();
                            n++;
                        }
                    }
                    w.WriteEndObject();
                    w.WriteEndObject();
                }
                var tmp = path + ".tmp";
                File.WriteAllBytes(tmp, ms.ToArray());
                File.Move(tmp, path, true);
                _wardrobeCacheDirty = false;
                _wardrobeCacheLastSave = DateTime.UtcNow;
            }
            catch
            {
                // Disque plein ou dossier en lecture seule : la liste reste en mémoire pour la partie.
            }
        }
    }

    /// <summary>id → sha256 d'après la liste simplifiée (colonnes id … sha256, 10e colonne).</summary>
    private static Dictionary<string, string> WardrobeListShas(string? list)
    {
        var map = new Dictionary<string, string>(StringComparer.Ordinal);
        if (string.IsNullOrEmpty(list)) return map;
        foreach (var line in list.Split('\n'))
        {
            var f = line.Split('\t');
            if (f.Length >= 10 && f[0].Length > 0 && f[9].Length > 0) map[f[0]] = f[9].Trim().ToLowerInvariant();
        }
        return map;
    }

    /// <summary>Détail gardé pour cette tenue s'il correspond encore à l'empreinte de la liste, sinon null.</summary>
    private static string? WardrobeCachedDetail(string id)
    {
        lock (WardrobeCacheLock)
        {
            if (_wardrobeDetailStore == null) WardrobeCacheLoadUnlocked();
            if (_wardrobeDetailStore == null || !_wardrobeDetailStore.TryGetValue(id, out var d)) return null;
            return WardrobeListShas(_wardrobesListCache).TryGetValue(id, out var sha) && sha == d.Sha ? d.Body : null;
        }
    }

    private static void WardrobeCacheLoadUnlocked()
    {
        // Monitor est réentrant : WardrobeCacheLoad reprend le même verrou.
        var list = WardrobeCacheLoad();
        if (string.IsNullOrEmpty(_wardrobesListCache) && list.Length > 0) _wardrobesListCache = list;
    }

    private static void WardrobeCacheStoreDetail(string id, string body)
    {
        if (string.IsNullOrEmpty(body)) return;
        lock (WardrobeCacheLock)
        {
            if (!WardrobeListShas(_wardrobesListCache).TryGetValue(id, out var sha)) return;
            _wardrobeDetailStore ??= new Dictionary<string, (string, string)>(StringComparer.Ordinal);
            _wardrobeDetailStore[id] = (sha, body);
            _wardrobeCacheDirty = true;
            if ((DateTime.UtcNow - _wardrobeCacheLastSave).TotalSeconds < 5) return;
        }
        WardrobeCacheSave();
    }

    /// <summary>
    /// « WardrobesCacheInfo » : OK|savedAt\tcount\tlocalOk\tpathHint
    /// savedAt vide = jamais chargé sur ce PC ; localOk = 1 si wardrobes.json est présent dans Documents.
    /// </summary>
    private static string WardrobeCacheInfo()
    {
        if (_wardrobeCacheDirty) WardrobeCacheSave();
        if (string.IsNullOrEmpty(_wardrobesListCache))
        {
            var list = WardrobeCacheLoad();
            if (list.Length > 0) _wardrobesListCache = list;
        }
        var count = string.IsNullOrEmpty(_wardrobesListCache) ? 0 : _wardrobesListCache.Split('\n', StringSplitOptions.RemoveEmptyEntries).Length;
        var path = WardrobeCachePath();
        var localOk = path is not null && File.Exists(path) ? "1" : "0";
        var hint = "Documents\\Arma 3 - COMSPEC\\Sync";
        return "OK|" + _wardrobeCacheSavedAt + "\t" + count + "\t" + localOk + "\t" + hint;
    }
}
