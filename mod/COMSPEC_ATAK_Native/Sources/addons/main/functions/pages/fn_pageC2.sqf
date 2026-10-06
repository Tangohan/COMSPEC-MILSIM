/*
    App C2 : tableau de commande d'un coup d'œil. Bandeau du groupe, tuiles (alliés, contacts,
    ordres à traiter, liaisons perdues), état de mon groupe, contacts les plus proches,
    derniers ordres et raccourcis (carte, BFT, tâches, nouvel ordre).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _grey = "#8a9a93";
private _units = values (_data getOrDefault ["units", createHashMap]);
private _friends = _units select { (_x getOrDefault ["affiliation", ""]) isEqualTo "friend" };
private _hostiles = _units select { (_x getOrDefault ["affiliation", ""]) isEqualTo "hostile" };
private _lost = { (_x getOrDefault ["freshness", "LIVE"]) in ["LOST", "OFFLINE"] } count _friends;
private _orders = [] call comspec_atak_native_fnc_tasksAll;
private _open = [];
{ if !((toUpper (_y getOrDefault ["status", "NEW"])) in ["DELIVERED", "FAILED", "SUCCEEDED", "CANCELED"]) then { _open pushBack _y; }; } forEach _orders;
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _dist = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _posOf = { private _o = _this getOrDefault ["object", objNull]; if (isNull _o) then { _this getOrDefault ["position", []] } else { getPosASL _o } };
private _tile = { params ["_n", "_lab", "_col"]; format ["<t size='1.5' font='RobotoCondensedBold' color='%3'>%1</t><t size='0.8' color='#8a9a93'> %2</t>", _n, _lab, _col] };
private _canOrder = ([] call comspec_atak_native_fnc_bridge) && { [] call comspec_overwatch_connect_fnc_canIssueOrder };
private _grp = group player;
private _rows = [
    ["hero", "\z\comspec_atak_native\addons\main\data\app_c2.paa", format ["<t size='1.3' font='RobotoCondensedBold'>%1</t><br/><t size='0.85' color='%2'>Chef : %3 · %4 · %5</t>",
        groupId _grp, _grey, name leader _grp, [dayTime, "HH:MM"] call BIS_fnc_timeToString, worldName]],
    ["text", format ["%1     %2     %3     %4",
        [count _friends, "alliés", "#47b3ff"] call _tile,
        [count _hostiles, "contacts", ["#8a9a93", "#e5483a"] select ((count _hostiles) > 0)] call _tile,
        [count _open, "ordres à traiter", ["#8a9a93", "#f2ab33"] select ((count _open) > 0)] call _tile,
        [_lost, "liaisons perdues", ["#8a9a93", "#e5483a"] select (_lost > 0)] call _tile]],
    ["buttons", [
        ["CARTE", { ["MAP"] call comspec_atak_native_fnc_navigate; }, true],
        ["BFT", { ["BFT"] call comspec_atak_native_fnc_navigate; }],
        ["TÂCHES", { ["TASK"] call comspec_atak_native_fnc_navigate; }]
    ]]
];

// Mon groupe : une ligne par membre (état, distance, gisement).
_rows pushBack ["section", "Mon groupe", format ["%1 membre(s)", count units _grp]];
{
    private _life = switch (true) do {
        case (!alive _x): { "<t color='#e5483a'>mort</t>" };
        case !(["state", "allies"] call comspec_atak_native_fnc_medShow): { "<t color='#8a9a93'>—</t>" };
        case ((lifeState _x) isEqualTo "INCAPACITATED"): { "<t color='#e5483a'>inconscient</t>" };
        case ((damage _x) > 0.4): { "<t color='#f2ab33'>blessé</t>" };
        default { "<t color='#5cc76b'>valide</t>" };
    };
    private _where = if (_x isEqualTo player) then { "moi" } else { format ["%1 · %2", [player distance2D _x] call _dist, [player getDir _x] call _card] };
    _rows pushBack ["info", format ["%1%2", name _x, ["", " ★"] select (leader _grp isEqualTo _x)], format ["%1  <t color='%2'>%3</t>", _life, _grey, _where]];
} forEach ((units _grp) select [0, 12]);

// Contacts les plus proches.
private _near = (_hostiles apply { private _p = _x call _posOf; [if ((count _p) >= 2) then { player distance2D _p } else { 1e9 }, _x] }) select { (_x select 0) < 1e9 };
_near sort true;
_rows pushBack ["section", "Contacts", ["Aucun contact signalé", format ["%1 signalé(s), les plus proches", count _hostiles]] select ((count _hostiles) > 0)];
{
    _x params ["_m", "_u"];
    private _p = _u call _posOf;
    private _fr = createHashMapFromArray [["LIVE", "<t color='#5cc76b'>direct</t>"], ["STALE", "<t color='#f2ab33'>retardé</t>"], ["LOST", "<t color='#e5483a'>perdu</t>"]] getOrDefault [_u getOrDefault ["freshness", "LIVE"], ""];
    _rows pushBack ["info", format ["<t color='#e5483a'>%1</t>", _u getOrDefault ["callsign", "CONTACT"]], format ["%1 · %2° %3  %4", [_m] call _dist, round (player getDir _p), [player getDir _p] call _card, _fr]];
} forEach (_near select [0, 5]);

// Ordres en cours.
_rows pushBack ["section", "Ordres en cours", ["Aucun ordre à traiter", format ["%1 sur %2 reçus", count _open, count _orders]] select ((count _open) > 0)];
{
    private _label = _x getOrDefault ["typeLabel", ""];
    if (_label isEqualTo "") then { _label = _x getOrDefault ["type", "ORDRE"]; };
    private _prio = (toUpper (_x getOrDefault ["priority", "NORMAL"])) in ["HIGH", "URGENT", "FLASH", "PRIORITY"];
    _rows pushBack ["info", format ["<t color='%1'>%2</t>", ["#c9d4cf", "#e04038"] select _prio, _label], format ["<t color='%1'>%2 · cible %3</t>", _grey, _x getOrDefault ["issuer", "TOC"], _x getOrDefault ["target", "-"]]];
} forEach (_open select [0, 4]);
if (_canOrder) then { _rows pushBack ["buttons", [["NOUVEL ORDRE", { ['open'] call comspec_atak_native_fnc_orderAction; ["TASK"] call comspec_atak_native_fnc_navigate; }, true]]]; };
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
