/*
    Relais ATAK en Zeus : modifier un relais déjà posé (pylône COMSPEC, module « ATAK · Relais », relais Overwatch).
      ["register"]                 : avec ZEN (XEH_postInitClient), ajoute les attributs du relais à la fenêtre
                                     « Éditer » de l'objet (double clic) : nom, fréquence, portée, débit, hauteur d'antenne,
                                     places, réseau du camp, liaison, alimentation, batterie, en service ; et, au clic droit
                                     sur un relais, « ATAK · Relais » : fiche, allumer / couper, recharger la batterie,
                                     réseau exclusivement par relais (toute la mission).
      ["set", [relais, clé, valeur]] : écrit une variable du relais pour tous (COMSPEC_AtakRelay<clé>) et recalcule la liste.
      ["only", vrai | faux]       : réseau exclusivement par relais dans toute la mission (COMSPEC_ATAK_RelayOnly).
    Sans ZEN : le module « ATAK · Relais » lâché sur un relais existant ouvre le même formulaire prérempli (fn_moduleRelay).
*/
params [["_act", ""], ["_arg", []]];
switch (_act) do {
    case "set": {
        _arg params [["_o", objNull], ["_k", ""], "_v"];
        if (isNull _o || {_k isEqualTo ""} || {isNil "_v"}) exitWith { false };
        _o setVariable [format ["COMSPEC_AtakRelay%1", _k], _v, true];
        missionNamespace setVariable ["COMSPEC_ATAK_RelayCache", nil, true];
        true
    };
    case "only": {
        missionNamespace setVariable ["COMSPEC_ATAK_RelayOnly", _arg isEqualTo true, true];
        ["feedback", [["Réseau exclusivement par relais : désactivé (le téléphone garde du réseau sans relais)", "Réseau exclusivement par relais : activé (sans relais, aucun réseau)"] select (_arg isEqualTo true)]] call comspec_atak_native_fnc_zeusMenu;
        true
    };
    case "register": {
        if (!hasInterface || {isNil "zen_attributes_fnc_addAttribute"} || {missionNamespace getVariable ["COMSPEC_ATAK_RelayZeusOn", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_RelayZeusOn", true];
        // Valeur appliquée au relais ouvert et aux autres relais sélectionnés dans Zeus.
        uiNamespace setVariable ["COMSPEC_ATAK_RelayZeusSet", {
            params ["_entity", "_k", "_v"];
            private _sel = ((curatorSelected select 0) select { _x getVariable ["COMSPEC_AtakRelay", false] });
            _sel pushBackUnique _entity;
            { ["set", [_x, _k, _v]] call comspec_atak_native_fnc_relayZeus; } forEach _sel;
        }];
        private _tr = { _this call comspec_atak_native_fnc_tr };
        private _attr = {
            params ["_label", "_tip", "_ctrl", "_info", "_key", "_get", ["_cond", {true}]];
            [
                "Object", [("Relais · " + _label) call _tr, _tip call _tr], _ctrl, _info,
                compile format ["[_entity, '%1', _value] call (uiNamespace getVariable 'COMSPEC_ATAK_RelayZeusSet');", _key],
                _get, compile format ["(_entity getVariable ['COMSPEC_AtakRelay', false]) && {call %1}", _cond]
            ] call zen_attributes_fnc_addAttribute;
        };
        private _num = { params ["_k", "_d"]; private _v = _entity getVariable [format ["COMSPEC_AtakRelay%1", _k], _d]; [_d, _v] select (_v isEqualType 0) };
        uiNamespace setVariable ["COMSPEC_ATAK_RelayZeusNum", _num];
        private _freqs = [[0, "Automatique (premier canal libre)"], [400, "UHF 400 MHz"], [1400, "L 1,4 GHz"], [2400, "S 2,4 GHz"], [4900, "C 4,9 GHz"]]
            + ([4940, 4945, 4950, 4955, 4960, 4965, 4970, 4975] apply { [_x, format ["C %1 MHz", _x]] });
        [
            ["Nom", "Nom affiché sur la carte et dans l'app Réseau des téléphones.", "zen_attributes_edit", [], "Name", { _entity getVariable ["COMSPEC_AtakRelayName", ""] }],
            ["En service", "Allumé ou coupé (un relais coupé ne porte plus le réseau).", "zen_attributes_combo", [[[true, "Allumé" call _tr], [false, "Coupé" call _tr]]], "On", { _entity getVariable ["COMSPEC_AtakRelayOn", true] }],
            ["Fréquence", "Canal radio. Deux relais voisins sur le même canal se brouillent : débit réduit, perte et latence en hausse.", "zen_attributes_combo", [_freqs apply { [_x select 0, (_x select 1) call _tr] }], "Freq", { ["Freq", 0] call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusNum") }],
            ["Portée (m)", "Rayon de réception des téléphones (réduit à 35 % derrière le relief).", "zen_attributes_slider", [100, 30000, 100, false, 0], "Range", { ["Range", 2000] call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusNum") }],
            ["Débit (Mbit/s)", "Débit offert par le relais avant dégâts, brouillage et saturation.", "zen_attributes_slider", [0.5, 50, 0.5, false, 1], "Throughput", { ["Throughput", 12] call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusNum") }],
            ["Hauteur d'antenne (m)", "Hauteur de l'antenne au-dessus du pied de l'objet (lignes de vue vers les téléphones).", "zen_attributes_slider", [1, 80, 1, false, 0], "Height", { ["Height", 4] call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusNum") }],
            ["Places", "Téléphones servis à plein débit ; au-delà, le débit se partage. 0 = illimité.", "zen_attributes_slider", [0, 128, 1, false, 0], "Slots", { ["Slots", 0] call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusNum") }],
            ["Réseau du camp", "Camp qui voit et utilise ce relais.", "zen_attributes_combo", [[["", "Tous les camps" call _tr], ["WEST", "BLUFOR"], ["EAST", "OPFOR"], ["GUER", "Indépendants" call _tr], ["CIV", "Civils" call _tr]]], "Side", { _entity getVariable ["COMSPEC_AtakRelaySide", ""] }],
            ["Liaison", "Raccordé : porte le réseau seul (satellite, fibre). Répéteur : doit voir un relais raccordé.", "zen_attributes_combo", [[[true, "Raccordé (satellite ou fibre)" call _tr], [false, "Répéteur" call _tr]]], "Root", { _entity getVariable ["COMSPEC_AtakRelayRoot", true] }],
            ["Alimentation", "Secteur : jamais à court. Batterie : se vide en service (autonomie pour 100 %).", "zen_attributes_combo", [[[0, "Secteur / groupe électrogène" call _tr], [60, "Batterie 1 h" call _tr], [120, "Batterie 2 h" call _tr], [240, "Batterie 4 h" call _tr], [480, "Batterie 8 h" call _tr], [720, "Batterie 12 h" call _tr], [1440, "Batterie 24 h" call _tr]]], "Autonomy", { ["Autonomy", 0] call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusNum") }],
            ["Batterie (%)", "Charge restante (relais sur batterie seulement). 0 = batterie vide, relais hors service.", "zen_attributes_slider", [0, 100, 1, false, 0], "Batt", { ["Batt", 100] call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusNum") }, "{ ((_entity getVariable ['COMSPEC_AtakRelayBatt', -1]) isEqualType 0) && {(_entity getVariable ['COMSPEC_AtakRelayBatt', -1]) >= 0} }"]
        ] apply {
            _x params ["_l", "_t", "_c", "_i", "_k", "_g", ["_cd", "{true}"]];
            [_l, _t, _c, _i, _k, _g, _cd] call _attr;
        };
        // Clic droit Zeus sur un relais : fiche, marche / arrêt, batterie, et le réglage de mission.
        if (isNil "zen_context_menu_fnc_addAction") exitWith {};
        private _relayOf = { params ["", ["_objs", []], "", "", "", ["_hover", objNull]];
            ((_objs + [_hover]) select { _x isEqualType objNull && {!isNull _x} && {_x getVariable ["COMSPEC_AtakRelay", false]} }) param [0, objNull] };
        uiNamespace setVariable ["COMSPEC_ATAK_RelayZeusOf", _relayOf];
        private _icon = "\z\comspec_atak_native\addons\main\data\app_waverelay.paa";
        private _root = ["comspec_atak_relay", "ATAK · Relais", _icon, {}, { !isNull (_this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf")) }] call comspec_atak_native_fnc_zenAction;
        [_root, [], 6] call zen_context_menu_fnc_addAction;
        {
            _x params ["_id", "_label", "_code", ["_cond", { true }]];
            private _a = [format ["comspec_atak_relay_%1", _id], _label, "", _code, _cond] call comspec_atak_native_fnc_zenAction;
            [_a, ["comspec_atak_relay"], 50 - _forEachIndex] call zen_context_menu_fnc_addAction;
        } forEach [
            ["info", "Fiche du relais", {
                private _o = _this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf");
                private _e = ["entry", _o] call comspec_atak_native_fnc_relayNet;
                if (_e isEqualTo []) then {
                    // Relais d'un autre camp : la liste du Zeus ne le contient pas, on lit ses variables.
                    ["feedback", [format ["%1 : %2 MHz, portée %3 m", _o getVariable ["COMSPEC_AtakRelayName", "Relais"], _o getVariable ["COMSPEC_AtakRelayFreq", 0], _o getVariable ["COMSPEC_AtakRelayRange", 0]]]] call comspec_atak_native_fnc_zeusMenu;
                } else {
                    _e params ["", "", "_n", "", "_r", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "", "", "_fq", "_auto", "_loss", "_hp", "_hl", "", "_co", "_lat"];
                    ["feedback", [format ["%1 · %2 · %3 MHz%4 · perte %5 %% · %6 ms · structure %7 %% · %8 Mbit/s · %9 tél.%10",
                        _n, [_hl, _st] select !_ok, _fq, ["", " (auto)"] select _auto, _loss, _lat, _hp, _mb toFixed 1, _u,
                        ["", format [" · %1 sur le même canal", _co]] select (_co > 0)]]] call comspec_atak_native_fnc_zeusMenu;
                };
            }],
            ["on", "Allumer", { ["set", [_this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf"), "On", true]] call comspec_atak_native_fnc_relayZeus; },
                { !((_this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf")) getVariable ["COMSPEC_AtakRelayOn", true]) }],
            ["off", "Couper", { ["set", [_this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf"), "On", false]] call comspec_atak_native_fnc_relayZeus; },
                { (_this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf")) getVariable ["COMSPEC_AtakRelayOn", true] }],
            ["batt", "Batterie neuve (100 %)", { ["set", [_this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf"), "Batt", 100]] call comspec_atak_native_fnc_relayZeus; },
                { private _b = (_this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf")) getVariable ["COMSPEC_AtakRelayBatt", -1]; _b isEqualType 0 && {_b >= 0} }],
            ["repair", "Réparer", { private _o = _this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf"); if (alive _o) then { [_o, 0] remoteExec ["setDamage", _o]; }; missionNamespace setVariable ["COMSPEC_ATAK_RelayCache", nil, true]; },
                { private _o = _this call (uiNamespace getVariable "COMSPEC_ATAK_RelayZeusOf"); alive _o && {damage _o > 0} }],
            ["onlyOn", "Réseau exclusivement par relais : activer", { ["only", true] call comspec_atak_native_fnc_relayZeus; },
                { !(missionNamespace getVariable ["COMSPEC_ATAK_RelayOnly", false]) }],
            ["onlyOff", "Réseau exclusivement par relais : désactiver", { ["only", false] call comspec_atak_native_fnc_relayZeus; },
                { missionNamespace getVariable ["COMSPEC_ATAK_RelayOnly", false] }]
        ];
    };
};
true
