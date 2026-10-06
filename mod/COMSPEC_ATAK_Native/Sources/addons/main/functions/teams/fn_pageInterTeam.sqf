/*
    App Inter-team : vue commune des escouades, des équipes de feu et du BFT de tout le camp, et état de la synchronisation.
      ESCOUADES : chaque groupe avec joueurs (le mien en tête), son chef, ses équipes (couleur, icône, chef, membres),
                  balises BFT en ligne ; carte et SMS au chef.
      ÉQUIPES   : toutes les équipes du camp, de la plus proche à la plus loin : centre de l'équipe, distance et gisement,
                  dispersion, membres en ligne ; carte et itinéraire vers l'équipe.
      SYNCHRO   : révision partagée par le serveur, dernier envoi à Athena (COMSPEC Link Squad.Sync), bilan, resynchroniser.
    Params (module, fn_pageRender) : [page, [x, y, largeur, hauteur]]
*/
params [["_page", "INTERTEAM"], ["_rect", [0, 0, 1, 1]]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["itTab", "SQUADS"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _dist = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
private _types = [] call comspec_atak_native_fnc_groupTypes;
private _mine = group player;
private _groups = allGroups select { side _x isEqualTo side _mine && {((units _x) findIf { isPlayer _x }) >= 0} };
_groups = [_mine] + (_groups - [_mine]);
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['itTab', '%1']; [{ ['INTERTEAM'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _nTeams = 0;
{ _nTeams = _nTeams + count (_x getVariable ["COMSPEC_FireTeams", []]); } forEach _groups;
private _rows = [["segment", "", [[format ["ESCOUADES (%1)", count _groups], "SQUADS"] call _tabBtn, [format ["ÉQUIPES (%1)", _nTeams], "TEAMS"] call _tabBtn, ["SYNCHRO", "SYNC"] call _tabBtn]]];
private _online = { params ["_us"]; { _x getVariable ["COMSPEC_ATAK_Beacon", false] } count _us };
private _center = {
    params ["_us"];
    private _alive = _us select { alive _x };
    if ((count _alive) isEqualTo 0) exitWith { [] };
    private _sum = [0, 0, 0];
    { _sum = _sum vectorAdd (getPosASL _x); } forEach _alive;
    _sum vectorMultiply (1 / count _alive)
};
private _act = { params ["_label", "_a", ["_args", []], ["_primary", false]]; [_label, compile format ["[%1, %2] call comspec_atak_native_fnc_interTeamAction;", str _a, str _args], _primary] };

switch (_tab) do {
    case "SQUADS": {
        _rows pushBack ["section", "Escouades du camp", "Groupes avec au moins un joueur ; équipes de feu et balises BFT"];
        {
            private _g = _x;
            private _gt = _g getVariable ["COMSPEC_GroupType", "INF"];
            private _t = (_types select { (_x select 0) isEqualTo _gt }) param [0, _types select 0];
            private _us = units _g;
            private _d = (leader _g) distance2D player;
            private _btns = [["CARTE", "mapGroup", [netId _g]] call _act];
            if (isPlayer leader _g && {leader _g isNotEqualTo player}) then {
                _btns pushBack ["SMS", compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['chatPeer', '%1']; ['CHAT'] call comspec_atak_native_fnc_navigate;", name leader _g]];
            };
            _rows pushBack ["person", _t select 2, format ["<t font='RobotoCondensedBold' size='1.1'>%1</t>%2  <t size='0.8' color='#7fb6e6'>%3</t><br/><t size='0.8' color='#8a9a93'>Chef %4 · %5 membre(s) · %6 BFT en ligne · %7</t>",
                [groupId _g] call _esc, ["", " <t color='#e8b84a'>● mon groupe</t>"] select (_g isEqualTo _mine), _t select 1,
                [[leader _g] call comspec_atak_native_fnc_unitCallsign] call _esc, count _us, [_us] call _online, ["moi", [_d] call _dist] select (_g isNotEqualTo _mine || {leader _g isNotEqualTo player})], _btns, [0.28, 0.70, 1, 1]];
            private _all = [_g] call comspec_atak_native_fnc_ftTeams;
            private _free = (_all select ((count _all) - 1)) select 1;
            _all deleteAt ((count _all) - 1);
            {
                _x params ["_tm", "_m"];
                private _lead = (_m select { (_x getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE" }) param [0, objNull];
                private _c = [_m] call _center;
                private _names = (_m apply {
                    private _r = [_x] call comspec_atak_native_fnc_ftInfo;
                    format ["%1%2%3", ["", format ["<t color='#7fb6e6'>%1</t> ", _r get "roleShort"]] select ((_r get "roleShort") isNotEqualTo ""), [name _x] call _esc, ["", " <t color='#6c7671'>○</t>"] select !(_x getVariable ["COMSPEC_ATAK_Beacon", false])]
                }) joinString " · ";
                _rows pushBack ["person", _tm get "icon", format ["<t color='%1' font='RobotoCondensedBold'>   ● %2</t>  <t size='0.8' color='#8a9a93'>%3 · chef %4%5</t>%6<br/><t size='0.8'>   %7</t>",
                    _tm get "hex", [_tm get "name"] call _esc, count _m, [["—", name _lead] select !isNull _lead] call _esc,
                    if ((count _c) > 0) then { format [" · %1 %2°", [player distance2D _c] call _dist, round (player getDir _c)] } else { "" },
                    ["", format ["<br/><t size='0.8' color='#b8c4bd'>   %1</t>", [_tm get "desc"] call _esc]] select ((_tm get "desc") isNotEqualTo ""),
                    [_names, "<t color='#8a9a93'>vide</t>"] select ((count _m) isEqualTo 0)],
                    [[], [["CARTE", "mapTeam", [netId _g, _tm get "id"]] call _act]] select ((count _c) > 0), _tm get "rgba"];
            } forEach _all;
            if ((count _free) > 0 && {(count _all) > 0}) then {
                _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>   Sans équipe : %1</t>", (_free apply { [name _x] call _esc }) joinString " · "]];
            };
            if ((count _all) isEqualTo 0) then { _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>   Pas d'équipe de feu.</t>"]; };
        } forEach _groups;
        _rows pushBack ["buttons", [["GÉRER MES ÉQUIPES", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["grpTab", "FT"]; ["GROUP"] call comspec_atak_native_fnc_navigate; }, true], ["OUVRIR LE BFT", { ["BFT"] call comspec_atak_native_fnc_navigate; }]]];
    };
    case "TEAMS": {
        _rows pushBack ["section", "Équipes de feu du camp", "De la plus proche à la plus loin (centre des membres vivants)"];
        private _list = [];
        {
            private _g = _x;
            private _all = [_g] call comspec_atak_native_fnc_ftTeams;
            _all deleteAt ((count _all) - 1);
            { _x params ["_tm", "_m"]; private _c = [_m] call _center; _list pushBack [if ((count _c) > 0) then { player distance2D _c } else { 1e9 }, _g, _tm, _m, _c]; } forEach _all;
        } forEach _groups;
        _list sort true;
        {
            _x params ["_d", "_g", "_tm", "_m", "_c"];
            private _spread = 0;
            if ((count _c) > 0) then { { if (alive _x) then { _spread = _spread max (_x distance2D _c); }; } forEach _m; };
            private _dead = { !alive _x || {lifeState _x isEqualTo "INCAPACITATED"} } count _m;
            private _btns = [];
            if ((count _c) > 0) then {
                _btns pushBack (["CARTE", "mapTeam", [netId _g, _tm get "id"]] call _act);
                _btns pushBack (["Y ALLER", "routeTeam", [netId _g, _tm get "id"], true] call _act);
            };
            _rows pushBack ["person", _tm get "icon", format ["<t color='%1' font='RobotoCondensedBold'>%2</t>  <t size='0.8' color='#8a9a93'>%3</t><br/><t size='0.8' color='#8a9a93'>%4 membre(s) · %5 en ligne%6 · %7</t>",
                _tm get "hex", [_tm get "name"] call _esc, [groupId _g] call _esc, count _m, [_m] call _online,
                ["", format [" · <t color='#e5483a'>%1 hors de combat</t>", _dead]] select (_dead > 0),
                if ((count _c) > 0) then { format ["%1 · %2 %3° · dispersion %4", mapGridPosition _c, [_d] call _dist, round (player getDir _c), [_spread] call _dist] } else { "position inconnue" }],
                _btns, _tm get "rgba"];
        } forEach _list;
        if ((count _list) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune équipe de feu dans le camp. Créez-en dans l'app Groupe, onglet ÉQUIPES.</t>"]; };
    };
    default {
        (missionNamespace getVariable ["COMSPEC_ATAK_SquadSyncLast", [-1, "jamais", 0]]) params ["_at", "_st", "_n"];
        private _players = (units _mine) select { isPlayer _x };
        private _reporter = [_players param [0, objNull], leader _mine] select (isPlayer leader _mine);
        private _units = (uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap];
        private _fresh = [0, 0, 0];
        { private _k = ["LIVE", "STALE"] find (_y getOrDefault ["freshness", "LIVE"]); if (_k < 0) then { _k = 2; }; _fresh set [_k, (_fresh select _k) + 1]; } forEach _units;
        private _inTeam = 0; private _total = 0;
        { { _total = _total + 1; if ((_x getVariable ["COMSPEC_FT", ""]) isNotEqualTo "") then { _inTeam = _inTeam + 1; }; } forEach ((units _x) select { isPlayer _x }); } forEach _groups;
        _rows append [
            ["section", "Synchronisation", "Le serveur fait foi : chaque changement d'équipe passe par lui et part à tout le camp"],
            ["info", "Révision serveur", str (missionNamespace getVariable ["COMSPEC_ATAK_SquadRev", 0])],
            ["info", "Révision de mon groupe", str (_mine getVariable ["COMSPEC_FTRev", 0])],
            ["info", "Escouades / équipes", format ["%1 / %2", count _groups, _nTeams]],
            ["info", "Joueurs en équipe", format ["%1 sur %2", _inTeam, _total]],
            ["info", "Pistes BFT", format ["%1 directes · %2 anciennes · %3 perdues", _fresh select 0, _fresh select 1, _fresh select 2]],
            ["section", "Athena", "Escouades et équipes visibles sur le site (back-office, Escouades en jeu)"],
            ["info", "Envoi par", [format ["%1 (chef ou premier joueur du groupe)", name _reporter], "moi"] select (_reporter isEqualTo player)],
            ["info", "Dernier envoi", if (_at < 0) then { "jamais" } else { format ["%1 · %2 · %3 équipe(s)", [_at, "HH:MM"] call BIS_fnc_timeToString, _st, _n] }],
            ["info", "Liaison", ["Pas de COMSPEC Link : équipes partagées en jeu seulement", "COMSPEC Link présent"] select ([] call comspec_atak_native_fnc_bridge)],
            ["buttons", [["RESYNCHRONISER", "sync", [], true] call _act, ["ENVOYER À ATHENA", "push"] call _act]]
        ];
    };
};
[_rows, _rect] call comspec_atak_native_fnc_formRender;
true
