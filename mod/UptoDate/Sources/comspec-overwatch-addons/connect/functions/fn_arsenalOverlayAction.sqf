/*
    Actions du panneau Tenues Athena (ACE Arsenal).
      "toggle" / "close" / "reload"        ouvrir-fermer le panneau, recharger depuis Athena
      "tabCloud" / "tabLocal" / "filter"   onglet, recherche ou collection changés
      "select", i / "apply", i            aperçu de la ligne i, enfiler (double-clic ou ENFILER)
      "keep"                              Communauté : importer dans mon arsenal · Mes tenues : partager
      "remove"                            Communauté : retirer (si c'est la vôtre) · Mes tenues : supprimer
      "bulk"                              importer / partager toutes les tenues affichées (filtre appliqué)
      "saveCurrent"                       enregistre la tenue du mannequin sous le nom saisi, puis la partage
*/
params [["_act", ""], ["_arg", -1]];
private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
if (isNull _d) exitWith {};
private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _g) exitWith {};
if ((_g getVariable ["COMSPEC_ArsenalUiLock", false]) && {_act in ["select", "filter"]}) exitWith {};
private _isCloud = (_g getVariable ["COMSPEC_ArsenalTab", "cloud"]) isEqualTo "cloud";
private _say = { params ["_t", ["_lvl", "info"]]; [_t, "arsenal", _lvl, true] call comspec_overwatch_connect_fnc_announce; };
private _hint = { params ["_t"]; [_g, _t] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]); };
private _row = {
    private _i = _g getVariable ["COMSPEC_ArsenalSelected", -1];
    (_g getVariable ["COMSPEC_ArsenalShown", []]) param [_i, []]
};
private _reload = { missionNamespace setVariable ["COMSPEC_ArsenalWardrobeAt", -1e9, false]; _d setVariable ["COMSPEC_ArsenalForceList", true]; [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
private _metaCloud = { params ["_r"]; format ["%1%2%3", _r select 3, ["", format [" · par %1", _r select 4]] select ((_r select 4) isNotEqualTo ""), ["", format [" · %1", (_r select 5) select [0, 10]]] select ((_r select 5) isNotEqualTo "")] };

switch (_act) do {
    case "toggle": {
        private _open = !(_d getVariable ["COMSPEC_ArsenalOverlayOpen", false]);
        _d setVariable ["COMSPEC_ArsenalOverlayOpen", _open];
        _g ctrlShow _open;
        private _t = _d getVariable ["COMSPEC_ArsenalToggle", controlNull];
        if (!isNull _t) then { _t ctrlSetText (["ATHENA · TENUES", "FERMER LES TENUES"] select _open); };
        if (_open) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayBeginLoad; };
        // Les cadres de l'arsenal ACE placés sous le panneau (statistiques, infos) se dessinaient par-dessus :
        // masqués tant que le panneau est ouvert (ACE les réaffiche, donc on repasse toutes les 0,25 s).
        private _hidden = _d getVariable ["COMSPEC_ArsenalHidden", []];
        { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;
        _d setVariable ["COMSPEC_ArsenalHidden", []];
        if (_open) then {
            (ctrlPosition _g) params ["_gx", "_gy", "_gw", "_gh"];
            private _screen = safeZoneW * safeZoneH;
            private _under = (allControls _d) select {
                isNull (ctrlParentControlsGroup _x) && {_x isNotEqualTo _g} && {_x isNotEqualTo _t} && {ctrlShown _x}
                && { (ctrlPosition _x) params ["_cx", "_cy", "_cw", "_ch"];
                     (_cw * _ch) < (_screen * 0.5) && {_cx < (_gx + _gw)} && {(_cx + _cw) > _gx} && {_cy < (_gy + _gh)} && {(_cy + _ch) > _gy} }
            };
            _d setVariable ["COMSPEC_ArsenalHidden", _under];
            [{
                params ["_d", "_pfh"];
                if (isNull _d || {!(_d getVariable ["COMSPEC_ArsenalOverlayOpen", false])}) exitWith { [_pfh] call CBA_fnc_removePerFrameHandler; };
                { if (!isNull _x && {ctrlShown _x}) then { _x ctrlShow false; }; } forEach (_d getVariable ["COMSPEC_ArsenalHidden", []]);
            }, 0.25, _d] call CBA_fnc_addPerFrameHandler;
            { _x ctrlShow false; } forEach _under;
        };
    };
    case "close": { _d setVariable ["COMSPEC_ArsenalOverlayOpen", true]; ["toggle"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "reload": { [] spawn { ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }; };
    case "reloadNow": { ["Rechargement depuis Athena…"] call _hint; call _reload; };
    case "tabCloud";
    case "tabLocal": {
        private _t = ["local", "cloud"] select (_act isEqualTo "tabCloud");
        _g setVariable ["COMSPEC_ArsenalTab", _t];
        profileNamespace setVariable ["COMSPEC_ArsenalTab", _t];
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
    };
    case "filter": { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayFill; };
    case "select": {
        _g setVariable ["COMSPEC_ArsenalSelected", _arg];
        private _r = call _row;
        if (_r isEqualTo []) exitWith { [_d, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview; };
        if (_isCloud) then {
            [_d, [], _r select 2, "Chargement…"] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
            [_d, _r, _arg, [_r] call _metaCloud] spawn {
                params ["_d", "_r", "_i", "_meta"];
                private _lo = [_r select 0] call comspec_overwatch_connect_fnc_arsenalCloudLoadout;
                private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
                if (isNull _g || {(_g getVariable ["COMSPEC_ArsenalSelected", -1]) isNotEqualTo _i}) exitWith {};
                [_d, _lo, _r select 2, _meta] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
            };
        } else {
            [_d, [_r select 3] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout, _r select 1, format ["%1 · sur cet ordinateur", _r select 2]] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
        };
    };
    case "apply": {
        if (_arg isEqualType 0 && {_arg >= 0}) then { _g setVariable ["COMSPEC_ArsenalSelected", _arg]; };
        private _r = call _row;
        if (_r isEqualTo []) exitWith { ["Choisissez d'abord une tenue dans la liste."] call _say; };
        if (_isCloud) then {
            [_r select 0] spawn { params ["_id"]; [_id] call comspec_overwatch_connect_fnc_arsenalApplyCloud; };
        } else {
            [[_r select 3] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout, _r select 0] call comspec_overwatch_connect_fnc_arsenalApplyLoadout;
        };
    };
    case "keep": {
        private _r = call _row;
        if (_r isEqualTo []) exitWith { ["Choisissez d'abord une tenue dans la liste."] call _say; };
        [_r, _isCloud] spawn {
            params ["_r", "_isCloud"];
            if (_isCloud) then { ["", [_r select 0]] call comspec_overwatch_connect_fnc_arsenalPullAll; } else { [[_r select 0]] call comspec_overwatch_connect_fnc_arsenalPushAll; };
            ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        };
    };
    case "remove": {
        private _r = call _row;
        if (_r isEqualTo []) exitWith { ["Choisissez d'abord une tenue dans la liste."] call _say; };
        [_r, _isCloud, _d] spawn {
            params ["_r", "_isCloud", "_d"];
            private _label = [_r select 1, _r select 2] select _isCloud;
            private _ok = [format [["Supprimer « %1 » de votre arsenal sur cet ordinateur ?", "Retirer « %1 » des tenues de l'organisation ? Seul son auteur ou un responsable peut le faire."] select _isCloud, _label], "Tenues Athena", true, true, _d] call BIS_fnc_guiMessage;
            if (!_ok) exitWith {};
            private _done = if (_isCloud) then { [_r select 0] call comspec_overwatch_connect_fnc_arsenalDeleteCloud } else { [_r select 0] call comspec_overwatch_connect_fnc_arsenalDeleteLocal };
            if (_done) then { [format ["« %1 » retirée.", _label], "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce; };
            ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        };
    };
    case "bulk": {
        private _shown = _g getVariable ["COMSPEC_ArsenalShown", []];
        if ((count _shown) isEqualTo 0) exitWith {};
        [_shown, _isCloud, _d] spawn {
            params ["_shown", "_isCloud", "_d"];
            private _ok = [format [["Partager %1 tenue(s) à l'organisation ?", "Importer %1 tenue(s) dans votre arsenal ? Une tenue du même nom sera remplacée."] select _isCloud, count _shown], "Tenues Athena", true, true, _d] call BIS_fnc_guiMessage;
            if (!_ok) exitWith {};
            if (_isCloud) then { ["", _shown apply { _x select 0 }] call comspec_overwatch_connect_fnc_arsenalPullAll; } else { [_shown apply { _x select 0 }] call comspec_overwatch_connect_fnc_arsenalPushAll; };
            ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        };
    };
    case "saveCurrent": {
        private _name = trim ctrlText (_g getVariable ["COMSPEC_ArsenalSaveName", controlNull]);
        if (_name isEqualTo "") exitWith { ["Donnez un nom à la tenue (ex. SOAR - Breacher), puis Enregistrer."] call _say; };
        private _unit = missionNamespace getVariable ["ace_arsenal_center", player];
        if (isNull _unit) then { _unit = player; };
        private _lo = getUnitLoadout _unit;
        private _saved = profileNamespace getVariable ["ace_arsenal_saved_loadouts", []];
        if !(_saved isEqualType []) then { _saved = []; };
        private _i = _saved findIf { (_x isEqualType []) && {(toLower (_x param [0, ""])) isEqualTo (toLower _name)} };
        if (_i >= 0) then { _saved set [_i, [_name, _lo]]; } else { _saved pushBack [_name, _lo]; };
        profileNamespace setVariable ["ace_arsenal_saved_loadouts", _saved];
        profileNamespace setVariable ["COMSPEC_ArsenalLastSaveName", _name];
        saveProfileNamespace;
        [format ["« %1 » enregistrée dans votre arsenal, partage en cours…", _name], "ok"] call _say;
        [_name] spawn { params ["_n"]; [[_n]] call comspec_overwatch_connect_fnc_arsenalPushAll; ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    };
};
