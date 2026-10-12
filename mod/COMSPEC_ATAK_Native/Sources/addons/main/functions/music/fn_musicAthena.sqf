/*
    App Musique : bibliothèque musicale d'Athena (site), lue par COMSPEC Link (commande AthenaMusic, DLL 2.0.73+).
      ["load", vue] : vue = "global" (playlist globale de la plateforme), "community" (bibliothèque communautaire,
                      toute la plateforme), "org" (bibliothèque de mon organisation), "mine" (mes envois),
                      "playlists" (playlists publiques, de mon organisation et les miennes), "pl:<id>" (pistes d'une playlist).
      La recherche (uiNamespace COMSPEC_ATAK_MusicUi "athQ") s'applique aux vues de pistes.
    État : uiNamespace COMSPEC_ATAK_MusicAth = HashMap vue → [éléments, heure de synchro, erreur, chargement]
           piste    = ["lib", URL signée, titre, durée s, artiste, id, envoyé par]
           playlist = [id, nom, propriétaire, nombre de pistes, type global|public|org|private, description]
    L'app Musique est redessinée à la fin.
*/
params [["_act", "load"], ["_view", "global"]];
private _ath = uiNamespace getVariable ["COMSPEC_ATAK_MusicAth", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_MusicAth", _ath];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MUSIC") then { ["MUSIC"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
if (_act isNotEqualTo "load") exitWith {};
private _prev = _ath getOrDefault [_view, [[], "", "", false]];
if (_prev select 3) exitWith {};
_ath set [_view, [_prev select 0, _prev select 1, "", true]];
call _render;
[_view, _ath, _render] spawn {
    params ["_view", "_ath", "_render"];
    private _res = { params ["_r"]; if (!isNil "comspec_overwatch_connect_fnc_extResult") then { [_r] call comspec_overwatch_connect_fnc_extResult } else { _r } };
    private _split = { params ["_s"]; if (!isNil "comspec_overwatch_connect_fnc_splitKeepEmpty") then { [_s, toString [9]] call comspec_overwatch_connect_fnc_splitKeepEmpty } else { _s splitString toString [9] } };
    private _q = (uiNamespace getVariable ["COMSPEC_ATAK_MusicUi", createHashMap]) getOrDefault ["athQ", ""];
    if (_view isEqualTo "playlists") then { _q = ""; };
    private _raw = [(["COMSPECExtension", "AthenaMusic", [_view, _q, "80"]] call comspec_atak_native_fnc_extAwait)] call _res;
    if !(_raw isEqualType "") then { _raw = str _raw; };
    private _now = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
    if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith {
        private _err = switch (true) do {
            case (_raw isEqualTo "" || {(_raw find "commande inconnue") >= 0} || {(_raw find "unknown") >= 0}): { "La DLL COMSPEC Link ne connaît pas encore la bibliothèque musicale : mettez-la à jour (2.0.73 ou plus)." };
            case ((_raw find "not_connected") >= 0 || {(_raw find "unauthorized") >= 0}): { "Connexion Athena requise : liez le téléphone à votre compte (app Athena)." };
            case ((_raw find "migration_required") >= 0): { "Bibliothèque musicale pas encore installée sur Athena (migrations à lancer)." };
            case ((_raw find "not_found") >= 0): { "Playlist introuvable ou privée." };
            default { format ["Athena ne répond pas (%1).", _raw select [0, 60]] };
        };
        _ath set [_view, [(_ath getOrDefault [_view, [[]]]) select 0, _now, _err, false]];
        call _render;
    };
    private _items = [];
    {
        private _c = [_x] call _split;
        switch (_c param [0, ""]) do {
            // T id titre artiste durée url envoyeur
            case "T": { if ((count _c) >= 6 && {(_c select 5) isNotEqualTo ""}) then { _items pushBack ["lib", _c select 5, _c select 2, parseNumber (_c select 4), _c select 3, _c select 1, _c param [6, ""]]; }; };
            // P id nom propriétaire nombre type description
            case "P": { if ((count _c) >= 6) then { _items pushBack [_c select 1, _c select 2, _c select 3, parseNumber (_c select 4), _c select 5, _c param [6, ""]]; }; };
        };
    } forEach ((_raw select [3]) splitString toString [10]);
    _ath set [_view, [_items, _now, "", false]];
    call _render;
};
true
