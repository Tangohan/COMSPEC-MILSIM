/*
    Dépose un instantané technique (URL, tenant, état de liaison, version, dernière position)
    dans le journal, catégorie "system" — jamais masquable, pour toujours pouvoir diagnostiquer.
    La clé API n'est jamais affichée en clair (longueur seulement).
*/
if (!hasInterface) exitWith {};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
private _tenant = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
private _keyLen = count (missionNamespace getVariable ["comspec_overwatch_api_key", ""]);
private _version = [] call comspec_overwatch_connect_fnc_getModVersion;
private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
private _lastSync = missionNamespace getVariable ["COMSPEC_LastPositionSync", -1];
private _enabled = missionNamespace getVariable ["comspec_overwatch_enabled", true];

private _extStatus = [] call comspec_overwatch_connect_fnc_extensionStatus;
_extStatus params ["_extOk", "_extCode", "_ping", ["_extErr", 0]];
private _extLabel = if (!_extOk) then {
    if (_extCode isEqualTo "not_loaded") then {
        format ["NON CHARGÉE (réponse vide, err Arma %1 — souvent BattlEye / mauvais dossier mod, PAS un test de taille DLL)", _extErr]
    } else {
        format ["RÉPONSE INVALIDE : %1", _ping]
    }
} else {
    if ((_ping select [0, 3]) == "OK|") then { _ping select [3, (count _ping) - 3] } else { _ping };
};

private _lines = [
    "[Debug] --- Instantané technique ---",
    format ["[Debug] Version mod : %1", _version],
    format ["[Debug] Extension DLL : %1", _extLabel],
    format ["[Debug] Extension status : %1 (err Arma %2)", _extCode, missionNamespace getVariable ["COMSPEC_LastExtError", _extErr]],
    format ["[Debug] Overwatch activé : %1", if (_enabled) then { "oui" } else { "non" }],
    format ["[Debug] URL portail : %1", if (_url == "") then { "(vide)" } else { _url }],
    format ["[Debug] Tenant : %1", if (_tenant == "") then { "(défaut)" } else { _tenant }],
    format ["[Debug] Clé API : %1", if (_keyLen > 0) then { format ["renseignée (%1 car.)", _keyLen] } else { "(absente — liez le compte via un code Athena)" }],
    format ["[Debug] État liaison : %1%2", _state, if (_detail != "") then { format [" — %1", _detail] } else { "" }],
    format ["[Debug] Dernière position envoyée : %1", if (_lastSync >= 0) then { format ["il y a %1 s", round (diag_tickTime - _lastSync)] } else { "jamais" }]
];

private _profileLines = [] call comspec_overwatch_connect_fnc_profileReport;
_lines append _profileLines;

{ [_x, "system"] call comspec_overwatch_connect_fnc_appendLinkLog; } forEach _lines;
