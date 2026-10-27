using System;
using System.IO;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

/// <summary>
/// Fiches médicales de la communauté pour l'app Médical de l'ATAK natif (groupe sanguin, poids, taille, intolérances,
/// allergies : Effectifs > Tableur > Médical du site), gardées sur ce PC dans Documents\Arma 3 - COMSPEC\Sync\medical_roster.json.
/// Le téléphone lit le fichier ; le site n'est appelé que sur ACTUALISER ou si ce PC n'a encore rien.
/// Le fichier est propre à l'adresse du site, à la communauté et au joueur ; un autre compte repart de zéro.
/// Un refus du site (droit retiré) efface la copie de ce PC.
/// </summary>
public static partial class Extension
{
    private static readonly object MedicalRosterLock = new();
    private static string _medicalRosterList = "";
    private static string _medicalRosterSavedAt = "";
    private static string _medicalRosterRole = "";
    private static string _medicalRosterOwner = "";

    private static string? MedicalRosterPath()
    {
        var dir = SyncStoreDir();
        return dir is null ? null : Path.Combine(dir, "medical_roster.json");
    }

    /// <summary>Recharge le fichier s'il est à ce compte (la liste en mémoire d'un autre compte est oubliée).</summary>
    private static void MedicalRosterLoad()
    {
        lock (MedicalRosterLock)
        {
            var owner = WardrobeCacheOwner();
            if (_medicalRosterOwner == owner && _medicalRosterList.Length > 0) return;
            _medicalRosterList = ""; _medicalRosterSavedAt = ""; _medicalRosterRole = ""; _medicalRosterOwner = owner;
            try
            {
                var path = MedicalRosterPath();
                if (path is null || !File.Exists(path)) return;
                using var doc = JsonDocument.Parse(File.ReadAllText(path, Encoding.UTF8));
                var root = doc.RootElement;
                if (!root.TryGetProperty("owner", out var o) || o.GetString() != owner) return;
                _medicalRosterSavedAt = root.TryGetProperty("saved_at", out var s) ? s.GetString() ?? "" : "";
                _medicalRosterRole = root.TryGetProperty("role", out var r) ? r.GetString() ?? "" : "";
                _medicalRosterList = root.TryGetProperty("list", out var l) ? l.GetString() ?? "" : "";
            }
            catch
            {
                // Fichier abîmé : on repart du site.
                _medicalRosterList = "";
            }
        }
    }

    private static void MedicalRosterSave()
    {
        lock (MedicalRosterLock)
        {
            try
            {
                var path = MedicalRosterPath();
                if (path is null) return;
                using var ms = new MemoryStream();
                using (var w = new Utf8JsonWriter(ms))
                {
                    w.WriteStartObject();
                    w.WriteString("owner", _medicalRosterOwner);
                    w.WriteString("saved_at", _medicalRosterSavedAt);
                    w.WriteString("role", _medicalRosterRole);
                    w.WriteString("list", _medicalRosterList);
                    w.WriteEndObject();
                }
                var tmp = path + ".tmp";
                File.WriteAllBytes(tmp, ms.ToArray());
                File.Move(tmp, path, true);
            }
            catch
            {
                // Disque plein ou dossier en lecture seule : la liste reste en mémoire pour la partie.
            }
        }
    }

    private static void MedicalRosterForget()
    {
        lock (MedicalRosterLock)
        {
            _medicalRosterList = ""; _medicalRosterSavedAt = ""; _medicalRosterRole = "";
            try
            {
                var path = MedicalRosterPath();
                if (path != null && File.Exists(path)) File.Delete(path);
            }
            catch { }
        }
    }

    /// <summary>
    /// « MedicalRoster » args : [ligne de départ, mode]. Mode « cache » : fichier de ce PC, le site n'est appelé
    /// que s'il n'y a rien ; « refresh » (ou absent) à la ligne 0 : relu sur le site (bouton ACTUALISER).
    /// Lignes (champ vide écrit « - ») : steam\tindicatif\tfonction\tgroupe sanguin\tpoids kg\ttaille cm\tintolérances\tallergies.
    /// Retour page : OK|NEXT\tligne suivante\nlignes… ou OK|END\nlignes… ; refus : ERR|forbidden, ERR|session_required…
    /// « MedicalRosterInfo » : OK|enregistré le\tnombre\tdroit (vide = jamais chargé sur ce PC).
    /// </summary>
    private static string MedicalRosterCommand(string function, string[] args, CancellationToken token)
    {
        MedicalRosterLoad();
        if (function == "MedicalRosterInfo")
        {
            var n = _medicalRosterList.Split('\n', StringSplitOptions.RemoveEmptyEntries).Length;
            return "OK|" + _medicalRosterSavedAt + "\t" + n + "\t" + _medicalRosterRole;
        }
        var startLine = 0;
        if (args.Length >= 1 && int.TryParse((args[0] ?? "0").Trim(), out var sl) && sl >= 0) startLine = sl;
        var fromCache = args.Length >= 2 && (args[1] ?? "").Trim() == "cache";
        if ((startLine == 0 && !fromCache) || _medicalRosterSavedAt.Length == 0)
        {
            if (!HasPortalAuth()) return "ERR|session_required";
            var resp = SendJsonPost(_baseUrl + "/api/atak/medical-roster", EnrichAtakPayload("{}"), token);
            var body = ReadContentUtf8(resp, token);
            var code = (int)resp.StatusCode;
            if (!resp.IsSuccessStatusCode)
            {
                if (code == 403 && body.Contains("\"forbidden\"", StringComparison.Ordinal)) { MedicalRosterForget(); return "ERR|forbidden"; }
                if (code == 403 && body.Contains("session_required", StringComparison.Ordinal)) return "ERR|session_required";
                if (code == 401 || code == 403) return "ERR|unauthorized";
                if (code == 503) return "ERR|unavailable";
                return "ERR|http_" + code;
            }
            var sb = new StringBuilder();
            var role = "";
            try
            {
                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;
                role = root.TryGetProperty("role", out var rr) ? rr.GetString() ?? "" : "";
                if (root.TryGetProperty("rows", out var rows) && rows.ValueKind == JsonValueKind.Array)
                {
                    foreach (var el in rows.EnumerateArray())
                    {
                        string C(string k)
                        {
                            if (!el.TryGetProperty(k, out var v)) return "";
                            var t = v.ValueKind == JsonValueKind.String ? (v.GetString() ?? "") : v.ValueKind == JsonValueKind.Number ? v.GetRawText() : "";
                            t = t.Replace("\t", " ").Replace("\r", "").Replace("\n", " ").Replace("|", "/").Trim();
                            // Champ vide écrit « - » : splitString de SQF saute les champs vides.
                            return t.Length > 0 ? t : "-";
                        }
                        sb.Append(string.Join("\t", C("steam"), C("callsign"), C("job"), C("blood_type"), C("weight_kg"), C("height_cm"), C("drug_intolerances"), C("allergies"))).Append('\n');
                    }
                }
            }
            catch { return "ERR|bad_json"; }
            lock (MedicalRosterLock)
            {
                _medicalRosterList = sb.ToString();
                _medicalRosterRole = role;
                _medicalRosterSavedAt = DateTime.Now.ToString("dd/MM HH:mm");
                _medicalRosterOwner = WardrobeCacheOwner();
            }
            MedicalRosterSave();
        }
        return FormatWardrobesListPage(_medicalRosterList, startLine);
    }
}
