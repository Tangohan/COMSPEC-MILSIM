/*
    App Musique. Params : [action, argument]
      "playList" [pistes, index] : joue la piste et met la liste en file      "enqueue" piste : ajoute à la file
      "toggle" : pause / reprise     "stop"     "next"     "prev"     "jump" index : piste de la file
      "vol" n (0-100)    "volStep" ±n    "speaker" / "hear" / "shuffle" : bascule    "repeat" off|all|one
      "files" : relit le dossier local (DLL)    "openFolder"    "url" : lit l'URL saisie    "urlDel" index
      "tab" onglet    "srvCat" catégorie    "srvSearch" : applique la recherche    "srvPage" n
    Une piste = [kind, ref, titre, durée s] ; kind = file (dossier Documents\Arma 3\COMSPEC_Music), url, srv (CfgMusic).
    Le son lui-même est lancé par comspec_atak_native_fnc_musicTick (aussi pour les haut-parleurs des autres).
*/
params [["_act", ""], ["_arg", ""]];
private _m = [] call comspec_atak_native_fnc_musicState;
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_MusicUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_MusicUi", _ui];
private _now = [time, serverTime] select isMultiplayer;
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MUSIC") then { ["MUSIC"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _start = {
    params ["_i"];
    private _q = _m get "queue";
    if ((count _q) isEqualTo 0) exitWith { ["stop"] call comspec_atak_native_fnc_musicAction; };
    _i = (_i max 0) min ((count _q) - 1);
    (_q select _i) params ["_k", "_r", "_t", ["_len", 0]];
    _m set ["qi", _i]; _m set ["kind", _k]; _m set ["ref", _r]; _m set ["title", _t]; _m set ["len", _len];
    _m set ["start", _now]; _m set ["paused", false]; _m set ["pausedAt", 0]; _m set ["status", ["loading", 0, 0, _t, ""]];
    _m set ["tok", (_m getOrDefault ["tok", 0]) + 1];
    [] call comspec_atak_native_fnc_musicTick;
};
private _usable = {
    if !(missionNamespace getVariable ["comspec_atak_native_music_enabled", true]) exitWith { ["WARNING", "Musique désactivée sur ce serveur", 3, 30] call comspec_atak_native_fnc_notify; false };
    private _why = [] call comspec_atak_native_fnc_canUse;
    if (_why isNotEqualTo "") exitWith { ["WARNING", "Téléphone indisponible : pas de musique", 3, 30] call comspec_atak_native_fnc_notify; false };
    true
};
switch (_act) do {
    case "playList": {
        if !(call _usable) exitWith {};
        _arg params [["_items", []], ["_i", 0]];
        if (!(missionNamespace getVariable ["comspec_atak_native_music_urls", true]) && {(_items findIf { (_x select 0) isEqualTo "url" }) >= 0}) exitWith {
            ["WARNING", "Lecture d'URL interdite sur ce serveur", 3, 30] call comspec_atak_native_fnc_notify;
        };
        if ((count _items) isEqualTo 0) exitWith {};
        _m set ["queue", +_items]; _m set ["errors", 0];
        if (_m get "shuffle") then {
            // La piste choisie d'abord, le reste mélangé.
            private _first = _items select _i;
            private _rest = _items - [_first];
            _m set ["queue", [_first] + (_rest call BIS_fnc_arrayShuffle)];
            _i = 0;
        };
        [_i] call _start;
        call _rerender;
    };
    case "enqueue": {
        private _q = _m get "queue";
        _q pushBack _arg;
        ["INFO", format ["Ajouté à la file : %1", _arg select 2], 2, 20] call comspec_atak_native_fnc_notify;
        if ((_m get "kind") isEqualTo "" && {call _usable}) then { [(count _q) - 1] call _start; };
        call _rerender;
    };
    case "jump": { if (call _usable) then { [_arg] call _start; call _rerender; }; };
    case "toggle": {
        if ((_m get "kind") isEqualTo "") exitWith {
            if ((count (_m get "queue")) > 0 && {call _usable}) then { [_m get "qi"] call _start; call _rerender; };
        };
        if (_m get "paused") then {
            _m set ["start", _now - (_m get "pausedAt")]; _m set ["paused", false];
        } else {
            _m set ["pausedAt", (_now - (_m get "start")) max 0]; _m set ["paused", true];
        };
        [] call comspec_atak_native_fnc_musicTick;
        call _rerender;
    };
    case "stop": {
        _m set ["kind", ""]; _m set ["paused", false]; _m set ["status", ["idle", 0, 0, "", ""]];
        [] call comspec_atak_native_fnc_musicTick;
        call _rerender;
    };
    case "next";
    case "prev": {
        private _q = _m get "queue";
        if ((count _q) isEqualTo 0) exitWith {};
        // Précédent après 5 s de lecture : on reprend la piste au début.
        if (_act isEqualTo "prev" && {(_now - (_m get "start")) > 5} && {!(_m get "paused")}) exitWith { [_m get "qi"] call _start; call _rerender; };
        private _wrap = (_m get "repeat") isNotEqualTo "off";
        private _i = (_m get "qi") + ([1, -1] select (_act isEqualTo "prev"));
        if (_i >= (count _q)) then { _i = [-1, 0] select _wrap; };
        if (_act isEqualTo "prev" && {_i < 0}) then { _i = [0, (count _q) - 1] select _wrap; };
        if (_i < 0) exitWith { ["stop"] call comspec_atak_native_fnc_musicAction; };
        [_i] call _start;
        call _rerender;
    };
    case "ended": {
        // Fin de piste (appelé par musicTick) : répéter, suivante, ou fin de file.
        private _q = _m get "queue";
        if ((_m get "repeat") isEqualTo "one") exitWith { [_m get "qi"] call _start; };
        private _i = (_m get "qi") + 1;
        if (_i >= (count _q)) exitWith {
            if ((_m get "repeat") isEqualTo "all" && {(count _q) > 0}) then {
                if (_m get "shuffle") then { _m set ["queue", _q call BIS_fnc_arrayShuffle]; };
                [0] call _start;
            } else { ["stop"] call comspec_atak_native_fnc_musicAction; };
        };
        [_i] call _start;
        call _rerender;
    };
    case "vol": {
        private _v = (round (if (_arg isEqualType "") then { parseNumber _arg } else { _arg })) max 0 min 100;
        if (_v isEqualTo 0 && {(_m get "vol") > 0}) then { profileNamespace setVariable ["COMSPEC_ATAK_MusicVolBack", _m get "vol"]; };
        _m set ["vol", _v];
        profileNamespace setVariable ["COMSPEC_ATAK_MusicVol", _v];
        [] call comspec_atak_native_fnc_musicTick;
        call _rerender;
    };
    case "volStep": { ["vol", (_m get "vol") + _arg] call comspec_atak_native_fnc_musicAction; };
    case "speaker": {
        if !(missionNamespace getVariable ["comspec_atak_native_music_speaker", true]) exitWith { ["WARNING", "Haut-parleur interdit sur ce serveur", 3, 30] call comspec_atak_native_fnc_notify; };
        _m set ["speaker", !(_m get "speaker")];
        ["INFO", ["Haut-parleur coupé : vous seul entendez", format ["Haut-parleur : audible à %1 m", round (missionNamespace getVariable ["comspec_atak_native_music_range", 30])]] select (_m get "speaker"), 3, 20] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_musicTick;
        call _rerender;
    };
    case "hear": { _m set ["hear", !(_m get "hear")]; profileNamespace setVariable ["COMSPEC_ATAK_MusicHear", _m get "hear"]; [] call comspec_atak_native_fnc_musicTick; call _rerender; };
    case "shuffle": { _m set ["shuffle", !(_m get "shuffle")]; profileNamespace setVariable ["COMSPEC_ATAK_MusicShuffle", _m get "shuffle"]; call _rerender; };
    case "repeat": { _m set ["repeat", _arg]; profileNamespace setVariable ["COMSPEC_ATAK_MusicRepeat", _arg]; call _rerender; };
    case "files": {
        private _raw = ["MusicList"] call comspec_atak_native_fnc_extensionCall;
        private _p = _raw splitString "|";
        if ((_p param [0, ""]) isEqualTo "OK") then {
            _ui set ["dir", _p param [1, ""]];
            _ui set ["files", _p select [2, 400]];
            _ui set ["filesErr", ""];
        } else {
            // Une DLL sans lecteur (ancienne, ou pas encore recompilée) répond par une erreur de connexion Athena.
            private _why = _p param [1, ""];
            private _oldDll = !((_p param [0, ""]) isEqualTo "ERR") || {_why in ["not_connected", "unauthorized", "unknown_function", "unknown_command"]};
            _ui set ["filesErr", [_why, "Lecteur audio absent de la DLL du mod : relancez build_mod.bat (DLL COMSPECATAKNativeExtension à recompiler)."] select _oldDll];
        };
        call _rerender;
    };
    case "openFolder": {
        private _raw = ["MusicOpenFolder"] call comspec_atak_native_fnc_extensionCall;
        if ((_raw find "OK|") isEqualTo 0) then { ["INFO", "Dossier ouvert sur le bureau (Alt+Tab)", 3, 20] call comspec_atak_native_fnc_notify; };
    };
    case "url": {
        if !(missionNamespace getVariable ["comspec_atak_native_music_urls", true]) exitWith { ["WARNING", "Lecture d'URL interdite sur ce serveur", 3, 30] call comspec_atak_native_fnc_notify; };
        private _u = (["musicUrl"] call comspec_atak_native_fnc_formValue) trim [" ", 0];
        private _t = (["musicUrlTitle"] call comspec_atak_native_fnc_formValue) trim [" ", 0];
        if !((_u find "http://") isEqualTo 0 || {(_u find "https://") isEqualTo 0}) exitWith { ["WARNING", "Adresse invalide : elle doit commencer par http:// ou https://", 4, 30] call comspec_atak_native_fnc_notify; };
        if (_t isEqualTo "") then { _t = (_u splitString "/") param [((count (_u splitString "/")) - 1), _u]; };
        private _rec = profileNamespace getVariable ["COMSPEC_ATAK_MusicUrls", []];
        _rec = _rec select { (_x select 1) isNotEqualTo _u };
        _rec = [[_t, _u]] + _rec;
        profileNamespace setVariable ["COMSPEC_ATAK_MusicUrls", _rec select [0, 12]];
        ["playList", [[["url", _u, _t, 0]], 0]] call comspec_atak_native_fnc_musicAction;
    };
    case "urlDel": {
        private _rec = profileNamespace getVariable ["COMSPEC_ATAK_MusicUrls", []];
        _rec deleteAt _arg;
        profileNamespace setVariable ["COMSPEC_ATAK_MusicUrls", _rec];
        call _rerender;
    };
    case "tab": { _ui set ["tab", _arg]; if (_arg isEqualTo "LOCAL" && {!("files" in _ui)}) exitWith { ["files"] call comspec_atak_native_fnc_musicAction; }; call _rerender; };
    case "srvCat": { _ui set ["cat", _arg]; _ui set ["page", 0]; call _rerender; };
    case "srvSearch": { _ui set ["q", (["musicSearch"] call comspec_atak_native_fnc_formValue) trim [" ", 0]]; _ui set ["page", 0]; call _rerender; };
    case "srvPage": { _ui set ["page", _arg max 0]; call _rerender; };
};
true
