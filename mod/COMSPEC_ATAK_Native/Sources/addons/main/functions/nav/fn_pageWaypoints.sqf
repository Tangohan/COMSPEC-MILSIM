/*
    App Itinéraire (points de passage) : étape active et navigation (flèche de cap en mode mini), partage en direct
    (moi seul, groupe, camp), liste des étapes avec leur type et leur tronçon, inverser l'ordre, envoi au groupe
    avec lancement de la navigation chez tous. Les étapes se posent et se modifient sur la carte (fn_wpCard).
    4.2.1 : distance et cap de l'étape active, reste à parcourir et tronçon « depuis moi » réécrits sur place chaque
    seconde (["live"], minuterie COMSPEC_ATAK_WpLive), téléphone ouvert sur cette page seulement : sans redessiner la page.
    Params : [mode] ("" : dessin de la page ; "live" : textes vivants seulement)
*/
params [["_mode", ""]];
disableSerialization;
private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
private _pts = _w getOrDefault ["pts", []];
private _idx = (_w getOrDefault ["idx", 0]) min (((count _pts) - 1) max 0);
private _nav = _w getOrDefault ["nav", false];
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _hex = { params ["_c"]; "#" + (((_c select [0, 3]) apply { private _v = ((round (_x * 255)) max 0) min 255; ("0123456789abcdef" select [floor (_v / 16), 1]) + ("0123456789abcdef" select [_v mod 16, 1]) }) joinString "") };
// Textes vivants (dessin et mise à jour sur place) : étape active, reste à parcourir, ligne d'une étape de la liste.
private _activeTxt = {
    (_pts select _idx) params ["_ap", "_an"];
    private _ac = (["type", (_pts select _idx) param [2, "WP"]] call comspec_atak_native_fnc_wpAction) select 3;
    format ["<t size='1.25' font='COMSPEC_BarlowBold' color='%1'>%2</t> <t size='1.25' font='COMSPEC_BarlowBold' color='%3'>%4. %5</t><br/>%6 · %7° %8 · <t font='EtelkaMonospacePro'>%9</t>",
        [(COMSPEC_ATAK_Th get "hexTextMuted"), (COMSPEC_ATAK_Th get "hexOk")] select _nav, ["EN PAUSE :", "CAP SUR"] select _nav, [_ac] call _hex, _idx + 1, _an regexReplace ["[<>&]", ""],
        [player distance2D _ap] call _fmt, round (player getDir _ap), [player getDir _ap] call _card, [_ap, 8] call comspec_atak_native_fnc_gridRef]
};
// Longueur restante à pied depuis ma position.
private _restTxt = {
    private _rest = 0; private _prev = getPosATL player;
    for "_i" from _idx to ((count _pts) - 1) do { _rest = _rest + (_prev distance2D ((_pts select _i) select 0)); _prev = (_pts select _i) select 0; };
    format ["%1 · ~%2 min à pied", [_rest] call _fmt, ceil (_rest / 1.4 / 60)]
};
// Ligne d'une étape : [texte, teinte de l'icône]. La première se mesure depuis moi : seule celle-là bouge.
private _stepTxt = {
    params ["_i"];
    (_pts select _i) params ["_p", "_n"];
    _n = [_n] call comspec_atak_native_fnc_markerAutoName; // FIN <-> END selon la langue
    private _tRow = ["type", (_pts select _i) param [2, "WP"]] call comspec_atak_native_fnc_wpAction;
    private _prevP = if (_i isEqualTo 0) then { getPosATL player } else { (_pts select (_i - 1)) select 0 };
    private _on = _i isEqualTo _idx;
    private _done = _nav && {_i < _idx};
    [format ["<t font='COMSPEC_BarlowBold' color='%1'>%2. %3</t>  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%4</t><br/><t size='0.8'>%5 %6 · %7°  <t font='EtelkaMonospacePro'>%8</t></t>",
        [[(COMSPEC_ATAK_Th get "hexTextDim"), (COMSPEC_ATAK_Th get "hexOk")] select _on, (COMSPEC_ATAK_Th get "hexTextMuted")] select _done, _i + 1, _n regexReplace ["[<>&]", ""], toLower (_tRow select 1),
        [_prevP distance2D _p] call _fmt, ["depuis l'étape précédente", "depuis moi"] select (_i isEqualTo 0), round (_prevP getDir _p), [_p, 8] call comspec_atak_native_fnc_gridRef],
        [_tRow select 3, (COMSPEC_ATAK_Th get "off")] select _done]
};
if (_mode isEqualTo "live") exitWith {
    private _ok = (count _pts) > 0;
    if (_ok) then {
        private _t = uiNamespace getVariable ["COMSPEC_ATAK_WpLiveTxt", controlNull];
        if (!isNull _t) then { _t ctrlSetStructuredText parseText ((call _activeTxt) call comspec_atak_native_fnc_tr); };
        ["wpRest", call _restTxt] call comspec_atak_native_fnc_formSet;
        ["wpStep0", [0] call _stepTxt] call comspec_atak_native_fnc_formSet;
    };
    _ok
};

