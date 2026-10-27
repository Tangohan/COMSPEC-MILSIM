using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace COMSPECExtension;

/// <summary>
/// Auto-test de la liaison, lancé par « Dépannage liaison » (fn_diagChecks, via Async : rien ne fige le jeu).
///   1. GET /api/atak/diag/selftest : Athena refait ses contrôles (clé, communauté, maintenance, formule, compte,
///      unité sur la carte, base, erreurs récentes, versions publiées).
///   2. Chaque canal sondé pendant la partie (ordres, marqueurs, chat, alertes…) est rejoué une fois, quatre à la
///      fois, avec son code HTTP et son temps de réponse : un canal refusé ou lent se voit tout de suite.
///   3. État local : file d'envoi, pause demandée par Athena (429/503) ou réseau coupé, horloge du PC.
/// Réponse : « OK| » puis une ligne par contrôle, champs séparés par une tabulation :
///   origine (S serveur, C canal, L local)\tniveau (ok, info, warn, ko)\tlibellé\tvaleur\tconseil
/// </summary>
public static partial class Extension
{
    private const int SelfTestParallel = 4;
    private const int SelfTestTimeoutSeconds = 10;
    private const int SelfTestSlowMs = 3000;

    private static string LinkSelfTest(string? mapArg)
    {
        var baseUrl = _baseUrl;
        if (string.IsNullOrEmpty(baseUrl))
            return "ERR|no_base_url";
        var lines = new List<string>();
        void Add(string origin, string level, string label, string value, string hint = "") =>
            lines.Add(string.Join('\t', origin, level, SelfTestField(label), SelfTestField(value), SelfTestField(hint)));

        // 1. Contrôles côté Athena.
        var mapId = int.TryParse(mapArg, out var m) && m > 0 ? m : 1;
        var sw = Stopwatch.StartNew();
        try
        {
            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(SelfTestTimeoutSeconds));
            using var resp = SendGet(baseUrl + "/api/atak/diag/selftest?mapId=" + mapId, cts.Token);
            var code = (int)resp.StatusCode;
            var body = ReadContentUtf8(resp, cts.Token);
            sw.Stop();
            if (code == 401 || code == 403)
            {
                Add("S", "ko", "Authentification", "refusée (HTTP " + code + ")",
                    "Athena refuse la clé ou la session de ce jeu : Lier mon compte (nouveau code sur le site).");
            }
            else if (code == 404)
            {
                Add("S", "info", "Auto-test Athena", "absent (site plus ancien)", "Le site n'a pas encore l'auto-test : seuls les canaux sont vérifiés.");
            }
            else if (code == 503)
            {
                Add("S", "ko", "Athena", "maintenance ou surcharge (HTTP 503)", "Athena est en maintenance ou surchargée : réessayez dans quelques minutes.");
            }
            else if (code < 200 || code >= 300)
            {
                Add("S", "ko", "Auto-test Athena", "HTTP " + code, "Athena a répondu par une erreur : signalez-le au staff.");
            }
            else
            {
                Add("S", sw.ElapsedMilliseconds > SelfTestSlowMs ? "warn" : "ok", "Réponse d'Athena", sw.ElapsedMilliseconds + " ms",
                    sw.ElapsedMilliseconds > SelfTestSlowMs ? "Athena répond lentement : les échanges prennent du retard." : "");
                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;
                if (root.TryGetProperty("checks", out var checks) && checks.ValueKind == JsonValueKind.Array)
                {
                    foreach (var c in checks.EnumerateArray())
                    {
                        Add("S", SelfTestString(c, "level", "info"), SelfTestString(c, "label", "?"),
                            SelfTestString(c, "value", ""), SelfTestString(c, "hint", ""));
                    }
                }
                if (root.TryGetProperty("server_unix", out var su) && su.TryGetInt64(out var serverUnix))
                {
                    var skew = Math.Abs(DateTimeOffset.UtcNow.ToUnixTimeSeconds() - serverUnix);
                    Add("L", skew > 120 ? "warn" : "ok", "Horloge du PC", skew > 120 ? "décalée de " + skew + " s" : "à l'heure",
                        skew > 120 ? "Réglez l'heure automatique de Windows : un PC décalé fait expirer les codes et les sessions." : "");
                }
            }
        }
        catch (Exception ex)
        {
            Add("S", "ko", "Athena", "injoignable (" + (ex is OperationCanceledException ? "délai dépassé" : ex.GetType().Name) + ")",
                "Le site ne répond pas : vérifiez internet, puis que le site s'ouvre dans un navigateur.");
        }

