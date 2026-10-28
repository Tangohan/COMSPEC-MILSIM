/*
    Module Zeus/Eden : présentation des documents / fiches SSE.

    Impose le modèle (papier, bandeau, titres, pied de page) pour toute la mission.
    Les champs libres remplacent le modèle lorsqu’ils sont renseignés.
*/
// Toutes les formes d'appel de BI, dont ["init", [logic, activé, posé par Zeus]] des modules Eden (is3DEN = 1).
private _args = _this call comspec_sse_fnc_moduleArgs;
if (_args isEqualTo []) exitWith { true };
_args params ["_logic", "", "_activated"];
if (!_activated) exitWith { false };

// Client : le serveur applique et supprime la logique (un deleteVehicle ici l'effaçait avant lui).
if (!isServer && { isMultiplayer }) exitWith { true };

if (isNil "comspec_sse_fnc_getDocumentChrome") exitWith {
    ["Présentation documents : pack SSE requis", "WARN"] call comspec_sse_fnc_log;
    deleteVehicle _logic;
    false
};

private _prefab = _logic getVariable ["Prefab", "standard_restreint"];
if (!(_prefab isEqualType "")) then { _prefab = "standard_restreint"; };
_prefab = toLower (trim _prefab);

private _chrome = ["apply_prefab", _prefab] call comspec_sse_fnc_getDocumentChrome;

private _overrides = createHashMap;
{
    _x params ["_key", "_var"];
    private _v = _logic getVariable [_var, ""];
    if (_v isEqualType "" && {(trim _v) isNotEqualTo ""}) then {
        _overrides set [_key, trim _v];
    };
} forEach [
    ["paper_style", "PaperStyle"],
    ["banner", "Banner"],
    ["title_person", "TitlePerson"],
    ["title_docs", "TitleDocs"],
    ["subtitle_dossier", "SubtitleDossier"],
    ["footer", "Footer"],
    ["btn_consult", "BtnConsult"],
    ["btn_transmit", "BtnTransmit"],
    ["btn_close", "BtnClose"]
];

if ((count _overrides) > 0) then {
    _chrome = ["set", _overrides] call comspec_sse_fnc_getDocumentChrome;
};

[format [
    "Présentation documents SSE : modèle %1, papier %2",
    _chrome getOrDefault ["prefab", _prefab],
    _chrome getOrDefault ["paper_style", "clean"]
], "INFO"] call comspec_sse_fnc_log;

deleteVehicle _logic;
true
