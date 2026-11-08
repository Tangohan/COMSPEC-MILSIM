/*
    Fiche joueur unique : les mêmes actions, dans le même ordre et avec les mêmes libellés partout (carte, panneau
    SITUATION, BFT, Groupe, Inter-team). Une cible est une entité BFT (HashMap), son identifiant, ou un objet joueur.
    Ordre fixe : CARTE · Y ALLER · SMS · VIBRER · PING · CAMÉRA · SUIVRE · PASSER LE COMMANDEMENT · CHAMP DE VISION ·
    LOCALISER · FICHE BFT. Sur la carte, où centrer sert moins, SUIVRE prend la première place et CARTE passe derrière « … ».
    Règles communes : grisé sans téléphone, « HORS LIGNE » à la place de VIBRER pour une piste hors ligne, CAMÉRA seulement
    si elle m'est partagée, passage du commandement réservé au chef et en deux temps (fn_groupAction « lead » au second appui).
    Modes :
      ["resolve", cible]              -> entité (HashMap vide si inconnue) ; un objet sans piste donne une entité de passage
      ["state", cible ou [cible, carte]] -> [code, libellé, couleur hex, couleur rgba]. Code : 0 apte, 1 blessé, 2 inconscient,
                                         3 mort, -1 état médical masqué (fn_medShow), -2 hors ligne. carte : encres de la carte.
      ["catalog", [cible, contexte]]  -> [[clé, libellé, actif, aide, style ("" | "main" | "red")]...]
                                         contexte : "bft" | "map" | "panel" | "group"
      ["do", [clé, identifiant]]      -> exécute l'action (par fn_bftAction, fn_groupAction, fn_reliefAction, fn_relayNet)
      ["rows", [cible, contexte]]     -> lignes « buttons » de fn_formRender, par rangées de 3 (portrait mini) ou 5, hauteur tap
      ["bar", [cible, contexte, x, y, largeur, dans la zone de contenu]] -> rangée de boutons en contrôles libres, le surplus
                                         derrière « … » (montré ou caché sans redessin). Renvoie un HashMap : h, keys, btns, more.
      ["barUpdate", [barre, cible, contexte]] -> réécrit la rangée sur place ; false si l'ensemble des actions a changé.
      ["select", cible]               -> pose la sélection de la carte (crochets et fiche)
      ["refresh"]                     -> redessine la fiche ouverte après un changement d'état (commandement armé, suivi)
      ["mapCard"]                     -> (re)crée la fiche flottante de la carte pour la sélection ; renvoie la hauteur prise
      ["tick"]                        -> réécrit la fiche flottante (fn_statusUpdate, une fois par seconde), si elle change
      ["step", 1 ou -1]               -> équipier suivant / précédent, sans viser, sans redessiner la carte
    Aucun coût par image : contrôles créés au rendu, réécrits au plus une fois par seconde.
*/
params [["_mode", "catalog"], ["_arg", []]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _units = (uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap];
private _ofObject = {
    params ["_o"];
    private _hit = (values _units) findIf { (_x getOrDefault ["object", objNull]) isEqualTo _o };
    if (_hit >= 0) exitWith { (values _units) select _hit };
    if (isNull _o) exitWith { createHashMap };
    createHashMapFromArray [["id", "o:" + netId _o], ["object", _o], ["callsign", [_o, true] call comspec_atak_native_fnc_unitCallsign], ["position", getPosASL _o],
        ["affiliation", "friend"], ["freshness", "LIVE"], ["updated", diag_tickTime], ["self", _o isEqualTo player]]
};
private _resolve = {
    params ["_t"];
    if (_t isEqualType objNull) exitWith { [_t] call _ofObject };
    if (_t isEqualType "") exitWith {
        if ((_t select [0, 2]) isEqualTo "o:") exitWith { [objectFromNetId (_t select [2])] call _ofObject };
        _units getOrDefault [_t, createHashMap]
    };
    if (_t isEqualType createHashMap) exitWith { _units getOrDefault [_t getOrDefault ["id", ""], _t] };
    createHashMap
};
private _armed = {
    params ["_id"];
    (_s getOrDefault ["unitLeadArm", []]) params [["_aid", ""], ["_at", -1e9]];
    _aid isEqualTo _id && {(diag_tickTime - _at) < 6}
};
private _catalog = {
    params ["_t", ["_ctx", "bft"]];
    private _e = [_t] call _resolve;
    private _has = (count _e) > 0;
    private _id = _e getOrDefault ["id", ""];
    private _o = _e getOrDefault ["object", objNull];
    private _me = _e getOrDefault ["self", false];
    private _friend = (_e getOrDefault ["affiliation", "friend"]) isEqualTo "friend";
    private _off = (_e getOrDefault ["freshness", "LIVE"]) isEqualTo "OFFLINE";
    private _pl = !isNull _o && {isPlayer _o} && {!_me};
    private _dev = _pl && {[_o] call comspec_atak_native_fnc_hasDevice};
    private _al = !isNull _o && {alive _o};
    private _net = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) > 0;
    private _follow = (_s getOrDefault ["mapFollowId", ""]) isEqualTo _id && {_id isNotEqualTo ""};
    private _cam = ["cam", "CAMÉRA", _dev && {_al} && {["can", _o] call comspec_atak_native_fnc_camShare}, "Voir sa caméra (si elle vous est partagée), en petite fenêtre sur la carte", ""];
    private _sui = ["follow", ["SUIVRE", "NE PLUS SUIVRE"] select _follow, _has && {_id in _units} && {!_me}, "La carte reste sur cette unité", ""];
    private _car = ["center", "CARTE", _has, "Centre la carte sur lui et le sélectionne", ""];
    private _all = [
        ["route", "Y ALLER", _has && {!_me}, "Guidage GPS jusqu'à lui", "main"],
        ["sms", "SMS", _dev && {_al}, ["Pas de téléphone ATAK chez cet allié", "Ouvre Messages avec lui"] select _dev, ""],
        [["vibe", "buzz"] select (_ctx isEqualTo "bft"), ["VIBRER", "HORS LIGNE"] select _off, _net && {_dev} && {!_off}, ["Pas de téléphone ATAK chez cet allié", "Fait vibrer son téléphone (une fois toutes les 5 s)"] select _dev, ""],
        ["ping", "PING", _has, "Ping sur sa position, visible de tout le camp", ""]
    ];
    if (!_friend) exitWith { [_car] + (_all select { (_x select 0) in ["route", "ping"] }) };
    if (_me) exitWith { [_car] + (_all select { (_x select 0) isEqualTo "ping" }) + [["viewshed", "CHAMP DE VISION", _al, "Ce qui est visible depuis vous", ""]] };
    private _tail = [];
    private _grp = group player;
    if (_pl && {group _o isEqualTo _grp} && {(leader _grp) isEqualTo player || {(count units _grp) isEqualTo 1}}) then {
        _tail pushBack ["lead", ["PASSER LE COMMANDEMENT", "CONFIRMER"] select ([_id] call _armed), true, "Passer le commandement du groupe : un second appui confirme", "red"];
    };
    _tail pushBack ["viewshed", "CHAMP DE VISION", _al, "Ce qu'il voit depuis sa position", ""];
    _tail pushBack ["locate", "LOCALISER", _dev && {_al}, "Localiser son téléphone par les relais (triangulation)", ""];
    if (_ctx isNotEqualTo "bft") then { _tail pushBack ["bft", "FICHE BFT", _has, "Ouvre l'app BFT sur cette piste", ""]; };
    if (_ctx isEqualTo "map") exitWith { [_sui] + _all + [_cam, _car] + _tail };
    [_car] + _all + [_cam, _sui] + _tail
};
// Revue au pouce : mon groupe puis les alliés à moins d'un kilomètre, chacun du plus proche au plus loin.
private _peerList = {
    private _mine = [];
    private _near = [];
    private _gp = group player;
    {
        if ((_y getOrDefault ["affiliation", ""]) isEqualTo "friend" && {!(_y getOrDefault ["self", false])}) then {
            private _d = player distance2D (_y getOrDefault ["position", [0, 0, 0]]);
            private _ob = _y getOrDefault ["object", objNull];
            if (!isNull _ob && {group _ob isEqualTo _gp}) then { _mine pushBack [_d, _x]; } else { if (_d < 1000) then { _near pushBack [_d, _x]; }; };
        };
    } forEach _units;
    _mine sort true;
    _near sort true;
    (_mine + _near) apply { _units get (_x select 1) }
};
private _perRow = {
    private _l = [] call comspec_atak_native_fnc_layoutGet;
    [5, 3] select ((_l get "mini") && {!(_l get "landscape")})
};
switch (_mode) do {
    case "resolve": { [_arg] call _resolve };
    case "state": {
        private _t = _arg;
        private _forMap = false;
        if (_arg isEqualType []) then { _t = _arg param [0, objNull]; _forMap = _arg param [1, false]; };
        private _obj = _t;
        private _offline = false;
        if !(_t isEqualType objNull) then {
            private _e = [_t] call _resolve;
            _obj = _e getOrDefault ["object", objNull];
            _offline = (_e getOrDefault ["freshness", "LIVE"]) isEqualTo "OFFLINE";
        };
        (switch (true) do {
            case (_offline): { [-2, "Hors ligne", "off"] };
            case (isNull _obj): { [-1, "—", "off"] };
            case (!alive _obj): { [3, "Mort", "alert"] };
            case !(["state", "allies"] call comspec_atak_native_fnc_medShow): { [-1, "—", "off"] };
            case (lifeState _obj isEqualTo "INCAPACITATED" || {_obj getVariable ["ACE_isUnconscious", false]}): { [2, "Inconscient", "alert"] };
            case ((damage _obj) > 0.25): { [1, "Blessé", "warn"] };
            default { [0, "Apte", "ok"] };
        }) params ["_code", "_label", "_tone"];
        private _th = COMSPEC_ATAK_Th;
        private _pair = if (_forMap) then {
            switch (_tone) do { case "ok": { [_th get "hexMapOk", _th get "mapOk"] }; case "warn": { [_th get "hexMapWarn", _th get "mapWarn"] }; case "alert": { [_th get "hexMapAlert", _th get "mapAlert"] }; default { [_th get "hexMapDim", _th get "mapDim"] }; }
        } else {
            switch (_tone) do { case "ok": { [_th get "hexOk", _th get "ok"] }; case "warn": { [_th get "hexWarn", _th get "warn"] }; case "alert": { [_th get "hexAlert", _th get "alert"] }; default { [_th get "hexTextMuted", _th get "textMuted"] }; }
        };
        [_code, _label, _pair select 0, _pair select 1]
    };
    case "catalog": { _arg call _catalog };
    case "select": {
        private _e = [_arg] call _resolve;
        if ((count _e) isEqualTo 0) exitWith { false };
        _s set ["selectedEntity", _e];
        _s set ["selectedMarker", ""];
        true
    };
    case "refresh": {
        private _page = _s getOrDefault ["activePage", ""];
        // Carte sans panneau ouvert : la fiche seule, pas de rendu de la page.
        if (_page isEqualTo "MAP" && {!(missionNamespace getVariable ["COMSPEC_ATAK_InspOpen", false])}) exitWith { [{ ["mapCard"] call comspec_atak_native_fnc_unitCard; }] call comspec_atak_native_fnc_nextFrame; true };
        if (_page in ["BFT", "GROUP", "MAP"]) then { [{ [_this] call comspec_atak_native_fnc_pageRender; }, _page] call comspec_atak_native_fnc_nextFrame; };
        true
    };
    case "do": {
        _arg params [["_key", ""], ["_id", ""]];
        private _e = [_id] call _resolve;
        if ((count _e) isEqualTo 0) exitWith { false };
        private _eid = _e getOrDefault ["id", ""];
        private _o = _e getOrDefault ["object", objNull];
        // fn_bftAction lit la piste choisie : une entité de passage (allié sans piste, calque masqué) lui est donnée à part.
        _s set ["bftSel", _eid];
        _s set ["bftSelEnt", _e];
        switch (_key) do {
            case "center": { ["center"] call comspec_atak_native_fnc_bftAction; };
            case "route": { ["route"] call comspec_atak_native_fnc_bftAction; };
            case "sms": { ["sms"] call comspec_atak_native_fnc_bftAction; };
            case "vibe": { ["vibe"] call comspec_atak_native_fnc_bftAction; };
            case "buzz": { ["buzz"] call comspec_atak_native_fnc_bftAction; };
            case "ping": { ["ping"] call comspec_atak_native_fnc_bftAction; };
            case "cam": { ["cam"] call comspec_atak_native_fnc_bftAction; };
            case "follow": {
                if ((_s getOrDefault ["mapFollowId", ""]) isEqualTo _eid) then {
                    _s set ["mapFollowId", ""];
                } else {
                    private _pos = _e getOrDefault ["position", [0, 0, 0]];
                    _s set ["mapFollow", false];
                    _s set ["mapFollowId", _eid];
                    if ((_s getOrDefault ["activePage", ""]) isNotEqualTo "MAP") then { ["MAP"] call comspec_atak_native_fnc_navigate; };
                    _s set ["selectedEntity", _e];
                    [_pos, -1, true] call comspec_atak_native_fnc_mapCenter;
                };
                ["refresh"] call comspec_atak_native_fnc_unitCard;
            };
            case "lead": {
                if ([_eid] call _armed) then {
                    _s set ["unitLeadArm", []];
                    ["lead", netId _o] call comspec_atak_native_fnc_groupAction;
                } else {
                    // Premier appui : le bouton passe à CONFIRMER, six secondes, puis revient.
                    _s set ["unitLeadArm", [_eid, diag_tickTime]];
                    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["unitLeadArm", []]) isNotEqualTo []) then { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["unitLeadArm", []]; ["refresh"] call comspec_atak_native_fnc_unitCard; }; }, [], 6.2] call comspec_atak_native_fnc_waitAndExec;
                };
                ["refresh"] call comspec_atak_native_fnc_unitCard;
            };
            case "viewshed": { ["compute", [netId _o, ""] select (isNull _o)] call comspec_atak_native_fnc_reliefAction; };
            case "locate": {
                profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true];
                ["locateStart", netId _o] call comspec_atak_native_fnc_relayNet;
            };
            case "bft": { ["BFT"] call comspec_atak_native_fnc_navigate; };
        };
        _s deleteAt "bftSelEnt";
        true
    };
    case "rows": {
        _arg params ["_t", ["_ctx", "panel"]];
        private _id = ([_t] call _resolve) getOrDefault ["id", ""];
        private _per = call _perRow;
        private _tap = ([] call comspec_atak_native_fnc_layoutGet) get "tap";
        private _btns = ([_t, _ctx] call _catalog) apply {
            _x params ["_key", "_label", "_on", "_tip", "_style"];
            [_label, compile format ["[{ ['do', _this] call comspec_atak_native_fnc_unitCard; }, [%1, %2]] call comspec_atak_native_fnc_nextFrame;", str _key, str _id],
                switch (_style) do { case "main": { true }; case "red": { "red" }; default { false }; }, _on, _tip]
        };
        private _rows = [];
        for "_i" from 0 to ((count _btns) - 1) step _per do { _rows pushBack ["buttons", _btns select [_i, _per], "", _tap]; };
        _rows
    };
    case "bar": {
        _arg params ["_t", ["_ctx", "bft"], ["_x0", 0], ["_y0", 0], ["_w", 0.3], ["_inGroup", true]];
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
        private _gap = (_l get "pad");
        private _h = _l get "tap";
        private _fh = ((_l get "fontSmall") * 0.85) max COMSPEC_ATAK_FontMin;
        private _cat = [_t, _ctx] call _catalog;
        private _id = ([_t] call _resolve) getOrDefault ["id", ""];
        // Nombre de boutons par rangée : 3 en portrait mini, 5 ailleurs, moins si le plus long libellé ne tiendrait pas.
        private _per = (call _perRow) min (count _cat);
        private _fits = {
            params ["_n"];
            private _cells = _n + ([0, 1] select ((count _cat) > _n));
            private _lon = 0;
            { _lon = _lon max (count (((_x select 1) call comspec_atak_native_fnc_tr) splitString "")); } forEach (_cat select [0, _n]);
            (_lon * _fh * 0.58 / _ratio) <= ((_w - (_cells - 1) * _gap) / (_cells max 1))
        };
        while { _per > 2 && {!([_per] call _fits)} } do { _per = _per - 1; };
        private _more = (count _cat) > _per;
        private _cells = _per + ([0, 1] select _more);
        private _cw = (_w - (_cells - 1) * _gap) / (_cells max 1);
        private _isMap = _ctx isEqualTo "map";
        private _th = COMSPEC_ATAK_Th;
        private _make = {
            params ["_row", "_px", "_py", "_pw"];
            _row params ["_key", "_label", "_on", "_tip", "_style"];
            private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_style isEqualTo "main"), [_px, _py, _pw, _h], _label, _inGroup] call comspec_atak_native_fnc_pageCtrl;
            _b ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            _b ctrlSetFontHeight _fh;
            _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
            if (_style isNotEqualTo "main") then { _b ctrlSetBackgroundColor ([_th get "surface2", [0.08, 0.1, 0.09, 0.92]] select _isMap); };
            if (_style isEqualTo "red") then { _b ctrlSetBackgroundColor ([_th get "alert", _th get "mapAlert"] select _isMap); _b ctrlSetTextColor (_th get "onChrome"); };
            _b ctrlEnable _on;
            _b setVariable ["ucKey", _key];
            _b setVariable ["ucId", _id];
            _b ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                [{ ["do", _this] call comspec_atak_native_fnc_unitCard; }, [_c getVariable ["ucKey", ""], _c getVariable ["ucId", ""]]] call comspec_atak_native_fnc_nextFrame;
            }];
            _b
        };
        private _btns = [];
        private _allC = [];
        { _btns pushBack ([_x, _x0 + _forEachIndex * (_cw + _gap), _y0, _cw] call _make); } forEach (_cat select [0, _per]);
        _allC append _btns;
        // Le surplus : un panneau au-dessus de la rangée, montré ou caché par « … » sans redessiner la page.
        private _moreBtn = controlNull;
        if (_more) then {
            private _rest = _cat select [_per];
            private _pp = (call _perRow) min 3 max 2;
            private _rr = ceil ((count _rest) / _pp);
            private _ph = _rr * (_h + _gap) + _gap;
            private _py = _y0 - _ph;
            private _bg = ["COMSPEC_RscText", [_x0, _py, _w, _ph], "", _inGroup] call comspec_atak_native_fnc_pageCtrl;
            _bg ctrlSetBackgroundColor ([_th get "surface", [0.035, 0.042, 0.038, 0.97]] select _isMap);
            private _panel = [_bg];
            private _pw = (_w - (_pp + 1) * _gap) / _pp;
            {
                private _b = [_x, _x0 + _gap + (_forEachIndex mod _pp) * (_pw + _gap), _py + _gap + (floor (_forEachIndex / _pp)) * (_h + _gap), _pw] call _make;
                _btns pushBack _b;
                _panel pushBack _b;
            } forEach _rest;
            { _x ctrlShow false; } forEach _panel;
            _allC append _panel;
            _moreBtn = ["COMSPEC_RscButton", [_x0 + _per * (_cw + _gap), _y0, _cw, _h], "…", _inGroup] call comspec_atak_native_fnc_pageCtrl;
            _moreBtn ctrlSetFontHeight _fh;
            _moreBtn ctrlSetTooltip (("Autres actions") call comspec_atak_native_fnc_tr);
            _moreBtn ctrlSetBackgroundColor ([_th get "surface2", [0.08, 0.1, 0.09, 0.92]] select _isMap);
            _allC pushBack _moreBtn;
            _moreBtn setVariable ["ucPanel", _panel];
            _moreBtn ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                private _panel = _c getVariable ["ucPanel", []];
                private _show = !ctrlShown (_panel param [0, controlNull]);
                { _x ctrlShow _show; } forEach _panel;
            }];
        };
        createHashMapFromArray [["h", _h], ["keys", _cat apply { _x select 0 }], ["btns", _btns], ["more", _moreBtn], ["all", _allC]]
    };
    case "barUpdate": {
        _arg params ["_bar", "_t", ["_ctx", "bft"]];
        private _cat = [_t, _ctx] call _catalog;
        if ((_cat apply { _x select 0 }) isNotEqualTo (_bar getOrDefault ["keys", []])) exitWith { false };
        private _id = ([_t] call _resolve) getOrDefault ["id", ""];
        {
            private _b = (_bar get "btns") param [_forEachIndex, controlNull];
            _x params ["", "_label", "_on"];
            if (!isNull _b) then {
                _b ctrlSetText ((_label) call comspec_atak_native_fnc_tr);
                _b ctrlEnable _on;
                _b setVariable ["ucId", _id];
            };
        } forEach _cat;
        true
    };
    // ---- Fiche flottante de la carte (bas au centre, au-dessus du bandeau) : créée au clic, jamais par image.
    case "mapCard": {
        { ctrlDelete _x; } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_UnitCard", createHashMap]) getOrDefault ["ctrls", []]);
        uiNamespace setVariable ["COMSPEC_ATAK_UnitCard", createHashMap];
        private _geo = uiNamespace getVariable ["COMSPEC_ATAK_UnitCardGeom", []];
        private _e = [_s getOrDefault ["selectedEntity", createHashMap]] call _resolve;
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _mini = _l get "mini";
        // Un seul occupant du bas : marqueur sélectionné, édition, palette ou menu ouvert en petit format : pas de fiche.
        if ((count _geo) < 7 || {(count _e) isEqualTo 0} || {!(_l get "interactive")}
            || {(_s getOrDefault ["activePage", ""]) isNotEqualTo "MAP"}
            || {(_s getOrDefault ["selectedMarker", ""]) isNotEqualTo ""}
            || {(count (_s getOrDefault ["markerEdit", createHashMap])) > 0}
            || {(_s getOrDefault ["mapMode", "SELECT"]) in ["MARKER", "WP"]}
            || {_mini && {_s getOrDefault ["mapToolsOpen", false]}}) exitWith { 0 };
        _geo params ["_bx", "_by", "_mw", "_mh", "_panH", "_pad", "_toolCol"];
        private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
        private _fs = _l get "fontSmall";
        private _font = _l get "font";
        private _tap = _l get "tap";
        private _th = COMSPEC_ATAK_Th;
        private _dir = "\z\comspec_atak_native\addons\main\data\";
        private _in = _pad * 0.8;
        private _lineH = _fs * 1.15;
        private _pw = (((_mw * 0.55) max (_font * 14 / _ratio)) min (_mw - _toolCol - 2 * _pad)) max (_font * 8 / _ratio);
        private _px = ((_bx + (_mw - _pw) / 2) min (_bx + _mw - _toolCol - _pad - _pw)) max (_bx + _pad);
        private _ph = _in + _tap + ([_lineH * 2 + _in * 0.4, 0] select _mini) + _in * 0.6 + _tap + _in;
        // Au-dessus du bandeau et de RECHERCHER.
        private _py = _by + _mh - _panH - _pad * 2 - _fs * 1.9 - _pad - _ph;
        private _made = [];
        private _mk = { params ["_class", "_pos", ["_text", ""]]; private _c = [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl; _made pushBack _c; _c };
        ([_e] call comspec_atak_native_fnc_symbology) params ["_icon", "_color"];
        if ((profileNamespace getVariable ["COMSPEC_ATAK_UnitSymbols", "NATO"]) isEqualTo "NATO") then { _icon = ([_e] call comspec_atak_native_fnc_natoSymbol) select 0; };
        private _o = _e getOrDefault ["object", objNull];
        if (!isNull _o && {(_e getOrDefault ["affiliation", ""]) isEqualTo "friend"}) then {
            private _ft = [_o] call comspec_atak_native_fnc_ftInfo;
            if ((_ft get "id") isNotEqualTo "") then { _color = _ft get "rgba"; };
        };
        private _rgb = [_color select 0, _color select 1, _color select 2, 1];
        private _brd = ["COMSPEC_RscText", [_px - pixelW, _py - pixelH, _pw + pixelW * 2, _ph + pixelH * 2]] call _mk;
        _brd ctrlSetBackgroundColor [0.30, 0.36, 0.32, 0.9];
        private _cbg = ["COMSPEC_RscText", [_px, _py, _pw, _ph]] call _mk;
        _cbg ctrlSetBackgroundColor [0.035, 0.042, 0.038, 0.97];
        private _edge = ["COMSPEC_RscText", [_px, _py, pixelW * 4, _ph]] call _mk;
        _edge ctrlSetBackgroundColor _rgb;
        // Pastille : le symbole de l'unité dans sa couleur.
        private _x0 = _px + pixelW * 4 + _in / _ratio * 0.6;
        private _chipW = _tap / _ratio;
        private _chip = ["COMSPEC_RscText", [_x0, _py + _in, _chipW, _tap]] call _mk;
        _chip ctrlSetBackgroundColor [_rgb select 0, _rgb select 1, _rgb select 2, 0.16];
        private _ic = ["COMSPEC_RscIcon", [_x0 + _chipW * 0.15, _py + _in + _tap * 0.15, _chipW * 0.7, _tap * 0.7], _icon] call _mk;
        _ic ctrlSetTextColor _rgb;
        // Passer en revue : précédent, n/m, suivant ; puis fermer. Cibles de la hauteur tactile.
        private _peers = call _peerList;
        private _at = _peers findIf { (_x getOrDefault ["id", ""]) isEqualTo (_e getOrDefault ["id", ""]) };
        private _xR = _px + _pw - _in / _ratio * 0.6;
        private _bW = _tap / _ratio;
        private _close = ["COMSPEC_RscIconButton", [_xR - _bW, _py + _in, _bW, _tap], _dir + "ui_close.paa"] call _mk;
        _close ctrlSetTooltip (("Fermer la fiche") call comspec_atak_native_fnc_tr);
        _close ctrlSetTextColor (_th get "mapDim");
        _close ctrlAddEventHandler ["ButtonClick", {
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["selectedEntity", createHashMap];
            [{ ["mapCard"] call comspec_atak_native_fnc_unitCard; [] call comspec_atak_native_fnc_inspectorUpdate; }] call comspec_atak_native_fnc_nextFrame;
        }];
        private _xT = _xR - _bW;
        if ((count _peers) > 0) then {
            private _cntW = _fs * 2.6 / _ratio;
            private _showCnt = _pw > (_chipW + _bW * 3 + _cntW + _font * 5 / _ratio);
            private _nx = ["COMSPEC_RscIconButton", [_xT - _bW, _py + _in, _bW, _tap], _dir + "ui_chevron_right.paa"] call _mk;
            _nx ctrlSetTooltip (("Équipier suivant") call comspec_atak_native_fnc_tr);
            _nx ctrlSetTextColor (_th get "mapText");
            _nx ctrlAddEventHandler ["ButtonClick", { [{ ["step", 1] call comspec_atak_native_fnc_unitCard; }] call comspec_atak_native_fnc_nextFrame; }];
            _xT = _xT - _bW;
            if (_showCnt) then {
                private _cnt = ["COMSPEC_RscTextCenter", [_xT - _cntW, _py + _in, _cntW, _tap], format ["%1/%2", _at + 1, count _peers]] call _mk;
                _cnt ctrlSetFont "EtelkaMonospacePro";
                _cnt ctrlSetFontHeight (((_fs * 0.85) max COMSPEC_ATAK_FontMin));
                _cnt ctrlSetTextColor (_th get "mapDim");
                _xT = _xT - _cntW;
            };
            private _pv = ["COMSPEC_RscIconButton", [_xT - _bW, _py + _in, _bW, _tap], _dir + "ui_back.paa"] call _mk;
            _pv ctrlSetTooltip (("Équipier précédent") call comspec_atak_native_fnc_tr);
            _pv ctrlSetTextColor (_th get "mapText");
            _pv ctrlAddEventHandler ["ButtonClick", { [{ ["step", -1] call comspec_atak_native_fnc_unitCard; }] call comspec_atak_native_fnc_nextFrame; }];
            _xT = _xT - _bW;
        };
        private _tx0 = _x0 + _chipW + _in / _ratio * 0.6;
        private _title = ["COMSPEC_RscStructuredText", [_tx0, _py + _in + (_tap - _fs * 1.35) / 2, (_xT - _tx0 - pixelW * 2) max (_chipW), _fs * 1.35]] call _mk;
        _title ctrlSetFontHeight _fs;
        private _detail = controlNull;
        if (!_mini) then {
            _detail = ["COMSPEC_RscStructuredText", [_x0, _py + _in + _tap + _in * 0.2, _pw - (_x0 - _px) - _in / _ratio * 0.6, _lineH * 2 + _in * 0.2]] call _mk;
            _detail ctrlSetFontHeight _fs;
        };
        private _bar = ["bar", [_e, "map", _x0, _py + _ph - _in - _tap, _pw - (_x0 - _px) - _in / _ratio * 0.6, false]] call comspec_atak_native_fnc_unitCard;
        _made append (_bar get "all");
        private _card = createHashMapFromArray [["ctrls", _made], ["title", _title], ["detail", _detail], ["bar", _bar], ["id", _e getOrDefault ["id", ""]], ["texts", ["", ""]]];
        uiNamespace setVariable ["COMSPEC_ATAK_UnitCard", _card];
        ["tick"] call comspec_atak_native_fnc_unitCard;
        _ph + _fs * 1.9 + _pad
    };
    // Rafraîchi une fois par seconde (fn_statusUpdate) : textes écrits seulement s'ils changent, boutons sur place.
    case "tick": {
        private _card = uiNamespace getVariable ["COMSPEC_ATAK_UnitCard", createHashMap];
        if ((count _card) isEqualTo 0) exitWith { false };
        private _e = [_card get "id"] call _resolve;
        // Unité disparue des pistes : la fiche se referme ; autre unité sélectionnée ailleurs : elle est refaite.
        if ((count _e) isEqualTo 0) exitWith {
            _s set ["selectedEntity", createHashMap];
            ["mapCard"] call comspec_atak_native_fnc_unitCard;
            false
        };
        private _sz = ((COMSPEC_ATAK_FontMin / ((([] call comspec_atak_native_fnc_layoutGet) get "fontSmall") max 0.001)) max 0.85) toFixed 2;
        private _esc = { [[(_this regexReplace ["&", "&amp;"]), "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
        private _th = COMSPEC_ATAK_Th;
        private _st = ["state", [_e, true]] call comspec_atak_native_fnc_unitCard;
        private _o = _e getOrDefault ["object", objNull];
        private _friend = (_e getOrDefault ["affiliation", ""]) isEqualTo "friend";
        private _t1 = format ["<t font='COMSPEC_BarlowBold' size='1.15' color='%1'>%2</t>  <t size='%3' color='%4'>● %5</t>", _th get "hexMapText", (_e getOrDefault ["callsign", "CONTACT"]) call _esc, _sz, _st select 2, toUpper ((_st select 1) call comspec_atak_native_fnc_tr)];
        private _t2 = "";
        if (!isNull (_card get "detail")) then {
            private _parts = [];
            if (_friend && {!isNull _o}) then {
                private _ft = [_o] call comspec_atak_native_fnc_ftInfo;
                if ((_ft get "name") isNotEqualTo "") then { _parts pushBack ((_ft get "name") call _esc); };
                if ((_ft get "roleShort") isNotEqualTo "") then { _parts pushBack ((_ft get "roleShort") call _esc); };
                if (isPlayer _o) then { _parts pushBack ((name _o) call _esc); };
            };
            if (!_friend) then { _parts pushBack ((_e getOrDefault ["affiliation", "unknown"]) call comspec_atak_native_fnc_tr); };
            if (!isNull _o && {(vehicle _o) isNotEqualTo _o}) then { _parts pushBack format [("à bord %1") call comspec_atak_native_fnc_tr, (getText (configOf vehicle _o >> "displayName")) call _esc]; };
            private _p = _e getOrDefault ["position", [0, 0, 0]];
            private _dd = player distance2D _p;
            private _age = round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]));
            _t2 = format ["<t size='%1' color='%2'>%3</t><br/><t font='EtelkaMonospacePro' size='%1' color='%2'>%4 · %5 · %6° · %7</t>", _sz, _th get "hexMapDim", _parts joinString " · ",
                [_p, 8] call comspec_atak_native_fnc_gridRef, [format ["%1 m", round _dd], format ["%1 km", (_dd / 1000) toFixed 1]] select (_dd >= 1000),
                [(round (player getDir _p)) mod 360, 3] call CBA_fnc_formatNumber, [format ["%1 s", _age], format ["%1 min", floor (_age / 60)]] select (_age >= 60)];
        };
        private _texts = [_t1, _t2];
        if (_texts isNotEqualTo (_card get "texts")) then {
            _card set ["texts", _texts];
            (_card get "title") ctrlSetStructuredText parseText _t1; // i18n:brut
            if (!isNull (_card get "detail")) then { (_card get "detail") ctrlSetStructuredText parseText _t2; }; // i18n:brut
        };
        if !(["barUpdate", [_card get "bar", _e, "map"]] call comspec_atak_native_fnc_unitCard) then { ["mapCard"] call comspec_atak_native_fnc_unitCard; };
        true
    };
    // Équipier suivant ou précédent : mon groupe, puis les alliés à moins d'un kilomètre, du plus proche au plus loin.
    case "step": {
        private _peers = call _peerList;
        if ((count _peers) isEqualTo 0) exitWith { false };
        private _id = (_s getOrDefault ["selectedEntity", createHashMap]) getOrDefault ["id", ""];
        private _at = _peers findIf { (_x getOrDefault ["id", ""]) isEqualTo _id };
        private _n = count _peers;
        private _next = _peers select ([(_at + _arg + _n) mod _n, [_n - 1, 0] select (_arg > 0)] select (_at < 0));
        _s set ["selectedEntity", _next];
        // La carte ne bouge que si l'équipier est hors de la vue, et jamais pendant un suivi.
        private _map = [] call comspec_atak_native_fnc_mapCtrl;
        if (!isNull _map && {(_s getOrDefault ["mapFollowId", ""]) isEqualTo ""} && {!(_s getOrDefault ["mapFollow", false])}) then {
            private _p = _next getOrDefault ["position", [0, 0, 0]];
            private _sp = _map ctrlMapWorldToScreen _p;
            (ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
            if ((_sp select 0) < _mx || {(_sp select 0) > (_mx + _mw)} || {(_sp select 1) < _my} || {(_sp select 1) > (_my + _mh)}) then { [_p, -1, true] call comspec_atak_native_fnc_mapCenter; };
        };
        ["mapCard"] call comspec_atak_native_fnc_unitCard;
        [] call comspec_atak_native_fnc_inspectorUpdate;
        true
    };
    default { false };
}