        // 2. Chaque canal sondé pendant la partie, rejoué une fois.
        var channels = PollGetCache
            .Where(kv => !string.IsNullOrEmpty(kv.Value.Url))
            .Select(kv => (Key: kv.Key.Split(':')[0], Url: kv.Value.Url, Slot: kv.Value))
            .GroupBy(c => c.Key, StringComparer.Ordinal)
            .Select(g => g.First())
            .OrderBy(c => c.Key, StringComparer.Ordinal)
            .ToList();
        if (channels.Count == 0)
        {
            Add("C", "info", "Canaux", "aucun sondé pour l'instant", "Les canaux démarrent quand la liaison est active et le téléphone porté.");
        }
        else
        {
            var results = new string[channels.Count];
            // Ni « using » sur le sémaphore ni sur le délai global : des tâches peuvent encore tourner après
            // Task.WaitAll (Athena lente), et un Release / WaitAsync sur un objet libéré levait une exception perdue.
            // L'annulation globale réveille celles qui attendent encore leur tour.
            var gate = new SemaphoreSlim(SelfTestParallel);
            var overall = new CancellationTokenSource();
            var tasks = channels.Select((c, i) => Task.Run(async () =>
            {
                var entered = false;
                var t = Stopwatch.StartNew();
                try
                {
                    await gate.WaitAsync(overall.Token).ConfigureAwait(false);
                    entered = true;
                    t.Restart();
                    using var cts = CancellationTokenSource.CreateLinkedTokenSource(overall.Token);
                    cts.CancelAfter(TimeSpan.FromSeconds(SelfTestTimeoutSeconds));
                    using var resp = SendGet(c.Url, cts.Token);
                    var code = (int)resp.StatusCode;
                    var body = ReadContentUtf8(resp, cts.Token);
                    t.Stop();
                    var ms = t.ElapsedMilliseconds;
                    // Canal en pause ou tu (fonction hors offre) et de nouveau accepté : il reprend tout de suite.
                    var stopped = IsRefusalLatched(c.Url);
                    if (code is >= 200 and < 400)
                    {
                        NotePollResult(c.Slot, code);
                        FeatureLatchUntilMs.TryRemove(RefusalEndpointKey(c.Url), out _);
                        FeatureLatchCodes.TryRemove(RefusalEndpointKey(c.Url), out _);
                    }
                    var why = code is 401 or 403 ? RefusalErrorCode(body) : "";
                    var (level, hint) = code switch
                    {
                        >= 200 and < 300 or 304 => (ms > SelfTestSlowMs ? "warn" : "ok", ms > SelfTestSlowMs ? "Canal lent : ses données arrivent en retard." : ""),
                        401 or 403 => ("ko", "Refusé par Athena : formule, droits ou session (voir les contrôles serveur)."),
                        404 => ("warn", "Absent du site : mod plus récent que le site, ou l'inverse."),
                        429 => ("warn", "Athena demande de ralentir : trop d'appels."),
                        503 => ("ko", "Maintenance ou surcharge d'Athena."),
                        _ => ("ko", "Erreur d'Athena sur ce canal."),
                    };
                    if (why.Length > 0)
                        hint = RefusalHint(why) + (stopped ? " Canal arrêté jusqu'à une nouvelle liaison." : "");
                    results[i] = string.Join('\t', "C", level, SelfTestField(c.Key),
                        SelfTestField("HTTP " + code + (why.Length > 0 ? " " + why : "") + " · " + ms + " ms"), SelfTestField(hint));
                }
                catch (Exception ex)
                {
                    results[i] = string.Join('\t', "C", "ko", SelfTestField(c.Key),
                        SelfTestField(ex is OperationCanceledException ? "délai dépassé" : "réseau"), SelfTestField("Pas de réponse d'Athena sur ce canal."));
                }
                finally
                {
                    if (entered) gate.Release();
                }
            })).ToArray();
            try { Task.WaitAll(tasks, TimeSpan.FromSeconds(SelfTestTimeoutSeconds * 3)); } catch { /* chaque tâche range son résultat */ }
            // Tâches encore en cours ou en attente : annulées (résultat « délai dépassé » ci-dessous).
            try { overall.Cancel(); } catch { /* ignore */ }
            for (var i = 0; i < results.Length; i++)
                lines.Add(results[i] ?? string.Join('\t', "C", "ko", SelfTestField(channels[i].Key), "délai dépassé", ""));
        }

