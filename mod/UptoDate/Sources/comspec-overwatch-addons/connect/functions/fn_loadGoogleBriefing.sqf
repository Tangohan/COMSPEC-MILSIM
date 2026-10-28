/*
    Lance le chargement d'une présentation Google Slides publique via l'extension.
    Params: [_url, _index, _broadcast]
      _broadcast (défaut true) : diffuse aux autres clients via CBA.
*/
params [
    ["_url", "", [""]],
    ["_index", 0, [0]],
    ["_broadcast", true, [true]]
];

if (!hasInterface) exitWith { "" };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { "" };

_url = trim _url;
if (_url isEqualTo "") exitWith {
    ["COMSPEC_Warning", ["Collez un lien Google Slides avant de charger."]] call comspec_overwatch_connect_fnc_showNotification;
    ""
};

private _lower = toLowerANSI _url;
if ((_lower find "https://docs.google.com/presentation/d/") isNotEqualTo 0
    && {(_lower find "http://docs.google.com/presentation/d/") isNotEqualTo 0}
) exitWith {
    ["COMSPEC_Warning", ["Lien invalide. Utilisez un lien Google Slides partagé."]] call comspec_overwatch_connect_fnc_showNotification;
    ""
};

"COMSPECExtension" callExtension ["CancelGoogleDeck", []];

private _requestId = format [
    "gdeck_%1_%2",
    clientOwner,
    floor (diag_tickTime * 1000)
];
private _newDeck = (missionNamespace getVariable ["COMSPEC_GoogleBriefingUrl", ""]) isNotEqualTo _url;
missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", _requestId];
missionNamespace setVariable ["COMSPEC_GoogleBriefingUrl", _url];
missionNamespace setVariable ["COMSPEC_GoogleBriefingPendingIndex", floor _index];

// ATAK natif : la présentation s'affiche dans l'app BRIEFING du téléphone (COMSPEC_GoogleBriefing*), jamais dans l'ancien
// tableau de briefing ouvert par-dessus le jeu (il apparaissait aussi aux Zeus et aux joueurs sans téléphone, JIP compris).
private _native = isClass (configFile >> "CfgPatches" >> "comspec_atak_native_main");
if (_native) then {
    // Une seule notification par présentation (pas à chaque diapositive).
    if (_newDeck && {[player] call comspec_atak_native_fnc_hasDevice}) then {
        ["INFO", "Présentation de briefing diffusée : ouvrez l'app Briefing.", 8, 30, "BRIEFING"] call comspec_atak_native_fnc_notify;
    };
};
// Ouverture uniquement sur action locale (_broadcast) : une diffusion reçue ne fait que mettre l'état à jour,
// sans dialogue par-dessus le jeu (Zeus, joueurs sans téléphone, en plein combat).
if (!_native
    && {_broadcast}
    && {isNull (uiNamespace getVariable ["COMSPEC_ATAK_Briefing_group", controlNull])}
    && {isNull (findDisplay 9970)}
) then {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openBriefing"
        && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
    ) then {
        [] call comspec_overwatch_atak_athena_fnc_athena_openBriefing;
    } else {
        // Repli hors ATAK uniquement
        createDialog "COMSPEC_Briefing_Dialog";
    };
};

["COMSPEC_Info", ["Téléchargement de la présentation Google…"]] call comspec_overwatch_connect_fnc_showNotification;

private _raw = ["COMSPECExtension" callExtension ["LoadGoogleDeck", [_url, str (floor _index), _requestId]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "") exitWith {
    missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", ""];
    ["COMSPEC_Warning", ["Extension indisponible pour charger Google Slides."]] call comspec_overwatch_connect_fnc_showNotification;
    ""
};

private _parsed = parseSimpleArray _raw;
private _status = _parsed param [0, ""];
if (_status isNotEqualTo "accepted") exitWith {
    missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", ""];
    private _reason = _parsed param [1, "unknown"];
    private _msg = switch (_reason) do {
        case "invalid_url": { "Lien Google Slides invalide." };
        default { "Impossible de démarrer le chargement." };
    };
    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
    ""
};

if (_broadcast) then {
    ["show", _url, floor _index] call comspec_overwatch_connect_fnc_broadcastGoogleBriefingState;
};

_requestId
