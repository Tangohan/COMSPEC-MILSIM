/*
    App Musique. Params : [action, argument]
      "playList" [pistes, index] : joue la piste et met la liste en file      "enqueue" piste : ajoute à la file
      "toggle" : pause / reprise     "stop"     "next"     "prev"     "jump" index : piste de la file
      "vol" n (0-100) : écouteurs    "volStep" ±n    "spkVol" n / "spkVolStep" ±n : haut-parleur
      "speaker" / "hear" / "shuffle" : bascule    "repeat" off|all|one    "repeatCycle" : non -> la file -> le morceau
      "files" : relit le dossier local (DLL)    "openFolder"    "url" : lit l'URL saisie    "urlDel" index
      "tab" onglet    "fold" clé : section repliable de LECTURE    "srvCat" catégorie    "srvSearch" : applique la recherche    "srvPage" n
      "seek" ±s : avance / recule dans la piste    "seekFrac" 0-1 : va à ce point de la piste (barre de progression)
      "qDel" index    "qUp" index    "qClear" : file d'attente
      "fav" piste : ajoute / retire des favoris (profil)
      "ath" vue : vue de la bibliothèque Athena    "athSearch"    "athOpen" [id, nom] : ouvre une playlist    "athReload"
    Une piste = [kind, ref, titre, durée s] ; kind = file (dossier Documents\Arma 3\COMSPEC_Music), url, srv (CfgMusic),
    lib (bibliothèque musicale d'Athena : URL signée, mise en cache par la DLL).
    3e paramètre false (curseurs de volume pendant le glissé) : la page n'est pas redessinée, le son est appliqué
    au plus 10 fois par seconde puis une dernière fois après le dernier cran.
    Le son lui-même est lancé par comspec_atak_native_fnc_musicTick (aussi pour les haut-parleurs des autres).
*/
params [["_act", ""], ["_arg", ""], ["_redraw", true]];
private _m = [] call comspec_atak_native_fnc_musicState;
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_MusicUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_MusicUi", _ui];
private _now = [time, serverTime] select isMultiplayer;
private _rerender = { if (!_redraw) exitWith {}; [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MUSIC") then { ["MUSIC"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
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
    if (["mute", false] call comspec_atak_native_fnc_zeusFx) exitWith { ["WARNING", "Haut-parleurs coupés à distance : pas de musique", 3, 30] call comspec_atak_native_fnc_notify; false };
    true
};
private _volTick = {
    if (_redraw) exitWith { [] call comspec_atak_native_fnc_musicTick; };
    if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_MusicVolAt", -1]) > 0.1) then {
        uiNamespace setVariable ["COMSPEC_ATAK_MusicVolAt", diag_tickTime];
        [] call comspec_atak_native_fnc_musicTick;
    };
    [{ [] call comspec_atak_native_fnc_musicTick; }, [], 0.15] call CBA_fnc_waitAndExecute;
};
switch (_act) do {
    case "playList": {
        if !(call _usable) exitWith {};
        _arg params [["_items", []], ["_i", 0]];
        if (!(missionNamespace getVariable ["comspec_atak_native_music_athena", true]) && {(_items findIf { (_x select 0) isEqualTo "lib" }) >= 0}) exitWith {
            ["WARNING", "Bibliothèque Athena désactivée sur ce serveur", 3, 30] call comspec_atak_native_fnc_notify;
        };
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
        call _volTick;
        call _rerender;
    };
    case "volStep": { ["vol", (_m get "vol") + _arg] call comspec_atak_native_fnc_musicAction; };
    case "spkVol": {
        private _v = (round (if (_arg isEqualType "") then { parseNumber _arg } else { _arg })) max 0 min 100;
        if (_v isEqualTo 0 && {(_m getOrDefault ["spkVol", 70]) > 0}) then { profileNamespace setVariable ["COMSPEC_ATAK_MusicSpkVolBack", _m getOrDefault ["spkVol", 70]]; };
        _m set ["spkVol", _v];
        profileNamespace setVariable ["COMSPEC_ATAK_MusicSpkVol", _v];
        call _volTick;
        call _rerender;
    };
    case "spkVolStep": { ["spkVol", (_m getOrDefault ["spkVol", 70]) + _arg] call comspec_atak_native_fnc_musicAction; };
    case "speaker": {
        if !(missionNamespace getVariable ["comspec_atak_native_music_speaker", true]) exitWith { ["WARNING", "Haut-parleur interdit sur ce serveur", 3, 30] call comspec_atak_native_fnc_notify; };
        if (!(_m get "speaker") && {((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["audio", 0]) >= 0.5}) exitWith { ["WARNING", "Haut-parleur du téléphone hors service (à réparer)", 3, 30] call comspec_atak_native_fnc_notify; };
        _m set ["speaker", !(_m get "speaker")];
        ["INFO", ["Haut-parleur coupé : vous seul entendez", format ["Haut-parleur : audible à %1 m", round (missionNamespace getVariable ["comspec_atak_native_music_range", 30])]] select (_m get "speaker"), 3, 20] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_musicTick;
        call _rerender;
    };
    case "hear": { _m set ["hear", !(_m get "hear")]; profileNamespace setVariable ["COMSPEC_ATAK_MusicHear", _m get "hear"]; [] call comspec_atak_native_fnc_musicTick; call _rerender; };
    case "shuffle": { _m set ["shuffle", !(_m get "shuffle")]; profileNamespace setVariable ["COMSPEC_ATAK_MusicShuffle", _m get "shuffle"]; call _rerender; };
    case "repeatCycle": { ["repeat", (createHashMapFromArray [["off", "all"], ["all", "one"], ["one", "off"]]) getOrDefault [_m get "repeat", "all"]] call comspec_atak_native_fnc_musicAction; };
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
    case "tab": {
        _ui set ["tab", _arg];
        if (_arg isEqualTo "LOCAL" && {!("files" in _ui)}) exitWith { ["files"] call comspec_atak_native_fnc_musicAction; };
        if (_arg isEqualTo "ATH" && {!((_ui getOrDefault ["ath", "global"]) in (uiNamespace getVariable ["COMSPEC_ATAK_MusicAth", createHashMap]))}) exitWith { ["ath", _ui getOrDefault ["ath", "global"]] call comspec_atak_native_fnc_musicAction; };
        call _rerender;
    };
    case "fold": {
        // Sections repliables de l'onglet LECTURE, en accordéon : ouvre celle-ci (et ferme l'autre), ou la referme.
        _ui set ["fold", [_arg, ""] select ((_ui getOrDefault ["fold", "son"]) isEqualTo _arg)];
        call _rerender;
    };
    case "srvCat": { _ui set ["cat", _arg]; _ui set ["page", 0]; call _rerender; };
    case "srvSearch": { _ui set ["q", (["musicSearch"] call comspec_atak_native_fnc_formValue) trim [" ", 0]]; _ui set ["page", 0]; call _rerender; };
    case "srvPage": { _ui set ["page", _arg max 0]; call _rerender; };
    case "seek": {
        // Nouveau départ décalé ; le jeton change pour que musicTick relance la piste à la nouvelle position
        // (et que les auditeurs du haut-parleur suivent).
        if ((_m get "kind") isEqualTo "") exitWith {};
        private _len = _m get "len";
        if (_m get "paused") exitWith {
            private _p = ((_m get "pausedAt") + _arg) max 0;
            if (_len > 0) then { _p = _p min (_len - 1); };
            _m set ["pausedAt", _p];
            call _rerender;
        };
        private _pos = ((_now - (_m get "start")) + _arg) max 0;
        if (_len > 0 && {_pos >= _len - 1}) exitWith { ["next"] call comspec_atak_native_fnc_musicAction; };
        _m set ["start", _now - _pos];
        _m set ["tok", (_m getOrDefault ["tok", 0]) + 1];
        [] call comspec_atak_native_fnc_musicTick;
        call _rerender;
    };
    case "seekFrac": {
        // Barre de progression : position visée moins position actuelle, puis le même chemin que « seek ».
        private _len = _m get "len";
        if ((_m get "kind") isEqualTo "" || {_len <= 0}) exitWith {};
        private _pos = if (_m get "paused") then { _m get "pausedAt" } else { (_now - (_m get "start")) max 0 };
        ["seek", ((_arg max 0 min 1) * _len) - _pos] call comspec_atak_native_fnc_musicAction;
    };
    case "qDel": {
        private _q = _m get "queue";
        if (_arg < 0 || {_arg >= count _q}) exitWith {};
        private _cur = _m get "qi";
        private _playing = (_m get "kind") isNotEqualTo "";
        _q deleteAt _arg;
        if (_arg < _cur) then { _m set ["qi", _cur - 1]; };
        if (_arg isEqualTo _cur && {_playing}) then {
            if ((count _q) isEqualTo 0) then { ["stop"] call comspec_atak_native_fnc_musicAction; } else { [_cur min ((count _q) - 1)] call _start; };
        };
        call _rerender;
    };
    case "qUp": {
        private _q = _m get "queue";
        if (_arg < 1 || {_arg >= count _q}) exitWith {};
        private _a = _q select (_arg - 1);
        _q set [_arg - 1, _q select _arg];
        _q set [_arg, _a];
        private _cur = _m get "qi";
        if (_cur isEqualTo _arg) then { _m set ["qi", _arg - 1]; } else { if (_cur isEqualTo (_arg - 1)) then { _m set ["qi", _arg]; }; };
        call _rerender;
    };
    case "qClear": {
        // Garde la piste en cours, vide le reste.
        private _q = _m get "queue";
        if ((_m get "kind") isNotEqualTo "" && {(_m get "qi") < count _q}) then {
            _m set ["queue", [_q select (_m get "qi")]]; _m set ["qi", 0];
        } else { _m set ["queue", []]; _m set ["qi", 0]; };
        call _rerender;
    };
    case "fav": {
        _arg params ["_k", "_r", "_t", ["_len", 0]];
        // Une URL signée Athena change : un favori Athena est reconnu par son identifiant de piste (6e champ).
        private _id = _arg param [5, ""];
        private _favs = profileNamespace getVariable ["COMSPEC_ATAK_MusicFavs", []];
        private _i = _favs findIf { (_x select 0) isEqualTo _k && {(_x select 1) isEqualTo _r || {_id isNotEqualTo "" && {(_x param [5, ""]) isEqualTo _id}}} };
        if (_i >= 0) then {
            _favs deleteAt _i;
            ["INFO", format ["Retiré des favoris : %1", _t], 2, 20] call comspec_atak_native_fnc_notify;
        } else {
            _favs = [+_arg] + _favs;
            ["INFO", format ["Ajouté aux favoris : %1", _t], 2, 20] call comspec_atak_native_fnc_notify;
        };
        profileNamespace setVariable ["COMSPEC_ATAK_MusicFavs", _favs select [0, 100]];
        call _rerender;
    };
    case "ath": {
        _ui set ["ath", _arg];
        ["ensure", _arg] call comspec_atak_native_fnc_musicAthena;
        call _rerender;
    };
    case "athOpen": {
        _arg params ["_id", ["_name", ""]];
        _ui set ["athPl", [_id, _name]];
        ["ath", "pl:" + _id] call comspec_atak_native_fnc_musicAction;
    };
    case "athSearch": {
        _ui set ["athQ", (["musicAthSearch"] call comspec_atak_native_fnc_formValue) trim [" ", 0]];
        private _v = _ui getOrDefault ["ath", "global"];
        if (_v isEqualTo "playlists") then { _v = "community"; _ui set ["ath", _v]; };
        ["load", _v] call comspec_atak_native_fnc_musicAthena;
    };
    case "athReload": { ["load", _ui getOrDefault ["ath", "global"]] call comspec_atak_native_fnc_musicAthena; };
};
true