        // Liaison ou fonctions arrêtées sur un refus définitif (Extension_RefusalLatch.cs).
        if (_authLatchCode.Length > 0)
            Add("L", "ko", "Liaison arrêtée", _authLatchCode, RefusalHint(_authLatchCode) + " Reprise après une nouvelle liaison.");
        foreach (var key in FeatureLatchUntilMs.Keys)
        {
            var why = FeatureLatchCodes.TryGetValue(key, out var c) && c.Length > 0 ? c : "feature_unavailable";
            Add("L", "warn", why == "feature_unavailable" ? "Fonction hors offre" : "Canal refusé (" + why + ")", key,
                RefusalHint(why) + " Nouvel essai toutes les 30 min.");
        }

        // 3. État local de la DLL.
        var s = JournalSummarize();
        var pending = s.Queued + s.Retry;
        Add("L", s.Failed > 0 ? "warn" : (pending > 20 ? "warn" : "ok"), "File d'envoi",
            pending + " en attente · " + s.Failed + " en échec · " + s.Done + " envoyés",
            s.Failed > 0 ? "Des envois ont échoué définitivement (voir l'app Réseau du téléphone)." : (pending > 20 ? "Les envois s'accumulent : Athena lente ou liaison coupée." : ""));
        if (IsRateLimitedNow())
            Add("L", "warn", "Envois", "en pause (Athena a demandé d'attendre)", "Reprise automatique dans quelques secondes.");
        else if (IsNetworkBackoffNow())
            Add("L", "warn", "Envois", "en pause (réseau)", "Plusieurs envois ont échoué : nouvel essai automatique bientôt.");
        if (s.H429 + s.H5xx + s.Timeouts > 0)
            Add("L", "warn", "Incidents récents", s.H429 + " × 429 · " + s.H5xx + " × 5xx · " + s.Timeouts + " délais", "Athena a été surchargée ou injoignable ces dernières minutes.");

        return "OK|" + string.Join('\n', lines);
    }

    /// <summary>Conseil au joueur selon le motif de refus d'Athena.</summary>
    private static string RefusalHint(string reason) => reason.ToLowerInvariant() switch
    {
        "unauthorized" => "Clé ou session refusée : Lier mon compte (nouveau code sur le site).",
        "feature_unavailable" => "La formule de la communauté n'inclut pas cette fonction.",
        "atak_blocked" => "Joueur bloqué par la communauté : contactez un administrateur.",
        "account_disabled" => "Compte désactivé : contactez un administrateur.",
        "tenant_context_required" or "no_tenant" => "Jeu rattaché à aucune communauté : Lier mon compte.",
        "steam_not_linked" => "Steam non lié au compte : Lier mon compte depuis ce jeu.",
        _ => "Droits insuffisants pour ce canal.",
    };

    private static string SelfTestString(JsonElement e, string name, string fallback) =>
        e.TryGetProperty(name, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? fallback : fallback;

    /// <summary>Champ sur une ligne : ni tabulation, ni retour à la ligne, ni « | » (séparateur des réponses SQF).</summary>
    private static string SelfTestField(string? v) =>
        (v ?? "").Replace('\t', ' ').Replace('\n', ' ').Replace('\r', ' ').Replace('|', '/');
}
