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
      "menu", i / "moreMenu" / "menuDo", act / "menuClose"   menu clic droit d'une carte (ou « … » de la fiche) :
                                          Enfiler (aussi en gardant mon sac ou mes armes : "applyKeep", pack / arms),
                                          Importer / Envoyer / Mettre à jour, Supprimer, Renommer et Déplacer vers une
                                          collection ("ask" / "askOk"), Dupliquer ("dup"), Comparer avec la tenue
                                          portée ("compare"), Vérifier les objets manquants ("missing"), Copier la
                                          tenue ("copy"), afficher sa collection, la cocher
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
      "nav", sens / "navFocus"            clavier sur les cartes : left / right / up / down / pgUp / pgDn / home / end
                                          (la page suit), focus sur la carte choisie (flèche bas dans la recherche)
      "navEnter" / "navDelete" / "navCopy"   Entrée (enfiler ; sélection multiple : cocher), Suppr (supprimer, avec
                                          confirmation), Ctrl+C (copier la tenue)
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
    private _gBack = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
    if (!isNull _gBack && {_d getVariable ["COMSPEC_ArsenalOverlayOpen", false]}) then { [_gBack] call (_gBack getVariable ["COMSPEC_ArsenalSettle", {}]); };
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
// Tenue choisie complète ([tenue, infos étendues]), puis _code [tenue étendue, ligne, panneau, extra, communauté ?],
// dans un spawn : celle du site vient du cache de COMSPEC Link ou du site (arsenalCloudLoadout), comme pour Enfiler.
private _withData = {
    params ["_code", ["_extra", ""]];
    private _r = call _row;
    if (_r isEqualTo []) exitWith { ["Choisissez d'abord une tenue dans la liste."] call _say; };
    [_r, _isCloud, _code, _extra, _g] spawn {
        params ["_r", "_isCloud", "_code", "_extra", "_g"];
        private _data = if (_isCloud) then { [_r select 0, _r select 9] call comspec_overwatch_connect_fnc_arsenalCloudLoadout } else {
            private _d = _r select 3;
            if (([_d] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout) isEqualTo [] && {(_r param [13, ""]) isNotEqualTo ""}) then { [_r select 13] call comspec_overwatch_connect_fnc_arsenalCloudLoadout } else { _d }
        };
        private _ext = ["extended", _data] call comspec_overwatch_connect_fnc_arsenalAceStore;
        if (_ext isEqualTo []) exitWith { [_g, "Cette tenue est illisible ou introuvable.", "err"] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]); };
        [_ext, _r, _g, _extra, _isCloud] call _code;
    };
};
// Mes tenues : renommer (ou changer de collection = changer le préfixe « Collection - »). Par arsenalAceStore, seul
// écrivain des tenues ACE : la nouvelle entrée est écrite avant que l'ancienne soit retirée ; un nom déjà pris est refusé.
private _renameTo = {
    params ["_r", "_new"];
    private _old = _r select 0;
    _new = trim _new;
    if (_new isEqualTo "") exitWith { ["Donnez un nom à la tenue.", "warn"] call _hint; };
    if (_new isEqualTo _old) exitWith {};
    private _kn = ["key", _new] call comspec_overwatch_connect_fnc_arsenalAceStore;
    private _same = _kn isEqualTo (["key", _old] call comspec_overwatch_connect_fnc_arsenalAceStore);
    if (!_same && {((_g getVariable ["COMSPEC_ArsenalLocalRows", []]) findIf { (["key", _x select 0] call comspec_overwatch_connect_fnc_arsenalAceStore) isEqualTo _kn }) >= 0}) exitWith {
        [format [("Une tenue s'appelle déjà « %1 » : choisissez un autre nom.") call comspec_overwatch_connect_fnc_tr, _new], "warn"] call _hint;
    };
    if (((["upsert", [[_new, _r select 3]], _same] call comspec_overwatch_connect_fnc_arsenalAceStore) select 0) < 1) exitWith {
        [format [("« %1 » n'a pas pu être renommée.") call comspec_overwatch_connect_fnc_tr, _old], "err"] call _hint;
    };
    if (!_same) then { ["remove", [_old]] call comspec_overwatch_connect_fnc_arsenalAceStore; };
    _g setVariable ["COMSPEC_ArsenalSelKey", ["local", toLower _new]];
    [_g, _old, _new] spawn {
        params ["_g", "_o", "_n"];
        ["redrawNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        [_g, format [("« %1 » devient « %2 ».") call comspec_overwatch_connect_fnc_tr, _o, _n], "ok"] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
    };
};

switch (_act) do {
    case "toggle": {
        ["menuClose"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        private _open = !(_d getVariable ["COMSPEC_ArsenalOverlayOpen", false]);
        _d setVariable ["COMSPEC_ArsenalOverlayOpen", _open];
        _g ctrlShow _open;
        if (_open) then { [_g] call (_g getVariable ["COMSPEC_ArsenalSettle", {}]); };
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
            // Une seule boucle par panneau : rouvert en moins de 0,25 s, l'ancienne boucle se retire (avant : deux en parallèle).
            _d setVariable ["COMSPEC_ArsenalHidePfh", [{
                params ["_d", "_pfh"];
                if (isNull _d || {!(_d getVariable ["COMSPEC_ArsenalOverlayOpen", false])} || {(_d getVariable ["COMSPEC_ArsenalHidePfh", -1]) isNotEqualTo _pfh}) exitWith { [_pfh] call CBA_fnc_removePerFrameHandler; };
                { if (!isNull _x && {ctrlShown _x}) then { _x ctrlShow false; }; } forEach (_d getVariable ["COMSPEC_ArsenalHidden", []]);
            }, 0.25, _d] call CBA_fnc_addPerFrameHandler];
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
    // Clavier sur les cartes (arsenalOverlayShow) : la tenue choisie se déplace, la page suit, la carte garde le
    // focus. Sélection multiple : les flèches déplacent seulement le cadre, Entrée coche.
    case "navFocus";
    case "nav": {
        private _shown = _g getVariable ["COMSPEC_ArsenalShown", []];
        private _n = count _shown;
        if (_n isEqualTo 0) exitWith {};
        private _cards = _g getVariable ["COMSPEC_ArsenalCards", []];
        private _per = (count _cards) max 1;
        private _cols = (_g getVariable ["COMSPEC_ArsenalCols", 1]) max 1;
        private _page = _g getVariable ["COMSPEC_ArsenalPage", 0];
        private _cur = _g getVariable ["COMSPEC_ArsenalSelected", -1];
        private _new = switch (true) do {
            case (_cur < 0 || {_act isEqualTo "navFocus"}): { [_cur, _page * _per] select (_cur < 0 || {(floor (_cur / _per)) isNotEqualTo _page}) };
            case (_arg isEqualTo "left"): { _cur - 1 };
            case (_arg isEqualTo "right"): { _cur + 1 };
            case (_arg isEqualTo "up"): { _cur - _cols };
            case (_arg isEqualTo "down"): { _cur + _cols };
            case (_arg isEqualTo "pgUp"): { _cur - _per };
            case (_arg isEqualTo "pgDn"): { _cur + _per };
            case (_arg isEqualTo "home"): { 0 };
            case (_arg isEqualTo "end"): { _n - 1 };
            default { _cur };
        };
        _new = (_new max 0) min (_n - 1);
        private _np = floor (_new / _per);
        if (_np isNotEqualTo _page) then { _g setVariable ["COMSPEC_ArsenalPage", _np]; ["page"] call _fill; };
        if (!_isCloud && {_g getVariable ["COMSPEC_ArsenalPickMode", false]}) then {
            _g setVariable ["COMSPEC_ArsenalSelected", _new];
            [_g] call (_g getVariable ["COMSPEC_ArsenalPaintCards", {}]);
        } else {
            if (_new isNotEqualTo _cur || {(_g getVariable ["COMSPEC_ArsenalSelKey", []]) isEqualTo []}) then { ["select", _new] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
        };
        private _i = _cards findIf { ((_x select 0) getVariable ["idx", -1]) isEqualTo _new };
        if (_i >= 0) then { ctrlSetFocus ((_cards select _i) select 0); };
    };
    case "navEnter": {
        private _i = _g getVariable ["COMSPEC_ArsenalSelected", -1];
        if (_i < 0) exitWith {};
        ([["apply", _i], ["select", _i]] select (!_isCloud && {_g getVariable ["COMSPEC_ArsenalPickMode", false]})) call comspec_overwatch_connect_fnc_arsenalOverlayAction;
    };
    case "navDelete": {
        if ((_g getVariable ["COMSPEC_ArsenalSelected", -1]) < 0 || {!_isCloud && {_g getVariable ["COMSPEC_ArsenalPickMode", false]}}) exitWith {};
        (_g getVariable ["COMSPEC_ArsenalActState", []]) params [["_has", false], "", ["_del", ["", false]]];
        if (_has && {_del select 1}) then { ["remove"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; } else {
            ["Impossible de retirer cette tenue ici : seul son auteur ou un responsable peut le faire, avec Athena joignable.", "warn"] call _hint;
        };
    };
    case "navCopy": { if ((_g getVariable ["COMSPEC_ArsenalSelected", -1]) >= 0) then { ["copy"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }; };
    // Menu clic droit d'une carte (ou « … » de la fiche), à la souris, gardé dans le panneau ; le focus va à sa
    // première ligne active (sinon la carte cliquée, qui garde le focus, se dessinait par-dessus). [] = séparateur.
    case "moreMenu": { ["menu", _g getVariable ["COMSPEC_ArsenalSelected", -1]] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    case "menu": {
        if (!(_arg isEqualType 0) || {_arg < 0}) exitWith {};
        if (!_isCloud && {_g getVariable ["COMSPEC_ArsenalPickMode", false]}) exitWith { ["select", _arg] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
        if ((_g getVariable ["COMSPEC_ArsenalSelected", -1]) isNotEqualTo _arg) then { ["select", _arg] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
        private _r = call _row;
        (_g getVariable ["COMSPEC_ArsenalActState", []]) params [["_has", false], ["_keep", ["", false]], ["_del", ["", false]]];
        if (_r isEqualTo [] || {!_has}) exitWith {};
        private _items = [
            ["Enfiler", "apply", true],
            ["Enfiler en gardant mon sac", "applyPack", true],
            ["Enfiler en gardant mes armes", "applyArms", true],
            [],
            [_keep select 0, "keep", _keep select 1],
            [_del select 0, "remove", _del select 1],
            []
        ];
        if (!_isCloud) then { _items append [["Renommer…", "rename", true], ["Dupliquer", "dup", true], ["Déplacer vers une collection…", "move", true], []]; };
        _items append [
            [["Comparer avec la tenue portée", "Arrêter la comparaison"] select (_g getVariable ["COMSPEC_ArsenalCompare", false]), "compare", true],
            ["Vérifier les objets manquants", "missing", true],
            ["Copier la tenue (format ACE)", "copy", true],
            [],
            [format [("Afficher la collection « %1 »") call comspec_overwatch_connect_fnc_tr, _r select ([2, 3] select _isCloud)], "menuColl", true]
        ];
        if (!_isCloud) then { _items pushBack ["Cocher pour supprimer en groupe", "menuPick", true]; };
        (_g getVariable ["COMSPEC_ArsenalMenu", []]) params ["_scrim", "_menu", "_mRows", "_mRowH", "_menuW", "_mBg", ["_mSeps", []]];
        { _x ctrlShow false; } forEach (_mRows + _mSeps);
        private _y = 3 * pixelH;
        private _ri = 0;
        private _si = 0;
        private _first = controlNull;
        {
            if (_x isEqualTo []) then {
                private _l = _mSeps param [_si, controlNull];
                if (!isNull _l) then {
                    (ctrlPosition _l) params ["_lx", "", "_lw"];
                    _l ctrlSetPosition [_lx, _y + _mRowH * 0.14, _lw, pixelH];
                    _l ctrlCommit 0;
                    _l ctrlShow true;
                };
                _si = _si + 1;
                _y = _y + _mRowH * 0.3;
                continue;
            };
            private _b = _mRows param [_ri, controlNull];
            if (isNull _b) then { continue };
            _x params ["_t", "_a", "_on"];
            // Libellés en capitales sur les boutons de la fiche : en phrase dans le menu.
            private _tt = (_t) call comspec_overwatch_connect_fnc_tr;
            if (_tt isEqualTo toUpper _tt) then { _tt = (toUpper (_tt select [0, 1])) + (toLower (_tt select [1])); };
            _b ctrlSetText ((("  " + _tt)) call comspec_overwatch_connect_fnc_tr);
            _b ctrlEnable _on;
            _b setVariable ["act", _a];
            (ctrlPosition _b) params ["_bx", "", "_bw", "_bh"];
            _b ctrlSetPosition [_bx, _y, _bw, _bh];
            _b ctrlCommit 0;
            _b ctrlShow true;
            if (isNull _first && {_on}) then { _first = _b; };
            _ri = _ri + 1;
            _y = _y + _mRowH;
        } forEach _items;
        private _mh = _y + 2 * pixelH;
        _mBg ctrlSetPosition [0, 0, _menuW, _mh];
        _mBg ctrlCommit 0;
        getMousePosition params ["_mx", "_my"];
        (ctrlPosition _g) params ["_gx", "_gy", "_gw", "_gh"];
        _menu ctrlSetPosition [(((_mx - _gx) min (_gw - _menuW - 0.004)) max 0), (((_my - _gy) min (_gh - _mh - 0.004)) max 0), _menuW, _mh];
        _menu ctrlCommit 0;
        _scrim ctrlSetPosition (_g getVariable ["COMSPEC_ArsenalScrimRect", [0, 0, 0, 0]]);
        _scrim ctrlCommit 0;
        _scrim ctrlShow true;
        _menu ctrlShow true;
        if (!isNull _first) then { ctrlSetFocus _first; };
    };
    case "menuClose": {
        (_g getVariable ["COMSPEC_ArsenalMenu", []]) params [["_scrim", controlNull], ["_menu", controlNull]];
        (_g getVariable ["COMSPEC_ArsenalAsk", []]) params [["_ask", controlNull]];
        private _askOn = !isNull _ask && {ctrlShown _ask};
        // Toujours, et sans surface : ctrlShown ne dit rien de fiable juste après l'affichage du groupe parent.
        { if (!isNull _x) then { _x ctrlShow false; _x ctrlSetPosition [0, 0, 0, 0]; _x ctrlCommit 0; }; } forEach [_scrim, _menu, _ask];
        // Le champ de saisie masqué ne garde pas les raccourcis d'ACE.
        if (_askOn) then { ace_arsenal_leftSearchbarFocus = false; };
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
            case "applyPack";
            case "applyArms": { ["applyKeep", ["pack", "arms"] select (_arg isEqualTo "applyArms")] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
            case "rename": { ["ask", ["rename", "Nouveau nom de la tenue (le préfixe avant « - » sert de collection)", _r select 0]] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
            case "move": {
                ["ask", ["move", format [("Collection de « %1 » : existante ou nouvelle (vide : sans collection)") call comspec_overwatch_connect_fnc_tr, _r select 1], ["", _r select 2] select ((_r select 2) isNotEqualTo "Autres")]] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            };
            default { if (_arg in ["apply", "keep", "remove", "dup", "compare", "missing", "copy"]) then { [_arg] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }; };
        };
    };
    // Petite fenêtre de saisie (Renommer, Déplacer vers une collection) : [genre, titre, texte de départ].
    case "ask": {
        _arg params [["_kind", ""], ["_title", ""], ["_prefill", ""]];
        (_g getVariable ["COMSPEC_ArsenalAsk", []]) params [["_ask", controlNull], ["_t", controlNull], ["_e", controlNull]];
        (_g getVariable ["COMSPEC_ArsenalMenu", []]) params [["_scrim", controlNull]];
        if (isNull _ask) exitWith {};
        _g setVariable ["COMSPEC_ArsenalAskKind", _kind];
        _t ctrlSetStructuredText parseText (((format ["<t size='%2' color='#dce8e0'>%1</t>", (_title) call comspec_overwatch_connect_fnc_tr, 0.82 max (_g getVariable ["COMSPEC_ArsenalStMin", 0])])) call comspec_overwatch_connect_fnc_tr);
        _e ctrlSetText _prefill; // i18n:brut
        _scrim ctrlSetPosition (_g getVariable ["COMSPEC_ArsenalScrimRect", [0, 0, 0, 0]]);
        _scrim ctrlCommit 0;
        _ask ctrlSetPosition (_g getVariable ["COMSPEC_ArsenalAskRect", [0, 0, 0, 0]]);
        _ask ctrlCommit 0;
        _scrim ctrlShow true;
        _ask ctrlShow true;
        ctrlSetFocus _e;
    };
    case "askOk": {
        (_g getVariable ["COMSPEC_ArsenalAsk", []]) params [["_ask", controlNull], "", ["_e", controlNull]];
        if (isNull _ask || {!ctrlShown _ask}) exitWith {};
        private _txt = trim ctrlText _e;
        ["menuClose"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
        private _r = call _row;
        if (_r isEqualTo [] || {_isCloud}) exitWith {};
        private _new = switch (true) do {
            case ((_g getVariable ["COMSPEC_ArsenalAskKind", ""]) isNotEqualTo "move"): { _txt };
            case (_txt isEqualTo ""): { _r select 1 };
            default { format ["%1 - %2", _txt, _r select 1] };
        };
        [_r, _new] call _renameTo;
    };
    // Mes tenues : copie sous « nom (copie) », « nom (copie 2) »… ; rien d'autre ne bouge.
    case "dup": {
        private _r = call _row;
        if (_r isEqualTo [] || {_isCloud}) exitWith {};
        private _keys = (_g getVariable ["COMSPEC_ArsenalLocalRows", []]) apply { ["key", _x select 0] call comspec_overwatch_connect_fnc_arsenalAceStore };
        private _copy = ("copie") call comspec_overwatch_connect_fnc_tr;
        private _n = format ["%1 (%2)", _r select 0, _copy];
        private _i = 2;
        while { (["key", _n] call comspec_overwatch_connect_fnc_arsenalAceStore) in _keys } do { _n = format ["%1 (%2 %3)", _r select 0, _copy, _i]; _i = _i + 1; };
        if (((["upsert", [[_n, _r select 3]], false] call comspec_overwatch_connect_fnc_arsenalAceStore) select 0) < 1) exitWith {
            [format [("« %1 » n'a pas pu être dupliquée.") call comspec_overwatch_connect_fnc_tr, _r select 0], "err"] call _hint;
        };
        _g setVariable ["COMSPEC_ArsenalSelKey", ["local", toLower _n]];
        [_g, _r select 0, _n] spawn {
            params ["_g", "_o", "_n"];
            ["redrawNow"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            [_g, format [("« %1 » dupliquée en « %2 ».") call comspec_overwatch_connect_fnc_tr, _o, _n], "ok"] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
        };
    };
    // Fiche : pièces qui diffèrent de la tenue portée par le mannequin, en orange (arsenalOverlayPreview).
    case "compare": {
        private _on = !(_g getVariable ["COMSPEC_ArsenalCompare", false]);
        _g setVariable ["COMSPEC_ArsenalCompare", _on];
        [["Comparaison arrêtée.", "Comparaison avec la tenue portée : les pièces différentes sont en orange dans la fiche."] select _on, "info"] call _hint;
        private _i = _g getVariable ["COMSPEC_ArsenalSelected", -1];
        if (_i >= 0) then { ["select", _i] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
    };
    case "missing": {
        [{
            params ["_ext", "_r", "_g", "", "_isCloud"];
            private _miss = [_ext] call comspec_overwatch_connect_fnc_arsenalMissingItems;
            private _label = _r select ([0, 1] select _isCloud);
            [_g, if (_miss isEqualTo []) then {
                format [("Tous les objets de « %1 » existent dans vos mods.") call comspec_overwatch_connect_fnc_tr, _label]
            } else {
                format [("%1 objet(s) de « %2 » absents de vos mods : %3") call comspec_overwatch_connect_fnc_tr, count _miss, _label,
                    ((_miss select [0, 4]) joinString ", ") + (["", "…"] select ((count _miss) > 4))]
            }, ["warn", "ok"] select (_miss isEqualTo [])] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
        }] call _withData;
    };
    // Presse-papiers : tableau getUnitLoadout, relu par Importer d'ACE ou setUnitLoadout.
    case "copy": {
        [{
            params ["_ext", "_r", "_g", "", "_isCloud"];
            copyToClipboard (str (_ext select 0));
            [_g, format [("« %1 » copiée dans le presse-papiers (format ACE, à coller dans Importer d'ACE).") call comspec_overwatch_connect_fnc_tr, _r select ([0, 1] select _isCloud)], "ok"] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
        }] call _withData;
    };
    // Enfiler en gardant le sac porté (« pack ») ou les trois armes portées (« arms ») : le reste vient de la tenue.
    case "applyKeep": {
        ["Chargement de la tenue…", "busy"] call _hint;
        [{
            params ["_ext", "_r", "_g", "_what", "_isCloud"];
            private _unit = missionNamespace getVariable ["ace_arsenal_center", player];
            if (isNull _unit) then { _unit = player; };
            private _cur = getUnitLoadout _unit;
            private _lo = +(_ext select 0);
            { _lo set [_x, +(_cur select _x)]; } forEach ([[0, 1, 2], [5]] select (_what isEqualTo "pack"));
            if ([[_lo, _ext select 1], _r select ([0, 1] select _isCloud)] call comspec_overwatch_connect_fnc_arsenalApplyLoadout) then {
                ["close"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
            } else {
                [_g, "La tenue n'a pas pu être enfilée : voir le message de l'arsenal.", "err"] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
            };
        }, _arg] call _withData;
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
