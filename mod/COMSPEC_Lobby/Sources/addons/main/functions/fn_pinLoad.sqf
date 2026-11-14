/*
    SERVEUR. Table des codes de poste : missionNamespace comspec_lobby_pins = HashMap ÉTIQUETTE (majuscules) -> [code, source].
    Trois sources, de la moins à la plus prioritaire (la plus prioritaire gagne pour une même étiquette) :
      ATHENA   tableau de paires [étiquette, code] posé par un autre module dans missionNamespace COMSPEC_LOBBY_AthenaPins ;
      FICHIER  fichier du serveur userconfig\comspec_lobby\pins.txt, une ligne « ÉTIQUETTE=CODE » (# = commentaire) ;
      ZEUS     codes posés par un Zeus (module « Code du poste », ZEN), gardés dans le profil du serveur (COMSPEC_LOBBY_Pins) :
               c'est la source du mode local, sans Athena ni fichier.
    Un code a quatre chiffres. Les codes ne sont JAMAIS écrits au journal : seulement les nombres par source.
    [] call comspec_lobby_fnc_pinLoad
*/
if (!isServer) exitWith {};
private _valid = { params [["_p", "", [""]]]; (count _p) isEqualTo 4 && {(_p splitString "0123456789") isEqualTo []} };
private _t = createHashMap;
private _n = createHashMapFromArray [["ATHENA", 0], ["FICHIER", 0], ["ZEUS", 0]];
private _add = {
    params ["_tag", "_pin", "_src"];
    _tag = toUpper (trim _tag);
    if (_tag isEqualTo "" || {!([_pin] call _valid)}) exitWith {};
    _t set [_tag, [_pin, _src]];
    _n set [_src, (_n get _src) + 1];
};
{ _x params [["_g", ""], ["_p", ""]]; [_g, _p, "ATHENA"] call _add; } forEach (missionNamespace getVariable ["COMSPEC_LOBBY_AthenaPins", []]);
private _raw = loadFile "userconfig\comspec_lobby\pins.txt";
{
    private _line = trim _x;
    if (_line isNotEqualTo "" && {(_line select [0, 1]) isNotEqualTo "#"}) then {
        private _kv = _line splitString "=";
        if ((count _kv) >= 2) then { [_kv select 0, trim (_kv select 1), "FICHIER"] call _add; };
    };
} forEach (_raw splitString (toString [10, 13]));
{ _x params [["_g", ""], ["_p", ""]]; [_g, _p, "ZEUS"] call _add; } forEach (profileNamespace getVariable ["COMSPEC_LOBBY_Pins", []]);
missionNamespace setVariable ["comspec_lobby_pins", _t];
(format ["[COMSPEC LOBBY] codes de poste : %1 en tout (Athena %2, fichier %3, Zeus %4)", count _t, _n get "ATHENA", _n get "FICHIER", _n get "ZEUS"]) call { diag_log _this };
