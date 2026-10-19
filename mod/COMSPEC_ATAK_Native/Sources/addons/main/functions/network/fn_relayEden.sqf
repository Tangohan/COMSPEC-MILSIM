/*
    Relais dans l'éditeur Eden.
      ["watch"] call comspec_atak_native_fnc_relayEden
          Aperçu à la pose : un module « ATAK · Relais » de type « Tour fixe » sans objet synchronisé crée la tour de 40 m
          au lancement ; dans Eden on la voit déjà à sa place (objet local, ni sauvé ni exporté), elle suit le module
          déplacé ou tourné et disparaît avec lui ou quand il change de type ou reçoit un objet synchronisé.
          Lancé par Cfg3DEN (ouverture / nouvelle mission) et par l'attribut Type du module ; une seule boucle.
      ["table"] call ...   TBR, tableau des relais (menu COMSPEC ATAK d'Eden) : pylônes et modules relais de la mission,
                           nom, type, camp, portée, bande, liaison, état, position ; répéteurs sans relais raccordé à portée
                           signalés ; Aller (caméra), Sélectionner, double clic = les deux.
      ["help"] call ...    Aide relais (même menu) : texte du manuel de terrain dans une fenêtre de l'éditeur.
*/
params [["_mode", "watch"]];
if (!is3DEN) exitWith {};
disableSerialization;
// Menus d'Eden (TBR, aide) : l'action d'un menu s'exécute pendant que la liste déroulante du menu est encore ouverte.
// Une fenêtre créée à ce moment restait invisible et gardait la souris (menu bloqué) ; on la crée donc juste après,
// dans un script à part, une fois le menu refermé.
if (_mode in ["table", "help"] && {!canSuspend}) exitWith { [_mode] spawn { uiSleep 0.05; _this call comspec_atak_native_fnc_relayEden; }; };
// Fenêtre de l'éditeur : fond assombri, panneau centré, titre. Renvoie [display, x, y, l, h, unité] ou [].
// Positions en safeZone (comme le formulaire Zeus) ; Échap ferme (RscDisplayEmpty).
private _window = {
    params ["_titleTxt", ["_wr", 0.62], ["_hr", 0.62]];
    private _parent = findDisplay 313;
    if (isNull _parent) exitWith { [] };
    private _d = _parent createDisplay "RscDisplayEmpty";
    if (isNull _d) exitWith { [] };
    private _w = safeZoneW * _wr * 0.75;
    private _h = safeZoneH * _hr;
    private _x0 = safeZoneX + (safeZoneW - _w) / 2;
    private _y0 = safeZoneY + (safeZoneH - _h) / 2 + safeZoneH * 0.015;
    private _u = safeZoneH * 0.006;
    private _dim = _d ctrlCreate ["ctrlStatic", -1];
    _dim ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
    _dim ctrlSetBackgroundColor [0, 0, 0, 0.45];
    _dim ctrlCommit 0;
    private _bg = _d ctrlCreate ["ctrlStatic", -1];
    _bg ctrlSetPosition [_x0, _y0, _w, _h];
    _bg ctrlSetBackgroundColor [0.13, 0.14, 0.13, 0.97];
    _bg ctrlCommit 0;
    private _title = _d ctrlCreate ["ctrlStatic", -1];
    _title ctrlSetPosition [_x0, _y0 - 5 * _u, _w, 5 * _u];
    _title ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
    _title ctrlSetTextColor [1, 1, 1, 1];
    _title ctrlSetText ((("  " + _titleTxt)) call comspec_atak_native_fnc_tr);
    _title ctrlCommit 0;
    [_d, _x0, _y0, _w, _h, _u]
};
private _towerCls = "COMSPEC_ATAK_RelayTower_Base";
private _modCls = "COMSPEC_ATAK_Module_Relay";
switch (_mode) do {
    case "watch": {
        if (!isNil {uiNamespace getVariable "COMSPEC_ATAK_EdenWatch"} && {!scriptDone (uiNamespace getVariable "COMSPEC_ATAK_EdenWatch")}) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_EdenWatch", [] spawn {
            private _previews = createHashMap;
            private _model = "COMSPEC_ATAK_RelayTower_40";
            while { is3DEN } do {
                private _seen = [];
                if (isClass (configFile >> "CfgVehicles" >> _model)) then {
                    {
                        if (typeOf _x isNotEqualTo "COMSPEC_ATAK_Module_Relay") then { continue };
                        private _kind = (_x get3DENAttribute "COMSPEC_ATAK_Module_Relay_Kind") param [0, _x getVariable ["Kind", "mat"]];
                        private _sync = ((get3DENConnections _x) select { (_x select 0) isEqualTo "Sync" }) isNotEqualTo [];
                        if (_kind isNotEqualTo "tour" || _sync) then { continue };
                        private _key = str (get3DENEntityID _x);
                        _seen pushBack _key;
                        private _p = getPosATL _x;
                        private _o = _previews getOrDefault [_key, objNull];
                        if (isNull _o) then {
                            _o = createSimpleObject [_model, AGLToASL _p, true];
                            _previews set [_key, _o];
                        };
                        if ((getPosATL _o) distance2D _p > 0.05 || {abs ((getDir _o) - (getDir _x)) > 0.1}) then {
                            _o setDir (getDir _x);
                            _o setPosATL [_p select 0, _p select 1, 0];
                        };
                    } forEach ((all3DENEntities param [3, []]));
                };
                {
                    if !(_x in _seen) then { deleteVehicle _y; _previews deleteAt _x; };
                } forEach +_previews;
                // Aucun relais « tour » posé : la mission n'est relue que toutes les 1,5 s (all3DENEntities parcourt tout
                // l'éditeur) ; avec une tour, 0,4 s pour que l'aperçu suive le module déplacé.
                uiSleep ([1.5, 0.4] select (_seen isNotEqualTo []));
            };
            { deleteVehicle _y; } forEach _previews;
        }];
    };
    case "help": {
        // Aide relais (menu COMSPEC ATAK > Aide : poser un relais). Même texte que la page du manuel de terrain (CfgHints),
        // affiché dans une fenêtre à nous : BIS_fnc_openFieldManual lancé depuis ce menu cherchait un écran parent et
        // tombait sur un booléen (erreur createDisplay dans fn_openFieldManual.sqf, ligne 40).
        private _win = [localize "STR_COMSPEC_ATAK_CFG_2C44314089", 0.62, 0.66] call _window;
        if (_win isEqualTo []) exitWith {};
        _win params ["_d", "_x0", "_y0", "_w", "_h", "_u"];
        private _grp = _d ctrlCreate ["ctrlControlsGroup", -1];
        _grp ctrlSetPosition [_x0 + 2 * _u, _y0 + 2 * _u, _w - 4 * _u, _h - 11 * _u];
        _grp ctrlCommit 0;
        private _txt = _d ctrlCreate ["ctrlStructuredText", -1, _grp];
        private _tw = _w - 7 * _u;
        _txt ctrlSetPosition [0, 0, _tw, _h];
        _txt ctrlSetStructuredText parseText ((format [localize "STR_COMSPEC_ATAK_CFG_BF0FFB1714",
            "<br/><br/>", "<br/>• ", "<t color='#7ED38A' font='RobotoCondensedBold' size='1.1'>", "</t>"]) call comspec_atak_native_fnc_tr);
        _txt ctrlCommit 0;
        _txt ctrlSetPosition [0, 0, _tw, (ctrlTextHeight _txt) + 2 * _u];
        _txt ctrlCommit 0;
        private _bw = _w * 0.2;
        private _b = _d ctrlCreate ["ctrlButton", -1];
        _b ctrlSetPosition [_x0 + _w - _bw - _u, _y0 + _h - 6 * _u, _bw, 5 * _u];
        _b ctrlSetText ("Fermer" call comspec_atak_native_fnc_tr);
        _b ctrlAddEventHandler ["ButtonClick", { (ctrlParent (_this select 0)) closeDisplay 2; }];
        _b ctrlCommit 0;
        ctrlSetFocus _b;
    };
    case "table": {
        private _num = { params ["_v", "_d"]; [_d, _v] select (_v isEqualType 0) };
        private _sideTxt = createHashMapFromArray [["", "Tous"], ["WEST", "BLUFOR"], ["EAST", "OPFOR"], ["GUER", "Indépendants"], ["CIV", "Civils"]];
        private _kindTxt = createHashMapFromArray [["portable", "Radio portable"], ["mat", "Mât tactique"], ["tour", "Tour fixe"], ["satellite", "Satellite"]];
        private _presetR = createHashMapFromArray [["portable", 1500], ["mat", 3000], ["tour", 6000], ["satellite", 1000]];
        // [entité, nom, type, camp, portée m, raccordé, allumé, indestructible, position, bande MHz (0 = auto)]
        private _rows = [];
        {
            if !(_x isKindOf _towerCls) then { continue };
            private _cfg = configOf _x;
            private _n = (_x get3DENAttribute "COMSPEC_TowerName") param [0, ""];
            if (_n isEqualTo "") then { _n = format ["Pylône %1", mapGridPosition _x]; };
            _rows pushBack [_x, _n, getText (_cfg >> "displayName"),
                (_x get3DENAttribute "COMSPEC_TowerSide") param [0, ""], getNumber (_cfg >> "comspec_atak_relayRange"),
                ((_x get3DENAttribute "COMSPEC_TowerUplink") param [0, "root"]) isNotEqualTo "repeater",
                (_x get3DENAttribute "COMSPEC_TowerOn") param [0, true], (_x get3DENAttribute "COMSPEC_TowerIndestructible") param [0, false],
                getPosATL _x, (_x get3DENAttribute "COMSPEC_TowerFreq") param [0, 0]];
        } forEach (all3DENEntities param [0, []]);
        {
            if (typeOf _x isNotEqualTo _modCls) then { continue };
            private _a = { params ["_k", "_d"]; (_x get3DENAttribute ("COMSPEC_ATAK_Module_Relay_" + _k)) param [0, _d] };
            private _kind = ["Kind", "mat"] call _a;
            private _n = ["Name", ""] call _a;
            if (_n isEqualTo "") then { _n = format ["Relais %1", mapGridPosition _x]; };
            private _area = (_x get3DENAttribute "size2") param [0, []];
            private _r = if ((count _area) >= 2) then { (_area select 0) max (_area select 1) } else { 0 };
            if (_r <= 0) then { _r = _presetR getOrDefault [_kind, 3000]; };
            private _synced = ((get3DENConnections _x) select { (_x select 0) isEqualTo "Sync" }) isNotEqualTo [];
            _rows pushBack [_x, _n, format ["Module · %1%2", _kindTxt getOrDefault [_kind, _kind], ["", " (objet synchronisé)"] select _synced],
                ["Side", ""] call _a, [_r, 3000] call _num, (["Uplink", "root"] call _a) isNotEqualTo "repeater",
                ["StartOn", true] call _a, ["Indestructible", false] call _a, getPosATL _x, ["Freq", 0] call _a];
        } forEach (all3DENEntities param [3, []]);
        // Répéteur isolé : aucun relais raccordé (ou répéteur déjà relié) à portée, relief ignoré (estimation Eden).
        private _fed = _rows apply { (_x select 5) && {_x select 6} };
        private _changed = true;
        while { _changed } do {
            _changed = false;
            {
                if (_fed select _forEachIndex || {!(_x select 6)}) then { continue };
                private _r = _x;
                private _i = _forEachIndex;
                for "_j" from 0 to (count _rows) - 1 do {
                    private _o = _rows select _j;
                    if ((_fed select _j) && {((_o select 8) distance2D (_r select 8)) <= ((_o select 4) max (_r select 4))}) exitWith {
                        _fed set [_i, true];
                        _changed = true;
                    };
                };
            } forEach _rows;
        };

        private _win = [format ["TBR · Tableau des relais (%1)", count _rows] call comspec_atak_native_fnc_tr, 0.9, 0.62] call _window;
        if (_win isEqualTo []) exitWith {};
        _win params ["_d", "_x0", "_y0", "_w", "_h", "_u"];
        private _list = _d ctrlCreate ["ctrlListNBox", 100];
        _list ctrlSetPosition [_x0 + _u, _y0 + _u, _w - 2 * _u, _h - 14 * _u];
        _list lnbSetColumnsPos [0, 0.22, 0.40, 0.49, 0.57, 0.66, 0.78, 0.89];
        _list ctrlCommit 0;
        _list lnbAddRow (((["Nom", "Type", "Camp", "Portée", "Bande", "Liaison", "État", "Position"] apply { _x call comspec_atak_native_fnc_tr })) call comspec_atak_native_fnc_tr);
        _list lnbSetColor [[0, 0], [0.6, 0.65, 0.62, 1]];
        for "_c" from 0 to 7 do { _list lnbSetColor [[0, _c], [0.6, 0.65, 0.62, 1]]; };
        private _isolated = 0;
        {
            _x params ["_e", "_n", "_t", "_s", "_r", "_root", "_on", "_ind", "_p", "_f"];
            private _link = ["Répéteur", "Raccordé"] select _root;
            private _st = ["Éteint", "Allumé"] select _on;
            if (_ind) then { _st = _st + " · indestr."; };
            if (_on && {!(_fed select _forEachIndex)}) then { _link = _link + " · isolé"; _isolated = _isolated + 1; };
            private _i = _list lnbAddRow ((([_n, _t, _sideTxt getOrDefault [_s, _s], format ["%1 km", (_r / 1000) toFixed 1], [format ["%1 MHz", _f], "auto"] select (_f isEqualTo 0), _link, _st, mapGridPosition _p] apply { _x call comspec_atak_native_fnc_tr })) call comspec_atak_native_fnc_tr);
            _list lnbSetValue [[_i, 0], _forEachIndex];
            if (_on && {!(_fed select _forEachIndex)}) then { _list lnbSetColor [[_i, 5], [1, 0.6, 0.2, 1]]; };
            if (!_on) then { for "_c" from 0 to 7 do { _list lnbSetColor [[_i, _c], [0.6, 0.6, 0.6, 1]]; }; };
        } forEach _rows;
        uiNamespace setVariable ["COMSPEC_ATAK_TbrRows", _rows apply { [_x select 0, _x select 8] }];
        private _info = _d ctrlCreate ["ctrlStructuredText", -1];
        _info ctrlSetPosition [_x0 + _u, _y0 + _h - 12 * _u, _w - 2 * _u, 5 * _u];
        private _txt = if (_rows isEqualTo []) then {
            "Aucun relais : posez un pylône (Objets, Structures, Relais ATAK) ou le module ATAK · Relais (Modules, Réseau ATAK)."
        } else {
            format ["%1 raccordé(s), %2 répéteur(s)%3. Portées sans relief : la page Réseau du téléphone donne la couverture réelle.",
                { (_x select 5) } count _rows, { !(_x select 5) } count _rows,
                ["", format [", %1 isolé(s) sans relais raccordé à portée", _isolated]] select (_isolated > 0)]
        };
        _info ctrlSetStructuredText parseText (_txt call comspec_atak_native_fnc_tr);
        _info ctrlCommit 0;
        private _act = {
            params ["_list", "_what"];
            private _i = lnbCurSelRow _list;
            if (_i < 1) exitWith {};
            private _row = (uiNamespace getVariable ["COMSPEC_ATAK_TbrRows", []]) param [_list lnbValue [_i, 0], []];
            if (_row isEqualTo []) exitWith {};
            _row params ["_e", "_p"];
            if (_what in ["go", "both"]) then { move3DENCamera [_p, true]; };
            if (_what in ["sel", "both"] && {!isNull _e}) then { set3DENSelected [_e]; };
        };
        uiNamespace setVariable ["COMSPEC_ATAK_TbrAct", _act];
        private _bw = _w * 0.18;
        private _by = _y0 + _h - 6 * _u;
        {
            _x params ["_lbl", "_code", "_k"];
            private _b = _d ctrlCreate ["ctrlButton", -1];
            _b ctrlSetPosition [_x0 + _w - (_k + 1) * (_bw + _u), _by, _bw, 5 * _u];
            _b ctrlSetText (_lbl call comspec_atak_native_fnc_tr);
            _b ctrlAddEventHandler ["ButtonClick", _code];
            _b ctrlCommit 0;
        } forEach [
            ["Fermer", { (ctrlParent (_this select 0)) closeDisplay 2; }, 0],
            ["Sélectionner", { [(ctrlParent (_this select 0)) displayCtrl 100, "sel"] call (uiNamespace getVariable "COMSPEC_ATAK_TbrAct"); }, 1],
            ["Aller", { [(ctrlParent (_this select 0)) displayCtrl 100, "go"] call (uiNamespace getVariable "COMSPEC_ATAK_TbrAct"); }, 2]
        ];
        _list ctrlAddEventHandler ["LBDblClick", { params ["_c"]; [_c, "both"] call (uiNamespace getVariable "COMSPEC_ATAK_TbrAct"); (ctrlParent _c) closeDisplay 2; }];
    };
};
