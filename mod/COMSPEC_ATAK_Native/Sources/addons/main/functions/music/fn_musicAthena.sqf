/*
    App Musique : bibliothèque musicale d'Athena (site), lue par COMSPEC Link (commande AthenaMusic, DLL 2.0.73+).
      ["load", vue] : vue = "global" (playlist globale de la plateforme), "community" (bibliothèque communautaire,
                      toute la plateforme), "org" (bibliothèque de mon organisation), "mine" (mes envois),
                      "playlists" (playlists publiques, de mon organisation et les miennes), "pl:<id>" (pistes d'une playlist).
      La recherche (uiNamespace COMSPEC_ATAK_MusicUi "athQ") s'applique aux vues de pistes.
      ["ensure", vue] : recharge la vue si elle n'a jamais été chargée, date de plus de 60 s (20 s après une erreur)
                        ou si un chargement est resté bloqué : un morceau mis en ligne sur le site apparaît sans redémarrer.
    État : uiNamespace COMSPEC_ATAK_MusicAth = HashMap vue → [éléments, heure de synchro, erreur, chargement,
           diag_tickTime de la dernière réponse, diag_tickTime du début du chargement]
           piste    = ["lib", URL signée, titre, durée s, artiste, id, envoyé par]
           playlist = [id, nom, propriétaire, nombre de pistes, type global|public|org|private, description]
    Un chargement ne peut plus rester affiché indéfiniment : uiNamespace survit à la fin de mission alors que le
    script qui attendait la réponse est arrêté. Au-delà de BUSY_MAX secondes il est considéré comme perdu, et un
    garde-fou le clôt avec un message d'erreur et le bouton ACTUALISER. La liste déjà reçue reste affichée.
    L'app Musique est redessinée à la fin.
*/
#define BUSY_MAX 35
params [["_act", "load"], ["_view", "global"]];
private _ath = uiNamespace getVariable ["COMSPEC_ATAK_MusicAth", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_MusicAth", _ath];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MUSIC") then { ["MUSIC"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _prev = _ath getOrDefault [_view, [[], "", "", false, -1e9, -1e9]];
// Chargement en cours depuis trop longtemps (ou d'une ancienne mission / version) : perdu.
private _stale = (_prev select 3) && {diag_tickTime - (_prev param [5, -1e9]) > BUSY_MAX};
if (_act isEqualTo "busy") exitWith { (_prev select 3) && {!_stale} };
if (_act isEqualTo "ensure") then {
    private _age = diag_tickTime - (_prev param [4, -1e9]);
    if (_stale || {!(_prev select 3) && {_age >= ([60, 20] select ((_prev select 2) isNotEqualTo ""))}}) then { _act = "load"; };
};
if (_act isNotEqualTo "load") exitWith {};
if ((_prev select 3) && {!_stale}) exitWith {};
private _start = diag_tickTime;
_ath set [_view, [_prev select 0, _prev select 1, "", true, _prev param [4, -1e9], _start]];
call _render;
// Garde-fou : si la réponse n'est pas arrivée (script arrêté, DLL muette), on rend la main avec une erreur.
[{
    params ["_ath", "_view", "_start", "_render"];
    private _s = _ath getOrDefault [_view, []];
    if ((_s param [3, false]) && {(_s param [5, -1]) isEqualTo _start}) then {
        _ath set [_view, [_s select 0, _s select 1, "Athena n'a pas répondu à temps. Réessayez avec ACTUALISER (nouvel essai automatique dans 20 s).", false, diag_tickTime, _start]];
        call _render;
    };
}, [_ath, _view, _start, _render], BUSY_MAX + 1] call CBA_fnc_waitAndExecute;
[_view, _ath, _render, _start] spawn {
    params ["_view", "_ath", "_render", "_start"];
    private _res = { params ["_r"]; if (!isNil "comspec_overwatch_connect_fnc_extResult") then { [_r] call comspec_overwatch_connect_fnc_extResult } else { _r } };
    private _split = { params ["_s"]; if (!isNil "comspec_overwatch_connect_fnc_splitKeepEmpty") then { [_s, toString [9]] call comspec_overwatch_connect_fnc_splitKeepEmpty } else { _s splitString toString [9] } };
    private _q = (uiNamespace getVariable ["COMSPEC_ATAK_MusicUi", createHashMap]) getOrDefault ["athQ", ""];
    if (_view isEqualTo "playlists") then { _q = ""; };
    private _raw = [(["COMSPECExtension", "AthenaMusic", [_view, _q, "80"], 25] call comspec_atak_native_fnc_extAwait)] call _res;
    if !(_raw isEqualType "") then { _raw = str _raw; };
    // Réponse d'un chargement déjà abandonné (garde-fou passé, ou un plus récent a démarré) : ignorée.
    private _cur = _ath getOrDefault [_view, []];
    if ((_cur param [5, -1]) isNotEqualTo _start) exitWith {};
    private _now = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
    if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith {
        private _err = switch (true) do {
            case (_raw isEqualTo "" || {(_raw find "commande inconnue") >= 0} || {(_raw find "unknown") >= 0}): { "La DLL COMSPEC Link ne connaît pas encore la bibliothèque musicale : mettez-la à jour (2.0.73 ou plus)." };
            case ((_raw find "not_connected") >= 0 || {(_raw find "unauthorized") >= 0}): { "Connexion Athena requise : liez le téléphone à votre compte (app Athena)." };
            // La DLL traduit toute erreur 503 du site par migration_required : souvent une surcharge ou une maintenance.
            case ((_raw find "migration_required") >= 0): { "Athena est indisponible (erreur 503 : maintenance, surcharge ou mise à jour en cours). Nouvel essai automatique dans 20 s." };
            case ((_raw find "not_found") >= 0): { "Playlist introuvable ou privée." };
            case ((_raw find "timeout") >= 0): { "Athena n'a pas répondu à temps. Nouvel essai automatique dans 20 s." };
            case ((_raw find "http_5") >= 0): { format ["Athena est en difficulté (%1). Nouvel essai automatique dans 20 s.", _raw select [4, 20]] };
            default { format ["Athena ne répond pas (%1). Nouvel essai automatique dans 20 s.", _raw select [0, 60]] };
        };
        _ath set [_view, [_cur select 0, _cur select 1, _err, false, diag_tickTime, _start]];
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
    _ath set [_view, [_items, _now, "", false, diag_tickTime, _start]];
    call _render;
};
true
