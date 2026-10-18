/*
    Volume échangé avec Athena par canal, lu en mémoire dans COMSPEC Link 2.0.88+ (SyncStats, sans requête réseau).
    Chaque canal est un chemin d'API, nommé ici comme le joueur le voit (positions, fiche opérateur, messages…).
    « Inchangé » : Athena a répondu que la liste n'avait pas bougé depuis le dernier passage, rien n'a été retéléchargé.
    Retourne un HashMap :
      ok (false si la DLL est absente ou trop ancienne), up (s depuis le lancement), out / in (octets envoyés / reçus),
      calls, same (réponses inchangées), saved (octets évités), wOut / wIn / span (les 5 dernières minutes et leur durée en s),
      rows : [[libellé, chemin, appels, envoyés, reçus, inchangées, âge du dernier appel (s), dernier code HTTP], ...]
      triés du plus lourd au plus léger.
    Mis en cache 2 s (l'app Réseau se redessine toutes les 5 s).
*/
private _out = createHashMapFromArray [["ok", false], ["rows", []]];
if !([] call comspec_atak_native_fnc_bridge) exitWith { _out };
private _prev = missionNamespace getVariable ["COMSPEC_ATAK_SyncStats", createHashMap];
if ((count _prev) > 0 && {diag_tickTime - (_prev getOrDefault ["at", -1e9]) < 2}) exitWith { _prev };
if (missionNamespace getVariable ["COMSPEC_ATAK_SyncStatsNo", false]) exitWith { _out };

private _raw = ("COMSPECExtension" callExtension ["SyncStats", []]) select 0;
if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith {
    // COMSPEC Link plus ancien : plus redemandé de la mission.
    missionNamespace setVariable ["COMSPEC_ATAK_SyncStatsNo", true];
    _out
};
private _labels = createHashMapFromArray [
    ["/api/atak/position", "Ma position"],
    ["/api/atak/telemetry/batch", "Positions et télémétrie (lots)"],
    ["/api/atak/operator/sync", "Fiche opérateur"],
    ["/api/atak/operator/register", "Fiche opérateur (connexion)"],
    ["/api/chat", "Messages"],
    ["/api/chat/channels", "Canaux de discussion"],
    ["/api/atak/markers", "Marqueurs"],
    ["/api/atak/marker", "Marqueurs (envoi)"],
    ["/api/map-shapes", "Formes sur la carte"],
    ["/api/atak/orders", "Ordres"],
    ["/api/atak/zones", "Zones tactiques"],
    ["/api/atak/medical-alerts", "Alertes médicales"],
    ["/api/atak/tactical-alerts", "Alertes tactiques"],
    ["/api/atak/waypoints", "Points de passage"],
    ["/api/atak/mission-plan", "Plan de mission"],
    ["/api/atak/explosive-timers/commands", "Charges explosives"],
    ["/api/atak/units", "Unités alliées"],
    ["/api/units", "Unités alliées"],
    ["/api/pings", "Pings"],
    ["/api/cas", "Appui aérien"],
    ["/api/atak/relays/sync", "Relais"],
    ["/api/atak/mod-modules", "Modules du serveur"],
    ["/api/atak/experience", "Expérience communauté"],
    ["/api/atak/roleplay-stats", "Réglages roleplay"],
    ["/api/atak/marker-detection-rules", "Règles des marqueurs"],
    ["/api/atak/sync/roster", "Effectif"],
    ["/api/atak/send-queue/report", "Journal d'envoi"],
    ["/api/atak/device-logs", "Journal du téléphone"],
    ["/api/atak/screen-time/batch", "Temps d'écran"],
    ["/api/atak/distance/batch", "Distances"],
    ["/api/atak/garmin", "Montre Garmin"],
    ["/api/atak/vehicles", "Véhicules"],
    ["/api/atak/whoami", "Liaison du compte"],
    ["/api/atak/server-presence", "Serveur de jeu"],
    ["/api/recon/images", "Photos"],
    ["/api/atak/terrain/chunk", "Relevé du terrain"],
    ["/api/atak/scene/ingest", "Relevé de la scène"],
    ["/api/atak/geo/ingest", "Relevé réseau"]
];
private _lines = (_raw select [3]) splitString toString [10];
((_lines param [0, ""]) splitString toString [9]) params [["_up", "1"], ["_o", "0"], ["_i", "0"], ["_c", "0"], ["_nm", "0"], ["_sv", "0"], ["_wo", "0"], ["_wi", "0"], ["_sp", "1"]];
private _rows = [];
{
    (_x splitString toString [9]) params [["_path", ""], ["_calls", "0"], ["_bo", "0"], ["_bi", "0"], ["_same", "0"], ["_saved", "0"], ["_age", "0"], ["_code", "0"]];
    if (_path isEqualTo "") then { continue };
    private _lbl = _labels getOrDefault [_path, ""];
    if (_lbl isEqualTo "") then { _lbl = (_path splitString "/") select { !(_x in ["api", "atak", "{id}"]) } joinString " "; };
    _rows pushBack [_lbl, _path, parseNumber _calls, parseNumber _bo, parseNumber _bi, parseNumber _same, parseNumber _age, parseNumber _code];
} forEach (_lines select [1]);

_out = createHashMapFromArray [
    ["ok", true], ["at", diag_tickTime], ["up", (parseNumber _up) max 1], ["out", parseNumber _o], ["in", parseNumber _i],
    ["calls", parseNumber _c], ["same", parseNumber _nm], ["saved", parseNumber _sv],
    ["wOut", parseNumber _wo], ["wIn", parseNumber _wi], ["span", (parseNumber _sp) max 1], ["rows", _rows]
];
missionNamespace setVariable ["COMSPEC_ATAK_SyncStats", _out];
_out
