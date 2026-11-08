/*
    Assistant de configuration du réalisme : quelques écrans, un choix expliqué à la fois, un résumé, rien n'est appliqué sans confirmation.
    Ouvert depuis le panneau « Réalisme et réseau » du menu Échap, l'app Admin et le clic droit Zeus. Proposé une fois à l'admin (réglage comspec_atak_native_wizard_offer).
    Application : réglages CBA du serveur pour fn_adminAllowed (le serveur revérifie, fn_adminServer "realismSet") ; pour les autres, profil personnel, valable sur les parties qu'ils hébergent.
    Ce qui est imposé par Athena reste verrouillé : l'écran le dit et dit où le changer.
    Params : ["offer"] | ["open", écran parent] | ["draw"] | ["go", +1 | -1] | ["apply"] | ["fields"] -> écrans
*/
params [["_act", "open"], ["_arg", displayNull]];
disableSerialization;
private _T = { _this call comspec_atak_native_fnc_tr };
private _p = "comspec_atak_native_";
switch (_act) do {
    case "offer": {
        if (!hasInterface || {!(missionNamespace getVariable [_p + "wizard_offer", true])} || {profileNamespace getVariable ["COMSPEC_ATAK_WizardOffered", false]}) exitWith {};
        if !([player] call comspec_atak_native_fnc_adminAllowed) exitWith {};
        profileNamespace setVariable ["COMSPEC_ATAK_WizardOffered", true];
        [] call comspec_atak_native_fnc_saveProfile;
        ["INFO", "Assistant de configuration du réalisme disponible : menu Échap, Réalisme et réseau", 12, 40] call comspec_atak_native_fnc_notify;
    };
    case "fields": {
        private _acre = isClass (configFile >> "CfgPatches" >> "acre_main");
        private _tfar = isClass (configFile >> "CfgPatches" >> "tfar_core") || {isClass (configFile >> "CfgPatches" >> "task_force_radio")};
        private _det = switch (true) do { case (_acre): { "ACRE2 détecté." }; case (_tfar): { "TFAR détecté." }; default { "Aucun mod radio détecté : le mod marche sans." } };
        private _yn = [[true, false], ["Activé", "Coupé"]];
        // Objets proposés : d'abord les vrais objets du mod radio présent, instances reconnues par leur classe de base.
        private _kits = [""]; private _kitN = ["Aucun objet exigé"];
        if (_acre) then { _kits pushBack "ACRE_PRC343|ACRE_PRC148|ACRE_PRC152|ACRE_PRC117F"; _kitN pushBack "Une radio ACRE2 (343, 148, 152 ou 117F)"; };
        if (_tfar) then { _kits pushBack "TFAR_anprc152|TFAR_anprc148jem|TFAR_rf7800str|TFAR_fadak"; _kitN pushBack "Une radio portative TFAR"; };
        if (!_acre && {!_tfar}) then { _kits pushBack "COMSPEC_ATAK_MPU5"; _kitN pushBack "Notre MPU5 (pas de mod radio ici)"; };
        private _reqOk = !isNil {missionNamespace getVariable (_p + "net_required_items")};
        [
            ["Profil de réalisme", "Le profil règle d'un coup le réseau, le GPS et la batterie. Milsim : tout à sa valeur réelle. Standard : les mêmes simulations en plus doux. Arcade : réseau parfait, GPS exact, batterie infinie, dégâts coupés, liaison radio coupée. Le moyen simple de tout désactiver.",
                [[_p + "realism_profile", "Profil", [3, 2, 1], ["Milsim", "Standard", "Arcade"]]]],
            ["Radio utilisée", _det + " Le mod s'appuie d'abord sur les vraies radios du joueur : ACRE2 ou TFAR, avec leur portée. Notre MPU5 ne sert qu'en complément ou sans mod radio. La voix relayée par le maillage n'a jamais été testée en jeu : laissez-la coupée tant que ce n'est pas fait.",
                [[_p + "radio_source", "Radio", [0, 1, 2, 3], ["Détectée automatiquement", "ACRE2", "TFAR", "Aucune"]], [_p + "mpu_real", "Liaison téléphone-radio réaliste", _yn select 0, _yn select 1], [_p + "mpu_voice", "Voix relayée par le maillage", _yn select 0, _yn select 1]]],
            ["Objets obligatoires", ["Sans cet objet, le joueur n'est plus un nœud du réseau : le téléphone reste utilisable, la voix aussi. Ne l'exigez que si vos joueurs l'ont vraiment dans leur équipement.", "Le réglage d'objet obligatoire n'existe pas encore dans cette version du mod. « Radio de données obligatoire » coupe le réseau du téléphone sans radio : laissez-le coupé sans préparation de mission."] select !_reqOk,
                ([[[_p + "net_required_items", "Objet obligatoire", _kits, _kitN]], []] select !_reqOk) + [[_p + "mpu_require", "Radio de données obligatoire", _yn select 0, _yn select 1]]],
            ["Brouillage et clés", "Les brouilleurs gênent le téléphone, jamais la portée radio d'ACRE2 ou TFAR. Les clés demandent une préparation de mission : sans clé chargée, un joueur sort du maillage. Clés chargées automatiquement : chaque radio reçoit celles de son camp.",
                [[_p + "jam_tfar", "Brouillage des scripts TFAR", _yn select 0, _yn select 1], [_p + "zeus_crows", "Brouilleurs Crows Electronic Warfare", _yn select 0, _yn select 1], [_p + "mesh_keys", "Clés du maillage", _yn select 0, _yn select 1], [_p + "mesh_keys_auto", "Clés chargées automatiquement", _yn select 0, _yn select 1]]],
            ["Batterie", "La batterie du téléphone se vide à l'usage et se recharge dans un véhicule moteur allumé. À 0 %, le téléphone s'éteint. Les radios ACRE2 et TFAR n'ont pas de batterie chez nous.",
                [[_p + "battery_sim", "Batterie réaliste", _yn select 0, _yn select 1], [_p + "battery_hours", "Autonomie en main", [1, 3, 6, 12, 24], ["1 h", "3 h", "6 h", "12 h", "24 h"]], [_p + "battery_cold", "Froid : batterie moins efficace", _yn select 0, _yn select 1]]],
            ["Dégâts", "Le téléphone peut être abîmé par les balles, les éclats, les chutes et l'eau, selon l'endroit où il est porté. Réparation par action ACE. Saleté et sang se nettoient gratuitement.",
                [[_p + "damage_sim", "Dégâts du téléphone", _yn select 0, _yn select 1], [_p + "dirt_sim", "Saleté et sang sur l'écran", _yn select 0, _yn select 1]]]
        ]
    };
    case "open": {
        private _parent = _arg;
        if (isNull _parent) then { _parent = (allDisplays select { !isNull _x }) param [-1, displayNull]; };
        if (isNull _parent) exitWith {};
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith {};
        private _fields = ["fields"] call comspec_atak_native_fnc_setupWizard;
        private _rows = ["rows"] call comspec_atak_native_fnc_realismCatalog;
        private _start = createHashMap;
        private _locked = createHashMap;
        {
            {
                _x params ["_var", "", "_vals"];
                private _i = _rows findIf { (_x select 2) isEqualTo _var };
                private _cur = missionNamespace getVariable _var;
                if (_i >= 0) then {
                    private _st = ["state", _rows select _i] call comspec_atak_native_fnc_realismCatalog;
                    _cur = _st select 0;
                    if (_st select 4) then { _locked set [_var, _st select 2]; };
                };
                if (isNil "_cur") then { _cur = _vals select 0; };
                _start set [_var, _cur];
            } forEach (_x select 2);
        } forEach _fields;
        uiNamespace setVariable ["COMSPEC_ATAK_WizD", _d];
        uiNamespace setVariable ["COMSPEC_ATAK_WizS", createHashMapFromArray [["step", 0], ["start", _start], ["now", +_start], ["locked", _locked], ["fields", _fields], ["admin", [player] call comspec_atak_native_fnc_adminAllowed]]];
        ["draw"] call comspec_atak_native_fnc_setupWizard;
    };
    case "go": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_WizS", createHashMap];
        _s set ["step", ((_s getOrDefault ["step", 0]) + _arg) max 0];
        ["draw"] call comspec_atak_native_fnc_setupWizard;
    };
    case "draw": {
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_WizD", displayNull];
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_WizS", createHashMap];
        if (isNull _d || {_s isEqualTo createHashMap}) exitWith {};
        { ctrlDelete _x; } forEach (allControls _d);
        private _fields = _s get "fields";
        private _step = _s get "step";
        private _last = count _fields;
        private _admin = _s get "admin";
        // Même largeur réelle en 16:9 et en 21:9 : la largeur suit la hauteur de l'écran.
        private _w = (safeZoneH * 1.05) min (safeZoneW * 0.94);
        private _h = safeZoneH * 0.78;
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2;
        private _fh = safeZoneH * 0.024;
        private _line = safeZoneH * 0.05;
        private _mk = {
            params ["_cls", "_pos", ["_grp", controlNull]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
            _c ctrlSetPosition _pos;
            _c ctrlSetFontHeight _fh;
            _c ctrlCommit 0;
            _c
        };
        (["ctrlStatic", [safeZoneX, safeZoneY, safeZoneW, safeZoneH]] call _mk) ctrlSetBackgroundColor [0, 0, 0, 0.55];
        (["ctrlStatic", [_x0, _y0, _w, _h]] call _mk) ctrlSetBackgroundColor [0.12, 0.13, 0.12, 0.98];
        private _title = ["ctrlStatic", [_x0, _y0, _w, _line * 0.8]] call _mk;
        _title ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
        _title ctrlSetText (format ["%1 · %2 / %3", "Assistant de configuration" call _T, (_step + 1) min (_last + 1), _last + 1]);
        private _pad = _w * 0.02;
        private _gy = _y0 + _line;
        private _gh = _h - _line * 2.3;
        private _grp = ["ctrlControlsGroup", [_x0 + _pad, _gy, _w - 2 * _pad, _gh]] call _mk;
        private _gw = _w - 2 * _pad - safeZoneW * 0.012;
        private _cy = 0;
        // Texte de hauteur réelle : jamais coupé, le groupe défile s'il le faut.
        private _para = {
            params ["_html"];
            private _c = ["ctrlStructuredText", [0, _cy, _gw, _line], _grp] call _mk;
            _c ctrlSetStructuredText parseText _html;
            private _th = ctrlTextHeight _c;
            _c ctrlSetPosition [0, _cy, _gw, _th];
            _c ctrlCommit 0;
            _cy = _cy + _th + _line * 0.2;
        };
        private _mode = [("Ces choix iront dans votre profil personnel et ne valent que sur les parties que vous hébergez. Vous n'avez pas les droits de ce serveur : il ne change pas." call _T), ("Ces choix s'appliqueront à ce serveur, pour tous les joueurs." call _T)] select _admin;
        if (_step < _last) then {
            (_fields select _step) params ["_tt", "_txt", "_list"];
            [format ["<t size='1.2' color='#7ED38A'>%1</t><br/>%2<br/><t color='#9AA59C'>%3</t>", _tt call _T, _txt call _T, _mode]] call _para;
            private _lw = _gw * 0.55;
            {
                _x params ["_var", "_label", "_vals", "_names"];
                private _lock = (_s get "locked") getOrDefault [_var, ""];
                private _lb = ["ctrlStructuredText", [0, _cy, _lw, _line], _grp] call _mk;
                _lb ctrlSetStructuredText parseText (_label call _T);
                private _cb = ["ctrlCombo", [_lw, _cy, _gw - _lw, _line * 0.72], _grp] call _mk;
                private _now = (_s get "now") get _var;
                private _vv = +_vals; private _nn = +_names;
                if !(_now in _vv) then { _vv pushBack _now; _nn pushBack format ["%1 (%2)", _now, "actuel" call _T]; };
                { private _i = _cb lbAdd ((_nn select _forEachIndex) call _T); _cb lbSetValue [_i, _forEachIndex]; } forEach _vv;
                _cb lbSetCurSel (_vv find _now);
                _cb setVariable ["w", [_var, _vv]];
                _cb ctrlEnable (_lock isEqualTo "");
                _cb ctrlAddEventHandler ["LBSelChanged", {
                    params ["_c", "_i"];
                    (_c getVariable ["w", ["", []]]) params ["_var", "_vv"];
                    ((uiNamespace getVariable "COMSPEC_ATAK_WizS") get "now") set [_var, _vv select (_c lbValue _i)];
                }];
                _cy = _cy + _line;
                if (_lock isNotEqualTo "") then { [format ["<t color='#E0B050'>%1. %2</t>", _lock, "À changer sur le site, dans Contrôle serveur." call _T]] call _para; };
            } forEach _list;
        } else {
            // Résumé : ce qui va changer, avant toute application.
            private _rowsTxt = [];
            {
                {
                    _x params ["_var", "_label", "_vals", "_names"];
                    private _a = (_s get "start") get _var;
                    private _b = (_s get "now") get _var;
                    if (_a isEqualTo _b) then { continue };
                    private _nameOf = { params ["_v"]; private _i = _vals find _v; if (_i >= 0) then { (_names select _i) call _T } else { str _v } };
                    _rowsTxt pushBack format ["• %1 : %2 → %3", _label call _T, [_a] call _nameOf, [_b] call _nameOf];
                } forEach (_x select 2);
            } forEach _fields;
            [format ["<t size='1.2' color='#7ED38A'>%1</t><br/>%2", "Résultat" call _T, _mode]] call _para;
            [[("Rien ne change : les valeurs choisies sont déjà en place." call _T), _rowsTxt joinString "<br/>"] select (_rowsTxt isNotEqualTo [])] call _para;
            [format ["<t color='#9AA59C'>%1</t>", "Rien n'est appliqué tant que vous ne confirmez pas. Tout se règle ensuite dans Réalisme et réseau, ou dans les options CBA." call _T]] call _para;
        };
        // Boutons, toujours visibles sous le texte.
        private _bw = _w * 0.23;
        private _by = _y0 + _h - _line * 1.05;
        private _bt = {
            params ["_txt", "_bx", "_code", ["_on", true], ["_col", []]];
            private _c = ["ctrlButton", [_bx, _by, _bw, _line * 0.8]] call _mk;
            _c ctrlSetText (_txt call _T);
            _c ctrlEnable _on;
            if (_col isNotEqualTo []) then { _c ctrlSetBackgroundColor _col; };
            _c ctrlAddEventHandler ["ButtonClick", _code];
        };
        ["Annuler", _x0 + _pad, { (ctrlParent (_this select 0)) closeDisplay 2; }] call _bt;
        ["Précédent", _x0 + _w - _pad - 2 * _bw - _pad, { ["go", -1] call comspec_atak_native_fnc_setupWizard; }, _step > 0] call _bt;
        if (_step < _last) then {
            ["Suivant", _x0 + _w - _pad - _bw, { ["go", 1] call comspec_atak_native_fnc_setupWizard; }, true, [0.10, 0.40, 0.20, 1]] call _bt;
        } else {
            ["Confirmer", _x0 + _w - _pad - _bw, { ["apply"] call comspec_atak_native_fnc_setupWizard; (ctrlParent (_this select 0)) closeDisplay 1; }, true, [0.10, 0.40, 0.20, 1]] call _bt;
        };
    };
    case "apply": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_WizS", createHashMap];
        private _pairs = [];
        {
            private _a = _y;
            private _var = _x;
            if (_var in (_s getOrDefault ["locked", createHashMap])) then { continue };
            private _b = (_s get "now") get _var;
            if (_a isNotEqualTo _b) then { _pairs pushBack [_var, _b]; };
        } forEach (_s getOrDefault ["start", createHashMap]);
        if (_pairs isEqualTo []) exitWith { ["INFO", "Assistant : rien à changer", 4, 30] call comspec_atak_native_fnc_notify; };
        if ([player] call comspec_atak_native_fnc_adminAllowed) then {
            // Conditions appliquées en jeu effacées : elles masqueraient le profil choisi.
            ["comspec_atak_native_admin", [player, "realismSet", [_pairs, true]]] call CBA_fnc_serverEvent;
        } else {
            { [_x select 0, _x select 1, false, "client", true] call CBA_settings_fnc_set; } forEach _pairs;
            ["INFO", format ["Enregistré dans votre profil (%1 réglage(s)). Ce serveur ne change pas : il faut être admin, Zeus ou gestionnaire désigné.", count _pairs], 8, 30] call comspec_atak_native_fnc_notify;
        };
    };
};
