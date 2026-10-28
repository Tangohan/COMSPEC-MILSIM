/*
    App Itinéraire (points de passage) : étape active et navigation (flèche de cap en mode mini), partage en direct
    (moi seul, groupe, camp), liste des étapes avec leur type et leur tronçon, inverser l'ordre, envoi au groupe
    avec lancement de la navigation chez tous. Les étapes se posent et se modifient sur la carte (fn_wpCard).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
private _pts = _w getOrDefault ["pts", []];
private _idx = (_w getOrDefault ["idx", 0]) min (((count _pts) - 1) max 0);
private _nav = _w getOrDefault ["nav", false];
private _from = _w getOrDefault ["from", ""];
private _share = profileNamespace getVariable ["COMSPEC_ATAK_WpShare", 1];
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _hex = { params ["_c"]; "#" + (((_c select [0, 3]) apply { private _v = ((round (_x * 255)) max 0) min 255; ("0123456789abcdef" select [floor (_v / 16), 1]) + ("0123456789abcdef" select [_v mod 16, 1]) }) joinString "") };
private _openMap = { ["MAP"] call comspec_atak_native_fnc_navigate; [{ ["WP"] call comspec_atak_native_fnc_mapToolSet; ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
private _tot = 0;
for "_i" from 1 to ((count _pts) - 1) do { _tot = _tot + (((_pts select (_i - 1)) select 0) distance2D ((_pts select _i) select 0)); };
private _rows = [["section", "Itinéraire", [
    format ["%1 étape(s) · %2%3", count _pts, [_tot] call _fmt, ["", format [" · modifié par %1", _from]] select (_from isNotEqualTo "")],
    "Aucune étape : posez-les sur la carte (clic droit > Point de passage)"
] select ((count _pts) isEqualTo 0)]];
if ((count _pts) > 0) then {
    (_pts select _idx) params ["_ap", "_an"];
    private _ac = (["type", (_pts select _idx) param [2, "WP"]] call comspec_atak_native_fnc_wpAction) select 3;
    _rows pushBack ["text", format ["<t size='1.25' font='COMSPEC_BarlowBold' color='%1'>%2</t> <t size='1.25' font='COMSPEC_BarlowBold' color='%3'>%4. %5</t><br/>%6 · %7° %8 · <t font='EtelkaMonospacePro'>%9</t>",
        ["#8a9a93", "#5cc76b"] select _nav, ["EN PAUSE :", "CAP SUR"] select _nav, [_ac] call _hex, _idx + 1, _an regexReplace ["[<>&]", ""],
        [player distance2D _ap] call _fmt, round (player getDir _ap), [player getDir _ap] call _card, [_ap, 8] call comspec_atak_native_fnc_gridRef]];
    _rows pushBack ["buttons", [
        ["<", { ["prev"] call comspec_atak_native_fnc_wpAction; }, false, _idx > 0],
        [["NAVIGUER", "PAUSE"] select _nav, { ["nav"] call comspec_atak_native_fnc_wpAction; }, true],
        [">", { ["next"] call comspec_atak_native_fnc_wpAction; }, false, _idx < ((count _pts) - 1)]
    ]];
    // Longueur restante à pied depuis ma position.
    private _rest = 0; private _prev = getPosATL player;
    for "_i" from _idx to ((count _pts) - 1) do { _rest = _rest + (_prev distance2D ((_pts select _i) select 0)); _prev = (_pts select _i) select 0; };
    _rows pushBack ["info", "Reste à parcourir", format ["%1 · ~%2 min à pied", [_rest] call _fmt, ceil (_rest / 1.4 / 60)]];
    _rows pushBack ["buttons", [["GPS VERS L'ÉTAPE", { ["gps"] call comspec_atak_native_fnc_wpAction; }], ["FAIRE NAVIGUER LE GROUPE", { ["push"] call comspec_atak_native_fnc_wpAction; }, false, true, "Envoie l'itinéraire et lance la navigation chez tous ceux qui le partagent"]]];
};
_rows pushBack ["segment", "Partage", [
    ["MOI SEUL", { ["setShare", 0] call comspec_atak_native_fnc_wpAction; }, _share isEqualTo 0, "L'itinéraire reste sur mon téléphone"],
    ["GROUPE", { ["setShare", 1] call comspec_atak_native_fnc_wpAction; }, _share isEqualTo 1, "Chaque changement s'affiche aussitôt chez mon groupe"],
    ["CAMP", { ["setShare", 2] call comspec_atak_native_fnc_wpAction; }, _share isEqualTo 2, "Tout mon camp voit l'itinéraire"]
], ["", "en direct"] select (_share > 0)];
if ((count _pts) > 0) then {
    _rows pushBack ["section", "Étapes", "Toucher MODIFIER pour renommer, changer le type ou déplacer sur la carte"];
    {
        _x params ["_p", "_n"];
        _n = [_n] call comspec_atak_native_fnc_markerAutoName; // FIN <-> END selon la langue
        private _tRow = ["type", _x param [2, "WP"]] call comspec_atak_native_fnc_wpAction;
        private _prevP = if (_forEachIndex isEqualTo 0) then { getPosATL player } else { (_pts select (_forEachIndex - 1)) select 0 };
        private _on = _forEachIndex isEqualTo _idx;
        private _done = _nav && {_forEachIndex < _idx};
        _rows pushBack ["person", ["icon", _tRow select 0] call comspec_atak_native_fnc_wpAction, format ["<t font='COMSPEC_BarlowBold' color='%1'>%2. %3</t>  <t size='0.8' color='#8a9a93'>%4</t><br/><t size='0.8'>%5 %6 · %7°  <t font='EtelkaMonospacePro'>%8</t></t>",
            [["#c9d4cf", "#5cc76b"] select _on, "#6f7b75"] select _done, _forEachIndex + 1, _n regexReplace ["[<>&]", ""], toLower (_tRow select 1),
            [_prevP distance2D _p] call _fmt, ["depuis l'étape précédente", "depuis moi"] select (_forEachIndex isEqualTo 0), round (_prevP getDir _p), [_p, 8] call comspec_atak_native_fnc_gridRef],
            [
                ["ALLER", compile format ["['goto', %1] call comspec_atak_native_fnc_wpAction;", _forEachIndex], false, true, "Cap sur cette étape"],
                ["MODIFIER", compile format ["['MAP'] call comspec_atak_native_fnc_navigate; [{ ['edit', %1] call comspec_atak_native_fnc_wpAction; }] call CBA_fnc_execNextFrame;", _forEachIndex], false, true, "Renommer, changer le type ou déplacer sur la carte"],
                ["X", compile format ["['del', %1] call comspec_atak_native_fnc_wpAction;", _forEachIndex], false, true, "Supprimer l'étape"]
            ],
            [_tRow select 3, [0.55, 0.60, 0.57, 0.6]] select _done];
    } forEach _pts;
    _rows pushBack ["buttons", [["INVERSER", { ["reverse"] call comspec_atak_native_fnc_wpAction; }, false, (count _pts) > 1, "Parcourir l'itinéraire dans l'autre sens"], ["TOUT EFFACER", { ["clear"] call comspec_atak_native_fnc_wpAction; }]]];
};
_rows pushBack ["buttons", [[["POSER LES ÉTAPES SUR LA CARTE", "AJOUTER DES ÉTAPES SUR LA CARTE"] select ((count _pts) > 0), _openMap, true]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
