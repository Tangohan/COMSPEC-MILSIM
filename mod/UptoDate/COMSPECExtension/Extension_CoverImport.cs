using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;

namespace COMSPECExtension;

/// <summary>
/// Import d'une couverture générée sur Athena (app « Ma couverture » du téléphone ATAK). LOCAL SEULEMENT : lit un
/// fichier du dossier Documents\Arma 3 - COMSPEC, ne fait aucune requête réseau et ne dépend d'aucune autorisation
/// d'Athena (le jeu n'a pas besoin d'être connecté).
/// <list type="bullet">
/// <item>ReadCoverFile [nom?] → « OK|nbMorceaux|ref » ou « ERR|code ». Sans nom : le plus récent des fichiers
/// comspec-couverture-CVR-XXXXXXXX.json. Un nom ne peut être que de cette forme (jamais un chemin).</item>
/// <item>ReadCoverFileChunk [i] → « OK|morceau i » (au plus 6000 octets) ; morceaux concaténés → parseSimpleArray :
/// paires [["clé", valeur]…], chaînes entre guillemets doublés.</item>
/// </list>
/// Lecture stricte (même contrat que SseCoverExportService::parse du site) : 256 Kio au plus, JSON UTF-8 de profondeur
/// 6, clés connues et uniques, types et tailles, aucun caractère de contrôle, empreinte SHA-256 du texte de « cover »,
/// date de fin de validité, photo vérifiée (JPEG, 150 000 caractères) mais jamais retransmise. Aucun contenu n'est
/// exécuté ni interprété. La signature du fichier ne se vérifie que sur Athena : ici seul le format est garanti.
/// Codes d'erreur : no_file, bad_name, read, too_large, json, keys, format, version, header, expired, hash, cover.
/// </summary>
public static partial class Extension
{
    private const long CoverFileMaxBytes = 262144;
    private const int CoverPhotoMaxChars = 150000;
    private const int CoverChunkBytes = 6000;

    private static readonly object CoverImportLock = new();
    private static string _coverImportText = "";
    private static List<int> _coverImportStarts = new();

    private static readonly Regex CoverFileNameRe = new(@"^comspec-couverture-CVR-[0-9A-F]{8}\.json$", RegexOptions.CultureInvariant);
    private static readonly Regex CoverRefRe = new(@"^CVR-[0-9A-F]{8}$", RegexOptions.CultureInvariant);
    private static readonly Regex CoverHex16Re = new(@"^[0-9a-f]{16}$", RegexOptions.CultureInvariant);
    private static readonly Regex CoverHex64Re = new(@"^[0-9a-f]{64}$", RegexOptions.CultureInvariant);
    private static readonly Regex CoverIsoRe = new(@"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$", RegexOptions.CultureInvariant);
    private static readonly Regex CoverDateRe = new(@"^\d{4}-\d{2}-\d{2}$", RegexOptions.CultureInvariant);
    private static readonly Regex CoverB64Re = new(@"^[A-Za-z0-9+/]+={0,2}$", RegexOptions.CultureInvariant);

    private static readonly string[] CoverTopKeys = { "format", "version", "ref", "generated_at", "expires_at", "account_ref", "cover", "content_sha256", "sig" };
    private static readonly string[] CoverKeys =
    {
        "seed", "lang", "name", "first_name", "last_name", "sex", "dob", "pob", "country", "country_code", "address", "city",
        "profession", "employer", "phone", "travel", "family", "history", "contacts", "docs", "photo",
    };
    private static readonly Dictionary<string, int> CoverTextMax = new(StringComparer.Ordinal)
    {
        ["name"] = 160, ["first_name"] = 80, ["last_name"] = 80, ["pob"] = 120, ["country"] = 80, ["address"] = 255, ["city"] = 120,
        ["profession"] = 120, ["employer"] = 190, ["phone"] = 60, ["travel"] = 255, ["family"] = 190,
    };
    private static readonly string[] CoverDocTypes = { "id_card", "passport", "driving_license", "military_id" };

