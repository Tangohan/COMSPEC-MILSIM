/*
    Fiche d'une pièce et son journal, en texte structuré (terminal, tableau des liens).
    [_pieceId] call comspec_sse_fnc_custodyText → String
*/
params [["_id", "", [""]]];
private _p = (missionNamespace getVariable ["comspec_sse_custody", createHashMap]) getOrDefault [_id, createHashMap];
if (count _p == 0) exitWith { "<t color='#9aa'>Pièce inconnue du registre.</t>" };

([_id] call comspec_sse_fnc_custodyVerify) params ["_ok", "_bad"];
private _stateCol = switch (_p get "state") do {
    case "SCELLÉE": { "#8fdc8f" };
    case "OUVERTE": { "#f0c060" };
    default { "#cfd8d8" };
};
private _lines = [
    format ["<t color='#8f8' size='1.05'>%1</t>  <t color='%2'>%3</t>", _id, _stateCol, _p get "state"],
    format ["<t color='#9aa'>Objet</t>  %1", _p getOrDefault ["desc", "?"]],
    format ["<t color='#9aa'>Étiquette</t>  %1", _p getOrDefault ["label", ""]],
    format ["<t color='#9aa'>Scellé</t>  %1", [_p getOrDefault ["seal", ""], "non posé"] select ((_p getOrDefault ["seal", ""]) isEqualTo "")],
    format ["<t color='#9aa'>Détenteur</t>  %1%2", _p getOrDefault ["holder", "?"], ["", " · archivée (mission précédente)"] select (_p getOrDefault ["archived", false])],
    format ["<t color='#9aa'>Intégrité du journal</t>  %1",
        ["<t color='#ff6b6b'>ROMPUE à la ligne " + str (_bad + 1) + "</t>", "<t color='#8fdc8f'>intègre</t>"] select _ok],
    ""
];
private _seals = _p getOrDefault ["seals", []];
if ((count _seals) > 1) then { _lines insert [5, [format ["<t color='#9aa'>Scellés successifs</t>  %1", _seals joinString ", "]]]; };
{
    _x params ["_when", "_what", "_who", "", "_grid", "_rem", "_h"];
    private _col = ["#cfd8d8", "#ff6b6b"] select (!_ok && {_forEachIndex isEqualTo _bad});
    _lines pushBack format ["<t color='%1'>%2  <t color='#8f8'>%3</t>  %4  · %5</t>", _col, _when, _what, _who, _grid];
    if (_rem isNotEqualTo "") then { _lines pushBack format ["<t color='#9aa' size='0.9'>      %1</t>", _rem]; };
    _lines pushBack format ["<t color='#566' size='0.8'>      empreinte %1</t>", _h select [0, 12]];
} forEach (_p getOrDefault ["events", []]);
_lines joinString "<br/>"
