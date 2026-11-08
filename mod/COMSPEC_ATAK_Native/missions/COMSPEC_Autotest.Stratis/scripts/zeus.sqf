// Zeus : écran du curateur, modules du mod, Centre Zeus. ACRE : aucune écriture, lectures seulement.
if ("zeus" call AT_fnc_groupe) then {
    ["zeus_modules_centre", {
        private _modules = ("((configName _x) select [0, 20]) isEqualTo 'COMSPEC_ATAK_Module_' && {(getNumber (_x >> 'scopeCurator')) > 1}" configClasses (configFile >> "CfgVehicles")) apply { configName _x };
        (format ["%1 modules ATAK pour Zeus : %2", count _modules, _modules joinString ", "]) call AT_fnc_info;
        [(count _modules) > 0, "aucun module ATAK déclaré pour Zeus"] call AT_fnc_check;
        {
            private _f = getText (configFile >> "CfgVehicles" >> _x >> "function");
            [_f isEqualTo "" || {!isNil _f}, format ["module %1 : fonction %2 absente", _x, _f]] call AT_fnc_check;
        } forEach _modules;
        private _g = createGroup sideLogic;
        private _cur = _g createUnit ["ModuleCurator_F", [0, 0, 0], [], 0, "NONE"];
        _cur setVariable ["Addons", 3, true];
        player assignCurator _cur;
        _cur addCuratorEditableObjects [[player], true];
        uiSleep 2;
        openCuratorInterface;
        if !([[{ !isNull findDisplay 312 }, 10] call AT_fnc_wait, "écran Zeus non ouvert"] call AT_fnc_check) exitWith { unassignCurator _cur; deleteVehicle _cur; call AT_fnc_done; };
        uiSleep 3;
        ["zeus_ecran"] call AT_fnc_shot;
        // Listes de l'écran Zeus : cherche nos modules dans les arbres (chargés au premier affichage).
        private _compte = {
            params ["_t", "_chemin"];
            private _n = 0;
            for "_i" from 0 to ((_t tvCount _chemin) - 1) do {
                private _p = _chemin + [_i];
                private _txt = toUpper (_t tvText _p);
                if (((_txt find "ATAK") >= 0) || {(_txt find "COMSPEC") >= 0} || {(_txt find "SSE") >= 0}) then { _n = _n + 1; };
                _n = _n + ([_t, _p] call _compte);
            };
            _n
        };
        private _arbres = (allControls findDisplay 312) select { (ctrlType _x) isEqualTo 12 };
        private _vus = _arbres apply { [[_x, []] call _compte, _x] };
        _vus sort false;
        (format ["arbres Zeus : %1, entrées COMSPEC trouvées : %2", count _arbres, _vus apply { _x select 0 }]) call AT_fnc_info;
        if (_vus isNotEqualTo [] && {((_vus select 0) select 0) > 0}) then {
            { _x ctrlShow false; } forEach _arbres;
            private _t = (_vus select 0) select 1;
            _t ctrlShow true;
            tvExpandAll _t;
            uiSleep 1;
            ["zeus_modules"] call AT_fnc_shot;
        } else {
            "modules COMSPEC non trouvés dans les listes de l'écran Zeus : à voir sur la capture" call AT_fnc_visuel;
        };
        if (isNil "comspec_atak_native_fnc_zeusCentre") then {
            "Centre Zeus absent de ce build (feat/atak-zeus-etat non fusionnée)" call AT_fnc_info;
        } else {
            ["toggle"] call comspec_atak_native_fnc_zeusCentre;
            uiSleep 4;
            ["zeus_centre"] call AT_fnc_shot;
            ["close"] call comspec_atak_native_fnc_zeusCentre;
        };
        (findDisplay 312) closeDisplay 2;
        uiSleep 1;
        unassignCurator _cur;
        deleteVehicle _cur;
        call AT_fnc_done;
    }, 90] call AT_fnc_run;
};

if ("acre" call AT_fnc_groupe) then {
    ["acre_gardefous", {
        if !(isClass (configFile >> "CfgPatches" >> "acre_main")) exitWith { "ACRE non chargé" call AT_fnc_skip; };
        if (["comspec_atak_native_fnc_mpuVoice"] call AT_fnc_nil) exitWith {};
        // Voix par le maillage coupée par défaut : notre fonction de signal ne doit pas être installée.
        [!((missionNamespace getVariable ["comspec_atak_native_mpu_voice", false]) isEqualTo true), "voix par le maillage active par défaut"] call AT_fnc_check;
        [isNil { missionNamespace getVariable "COMSPEC_ATAK_AcreSigPrev" }, "fonction de signal ACRE chaînée alors que la voix est coupée"] call AT_fnc_check;
        // Fonction pure, sans écrire dans ACRE : aucun gain sans lien, ni au niveau 3 de la gravité.
        [(["sigMap", [0.2, -110, -1, 0, true]] call comspec_atak_native_fnc_mpuVoice) isEqualTo [0.2, -110], "sigMap change le signal sans lien"] call AT_fnc_check;
        [(["sigMap", [0.2, -110, 2, 3, true]] call comspec_atak_native_fnc_mpuVoice) isEqualTo [0.2, -110], "sigMap change le signal au niveau 3"] call AT_fnc_check;
        (format ["radios ACRE vues par le mod (lecture) : %1", ["radios"] call comspec_atak_native_fnc_mpuVoice]) call AT_fnc_info;
        "pas de son dans ce test ; présence de TeamSpeak : voir le rapport" call AT_fnc_info;
        call AT_fnc_done;
    }, 30] call AT_fnc_run;
};
