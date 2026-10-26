/*
    Actions du panneau Tenues Athena (ACE Arsenal).
      "toggle" / "close" / "reload"        ouvrir-fermer le panneau, recharger depuis Athena
      "tabCloud" / "tabLocal" / "filter"   onglet, recherche changés
      "stateFilter" / "sort", k           filtre d'état (liste), tri (coll, name, state, recent)
      "chip", coll / "chipPrev" / "chipNext"  pastille de collection, défilement des pastilles
      "pillPending" / "pillNew"           Mes tenues pas à jour sur le site / Communauté nouvelles
      "select", i / "apply", i            aperçu de la ligne i, enfiler (double-clic ou ENFILER)
      "keep"                              Communauté : importer · Mes tenues : envoyer au site, ou prendre la version
                                          du site si elle y a changé
      "remove"                            Communauté : retirer (si c'est la vôtre) · Mes tenues : supprimer
      "bulk"                              Mes tenues : envoyer celles qui ne sont pas à jour sur le site (pas les
                                          autres) · Communauté : importer celles que je n'ai pas (filtre appliqué)
      "saveCurrent"                       enregistre la tenue du mannequin sous le nom saisi, puis l'envoie
      "markSeen"                          les tenues de la communauté affichées ne sont plus « nouvelles »
      "pick" / "markAll"                  Mes tenues : sélection multiple (un clic coche), tout (dé)cocher l'affiché
      "deleteMarked" / "undoDelete"       supprimer les tenues cochées en une fois, les remettre (dernière suppression)
      "loadoutsOpen", display / "loadoutsClosed"   écran « Mes équipements » d'ACE ouvert / fermé (arsenalInitOverlay)
*/
params [["_act", ""], ["_arg", -1]];
private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
if (isNull _d) exitWith {};
// Écran « Mes équipements » d'ACE par-dessus l'arsenal : rien des Tenues Athena ne reste actif dessous (un de nos
// champs gardait le focus et captait la frappe du champ Nom d'ACE), le champ Nom d'ACE prend le focus.
if (_act isEqualTo "loadoutsOpen") exitWith {
    ace_arsenal_leftSearchbarFocus = false;
    private _was = [];
    {
        private _c = _d getVariable [_x, controlNull];
        if (!isNull _c) then { _was pushBack [_c, ctrlShown _c]; _c ctrlEnable false; _c ctrlShow false; };
    } forEach ["COMSPEC_ArsenalToggle", "COMSPEC_ArsenalOverlay"];
    _d setVariable ["COMSPEC_ArsenalBehindLoadouts", _was];
    if (_arg isEqualType displayNull && {!isNull _arg}) then {
        [{ if (!isNull _this) then { ctrlSetFocus (_this displayCtrl 303); }; }, _arg] call CBA_fnc_execNextFrame;
    };
};
if (_act isEqualTo "loadoutsClosed") exitWith {
    { _x params ["_c", "_shown"]; if (!isNull _c) then { _c ctrlEnable true; _c ctrlShow _shown; }; } forEach (_d getVariable ["COMSPEC_ArsenalBehindLoadouts", []]);
    _d setVariable ["COMSPEC_ArsenalBehindLoadouts", []];
    // Une tenue a pu être enregistrée ou supprimée dans l'écran d'ACE : Mes tenues relue si le panneau est ouvert.
    if (_d getVariable ["COMSPEC_ArsenalOverlayOpen", false]) then { [_d] spawn { params ["_d"]; [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; }; };
};
private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _g) exitWith {};
if ((_g getVariable ["COMSPEC_ArsenalUiLock", false]) && {_act in ["select", "filter", "stateFilter", "sort"]}) exitWith {};
private _isCloud = (_g getVariable ["COMSPEC_ArsenalTab", "local"]) isEqualTo "cloud";
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
        if (!isNull _t) then { _t ctrlSetText (((["ATHENA · TENUES", "FERMER LES TENUES"] select _open)) call comspec_overwatch_connect_fnc_tr); };
        if (_open) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayBeginLoad; } else {
            ["markSeen"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            // Panneau masqué : ses champs ne gardent pas le focus (la frappe partirait dans un champ invisible).
            ace_arsenal_leftSearchbarFocus = false;
            if (!isNull _t) then { ctrlSetFocus _t; };
        };
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
        if (_t isNotEqualTo (_g getVariable ["COMSPEC_ArsenalTab", "local"])) then {
            _g setVariable ["COMSPEC_ArsenalColl", ""];
            _g setVariable ["COMSPEC_ArsenalChipOffset", 0];
            _g setVariable ["COMSPEC_ArsenalStateKey", if (_arg isEqualType "" && {_arg isNotEqualTo ""}) then { _arg } else { "all" }];
        } else {
            if (_arg isEqualType "" && {_arg isNotEqualTo ""}) then { _g setVariable ["COMSPEC_ArsenalStateKey", _arg]; };
        };
        _g setVariable ["COMSPEC_ArsenalTab", _t];
        profileNamespace setVariable ["COMSPEC_ArsenalTab", _t];
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
    };
    case "filter": { _g setVariable ["COMSPEC_ArsenalChipOffset", 0]; [_d] call comspec_overwatch_connect_fnc_arsenalOverlayFill; };
    case "stateFilter": {
        private _c = _g getVariable ["COMSPEC_ArsenalStateCombo", controlNull];
        if (isNull _c || {(lbCurSel _c) < 0}) exitWith {};
        _g setVariable ["COMSPEC_ArsenalStateKey", _c lbData (lbCurSel _c)];
        _g setVariable ["COMSPEC_ArsenalChipOffset", 0];
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
    };
    case "sort": {
        if !(_arg isEqualType "") exitWith {};
        _g setVariable ["COMSPEC_ArsenalSort", _arg];
        profileNamespace setVariable ["COMSPEC_ArsenalSort", _arg];
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
    };
    case "chip": {
        _g setVariable ["COMSPEC_ArsenalColl", [_arg, ""] select !(_arg isEqualType "")];
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
    };
    case "chipPrev";
    case "chipNext": {
        // Défile d'une « page » : vers la droite, la première pastille masquée devient la première affichée.
        private _off = _g getVariable ["COMSPEC_ArsenalChipOffset", 0];
        private _n = count (_g getVariable ["COMSPEC_ArsenalChips", []]);
        _g setVariable ["COMSPEC_ArsenalChipOffset", if (_act isEqualTo "chipNext") then { _off + (_n max 1) } else { (_off - 4) max 0 }];
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
    };
    case "pillPending": { ["tabLocal", "pending"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "pillNew": { ["tabCloud", "new"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "markSeen": { [] call comspec_overwatch_connect_fnc_arsenalMarkSeen; };
    case "select": {
        // Sélection multiple : le clic coche ou décoche la ligne, puis la ligne est désélectionnée pour qu'un second
        // clic sur la même tenue la décoche.
        if (!_isCloud && {_g getVariable ["COMSPEC_ArsenalPickMode", false]}) exitWith {
            private _list = _g getVariable ["COMSPEC_ArsenalList", controlNull];
            if (!(_arg isEqualType 0) || {_arg < 0} || {isNull _list}) exitWith {};
            private _r = (_g getVariable ["COMSPEC_ArsenalShown", []]) param [_list lnbValue [_arg, 0], []];
            if (_r isEqualTo []) exitWith {};
            private _k = toLower (_r select 0);
            private _marked = _g getVariable ["COMSPEC_ArsenalMarked", []];
            private _on = !(_k in _marked);
            if (_on) then { _marked pushBackUnique _k; } else { _marked = _marked - [_k]; };
            _g setVariable ["COMSPEC_ArsenalMarked", _marked];
            [_list, _arg, _on] call comspec_overwatch_connect_fnc_arsenalOverlayMark;
            [_g] call (_g getVariable ["COMSPEC_ArsenalPickRefresh", {}]);
            _g setVariable ["COMSPEC_ArsenalUiLock", true];
            _list lnbSetCurSelRow -1;
            _g setVariable ["COMSPEC_ArsenalUiLock", false];
        };
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
        // Sélection multiple : un double-clic coche puis décoche, il n'enfile pas la tenue.
        if (!_isCloud && {_g getVariable ["COMSPEC_ArsenalPickMode", false]} && {_arg isEqualType 0} && {_arg >= 0}) exitWith {};
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
        // Mes tenues modifiée sur le site : on prend la version du site (votre tenue sur le site, même nom).
        private _siteId = "";
        if (!_isCloud && {(_r select 4) isEqualTo "remote"}) then {
            private _k = toLower trim (_r select 0);
            private _hasMine = _g getVariable ["COMSPEC_ArsenalHasHash", false];
            private _i = (_g getVariable ["COMSPEC_ArsenalCloudRows", []]) findIf { (toLower trim (_x select 1)) isEqualTo _k && {!_hasMine || {_x select 8}} };
            if (_i >= 0) then { _siteId = ((_g getVariable ["COMSPEC_ArsenalCloudRows", []]) select _i) select 0; };
        };
        [_r, _isCloud, _siteId] spawn {
            params ["_r", "_isCloud", "_siteId"];
            switch (true) do {
                case (_isCloud): { ["", [_r select 0]] call comspec_overwatch_connect_fnc_arsenalPullAll; };
                case (_siteId isNotEqualTo ""): { ["", [_siteId]] call comspec_overwatch_connect_fnc_arsenalPullAll; };
                default { [[_r select 0]] call comspec_overwatch_connect_fnc_arsenalPushAll; };
            };
            ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        };
    };
    case "remove": {
        private _r = call _row;
        if (_r isEqualTo []) exitWith { ["Choisissez d'abord une tenue dans la liste."] call _say; };
        [_r, _isCloud, _d] spawn {
            params ["_r", "_isCloud", "_d"];
            private _label = [_r select 1, _r select 2] select _isCloud;
            private _ok = [format [(["Supprimer « %1 » de votre arsenal sur cet ordinateur ?", "Retirer « %1 » des tenues de l'organisation ? Seul son auteur ou un responsable peut le faire."] select _isCloud) call comspec_overwatch_connect_fnc_tr, _label], ("Tenues Athena") call comspec_overwatch_connect_fnc_tr, true, true, _d] call BIS_fnc_guiMessage;
            if (!_ok) exitWith {};
            private _done = if (_isCloud) then { [_r select 0] call comspec_overwatch_connect_fnc_arsenalDeleteCloud } else { [_r select 0] call comspec_overwatch_connect_fnc_arsenalDeleteLocal };
            if (_done) then {
                // Retirée du site par vous : plus « retirée du site » à signaler côté Mes tenues.
                if (_isCloud) then { ["del", _r select 1] call comspec_overwatch_connect_fnc_arsenalLedger; [] call comspec_overwatch_connect_fnc_saveProfile; };
                [format ["« %1 » retirée.", _label], "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;
            };
            ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        };
    };
    case "bulk": {
        // Seulement les tenues à envoyer (pas à jour sur le site) ou à importer (pas dans mon arsenal) : arsenalOverlayFill.
        private _shown = _g getVariable ["COMSPEC_ArsenalBulkRows", []];
        if ((count _shown) isEqualTo 0) exitWith {};
        [_shown, _isCloud, _d] spawn {
            params ["_shown", "_isCloud", "_d"];
            private _ok = [format [(["Envoyer au site %1 tenue(s) qui n'y sont pas à jour ? Les autres ne sont pas renvoyées.", "Importer %1 tenue(s) dans votre arsenal ? Une tenue du même nom sera remplacée."] select _isCloud) call comspec_overwatch_connect_fnc_tr, count _shown], ("Tenues Athena") call comspec_overwatch_connect_fnc_tr, true, true, _d] call BIS_fnc_guiMessage;
            if (!_ok) exitWith {};
            if (_isCloud) then { ["", _shown apply { _x select 0 }] call comspec_overwatch_connect_fnc_arsenalPullAll; } else { [_shown apply { _x select 0 }] call comspec_overwatch_connect_fnc_arsenalPushAll; };
            ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        };
    };
    case "pick": {
        if (_isCloud) exitWith {};
        private _mode = !(_g getVariable ["COMSPEC_ArsenalPickMode", false]);
        _g setVariable ["COMSPEC_ArsenalPickMode", _mode];
        if (!_mode) then { _g setVariable ["COMSPEC_ArsenalMarked", []]; };
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
    };
    case "markAll": {
        if (_isCloud) exitWith {};
        private _shown = (_g getVariable ["COMSPEC_ArsenalShown", []]) apply { toLower (_x select 0) };
        private _marked = _g getVariable ["COMSPEC_ArsenalMarked", []];
        _marked = if ((_shown findIf { !(_x in _marked) }) < 0) then { _marked - _shown } else { _marked + (_shown - _marked) };
        _g setVariable ["COMSPEC_ArsenalMarked", _marked];
        private _list = _g getVariable ["COMSPEC_ArsenalList", controlNull];
        if (!isNull _list) then {
            for "_i" from 0 to ((lnbSize _list) select 0) - 1 do {
                private _r = (_g getVariable ["COMSPEC_ArsenalShown", []]) param [_list lnbValue [_i, 0], []];
                if (_r isNotEqualTo []) then { [_list, _i, (toLower (_r select 0)) in _marked] call comspec_overwatch_connect_fnc_arsenalOverlayMark; };
            };
        };
        [_g] call (_g getVariable ["COMSPEC_ArsenalPickRefresh", {}]);
    };
    case "deleteMarked": {
        private _marked = _g getVariable ["COMSPEC_ArsenalMarked", []];
        if (_isCloud || {_marked isEqualTo []}) exitWith {};
        [_marked, _d] spawn {
            params ["_marked", "_d"];
            private _ok = [format [("Supprimer %1 tenue(s) de votre arsenal sur cet ordinateur ? Celles déjà sur le site y restent. Annuler les remet.") call comspec_overwatch_connect_fnc_tr, count _marked], ("Tenues Athena") call comspec_overwatch_connect_fnc_tr, true, true, _d] call BIS_fnc_guiMessage;
            if (!_ok) exitWith {};
            // Une seule écriture du profil pour toute la sélection ; les tenues retirées vont à la corbeille (Annuler).
            private _saved = profileNamespace getVariable ["ace_arsenal_saved_loadouts", []];
            if !(_saved isEqualType []) then { _saved = []; };
            private _gone = _saved select { (_x isEqualType []) && {(_x param [0, ""]) isEqualType ""} && {(toLower (_x param [0, ""])) in _marked} };
            profileNamespace setVariable ["ace_arsenal_saved_loadouts", _saved select { !((_x isEqualType []) && {(_x param [0, ""]) isEqualType ""} && {(toLower (_x param [0, ""])) in _marked}) }];
            profileNamespace setVariable ["COMSPEC_ArsenalTrash", _gone];
            [] call comspec_overwatch_connect_fnc_saveProfile;
            private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
            if (!isNull _g) then { _g setVariable ["COMSPEC_ArsenalMarked", []]; };
            [format [("%1 tenue(s) supprimée(s) de votre arsenal. ANNULER les remet.") call comspec_overwatch_connect_fnc_tr, count _gone], "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;
            ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        };
    };
    case "undoDelete": {
        private _trash = profileNamespace getVariable ["COMSPEC_ArsenalTrash", []];
        if !(_trash isEqualType [] && {_trash isNotEqualTo []}) exitWith {};
        private _saved = profileNamespace getVariable ["ace_arsenal_saved_loadouts", []];
        if !(_saved isEqualType []) then { _saved = []; };
        // Une tenue du même nom enregistrée depuis n'est pas écrasée.
        private _have = _saved apply { toLower (_x param [0, ""]) };
        private _back = _trash select { !((toLower (_x param [0, ""])) in _have) };
        profileNamespace setVariable ["ace_arsenal_saved_loadouts", _saved + _back];
        profileNamespace setVariable ["COMSPEC_ArsenalTrash", []];
        [] call comspec_overwatch_connect_fnc_saveProfile;
        [format [("%1 tenue(s) remise(s) dans votre arsenal.") call comspec_overwatch_connect_fnc_tr, count _back], "ok"] call _say;
        [] spawn { ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
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
        [] call comspec_overwatch_connect_fnc_saveProfile;
        [format ["« %1 » enregistrée dans votre arsenal, partage en cours…", _name], "ok"] call _say;
        [_name] spawn { params ["_n"]; [[_n]] call comspec_overwatch_connect_fnc_arsenalPushAll; ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    };
};
