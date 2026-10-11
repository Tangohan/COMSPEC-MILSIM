/*
    App Accessoires (établi, fn_pageAccessories) : état des emplacements et actions.
    Aucun état propre : tout est lu et modifié par les fonctions existantes (fn_antenna, fn_powerBank, fn_irBeacon,
    fn_screenGlow, fn_batteryItem / fn_batterySwap, fn_dataAction pour le câble DL-7).
    Params : [action, arguments]
      "slots"                     -> liste de hashmaps, une par emplacement :
            id, title, sub, anchor [x, y] (pixels de la texture du dos 2048), mounted, icon, name, state, stateHex,
            cand [[nom, icône, nombre, argument]...] (objets compatibles de l'inventaire), remove (libellé ou ""),
            verbs [[libellé, verbe]...] (boutons propres à l'emplacement)
      "do", [id, verbe, argument] : verbes "fix" (argument : objet choisi), "remove", et ceux de verbs.
      "refresh" : redessine la page si elle est affichée.
*/
params [["_act", "slots"], ["_a", []]];
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _inv = items player;
private _cnt = { params ["_c"]; { _x isEqualTo _c } count _inv };
private _cfgName = {
    params ["_c"];
    private _n = getText (configFile >> "CfgWeapons" >> _c >> "displayName");
    if (_n isEqualTo "") then { _n = getText (configFile >> "CfgMagazines" >> _c >> "displayName"); };
    [_n, _c] select (_n isEqualTo "")
};
switch (_act) do {
    case "slots": {
        private _out = [];
        private _mk = {
            params ["_id", "_title", "_sub", "_anchor"];
            createHashMapFromArray [["id", _id], ["title", _title], ["sub", _sub], ["anchor", _anchor], ["mounted", false], ["icon", ""], ["name", "vide"],
                ["state", ""], ["stateHex", "#8a9a93"], ["cand", []], ["remove", ""], ["verbs", []]]
        };

        // Antenne vissée sur le port SMA (fn_antenna).
        private _s = ["antenna", "Antenne", "port SMA", [1618, 700]] call _mk;
        private _ant = ["get"] call comspec_atak_native_fnc_antenna;
        if (_ant isNotEqualTo []) then {
            _ant params ["_aid", "", "_aname", "_gain"];
            _s set ["mounted", true];
            _s set ["icon", format ["%1item_antenna_%2.paa", _dir, _aid]];
            _s set ["name", _aname];
            _s set ["state", format ["débit x%1", _gain]];
            _s set ["stateHex", "#5cc76b"];
            _s set ["remove", "DÉVISSER"];
        } else {
            _s set ["icon", _dir + "item_antenna_stub.paa"];
            _s set ["state", "port SMA bouché"];
        };
        private _cur = _ant param [0, ""];
        _s set ["cand", ((["owned"] call comspec_atak_native_fnc_antenna) select { (_x select 0) isNotEqualTo _cur }) apply {
            [_x select 2, format ["%1item_antenna_%2.paa", _dir, _x select 0], [_x select 1] call _cnt, _x select 0]
        }];
        _out pushBack _s;

        // Balise IR intégrée à la coque (fn_irBeacon).
        _s = ["ir", "Balise IR", "coque", [1327, 776]] call _mk;
        private _irOn = ["state"] call comspec_atak_native_fnc_irBeacon;
        _s set ["mounted", true];
        _s set ["icon", "\a3\ui_f\data\igui\cfg\simpletasks\types\target_ca.paa"];
        _s set ["name", "Balise IR intégrée"];
        _s set ["state", ["éteinte", "allumée · visible aux JVN"] select _irOn];
        _s set ["stateHex", ["#8a9a93", "#f2ab33"] select _irOn];
        _s set ["verbs", [[["ALLUMER", "ÉTEINDRE"] select _irOn, "toggle"]]];
        _out pushBack _s;

        // Batterie de rechange (même recherche que l'action ACE « Changer la batterie » : fn_batteryItem).
        _s = ["spare", "Batterie de rechange", "logement", [700, 860]] call _mk;
        private _spare = [player] call comspec_atak_native_fnc_batteryItem;
        private _lvl = round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
        _s set ["icon", _dir + "item_battery.paa"];
        if (_spare isNotEqualTo "") then {
            _s set ["mounted", true];
            _s set ["name", [_spare] call _cfgName];
            _s set ["state", format ["x%1 · téléphone %2 %%", [_spare] call _cnt, _lvl]];
            _s set ["stateHex", ["#5cc76b", "#f2ab33"] select (_lvl < 30)];
            _s set ["verbs", [["CHANGER LA BATTERIE", "swap"]]];
        } else {
            _s set ["state", format ["aucune · téléphone %1 %%", _lvl]];
            _s set ["stateHex", ["#8a9a93", "#e5483a"] select (_lvl < 15)];
        };
        _out pushBack _s;

        // Câble et module DL-7 (app Extraction) : porté ou branché.
        _s = ["dl7", "Module DL-7", "connecteur", [290, 1100]] call _mk;
        private _hasCable = "COMSPEC_ATAK_DataCable" in _inv;
        private _plugged = ((uiNamespace getVariable ["COMSPEC_ATAK_DataLink", createHashMap]) getOrDefault ["phase", ""]) isNotEqualTo "";
        _s set ["icon", _dir + "item_datacable.paa"];
        if (_hasCable || _plugged) then {
            _s set ["mounted", true];
            _s set ["name", ["COMSPEC_ATAK_DataCable"] call _cfgName];
            private _tint = createHashMapFromArray [["olive", "olive"], ["black", "noir"], ["tan", "tan"]] getOrDefault [profileNamespace getVariable ["COMSPEC_ATAK_ModuleTint", "olive"], "olive"];
            _s set ["state", format [["rangé · module %1", "branché · extraction en cours"] select _plugged, _tint]];
            _s set ["stateHex", ["#5cc76b", "#7fb6e6"] select _plugged];
            _s set ["verbs", [[["OUVRIR EXTRACTION", "open"]], [["DÉBRANCHER", "unplug"], ["OUVRIR EXTRACTION", "open"]]] select _plugged];
        } else {
            _s set ["state", "câble absent de l'équipement"];
        };
        _out pushBack _s;

        // Filtre d'écran JVN (fn_screenGlow, profil COMSPEC_ATAK_NvgFilter).
        _s = ["filter", "Filtre d'écran JVN", "écran", [480, 1300]] call _mk;
        private _fOn = ["filter"] call comspec_atak_native_fnc_screenGlow;
        private _fItem = "COMSPEC_ATAK_NvgFilter";
        _s set ["icon", _dir + "item_nvgfilter.paa"];
        if (_fOn) then {
            _s set ["mounted", true];
            _s set ["name", [_fItem] call _cfgName];
            _s set ["state", "posé · lueur de l'écran -88 %"];
            _s set ["stateHex", "#5cc76b"];
            _s set ["remove", "RETIRER"];
        } else {
            _s set ["state", ["non posé", "posé mais absent de l'inventaire"] select (profileNamespace getVariable ["COMSPEC_ATAK_NvgFilter", false])];
            if ((_fItem in _inv) && {!(profileNamespace getVariable ["COMSPEC_ATAK_NvgFilter", false])}) then {
                _s set ["cand", [[[_fItem] call _cfgName, _dir + "item_nvgfilter.paa", [_fItem] call _cnt, _fItem]]];
            };
        };
        _out pushBack _s;

        // Batterie externe sur la plaque (fn_powerBank).
        _s = ["powerbank", "Batterie externe", "plaque", [1025, 1180]] call _mk;
        private _pbItem = "COMSPEC_ATAK_PowerBank";
        _s set ["icon", _dir + "item_powerbank.paa"];
        if (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false]) then {
            private _ch = round (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankCharge", 100]);
            _s set ["mounted", true];
            _s set ["name", [_pbItem] call _cfgName];
            _s set ["state", format ["%1 %% · recharge 2 %%/min", _ch]];
            _s set ["stateHex", ["#5cc76b", "#f2ab33"] select (_ch < 25)];
            _s set ["remove", "RETIRER"];
        } else {
            _s set ["state", "plaque libre"];
            if (["has"] call comspec_atak_native_fnc_powerBank) then {
                _s set ["cand", [[[_pbItem] call _cfgName, _dir + "item_powerbank.paa", [_pbItem] call _cnt, _pbItem]]];
            };
        };
        _out pushBack _s;
        _out
    };
    case "do": {
        _a params [["_id", ""], ["_verb", ""], ["_arg", ""]];
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _redraw = true;
        switch (_id) do {
            case "antenna": {
                if (_verb isEqualTo "fix") then { ["mount", _arg] call comspec_atak_native_fnc_antenna; };
                if (_verb isEqualTo "remove") then {
                    ["unmount"] call comspec_atak_native_fnc_antenna;
                    playSound "ClickSoft";
                    ["INFO", "Antenne dévissée : port SMA bouché", 3, 10] call comspec_atak_native_fnc_notify;
                };
            };
            case "ir": { if (_verb isEqualTo "toggle") then { ["toggle"] call comspec_atak_native_fnc_irBeacon; }; };
            case "spare": {
                if (_verb isEqualTo "swap") then {
                    // Même code que l'action ACE « Changer la batterie » (XEH_postInitClient).
                    if (isNil "ace_common_fnc_progressBar") then {
                        [] call comspec_atak_native_fnc_batterySwap;
                    } else {
                        [5, [], { [] call comspec_atak_native_fnc_batterySwap; ["refresh"] call comspec_atak_native_fnc_accessoriesAction; }, {}, "Changement de la batterie…"] call ace_common_fnc_progressBar;
                    };
                };
            };
            case "dl7": {
                if (_verb isEqualTo "unplug") then { ["disconnect"] call comspec_atak_native_fnc_dataAction; };
                if (_verb isEqualTo "open") then { _redraw = false; _st set ["accSlot", ""]; ["DATALINK"] call comspec_atak_native_fnc_navigate; };
            };
            case "filter": {
                // fn_screenGlow « toggle » : pose si absent du profil, retire sinon.
                private _on = profileNamespace getVariable ["COMSPEC_ATAK_NvgFilter", false];
                if ((_verb isEqualTo "fix" && {!_on}) || {_verb isEqualTo "remove" && {_on}}) then { ["toggle"] call comspec_atak_native_fnc_screenGlow; };
            };
            case "powerbank": {
                if (_verb isEqualTo "fix") then { ["attach"] call comspec_atak_native_fnc_powerBank; };
                if (_verb isEqualTo "remove") then { ["detach"] call comspec_atak_native_fnc_powerBank; };
            };
        };
        if (_redraw) then { _st set ["accSlot", ""]; ["refresh"] call comspec_atak_native_fnc_accessoriesAction; };
        true
    };
    // Redessine l'établi à l'image suivante s'il est toujours affiché.
    case "refresh": {
        [{
            if (!isNull ([] call comspec_atak_native_fnc_display)
                && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isEqualTo "ACCESSORIES"}) then {
                ["ACCESSORIES"] call comspec_atak_native_fnc_pageRender;
            };
        }] call CBA_fnc_execNextFrame;
        true
    };
    default { [] };
};
