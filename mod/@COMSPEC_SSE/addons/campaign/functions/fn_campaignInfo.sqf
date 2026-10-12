/*
    Fiche de la campagne en cours (texte structuré) : type, missions, journal, personnages récurrents.
    [] call comspec_sse_fnc_campaignInfo → String
*/
private _c = missionNamespace getVariable ["comspec_sse_campaign", createHashMap];
if (count _c == 0) exitWith {
    "<t color='#7fd08a' size='1.1'>Campagne SSE</t><br/><br/>Aucune campagne chargée : cette partie est autonome.<br/><br/><t color='#9aa' size='0.9'>Un module Campagne (Eden) ou le Zeus en charge une. Permanente : tout est gardé d'une mission à l'autre. Récurrente : mêmes personnages à chaque drill, rien de connu au départ. Temporaire : drill jetable.</t>"
};
private _type = _c getOrDefault ["type", "permanente"];
private _lines = [
    format ["<t color='#7fd08a' size='1.1'>%1</t>", _c getOrDefault ["name", "?"]],
    format ["<t color='#9aa'>%1 · %2</t>", _c getOrDefault ["id", ""], ["Campagne permanente", "Campagne récurrente (drills)", "Drill temporaire"] select (((["permanente", "recurrente", "temporaire"] find _type)) max 0)],
    format ["Mission n° %1 de la campagne", _c getOrDefault ["missions", 1]],
    format ["Dernière sauvegarde : %1", [_c getOrDefault ["lastSave", ""], "pas encore"] select ((_c getOrDefault ["lastSave", ""]) isEqualTo "")]
];
if ((_c getOrDefault ["copyFrom", ""]) isNotEqualTo "") then { _lines pushBack format ["Structure copiée de %1", _c get "copyFrom"]; };
private _roster = _c getOrDefault ["roster", createHashMap];
if (count _roster > 0) then {
    _lines pushBack "";
    _lines pushBack format ["<t color='#7fd08a'>Personnages récurrents (%1)</t>", count _roster];
    {
        _lines pushBack format ["· %1%2 <t color='#9aa'>%3</t>", _y getOrDefault ["name", _x], ["", format [" « %1 »", _y get "alias"]] select ((_y getOrDefault ["alias", ""]) isNotEqualTo ""), _y getOrDefault ["status", ""]];
    } forEach _roster;
};
private _log = _c getOrDefault ["log", []];
if (_log isNotEqualTo []) then {
    _lines pushBack "";
    _lines pushBack "<t color='#7fd08a'>Journal de campagne</t>";
    {
        _x params ["_when", "_mission", "_world", "_why", "_sum"];
        _lines pushBack format ["· %1 · %2 (%3)<br/><t color='#9aa' size='0.9'>   %4</t>", _when, _mission, _world, _sum];
    } forEach (_log select [((count _log) - 6) max 0]);
};
_lines joinString "<br/>"