private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _from = _w getOrDefault ["from", ""];
private _share = profileNamespace getVariable ["COMSPEC_ATAK_WpShare", 1];
private _openMap = { ["MAP"] call comspec_atak_native_fnc_navigate; [{ ["WP"] call comspec_atak_native_fnc_mapToolSet; ["MAP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
private _tot = 0;
for "_i" from 1 to ((count _pts) - 1) do { _tot = _tot + (((_pts select (_i - 1)) select 0) distance2D ((_pts select _i) select 0)); };
private _rows = [["section", "Itinéraire", [
    format ["%1 étape(s) · %2%3", count _pts, [_tot] call _fmt, ["", format [" · modifié par %1", _from]] select (_from isNotEqualTo "")],
    ""
] select ((count _pts) isEqualTo 0)]];
if ((count _pts) isEqualTo 0) then {
    _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_waypoints.paa", "Aucune étape",
        "Touchez POSER LES ÉTAPES SUR LA CARTE, en bas, ou faites un clic droit sur la carte > Point de passage. Un itinéraire partagé par le groupe arrive aussi ici."];
};
if ((count _pts) > 0) then {
    _rows pushBack ["text", call _activeTxt, [], "COMSPEC_ATAK_WpLiveTxt"];
    _rows pushBack ["buttons", [
        ["<", { ["prev"] call comspec_atak_native_fnc_wpAction; }, false, _idx > 0],
        [["NAVIGUER", "PAUSE"] select _nav, { ["nav"] call comspec_atak_native_fnc_wpAction; }, true],
        [">", { ["next"] call comspec_atak_native_fnc_wpAction; }, false, _idx < ((count _pts) - 1)]
    ]];
    _rows pushBack ["info", "Reste à parcourir", call _restTxt, "wpRest"];
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
        ([_forEachIndex] call _stepTxt) params ["_txt", "_col"];
        _rows pushBack ["person", ["icon", _x param [2, "WP"]] call comspec_atak_native_fnc_wpAction, _txt,
            [
                ["ALLER", compile format ["['goto', %1] call comspec_atak_native_fnc_wpAction;", _forEachIndex], false, true, "Cap sur cette étape"],
                ["MODIFIER", compile format ["['MAP'] call comspec_atak_native_fnc_navigate; [{ ['edit', %1] call comspec_atak_native_fnc_wpAction; }] call comspec_atak_native_fnc_nextFrame;", _forEachIndex], false, true, "Renommer, changer le type ou déplacer sur la carte"],
                ["X", compile format ["['del', %1] call comspec_atak_native_fnc_wpAction;", _forEachIndex], false, true, "Supprimer l'étape"]
            ],
            _col, "", ["", "wpStep0"] select (_forEachIndex isEqualTo 0)];
    } forEach _pts;
    _rows pushBack ["buttons", [["INVERSER", { ["reverse"] call comspec_atak_native_fnc_wpAction; }, false, (count _pts) > 1, "Parcourir l'itinéraire dans l'autre sens"], ["TOUT EFFACER", { ["clear"] call comspec_atak_native_fnc_wpAction; }, "danger", true, "Efface toutes les étapes ; ANNULER les rend pendant 8 s, avant tout envoi au groupe"]]];
    // App Routes : l'itinéraire copié en route nommée (type de tracé, style, étapes typées, partage, guidage).
    _rows pushBack ["buttons", [["COPIER DANS L'APP ROUTES", { ["ROUTE"] call comspec_atak_native_fnc_navigate; ["#ui", ["import"]] call comspec_atak_native_fnc_pageRoute; }, false, true, "Crée une route nommée avec ces étapes : type de tracé, style, consignes, heure prévue, partage"]]];
};
_rows pushBack ["buttons", [[["POSER LES ÉTAPES SUR LA CARTE", "AJOUTER DES ÉTAPES SUR LA CARTE"] select ((count _pts) > 0), _openMap, true]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
// Minuterie de la page (une seule) : textes vivants chaque seconde, arrêtée dès que la page n'est plus affichée ou que le
// téléphone est fermé. Rien à suivre sans étape.
if ((count _pts) > 0 && {isNil { uiNamespace getVariable "COMSPEC_ATAK_WpLive" }}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_WpLive", [{
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if (isNull ([] call comspec_atak_native_fnc_display) || {(_st getOrDefault ["activePage", ""]) isNotEqualTo "WAYPOINTS"} || {!(["live"] call comspec_atak_native_fnc_pageWaypoints)}) exitWith {
            [_this select 1] call CBA_fnc_removePerFrameHandler;
            uiNamespace setVariable ["COMSPEC_ATAK_WpLive", nil];
        };
    }, 1] call CBA_fnc_addPerFrameHandler];
};
true
