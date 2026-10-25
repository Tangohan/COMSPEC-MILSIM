using System;
using System.Globalization;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

/// <summary>
/// Données remontées choisies par la communauté (Athena, Configuration ATAK > Données remontées).
/// GET /api/atak/data-flows (GetDataFlows, lu par COMSPEC Link toutes les 30 s) : chaque flux coupé n'est plus
/// mis en file ni envoyé, la DLL et le site se soulagent. Comptes rendus, photos, MEDEVAC, messages : toujours envoyés.
///   positions     positions des joueurs en direct (coupées : un signe de vie par minute, pour la présence)
///   vehicles      véhicules et drones suivis, manifestes de vol
///   combat        pertes pour la relecture (télémétrie combat)
///   phone_mirror  notifications du téléphone vers « Mon téléphone ATAK »
///   context       météo, SIGINT, état logistique
/// Avant la première réponse (ou site plus ancien, 404) : tout reste ouvert, comme avant.
/// </summary>
public static partial class Extension
{
    private static volatile bool _flowPositions = true;
    private static volatile bool _flowVehicles = true;
    private static volatile bool _flowCombat = true;
    private static volatile bool _flowPhoneMirror = true;
    private static volatile bool _flowContext = true;
    private static long _flowPositionBeatMs;
    private static long _flowSkipped;
    private const long FlowPositionsOffBeatMs = 60_000;

    private static string ServeDataFlows()
    {
        return ServePollGet("GetDataFlows", _baseUrl + "/api/atak/data-flows", (body, code) =>
        {
            if (code < 200 || code >= 300) return PollHttpErr(code);
            return "OK|" + ApplyDataFlowsJson(body);
        });
    }

    private static string ApplyDataFlowsJson(string body)
    {
        var preset = "";
        try
        {
            using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(body) ? "{}" : body);
            var root = doc.RootElement;
            if (root.ValueKind == JsonValueKind.Object && root.TryGetProperty("flows", out var flows)
                && flows.ValueKind == JsonValueKind.Object)
            {
                static bool Read(JsonElement f, string key, bool fallback)
                {
                    if (!f.TryGetProperty(key, out var v)) return fallback;
                    return v.ValueKind switch
                    {
                        JsonValueKind.True => true,
                        JsonValueKind.False => false,
                        JsonValueKind.Number => v.TryGetInt32(out var n) ? n != 0 : fallback,
                        _ => fallback
                    };
                }
                _flowPositions = Read(flows, "positions", true);
                _flowVehicles = Read(flows, "vehicles", true);
                _flowCombat = Read(flows, "combat", true);
                _flowPhoneMirror = Read(flows, "phone_mirror", true);
                _flowContext = Read(flows, "context", true);
            }
            if (root.ValueKind == JsonValueKind.Object && root.TryGetProperty("preset", out var p)
                && p.ValueKind == JsonValueKind.String)
                preset = (p.GetString() ?? "").Replace("\t", " ").Replace("\n", " ");
        }
        catch
        {
            // Réponse illisible : réglages gardés tels quels.
        }
        return FormatDataFlows(preset);
    }

    private static string FormatDataFlows(string preset)
    {
        var sb = new StringBuilder();
        sb.Append("positions=").Append(_flowPositions ? '1' : '0');
        sb.Append("\tvehicles=").Append(_flowVehicles ? '1' : '0');
        sb.Append("\tcombat=").Append(_flowCombat ? '1' : '0');
        sb.Append("\tphone_mirror=").Append(_flowPhoneMirror ? '1' : '0');
        sb.Append("\tcontext=").Append(_flowContext ? '1' : '0');
        sb.Append("\tpreset=").Append(preset);
        sb.Append("\tskipped=").Append(Interlocked.Read(ref _flowSkipped).ToString(CultureInfo.InvariantCulture));
        return sb.ToString();
    }

    private static bool FlowSkip()
    {
        Interlocked.Increment(ref _flowSkipped);
        return false;
    }

    /// <summary>Envoi (POST unitaire mis en file ou lot) permis par les flux de la communauté.</summary>
    private static bool DataFlowAllowsUrl(string url, string jsonBody)
    {
        if (string.IsNullOrEmpty(url)) return true;
        if (IsPositionEndpoint(url))
        {
            if (_flowPositions) return true;
            // Positions coupées : un envoi par minute garde le joueur « présent » côté site, sans suivi en direct.
            var now = Environment.TickCount64;
            var last = Interlocked.Read(ref _flowPositionBeatMs);
            if (last != 0 && now - last < FlowPositionsOffBeatMs) return FlowSkip();
            Interlocked.Exchange(ref _flowPositionBeatMs, now);
            return true;
        }
        if (url.Contains("/api/atak/vehicles", StringComparison.OrdinalIgnoreCase)
            && !url.Contains("/service", StringComparison.OrdinalIgnoreCase))
            return _flowVehicles || FlowSkip();
        if (url.Contains("/api/atak/flight-manifest", StringComparison.OrdinalIgnoreCase))
            return _flowVehicles || FlowSkip();
        if (IsWeatherEndpoint(url)
            || url.Contains("/api/logistics/update", StringComparison.OrdinalIgnoreCase)
            || (url.Contains("/api/atak/sigint", StringComparison.OrdinalIgnoreCase)
                && !url.Contains("/api/atak/sigint/", StringComparison.OrdinalIgnoreCase)))
            return _flowContext || FlowSkip();
        if (url.Contains("/api/atak/explosive-timers", StringComparison.OrdinalIgnoreCase)
            && !_flowPhoneMirror
            && (jsonBody ?? "").Contains("\"atak_notifs\"", StringComparison.Ordinal))
            return FlowSkip();
        return true;
    }

    /// <summary>Événement EmitTelemetry (type du bus) permis par les flux de la communauté.</summary>
    private static bool DataFlowAllowsType(string type)
    {
        switch (type)
        {
            case "pos":
                return _flowPositions || FlowSkip();
            case "veh":
            case "flight":
                return _flowVehicles || FlowSkip();
            case "combat":
                return _flowCombat || FlowSkip();
            case "weather":
            case "sigint":
            case "logstat":
                return _flowContext || FlowSkip();
            default:
                return true;
        }
    }
}
