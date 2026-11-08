/*
    Fiche joueur unique : les mêmes actions, dans le même ordre et avec les mêmes libellés partout (carte, panneau
    SITUATION, BFT, Groupe, Inter-team). Une cible est une entité BFT (HashMap), son identifiant, ou un objet joueur.
    Ordre fixe : CARTE · Y ALLER · SMS · VIBRER · PING · CAMÉRA · SUIVRE · PASSER LE COMMANDEMENT · CHAMP DE VISION ·
    LOCALISER · FICHE BFT. Sur la carte, où centrer sert moins, SUIVRE prend la première place et CARTE passe derrière ⋯.
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
                                         derrière ⋯ (montré ou caché sans redessin). Renvoie un HashMap : h, keys, btns, more.
      ["barUpdate", [barre, cible, contexte]] -> réécrit la rangée sur place ; false si l'ensemble des actions a changé.
      ["select", cible]               -> pose la sélection de la carte (crochets et fiche)
      ["refresh"]                     -> redessine la fiche ouverte après un changement d'état (commandement armé, suivi)
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
                    [{ ["refresh"] call comspec_atak_native_fnc_unitCard; }, [], 6.2] call comspec_atak_native_fnc_waitAndExec;
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
        { _btns pushBack ([_x, _x0 + _forEachIndex * (_cw + _gap), _y0, _cw] call _make); } forEach (_cat select [0, _per]);
        // Le surplus : un panneau au-dessus de la rangée, montré ou caché par ⋯ sans redessiner la page.
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
            _moreBtn = ["COMSPEC_RscButton", [_x0 + _per * (_cw + _gap), _y0, _cw, _h], "⋯", _inGroup] call comspec_atak_native_fnc_pageCtrl;
            _moreBtn ctrlSetFontHeight _fh;
            _moreBtn ctrlSetTooltip (("Autres actions") call comspec_atak_native_fnc_tr);
            _moreBtn ctrlSetBackgroundColor ([_th get "surface2", [0.08, 0.1, 0.09, 0.92]] select _isMap);
            _moreBtn setVariable ["ucPanel", _panel];
            _moreBtn ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                private _panel = _c getVariable ["ucPanel", []];
                private _show = !ctrlShown (_panel param [0, controlNull]);
                { _x ctrlShow _show; } forEach _panel;
            }];
        };
        createHashMapFromArray [["h", _h], ["keys", _cat apply { _x select 0 }], ["btns", _btns], ["more", _moreBtn]]
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
    default { false };
}