    private static string CoverImportCommand(string function, string?[] args)
    {
        if (function == "ReadCoverFileChunk")
        {
            if (args.Length < 1 || !int.TryParse((args[0] ?? "").Trim(), NumberStyles.Integer, CultureInfo.InvariantCulture, out var i) || i < 0)
                return "ERR|invalid_chunk";
            lock (CoverImportLock)
            {
                if (_coverImportText.Length == 0) return "ERR|no_import";
                if (i >= _coverImportStarts.Count) return "ERR|chunk_oob";
                var start = _coverImportStarts[i];
                var end = i + 1 < _coverImportStarts.Count ? _coverImportStarts[i + 1] : _coverImportText.Length;
                return "OK|" + _coverImportText.Substring(start, end - start);
            }
        }

        lock (CoverImportLock) { _coverImportText = ""; _coverImportStarts = new List<int>(); }
        try
        {
            var dir = LocalDocumentsPath("Arma 3 - COMSPEC");
            if (!Directory.Exists(dir)) return "ERR|no_file";
            var want = args.Length > 0 ? (args[0] ?? "").Trim() : "";
            string? path;
            if (want.Length > 0)
            {
                if (!CoverFileNameRe.IsMatch(want)) return "ERR|bad_name";
                path = Path.Combine(dir, want);
            }
            else
            {
                path = new DirectoryInfo(dir).EnumerateFiles("comspec-couverture-*.json")
                    .Where(f => CoverFileNameRe.IsMatch(f.Name))
                    .OrderByDescending(f => f.LastWriteTimeUtc)
                    .Select(f => f.FullName)
                    .FirstOrDefault();
            }
            if (path == null || !File.Exists(path)) return "ERR|no_file";
            var info = new FileInfo(path);
            if ((info.Attributes & FileAttributes.ReparsePoint) != 0) return "ERR|read";
            if (info.Length > CoverFileMaxBytes) return "ERR|too_large";
            var bytes = File.ReadAllBytes(path);
            if (bytes.Length > CoverFileMaxBytes) return "ERR|too_large";

            var (err, sqf, reference) = ParseCoverFile(bytes, DateTime.UtcNow);
            if (err != null) return "ERR|" + err;
            var starts = Utf8ChunkStarts(sqf, CoverChunkBytes);
            lock (CoverImportLock)
            {
                _coverImportText = sqf;
                _coverImportStarts = starts;
            }
            return "OK|" + starts.Count.ToString(CultureInfo.InvariantCulture) + "|" + reference;
        }
        catch (Exception)
        {
            return "ERR|read";
        }
    }

    private static string CoverQ(string s) => "\"" + s.Replace("\"", "\"\"") + "\"";

    private static bool CoverCleanText(JsonElement e, int max, out string value)
    {
        value = "";
        if (e.ValueKind != JsonValueKind.String) return false;
        var s = e.GetString() ?? "";
        if (s.Length > max) return false;
        foreach (var c in s)
        {
            if (c < 0x20 || c == 0x7F) return false;
        }
        value = s.Trim();
        return true;
    }

