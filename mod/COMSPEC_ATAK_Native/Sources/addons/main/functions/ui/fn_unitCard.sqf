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
                                         contexte : "bft" | "bftp" (personne de l'ORBAT sans piste : SMS et vibreur seuls,
                                         sa position reste inconnue) | "map" | "panel" | "group"
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
    private _id = _e getOrDefault ["id", ""];
    private _has = _id isNotEqualTo "";
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
        [["vibe", "buzz"] select (_ctx in ["bft", "bftp"]), ["VIBRER", "HORS LIGNE"] select _off, _net && {_dev} && {!_off}, ["Pas de téléphone ATAK chez cet allié", "Fait vibrer son téléphone (une fois toutes les 5 s)"] select _dev, ""],
        ["ping", "PING", _has, "Ping sur sa position, visible de tout le camp", ""]
    ];
    // Contact (ennemi, neutre, inconnu, civil) : aller, ping vers mon camp, et MARQUER (repère partagé à sa position, qui reste
    // quand la piste disparaît).
    if (!_friend) exitWith { [_car] + (_all select { (_x select 0) in ["route", "ping"] }) + [["mark", "MARQUER", _has && {(count (_e getOrDefault ["position", []])) >= 2}, "Pose un repère à sa position, partagé sur le canal", ""]] };
    if (_me) exitWith { [_car] + (_all select { (_x select 0) isEqualTo "ping" }) + [["viewshed", "CHAMP DE VISION", _al, "Ce qui est visible depuis vous", ""]] };
    private _tail = [];
    private _grp = group player;
    if (_pl && {group _o isEqualTo _grp} && {(leader _grp) isEqualTo player || {(count units _grp) isEqualTo 1}}) then {
        _tail pushBack ["lead", ["PASSER LE COMMANDEMENT", "CONFIRMER"] select ([_id] call _armed), true, "Passer le commandement du groupe : un second appui confirme", "red"];
    };
    _tail pushBack ["viewshed", "CHAMP DE VISION", _al, "Ce qu'il voit depuis sa position", ""];
    _tail pushBack ["locate", "LOCALISER", _dev && {_al}, "Localiser son téléphone par les relais (triangulation)", ""];
    if !(_ctx in ["bft", "bftp"]) then { _tail pushBack ["bft", "FICHE BFT", _has, "Ouvre l'app BFT sur cette piste", ""]; };
    if (_ctx isEqualTo "map") exitWith { [_sui] + _all + [_cam, _car] + _tail };
    private _full = [_car] + _all + [_cam, _sui] + _tail;
    if (_ctx isEqualTo "bftp") then { { if !((_x select 0) in ["sms", "buzz"]) then { _x set [2, false]; }; } forEach _full; };
    _full
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
        // fn_bftAction lit la piste choisie : une entité de passage (allié sans piste, calque masqué) lui est donnée à part,
        // sans changer la ligne choisie du BFT.
        if ((_eid select [0, 2]) isNotEqualTo "o:") then { _s set ["bftSel", _eid]; };
        _s set ["bftSelEnt", _e];
        switch (_key) do {
            case "center": { ["center"] call comspec_atak_native_fnc_bftAction; };
            case "route": { ["route"] call comspec_atak_native_fnc_bftAction; };
            case "sms": { ["sms"] call comspec_atak_native_fnc_bftAction; };
            case "vibe": { ["vibe"] call comspec_atak_native_fnc_bftAction; };
            case "buzz": { ["buzz"] call comspec_atak_native_fnc_bftAction; };
            case "ping": { ["ping"] call comspec_atak_native_fnc_bftAction; };
            case "mark": { ["mark", _e] call comspec_atak_native_fnc_contactStyle; };
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
        // 4.2.1 : largeur des libellés mesurée (bouton caché, même police), plus estimée au nombre de lettres : « GO THERE »
        // ou « Y ALLER » débordaient en paysage et dans la petite fiche de la carte.
        private _mz = ["COMSPEC_RscButton", [0, 0, _w, _h], "", _inGroup] call comspec_atak_native_fnc_pageCtrl;
        _mz ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _mz ctrlSetFontHeight _fh;
        _mz ctrlShow false;
        private _lw = _cat apply { _mz ctrlSetText ((_x select 1) call comspec_atak_native_fnc_tr); ctrlTextWidth _mz }; // i18n:brut
        ctrlDelete _mz;
        private _fits = {
            params ["_n"];
            private _cells = _n + ([0, 1] select ((count _cat) > _n));
            private _lon = 0;
            { _lon = _lon max _x; } forEach (_lw select [0, _n]);
            (_lon / 0.88) <= ((_w - (_cells - 1) * _gap) / (_cells max 1))
        };
        while { _per > 1 && {!([_per] call _fits)} } do { _per = _per - 1; };
        private _more = (count _cat) > _per;
        private _cells = _per + ([0, 1] select _more);
        private _cw = (_w - (_cells - 1) * _gap) / (_cells max 1);
        private _isMap = _ctx isEqualTo "map";
        private _th = COMSPEC_ATAK_Th;
        // 4.2.1 : libellé mesuré une fois dessiné et réduit jusqu'à la taille plancher s'il dépasse le bouton (la répartition
        // ci-dessus n'est qu'une estimation : « GO THERE » débordait en paysage).
        private _fitB = {
            params ["_b", "_f"];
            _b ctrlSetFontHeight _f;
            private _bw = ((ctrlPosition _b) select 2) * 0.9;
            private _tw = ctrlTextWidth _b;
            if (_tw > _bw && {_tw > 0}) then { _b ctrlSetFontHeight ((_f * _bw / _tw) max COMSPEC_ATAK_FontMin); };
        };
        private _make = {
            params ["_row", "_px", "_py", "_pw"];
            _row params ["_key", "_label", "_on", "_tip", "_style"];
            private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_style isEqualTo "main"), [_px, _py, _pw, _h], _label, _inGroup] call comspec_atak_native_fnc_pageCtrl;
            _b ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            [_b, _fh] call _fitB;
            _b setVariable ["ucFh", _fh];
            _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
            // Encres du thème : sur la carte les panneaux restent sombres ; ailleurs le thème jour a des boutons clairs.
            if (_style isNotEqualTo "main") then {
                _b ctrlSetBackgroundColor ([_th get "surface2", [0.08, 0.1, 0.09, 0.92]] select _isMap);
                _b ctrlSetTextColor ([[_th get "off", _th get "mapDim"] select _isMap, [_th get "text", _th get "mapText"] select _isMap] select _on);
            };
            if (_style isEqualTo "red") then { _b ctrlSetBackgroundColor ([_th get "alert", _th get "mapAlert"] select _isMap); _b ctrlSetTextColor (_th get "onChrome"); };
            _b ctrlEnable _on;
            _b setVariable ["ucKey", _key];
            _b setVariable ["ucStyle", _style];
            _b setVariable ["ucMap", _isMap];
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
            // Panneau du surplus : moins de colonnes si le plus long libellé n'y tiendrait pas.
            private _lonR = 0;
            { _lonR = _lonR max _x; } forEach (_lw select [_per]);
            while { _pp > 1 && {(_lonR / 0.88) > ((_w - (_pp + 1) * _gap) / _pp)} } do { _pp = _pp - 1; };
            private _rr = ceil ((count _rest) / _pp);
            private _ph = _rr * (_h + _gap) + _gap;
            private _py = _y0 - _ph;
            private _bg = ["COMSPEC_RscText", [_x0, _py, _w, _ph], "", _inGroup] call comspec_atak_native_fnc_pageCtrl;
            _bg ctrlSetBackgroundColor ([_th get "surface", _th get "mapCard"] select _isMap);
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
            _moreBtn ctrlSetTextColor ([_th get "text", _th get "mapText"] select _isMap);
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
                private _lt = (_label) call comspec_atak_native_fnc_tr;
                if ((ctrlText _b) isNotEqualTo _lt) then {
                    _b ctrlSetText _lt; // i18n:brut (déjà traduit)
                    // Libellé changé (HORS LIGNE, SUIVRE / NE PLUS SUIVRE…) : remesuré, réduit s'il dépasse le bouton.
                    private _f = _b getVariable ["ucFh", ctrlFontHeight _b];
                    _b ctrlSetFontHeight _f;
                    private _bw = ((ctrlPosition _b) select 2) * 0.9;
                    private _tw = ctrlTextWidth _b;
                    if (_tw > _bw && {_tw > 0}) then { _b ctrlSetFontHeight ((_f * _bw / _tw) max COMSPEC_ATAK_FontMin); };
                };
                _b ctrlEnable _on;
                _b setVariable ["ucId", _id];
                if ((_b getVariable ["ucStyle", ""]) isEqualTo "") then {
                    private _m = _b getVariable ["ucMap", false];
                    _b ctrlSetTextColor ([[COMSPEC_ATAK_Th get "off", COMSPEC_ATAK_Th get "mapDim"] select _m, [COMSPEC_ATAK_Th get "text", COMSPEC_ATAK_Th get "mapText"] select _m] select _on);
                };
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
        private _o = _e getOrDefault ["object", objNull];
        // À bord d'un engin (4.2.1) : silhouette de l'engin, bloc ENGIN (nom et genre) et tuiles de l'engin.
        private _veh = [objNull, vehicle _o] select (!isNull _o && {(vehicle _o) isNotEqualTo _o});
        private _aboard = !isNull _veh;
        private _air = _aboard && {_veh isKindOf "Air"};
        // Par ordre d'importance : les dernières sautent si la carte est trop basse.
        private _keys = [["GRILLE", "DERNIER SIGNAL", "DISTANCE", "AZIMUT"],
            ([["PLACE", "VITESSE", "CAP"], ["PLACE", "ALT. SOL", "VITESSE", "CAP"]] select _air) + ["GRILLE", "DERNIER SIGNAL", "DISTANCE", "AZIMUT", "CARBURANT", "À BORD"]] select _aboard;
        // Tuiles : autant par rangée que la largeur le permet (2 à 4), rangées en trop retirées pour rester dans la carte.
        private _cw = _pw - pixelW * 4 - 2 * _in / _ratio * 0.6;
        private _per = ((floor (_cw / (_font * 4.2 / _ratio))) max 2) min 4;
        private _tileH = _lineH * 1.85;
        private _pillH = _lineH * 1.1;
        private _fixedH = _in + _tap + _in * 0.3 + _pillH + _in * 0.3 + _lineH * 1.1 + _in * 0.3 + _in * 0.6 + _tap + _in;
        // Au-dessus du bandeau et de RECHERCHER. Carte trop basse (petit téléphone en portrait) pour l'essentiel (à bord :
        // place, altitude, vitesse, cap) : la fiche descend sur le bandeau et RECHERCHER, qui reviennent à sa fermeture.
        private _bottom = _by + _mh - _panH - _pad * 2 - _fs * 1.9 - _pad;
        private _need = ceil (([2, 4] select _aboard) / _per);
        if ((_fixedH + _need * _tileH) > (_bottom - _by - _pad)) then { _bottom = _by + _mh - _pad; };
        private _room = _bottom - _by - _pad;
        private _rows = ceil ((count _keys) / _per);
        while { _rows > 1 && {(_fixedH + _rows * _tileH) > _room} } do { _rows = _rows - 1; };
        _keys = _keys select [0, _rows * _per];
        private _ph = _fixedH + _rows * _tileH;
        // Jamais au-dessus du haut de la carte.
        private _py = (_bottom - _ph) max (_by + _pad);
        private _made = [];
        private _mk = { params ["_class", "_pos", ["_text", ""]]; private _c = [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl; _made pushBack _c; _c };
        ([_e] call comspec_atak_native_fnc_symbology) params ["_icon", "_color"];
        if ((profileNamespace getVariable ["COMSPEC_ATAK_UnitSymbols", "NATO"]) in ["NATO", "MILSTD"]) then { _icon = ([_e] call comspec_atak_native_fnc_natoSymbol) select 0; };
        if (_aboard) then {
            // Silhouette de l'engin (CfgVehicleIcons : avion, hélicoptère, véhicule, navire).
            // Silhouette générique du genre d'engin (la même pour tous les avions…), plus lisible en petit que l'icône du modèle ;
            // la même que celle de la carte (fn_vehIcon).
            _icon = ([_veh] call comspec_atak_native_fnc_vehIcon) select 1;
        };
        if (!isNull _o && {(_e getOrDefault ["affiliation", ""]) isEqualTo "friend"}) then {
            private _ft = [_o] call comspec_atak_native_fnc_ftInfo;
            if ((_ft get "id") isNotEqualTo "") then { _color = _ft get "rgba"; };
        };
        private _rgb = [_color select 0, _color select 1, _color select 2, 1];
        // Contact : encre de l'affiliation (jetons de fn_theme) et pastille à la forme de l'affiliation, jamais la couleur seule.
        private _cAff = ["aff", _e] call comspec_atak_native_fnc_contactStyle;
        private _contact = _cAff isNotEqualTo "friend";
        if (_contact) then { _rgb = ["ink", _cAff] call comspec_atak_native_fnc_contactStyle; };
        private _brd = ["COMSPEC_RscText", [_px - pixelW, _py - pixelH, _pw + pixelW * 2, _ph + pixelH * 2]] call _mk;
        _brd ctrlSetBackgroundColor (_th get "mapCardEdge");
        private _cbg = ["COMSPEC_RscText", [_px, _py, _pw, _ph]] call _mk;
        _cbg ctrlSetBackgroundColor (_th get "mapCard");
        private _edge = ["COMSPEC_RscText", [_px, _py, pixelW * 4, _ph]] call _mk;
        _edge ctrlSetBackgroundColor _rgb;
        // Pastille : le symbole de l'unité dans sa couleur (à bord : la silhouette de l'engin) ; pour un contact, la forme de
        // son affiliation avec le symbole dedans.
        private _x0 = _px + pixelW * 4 + _in / _ratio * 0.6;
        private _chipW = _tap / _ratio;
        private _tipIc = ("Symbole de l'unité à pied. À bord d'un engin : sa silhouette (avion, hélicoptère, véhicule, navire)") call comspec_atak_native_fnc_tr;
        if (_contact) then {
            {
                _x params ["_ctex", "_crole", "_csc"];
                private _ck = 0.25 * _csc;
                private _cic = ["COMSPEC_RscIcon", [_x0 + _chipW * (0.5 - _ck), _py + _in + _tap * (0.5 - _ck), _chipW * 2 * _ck, _tap * 2 * _ck], _ctex] call _mk;
                _cic ctrlSetTextColor ([_rgb, _th get "mapCard"] select (_crole isEqualTo "DARK"));
            } forEach (["layers", _cAff] call comspec_atak_native_fnc_contactStyle);
            private _ic = ["COMSPEC_RscIcon", [_x0 + _chipW * 0.25, _py + _in + _tap * 0.25, _chipW * 0.5, _tap * 0.5], _icon] call _mk;
            _ic ctrlSetTextColor _rgb;
            _ic ctrlSetTooltip _tipIc;
        } else {
            private _chip = ["COMSPEC_RscText", [_x0, _py + _in, _chipW, _tap]] call _mk;
            _chip ctrlSetBackgroundColor [_rgb select 0, _rgb select 1, _rgb select 2, 0.16];
            private _ic = ["COMSPEC_RscIcon", [_x0 + _chipW * 0.15, _py + _in + _tap * 0.15, _chipW * 0.7, _tap * 0.7], _icon] call _mk;
            _ic ctrlSetTextColor _rgb;
            _ic ctrlSetTooltip _tipIc;
        };
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
        // Petite fiche : sans PRÉCÉDENT / SUIVANT plutôt qu'un indicatif illisible (la revue reste au clic sur la carte).
        private _tx0 = _x0 + _chipW + _in / _ratio * 0.6;
        if ((count _peers) > 0 && {(_xT - _bW * 2 - _tx0) >= (_font * 5 / _ratio)}) then {
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
                _cnt ctrlSetTooltip (("Rang dans la revue : mon groupe, puis les alliés à moins d'un kilomètre. Exemple : 2/5") call comspec_atak_native_fnc_tr);
                _xT = _xT - _cntW;
            };
            private _pv = ["COMSPEC_RscIconButton", [_xT - _bW, _py + _in, _bW, _tap], _dir + "ui_back.paa"] call _mk;
            _pv ctrlSetTooltip (("Équipier précédent") call comspec_atak_native_fnc_tr);
            _pv ctrlSetTextColor (_th get "mapText");
            _pv ctrlAddEventHandler ["ButtonClick", { [{ ["step", -1] call comspec_atak_native_fnc_unitCard; }] call comspec_atak_native_fnc_nextFrame; }];
            _xT = _xT - _bW;
        };
        // Indicatif sur une ligne (texte simple : réduit puis raccourci « … » s'il ne tient pas, jamais sur deux lignes).
        private _title = ["COMSPEC_RscText", [_tx0, _py + _in, ((_xT - _tx0 - pixelW * 2) max _chipW), _tap]] call _mk;
        _title ctrlSetFont "COMSPEC_BarlowBold";
        _title ctrlSetTextColor (_th get "mapText");
        private _cy = _py + _in + _tap + _in * 0.3;
        // Pastilles : état médical (APTE…) puis état de l'engin (EN VOL…), à pied ou l'affiliation d'un contact.
        private _pills = [];
        {
            private _p = ["COMSPEC_RscTextCenter", [_x0, _cy, _cw / 2 - pixelW * 4, _pillH]] call _mk;
            _p ctrlSetFont "COMSPEC_BarlowBold";
            _p ctrlSetBackgroundColor (_th get "surface2");
            _p ctrlSetTooltip (_x call comspec_atak_native_fnc_tr);
            _pills pushBack _p;
        } forEach [
            "État médical. APTE : valide ; BLESSÉ : touché mais conscient ; INCONSCIENT ; MORT ; HORS LIGNE : sa balise ne transmet plus ; « — » : état masqué par la mission. Exemple : APTE",
            "État de l'engin. Aéronef : EN VOL, AU SOL, EN APPROCHE (descend sous 250 m), DÉCOLLAGE (monte sous 250 m), STATIONNAIRE (hélicoptère presque immobile). Véhicule ou navire : EN ROUTE ou À L'ARRÊT. NON REÇU : balise coupée. Exemple : EN VOL"
        ];
        _cy = _cy + _pillH + _in * 0.3;
        // Ligne sous les pastilles. À pied : nom, fonction et joueur (allié), piste ancienne (contact). À bord : bloc ENGIN,
        // silhouette, nom et genre de l'engin (sa place, son altitude… sont dans les tuiles).
        private _rh = _lineH * 1.1;
        private _head = controlNull;
        private _role = controlNull;
        if (_aboard) then {
            private _hi = ["COMSPEC_RscIcon", [_x0, _cy + _rh * 0.1, _rh * 0.8 / _ratio, _rh * 0.8], _icon] call _mk;
            _hi ctrlSetTextColor (_th get "mapText");
            _hi ctrlSetTooltip _tipIc;
            _head = ["COMSPEC_RscText", [_x0 + _rh / _ratio, _cy, _cw - _rh / _ratio, _rh]] call _mk;
            _head ctrlSetFont "COMSPEC_BarlowBold";
            _head ctrlSetTextColor (_th get "mapText");
            _role = _head;
        } else {
            _role = ["COMSPEC_RscText", [_x0, _cy, _cw, _rh]] call _mk;
            _role ctrlSetTextColor (_th get "mapDim");
        };
        _cy = _cy + _rh + _in * 0.3;
        // Tuiles : libellé en petit, valeur dessous ; l'aide de chaque tuile en bulle.
        private _tips = createHashMapFromArray [
            ["PLACE", "Sa place à bord : pilote, copilote, tireur, chef de bord, conducteur ou passager. Exemple : Pilote"],
            ["ALT. SOL", "Hauteur au-dessus du sol ou de la mer (pas l'altitude GPS). Exemple : 450 m"],
            ["VITESSE", "Vitesse de l'engin. Exemple : 520 km/h (en nœuds, kt, si la carte est réglée en unités impériales)"],
            ["CAP", "Direction suivie par l'engin, en degrés et point cardinal. Exemple : 210° SSO (sud-sud-ouest)"],
            ["CARBURANT", "Carburant restant dans l'engin. Exemple : 64 %"],
            ["À BORD", "Personnes à bord, équipage et passagers compris. Exemple : 2 pers. (pilote et copilote)"],
            ["GRILLE", "Position sur la grille de la carte (8 chiffres). Exemple : 1467 1638"],
            ["DISTANCE", "Distance à vol d'oiseau depuis vous. Exemple : 1,1 km"],
            ["AZIMUT", "Direction à prendre depuis vous pour le rejoindre, en degrés et point cardinal. Exemple : 210° SSO (sud-sud-ouest)"],
            ["DERNIER SIGNAL", "Âge de la dernière position reçue. Exemple : il y a 11 s (« en direct » sous 2 s). Au-delà d'une minute, il a pu bouger."]
        ];
        private _tw = (_cw - (_per - 1) * pixelW * 6) / _per;
        private _tiles = [];
        {
            private _tx = _x0 + (_forEachIndex mod _per) * (_tw + pixelW * 6);
            private _ty = _cy + (floor (_forEachIndex / _per)) * _tileH;
            private _tip = (_tips getOrDefault [_x, ""]) call comspec_atak_native_fnc_tr;
            private _lb = ["COMSPEC_RscText", [_tx, _ty, _tw, _lineH * 0.9], _x] call _mk;
            _lb ctrlSetFontHeight ((_fs * 0.78) max COMSPEC_ATAK_FontMin);
            // Libellé réduit, puis abrégé « … », s'il dépasse la tuile (« DERNIER SIGNAL » dans une tuile étroite).
            private _lt = ctrlText _lb;
            private _lwM = _tw * 0.96;
            if ((ctrlTextWidth _lb) > _lwM) then { _lb ctrlSetFontHeight ((((_fs * 0.78) * _lwM / (ctrlTextWidth _lb)) max COMSPEC_ATAK_FontMin)); };
            private _ln = count _lt;
            while { _ln > 1 && {(ctrlTextWidth _lb) > _lwM} } do { _ln = _ln - 1; _lb ctrlSetText ((_lt select [0, _ln]) + "…"); }; // i18n:brut
            _lb ctrlSetTextColor (_th get "mapDim");
            _lb ctrlSetTooltip _tip;
            private _vl = ["COMSPEC_RscText", [_tx, _ty + _lineH * 0.8, _tw, _lineH]] call _mk;
            _vl ctrlSetFont "EtelkaMonospacePro";
            _vl ctrlSetTextColor (_th get "mapText");
            _vl ctrlSetTooltip _tip;
            _tiles pushBack [_x, _vl];
        } forEach _keys;
        private _bar = ["bar", [_e, "map", _x0, _py + _ph - _in - _tap, _pw - (_x0 - _px) - _in / _ratio * 0.6, false]] call comspec_atak_native_fnc_unitCard;
        _made append (_bar get "all");
        private _card = createHashMapFromArray [["ctrls", _made], ["title", _title], ["role", _role], ["head", _head], ["pills", _pills], ["tiles", _tiles],
            ["aboard", _aboard], ["veh", _veh], ["pillMax", _cw / 2 - pixelW * 4], ["icon", _icon], ["bar", _bar], ["id", _e getOrDefault ["id", ""]], ["texts", []]];
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
        private _o = _e getOrDefault ["object", objNull];
        private _veh = [objNull, vehicle _o] select (!isNull _o && {(vehicle _o) isNotEqualTo _o});
        // Monté ou descendu d'un engin, ou changé d'engin : la fiche change de forme, elle est refaite.
        if (_veh isNotEqualTo (_card get "veh")) exitWith { ["mapCard"] call comspec_atak_native_fnc_unitCard; false };
        private _tr = { _this call comspec_atak_native_fnc_tr };
        private _fs = ([] call comspec_atak_native_fnc_layoutGet) get "fontSmall";
        private _th = COMSPEC_ATAK_Th;
        private _st = ["state", [_e, true]] call comspec_atak_native_fnc_unitCard;
        private _friend = (_e getOrDefault ["affiliation", ""]) isEqualTo "friend";
        private _p = _e getOrDefault ["position", [0, 0, 0]];
        private _dd = player distance2D _p;
        private _age = round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]));
        private _deg = { params ["_a"]; format ["%1° %2", [(round _a) mod 360, 3] call CBA_fnc_formatNumber, [_a] call comspec_atak_native_fnc_cardinal] };
        // Rôle.
        private _parts = [];
        if (_friend && {!isNull _o}) then {
            private _ft = [_o] call comspec_atak_native_fnc_ftInfo;
            if ((_ft get "name") isNotEqualTo "") then { _parts pushBack (_ft get "name"); };
            if ((_ft get "roleShort") isNotEqualTo "") then { _parts pushBack (_ft get "roleShort"); };
            if (isPlayer _o) then { _parts pushBack (name _o); };
        };
        private _cAff = ["aff", _e] call comspec_atak_native_fnc_contactStyle;
        if (!_friend) then {
            // Téléphone tracé (fn_pollUnits) : « TÉLÉPHONE », jamais un camp deviné ; « position ancienne » si la piste est figée.
            if ((_e getOrDefault ["freshness", "LIVE"]) in ["STALE", "LOST", "OFFLINE"]) then { _parts pushBack (("POSITION ANCIENNE") call _tr); };
        };
        // Pastille d'engin : état du vol (aéronef) ou de la route ; à pied : À PIED, ou l'affiliation d'un contact.
        private _vehTxt = "";
        private _pill2 = [("À PIED") call _tr, toUpper ([["label", _cAff] call comspec_atak_native_fnc_contactStyle, ("TÉLÉPHONE") call _tr] select (_e getOrDefault ["phone", false]))] select !_friend;
        private _pill2Col = [_th get "mapDim", ["ink", _cAff] call comspec_atak_native_fnc_contactStyle] select !_friend;
        private _vals = createHashMapFromArray [
            ["GRILLE", [_p, 8] call comspec_atak_native_fnc_gridRef],
            ["DISTANCE", [format ["%1 m", round _dd], format ["%1 km", (_dd / 1000) toFixed 1]] select (_dd >= 1000)],
            ["AZIMUT", [player getDir _p] call _deg],
            ["DERNIER SIGNAL", call {
                if (_age < 2) exitWith { ("en direct") call _tr };
                if (_age < 60) exitWith { format [("il y a %1 s") call _tr, _age] };
                format [("il y a %1") call _tr, format ["%1 min", floor (_age / 60)]]
            }]
        ];
        if (!isNull _veh) then {
            private _agl = (getPos _veh) select 2;
            private _spd = speed _veh;
            private _vz = (velocity _veh) select 2;
            private _kind = call {
                if (_veh isKindOf "Plane") exitWith { "AVION" };
                if (_veh isKindOf "Helicopter") exitWith { "HÉLICOPTÈRE" };
                if (_veh isKindOf "Air") exitWith { "AÉRONEF" };
                if (_veh isKindOf "Ship") exitWith { "NAVIRE" };
                if (_veh isKindOf "StaticWeapon") exitWith { "ARME FIXE" };
                "VÉHICULE"
            };
            _vehTxt = format ["%1 · %2", getText (configOf _veh >> "displayName"), _kind call _tr];
            _pill2 = toUpper ((call {
                if !(_veh isKindOf "Air") exitWith { ["À l'arrêt", "En route"] select (_spd > 3) };
                if (isTouchingGround _veh || {_agl < 2}) exitWith { "Au sol" };
                if (_veh isKindOf "Helicopter" && {_spd < 10} && {(abs _vz) < 2}) exitWith { "Stationnaire" };
                if (_agl < 250 && {_vz < -2}) exitWith { "EN APPROCHE" };
                if (_agl < 250 && {_vz > 2}) exitWith { "Décollage" };
                "En vol"
            }) call _tr);
            _pill2Col = [_th get "mapText", _th get "mapOk"] select (_veh isKindOf "Air" && {!(isTouchingGround _veh)});
            private _seat = call {
                if ((driver _veh) isEqualTo _o) exitWith { [["Conducteur", "Barreur"] select (_veh isKindOf "Ship"), "Pilote"] select (_veh isKindOf "Air") };
                if ((_veh isKindOf "Air") && {(_veh turretUnit [0]) isEqualTo _o}) exitWith { "Copilote" };
                if ((commander _veh) isEqualTo _o) exitWith { "Chef de bord" };
                if ((gunner _veh) isEqualTo _o) exitWith { "Tireur" };
                if (((assignedVehicleRole _o) param [0, ""]) isEqualTo "cargo") exitWith { "Passager" };
                "Équipage"
            };
            _vals set ["PLACE", _seat call _tr];
            _vals set ["ALT. SOL", format ["%1 m", round _agl]];
            _vals set ["VITESSE", format ["%1 km/h", round _spd]];
            _vals set ["CAP", [getDir _veh] call _deg];
            _vals set ["CARBURANT", format ["%1 %%", round ((fuel _veh) * 100)]];
            _vals set ["À BORD", format [("%1 pers.") call _tr, count (crew _veh)]];
        };
        private _title = _e getOrDefault ["callsign", "CONTACT"];
        private _pill1 = format ["● %1", toUpper ((_st select 1) call _tr)];
        private _tileTxt = (_card get "tiles") apply { _vals getOrDefault [_x select 0, "—"] };
        private _texts = [_title, _pill1, _pill2, _parts joinString " · ", _vehTxt] + _tileTxt;
        if (_texts isNotEqualTo (_card get "texts")) then {
            _card set ["texts", _texts];
            // Texte sur une ligne : réduit jusqu'à la taille minimale, puis raccourci « … », jamais coupé ni sur deux lignes.
            private _fit = {
                params ["_c", "_t", "_fh"];
                if (isNull _c) exitWith {};
                private _w = ((ctrlPosition _c) select 2) * 0.96;
                _c ctrlSetFontHeight _fh;
                _c ctrlSetText _t; // i18n:brut (déjà traduit)
                private _tw = ctrlTextWidth _c;
                if (_tw > _w) then { _c ctrlSetFontHeight ((_fh * _w / _tw) max COMSPEC_ATAK_FontMin); };
                private _n = count _t;
                while { _n > 1 && {(ctrlTextWidth _c) > _w} } do { _n = _n - 1; _c ctrlSetText ((_t select [0, _n]) + "…"); }; // i18n:brut
            };
            [_card get "title", _title, _fs * 1.15] call _fit;
            (_card get "pills") params ["_p1", "_p2"];
            [_p1, _pill1, _fs * 0.85] call _fit;
            _p1 ctrlSetTextColor (_st select 3);
            [_p2, _pill2, _fs * 0.85] call _fit;
            _p2 ctrlSetTextColor _pill2Col;
            // Pastilles à la largeur de leur texte, côte à côte.
            private _x1 = (ctrlPosition _p1) select 0;
            {
                (ctrlPosition _x) params ["", "_yy", "", "_hh"];
                private _ww = ((ctrlTextWidth _x) + pixelW * 16) min ((_card getOrDefault ["pillMax", 0.1]));
                _x ctrlSetPosition [_x1, _yy, _ww, _hh];
                _x ctrlCommit 0;
                _x1 = _x1 + _ww + pixelW * 6;
            } forEach [_p1, _p2];
            if (isNull (_card get "head")) then { [_card get "role", _parts joinString " · ", _fs * 0.9] call _fit; } else { [_card get "head", _vehTxt, _fs * 0.95] call _fit; };
            { [(_x select 1), _tileTxt select _forEachIndex, _fs * 0.95] call _fit; } forEach (_card get "tiles");
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
