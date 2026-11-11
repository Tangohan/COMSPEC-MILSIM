/*
    Veille des dalles du TOC (demande de TTRD : alimentation par dalle « éteinte, allumée, veille » et veille
    automatique, onglet ALIMENTATION de la fenêtre « Gérer les écrans », fn_tocManagerUi).
      ALLUMÉE   COMSPEC_ATAK_TocOff faux.
      ÉTEINTE   COMSPEC_ATAK_TocOff vrai : elle le reste (4.8.0, inchangé).
      EN VEILLE COMSPEC_ATAK_TocOff vrai et COMSPEC_ATAK_TocStandby vrai : le serveur la rallume quand un joueur
                approche à 10 m, et la remet en veille 2 min après le départ du dernier joueur (plus de 25 m).
      Veille automatique : COMSPEC_ATAK_TocAutoOff (minutes, sur l'écran hôte, 0 : jamais) ; une dalle allumée sans
                joueur à moins de 25 m depuis ce délai passe en veille.
    Tenu par le serveur seul : une boucle toutes les 5 s, qui ne regarde que les dalles en veille ou à veille
    automatique (rien d'autre n'est parcouru), sans téléphone ni Athena. Serveur plus ancien ou sans le mod : la veille
    reste une extinction simple, et la fenêtre le dit. Ces deux réglages ne sont pas encore gardés d'une partie à
    l'autre (fn_tocPersist ne les connaît pas).
    Lancé par CfgFunctions (postInit), sans toucher aux XEH des autres lots.
    Modes :
      ["postInit"]                    partout : le serveur lance « initServer »
      ["set", [objet, "standby" | "autoOff", valeur]]   client : demande au serveur (ou sur ce poste en repli)
      ["rule", [éteinte, en veille, réveillée par une présence, délai en min, distance du joueur le plus proche en m
                (-1 : personne), secondes sans personne]]   -> "wake" | "sleep" | "" (fonction pure, table _cases)
      ["loop"]                        serveur : une passe
      ["selfTest"]                    [réussis, cas en échec]
*/
params [["_mode", "postInit"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocManagerUiPower;
switch (_mode) do {
    case "postInit": {
        if (isServer) then { ["initServer"] call _fn; };
        true
    };
    case "initServer": {
        if (!isServer || {missionNamespace getVariable ["COMSPEC_ATAK_TocMgrPowerOn", false]}) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TocMgrPowerOn", true];
        ["comspec_atak_native_tocMgrPower", { ["srv", _this] call comspec_atak_native_fnc_tocManagerUiPower; }] call CBA_fnc_addEventHandler;
        [{ ["loop"] call comspec_atak_native_fnc_tocManagerUiPower; }, 5] call CBA_fnc_addPerFrameHandler;
        true
    };
    case "set": {
        _a params [["_o", objNull], ["_k", ""], ["_v", 0]];
        if (isNull _o) exitWith { false };
        // Serveur plus ancien ou objet tenu par les joueurs : la variable est posée d'ici, comme le reste de l'écran.
        if (isServer || {["local", _o] call comspec_atak_native_fnc_tocConfig}) exitWith { ["srv", _a] call _fn };
        ["comspec_atak_native_tocMgrPower", _a] call CBA_fnc_serverEvent;
        true
    };
    case "srv": {
        _a params [["_o", objNull], ["_k", ""], ["_v", 0]];
        if (isNull _o) exitWith { false };
        switch (_k) do {
            case "standby": {
                if ((_o getVariable ["COMSPEC_ATAK_TocStandby", false]) isNotEqualTo (_v isEqualTo true)) then { _o setVariable ["COMSPEC_ATAK_TocStandby", _v isEqualTo true, true]; };
                _o setVariable ["COMSPEC_ATAK_TocWoke", false];
            };
            case "autoOff": { if (_v isEqualType 0) then { _o setVariable ["COMSPEC_ATAK_TocAutoOff", ((round _v) max 0) min 240, true]; }; };
        };
        true
    };
    case "rule": {
        _a params [["_off", false], ["_standby", false], ["_woke", false], ["_auto", 0], ["_near", -1], ["_idle", 0]];
        switch (true) do {
            case (_off && {_standby} && {_near >= 0} && {_near <= 10}): { "wake" };
            case (_off): { "" };
            case (_near >= 0 && {_near <= 25}): { "" };
            case (_woke && {_idle >= 120}): { "sleep" };
            case (_auto > 0 && {_idle >= _auto * 60}): { "sleep" };
            default { "" };
        }
    };
    case "loop": {
        if (!isServer) exitWith { false };
        private _players = allPlayers select { alive _x && {!(_x isKindOf "HeadlessClient_F")} };
        {
            private _o = _x;
            if (isNull _o) then { continue };
            private _host = _o getVariable ["COMSPEC_ATAK_TocHost", objNull];
            if (isNull _host) then { _host = _o; };
            private _standby = _o getVariable ["COMSPEC_ATAK_TocStandby", false];
            private _auto = _host getVariable ["COMSPEC_ATAK_TocAutoOff", 0];
            private _woke = _o getVariable ["COMSPEC_ATAK_TocWoke", false];
            // Ni veille ni veille automatique : la dalle n'est pas regardée.
            if (!_standby && {_auto <= 0} && {!_woke}) then { continue };
            private _near = -1;
            { private _d = _x distance _host; if (_near < 0 || {_d < _near}) then { _near = _d; }; } forEach _players;
            if (_near >= 0 && {_near <= 25}) then { _o setVariable ["COMSPEC_ATAK_TocSeenAt", serverTime]; };
            private _seen = _o getVariable ["COMSPEC_ATAK_TocSeenAt", -1];
            if (_seen < 0) then { _seen = serverTime; _o setVariable ["COMSPEC_ATAK_TocSeenAt", _seen]; };
            switch (["rule", [_o getVariable ["COMSPEC_ATAK_TocOff", false], _standby, _woke, _auto, _near, serverTime - _seen]] call _fn) do {
                // Par fn_tocHall « power » : même chemin qu'un opérateur, sauvegarde comprise.
                case "wake": {
                    ["do", ["power", [_o, true]]] call comspec_atak_native_fnc_tocHall;
                    _o setVariable ["COMSPEC_ATAK_TocWoke", true];
                    _o setVariable ["COMSPEC_ATAK_TocSeenAt", serverTime];
                };
                case "sleep": {
                    ["do", ["power", [_o, false]]] call comspec_atak_native_fnc_tocHall;
                    _o setVariable ["COMSPEC_ATAK_TocStandby", true, true];
                    _o setVariable ["COMSPEC_ATAK_TocWoke", false];
                };
            };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]);
        true
    };
    case "selfTest": {
        // ---- TABLE _cases (JSON) : [nom, éteinte, en veille, réveillée, délai min, distance m (-1 : personne), s sans personne, attendu].
        private _cases = [
["en veille, quelqu'un arrive", true, true, false, 0, 6, 0, "wake"],
["en veille, personne", true, true, false, 0, -1, 900, ""],
["en veille, joueur à 18 m", true, true, false, 0, 18, 0, ""],
["éteinte reste éteinte", true, false, false, 15, 3, 0, ""],
["réveillée, le joueur est encore là", false, true, true, 0, 12, 0, ""],
["réveillée, parti depuis 1 min", false, true, true, 0, 60, 60, ""],
["réveillée, parti depuis 2 min", false, true, true, 0, 60, 120, "sleep"],
["veille automatique 5 min, 4 min sans personne", false, false, false, 5, -1, 240, ""],
["veille automatique 5 min, 5 min sans personne", false, false, false, 5, -1, 300, "sleep"],
["veille automatique, joueur devant", false, false, false, 5, 4, 900, ""],
["allumée sans veille automatique", false, false, false, 0, -1, 9000, ""]
];
        // ---- FIN
        private _bad = [];
        private _okN = 0;
        {
            _x params ["_name", "_off", "_standby", "_woke", "_auto", "_near", "_idle", "_want"];
            if ((["rule", [_off, _standby, _woke, _auto, _near, _idle]] call _fn) isEqualTo _want) then { _okN = _okN + 1; } else { _bad pushBack _name; };
        } forEach _cases;
        [_okN, _bad]
    };
    default { false };
};
