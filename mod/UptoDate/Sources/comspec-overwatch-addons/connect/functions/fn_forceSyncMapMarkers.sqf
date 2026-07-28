/*
    Pousse vers Athena tous les marqueurs carte BCE / Marker Widget / Dropper / cTab.
    Utilisé au démarrage, en poll, et par « Forcer une resynchronisation ».
    Params: [_announce] — true = feedback joueur (nombre envoyé).
    Retour: nombre de marqueurs Arma tentés.
*/
params [["_announce", false, [true]]];

if (!hasInterface) exitWith { 0 };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { 0 };
if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith {
    if (_announce) then {
        ["La synchronisation des marqueurs est désactivée dans les réglages.", "link", "warn", true] call comspec_overwatch_connect_fnc_announce;
    };
    0
};

// Invalider les snapshots pour forcer un renvoi complet
missionNamespace setVariable ["COMSPEC_Athena_MapMarkerSnap", createHashMap, false];
missionNamespace setVariable ["COMSPEC_Athena_BceMarkerQuickSnap", createHashMap, false];
missionNamespace setVariable ["COMSPEC_Athena_CtabMarkerSnap", createHashMap, false];

private _count = 0;
private _markers = allMapMarkers;
if (!(_markers isEqualType [])) then { _markers = []; };

{
    private _n = _x;
    if (_n isEqualTo "") then { continue };

    private _ul = toLower _n;
    private _isBce = (
        (_ul find "_user_defined") >= 0
        || {(_ul find "user_defined") >= 0}
        || {(_ul find "_defined #") >= 0}
        || {(_ul find "_ictab_defined") >= 0}
        || {(_ul find "ictab_defined") >= 0}
    );
    if (!_isBce && {!isNil "BCE_cTab_Marker_Sync"}) then {
        private _sync = BCE_cTab_Marker_Sync;
        if (_sync isEqualType "" && {_sync isNotEqualTo ""} && {(_n find _sync) == 0}) then {
            _isBce = true;
        };
    };
    // Aussi les marqueurs sans underscore (Dropper rare) déjà synchronisables
    if (!_isBce) then {
        if (!isNil "comspec_overwatch_connect_fnc_isSyncableMapMarker") then {
            if ([_n] call comspec_overwatch_connect_fnc_isSyncableMapMarker) then {
                if ((_n select [0, 1]) == "_") then { _isBce = true; };
            };
        };
    };
    if (!_isBce) then { continue };

    // Envoi forcé : contourne hasTerminal / canTransmit (tablette déjà ouverte)
    [_n, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
    _count = _count + 1;
} forEach _markers;

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
};

[] call comspec_overwatch_connect_fnc_queueMapMarker; // flush file d’attente

if (_announce) then {
    if (_count < 1) then {
        ["Aucun marqueur ATAK / Marker Widget trouvé à transmettre.", "link", "info", true] call comspec_overwatch_connect_fnc_announce;
    } else {
        [format ["%1 marqueur(s) renvoyé(s) vers Athena.", _count], "link", "info", true] call comspec_overwatch_connect_fnc_announce;
    };
    [format ["[Marqueurs] Force sync — %1 marqueur(s) BCE/Widget", _count], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
};

_count
