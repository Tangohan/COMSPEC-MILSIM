using System.Globalization;
using System.Text;
using System.Text.Encodings.Web;
using System.Text.Json;

namespace COMSPECExtension;

/// <summary>
/// Configuration locale du téléphone ATAK natif (mode full local, sans Athena) : fichiers JSON lisibles et modifiables
/// à la main dans Documents\Arma 3\COMSPEC_Local (config.json : numéros de téléphone et leurs types, contacts, poste TOC).
/// Le SQF écrit du JSON compact (fn_json) ; la DLL le vérifie et l'enregistre indenté, l'ancien fichier gardé en .bak.
/// À la lecture, le JSON est rendu en tableau SQF lisible par parseSimpleArray : un objet devient ["#obj", [clé, valeur]...],
/// null devient "".
/// Commandes SQF (retour "OK|..." ou "ERR|...") :
///   LocalBegin                    → vide le tampon d'écriture
///   LocalAppend [morceau]         → ajoute au tampon (morceaux de 4000 caractères : la ligne d'appel Arma est bornée)
///   LocalCommit [nom]             → écrit le tampon dans nom.json (nom : lettres, chiffres, - et _)
///   LocalGet [nom, décalage]      → OK|longueur totale|morceau du tableau SQF ; décalage 0 relit le fichier
///                                   ERR|absent (pas de fichier) ou ERR|json|message (fichier mal formé)
///   LocalFolder                   → OK|chemin du dossier
///   LocalOpenFolder               → ouvre le dossier dans l'explorateur
/// </summary>
public static partial class Extension
{
    private static readonly StringBuilder _localBuffer = new();
    private static string _localRead = "";

    private static string LocalFolder()
    {
        var docs = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);
        var dir = Path.Combine(docs, "Arma 3", "COMSPEC_Local");
        try { Directory.CreateDirectory(dir); } catch { /* lecture seule : on renvoie quand même le chemin */ }
        return dir;
    }

    // Nom de fichier venu du SQF : jamais de dossier, d'extension ni de caractère spécial.
    private static string? LocalFile(string name)
    {
        var n = name.Trim().Trim('"').ToLowerInvariant();
        if (n.Length == 0 || n.Length > 40) return null;
        foreach (var c in n)
        {
            if (!(char.IsAsciiLetterOrDigit(c) || c == '-' || c == '_')) return null;
        }
        return Path.Combine(LocalFolder(), n + ".json");
    }

    private static void LocalToSqf(JsonElement e, StringBuilder sb)
    {
        switch (e.ValueKind)
        {
            case JsonValueKind.Object:
                sb.Append("[\"#obj\"");
                foreach (var p in e.EnumerateObject())
                {
                    sb.Append(",[");
                    LocalSqfString(p.Name, sb);
                    sb.Append(',');
                    LocalToSqf(p.Value, sb);
                    sb.Append(']');
                }
                sb.Append(']');
                break;
            case JsonValueKind.Array:
                sb.Append('[');
                var first = true;
                foreach (var v in e.EnumerateArray())
                {
                    if (!first) sb.Append(',');
                    first = false;
                    LocalToSqf(v, sb);
                }
                sb.Append(']');
                break;
            case JsonValueKind.String:
                LocalSqfString(e.GetString() ?? "", sb);
                break;
            case JsonValueKind.Number:
                sb.Append(e.TryGetDouble(out var d) && double.IsFinite(d) ? d.ToString("0.######", CultureInfo.InvariantCulture) : "0");
                break;
            case JsonValueKind.True:
                sb.Append("true");
                break;
            case JsonValueKind.False:
                sb.Append("false");
                break;
            default:
                sb.Append("\"\"");
                break;
        }
    }

    // Texte SQF : guillemets doublés ; retours à la ligne gardés (parseSimpleArray les accepte dans une chaîne).
    private static void LocalSqfString(string s, StringBuilder sb)
    {
        sb.Append('"').Append(s.Replace("\"", "\"\"").Replace("\r", "")).Append('"');
    }

    private static string LocalCommand(string function, string?[] args)
    {
        string Arg(int i) => args.Length > i ? (args[i] ?? "") : "";
        try
        {
            switch (function)
            {
                case "LocalFolder":
                    return "OK|" + LocalFolder();
                case "LocalOpenFolder":
                    System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo("explorer.exe", "\"" + LocalFolder() + "\"") { UseShellExecute = false });
                    return "OK|" + LocalFolder();
                case "LocalBegin":
                    lock (_localBuffer) _localBuffer.Clear();
                    return "OK|0";
                case "LocalAppend":
                {
                    // Guillemets extérieurs déjà retirés (ArmaString) ; les guillemets internes arrivent doublés.
                    var chunk = Arg(0).Replace("\"\"", "\"");
                    lock (_localBuffer)
                    {
                        if (_localBuffer.Length + chunk.Length > 1_000_000) return "ERR|trop long";
                        _localBuffer.Append(chunk);
                        return "OK|" + _localBuffer.Length.ToString(CultureInfo.InvariantCulture);
                    }
                }
                case "LocalCommit":
                {
                    var path = LocalFile(Arg(0));
                    if (path == null) return "ERR|nom";
                    string text;
                    lock (_localBuffer) text = _localBuffer.ToString();
                    using var doc = JsonDocument.Parse(text);
                    var opts = new JsonWriterOptions { Indented = true, Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping };
                    using var ms = new MemoryStream();
                    using (var w = new Utf8JsonWriter(ms, opts)) doc.WriteTo(w);
                    var tmp = path + ".tmp";
                    File.WriteAllBytes(tmp, ms.ToArray());
                    if (File.Exists(path)) File.Copy(path, path + ".bak", true);
                    File.Move(tmp, path, true);
                    return "OK|" + path;
                }
                case "LocalGet":
                {
                    var offset = int.TryParse(Arg(1).Trim('"'), NumberStyles.Integer, CultureInfo.InvariantCulture, out var o) ? Math.Max(0, o) : 0;
                    // Décalage 0 : nouvelle lecture du fichier ; les morceaux suivants viennent de la même conversion.
                    if (offset == 0)
                    {
                        var path = LocalFile(Arg(0));
                        if (path == null) return "ERR|nom";
                        if (!File.Exists(path)) return "ERR|absent";
                        try
                        {
                            using var doc = JsonDocument.Parse(File.ReadAllText(path), new JsonDocumentOptions { CommentHandling = JsonCommentHandling.Skip, AllowTrailingCommas = true });
                            var sb = new StringBuilder();
                            LocalToSqf(doc.RootElement, sb);
                            _localRead = sb.ToString();
                        }
                        catch (JsonException ex)
                        {
                            return "ERR|json|" + (ex.LineNumber.HasValue ? $"ligne {ex.LineNumber.Value + 1} : " : "") + ex.Message.Split('.')[0];
                        }
                    }
                    var total = _localRead.Length;
                    var head = "OK|" + total.ToString(CultureInfo.InvariantCulture) + "|";
                    var room = Math.Max(0, MaxOutputBytes - head.Length - 64);
                    // Réponse bornée en octets : prudence pour l'UTF-8 (un caractère peut en prendre jusqu'à trois).
                    var take = Math.Min(Math.Max(0, total - offset), room / 3);
                    return head + (offset < total ? _localRead.Substring(offset, take) : "");
                }
            }
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.Message;
        }
        return "ERR|unknown_command";
    }
}
