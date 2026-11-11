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
            { _x ctrlShow true; } forEach _arbres;
        } else {
            "modules COMSPEC non trouvés dans les listes de l'écran Zeus : à voir sur la capture" call AT_fnc_visuel;
        };
        (findDisplay 312) closeDisplay 2;
        uiSleep 1;
        unassignCurator _cur;
        deleteVehicle _cur;
        call AT_fnc_done;
    }, 90] call AT_fnc_run;

    // Centre Zeus (4.2.2) : position hors de la liste de droite, aucun texte coupé, onglets, état du serveur, fenêtres
    // ouvertes devant le centre replié, étiquettes 3D, caméra, sirène d'alarme.
    ["zeus_centre", {
        if (["comspec_atak_native_fnc_zeusCentre", "comspec_atak_native_fnc_zeusState"] call AT_fnc_nil) exitWith {};
        private _g = createGroup sideLogic;
        private _cur = _g createUnit ["ModuleCurator_F", [0, 0, 0], [], 0, "NONE"];
        _cur setVariable ["Addons", 3, true];
        player assignCurator _cur;
        _cur addCuratorEditableObjects [[player], true];
        uiSleep 2;
        openCuratorInterface;
        if !([[{ !isNull findDisplay 312 }, 10] call AT_fnc_wait, "écran Zeus non ouvert"] call AT_fnc_check) exitWith { unassignCurator _cur; deleteVehicle _cur; call AT_fnc_done; };
        uiSleep 3;
        private _d = findDisplay 312;
        // Contrôles de droite de l'écran Zeus, pour comprendre la mesure du bord (type, position, visible).
        private _droite = (allControls _d) select { (ctrlType _x) in [5, 12, 102] && {((ctrlPosition _x) select 0) > safeZoneX + safeZoneW * 0.5} };
        (format ["contrôles de droite : %1", _droite apply { [ctrlType _x, ctrlIDC _x, (ctrlPosition _x) apply { round (_x * 1000) / 1000 }, ctrlShown _x, isNull ctrlParentControlsGroup _x] }]) call AT_fnc_info;
        private _bord = ["edge"] call comspec_atak_native_fnc_zeusCentre;
        (format ["bord gauche de la liste de droite : %1 (écran %2 à %3)", _bord, safeZoneX, safeZoneX + safeZoneW]) call AT_fnc_info;
        ["toggle"] call comspec_atak_native_fnc_zeusCentre;
        uiSleep 1;
        private _ui = uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreUi", createHashMap];
        if !([(count _ui) > 0, "Centre Zeus non ouvert"] call AT_fnc_check) exitWith { (findDisplay 312) closeDisplay 2; unassignCurator _cur; deleteVehicle _cur; call AT_fnc_done; };
        private _fond = (_ui get "all") select 0;
        (ctrlPosition _fond) params ["_fx", "_fy", "_fw", "_fh"];
        (format ["panneau : x %1 à %2, y %3 à %4 (h écran %5)", _fx, _fx + _fw, _fy, _fy + _fh, safeZoneY + safeZoneH]) call AT_fnc_info;
        [_fx + _fw <= _bord + 0.001, format ["le panneau recouvre la liste de droite de Zeus (bord droit %1 > bord de la liste %2)", _fx + _fw, _bord]] call AT_fnc_check;
        [_fy + _fh <= safeZoneY + 0.87 * safeZoneH, "le panneau descend sur la barre du bas"] call AT_fnc_check;
        // Un joueur fiché : en solo, le serveur et le joueur sont la même machine, la fiche arrive en quelques secondes.
        [{ (count (missionNamespace getVariable ["COMSPEC_ATAK_ZeusRows", createHashMap])) > 0 }, 12] call AT_fnc_wait;
        [(count (missionNamespace getVariable ["COMSPEC_ATAK_ZeusRows", createHashMap])) > 0, "fiche du joueur jamais reçue"] call AT_fnc_check;
        ["select", [getPlayerUID player]] call comspec_atak_native_fnc_zeusCentre;
        ["refresh", [true]] call comspec_atak_native_fnc_zeusCentre;
        uiSleep 0.5;
        private _carte = uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreCardText", ""];
        [(_carte find "Relevé reçu") >= 0, "fiche sans âge du relevé (« Relevé reçu … »)"] call AT_fnc_check;
        [(_carte find "Mod") >= 0, "fiche sans version du mod"] call AT_fnc_check;
        [(lbSize (_ui get "list")) > 0, "liste des joueurs vide"] call AT_fnc_check;
        // Aucun texte coupé : tous les boutons visibles du centre, onglet par onglet.
        private _verif = {
            params ["_nom"];
            {
                if ((ctrlType _x) isEqualTo 1 && {ctrlShown _x}) then { [_x, format ["bouton (%1)", _nom]] call AT_fnc_tient; };
            } forEach (_ui get "all");
            // Contrôle du Centre lui-même : bouton sans texte, chevauchement, texte coupé, contrôle hors du panneau.
            { [false, format ["onglet %1 : %2", _nom, _x]] call AT_fnc_check; } forEach (["audit"] call comspec_atak_native_fnc_zeusCentre);
        };
        ["joueurs"] call _verif;
        ["zeus_centre_joueurs"] call AT_fnc_shot;
        // Onglet SERVEUR : l'état arrive du serveur (même machine ici).
        ["tab", ["server"]] call comspec_atak_native_fnc_zeusCentre;
        [[{ (missionNamespace getVariable ["COMSPEC_ATAK_ZeusSrv", []]) isNotEqualTo [] }, 10] call AT_fnc_wait, "état du serveur jamais reçu"] call AT_fnc_check;
        uiSleep 2.5;
        private _srv = missionNamespace getVariable ["COMSPEC_ATAK_ZeusSrvText", ""];
        [(_srv find "FPS serveur") >= 0, "onglet SERVEUR sans FPS"] call AT_fnc_check;
        [(_srv find "VERSIONS") >= 0, "onglet SERVEUR sans versions"] call AT_fnc_check;
        [(_srv find "ATHENA") >= 0, "onglet SERVEUR sans liaison Athena"] call AT_fnc_check;
        private _srvMap = (missionNamespace getVariable ["COMSPEC_ATAK_ZeusSrv", [createHashMap, 0]]) select 0;
        (format ["état du serveur : fps %1, joueurs %2, IA %3, mods %4, athena %5, identité %6, mesh %7, log %8", _srvMap get "fps", _srvMap get "players", _srvMap get "ai", _srvMap get "mods", _srvMap get "athena", _srvMap get "identity", _srvMap get "mesh", count (_srvMap getOrDefault ["log", []])]) call AT_fnc_info;
        private _texte = missionNamespace getVariable ["COMSPEC_ATAK_ZeusSrvText", ""];
        [(_texte find "7656") < 0 && {(toLower _texte) find "token" < 0}, "état du serveur : UID ou jeton en clair"] call AT_fnc_check;
        ["serveur"] call _verif;
        ["zeus_centre_serveur"] call AT_fnc_shot;
        // Onglet ÉTIQUETTES : mode détaillé, caméra sur le joueur, capture des étiquettes 3D.
        ["tab", ["labels"]] call comspec_atak_native_fnc_zeusCentre;
        ["labelCfg", ["mode", "detailed"]] call comspec_atak_native_fnc_zeusCentre;
        if !(missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLabels", true]) then { ["labels"] call comspec_atak_native_fnc_zeusCentre; };
        ["étiquettes"] call _verif;
        private _avant = getPosASL curatorCamera;
        ["camera"] call comspec_atak_native_fnc_zeusCentre;
        uiSleep 1.5;
        private _apres = getPosASL curatorCamera;
        (format ["caméra Zeus : %1 -> %2 (joueur %3)", _avant, _apres, getPosASL player]) call AT_fnc_info;
        [(_apres distance (getPosASL player)) < 30, format ["caméra Zeus non placée sur le joueur (%1 m)", round (_apres distance (getPosASL player))]] call AT_fnc_check;
        private _marques = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreMarks", []];
        [(count _marques) > 0 && {(count ((_marques select 0) select 1)) >= 3}, format ["étiquette détaillée sans lignes : %1", _marques]] call AT_fnc_check;
        (format ["étiquette 3D du joueur : %1", (_marques param [0, [objNull, []]]) select 1]) call AT_fnc_info;
        ["zeus_centre_etiquettes_detail"] call AT_fnc_shot;
        ["labelCfg", ["mode", "compact"]] call comspec_atak_native_fnc_zeusCentre;
        uiSleep 2.5;
        ["zeus_centre_etiquettes_compact"] call AT_fnc_shot;
        // Onglet OUTILS, puis chaque fenêtre ouverte depuis le centre : le centre se replie, la fenêtre est seule, il revient.
        ["tab", ["tools"]] call comspec_atak_native_fnc_zeusCentre;
        ["outils"] call _verif;
        ["zeus_centre_outils"] call AT_fnc_shot;
        private _fenetre = {
            params ["_nom", "_code", ["_attente", 2]];
            private _avantD = allDisplays select { !isNull _x };
            ["tool", [_code]] call comspec_atak_native_fnc_zeusCentre;
            uiSleep _attente;
            private _nouveaux = allDisplays select { private _dd = _x; !isNull _dd && {(_avantD findIf { _x isEqualTo _dd }) < 0} };
            private _visibles = { ctrlShown _x } count (_ui get "all");
            (format ["%1 : nouveaux affichages %2, contrôles du centre visibles %3, dialogue %4", _nom, _nouveaux, _visibles, dialog]) call AT_fnc_info;
            if (_nouveaux isEqualTo [] && {!dialog}) then {
                [false, format ["%1 : aucune fenêtre ouverte", _nom]] call AT_fnc_check;
            } else {
                [_visibles isEqualTo 0, format ["%1 : le centre reste affiché (%2 contrôles) devant la fenêtre", _nom, _visibles]] call AT_fnc_check;
                [format ["zeus_fenetre_%1", _nom]] call AT_fnc_shot;
                { _x closeDisplay 2; } forEach _nouveaux;
                if (dialog) then { closeDialog 0; };
                if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_close; };
                uiSleep 1;
                [({ ctrlShown _x } count (_ui get "all")) > 0, format ["%1 : le centre ne revient pas après la fermeture", _nom]] call AT_fnc_check;
            };
        };
        if (!isNil "comspec_sse_fnc_chartOpen") then { ["liens", { [true] call comspec_sse_fnc_chartOpen; }] call _fenetre; };
        if (!isNil "comspec_sse_fnc_editor") then { ["editeur", { ["open", [getPosATL player, objNull]] call comspec_sse_fnc_editor; }, 3] call _fenetre; };
        if (!isNil "comspec_sse_fnc_aiConfigOpen") then {
            private _civ = (createGroup civilian) createUnit ["C_man_1", player getRelPos [6, 0], [], 0, "NONE"];
            _cur addCuratorEditableObjects [[_civ], true];
            ["ia", { [[(allUnits select { !isPlayer _x && {_x isKindOf "C_man_1"} }) param [0, objNull]], getPosATL player] call comspec_sse_fnc_aiConfigOpen; }, 3] call _fenetre;
            deleteVehicle _civ;
        };
        if (!isNil "comspec_sse_fnc_openGuide") then { ["manuel", { ["Zeus"] call comspec_sse_fnc_openGuide; }] call _fenetre; };
        ["admin", { ["admin"] call comspec_atak_native_fnc_zeusCentre; }, 3] call _fenetre;
        // Sirène d'alarme : relancée toutes les 2 s tant que l'alarme court et que le téléphone sert.
        ["once", ["noise", [5]]] call comspec_atak_native_fnc_zeusClient;
        private _t1 = uiNamespace getVariable ["COMSPEC_ATAK_ZeusNoiseAt", -10];
        uiSleep 2.5;
        ["tick"] call comspec_atak_native_fnc_zeusClient;
        private _t2 = uiNamespace getVariable ["COMSPEC_ATAK_ZeusNoiseAt", -10];
        [_t2 > _t1, "sirène d'alarme non relancée après 2 s"] call AT_fnc_check;
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusNoise", 0];
        ["tab", ["players"]] call comspec_atak_native_fnc_zeusCentre;
        ["close"] call comspec_atak_native_fnc_zeusCentre;
        [(count (uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreUi", createHashMap])) isEqualTo 0, "centre non fermé"] call AT_fnc_check;
        (findDisplay 312) closeDisplay 2;
        uiSleep 1;
        unassignCurator _cur;
        deleteVehicle _cur;
        call AT_fnc_done;
    }, 150] call AT_fnc_run;
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