    /// <summary>Contrat du fichier ; rend (code d'erreur | null, texte parseSimpleArray, référence).</summary>
    private static (string? err, string sqf, string reference) ParseCoverFile(byte[] bytes, DateTime nowUtc)
    {
        const string none = "";
        string text;
        try { text = new UTF8Encoding(false, true).GetString(bytes); }
        catch (Exception) { return ("json", none, none); }
        if (text.Length > 0 && text[0] == '﻿') text = text.Substring(1);
        JsonDocument doc;
        try
        {
            doc = JsonDocument.Parse(text, new JsonDocumentOptions { MaxDepth = 6, AllowTrailingCommas = false, CommentHandling = JsonCommentHandling.Disallow });
        }
        catch (JsonException) { return ("json", none, none); }
        using (doc)
        {
            var root = doc.RootElement;
            if (root.ValueKind != JsonValueKind.Object) return ("keys", none, none);
            var top = new Dictionary<string, JsonElement>(StringComparer.Ordinal);
            foreach (var p in root.EnumerateObject())
            {
                if (!CoverTopKeys.Contains(p.Name) || !top.TryAdd(p.Name, p.Value)) return ("keys", none, none);
            }
            if (!top.TryGetValue("format", out var fmt) || fmt.ValueKind != JsonValueKind.String || fmt.GetString() != "comspec-cover") return ("format", none, none);
            if (!top.TryGetValue("version", out var ver) || ver.ValueKind != JsonValueKind.Number || !ver.TryGetInt32(out var v) || v != 1) return ("version", none, none);

            string Str(string k) => top.TryGetValue(k, out var el) && el.ValueKind == JsonValueKind.String ? el.GetString() ?? "" : "";
            var reference = Str("ref");
            var sha = Str("content_sha256");
            if (!CoverRefRe.IsMatch(reference) || !CoverHex16Re.IsMatch(Str("account_ref")) || !CoverHex64Re.IsMatch(sha) || !CoverHex64Re.IsMatch(Str("sig"))
                || !CoverIsoRe.IsMatch(Str("generated_at")) || !CoverIsoRe.IsMatch(Str("expires_at")))
                return ("header", none, none);
            if (string.CompareOrdinal(Str("expires_at"), nowUtc.ToString("yyyy-MM-dd'T'HH:mm:ss'Z'", CultureInfo.InvariantCulture)) < 0) return ("expired", none, none);
            if (!top.TryGetValue("cover", out var cover) || cover.ValueKind != JsonValueKind.Object) return ("cover", none, none);
            var rawCover = cover.GetRawText();
            var digest = Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(rawCover))).ToLowerInvariant();
            if (digest != sha) return ("hash", none, none);

            var c = new Dictionary<string, JsonElement>(StringComparer.Ordinal);
            foreach (var p in cover.EnumerateObject())
            {
                if (!CoverKeys.Contains(p.Name) || !c.TryAdd(p.Name, p.Value)) return ("cover", none, none);
            }
            var sb = new StringBuilder("[");
            void Pair(string k, string valueSqf)
            {
                if (sb.Length > 1) sb.Append(',');
                sb.Append('[').Append(CoverQ(k)).Append(',').Append(valueSqf).Append(']');
            }
            Pair("ref", CoverQ(reference));
            Pair("generated_at", CoverQ(Str("generated_at")));
            Pair("expires_at", CoverQ(Str("expires_at")));

            foreach (var (k, max) in CoverTextMax)
            {
                var val = "";
                if (c.TryGetValue(k, out var el) && !CoverCleanText(el, max, out val)) return ("cover", none, none);
                if (!c.ContainsKey(k) && k == "name") return ("cover", none, none);
                if (k == "name" && val.Length == 0) return ("cover", none, none);
                Pair(k, CoverQ(val));
            }
            var dob = "";
            if (c.TryGetValue("dob", out var dobEl) && (!CoverCleanText(dobEl, 10, out dob) || (dob.Length > 0 && !CoverDateRe.IsMatch(dob)))) return ("cover", none, none);
            Pair("dob", CoverQ(dob));
            var sex = "";
            if (c.TryGetValue("sex", out var sexEl) && (!CoverCleanText(sexEl, 1, out sex) || (sex.Length > 0 && sex != "M" && sex != "F" && sex != "X"))) return ("cover", none, none);
            Pair("sex", CoverQ(sex));
            var lang = "fr";
            if (c.TryGetValue("lang", out var langEl) && (!CoverCleanText(langEl, 2, out lang) || (lang != "fr" && lang != "en"))) return ("cover", none, none);
            Pair("lang", CoverQ(lang));
            var cc = "";
            if (c.TryGetValue("country_code", out var ccEl) && !CoverCleanText(ccEl, 16, out cc)) return ("cover", none, none);
            Pair("country_code", CoverQ(cc));
            if (c.TryGetValue("seed", out var seedEl) && (seedEl.ValueKind != JsonValueKind.Number || !seedEl.TryGetUInt32(out _))) return ("cover", none, none);

            // history : au plus 8 lignes de 255 caractères.
            var hist = new List<string>();
            if (c.TryGetValue("history", out var hEl))
            {
                if (hEl.ValueKind != JsonValueKind.Array || hEl.GetArrayLength() > 8) return ("cover", none, none);
                foreach (var line in hEl.EnumerateArray())
                {
                    if (!CoverCleanText(line, 255, out var hv)) return ("cover", none, none);
                    hist.Add(CoverQ(hv));
                }
            }
            Pair("history", "[" + string.Join(",", hist) + "]");

