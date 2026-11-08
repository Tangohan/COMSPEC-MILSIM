/*
    Configurateur « Réalisme et réseau » du menu Échap : tous les réglages de réalisme (fn_realismCatalog) rangés par thème, valeur en vigueur et source,
    boutons de l'assistant (fn_setupWizard) et de coupure du lien radio, conditions du moment.
    Profil : le choisir remet chaque liste à la valeur du profil ; Appliquer n'envoie que le profil et les réglages qui
    s'en écartent, pour qu'un changement de profil plus tard les reprenne tous.
      - tout le monde l'ouvre (bouton « Conditions réseau » du menu Échap) et voit les valeurs en vigueur et leur source ;
      - un administrateur du serveur (#login), l'hôte ou un Zeus applique au serveur : COMSPEC_ATAK_NetCond diffusée
        à tous les joueurs (et aux arrivants), notification à chacun, puis envoi au site (COMSPEC Link
        NetConditions.Report) : la page Contrôle serveur montre les conditions appliquées en jeu ;
      - une condition imposée par la communauté sur le site (Contrôle serveur > Réseau et simulation) est verrouillée.
    Ordre des sources : fn_netCond.
    Params : ["button", écran du menu Échap] | ["open", écran parent] | ["apply", [[clé, valeur]...]] (client) |
             ["server", [[clé, valeur]...], nom] (serveur) | ["canEdit"] -> booléen | ["presets"] | ["expect", profil] -> HashMap clé -> valeur
*/
params [["_mode", "open"], ["_a", displayNull], ["_b", ""]];
disableSerialization;
private _T = { _this call comspec_atak_native_fnc_tr };
switch (_mode) do {
    case "canEdit": {
        !isMultiplayer || {isServer} || {[player] call comspec_atak_native_fnc_adminAllowed}
    };
    case "expect": {
        // Valeur de chaque condition avec ce profil, sans conditions appliquées en jeu (profil, sinon réglage CBA).
        private _pm = ["profileOf", _a] call comspec_atak_native_fnc_netCond;
        private _out = createHashMap;
        {
            _x params ["_key", "_cba", "", "", "_def"];
            private _v = missionNamespace getVariable [_cba, _def];
            if (_key in _pm && {_v isEqualTo _def}) then { _v = _pm get _key; };
            _out set [_key, _v];
        } forEach (["defs"] call comspec_atak_native_fnc_netCond);
        _out
    };
    case "presets": {
        [
            ["Par défaut : toutes les simulations actives", []],
            ["Beau temps, réseau léger", [["weatherK", 50], ["bgLoad", 0], ["lossFloor", 0], ["latencyAdd", 0]]],
            ["Tempête : météo deux fois plus forte", [["weather", true], ["weatherK", 200]]],
            ["Réseau saturé (trafic de fond 75 %)", [["congestion", true], ["bgLoad", 75]]],
            ["Liaison satellite lointaine", [["latencyAdd", 600], ["lossFloor", 3]]],
            ["Zone dégradée", [["realism", 2], ["bgLoad", 50], ["lossFloor", 10], ["latencyAdd", 150]]],
            ["Entraînement : simulation coupée", [["sim", false]]]
        ]
    };
    case "button": {
        private _d = _a;
        if (isNull _d || {!hasInterface}) exitWith {};
        private _old = _d displayCtrl 9690;
        if (!isNull _old) then { ctrlDelete _old; };
        private _bt = _d ctrlCreate ["ctrlButton", 9690];
        private _w = 0.125 * safeZoneW;
        // Sous la barre COMSPEC d'Overwatch (en haut à droite), alignée sur son bord droit.
        _bt ctrlSetPosition [safeZoneX + safeZoneW * 0.988 - _w, safeZoneY + 0.068 * safeZoneH, _w, 0.034 * safeZoneH];
        _bt ctrlSetText ((("Réalisme et réseau" call _T)) call comspec_atak_native_fnc_tr);
        // Police proportionnelle au bouton : avec la taille d'interface NORMALE ou plus grande, la police par défaut
        // débordait du bouton (texte écrasé).
        _bt ctrlSetFontHeight (0.034 * safeZoneH * 0.6);
        _bt ctrlSetTooltip ((("Profil de réalisme et simulation du serveur : réseau radio, météo, surcharge, pertes, positions BFT, GPS, batterie" call _T)) call comspec_atak_native_fnc_tr);
        _bt ctrlSetBackgroundColor [0.10, 0.30, 0.16, 0.95];
        _bt ctrlAddEventHandler ["ButtonClick", { ["open", ctrlParent (_this select 0)] call comspec_atak_native_fnc_netConfig; }];
        _bt ctrlCommit 0;
    };
    case "apply": {
        if !(["canEdit"] call comspec_atak_native_fnc_netConfig) exitWith {
            ["WARNING", "Seul un administrateur du serveur ou un Zeus peut appliquer des conditions réseau.", 5, 40] call comspec_atak_native_fnc_notify;
        };
        ["server", _a, name player] remoteExecCall ["comspec_atak_native_fnc_netConfig", 2];
        // Site : conditions appliquées en jeu, par qui (COMSPEC Link, si la liaison Athena est active).
        if ([] call comspec_atak_native_fnc_bridge) then {
            private _srv = createHashMapFromArray _a;
            private _cond = createHashMap;
            {
                _x params ["_key", "", "_sk", "_kind", "_def"];
                private _v = _srv getOrDefault [_key, missionNamespace getVariable [_x select 1, _def]];
                _cond set [_sk, [str _v, ["off", "on"] select _v] select (_kind isEqualTo "bool")];
            } forEach (["defs"] call comspec_atak_native_fnc_netCond);
            private _p = createHashMapFromArray [
                ["conditions", _cond], ["applied_by", name player], ["mission", missionName], ["world", worldName],
                ["server", serverName], ["reset", _a isEqualTo []]
            ];
            private _r = (["COMSPECExtension", "NetConditions.Report", [[_p] call comspec_atak_native_fnc_json]] call comspec_overwatch_connect_fnc_extDirect);
            ["INFO", "NET", format ["Conditions réseau envoyées au site : %1", _r]] call comspec_atak_native_fnc_log;
        };
    };
    case "server": {
        if (!isServer) exitWith {};
        // Contrôle côté serveur : administrateur connecté, hôte, Zeus, ou partie solo.
        private _owner = remoteExecutedOwner;
        private _ok = !isMultiplayer || {_owner in [0, 2, clientOwner]} || {(admin _owner) > 0};
        if (!_ok) then {
            private _u = allPlayers select { owner _x isEqualTo _owner };
            _ok = _u isNotEqualTo [] && {[_u select 0] call comspec_atak_native_fnc_adminAllowed};
        };
        if (!_ok) exitWith { ["WARNING", "NET", format ["Conditions réseau refusées (client %1 sans droits)", _owner]] call comspec_atak_native_fnc_log; };
        // Valeurs gardées seulement si la clé existe et le type correspond.
        private _defs = createHashMapFromArray ((["defs"] call comspec_atak_native_fnc_netCond) apply { [_x select 0, _x select 4] });
        private _clean = _a select { _x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) in _defs} && {(_x select 1) isEqualType (_defs get (_x select 0))} };
        missionNamespace setVariable ["COMSPEC_ATAK_NetCond", [_clean, _b, systemTime], true];
        private _msg = if (_clean isEqualTo []) then { format ["Conditions réseau : retour aux réglages du serveur (%1)", _b] } else { format ["Conditions réseau appliquées par %1 (%2 changement(s))", _b, count _clean] };
        ["INFO", _msg, 6, 50] remoteExecCall ["comspec_atak_native_fnc_notify", [0, -2] select isDedicated];
        ["INFO", "NET", _msg] call comspec_atak_native_fnc_log;
    };
    case "open": {
        private _parent = _a;
        if (isNull _parent) then { { if (!isNull (findDisplay _x)) exitWith { _parent = findDisplay _x; }; } forEach [49, 46]; };
        if (isNull _parent) exitWith {};
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith {};
        private _edit = ["canEdit"] call comspec_atak_native_fnc_netConfig;
        ["refresh"] call comspec_atak_native_fnc_netCond;
        // Même largeur réelle en 16:9 et en 21:9 : la largeur suit la hauteur de l'écran. Texte et listes à hauteur fixe, quelle que soit la taille d'interface.
        private _w = (safeZoneH * 1.28) min (safeZoneW * 0.96);
        private _h = safeZoneH * 0.86;
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2 + safeZoneH * 0.015;
        private _u = safeZoneH * 0.006;
        private _fh = safeZoneH * 0.021;
        private _mk = {
            params ["_cls", "_pos", ["_grp", controlNull], ["_idc", -1]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, _idc] } else { _d ctrlCreate [_cls, _idc, _grp] };
            _c ctrlSetPosition _pos;
            _c ctrlSetFontHeight _fh;
            _c ctrlCommit 0;
            _c
        };
        (["ctrlStatic", [safeZoneX, safeZoneY, safeZoneW, safeZoneH]] call _mk) ctrlSetBackgroundColor [0, 0, 0, 0.55];
        (["ctrlStatic", [_x0, _y0, _w, _h]] call _mk) ctrlSetBackgroundColor [0.12, 0.13, 0.12, 0.98];
        private _title = ["ctrlStatic", [_x0, _y0 - 5 * _u, _w, 5 * _u]] call _mk;
        _title ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
        _title ctrlSetText ("  COMSPEC ATAK · Réalisme et réseau du serveur" call _T);

        // Colonne de gauche : réglages par thème (libellé, valeur et source, liste de valeurs).
        private _lw = _w * 0.66;
        private _head = ["ctrlStructuredText", [_x0 + _u, _y0 + _u, _lw, 8 * _u]] call _mk;
        private _held = (missionNamespace getVariable ["COMSPEC_ATAK_NetCond", [[], "", 0]]);
        private _who = if ((_held select 0) isEqualTo []) then { "Aucune condition appliquée en jeu." call _T } else {
            format ["Conditions appliquées en jeu par %1 à %2." call _T, _held select 1, [(_held select 2) param [3, 0], (_held select 2) param [4, 0]] apply { [str _x, "0" + str _x] select (_x < 10) } joinString ":"]
        };
        _head ctrlSetStructuredText parseText format ["%1<br/><t color='#9AA59C'>%2</t>",
            [("Lecture seule : seuls un admin, un Zeus ou un gestionnaire désigné peuvent changer ces réglages." call _T), ("Choisissez puis Appliquer au serveur : tous les joueurs les reçoivent tout de suite." call _T)] select _edit, _who];
        private _grp = ["ctrlControlsGroup", [_x0 + _u, _y0 + 9 * _u, _lw, _h - 18 * _u]] call _mk;
        private _inner = _lw - safeZoneW * 0.012;
        private _rowH = safeZoneH * 0.052;
        private _cw = _inner * 0.36;
        private _combos = [];
        private _cbaCombos = [];
        private _order = ["Profil", "Réseau", "Radio et voix", "Maillage", "Positions et GPS", "Brouillage et clés", "Batterie", "Téléphone et dégâts", "Traces HVT", "Développement"];
        private _rows = ["rows"] call comspec_atak_native_fnc_realismCatalog;
        private _yy = 0;
        {
            private _th = _x;
            private _hd = ["ctrlStatic", [0, _yy, _inner, safeZoneH * 0.03], _grp] call _mk;
            _hd ctrlSetText (_th call _T);
            _hd ctrlSetTextColor [0.49, 0.83, 0.54, 1];
            _hd ctrlSetBackgroundColor [0.08, 0.09, 0.08, 1];
            _yy = _yy + safeZoneH * 0.034;
            {
                private _row = _x;
                _row params ["", "_label", "_var", "_kind", "_vals", "_names", "_cond", "_rule"];
                (["state", _row] call comspec_atak_native_fnc_realismCatalog) params ["_cur", "_txt", "_st", "_sc", "_lock", "_where", "_src"];
                private _lb = ["ctrlStructuredText", [0, _yy, _inner - _cw - _u, _rowH], _grp] call _mk;
                _lb ctrlSetStructuredText parseText format ["%1<br/><t size='0.85' color='%2'>%3</t>", _label call _T, _sc, _st];
                if (_kind in ["bool", "num"]) then {
                    private _cb = ["ctrlCombo", [_inner - _cw, _yy + 0.6 * _u, _cw, safeZoneH * 0.034], _grp] call _mk;
                    if (_kind isEqualTo "bool") then { _vals = [true, false]; _names = ["Actif", "Coupé"]; };
                    if !(_cur in _vals) then { _vals = _vals + [_cur]; _names = _names + [str _cur]; };
                    { private _i = _cb lbAdd ((_names select _forEachIndex) call _T); _cb lbSetData [_i, str _x]; } forEach _vals;
                    _cb lbSetCurSel (_vals find _cur);
                    _cb ctrlEnable (_edit && {!_lock});
                    if (_lock) then { _cb ctrlSetTooltip _where; };
                    if (_cond isNotEqualTo "") then {
                        _combos pushBack [_cond, _cb, _kind];
                        if (_cond isEqualTo "profile") then {
                            // Profil choisi : chaque liste modifiable reprend la valeur du profil (ou son réglage CBA hors profil).
                            _cb ctrlAddEventHandler ["LBSelChanged", {
                                params ["_c", "_i"];
                                private _set = ["expect", parseNumber (_c lbData _i)] call comspec_atak_native_fnc_netConfig;
                                {
                                    _x params ["_key", "_cb"];
                                    if (_key isEqualTo "profile" || {!ctrlEnabled _cb}) then { continue };
                                    private _v = _set get _key;
                                    for "_j" from 0 to (lbSize _cb) - 1 do { if ((_cb lbData _j) isEqualTo str _v) exitWith { _cb lbSetCurSel _j; }; };
                                } forEach (uiNamespace getVariable ["COMSPEC_ATAK_NetCfgCombos", []]);
                            }];
                        };
                    } else {
                        _cbaCombos pushBack [_var, _cb, _kind, str _cur];
                    };
                } else {
                    // Valeur en lecture seule, retour à la ligne si elle est longue.
                    private _vt = ["ctrlStructuredText", [_inner - _cw, _yy, _cw, _rowH], _grp] call _mk;
                    _vt ctrlSetStructuredText parseText format ["<t align='right'>%1</t>", _txt];
                    _vt ctrlSetTooltip _txt;
                };
                _yy = _yy + _rowH;
            } forEach (_rows select { (_x select 0) isEqualTo _th });
        } forEach _order;
        uiNamespace setVariable ["COMSPEC_ATAK_NetCfgCombos", _combos];
        uiNamespace setVariable ["COMSPEC_ATAK_NetCfgCba", _cbaCombos];

        // Colonne de droite : assistant, lien radio, modèles, conditions du moment.
        private _rx = _x0 + _lw + 3 * _u;
        private _rw = _w - _lw - 4 * _u;
        private _wz = ["ctrlButton", [_rx, _y0 + _u, _rw, 5 * _u]] call _mk;
        _wz ctrlSetText ("Assistant de configuration" call _T);
        _wz ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
        _wz ctrlAddEventHandler ["ButtonClick", { ["open", ctrlParent (_this select 0)] call comspec_atak_native_fnc_setupWizard; }];
        private _rc = ["ctrlButton", [_rx, _y0 + 7 * _u, _rw, 5 * _u]] call _mk;
        private _linked = ["read"] call comspec_atak_native_fnc_radioGuard;
        _rc ctrlSetText ((["Rétablir le lien radio", "Couper le lien radio"] select _linked) call _T);
        _rc ctrlSetTooltip ("Le mod ne touche plus aux radios ACRE2 et TFAR : voix normale, effet immédiat, pour tous les joueurs" call _T);
        _rc ctrlEnable _edit;
        _rc ctrlAddEventHandler ["ButtonClick", { ["cut", [["read"] call comspec_atak_native_fnc_radioGuard]] call comspec_atak_native_fnc_radioGuard; (ctrlParent (_this select 0)) closeDisplay 1; }];
        private _pc = ["ctrlCombo", [_rx, _y0 + 13 * _u, _rw, 5 * _u], controlNull, 300] call _mk;
        _pc lbAdd ("Choisir un modèle de conditions…" call _T);
        { _pc lbAdd ((_x select 0) call _T); } forEach (["presets"] call comspec_atak_native_fnc_netConfig);
        _pc lbSetCurSel 0;
        _pc ctrlEnable _edit;
        _pc ctrlAddEventHandler ["LBSelChanged", {
            params ["_c", "_i"];
            if (_i < 1) exitWith {};
            private _set = createHashMapFromArray (((["presets"] call comspec_atak_native_fnc_netConfig) select (_i - 1)) select 1);
            private _defs = createHashMapFromArray ((["defs"] call comspec_atak_native_fnc_netCond) apply { [_x select 0, _x select 4] });
            {
                _x params ["_key", "_cb"];
                if (!ctrlEnabled _cb) then { continue };
                private _v = _set getOrDefault [_key, _defs get _key];
                for "_j" from 0 to (lbSize _cb) - 1 do { if ((_cb lbData _j) isEqualTo str _v) exitWith { _cb lbSetCurSel _j; }; };
            } forEach (uiNamespace getVariable ["COMSPEC_ATAK_NetCfgCombos", []]);
        }];
        private _live = ["ctrlStructuredText", [_rx, _y0 + 19 * _u, _rw, _h - 28 * _u], controlNull, 301] call _mk;
        _live ctrlSetBackgroundColor [0.08, 0.09, 0.08, 1];

        // Boutons.
        private _bw = _w * 0.2;
        private _by = _y0 + _h - 7 * _u;
        private _ap = ["ctrlButton", [_x0 + _u, _by, _bw, 5 * _u]] call _mk;
        _ap ctrlSetText ("Appliquer au serveur" call _T);
        _ap ctrlSetBackgroundColor [0.10, 0.40, 0.20, 1];
        _ap ctrlEnable _edit;
        _ap ctrlAddEventHandler ["ButtonClick", {
            private _pairs = [];
            private _combos = uiNamespace getVariable ["COMSPEC_ATAK_NetCfgCombos", []];
            // Profil choisi (celui en vigueur si sa liste est verrouillée par le site), puis les écarts au profil seulement.
            private _pi = _combos findIf { (_x select 0) isEqualTo "profile" };
            private _prof = ["profile"] call comspec_atak_native_fnc_netCond;
            if (_pi >= 0) then { private _pc = (_combos select _pi) select 1; _prof = parseNumber (_pc lbData (lbCurSel _pc)); };
            private _expect = ["expect", _prof] call comspec_atak_native_fnc_netConfig;
            {
                _x params ["_key", "_cb", "_kind"];
                if (!ctrlEnabled _cb) then { continue };
                private _s = _cb lbData (lbCurSel _cb);
                private _v = if (_kind isEqualTo "bool") then { _s isEqualTo "true" } else { parseNumber _s };
                if (_key isNotEqualTo "profile" && {_v isEqualTo (_expect get _key)}) then { continue };
                _pairs pushBack [_key, _v];
            } forEach _combos;
            ["apply", _pairs] call comspec_atak_native_fnc_netConfig;
            // Réglages sans condition (maillage, clés, dégâts...) : réglages CBA du serveur, seulement ceux qui ont changé.
            private _cba = [];
            {
                _x params ["_var", "_cb", "_kind", "_was"];
                if (!ctrlEnabled _cb) then { continue };
                private _s = _cb lbData (lbCurSel _cb);
                if (_s isEqualTo _was) then { continue };
                _cba pushBack [_var, if (_kind isEqualTo "bool") then { _s isEqualTo "true" } else { parseNumber _s }];
            } forEach (uiNamespace getVariable ["COMSPEC_ATAK_NetCfgCba", []]);
            if (_cba isNotEqualTo []) then { ["comspec_atak_native_admin", [player, "realismSet", [_cba, false]]] call CBA_fnc_serverEvent; };
            (ctrlParent (_this select 0)) closeDisplay 1;
        }];
        private _rs = ["ctrlButton", [_x0 + 2 * _u + _bw, _by, _bw, 5 * _u]] call _mk;
        _rs ctrlSetText ("Revenir aux réglages serveur" call _T);
        _rs ctrlSetTooltip ("Efface les conditions appliquées en jeu : les réglages CBA du serveur reprennent (les règles du site restent)." call _T);
        _rs ctrlEnable _edit;
        _rs ctrlAddEventHandler ["ButtonClick", { ["apply", []] call comspec_atak_native_fnc_netConfig; (ctrlParent (_this select 0)) closeDisplay 1; }];
        private _cl = ["ctrlButton", [_x0 + _w - _bw * 0.7 - _u, _by, _bw * 0.7, 5 * _u]] call _mk;
        _cl ctrlSetText ("Fermer" call _T);
        _cl ctrlAddEventHandler ["ButtonClick", { (ctrlParent (_this select 0)) closeDisplay 2; }];

        // Conditions du moment, rafraîchies chaque seconde tant que la fenêtre est ouverte.
        [_d] spawn {
            params ["_d"];
            disableSerialization;
            private _T = { _this call comspec_atak_native_fnc_tr };
            while { !isNull _d } do {
                private _live = _d displayCtrl 301;
                private _q = [] call comspec_atak_native_fnc_linkQuality;
                private _w = vectorMagnitude wind;
                private _html = format ["<t color='#7ED38A' font='RobotoCondensedBold'>%1</t><br/>", "Conditions du moment" call _T];
                _html = _html + format ["%1 : %2 · %3 kbit/s · %4 ms · %5 %%<br/>", "Mon téléphone" call _T, (_q getOrDefault ["label", ""]) call _T, _q getOrDefault ["kbps", 0], _q getOrDefault ["latency", 0], _q getOrDefault ["loss", 0]];
                _html = _html + format ["%1 : %2 %% · %3 : %4 km/h · %5 : %6 %% · %7 : %8 %%<br/>",
                    "Pluie" call _T, round (rain * 100), "Vent" call _T, round (_w * 3.6), "Orage" call _T, round (lightnings * 100), "Brouillard" call _T, round (fog * 100)];
                _html = _html + format ["<t color='#7FB6E6'>%1</t><br/><br/>", (["text"] call comspec_atak_native_fnc_radioGuard) call _T];
                _html = _html + format ["<t color='#C9D2CB'>%1</t><br/>", "Ce qui coûte du signal en ce moment" call _T];
                {
                    _x params ["_n", "_e"];
                    _html = _html + format ["• %1 <t color='#E0B050'>%2</t><br/>", _n call _T, _e call _T];
                    if (_forEachIndex >= 10) exitWith {};
                } forEach (_q getOrDefault ["factors", []]);
                _live ctrlSetStructuredText parseText _html;
                uiSleep 1;
            };
        };
    };
};
