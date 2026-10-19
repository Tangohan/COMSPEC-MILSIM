/*
    API publique des brouilleurs ATAK, pour les missions et les autres mods (guerre électronique, scripts de brouillage).
    Un brouilleur ajouté ici agit comme ceux de l'app Guerre électronique et du module Zeus : débit, GPS, BFT, drones,
    vidéo et relais dans son rayon, et la goniométrie le relève. Il est publié dans COMSPEC_ATAK_Jammers.
      ["add", [source, rayon m, force 1-100, durée s (0 = sans fin), camp épargné ("" aucun, "WEST", "EAST", "GUER", "CIV"), id]]
            source : objet (le brouilleur suit l'objet et s'arrête quand il est détruit) ou position.
            id : optionnel ; par défaut "obj:<hashValue>" pour un objet, sinon "api:<n>". Même id ou même objet = mise à jour.
            Renvoie l'id (sur le serveur), "" ailleurs (la demande part au serveur, événement CBA comspec_atak_native_jammer).
      ["remove", id ou objet]
      ["list"] -> brouilleurs de l'API encore actifs (serveur)
    Interne (serveur) : "init" (postInit), "tick" (toutes les 5 s : objets détruits retirés, objets des classes listées
    dans le réglage « Objets brouilleurs d'autres mods » ajoutés, entrées effacées par une écriture concurrente remises).
    Un objet brouilleur s'éteint sans être retiré avec la variable a3f_jammer_active (scripts « TFAR Radio Jammer ») ou
    COMSPEC_ATAK_JamActive à false.
    Exemple (init d'un objet dans Eden) : if (isServer) then { ["add", [this, 800, 80]] call comspec_atak_native_fnc_jammer; };
*/
params [["_act", ""], ["_args", []]];
if (!isServer && {_act in ["add", "remove"]}) exitWith {
    ["comspec_atak_native_jammer", [_act, _args]] call CBA_fnc_serverEvent;
    ""
};
if (!isServer) exitWith { [] };
private _now = [time, serverTime] select isMultiplayer;
private _srvKey = "COMSPEC_ATAK_ExtJammersSrv";
private _publish = {
    // Liste publique : entrées des joueurs, de Zeus et de la mission gardées, celles de l'API remplacées.
    private _srv = missionNamespace getVariable [_srvKey, []];
    private _ids = _srv apply { _x select 2 };
    private _keep = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select {
        !(_x isEqualType []) || {!((_x param [2, ""]) isEqualType "")} || {!((_x param [2, ""]) in _ids) && {!(((_x param [2, ""]) select [0, 4]) in ["api:", "obj:", "cls:"])}}
    };
    missionNamespace setVariable ["COMSPEC_ATAK_Jammers", _keep + (_srv select { [_x] call _active }), true];
};
private _active = {
    params ["_e"];
    private _src = _e select 0;
    if ((_e select 4) < _now) exitWith { false };
    if (_src isEqualType objNull) exitWith {
        !isNull _src && {alive _src} && {(_src getVariable ["a3f_jammer_active", true]) isNotEqualTo false} && {(_src getVariable ["COMSPEC_ATAK_JamActive", true]) isNotEqualTo false}
    };
    true
};
switch (_act) do {
    case "init": {
        missionNamespace setVariable [_srvKey, []];
        ["comspec_atak_native_jammer", { params [["_a", ""], ["_x", []]]; if (_a in ["add", "remove"]) then { [_a, _x] call comspec_atak_native_fnc_jammer; }; }] call CBA_fnc_addEventHandler;
        [{ ["tick"] call comspec_atak_native_fnc_jammer; }, 5] call CBA_fnc_addPerFrameHandler;
    };
    case "add": {
        _args params [["_src", objNull, [objNull, []]], ["_rad", 500, [0]], ["_str", 100, [0]], ["_dur", 0, [0]], ["_spare", "", [""]], ["_id", "", [""]]];
        if (_src isEqualType objNull && {isNull _src}) exitWith { "" };
        if (_src isEqualType [] && {(count _src) < 2}) exitWith { "" };
        private _srv = missionNamespace getVariable [_srvKey, []];
        if (_id isEqualTo "") then {
            _id = if (_src isEqualType objNull) then { format ["obj:%1", hashValue _src] } else {
                private _n = (missionNamespace getVariable ["COMSPEC_ATAK_ExtJamN", 0]) + 1;
                missionNamespace setVariable ["COMSPEC_ATAK_ExtJamN", _n];
                format ["api:%1", _n]
            };
        };
        if !((_id select [0, 4]) in ["api:", "obj:", "cls:"]) then { _id = "api:" + _id; };
        // Entrée de brouilleur ATAK : [source, rayon, id, camp, fin, filtrage ami, force 0-1].
        private _e = [_src, _rad max 10, _id, _spare, [1e9, _now + _dur] select (_dur > 0), _spare isNotEqualTo "", ((_str max 1) min 100) / 100];
        _srv = _srv select { (_x select 2) isNotEqualTo _id && {!(_src isEqualType objNull) || {(_x select 0) isNotEqualTo _src}} };
        _srv pushBack _e;
        missionNamespace setVariable [_srvKey, _srv];
        call _publish;
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][JAM] Brouilleur %1 : %2 m, %3 %%, %4 s", _id, _rad, _str, _dur];
        _id
    };
    case "remove": {
        private _srv = missionNamespace getVariable [_srvKey, []];
        private _n = count _srv;
        _srv = _srv select { (_x select 2) isNotEqualTo _args && {(_x select 0) isNotEqualTo _args} };
        missionNamespace setVariable [_srvKey, _srv];
        call _publish;
        (count _srv) < _n
    };
    case "list": { (missionNamespace getVariable [_srvKey, []]) select { [_x] call _active } };
    case "tick": {
        private _srv = (missionNamespace getVariable [_srvKey, []]) select {
            private _src = _x select 0;
            (_x select 4) >= _now && {!(_src isEqualType objNull) || {!isNull _src && {alive _src}}}
        };
        // Objets d'autres mods désignés par l'admin : « Classe:rayon:force » séparés par des virgules (rayon 1 000 m et
        // force 80 % par défaut). Classes parentes acceptées (entities suit l'héritage). Liste relue toutes les 5 s.
        private _cfg = missionNamespace getVariable ["comspec_atak_native_jam_classes", ""];
        private _rules = [];
        if (_cfg isEqualType "" && {_cfg isNotEqualTo ""}) then {
            {
                private _p = (_x splitString ":") apply { trim _x };
                private _cls = _p param [0, ""];
                if (_cls isNotEqualTo "") then {
                    _rules pushBack [_cls, (parseNumber (_p param [1, "1000"])) max 10, ((parseNumber (_p param [2, "80"])) max 1) min 100];
                };
            } forEach (_cfg splitString ",;");
        };
        private _want = [];
        {
            _x params ["_cls", "_rad", "_str"];
            { _want pushBack [_x, _rad max 10, format ["cls:%1", hashValue _x], "", 1e9, false, _str / 100]; } forEach (entities [[_cls], [], false, true]);
        } forEach _rules;
        // Les entrées de classe suivent le réglage : nouvelles ajoutées, disparues retirées, rayon et force mis à jour.
        _srv = (_srv select { ((_x select 2) select [0, 4]) isNotEqualTo "cls:" }) + _want;
        missionNamespace setVariable [_srvKey, _srv];
        // Republie si la liste publique ne correspond plus (écriture concurrente d'un client, objet éteint ou rallumé).
        private _pubIds = ((missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { _x isEqualType [] }) apply { _x param [2, ""] };
        private _on = (_srv select { [_x] call _active }) apply { _x select 2 };
        private _stale = _pubIds select { _x isEqualType "" && {((_x select [0, 4]) in ["api:", "obj:", "cls:"])} };
        if (((_on - _pubIds) isNotEqualTo []) || {(_stale - _on) isNotEqualTo []} || {_want isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_ExtJamLast", []])}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_ExtJamLast", _want];
            call _publish;
        };
    };
};
