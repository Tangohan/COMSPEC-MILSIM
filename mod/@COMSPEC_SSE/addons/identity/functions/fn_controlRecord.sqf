/*
    Point d'entrée unique d'un relevé de document (bouton « RELEVER » de la fenêtre du document, scripts, autres mods).
    [_pairs] call comspec_sse_fnc_controlRecord -> clé du relevé ("" si rien d'exploitable)
      _pairs : tableau de paires [["clé", valeur]…] ou HashMap. Clés : type (id_card | passport | driving_license |
        military_id), country, number, last, first, dob (AAAA-MM-JJ), expires, holder (objet : personne qui présente,
        facultatif), place (grille, sinon celle du joueur), clock (HH:MM, sinon l'heure du jeu), visual (résultat de
        l'examen visuel : ras | doute | faux | expire…), docId (identifiant SSE du document, facultatif).
    Effets : (1) fiche du contrôle d'identité en cours complétée (« Document relevé », historique de l'app Contrôle ID),
      ou nouvelle fiche si ce document n'est pas en cours ; (2) noté au fichier de la mission (contrôle subi) ;
      (3) remonté à Athena (contrôle subi et journal du passage aux fichiers : genre id_record, file hors ligne sans liaison) ;
      (4) évènement CBA local comspec_sse_controlRecorded [clé, relevé].
*/
params [["_in", [], [[], createHashMap]]];
if (!hasInterface) exitWith { "" };
private _r = if (_in isEqualType createHashMap) then { +_in } else { createHashMapFromArray (_in select { _x isEqualType [] && {(count _x) isEqualTo 2} }) };
if ((_r getOrDefault ["number", ""]) isEqualTo "" && {(_r getOrDefault ["last", ""]) isEqualTo ""}) exitWith { "" };
if ((_r getOrDefault ["clock", ""]) isEqualTo "") then { _r set ["clock", [dayTime, "HH:MM"] call BIS_fnc_timeToString]; };
if ((_r getOrDefault ["place", ""]) isEqualTo "") then { _r set ["place", mapGridPosition player]; };
private _key = format ["REL-%1-%2-%3", getPlayerUID player, (_r getOrDefault ["number", ""]) regexReplace ["[^A-Za-z0-9]", ""], round (serverTime max time)];
_r set ["key", _key];
private _holder = _r getOrDefault ["holder", objNull];
if !(_holder isEqualType objNull) then { _holder = objNull; };
private _hKey = if (isNull _holder) then { "" } else { [_holder] call comspec_sse_fnc_bioPersonKey };
// (1) Fiche du contrôle : celle du même numéro, sinon une fiche « relevé » (sans niveau lu).
private _cur = ["current"] call comspec_sse_fnc_idHistory;
private _same = (count _cur) > 0 && {((_cur get "doc") getOrDefault ["number", ""]) isEqualTo (_r getOrDefault ["number", ""])};
if (!_same) then {
    private _doc = createHashMap;
    { _doc set [_x, _r getOrDefault [_x, ""]]; } forEach ["type", "country", "number", "last", "first", "dob", "expires"];
    _doc set ["id", _r getOrDefault ["docId", format ["REL%1", (_r getOrDefault ["number", ""]) regexReplace ["[^A-Za-z0-9]", ""]]]];
    _cur = ["start", [_doc, _holder, _hKey, createHashMap]] call comspec_sse_fnc_idHistory;
};
_cur set ["photo", "releve"];
_cur set ["photoClock", _r get "clock"];
// (2) Fichier de la mission.
private _srv = +_r;
_srv deleteAt "holder";
["record", [_srv]] call comspec_sse_fnc_fileClient;
// (3) Athena : contrôle subi (relevé) et journal du passage aux fichiers.
[createHashMapFromArray [
    ["kind", "id_record"], ["idempotency_key", _key],
    ["document", createHashMapFromArray [["type", _r getOrDefault ["type", ""]], ["country", _r getOrDefault ["country", ""]], ["number", _r getOrDefault ["number", ""]], ["local_id", _r getOrDefault ["docId", ""]]]],
    ["presented_name", createHashMapFromArray [["last", _r getOrDefault ["last", ""]], ["first", _r getOrDefault ["first", ""]], ["dob", _r getOrDefault ["dob", ""]]]],
    ["expires", _r getOrDefault ["expires", ""]], ["visual", _r getOrDefault ["visual", ""]],
    ["subject", createHashMapFromArray [["netid", [netId _holder, ""] select (isNull _holder)], ["bio_key", _hKey], ["steam_id", [getPlayerUID _holder, ""] select (isNull _holder || {!isPlayer _holder})]]],
    ["controller", createHashMapFromArray [["callsign", name player], ["steam_id", getPlayerUID player]]],
    ["grid", _r get "place"], ["game_time", format ["%1-%2-%3 %4", date select 0, date select 1, date select 2, _r get "clock"]], ["levels", ["N0"]]
]] call comspec_sse_fnc_fileSubmit;
["comspec_sse_controlRecorded", [_key, _r]] call CBA_fnc_localEvent;
[{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCHECK") then { ["IDCHECK"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
_key