            // contacts : au plus 10 triplets [nom, lien, téléphone].
            var contacts = new List<string>();
            if (c.TryGetValue("contacts", out var cEl))
            {
                if (cEl.ValueKind != JsonValueKind.Array || cEl.GetArrayLength() > 10) return ("cover", none, none);
                foreach (var row in cEl.EnumerateArray())
                {
                    if (row.ValueKind != JsonValueKind.Array || row.GetArrayLength() != 3) return ("cover", none, none);
                    var parts = new List<string>();
                    var limits = new[] { 160, 60, 60 };
                    var n = 0;
                    foreach (var cell in row.EnumerateArray())
                    {
                        if (!CoverCleanText(cell, limits[n++], out var cv)) return ("cover", none, none);
                        parts.Add(CoverQ(cv));
                    }
                    contacts.Add("[" + string.Join(",", parts) + "]");
                }
            }
            Pair("contacts", "[" + string.Join(",", contacts) + "]");

            // docs : au plus 10 papiers {type, country, number, issued, expires}.
            var docs = new List<string>();
            if (c.TryGetValue("docs", out var dEl))
            {
                if (dEl.ValueKind != JsonValueKind.Array || dEl.GetArrayLength() > 10) return ("cover", none, none);
                foreach (var d in dEl.EnumerateArray())
                {
                    if (d.ValueKind != JsonValueKind.Object) return ("cover", none, none);
                    var f = new Dictionary<string, JsonElement>(StringComparer.Ordinal);
                    foreach (var p in d.EnumerateObject())
                    {
                        if (p.Name is not ("type" or "country" or "number" or "issued" or "expires") || !f.TryAdd(p.Name, p.Value)) return ("cover", none, none);
                    }
                    string Get(string k, int max, out bool ok)
                    {
                        var val = "";
                        ok = f.TryGetValue(k, out var el) && CoverCleanText(el, max, out val);
                        return val;
                    }
                    var type = Get("type", 20, out var o1);
                    var country = Get("country", 80, out var o2);
                    var number = Get("number", 40, out var o3);
                    var issued = Get("issued", 10, out var o4);
                    var expires = Get("expires", 10, out var o5);
                    if (!(o1 && o2 && o3 && o4 && o5) || !CoverDocTypes.Contains(type) || number.Length == 0
                        || !CoverDateRe.IsMatch(issued) || !CoverDateRe.IsMatch(expires))
                        return ("cover", none, none);
                    docs.Add("[" + string.Join(",", new[] { CoverQ(type), CoverQ(country), CoverQ(number), CoverQ(issued), CoverQ(expires) }) + "]");
                }
            }
            Pair("docs", "[" + string.Join(",", docs) + "]");

            // photo : vérifiée (JPEG), jamais retransmise (le jeu ne peut pas afficher une image en mémoire).
            if (c.TryGetValue("photo", out var photo) && photo.ValueKind != JsonValueKind.Null)
            {
                if (photo.ValueKind != JsonValueKind.Object) return ("cover", none, none);
                string? mime = null, b64 = null;
                foreach (var p in photo.EnumerateObject())
                {
                    if (p.Name == "mime" && p.Value.ValueKind == JsonValueKind.String) mime = p.Value.GetString();
                    else if (p.Name == "b64" && p.Value.ValueKind == JsonValueKind.String) b64 = p.Value.GetString();
                    else return ("cover", none, none);
                }
                if (mime != "image/jpeg" || b64 == null || b64.Length == 0 || b64.Length > CoverPhotoMaxChars || !CoverB64Re.IsMatch(b64)) return ("cover", none, none);
                byte[] img;
                try { img = Convert.FromBase64String(b64); }
                catch (FormatException) { return ("cover", none, none); }
                if (img.Length < 3 || img[0] != 0xFF || img[1] != 0xD8 || img[2] != 0xFF) return ("cover", none, none);
            }
            sb.Append(']');
            return (null, sb.ToString(), reference);
        }
    }
}
