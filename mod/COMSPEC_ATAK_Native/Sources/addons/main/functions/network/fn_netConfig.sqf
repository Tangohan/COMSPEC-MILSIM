/*
    Configurateur « Conditions réseau » du menu Échap : régler la simulation radio de tout le serveur en cours de partie
    (débit simulé, réalisme, météo, surcharge, pertes de paquets, latence, relais), voir les conditions du moment.
      - tout le monde l'ouvre (bouton « Conditions réseau » du menu Échap) et voit les valeurs en vigueur et leur source ;
      - un administrateur du serveur (#login), l'hôte ou un Zeus applique au serveur : COMSPEC_ATAK_NetCond diffusée
        à tous les joueurs (et aux arrivants), notification à chacun, puis envoi au site (COMSPEC Link
        NetConditions.Report) : la page Contrôle serveur montre les conditions appliquées en jeu ;
      - une condition imposée par la communauté sur le site (Contrôle serveur > Réseau et simulation) est verrouillée.
    Ordre des sources : fn_netCond.
    Params : ["button", écran du menu Échap] | ["open", écran parent] | ["apply", [[clé, valeur]...]] (client) |
             ["server", [[clé, valeur]...], nom] (serveur) | ["canEdit"] -> booléen | ["presets"]
*/
params [["_mode", "open"], ["_a", displayNull], ["_b", ""]];
disableSerialization;
private _T = { _this call comspec_atak_native_fnc_tr };
switch (_mode) do {
    case "canEdit": {
        !isMultiplayer || {isServer} || {serverCommandAvailable "#kick"} || {!isNull (getAssignedCuratorLogic player)}
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
        _bt ctrlSetText ("Conditions réseau" call _T);
        _bt ctrlSetTooltip ("Simulation radio du serveur : météo, surcharge, pertes de paquets, latence, relais" call _T);
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
            private _r = "COMSPECExtension" callExtension ["NetConditions.Report", [[_p] call comspec_atak_native_fnc_json]];
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
            _ok = _u isNotEqualTo [] && {!isNull (getAssignedCuratorLogic (_u select 0))};
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
        private _defs = ["defs"] call comspec_atak_native_fnc_netCond;
        private _eff = ["refresh"] call comspec_atak_native_fnc_netCond;
        private _w = safeZoneW * 0.62;
        private _h = safeZoneH * 0.8;
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2 + safeZoneH * 0.015;
        private _u = safeZoneH * 0.006;
        private _mk = {
            params ["_cls", "_pos", ["_grp", controlNull], ["_idc", -1]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, _idc] } else { _d ctrlCreate [_cls, _idc, _grp] };
            _c ctrlSetPosition _pos;
            _c ctrlCommit 0;
            _c
        };
        (["ctrlStatic", [safeZoneX, safeZoneY, safeZoneW, safeZoneH]] call _mk) ctrlSetBackgroundColor [0, 0, 0, 0.55];
        (["ctrlStatic", [_x0, _y0, _w, _h]] call _mk) ctrlSetBackgroundColor [0.12, 0.13, 0.12, 0.98];
        private _title = ["ctrlStatic", [_x0, _y0 - 5 * _u, _w, 5 * _u]] call _mk;
        _title ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
        _title ctrlSetText (("  COMSPEC ATAK · Conditions réseau du serveur") call _T);

        // Colonne de gauche : une ligne par condition (libellé, source, liste de valeurs).
        private _lw = _w * 0.56;
        private _grp = ["ctrlControlsGroup", [_x0 + _u, _y0 + 9 * _u, _lw, _h - 18 * _u]] call _mk;
        private _head = ["ctrlStructuredText", [_x0 + _u, _y0 + _u, _lw, 8 * _u]] call _mk;
        private _held = (missionNamespace getVariable ["COMSPEC_ATAK_NetCond", [[], "", 0]]);
        private _who = if ((_held select 0) isEqualTo []) then { "Réglages du serveur (CBA), aucune condition appliquée en jeu." call _T } else {
            format ["Conditions appliquées en jeu par %1 à %2." call _T, _held select 1, [(_held select 2) param [3, 0], (_held select 2) param [4, 0]] apply { [str _x, "0" + str _x] select (_x < 10) } joinString ":"]
        };
        _head ctrlSetStructuredText parseText format ["<t size='0.9' color='#C9D2CB'>%1</t><br/><t size='0.8' color='#9AA59C'>%2</t>",
            [("Lecture seule : seul un administrateur du serveur (#login), l'hôte ou un Zeus peut appliquer des conditions." call _T), ("Choisissez les conditions puis Appliquer au serveur : tous les joueurs les reçoivent tout de suite." call _T)] select _edit, _who];
        _head ctrlCommit 0;
        private _labels = createHashMapFromArray [
            ["sim", "Réseau réaliste"], ["realism", "Niveau de réalisme radio"], ["weather", "La météo gêne le réseau"],
            ["weatherK", "Intensité de la météo"], ["congestion", "Saturation du réseau"], ["bgLoad", "Réseau déjà occupé"],
            ["loss", "Envois perdus"], ["lossFloor", "Pertes minimum"], ["latencyAdd", "Délai ajouté"],
            ["relayOnly", "Réseau uniquement par les relais"], ["gateways", "Véhicules relais et PC mobile"],
            ["offlineHold", "Mode hors connexion"], ["videoRelays", "Vidéo dépendante des relais"]
        ];
        private _srcTxt = createHashMapFromArray [["site", ["Imposé par le site", "#E0B050"]], ["server", ["Appliqué en jeu", "#7ED38A"]], ["cba", ["Réglage serveur", "#9AA59C"]]];
        private _rowH = 6.4 * _u;
        private _cw = _lw * 0.42;
        private _combos = [];
        {
            _x params ["_key", "_cba", "_sk", "_kind", "_def", "_vals", "_names"];
            private _y = _forEachIndex * _rowH;
            private _src = ["source", _key] call comspec_atak_native_fnc_netCond;
            (_srcTxt get _src) params ["_st", "_sc"];
            private _lb = ["ctrlStructuredText", [0, _y, _lw - _cw - 2 * _u, _rowH], _grp] call _mk;
            _lb ctrlSetStructuredText parseText format ["<t size='0.9'>%1</t><br/><t size='0.72' color='%2'>%3</t>", (_labels getOrDefault [_key, _key]) call _T, _sc, _st call _T];
            private _cb = ["ctrlCombo", [_lw - _cw - _u, _y + 0.8 * _u, _cw, 4.4 * _u], _grp, 200 + _forEachIndex] call _mk;
            if (_kind isEqualTo "bool") then { _vals = [true, false]; _names = ["Actif", "Coupé"]; };
            private _cur = _eff getOrDefault [_key, _def];
            if !(_cur in _vals) then { _vals = _vals + [_cur]; _names = _names + [str _cur]; };
            { private _i = _cb lbAdd ((_names select _forEachIndex) call _T); _cb lbSetData [_i, str _x]; } forEach _vals;
            _cb lbSetCurSel (_vals find _cur);
            _cb ctrlEnable (_edit && {_src isNotEqualTo "site"});
            if (_src isEqualTo "site") then { _cb ctrlSetTooltip ("Imposé par la communauté sur le site (Contrôle serveur). À changer depuis le site." call _T); };
            _combos pushBack [_key, _cb, _kind];
        } forEach _defs;
        uiNamespace setVariable ["COMSPEC_ATAK_NetCfgCombos", _combos];

        // Colonne de droite : modèles, conditions du moment.
        private _rx = _x0 + _lw + 3 * _u;
        private _rw = _w - _lw - 4 * _u;
        private _pl = ["ctrlStatic", [_rx, _y0 + _u, _rw, 4 * _u]] call _mk;
        _pl ctrlSetText ("Modèles de conditions" call _T);
        private _pc = ["ctrlCombo", [_rx, _y0 + 5 * _u, _rw, 4.4 * _u], controlNull, 300] call _mk;
        _pc lbAdd ("Choisir un modèle…" call _T);
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
        private _live = ["ctrlStructuredText", [_rx, _y0 + 11 * _u, _rw, _h - 20 * _u], controlNull, 301] call _mk;
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
            {
                _x params ["_key", "_cb", "_kind"];
                if (!ctrlEnabled _cb) then { continue };
                private _s = _cb lbData (lbCurSel _cb);
                private _v = if (_kind isEqualTo "bool") then { _s isEqualTo "true" } else { parseNumber _s };
                _pairs pushBack [_key, _v];
            } forEach (uiNamespace getVariable ["COMSPEC_ATAK_NetCfgCombos", []]);
            ["apply", _pairs] call comspec_atak_native_fnc_netConfig;
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
                private _html = format ["<t size='1.05' color='#7ED38A' font='RobotoCondensedBold'>%1</t><br/>", "Conditions du moment" call _T];
                _html = _html + format ["<t size='0.85'>%1 : %2 · %3 kbit/s · %4 ms · %5 %%</t><br/>", "Mon téléphone" call _T, (_q getOrDefault ["label", ""]) call _T, _q getOrDefault ["kbps", 0], _q getOrDefault ["latency", 0], _q getOrDefault ["loss", 0]];
                _html = _html + format ["<t size='0.85'>%1 : %2 %% · %3 : %4 km/h · %5 : %6 %% · %7 : %8 %%</t><br/><br/>",
                    "Pluie" call _T, round (rain * 100), "Vent" call _T, round (_w * 3.6), "Orage" call _T, round (lightnings * 100), "Brouillard" call _T, round (fog * 100)];
                _html = _html + format ["<t size='0.85' color='#C9D2CB'>%1</t><br/>", "Ce qui coûte du signal en ce moment" call _T];
                {
                    _x params ["_n", "_e"];
                    _html = _html + format ["<t size='0.8'>• %1 <t color='#E0B050'>%2</t></t><br/>", _n call _T, _e call _T];
                    if (_forEachIndex >= 10) exitWith {};
                } forEach (_q getOrDefault ["factors", []]);
                _live ctrlSetStructuredText parseText _html;
                uiSleep 1;
            };
        };
    };
};
