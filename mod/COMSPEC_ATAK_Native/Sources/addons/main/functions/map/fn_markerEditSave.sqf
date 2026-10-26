/*
    Enregistre l'éditeur : crée le marqueur sur le canal choisi (partagé comme un marqueur posé à la main,
    donc synchronisé vers Athena par Overwatch) ou modifie l'existant. La description est partagée
    avec toute la mission (COMSPEC_ATAK_MarkerNotes).
*/
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _e = _s getOrDefault ["markerEdit", createHashMap];
if ((count _e) isEqualTo 0) exitWith { false };
private _v = { params ["_k"]; [_k, _e getOrDefault [_k, ""]] call comspec_atak_native_fnc_formValue };
private _text = trim (["text"] call _v);
private _desc = trim (["desc"] call _v);
private _type = ["type"] call _v;
private _color = ["color"] call _v;
private _size = (parseNumber (["size"] call _v)) max 0.2;
private _dir = parseNumber (["dir"] call _v);
private _alpha = (parseNumber (["alpha"] call _v)) max 0.1;
private _name = _e get "name";
private _poly = (_e get "shape") isEqualTo "POLYLINE";
if (_name isEqualTo "") then {
    private _channel = parseNumber (["channel"] call _v);
    private _index = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _index];
    _name = format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _index, _channel];
    _name = ["create", [_name, _e get "pos", _channel]] call comspec_atak_native_fnc_markerNet;
};
if (_name isEqualTo "") exitWith { ["WARNING", "Marqueur refusé sur ce canal", 3, 20] call comspec_atak_native_fnc_notify; false };
if !(_poly) then {
    _name setMarkerTypeLocal _type;
    _name setMarkerSizeLocal [_size, _size];
    _name setMarkerDirLocal _dir;
    profileNamespace setVariable ["COMSPEC_ATAK_LastMarkerType", _type];
};
_name setMarkerColorLocal _color;
_name setMarkerAlphaLocal _alpha;
_name setMarkerText _text; // dernier appel global : diffuse tout l'état du marqueur
[_name] call comspec_atak_native_fnc_markerWeb;
profileNamespace setVariable ["COMSPEC_ATAK_LastMarkerColor", _color];
// Note envoyée seule au serveur, qui édite la table et la diffuse (à tous et JIP) : deux joueurs qui notent en même temps
// ne s'écrasent plus. Copie locale mise à jour tout de suite (affichage), remplacée par celle du serveur à son arrivée.
private _note = [[_desc, [player] call comspec_atak_native_fnc_unitCallsign], []] select (_desc isEqualTo "");
private _old = missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap];
if ((_old getOrDefault [_name, []]) isNotEqualTo _note) then {
    private _notes = +_old;
    if (_note isEqualTo []) then { _notes deleteAt _name; } else { _notes set [_name, _note]; };
    missionNamespace setVariable ["COMSPEC_ATAK_MarkerNotes", _notes];
    ["comspec_atak_native_tacMkSrv", ["note", _name, _note]] call CBA_fnc_serverEvent;
};
_s set ["markerEdit", createHashMap];
_s set ["selectedMarker", _name];
// Confirmation brève : toast Android (notification classique si le téléphone n'est pas affiché).
private _okMsg = format ["Marqueur %1 enregistré", [_text, "sans titre"] select (_text isEqualTo "")];
if !(([_okMsg, 2.5] call comspec_atak_native_fnc_toast)) then { ["SUCCESS", _okMsg, 3, 20] call comspec_atak_native_fnc_notify; };
[{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
true
