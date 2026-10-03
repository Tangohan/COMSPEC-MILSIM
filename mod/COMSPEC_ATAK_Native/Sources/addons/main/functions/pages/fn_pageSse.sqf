/*
    App SSE (exploitation de site) : dossier actif de l'élément, sujet devant le joueur,
    accès direct aux pages du terminal SEEK d'Overwatch et journal des interrogations du groupe.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _grey = "#8a9a93";
private _rows = [];
if (isNil "comspec_overwatch_connect_fnc_sseOpenTerminal") exitWith {
    _rows pushBack ["section", "Exploitation de site", "Terminal SEEK"];
    _rows pushBack ["text", format ["<t color='%1'>Le module SSE d'Overwatch n'est pas chargé. Relancez build_mod.bat puis le jeu avec @COMSPEC_ATAK_Native.</t>", _grey]];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// Dossier actif : contexte d'équipe, toutes les fiches suivantes y sont classées.
private _case = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase;
_rows pushBack ["section", "Dossier actif", "Référence donnée par le poste de commandement"];
_rows pushBack ["info", "Dossier", ["<t color='#8a9a93'>aucun : fiches non classées</t>", format ["<t font='RobotoCondensedBold' color='#5cc76b'>%1</t>", _case]] select (_case isNotEqualTo "")];
_rows pushBack ["edit", "sseCase", "Référence (ex. OBJ-ALPHA-01)", ["", profileNamespace getVariable ["COMSPEC_SseLastCaseCode", ""]] select (_case isEqualTo "")];
_rows pushBack ["buttons", [
    ["POSER POUR L'ÉLÉMENT", { ['case'] call comspec_atak_native_fnc_sseAction; }, true],
    ["EFFACER", { ['clear'] call comspec_atak_native_fnc_sseAction; }, false, _case isNotEqualTo ""]
]];

// Sujet visé (vivant ou non : l'exploitation d'un corps est un cas courant).
private _t = [] call comspec_atak_native_fnc_sseAction;
private _item = [] call comspec_overwatch_connect_fnc_sseHasTerminalItem;
_rows pushBack ["section", "Sujet", "Regardez la personne à moins de 6 m"];
if (isNull _t) then {
    _rows pushBack ["info", "Sujet", format ["<t color='%1'>personne devant vous : le terminal s'ouvrira sans sujet</t>", _grey]];
} else {
    private _who = trim format ["%1 %2", _t getVariable ["COMSPEC_SSE_FirstName", ""], _t getVariable ["COMSPEC_SSE_LastName", ""]];
    if (_who isEqualTo "") then { _who = name _t; };
    _rows pushBack ["info", "Sujet", format ["<t font='RobotoCondensedBold'>%1</t>  <t color='%2'>%3 · %4 m</t>", _who, _grey, ["décédé", "vivant"] select (alive _t), round (player distance _t)]];
};
if (!_item) then {
    _rows pushBack ["text", "<t color='#e5a03a'>Terminal SEEK absent : récupérez l'appareil dans votre équipement.</t>"];
};
_rows pushBack ["buttons", [["OUVRIR LE TERMINAL SEEK", { ['open', 0] call comspec_atak_native_fnc_sseAction; }, true, _item]]];
_rows pushBack ["buttons", [
    ["SUJET", { ['open', 1] call comspec_atak_native_fnc_sseAction; }, false, _item],
    ["BIOMÉTRIE", { ['open', 3] call comspec_atak_native_fnc_sseAction; }, false, _item],
    ["PHOTO", { ['open', 5] call comspec_atak_native_fnc_sseAction; }, false, _item]
]];
_rows pushBack ["buttons", [
    ["CONSTAT", { ['open', 4] call comspec_atak_native_fnc_sseAction; }, false, _item],
    ["DOSSIER", { ['open', 6] call comspec_atak_native_fnc_sseAction; }, false, _item],
    ["TERRAIN", { ['open', 7] call comspec_atak_native_fnc_sseAction; }, false, _item]
]];

// Journal : interrogations de l'élément (partagées par le groupe), puis les miennes.
private _hist = (group player) getVariable ["COMSPEC_SeekQueryHistory", []];
if (!(_hist isEqualType []) || {(count _hist) isEqualTo 0}) then { _hist = missionNamespace getVariable ["COMSPEC_SeekQueryHistory", []]; };
if (!(_hist isEqualType [])) then { _hist = []; };
_rows pushBack ["section", "Interrogations SEEK", format ["%1 dans le groupe", count _hist]];
if ((count _hist) isEqualTo 0) then {
    _rows pushBack ["text", format ["<t color='%1'>Aucune interrogation pour l'instant. Prenez un relevé biométrique puis interrogez la base depuis le terminal.</t>", _grey]];
};
{
    _x params [["_tick", 0], ["_clock", ""], ["_who", "?"], ["_alias", ""], ["_status", ""], ["_conf", 0], ["_ref", ""], ["_grid", ""], ["_res", "none"]];
    private _col = switch (_res) do { case "confirmed": { "#e5483a" }; case "possible": { "#e5a03a" }; default { _grey }; };
    _rows pushBack ["text", format ["<t font='RobotoCondensedBold'>%1</t>%2  <t color='%3'>%4%5</t><br/><t size='0.85' color='%6'>%7 · %8%9</t>",
        _who, ["", format [" « %1 »", _alias]] select (_alias isNotEqualTo ""), _col, _status, ["", format [" %1 %2", _conf, "%"]] select (_res isNotEqualTo "none"),
        _grey, _clock, _grid, ["", format [" · %1", _ref]] select (_ref isNotEqualTo "")]];
} forEach (_hist select [0, 12]);
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
