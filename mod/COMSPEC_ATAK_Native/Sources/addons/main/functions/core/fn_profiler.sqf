/*
    Mode profilage (réglage CBA comspec_atak_native_profiling, « ATAK · Debug », désactivé par défaut) : trace dans le
    RPT, toutes les 10 s, le temps passé par chaque tâche de fond, pour retrouver ce qui fait saccader le jeu.
    Mesuré : toutes les boucles CBA (PFH) de la partie, COMSPEC (ATAK, Overwatch, SSE) et autres mods à part ; les rappels
    et appels de la DLL (DLL …), les écritures du profil, et les clés déjà suivies par fn_perfAcc (carte, drone…).
    Chaque boucle est enveloppée par COMSPEC_ATAK_ProfWrap (même appel [_args, _handle], coût de quelques microsecondes) ;
    les boucles ajoutées en cours de partie le sont au bilan suivant. Arrêt : chaque boucle retrouve son code d'origine.
    Lignes écrites ([COMSPEC ATAK NATIVE][PROF]) :
      10 s : images par seconde, image moyenne et pire, saccades, temps COMSPEC et autres mods, scripts en file ;
      COMSPEC : tâche n× total ms max ms (les plus coûteuses d'abord) ; autres mods : idem ; pics > 5 ms ;
      Saccade : image de plus de 33 ms et de plus du double de la moyenne, avec ce qui a tourné autour (10 par bilan).
    Params : ["start"] | ["stop"] | ["wrap"] (nouvelles boucles) | ["frame"] (chaque image) | ["report"] (toutes les 10 s).
*/
params [["_mode", "report"]];
if (!hasInterface) exitWith { false };
switch (_mode) do {
    case "start": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) exitWith { true };
        if (isNil "cba_common_perFrameHandlerArray") exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_PerfSlow", [0, 0]];
        COMSPEC_ATAK_ProfOrig = createHashMap;
        COMSPEC_ATAK_ProfFrame = [];
        COMSPEC_ATAK_ProfPrev = [];
        COMSPEC_ATAK_ProfSpikes = [];
        COMSPEC_ATAK_ProfHitches = [];
        // [images, somme des durées, pire image, saccades, début du bilan, moyenne glissante]
        COMSPEC_ATAK_ProfWin = [0, 0, 0, 0, diag_tickTime, 0.016];
        COMSPEC_ATAK_ProfWrap = {
            private _comspecProfE = COMSPEC_ATAK_ProfOrig getOrDefault [_this select 1, []];
            if (_comspecProfE isEqualTo []) exitWith {};
            private _comspecProfT = diag_tickTime;
            _this call (_comspecProfE select 0);
            [_comspecProfE select 1, _comspecProfT] call comspec_atak_native_fnc_perfAcc;
        };
        // Les deux boucles du profilage lui-même ne sont pas enveloppées.
        private _hFrame = [{ ["frame"] call comspec_atak_native_fnc_profiler; }, 0] call CBA_fnc_addPerFrameHandler;
        private _hReport = [{ ["report"] call comspec_atak_native_fnc_profiler; }, 10] call CBA_fnc_addPerFrameHandler;
        COMSPEC_ATAK_ProfSkip = [_hFrame, _hReport];
        missionNamespace setVariable ["COMSPEC_ATAK_Prof", true];
        ["wrap"] call comspec_atak_native_fnc_profiler;
        diag_log format ["[COMSPEC ATAK NATIVE][PROF] Profilage activé (%1, %2 boucles CBA suivies) : bilan toutes les 10 s", missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", ""], count COMSPEC_ATAK_ProfOrig];
        true
    };
    case "stop": {
        if !(missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) exitWith { true };
        missionNamespace setVariable ["COMSPEC_ATAK_Prof", false];
        { [_x] call CBA_fnc_removePerFrameHandler; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_ProfSkip", []]);
        {
            _x params ["_f", "", "", "", "", "_h"];
            if (_f isEqualTo COMSPEC_ATAK_ProfWrap) then { _x set [0, (COMSPEC_ATAK_ProfOrig getOrDefault [_h, [{}]]) select 0]; };
        } forEach cba_common_perFrameHandlerArray;
        COMSPEC_ATAK_ProfOrig = createHashMap;
        diag_log "[COMSPEC ATAK NATIVE][PROF] Profilage arrêté";
        true
    };
    case "wrap": {
        private _ident = "abcdefghijklmnopqrstuvwxyz0123456789_";
        // Fonctions utilitaires appelées partout : on nomme la boucle d'après la suivante.
        private _generic = ["comspec_atak_native_fnc_hasdevice", "comspec_atak_native_fnc_display", "comspec_atak_native_fnc_bridge",
            "comspec_atak_native_fnc_linkquality", "comspec_atak_native_fnc_log", "comspec_atak_native_fnc_notify", "comspec_atak_native_fnc_perfacc",
            "comspec_atak_native_fnc_tr", "comspec_overwatch_connect_fnc_log", "comspec_overwatch_connect_fnc_dataflowallowed"];
        {
            _x params ["_f", "", "", "", "", "_h"];
            if (_h in COMSPEC_ATAK_ProfOrig || {_h in COMSPEC_ATAK_ProfSkip} || {_f isEqualTo {}} || {_f isEqualTo COMSPEC_ATAK_ProfWrap}) then { continue; };
            private _s = toLower str _f;
            // Nom : la première fonction appelée (hors utilitaires), sinon le début du code.
            private _names = [];
            private _i = _s find "_fnc_";
            private _from = 0;
            while { _i >= 0 && {(count _names) < 4} } do {
                private _a = _from + _i;
                private _b = _a + 5;
                while { _a > 0 && {(_ident find (_s select [_a - 1, 1])) >= 0} } do { _a = _a - 1; };
                while { _b < (count _s) && {(_ident find (_s select [_b, 1])) >= 0} } do { _b = _b + 1; };
                _names pushBack (_s select [_a, _b - _a]);
                _from = _b;
                _i = (_s select [_from]) find "_fnc_";
            };
            private _name = (_names select { !(_x in _generic) }) param [0, _names param [0, ""]];
            if (_name isEqualTo "") then { _name = ((_s splitString (" " + toString [9, 10, 13])) joinString " ") select [0, 60]; };
            private _ours = (_s find "comspec") >= 0;
            COMSPEC_ATAK_ProfOrig set [_h, [_f, [format ["[mod] %1", _name], format ["PFH %1", _name]] select _ours]];
            _x set [0, COMSPEC_ATAK_ProfWrap];
        } forEach cba_common_perFrameHandlerArray;
        true
    };
    case "frame": {
        private _dt = diag_deltaTime;
        private _w = COMSPEC_ATAK_ProfWin;
        _w set [0, (_w select 0) + 1];
        _w set [1, (_w select 1) + _dt];
        _w set [2, (_w select 2) max _dt];
        private _avg = _w select 5;
        // Saccade : image d'au moins 33 ms et de plus du double de la moyenne glissante. diag_deltaTime est la durée de
        // l'image précédente : ce qui a tourné pendant elle est dans les deux dernières listes.
        if (_dt > 0.033 && {_dt > 2 * _avg}) then {
            _w set [3, (_w select 3) + 1];
            if ((count COMSPEC_ATAK_ProfHitches) < 10) then { COMSPEC_ATAK_ProfHitches pushBack [_dt, _avg, COMSPEC_ATAK_ProfPrev + COMSPEC_ATAK_ProfFrame]; };
        } else {
            _w set [5, _avg * 0.95 + _dt * 0.05];
        };
        COMSPEC_ATAK_ProfPrev = COMSPEC_ATAK_ProfFrame;
        COMSPEC_ATAK_ProfFrame = [];
    };
    case "report": {
        ["wrap"] call comspec_atak_native_fnc_profiler;
        private _ms = { ((_this * 1000) toFixed 1) };
        private _acc = missionNamespace getVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
        COMSPEC_ATAK_ProfWin params ["_n", "_sum", "_worst", "_hitchN", "_t0", "_avg"];
        private _span = (diag_tickTime - _t0) max 0.001;
        COMSPEC_ATAK_ProfWin = [0, 0, 0, 0, diag_tickTime, _avg];
        private _rows = (keys _acc) apply { [(_acc get _x) select 1, _x] };
        _rows sort false;
        private _fmt = { params ["_k"]; private _c = _acc get _k; format ["%1 %2× %3 ms max %4 ms", _k, _c select 0, (_c select 1) call _ms, (_c select 2) call _ms] };
        // Totaux : boucles et rappels de la DLL seulement (les autres clés sont mesurées à l'intérieur de ces boucles).
        private _top = { params ["_k"]; ((_k select [0, 4]) isEqualTo "PFH ") || {(_k select [0, 11]) isEqualTo "Rappel DLL "} };
        private _ours = 0; private _theirs = 0;
        { private _k = _x select 1; if ((_k select [0, 6]) isEqualTo "[mod] ") then { _theirs = _theirs + (_x select 0); } else { if ([_k] call _top) then { _ours = _ours + (_x select 0); }; }; } forEach _rows;
        private _scripts = diag_activeSQFScripts;
        private _ourScripts = { ((toLower (_x param [1, ""])) find "comspec") >= 0 } count _scripts;
        diag_log format ["[COMSPEC ATAK NATIVE][PROF] %1 s : %2 img/s (image moyenne %3 ms, pire %4 ms), saccades %5 ; COMSPEC %6 ms (%7 %% du temps) ; autres mods (boucles CBA) %8 ms ; scripts en file %9 (COMSPEC %10) ; téléphone %11, page %12",
            round _span, round (_n / _span), (_sum / (_n max 1)) call _ms, _worst call _ms, _hitchN, _ours call _ms, ((_ours / _span) * 100) toFixed 2, _theirs call _ms,
            count _scripts, _ourScripts, ["rangé", "affiché"] select !isNull ([] call comspec_atak_native_fnc_display),
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]];
        private _mine = (_rows select { ((_x select 1) select [0, 6]) isNotEqualTo "[mod] " }) select [0, 15];
        if ((count _mine) > 0) then { diag_log format ["[COMSPEC ATAK NATIVE][PROF] COMSPEC : %1", (_mine apply { [_x select 1] call _fmt }) joinString " ; "]; };
        private _other = (_rows select { ((_x select 1) select [0, 6]) isEqualTo "[mod] " }) select [0, 8];
        if ((count _other) > 0) then { diag_log format ["[COMSPEC ATAK NATIVE][PROF] Autres mods : %1", (_other apply { [_x select 1] call _fmt }) joinString " ; "]; };
        if ((count COMSPEC_ATAK_ProfSpikes) > 0) then {
            diag_log format ["[COMSPEC ATAK NATIVE][PROF] Pics > 5 ms : %1", (COMSPEC_ATAK_ProfSpikes apply { format ["%1 %2 ms", _x select 0, (_x select 1) call _ms] }) joinString " ; "];
        };
        {
            _x params ["_dt", "_a", "_tasks"];
            private _sumC = 0; private _sumM = 0;
            { if (((_x select 0) select [0, 6]) isEqualTo "[mod] ") then { _sumM = _sumM + (_x select 1); } else { if ([_x select 0] call _top) then { _sumC = _sumC + (_x select 1); }; }; } forEach _tasks;
            private _sorted = (_tasks select { (_x select 1) >= 0.0005 }) apply { [_x select 1, _x select 0] };
            _sorted sort false;
            diag_log format ["[COMSPEC ATAK NATIVE][PROF] Saccade : image de %1 ms (moyenne %2 ms) ; COMSPEC autour %3 ms, autres mods %4 ms%5",
                _dt call _ms, _a call _ms, _sumC call _ms, _sumM call _ms,
                ["", " : " + ((_sorted select [0, 6]) apply { format ["%1 %2 ms", _x select 1, (_x select 0) call _ms] } joinString " ; ")] select ((count _sorted) > 0)];
        } forEach COMSPEC_ATAK_ProfHitches;
        COMSPEC_ATAK_ProfSpikes = [];
        COMSPEC_ATAK_ProfHitches = [];
    };
};
true
