/*
    Mesure des performances (réglage CBA comspec_atak_native_perf_log, « ATAK · Debug », décoché par défaut) : lignes
    [COMSPEC perf] dans le journal du jeu (RPT), jamais à l'écran. Pour envoyer un journal au lieu de deviner (TTRD, 11/11 :
    « il a du mal à s'ouvrir, il lag, ça rame »).
    Écrit :
      - ouverture du téléphone : de l'appui (Ctrl+U, Ctrl+Maj+U...) à la première image affichée, avec ses étapes
        (création de l'écran, scripts d'ouverture, première page, premier dessin de la carte) ;
      - ouverture d'une app : de l'appui à la page dessinée, puis à l'image affichée ;
      - carte, toutes les 5 s : coût du dessin par image (moyenne, pire), ce qui est dessiné (unités, marqueurs, étiquettes,
        tuiles du fond photo) ;
      - toutes les 10 s : images par seconde, temps du mod par seconde (boucles CBA chronométrées par fn_profiler, lancé
        ici s'il ne tourne pas, carte, rappels de la DLL), tâches les plus coûteuses, contrôles du téléphone, boucles CBA
        et scripts en file ; appels de DLL de plus de 5 ms (relevé de la DLL, Perf.Stats, et appels directs du mod).
    Arrêté : aucun coût, une seule lecture de COMSPEC_ATAK_PerfLog là où le mod se chronomètre déjà.
    Params : ["start"] | ["stop"] | ["key"] (appui d'une touche du téléphone) | ["openStart", en main] | ["phase", [étape, t0]]
      | ["app", [page, t0]] (page dessinée) | ["acc", [clé, durée]] (fn_perfAcc) | ["ext", [DLL, commande, t0]] | ["frame"]
*/
params [["_mode", ""], ["_a", []]];
if (!hasInterface) exitWith { false };
private _s = missionNamespace getVariable ["COMSPEC_ATAK_PerfLogS", createHashMap];
private _ms = { round (_this * 1000) };
switch (_mode) do {
    case "start": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_PerfLog", false]) exitWith { true };
        _s = createHashMapFromArray [
            ["win", [0, 0, 0, 0, diag_tickTime]], ["acc", createHashMap], ["map", [0, 0, 0, diag_tickTime]],
            ["open", []], ["app", []], ["extN", 0], ["key", -10]
        ];
        missionNamespace setVariable ["COMSPEC_ATAK_PerfLogS", _s];
        // Boucles CBA : chronométrées par le profilage (fn_profiler), lancé ici s'il ne tourne pas déjà, arrêté avec la mesure.
        private _byUs = !(missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]);
        if (_byUs) then { ["start"] call comspec_atak_native_fnc_profiler; };
        _s set ["profByUs", _byUs && {missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]}];
        _s set ["pfh", [{ ["frame"] call comspec_atak_native_fnc_perfLog; }, 0] call CBA_fnc_addPerFrameHandler];
        // Compteurs internes des DLL remis à zéro : relus toutes les 10 s.
        ["reset"] call comspec_atak_native_fnc_perfDll;
        missionNamespace setVariable ["COMSPEC_ATAK_PerfLog", true];
        diag_log format ["[COMSPEC perf] Mesure activée (%1) : ouverture du téléphone et des apps, carte toutes les 5 s, bilan toutes les 10 s", missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", ""]];
        true
    };
    case "stop": {
        if !(missionNamespace getVariable ["COMSPEC_ATAK_PerfLog", false]) exitWith { true };
        missionNamespace setVariable ["COMSPEC_ATAK_PerfLog", false];
        [_s getOrDefault ["pfh", -1]] call CBA_fnc_removePerFrameHandler;
        if ((_s getOrDefault ["profByUs", false]) && {!(missionNamespace getVariable ["comspec_atak_native_profiling", false])}) then { ["stop"] call comspec_atak_native_fnc_profiler; };
        diag_log "[COMSPEC perf] Mesure arrêtée";
        true
    };
    // Touche du téléphone (fn_hudToggle, fn_interactToggle) : départ du chronomètre d'ouverture.
    case "key": { _s set ["key", diag_tickTime]; };
    case "openStart": {
        private _t = _s getOrDefault ["key", -10];
        if (diag_tickTime - _t > 1) then { _t = diag_tickTime; };
        _s set ["key", -10];
        // [appui, image, mode, étapes [nom, ms], premier dessin de la carte (s, -1 : pas encore)]
        _s set ["open", [_t, diag_frameNo, ["miniature", "en main"] select (_a isEqualTo true), [], -1]];
    };
    case "phase": {
        _a params ["_name", "_t0"];
        private _o = _s getOrDefault ["open", []];
        if ((count _o) > 0) then { (_o select 3) pushBack [_name, (diag_tickTime - _t0) call _ms]; };
    };
    case "app": {
        _a params ["_page", "_t0"];
        _s set ["app", [_page, _t0, diag_frameNo, (diag_tickTime - _t0) call _ms]];
    };
    case "acc": {
        _a params ["_k", "_dt"];
        private _acc = _s getOrDefault ["acc", createHashMap];
        private _c = _acc getOrDefault [_k, []];
        if ((count _c) isEqualTo 0) then { _c = [0, 0, 0]; _acc set [_k, _c]; };
        _c set [0, (_c select 0) + 1];
        _c set [1, (_c select 1) + _dt];
        if (_dt > (_c select 2)) then { _c set [2, _dt]; };
        if (_k isEqualTo "carte (image)") then {
            private _m = _s getOrDefault ["map", [0, 0, 0, diag_tickTime]];
            _m set [0, (_m select 0) + 1];
            _m set [1, (_m select 1) + _dt];
            if (_dt > (_m select 2)) then { _m set [2, _dt]; };
            private _o = _s getOrDefault ["open", []];
            if ((count _o) > 0 && {(_o select 4) < 0}) then { _o set [4, _dt]; };
        };
    };
    // Appel de DLL du mod (fn_extensionCall, fn_extAwait) : le jeu attend la réponse ; noté au-delà de 5 ms, 20 par bilan.
    case "ext": {
        _a params ["_ext", "_cmd", "_t0"];
        private _dt = diag_tickTime - _t0;
        if (_dt <= 0.005 || {(_s getOrDefault ["extN", 0]) >= 20}) exitWith {};
        _s set ["extN", (_s getOrDefault ["extN", 0]) + 1];
        diag_log format ["[COMSPEC perf] Appel de DLL %1 %2 : %3 ms (le jeu attend la réponse)", _ext, _cmd, _dt call _ms];
    };
    case "frame": {
        private _now = diag_tickTime;
        private _dt = diag_deltaTime;
        private _w = _s getOrDefault ["win", [0, 0, 0, 0, _now]];
        _w set [0, (_w select 0) + 1];
        _w set [1, (_w select 1) + _dt];
        _w set [2, (_w select 2) max _dt];
        if (_dt > 0.05) then { _w set [3, (_w select 3) + 1]; };
        private _d = [] call comspec_atak_native_fnc_display;
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _page = _st getOrDefault ["activePage", ""];
        // Ouverture : image suivante celle de l'ouverture, donc après le dessin de la première image.
        private _o = _s getOrDefault ["open", []];
        if ((count _o) > 0 && {diag_frameNo > (_o select 1)}) then {
            _s set ["open", []];
            _o params ["_t0", "", "_how", "_ph", "_mapDt"];
            diag_log format ["[COMSPEC perf] Ouverture du téléphone (%1) : %2 ms de l'appui à la première image affichée (dernière image %3 ms) ; %4 ; page %5%6 ; %7 contrôles",
                _how, (_now - _t0) call _ms, _dt call _ms,
                [(_ph apply { format ["%1 %2 ms", _x select 0, _x select 1] }) joinString ", ", "étapes non mesurées"] select ((count _ph) isEqualTo 0),
                _page, ["", format [" (premier dessin de la carte %1 ms)", _mapDt call _ms]] select (_mapDt >= 0),
                [count (allControls _d), 0] select (isNull _d)];
        };
        private _ap = _s getOrDefault ["app", []];
        if ((count _ap) > 0 && {diag_frameNo > (_ap select 2)}) then {
            _s set ["app", []];
            _ap params ["_pg", "_t0", "", "_drawn"];
            diag_log format ["[COMSPEC perf] App %1 : %2 ms de l'appui à la page dessinée, %3 ms jusqu'à l'image affichée", _pg, _drawn, (_now - _t0) call _ms];
        };
        // Carte : bilan toutes les 5 s (rien si elle n'a pas été dessinée).
        private _m = _s getOrDefault ["map", [0, 0, 0, _now]];
        if (_now - (_m select 3) >= 5) then {
            if ((_m select 0) > 0) then {
                private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
                private _lbl = uiNamespace getVariable ["COMSPEC_ATAK_LblOut", []];
                private _gm = (missionNamespace getVariable ["COMSPEC_ATAK_GmList", [0, []]]) param [1, []];
                (uiNamespace getVariable ["COMSPEC_ATAK_PhotoLast", []]) params [["_tShow", 0], ["_tPre", 0], ["_tNew", 0], ["_zShow", -1], ["_zWant", -1]];
                private _style = [] call comspec_atak_native_fnc_mapStyle;
                diag_log format ["[COMSPEC perf] Carte 5 s : %1 images dessinées, moy %2 ms, pire %3 ms par image ; échelle %4 ; unités %5, marqueurs de mission %6, étiquettes %7 (demandées %8) ; fond %9%10",
                    _m select 0, ((_m select 1) / ((_m select 0) max 1) * 1000) toFixed 2, ((_m select 2) * 1000) toFixed 1,
                    [(ctrlMapScale ([] call comspec_atak_native_fnc_mapCtrl)) toFixed 3, "-"] select (isNull _d),
                    count (_data getOrDefault ["units", createHashMap]), { (count _x) > 0 } count _gm,
                    count (_lbl param [2, []]), _lbl param [5, 0], _style,
                    ["", format [" : tuiles %1 affichées, %2 en chargement, %3 neuves restantes, niveau %4 sur %5", _tShow, _tPre, _tNew, _zShow, _zWant]] select (_style in ["PHOTO", "SAT"])];
            };
            _s set ["map", [0, 0, 0, _now]];
        };
        // Bilan des 10 s.
        if (_now - (_w select 4) >= 10) then {
            _w params ["_n", "_sum", "_worst", "_slow", "_wt0"];
            private _span = (_now - _wt0) max 0.001;
            _s set ["win", [0, 0, 0, 0, _now]];
            private _acc = _s getOrDefault ["acc", createHashMap];
            _s set ["acc", createHashMap];
            _s set ["extN", 0];
            // Sans double compte : boucles CBA (fn_profiler), dessin de la carte, rappels de la DLL ; les autres clés sont
            // mesurées à l'intérieur de celles-ci.
            private _loops = 0; private _mapT = 0; private _cb = 0;
            {
                private _t = _y select 1;
                if ((_x select [0, 4]) isEqualTo "PFH ") then { _loops = _loops + _t; };
                if ((_x select [0, 11]) isEqualTo "Rappel DLL ") then { _cb = _cb + _t; };
                if (_x isEqualTo "carte (image)") then { _mapT = _t; };
            } forEach _acc;
            private _rows = (keys _acc) select { (_x select [0, 6]) isNotEqualTo "[mod] " } apply { [(_acc get _x) select 1, _x] };
            _rows sort false;
            private _top = (_rows select [0, 8]) apply {
                private _c = _acc get (_x select 1);
                format ["%1 %2× total %3 ms max %4 ms", _x select 1, _c select 0, ((_c select 1) * 1000) toFixed 1, ((_c select 2) * 1000) toFixed 1]
            };
            private _pfh = if (isNil "cba_common_perFrameHandlerArray") then { [] } else { cba_common_perFrameHandlerArray };
            private _orig = missionNamespace getVariable ["COMSPEC_ATAK_ProfOrig", createHashMap];
            private _ourPfh = { ((_y select 1) select [0, 4]) isEqualTo "PFH " } count _orig;
            private _scripts = diag_activeSQFScripts;
            diag_log format ["[COMSPEC perf] Bilan %1 s : %2 img/s (image moyenne %3 ms, pire %4 ms, %5 au-delà de 50 ms) ; téléphone %6, page %7, %8 contrôles ; mod %9 ms/s (boucles %10, carte %11, rappels DLL %12) ; boucles CBA %13 (COMSPEC %14) ; scripts en file %15 (COMSPEC %16) ; plus coûteux : %17",
                round _span, round (_n / _span), ((_sum / (_n max 1)) * 1000) toFixed 1, _worst call _ms, _slow,
                switch (true) do { case (isNull _d): { "rangé" }; case (_st getOrDefault ["interactive", false]): { "en main" }; default { "miniature" }; },
                [_page, "-"] select (isNull _d), [count (allControls _d), 0] select (isNull _d),
                (((_loops + _mapT + _cb) / _span) * 1000) toFixed 1, ((_loops / _span) * 1000) toFixed 1, ((_mapT / _span) * 1000) toFixed 1, ((_cb / _span) * 1000) toFixed 1,
                count _pfh, _ourPfh, count _scripts, { ((toLower (_x param [1, ""])) find "comspec") >= 0 } count _scripts,
                [_top joinString " ; ", "rien mesuré"] select ((count _top) isEqualTo 0)];
            // Temps passé dans les DLL (relevé de la DLL elle-même, Perf.Stats) : commandes dont un appel a dépassé 5 ms.
            {
                _x params ["_ext", "_label"];
                private _r = _ext callExtension ["Perf.Stats", []];
                private _raw = if (_r isEqualType []) then { _r param [0, ""] } else { _r };
                if (!(_raw isEqualType "") || {(_raw select [0, 3]) isNotEqualTo "OK|"}) then { continue };
                private _slowCmds = [];
                {
                    (_x splitString (toString [9])) params [["_k", ""], ["_c", "0"], ["_avg", "0"], ["", ""], ["_mx", "0"]];
                    if ((parseNumber _mx) > 5) then { _slowCmds pushBack format ["%1 %2× moy %3 ms max %4 ms", _k, _c, _avg, _mx]; };
                } forEach (((_raw select [3]) splitString (toString [10])) select [1]);
                if ((count _slowCmds) > 0) then { diag_log format ["[COMSPEC perf] %1 : appels de plus de 5 ms sur 10 s : %2", _label, _slowCmds joinString " ; "]; };
                _ext callExtension ["Perf.Stats", ["reset"]];
            } forEach [["COMSPECATAKNativeExtension", "DLL native"], ["COMSPECExtension", "COMSPEC Link"]];
        };
    };
};
true
