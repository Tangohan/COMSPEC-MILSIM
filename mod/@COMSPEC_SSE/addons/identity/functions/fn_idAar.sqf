/*
    Contrôles notables pour le compte rendu after-action (fn_afterActionReport) : détection, saisie, interpellation,
    HVT, issues des checkpoints IA. Une ligne par évènement, 80 au plus, dans l'ordre.
    ["add", [genre, texte, clé facultative]]   SERVEUR : inscrit la ligne (une seule fois par clé), diffusée aux postes
                                                (comspec_sse_idAarLog) pour le module AAR du Zeus
    ["lines"] -> lignes à afficher (tout poste) ; [] si rien de notable
    Ne contient que ce que les contrôleurs ont vu (nom imprimé, verdict), jamais la vraie identité.
*/
params [["_op", "lines", [""]], ["_a", []]];
if (_op isEqualTo "add") exitWith {
    if (!isServer) exitWith { false };
    _a params [["_kind", ""], ["_text", ""], ["_key", ""]];
    if (_text isEqualTo "") exitWith { false };
    private _reg = ["init"] call comspec_sse_fnc_idRegistry;
    private _seen = _reg getOrDefault ["aarSeen", createHashMap];
    _reg set ["aarSeen", _seen];
    if (_key isNotEqualTo "" && {_key in _seen}) exitWith { false };
    if (_key isNotEqualTo "") then { _seen set [_key, true]; };
    private _log = +(missionNamespace getVariable ["comspec_sse_idAarLog", []]);
    _log pushBack [[dayTime, "HH:MM"] call BIS_fnc_timeToString, _kind, _text];
    if ((count _log) > 80) then { _log deleteRange [0, (count _log) - 80]; };
    // Évènements rares (quelques-uns par mission) : diffusion simple, lue seulement par le compte rendu AAR.
    missionNamespace setVariable ["comspec_sse_idAarLog", _log, true];
    true
};
private _log = missionNamespace getVariable ["comspec_sse_idAarLog", []];
if (_log isEqualTo []) exitWith { [] };
private _lines = ["=== CONTRÔLES D'IDENTITÉ ==="];
{
    _x params ["_clock", "_kind", "_text"];
    _lines pushBack format ["%1 · %2 · %3", _clock, _kind, _text];
} forEach _log;
_lines
