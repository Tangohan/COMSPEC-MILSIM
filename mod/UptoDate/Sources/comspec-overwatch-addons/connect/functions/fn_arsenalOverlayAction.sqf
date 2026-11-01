/*
    Actions du panneau Tenues Athena (ACE Arsenal).
      "toggle" / "close" / "reload"        ouvrir-fermer le panneau, recharger la liste depuis Athena (Actualiser)
      "loaded"                             ligne de retour après un chargement (nombre de tenues, ou hors ligne)
      "redrawNow" / "redrawKeep"           relire Mes tenues avec la liste du site gardée sur ce PC (sans réseau) ;
                                           la tenue choisie reste choisie (arsenalOverlayFill, mode « data »)
      "tabCloud" / "tabLocal" / "tabFav"   onglet (Favoris : Communauté filtrée sur les favoris)
      "filter" / "filterNow" / "clearSearch"   recherche changée (filtrée 0,12 s après la dernière frappe), effacée
      "stateFilter" / "sort", k           filtre d'état (liste), tri (coll, name, state, recent)
      "chip", coll / "chipPrev" / "chipNext"  pastille de collection, défilement des pastilles
      "page", ±1 / "pagePrev" / "pageNext"   page de cartes (molette sur les cartes, ‹ ›)
      "pillPending" / "pillNew"           Mes tenues pas à jour sur le site / Communauté nouvelles
      "select", i / "apply", i            fiche de la carte i (index dans la liste affichée), enfiler (double-clic,
                                          ENFILER)
      "menu", i / "menuDo", act / "menuClose"   menu clic droit d'une carte : Enfiler, Importer / Envoyer / Mettre
                                          à jour, Supprimer, afficher sa collection, la cocher
      "keep"                              Communauté : importer · Mes tenues : envoyer au site, ou prendre la version
                                          du site si elle y a changé
      "remove"                            Communauté : retirer (si c'est la vôtre) · Mes tenues : supprimer
      "bulk"                              Mes tenues : envoyer celles qui ne sont pas à jour sur le site (pas les
                                          autres) · Communauté : importer celles que je n'ai pas (filtre appliqué)
      "syncAll"                           tout basculer dans l'arsenal ACE (arsenalSyncAll), après confirmation
      "saveCurrent"                       enregistre la tenue du mannequin sous le nom saisi, puis l'envoie
      "markSeen"                          les tenues de la communauté affichées ne sont plus « nouvelles »
      "pick" / "markAll"                  Mes tenues : sélection multiple (un clic coche), tout (dé)cocher l'affiché
      "deleteMarked" / "undoDelete"       supprimer les tenues cochées en une fois, les remettre (dernière suppression)
      "loadoutsOpen", display / "loadoutsClosed"   écran « Mes équipements » d'ACE ouvert / fermé (arsenalInitOverlay)
    Chaque action dit son issue sur la ligne de retour du pied (en cours, réussie, impossible) ; les messages
    d'arsenalPushAll / arsenalPullAll restent affichés comme avant.
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
private _hint = { params ["_t", ["_lvl", "info"]]; [_g, _t, _lvl] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]); };
private _row = {
    private _i = _g getVariable ["COMSPEC_ArsenalSelected", -1];
    (_g getVariable ["COMSPEC_ArsenalShown", []]) param [_i, []]
};
private _reload = { _d setVariable ["COMSPEC_ArsenalForceList", true]; [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
private _metaCloud = {
    params ["_r"];
    private _bits = [_r select 3];
    if ((_r select 4) isNotEqualTo "") then { _bits pushBack format [("par %1") call comspec_overwatch_connect_fnc_tr, _r select 4]; };
    if ((_r select 5) isNotEqualTo "") then { _bits pushBack ((_r select 5) select [0, 10]); };
    if ((_r param [14, ""]) isNotEqualTo "") then { _bits pushBack (_r select 14); };
    _bits joinString " · "
};
private _metaLocal = {
    params ["_r"];
    private _bits = [format ["%1 · %2", _r select 2, ("sur cet ordinateur") call comspec_overwatch_connect_fnc_tr]];
    if ((_r param [6, ""]) isNotEqualTo "") then { _bits pushBack format [("par %1") call comspec_overwatch_connect_fnc_tr, _r select 6]; };
    if ((_r param [10, ""]) isNotEqualTo "") then { _bits pushBack (_r select 10); };
    _bits joinString " · "
};
private _fill = { params [["_mode", ""]]; [_d, _mode] call comspec_overwatch_connect_fnc_arsenalOverlayFill; };

switch (_act) do {
    case "toggle": {
        ["menuClose"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
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
    case "reloadNow": { ["Rechargement depuis Athena…", "busy"] call _hint; call _reload; ["loaded"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "loaded": {
        if (_g getVariable ["COMSPEC_ArsenalOffline", false]) then {
            ["Athena injoignable : seules les tenues de cet ordinateur sont affichées. Reliez votre compte, puis Actualiser.", "warn"] call _hint;
        } else {
            [format [("Liste à jour : %1 tenue(s) dans votre arsenal, %2 partagée(s) par l'organisation.") call comspec_overwatch_connect_fnc_tr,
                count (_g getVariable ["COMSPEC_ArsenalLocalRows", []]), count (_g getVariable ["COMSPEC_ArsenalCloudRows", []])], "ok"] call _hint;
        };
    };
    // Rien n'a changé sur le site (import, suppression sur ce PC), ou comparaison des contenus terminée en arrière-plan
    // (arsenalOverlayRefresh) : pas de rechargement de la liste, la tenue choisie reste choisie.
    case "redrawNow";
    case "redrawKeep": { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
    case "tabFav": { ["tabCloud", "fav"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "tabCloud";
    case "tabLocal": {
        private _t = ["local", "cloud"] select (_act isEqualTo "tabCloud");
        private _k = ["", _arg] select (_arg isEqualType "" && {_arg isNotEqualTo ""});
        if (_t isNotEqualTo (_g getVariable ["COMSPEC_ArsenalTab", "local"])) then {
            _g setVariable ["COMSPEC_ArsenalColl", ""];
            _g setVariable ["COMSPEC_ArsenalChipOffset", 0];
            _g setVariable ["COMSPEC_ArsenalStateKey", [_k, "all"] select (_k isEqualTo "")];
        } else {
            // Communauté depuis Favoris : retour à toutes les tenues.
            private _cur = _g getVariable ["COMSPEC_ArsenalStateKey", "all"];
            _g setVariable ["COMSPEC_ArsenalStateKey", switch (true) do { case (_k isNotEqualTo ""): { _k }; case (_cur isEqualTo "fav" && {_t isEqualTo "cloud"}): { "all" }; default { _cur }; }];
        };
        _g setVariable ["COMSPEC_ArsenalTab", _t];
        profileNamespace setVariable ["COMSPEC_ArsenalTab", _t];
        [] call _fill;
    };
    // Frappe : filtrage léger une fois la frappe posée (0,12 s), pas à chaque touche.
    case "filter": {
        private _t = diag_tickTime;
        _g setVariable ["COMSPEC_ArsenalFilterAt", _t];
        [{
            params ["_g", "_t"];
            if (!isNull _g && {(_g getVariable ["COMSPEC_ArsenalFilterAt", -1]) isEqualTo _t}) then { ["filterNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
        }, [_g, _t], 0.12] call CBA_fnc_waitAndExecute;
    };
    case "filterNow": { _g setVariable ["COMSPEC_ArsenalChipOffset", 0]; ["light"] call _fill; };
    case "clearSearch": {
        private _s = _g getVariable ["COMSPEC_ArsenalSearch", controlNull];
        if (!isNull _s) then { _s ctrlSetText (("") call comspec_overwatch_connect_fnc_tr); ctrlSetFocus _s; };
        ["filterNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
    };
    case "stateFilter": {
        private _c = _g getVariable ["COMSPEC_ArsenalStateCombo", controlNull];
        if (isNull _c || {(lbCurSel _c) < 0}) exitWith {};
        _g setVariable ["COMSPEC_ArsenalStateKey", _c lbData (lbCurSel _c)];
        _g setVariable ["COMSPEC_ArsenalChipOffset", 0];
        [] call _fill;
    };
    case "sort": {
        if !(_arg isEqualType "") exitWith {};
        _g setVariable ["COMSPEC_ArsenalSort", _arg];
        profileNamespace setVariable ["COMSPEC_ArsenalSort", _arg];
        [] call _fill;
    };
    case "chip": {
        _g setVariable ["COMSPEC_ArsenalColl", [_arg, ""] select !(_arg isEqualType "")];
        [] call _fill;
    };
    case "chipPrev";
    case "chipNext": {
        // Défile d'une « page » : vers la droite, la première pastille masquée devient la première affichée.
        private _off = _g getVariable ["COMSPEC_ArsenalChipOffset", 0];
        private _n = _g getVariable ["COMSPEC_ArsenalChipCount", 1];
        _g setVariable ["COMSPEC_ArsenalChipOffset", if (_act isEqualTo "chipNext") then { _off + (_n max 1) } else { (_off - 4) max 0 }];
        ["light"] call _fill;
    };
    case "page": {
        if !(_arg isEqualType 0) exitWith {};
        private _per = (count (_g getVariable ["COMSPEC_ArsenalCards", []])) max 1;
        private _last = ((ceil ((count (_g getVariable ["COMSPEC_ArsenalShown", []])) / _per)) - 1) max 0;
        private _p = _g getVariable ["COMSPEC_ArsenalPage", 0];
        private _n = ((_p + _arg) max 0) min _last;
        if (_n isEqualTo _p) exitWith {};
        _g setVariable ["COMSPEC_ArsenalPage", _n];
        ["page"] call _fill;
    };
    case "pagePrev": { ["page", -1] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "pageNext": { ["page", 1] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "pillPending": { ["tabLocal", "pending"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "pillNew": { ["tabCloud", "new"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "markSeen": { [] call comspec_overwatch_connect_fnc_arsenalMarkSeen; };
    case "select": {
        if (!(_arg isEqualType 0) || {_arg < 0}) exitWith {};
        // Sélection multiple : le clic coche ou décoche la carte.
        if (!_isCloud && {_g getVariable ["COMSPEC_ArsenalPickMode", false]}) exitWith {
            private _r = (_g getVariable ["COMSPEC_ArsenalShown", []]) param [_arg, []];
            if (_r isEqualTo []) exitWith {};
            private _k = toLower (_r select 0);
            private _marked = _g getVariable ["COMSPEC_ArsenalMarked", []];
            if (_k in _marked) then { _marked = _marked - [_k]; } else { _marked pushBackUnique _k; };
            _g setVariable ["COMSPEC_ArsenalMarked", _marked];
            [_g] call (_g getVariable ["COMSPEC_ArsenalPaintCards", {}]);
            [_g] call (_g getVariable ["COMSPEC_ArsenalPickRefresh", {}]);
        };
        _g setVariable ["COMSPEC_ArsenalSelected", _arg];
        private _r = call _row;
        if (_r isEqualTo []) exitWith { _g setVariable ["COMSPEC_ArsenalSelKey", []]; [_d, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview; };
        _g setVariable ["COMSPEC_ArsenalSelKey", [["local", "cloud"] select _isCloud, [toLower (_r select 0), _r select 0] select _isCloud]];
        [_g] call (_g getVariable ["COMSPEC_ArsenalPaintCards", {}]);
        if (_isCloud) then {
            [_d, [], _r select 2, "Chargement…"] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
            [_d, _r, _arg, [_r] call _metaCloud] spawn {
                params ["_d", "_r", "_i", "_meta"];
                private _lo = [[_r select 0, _r select 9] call comspec_overwatch_connect_fnc_arsenalCloudLoadout] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
                private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
                if (isNull _g || {(_g getVariable ["COMSPEC_ArsenalSelected", -1]) isNotEqualTo _i}) exitWith {};
                [_d, _lo, _r select 2, _meta] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
            };
        } else {
            private _meta = [_r] call _metaLocal;
            private _lo = [_r select 3] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
            private _siteId = _r param [13, ""];
            if (_lo isEqualTo [] && {_siteId isNotEqualTo ""}) then {
                [_d, [], _r select 1, "Chargement…"] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
                [_d, _r, _arg, _meta, _siteId] spawn {
                    params ["_d", "_r", "_i", "_meta", "_id"];
                    private _lo = [[_id] call comspec_overwatch_connect_fnc_arsenalCloudLoadout] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
                    private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
                    if (isNull _g || {(_g getVariable ["COMSPEC_ArsenalSelected", -1]) isNotEqualTo _i}) exitWith {};
                    [_d, _lo, _r select 1, _meta] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
                };
            } else {
                [_d, _lo, _r select 1, _meta] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
            };
        };
    };
    // Menu clic droit d'une carte, à la souris, gardé dans le panneau ; le focus va à sa première ligne (sinon la carte
    // cliquée, qui garde le focus, se dessinait par-dessus).
    case "menu": {
        if (!(_arg isEqualType 0) || {_arg < 0}) exitWith {};
        if (!_isCloud && {_g getVariable ["COMSPEC_ArsenalPickMode", false]}) exitWith { ["select", _arg] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
        if ((_g getVariable ["COMSPEC_ArsenalSelected", -1]) isNotEqualTo _arg) then { ["select", _arg] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
        private _r = call _row;
        (_g getVariable ["COMSPEC_ArsenalActState", []]) params [["_has", false], ["_keep", ["", false]], ["_del", ["", false]]];
        if (_r isEqualTo [] || {!_has}) exitWith {};
        private _items = [
            ["Enfiler", "apply", true],
            [_keep select 0, "keep", _keep select 1],
            [_del select 0, "remove", _del select 1],
            [format [("Afficher la collection « %1 »") call comspec_overwatch_connect_fnc_tr, _r select ([2, 3] select _isCloud)], "menuColl", true]
        ];
        if (!_isCloud) then { _items pushBack ["Cocher pour supprimer en groupe", "menuPick", true]; };
        (_g getVariable ["COMSPEC_ArsenalMenu", []]) params ["_scrim", "_menu", "_mRows", "_mRowH", "_menuW", "_mBg"];
        {
            private _it = _items param [_forEachIndex, []];
            _x ctrlShow (_it isNotEqualTo []);
            if (_it isEqualTo []) then { continue };
            _it params ["_t", "_a", "_on"];
            // Libellés en capitales sur les boutons de la fiche : en phrase dans le menu.
            private _tt = (_t) call comspec_overwatch_connect_fnc_tr;
            if (_tt isEqualTo toUpper _tt) then { _tt = (toUpper (_tt select [0, 1])) + (toLower (_tt select [1])); };
            _x ctrlSetText ((("  " + _tt)) call comspec_overwatch_connect_fnc_tr);
            _x ctrlEnable _on;
            _x setVariable ["act", _a];
        } forEach _mRows;
        private _mh = (count _items) * _mRowH + 4 * pixelH;
        _mBg ctrlSetPosition [0, 0, _menuW, _mh];
        _mBg ctrlCommit 0;
        getMousePosition params ["_mx", "_my"];
        (ctrlPosition _g) params ["_gx", "_gy", "_gw", "_gh"];
        _menu ctrlSetPosition [(((_mx - _gx) min (_gw - _menuW - 0.004)) max 0), (((_my - _gy) min (_gh - _mh - 0.004)) max 0), _menuW, _mh];
        _menu ctrlCommit 0;
        _scrim ctrlShow true;
        _menu ctrlShow true;
        ctrlSetFocus (_mRows select 0);
    };
    case "menuClose": {
        (_g getVariable ["COMSPEC_ArsenalMenu", []]) params [["_scrim", controlNull], ["_menu", controlNull]];
        if (!isNull _menu && {ctrlShown _menu}) then { _scrim ctrlShow false; _menu ctrlShow false; };
    };
    case "menuDo": {
        ["menuClose"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        private _r = call _row;
        if (_r isEqualTo []) exitWith {};
        switch (_arg) do {
            case "menuColl": { ["chip", _r select ([2, 3] select _isCloud)] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
            case "menuPick": {
                if (_isCloud) exitWith {};
                _g setVariable ["COMSPEC_ArsenalPickMode", true];
                _g setVariable ["COMSPEC_ArsenalMarked", [toLower (_r select 0)]];
                [] call _fill;
            };
            default { if (_arg in ["apply", "keep", "remove"]) then { [_arg] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }; };
        };
    };
    case "apply": {
        // Sélection multiple : un double-clic coche puis décoche, il n'enfile pas la tenue.
        if (!_isCloud && {_g getVariable ["COMSPEC_ArsenalPickMode", false]} && {_arg isEqualType 0} && {_arg >= 0}) exitWith {};
        if (_arg isEqualType 0 && {_arg >= 0}) then { _g setVariable ["COMSPEC_ArsenalSelected", _arg]; };
        private _r = call _row;
        if (_r isEqualTo []) exitWith { ["Choisissez d'abord une tenue dans la liste."] call _say; };
        // Après enfiler : on ferme le panneau pour retrouver l'arsenal (retour de jeu).
        // Tenue posée comme le bouton Charger d'ACE (arsenalApplyLoadout : CBA_fnc_setLoadout + rafraîchissement ACE).
        if (_isCloud) then {
            ["Chargement de la tenue…", "busy"] call _hint;
            [_r select 0, _r select 9, _g] spawn {
                params ["_id", "_sr", "_g"];
                if ([_id, _sr] call comspec_overwatch_connect_fnc_arsenalApplyCloud) then {
                    ["close"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
                } else {
                    [_g, "La tenue n'a pas pu être enfilée : voir le message de l'arsenal.", "err"] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
                };
            };
        } else {
            if ([_r select 3, _r select 0] call comspec_overwatch_connect_fnc_arsenalApplyLoadout) then {
                ["close"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            } else {
                ["La tenue n'a pas pu être enfilée : voir le message de l'arsenal.", "err"] call _hint;
            };
        };
    };
    case "keep": {
        private _r = call _row;
        if (_r isEqualTo []) exitWith { ["Choisissez d'abord une tenue dans la liste."] call _say; };
        // Mes tenues modifiée sur le site : on prend la version du site (votre tenue sur le site, même nom).
        private _siteId = "";
        if (!_isCloud && {(_r select 4) isEqualTo "remote"}) then {
            private _k = ["key", _r select 0] call comspec_overwatch_connect_fnc_arsenalAceStore;
            private _hasMine = _g getVariable ["COMSPEC_ArsenalHasHash", false];
            private _i = (_g getVariable ["COMSPEC_ArsenalCloudRows", []]) findIf { ((["key", _x select 1] call comspec_overwatch_connect_fnc_arsenalAceStore) isEqualTo _k) && {!_hasMine || {_x select 8}} };
            if (_i >= 0) then { _siteId = ((_g getVariable ["COMSPEC_ArsenalCloudRows", []]) select _i) select 0; };
        };
        private _pull = _isCloud || {_siteId isNotEqualTo ""};
        [["Envoi au site…", "Import dans votre arsenal…"] select _pull, "busy"] call _hint;
        [_r, _isCloud, _siteId, _g, _pull] spawn {
            params ["_r", "_isCloud", "_siteId", "_g", "_pull"];
            private _pushed = false;
            private _ok = false;
            // Import d'une seule tenue, à la demande : celle du même nom est remplacée (le bouton le dit).
            switch (true) do {
                case (_isCloud): { _ok = ["", [_r select 0]] call comspec_overwatch_connect_fnc_arsenalPullAll; };
                case (_siteId isNotEqualTo ""): { _ok = ["", [_siteId]] call comspec_overwatch_connect_fnc_arsenalPullAll; };
                default { _ok = [[_r select 0]] call comspec_overwatch_connect_fnc_arsenalPushAll; _pushed = true; };
            };
            [["redrawNow", "reloadNow"] select _pushed] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            private _label = _r select ([0, 1] select _isCloud);
            [_g, format [(switch (true) do {
                case (_pull && {_ok isEqualTo true}): { "« %1 » est dans votre arsenal." };
                case (_pull): { "Import de « %1 » impossible : vérifiez la liaison Athena, puis réessayez." };
                case (_ok isEqualTo true): { "« %1 » envoyée au site." };
                default { "Envoi de « %1 » impossible : vérifiez la liaison Athena, puis réessayez." };
            }) call comspec_overwatch_connect_fnc_tr, _label], ["err", "ok"] select (_ok isEqualTo true)] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
        };
    };
    case "remove": {
        private _r = call _row;
        if (_r isEqualTo []) exitWith { ["Choisissez d'abord une tenue dans la liste."] call _say; };
        [_r, _isCloud, _d, _g] spawn {
            params ["_r", "_isCloud", "_d", "_g"];
            private _label = [_r select 1, _r select 2] select _isCloud;
            private _ok = [format [(["Supprimer « %1 » de votre arsenal sur cet ordinateur ?", "Retirer « %1 » des tenues de l'organisation ? Seul son auteur ou un responsable peut le faire."] select _isCloud) call comspec_overwatch_connect_fnc_tr, _label], ("Tenues Athena") call comspec_overwatch_connect_fnc_tr, true, true, _d] call BIS_fnc_guiMessage;
            if (!_ok) exitWith {};
            private _done = if (_isCloud) then { [_r select 0] call comspec_overwatch_connect_fnc_arsenalDeleteCloud } else { [_r select 0] call comspec_overwatch_connect_fnc_arsenalDeleteLocal };
            if (_done) then {
                // Retirée du site par vous : plus « retirée du site » à signaler côté Mes tenues.
                if (_isCloud) then { ["del", _r select 1] call comspec_overwatch_connect_fnc_arsenalLedger; [] call comspec_overwatch_connect_fnc_saveProfile; };
                [format ["« %1 » retirée.", _label], "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;
            };
            [["redrawNow", "reloadNow"] select _isCloud] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            [_g, format [(["« %1 » n'a pas pu être retirée.", "« %1 » retirée."] select _done) call comspec_overwatch_connect_fnc_tr, _label], ["err", "ok"] select _done] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
        };
    };
    case "bulk": {
        // Seulement les tenues à envoyer (pas à jour sur le site) ou à importer (pas dans mon arsenal) : arsenalOverlayFill.
        private _shown = _g getVariable ["COMSPEC_ArsenalBulkRows", []];
        if ((count _shown) isEqualTo 0) exitWith {};
        [_shown, _isCloud, _d, _g] spawn {
            params ["_shown", "_isCloud", "_d", "_g"];
            private _ok = [format [(["Envoyer au site %1 tenue(s) qui n'y sont pas à jour ? Les autres ne sont pas renvoyées.", "Importer dans votre arsenal %1 tenue(s) que vous n'avez pas encore ? Aucune de vos tenues n'est remplacée ni doublée."] select _isCloud) call comspec_overwatch_connect_fnc_tr, count _shown], ("Tenues Athena") call comspec_overwatch_connect_fnc_tr, true, true, _d] call BIS_fnc_guiMessage;
            if (!_ok) exitWith {};
            private _setHint = _g getVariable ["COMSPEC_ArsenalSetHint", {}];
            [_g, ["Envoi au site en cours…", "Import en cours, patientez…"] select _isCloud, "busy"] call _setHint;
            [(["Envoi au site en cours…", "Import en cours, patientez…"] select _isCloud), "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
            private _done = if (_isCloud) then { ["", _shown apply { _x select 0 }, true] call comspec_overwatch_connect_fnc_arsenalPullAll } else { [_shown apply { _x select 0 }] call comspec_overwatch_connect_fnc_arsenalPushAll };
            [["reloadNow", "redrawNow"] select _isCloud] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            [_g, [["Envoi groupé terminé.", "Import groupé terminé."] select _isCloud, "Rien n'a pu être transmis : vérifiez la liaison Athena, puis réessayez."] select !(_done isEqualTo true), ["err", "ok"] select (_done isEqualTo true)] call _setHint;
        };
    };
    case "syncAll": {
        [_d, _g] spawn {
            params ["_d", "_g"];
            private _ok = [("Tout basculer dans l'arsenal ACE ? Toutes les tenues de l'organisation arrivent dans « Mes équipements » d'ACE et vos tenues pas encore sur le site y sont envoyées. Rien n'est supprimé ni remplacé, ici comme sur le site.") call comspec_overwatch_connect_fnc_tr, ("Tenues Athena") call comspec_overwatch_connect_fnc_tr, true, true, _d] call BIS_fnc_guiMessage;
            if (!_ok) exitWith {};
            private _setHint = _g getVariable ["COMSPEC_ArsenalSetHint", {}];
            [_g, "Import et envoi des tenues en cours, patientez…", "busy"] call _setHint;
            ["Import et envoi des tenues en cours, patientez…", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
            private _done = [] call comspec_overwatch_connect_fnc_arsenalSyncAll;
            ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            [_g, ["Rien à basculer : votre arsenal ACE et le site sont déjà à jour.", "Tout est basculé dans l'arsenal ACE."] select (_done isEqualTo true), ["info", "ok"] select (_done isEqualTo true)] call _setHint;
        };
    };
    case "pick": {
        if (_isCloud) exitWith {};
        private _mode = !(_g getVariable ["COMSPEC_ArsenalPickMode", false]);
        _g setVariable ["COMSPEC_ArsenalPickMode", _mode];
        if (!_mode) then { _g setVariable ["COMSPEC_ArsenalMarked", []]; };
        ["page"] call _fill;
    };
    case "markAll": {
        if (_isCloud) exitWith {};
        private _shown = (_g getVariable ["COMSPEC_ArsenalShown", []]) apply { toLower (_x select 0) };
        private _marked = _g getVariable ["COMSPEC_ArsenalMarked", []];
        _marked = if ((_shown findIf { !(_x in _marked) }) < 0) then { _marked - _shown } else { _marked + (_shown - _marked) };
        _g setVariable ["COMSPEC_ArsenalMarked", _marked];
        [_g] call (_g getVariable ["COMSPEC_ArsenalPaintCards", {}]);
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
            private _gone = ["remove", _marked] call comspec_overwatch_connect_fnc_arsenalAceStore;
            profileNamespace setVariable ["COMSPEC_ArsenalTrash", _gone];
            [] call comspec_overwatch_connect_fnc_saveProfile;
            private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
            if (!isNull _g) then { _g setVariable ["COMSPEC_ArsenalMarked", []]; };
            [format [("%1 tenue(s) supprimée(s) de votre arsenal. ANNULER les remet.") call comspec_overwatch_connect_fnc_tr, count _gone], "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;
            ["redrawNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            if (!isNull _g) then { [_g, format [("%1 tenue(s) supprimée(s) de votre arsenal. ANNULER les remet.") call comspec_overwatch_connect_fnc_tr, count _gone], "ok"] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]); };
        };
    };
    case "undoDelete": {
        private _trash = profileNamespace getVariable ["COMSPEC_ArsenalTrash", []];
        if !(_trash isEqualType [] && {_trash isNotEqualTo []}) exitWith {};
        // Une tenue du même nom enregistrée depuis n'est pas écrasée.
        private _back = (["upsert", _trash select { _x isEqualType [] }, false] call comspec_overwatch_connect_fnc_arsenalAceStore) select 0;
        profileNamespace setVariable ["COMSPEC_ArsenalTrash", []];
        [] call comspec_overwatch_connect_fnc_saveProfile;
        [format [("%1 tenue(s) remise(s) dans votre arsenal.") call comspec_overwatch_connect_fnc_tr, _back], "ok"] call _say;
        [_g, _back] spawn {
            params ["_g", "_back"];
            ["redrawNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            [_g, format [("%1 tenue(s) remise(s) dans votre arsenal.") call comspec_overwatch_connect_fnc_tr, _back], "ok"] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
        };
    };
    case "saveCurrent": {
        private _name = trim ctrlText (_g getVariable ["COMSPEC_ArsenalSaveName", controlNull]);
        if (_name isEqualTo "") exitWith {
            ["Donnez un nom à la tenue (ex. SOAR - Breacher), puis Enregistrer."] call _say;
            ["Donnez un nom à la tenue (ex. SOAR - Breacher), puis Enregistrer.", "warn"] call _hint;
        };
        private _unit = missionNamespace getVariable ["ace_arsenal_center", player];
        if (isNull _unit) then { _unit = player; };
        // Comme le bouton Enregistrer d'ACE : tenue étendue CBA (visage, voix, insigne), même nom = remplacée.
        private _lo = if (isNil "CBA_fnc_getLoadout") then { [getUnitLoadout _unit, createHashMap] } else { _unit call CBA_fnc_getLoadout };
        if (((["upsert", [[_name, _lo]], true] call comspec_overwatch_connect_fnc_arsenalAceStore) select 0) < 1) exitWith {
            ["La tenue portée n'a pas pu être enregistrée."] call _say;
            ["La tenue portée n'a pas pu être enregistrée.", "err"] call _hint;
        };
        profileNamespace setVariable ["COMSPEC_ArsenalLastSaveName", _name];
        [] call comspec_overwatch_connect_fnc_saveProfile;
        [format ["« %1 » enregistrée dans votre arsenal, partage en cours…", _name], "ok"] call _say;
        [format [("« %1 » enregistrée dans votre arsenal, partage en cours…") call comspec_overwatch_connect_fnc_tr, _name], "busy"] call _hint;
        [_name, _g] spawn {
            params ["_n", "_g"];
            private _ok = [[_n]] call comspec_overwatch_connect_fnc_arsenalPushAll;
            ["reloadNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            [_g, format [(["« %1 » est enregistrée ici mais n'est pas partie au site : Envoyer la renverra.", "« %1 » enregistrée et envoyée au site."] select (_ok isEqualTo true)) call comspec_overwatch_connect_fnc_tr, _n], ["warn", "ok"] select (_ok isEqualTo true)] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
        };
    };
};
