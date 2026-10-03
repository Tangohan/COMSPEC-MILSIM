/*
    Photo de profil Athena d'un joueur, téléchargée par la DLL d'Overwatch (profil site → image locale).
    Renvoie le chemin local si déjà en cache, sinon "" et lance le téléchargement en arrière-plan
    (nouvel essai au plus toutes les 2 min). Sans Overwatch : toujours "".
    Params : [unité]
*/
params [["_unit", player]];
if (isNull _unit || {!isPlayer _unit} || {!([] call comspec_atak_native_fnc_bridge)}) exitWith { "" };
if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { "" };
private _uid = getPlayerUID _unit;
if (_uid isEqualTo "") exitWith { "" };
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_Avatars", createHashMap];
(_cache getOrDefault [_uid, ["", -1e9]]) params ["_path", "_triedAt"];
if (_path isNotEqualTo "") exitWith { _path };
if (diag_tickTime - _triedAt < 120) exitWith { "" };
_cache set [_uid, ["", diag_tickTime]];
uiNamespace setVariable ["COMSPEC_ATAK_Avatars", _cache];
[_uid] spawn {
    params ["_uid"];
    private _res = { params ["_r"]; if (_r isEqualType []) then { _r param [0, ""] } else { _r } };
    private _tenant = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
    private _info = (["COMSPECExtension" callExtension ["GetPlayerAvatarInfo", [_uid, _tenant]]] call _res) splitString "|";
    if ((_info param [0, ""]) isNotEqualTo "OK") exitWith {};
    private _cols = (_info param [1, ""]) splitString (toString [9]);
    // Qualifications (colonne « Q: », DLL récente) : [[nom, niveau, état, expiration], ...] ; absente = DLL ancienne.
    private _q = _cols findIf { (_x select [0, 2]) isEqualTo "Q:" };
    private _quals = uiNamespace getVariable ["COMSPEC_ATAK_Quals", createHashMap];
    _quals set [_uid, if (_q < 0) then { "olddll" } else { (((_cols select _q) select [2]) splitString ";") apply { private _f = _x splitString "~"; [_f param [0, ""], _f param [1, ""], _f param [2, ""], _f param [3, ""]] } }];
    uiNamespace setVariable ["COMSPEC_ATAK_Quals", _quals];
    private _url = _cols param [2, ""];
    if (_url isEqualTo "" || {(_url select [0, 2]) isEqualTo "Q:"}) exitWith {
        private _page = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
        if (_page isEqualTo "PROFILE") then { [{ ["PROFILE"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
    };
    private _dl = (["COMSPECExtension" callExtension ["DownloadBriefingSlideImage", [_url, "avatar_" + _uid]]] call _res) splitString "|";
    if ((_dl param [0, ""]) isNotEqualTo "OK" || {(_dl param [1, ""]) isEqualTo ""}) exitWith {};
    private _cache = uiNamespace getVariable ["COMSPEC_ATAK_Avatars", createHashMap];
    _cache set [_uid, [_dl select 1, diag_tickTime]];
    uiNamespace setVariable ["COMSPEC_ATAK_Avatars", _cache];
    // Redessiner la page qui attend l'image.
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _page = _s getOrDefault ["activePage", ""];
    if (_page in ["ATHENA", "GROUP", "PROFILE"]) then { [{ [_this] call comspec_atak_native_fnc_pageRender; }, _page] call CBA_fnc_execNextFrame; };
};
""
