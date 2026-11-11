/*
    Pistes du jeu pour le TOC (4.11.0, principe posé par TTRD : « tout le TOC doit être utilisable sans téléphone »).
    Avant, un nœud n'existait que si l'unité portait un ATAK (fn_tocFeed) : sans téléphone, carte, GROUPES, RÉSEAU et
    ORBAT étaient vides. Ici le serveur lit le jeu lui-même (unités, véhicules, aéronefs, drones du camp) et fusionne
    avec ce que les téléphones rapportent. Réglage « Source des positions du TOC » (comspec_atak_native_toc_source) :
      mix  (défaut) tout le monde est vu à sa vraie position ; un porteur d'ATAK garde en plus l'état de sa liaison
      game          tout le monde est vu, les téléphones ne sont pas lus
      atak          comme avant la 4.11.0 : seuls les porteurs, à leur dernier point
    Coût serveur : aucune boucle ici. fn_tocFeed « tick » appelle « nodes » pour les seuls camps qui ont un lecteur
    (PC du poste ouvert ou joueur près d'un écran) ; le relevé est gardé entre deux appels et son intervalle s'allonge
    avec le nombre de pistes (« interval ») ; au plus comspec_atak_native_toc_tracks_max pistes (« cap »), les joueurs
    d'abord ; munitions lues au plus 25 unités par relevé, une fois toutes les 30 s par unité.
    Unités suivies : joueurs, membres des groupes qui ont un joueur, unités ou groupes désignés par la mission
    (setVariable ["COMSPEC_ATAK_TocTrack", true, true] ; false : jamais suivi), véhicules et aéronefs avec équipage
    (un seul nœud, occupants comptés), chefs des groupes d'IA ; toutes les IA si comspec_atak_native_toc_tracks_ai
    vaut "all", aucune IA hors groupe de joueurs si "none".

    Nœud publié dans COMSPEC_ATAK_TocFeed_<camp> (champs 0 à 8 inchangés depuis la 4.4, 9 à 15 ajoutés en 4.11.0) :
      0 clé (netId de l'unité, du chef de bord pour un véhicule)   1 indicatif   2 [x, y]   3 cap
      4 état (fn_tocNodeStatus : 0 en direct, 1 ancien, 2 perdu, 3 hors ligne, 4 source inconnue)
      5 genre "inf" | "veh" | "armor" | "air" | "boat"   6 IA   7 groupe   8 occupants
      9 source "J" jeu | "A" réseau ATAK | "M" les deux
      10 santé : 0 opérationnel | 1 blessé | 2 inconscient | 3 tué (piste gardée 5 min à l'endroit de la mort, état 2 « perdu »)
      11 [altitude sol en m, vitesse en km/h] (véhicule, aéronef ; [] à pied)
      12 intégrité en % (unité ou véhicule ; -1 inconnue)
      13 munitions : 0 inconnues | 1 rouge | 2 ambre | 3 vert (arme principale, à pied seulement)
      14 téléphone : [] sans ATAK, sinon [état de sa liaison (0 à 4, comme le champ 4)]
      15 [sous-genre "" | "heli" | "plane" | "uav", nom du véhicule]
    État du flux (4e élément) : [brouilleurs, relais, joueurs du camp, IA du camp,
                                 [joueurs sur le réseau ATAK, IA sur le réseau ATAK, pistes affichées, pistes en tout,
                                  réglage "MIX" | "GAME" | "ATAK", plafond]]
    ORBAT du jeu, publié aux mêmes lecteurs, seulement quand il change : COMSPEC_ATAK_TocOrbat_<camp> =
      [serverTime, groupes, ordres du camp (fn_tocInbox « board »)]
      groupe : [nom, netId, chef (indicatif ou nom), chef joueur, vivants, effectif le plus haut vu, joueurs vivants,
                genre, véhicule, blessés, munitions les plus basses (0 à 3), santé la plus grave (0 à 3),
                membres [[nom, rôle, joueur, santé]...] (14 au plus)]

    Modes :
      ["mode"]                          -> "MIX" | "GAME" | "ATAK"
      ["max"]                           -> plafond de pistes (20 à 400)
      ["interval", nombre de pistes]    -> secondes entre deux relevés (pure)
      ["cap", [pistes, plafond]]        -> [pistes gardées, total] (pure ; piste : [priorité 0-5, nœud, clés des membres, locale])
      ["merge", [nœuds des téléphones, pistes, réglage, plafond]] -> [nœuds, affichés, total] (pure)
      ["collect", camp]                 serveur -> [pistes, [joueurs, IA], instant du relevé, intervalle]
      ["nodes", [camp, nœuds des téléphones, joueurs porteurs, IA porteuses]] serveur -> [nœuds, extension de l'état]
      ["orbat", camp]                   partout -> groupes du camp lus dans le jeu
      ["publish", [camp, propriétaires des lecteurs]] serveur (fn_tocFeed « tick ») : ORBAT et ordres, sur changement
      ["dirty", camp]                   serveur : à republier au prochain tour (ordre émis, accusé reçu)
      ["active", camp]                  serveur -> ce camp a un TOC (salle, écran ou PC) et au moins un joueur
      ["killed", entité]                serveur (EntityKilled) : perte comptée, piste « tué », ligne du flux
      ["selfTest"]                      -> [réussis, noms des cas en échec]
    Exemple de mission : _groupe setVariable ["COMSPEC_ATAK_TocTrack", true, true];   // ce groupe d'IA est suivi en entier
*/
params [["_mode", "nodes"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocTracks;
// Appel direct d'un joueur par remoteExec : refusé (seul fn_tocRoom « req », qui vérifie l'émetteur, ouvre la suite).
if (isServer && {isRemoteExecuted} && {!(remoteExecutedOwner in [0, 2])} && {isNil "_tocTrusted"} && {_mode in ["collect", "nodes", "publish", "dirty", "killed"]}) exitWith { false };
// ---- PALIERS (JSON) : [pistes au plus, secondes entre deux relevés].
private _steps = [[50, 3], [100, 5], [200, 8], [400, 12]];
// ---- FIN PALIERS
switch (_mode) do {
    case "mode": {
        private _v = missionNamespace getVariable ["comspec_atak_native_toc_source", "mix"];
        if !(_v isEqualType "") then { _v = "mix"; };
        (createHashMapFromArray [["game", "GAME"], ["atak", "ATAK"], ["mix", "MIX"]]) getOrDefault [toLower _v, "MIX"]
    };
    case "max": {
        private _v = missionNamespace getVariable ["comspec_atak_native_toc_tracks_max", 150];
        if !(_v isEqualType 0) then { _v = 150; };
        ((round _v) max 20) min 400
    };
    case "interval": {
        private _n = [0, _a] select (_a isEqualType 0);
        private _i = _steps findIf { _n <= (_x select 0) };
        if (_i < 0) then { _i = (count _steps) - 1; };
        (_steps select _i) select 1
    };
    case "cap": {
        _a params [["_tracks", []], ["_max", 150]];
        _max = _max max 0;
        private _total = count _tracks;
        if (_total <= _max) exitWith { [_tracks, _total] };
        // Plafond atteint : joueurs (0), leurs groupes (1), unités désignées (2), véhicules (3), chefs d'IA (4), autres IA (5).
        private _kept = [];
        for "_p" from 0 to 5 do {
            { if ((count _kept) < _max) then { _kept pushBack _x; }; } forEach (_tracks select { (_x select 0) isEqualTo _p });
        };
        [_kept, _total]
    };
    case "merge": {
        _a params [["_phone", []], ["_tracks", []], ["_m", "MIX"], ["_max", 150]];
        // Nœud d'un téléphone complété aux champs de la 4.11.0 (copie : le nœud reçu n'est pas modifié).
        private _pad = {
            params ["_n"];
            private _o = +_n;
            private _fr = _o param [4, 4];
            if ((count _o) < 16) then { _o resize 16; };
            {
                _x params ["_i", "_def"];
                if (isNil { _o select _i }) then { _o set [_i, _def]; };
            } forEach [[0, ""], [1, ""], [2, [0, 0]], [3, 0], [4, 4], [5, "inf"], [6, false], [7, ""], [8, 1], [10, 0], [11, []], [12, -1], [13, 0], [15, ["", ""]]];
            _o set [9, "A"];
            _o set [14, [_fr]];
            _o
        };
        if (_m isEqualTo "ATAK") exitWith {
            private _out = (_phone select [0, _max max 0]) apply { [_x] call _pad };
            [_out, count _out, count _phone]
        };
        (["cap", [_tracks, _max]] call _fn) params ["_kept", "_total"];
        private _out = _kept apply { +(_x select 1) };
        if (_m isEqualTo "GAME") exitWith { [_out, count _out, _total] };
        // Mixte : un téléphone et l'unité qui le porte ne font qu'un nœud (même identité : clé de l'unité, ou d'un
        // membre de l'équipage pour un véhicule). Un téléphone sans piste du jeu reste un nœud « A ».
        private _owner = createHashMap;
        { private _i = _forEachIndex; { _owner set [_x, _i]; } forEach (_x select 2); } forEach _kept;
        private _known = createHashMap;
        { { _known set [_x, true]; } forEach (_x select 2); } forEach _tracks;
        private _free = [];
        {
            private _k = _x param [0, ""];
            private _i = _owner getOrDefault [_k, -1];
            if (_i >= 0) then {
                private _n = _out select _i;
                private _fr = _x param [4, 4];
                private _old = (_n param [14, []]) param [0, 4];
                _n set [9, "M"];
                _n set [14, [[_old, _fr] select (_fr < _old)]];
            } else {
                if !(_k in _known) then { _free pushBack _x; };
            };
        } forEach _phone;
        { if ((count _out) < _max) then { _out pushBack ([_x] call _pad); }; } forEach _free;
        [_out, count _out, _total + (count _free)]
    };
    case "active": {
        if (!isServer || {!(_a isEqualType "")}) exitWith { false };
        private _side = _a;
        if (((missionNamespace getVariable [format ["COMSPEC_ATAK_TocRoom_%1", _side], []]) param [0, 0]) > 0) exitWith { true };
        if ((allPlayers findIf { (str side group _x) isEqualTo _side }) < 0) exitWith { false };
        ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) findIf { !isNull _x }) >= 0
            || {(count (missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []])) > 0}
            || {(allPlayers findIf { ((_x getVariable ["COMSPEC_ATAK_TocView", []]) param [0, ""]) isEqualTo _side }) >= 0}
    };
    case "collect": {
        if (!isServer || {!(_a isEqualType "")}) exitWith { [[], [0, 0], -1e9, 3] };
        private _side = _a;
        if (isNil "COMSPEC_ATAK_TocTrk") then {
            COMSPEC_ATAK_TocTrk = createHashMap;
            COMSPEC_ATAK_TocAmmo = createHashMap;
            COMSPEC_ATAK_TocMags = createHashMap;
            COMSPEC_ATAK_TocVeh = createHashMap;
            COMSPEC_ATAK_TocDead = createHashMap;
        };
        private _c = COMSPEC_ATAK_TocTrk getOrDefault [_side, [[], [0, 0], -1e9, 3]];
        // Relevé encore frais : rendu tel quel (le tour de fn_tocFeed est de 3 s, le relevé peut être plus espacé).
        if ((diag_tickTime - (_c select 2)) < ((_c select 3) - 0.5)) exitWith { _c };
        private _aiMode = missionNamespace getVariable ["comspec_atak_native_toc_tracks_ai", "leaders"];
        if !(_aiMode isEqualType "") then { _aiMode = "leaders"; };
        private _ammoBudget = 25;
        private _lifeOf = {
            params ["_u"];
            if (!alive _u) exitWith { 3 };
            if ((lifeState _u) isEqualTo "INCAPACITATED" || {(_u getVariable ["ACE_isUnconscious", false]) isEqualTo true}) exitWith { 2 };
            private _w = _u getVariable ["ace_medical_openWounds", []];
            if ((damage _u) >= 0.25 || {_w isEqualTypeAny [[], createHashMap] && {(count _w) > 0}}) exitWith { 1 };
            0
        };
        // Munitions de l'arme principale : chargeurs compatibles en réserve, plus celui qui est engagé.
        private _ammoOf = {
            params ["_u", "_key"];
            private _e = COMSPEC_ATAK_TocAmmo getOrDefault [_key, [0, -1e9]];
            if (diag_tickTime > (_e select 1) && {_ammoBudget > 0}) then {
                _ammoBudget = _ammoBudget - 1;
                private _w = primaryWeapon _u;
                private _lv = 0;
                if (_w isNotEqualTo "") then {
                    private _okMags = COMSPEC_ATAK_TocMags getOrDefault [_w, []];
                    if (_okMags isEqualTo []) then {
                        _okMags = (compatibleMagazines _w) apply { toLower _x };
                        COMSPEC_ATAK_TocMags set [_w, _okMags];
                    };
                    private _n = { (toLower _x) in _okMags } count (magazines _u);
                    if ((primaryWeaponMagazine _u) isNotEqualTo []) then { _n = _n + 1; };
                    _lv = switch (true) do { case (_n >= 5): { 3 }; case (_n >= 3): { 2 }; default { 1 }; };
                };
                _e = [_lv, diag_tickTime + 30 + random 10];
                COMSPEC_ATAK_TocAmmo set [_key, _e];
            };
            _e select 0
        };
        private _tracks = [];
        private _byVeh = createHashMap;
        private _vehNames = createHashMap;
        private _nP = 0;
        private _nAi = 0;
        {
            private _g = _x;
            if ((str side _g) isNotEqualTo _side) then { continue };
            private _us = (units _g) select { alive _x };
            if (_us isEqualTo []) then { continue };
            private _hasPl = (_us findIf { isPlayer _x }) >= 0;
            private _ld = leader _g;
            private _gTag = _g getVariable ["COMSPEC_ATAK_TocTrack", 0];
            private _gName = groupId _g;
            {
                private _u = _x;
                private _isP = isPlayer _u;
                if (_isP) then { _nP = _nP + 1; } else { _nAi = _nAi + 1; };
                private _tag = _u getVariable ["COMSPEC_ATAK_TocTrack", _gTag];
                if (_tag isEqualTo false) then { continue };
                private _v = vehicle _u;
                private _inVeh = _v isNotEqualTo _u && {!(_v isKindOf "ParachuteBase")};
                private _prio = switch (true) do {
                    case (_isP): { 0 };
                    case (_hasPl): { 1 };
                    case (_tag isEqualTo true): { 2 };
                    case (_aiMode isEqualTo "none"): { -1 };
                    case (_inVeh): { 3 };
                    case (_u isEqualTo _ld): { 4 };
                    case (_aiMode isEqualTo "all"): { 5 };
                    default { -1 };
                };
                if (_prio < 0) then { continue };
                private _key = netId _u;
                private _life = [_u] call _lifeOf;
                private _cs = [_u, true] call comspec_atak_native_fnc_unitCallsign;
                // Jamais le pseudo : fn_tocDisplayName ; un chef d'IA qui tient lieu de son groupe porte le nom du groupe.
                if (_cs isEqualTo "") then { _cs = if (!_isP && {_u isEqualTo _ld}) then { _gName } else { [_u] call comspec_atak_native_fnc_tocDisplayName }; };
                // Équipage d'un véhicule : un seul nœud, au nom du chef de bord, occupants comptés.
                if (_inVeh) then {
                    private _vk = netId _v;
                    private _i = _byVeh getOrDefault [_vk, -1];
                    if (_i >= 0) then {
                        private _t = _tracks select _i;
                        private _n = _t select 1;
                        _n set [8, (_n select 8) + 1];
                        (_t select 2) pushBack _key;
                        if (_prio < (_t select 0)) then { _t set [0, _prio]; };
                        if (_isP) then { _n set [6, false]; };
                        if (_life > (_n select 10)) then { _n set [10, _life]; };
                        if (_u isEqualTo (effectiveCommander _v)) then { _n set [0, _key]; _n set [1, _cs select [0, 28]]; _n set [7, _gName]; };
                        continue
                    };
                    _byVeh set [_vk, count _tracks];
                };
                private _pos = getPosASL _v;
                private _kind = "inf";
                private _sub = "";
                private _vn = "";
                private _ext = [];
                private _hp = round ((1 - ((damage _u) min 1)) * 100);
                private _ammo = 0;
                if (_inVeh) then {
                    _kind = switch (true) do {
                        case (_v isKindOf "Air"): { "air" };
                        case (_v isKindOf "Ship"): { "boat" };
                        case (_v isKindOf "Tank" || {_v isKindOf "Wheeled_APC_F"}): { "armor" };
                        default { "veh" };
                    };
                    _sub = switch (true) do {
                        case (unitIsUAV _v): { "uav" };
                        case (_v isKindOf "Helicopter"): { "heli" };
                        case (_v isKindOf "Plane"): { "plane" };
                        default { "" };
                    };
                    _vn = (getText (configOf _v >> "displayName")) select [0, 24];
                    if (_sub isEqualTo "uav") then { _cs = _vn; };
                    _ext = [round (((getPosATL _v) select 2) max 0), round (abs (speed _v))];
                    _hp = round ((1 - ((damage _v) min 1)) * 100);
                    _vehNames set [netId _v, [_side, _vn, _gName]];
                } else {
                    _ammo = [_u, _key] call _ammoOf;
                };
                _tracks pushBack [_prio, [_key, _cs select [0, 28], [round (_pos select 0), round (_pos select 1)], round (getDir _v), 0, _kind, !_isP, _gName, 1,
                    "J", _life, _ext, _hp, _ammo, [], [_sub, _vn]], [_key], local _u];
            } forEach _us;
        } forEach allGroups;
        // Tués depuis moins de 5 minutes : la piste reste à l'endroit de la mort, santé « tué ».
        private _dead = (COMSPEC_ATAK_TocDead getOrDefault [_side, []]) select { (_x select 0) > diag_tickTime };
        COMSPEC_ATAK_TocDead set [_side, _dead];
        { _tracks pushBack [2, +(_x select 1), [(_x select 1) select 0], true]; } forEach _dead;
        { COMSPEC_ATAK_TocVeh set [_x, _y]; } forEach _vehNames;
        _c = [_tracks, [_nP, _nAi], diag_tickTime, ["interval", count _tracks] call _fn];
        COMSPEC_ATAK_TocTrk set [_side, _c];
        // Ménage : unités et véhicules disparus.
        if ((count COMSPEC_ATAK_TocAmmo) > 800) then { COMSPEC_ATAK_TocAmmo = createHashMap; };
        if ((count COMSPEC_ATAK_TocVeh) > 400) then { COMSPEC_ATAK_TocVeh = _vehNames; };
        _c
    };
    case "nodes": {
        _a params [["_side", ""], ["_phone", []], ["_netP", 0], ["_netAi", 0]];
        if (!isServer) exitWith { [_phone, [_netP, _netAi, count _phone, count _phone, "ATAK", 150], [0, 0]] };
        private _m = ["mode"] call _fn;
        private _max = ["max"] call _fn;
        (["collect", _side] call _fn) params ["_tracks", "_strength", "_at", "_iv"];
        private _age = (diag_tickTime - _at) max 0;
        // État de chaque piste du jeu : en direct tant que le relevé est frais (fn_tocNodeStatus).
        // Piste d'un tué : état « perdu » (2), pour que les écrans qui ne lisent pas encore la santé ne le montrent pas en direct.
        {
            private _n = _x select 1;
            _n set [4, [["node", ["J", _x select 3, _age, _iv, true, false]] call comspec_atak_native_fnc_tocNodeStatus, 2] select ((_n select 10) isEqualTo 3)];
        } forEach _tracks;
        (["merge", [_phone, _tracks, _m, _max]] call _fn) params ["_nodes", "_shown", "_total"];
        [_nodes, [_netP, _netAi, _shown, _total, _m, _max], _strength]
    };
    case "orbat": {
        if !(_a isEqualType "") exitWith { [] };
        private _side = _a;
        private _seen = missionNamespace getVariable ["COMSPEC_ATAK_TocOrbatMax", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_TocOrbatMax", _seen];
        private _ammoMap = missionNamespace getVariable ["COMSPEC_ATAK_TocAmmo", createHashMap];
        private _lifeOf = {
            params ["_u"];
            if (!alive _u) exitWith { 3 };
            if ((lifeState _u) isEqualTo "INCAPACITATED" || {(_u getVariable ["ACE_isUnconscious", false]) isEqualTo true}) exitWith { 2 };
            if ((damage _u) >= 0.25) exitWith { 1 };
            0
        };
        private _rows = [];
        {
            private _g = _x;
            if ((str side _g) isNotEqualTo _side) then { continue };
            private _us = (units _g) select { alive _x };
            if (_us isEqualTo []) then { continue };
            private _ld = leader _g;
            private _v = vehicle _ld;
            // Drone : son équipage est un groupe du jeu, pas un élément de l'ORBAT.
            if (unitIsUAV _v) then { continue };
            private _gk = netId _g;
            private _top = (_seen getOrDefault [_gk, 0]) max (count _us);
            _seen set [_gk, _top];
            private _kind = switch (true) do {
                case (_v isEqualTo _ld || {_v isKindOf "ParachuteBase"}): { "inf" };
                case (_v isKindOf "Air"): { "air" };
                case (_v isKindOf "Ship"): { "boat" };
                case (_v isKindOf "Tank" || {_v isKindOf "Wheeled_APC_F"}): { "armor" };
                default { "veh" };
            };
            private _wia = 0;
            private _worst = 0;
            private _low = 9;
            private _members = [];
            {
                private _u = _x;
                private _life = [_u] call _lifeOf;
                if (_life > 0) then { _wia = _wia + 1; };
                if (_life > _worst) then { _worst = _life; };
                private _am = (_ammoMap getOrDefault [netId _u, [0, 0]]) select 0;
                if (_am > 0 && {_am < _low}) then { _low = _am; };
                if ((count _members) < 14) then {
                    private _role = roleDescription _u;
                    if (_role isEqualTo "") then { _role = getText (configOf _u >> "displayName"); };
                    // « Chef d'équipe@ALPHA » (nom de groupe du lobby) : seul le rôle est gardé.
                    _role = (_role splitString "@") param [0, _role];
                    _members pushBack [([_u] call comspec_atak_native_fnc_tocDisplayName) select [0, 32], _role select [0, 28], isPlayer _u, _life];
                };
            } forEach _us;
            private _lead = [_ld, true] call comspec_atak_native_fnc_unitCallsign;
            if (_lead isEqualTo "") then { _lead = [_ld] call comspec_atak_native_fnc_tocDisplayName; };
            _rows pushBack [groupId _g, _gk, _lead select [0, 28], isPlayer _ld, count _us, _top, { isPlayer _x } count _us, _kind,
                [(getText (configOf _v >> "displayName")) select [0, 24], ""] select (_kind isEqualTo "inf"), _wia, [_low, 0] select (_low > 3), _worst, _members];
        } forEach allGroups;
        _rows sort true;
        if ((count _seen) > 600) then { missionNamespace setVariable ["COMSPEC_ATAK_TocOrbatMax", createHashMap]; };
        _rows select [0, 80]
    };
    case "dirty": {
        if (!isServer || {isNil "COMSPEC_ATAK_TocPub"}) exitWith { false };
        private _st = COMSPEC_ATAK_TocPub getOrDefault [_a, []];
        if ((count _st) >= 3) then { _st set [2, -1e9]; };
        true
    };
    case "publish": {
        _a params [["_side", ""], ["_owners", []]];
        if (!isServer || {_side isEqualTo ""}) exitWith { false };
        if (isNil "COMSPEC_ATAK_TocPub") then { COMSPEC_ATAK_TocPub = createHashMap; };
        // [empreinte envoyée, lecteurs servis, instant du dernier relevé de l'ORBAT]
        private _st = COMSPEC_ATAK_TocPub getOrDefault [_side, [0, [], -1e9]];
        private _var = format ["COMSPEC_ATAK_TocOrbat_%1", _side];
        // ORBAT relu toutes les 15 s, tout de suite après un ordre ou un accusé (« dirty »).
        if ((diag_tickTime - (_st select 2)) > 15 || {isNil { missionNamespace getVariable _var }}) then {
            missionNamespace setVariable [_var, [serverTime, ["orbat", _side] call _fn, ["board", _side] call comspec_atak_native_fnc_tocInbox]];
            _st set [2, diag_tickTime];
        };
        private _val = missionNamespace getVariable [_var, [0, [], []]];
        private _h = hashValue (_val select [1, 2]);
        private _all = _h isNotEqualTo (_st select 0);
        private _to = [_owners - (_st select 1), _owners] select _all;
        { _x publicVariableClient _var; } forEach _to;
        COMSPEC_ATAK_TocPub set [_side, [_h, [((_st select 1) arrayIntersect _owners) + _to, _owners] select _all, _st select 2]];
        true
    };
    case "killed": {
        if (!isServer || {!(_a isEqualType objNull)} || {isNull _a}) exitWith { false };
        private _u = _a;
        if (isNil "COMSPEC_ATAK_TocKillQ") then { COMSPEC_ATAK_TocKillQ = createHashMap; };
        // Véhicule suivi détruit : une ligne dans le flux et la main courante du camp.
        if !(_u isKindOf "CAManBase") exitWith {
            private _rec = (missionNamespace getVariable ["COMSPEC_ATAK_TocVeh", createHashMap]) getOrDefault [netId _u, []];
            if ((count _rec) < 3) exitWith { false };
            COMSPEC_ATAK_TocVeh deleteAt (netId _u);
            _rec params ["_vSide", "_vName", "_vGroup"];
            if !(["active", _vSide] call _fn) exitWith { false };
            private _p = getPosASL _u;
            ["push", [_vSide, [[dayTime, "HH:MM"] call BIS_fnc_timeToString, "PERTE", "URGENT", _vGroup, [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef,
                format ["Véhicule détruit : %1", _vName], [round (_p select 0), round (_p select 1)]]]] call comspec_atak_native_fnc_tocFeed;
            ["do", [_vSide, "log", ["PERTE", format ["Véhicule détruit : %1 (%2)", _vName, _vGroup]], "Jeu", ""]] call comspec_atak_native_fnc_tocRoom;
            true
        };
        private _side = str side group _u;
        if !(_side in ["WEST", "EAST", "GUER", "CIV"]) exitWith { false };
        if !(["active", _side] call _fn) exitWith { false };
        private _el = groupId group _u;
        private _p = getPosASL _u;
        private _wasPlayer = isPlayer _u;
        // Piste gardée 5 minutes, seulement si l'unité était suivie (joueur, ou présente au dernier relevé).
        private _key = netId _u;
        private _tracks = ((missionNamespace getVariable ["COMSPEC_ATAK_TocTrk", createHashMap]) getOrDefault [_side, [[]]]) select 0;
        private _i = _tracks findIf { _key in (_x select 2) };
        if (_i >= 0 || {_wasPlayer}) then {
            private _cs = [_u, true] call comspec_atak_native_fnc_unitCallsign;
            if (_cs isEqualTo "") then { _cs = [_u] call comspec_atak_native_fnc_tocDisplayName; };
            private _node = [_key, _cs select [0, 28], [round (_p select 0), round (_p select 1)], round (getDir _u), 0, "inf", !_wasPlayer, _el, 1, "J", 3, [], 0, 0, [], ["", ""]];
            if (isNil "COMSPEC_ATAK_TocDead") then { COMSPEC_ATAK_TocDead = createHashMap; };
            private _dead = COMSPEC_ATAK_TocDead getOrDefault [_side, []];
            _dead pushBack [diag_tickTime + 300, _node];
            if ((count _dead) > 40) then { _dead deleteAt 0; };
            COMSPEC_ATAK_TocDead set [_side, _dead];
            // Joueur tué : dit dans le flux (les IA ne font que monter le compteur, sans ligne chacune).
            if (_wasPlayer) then {
                ["push", [_side, [[dayTime, "HH:MM"] call BIS_fnc_timeToString, "PERTE", "URGENT", _el, [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef,
                    format ["Tué : %1", _cs], [round (_p select 0), round (_p select 1)]]]] call comspec_atak_native_fnc_tocFeed;
            };
        };
        // Pertes regroupées : une seule écriture de la salle par camp toutes les 5 s, même sous le feu.
        private _q = COMSPEC_ATAK_TocKillQ getOrDefault [_side, createHashMap];
        private _first = (count _q) isEqualTo 0;
        _q set [_el, (_q getOrDefault [_el, 0]) + 1];
        COMSPEC_ATAK_TocKillQ set [_side, _q];
        if (_first) then {
            [{
                params ["_side"];
                private _q = COMSPEC_ATAK_TocKillQ getOrDefault [_side, createHashMap];
                COMSPEC_ATAK_TocKillQ set [_side, createHashMap];
                { ["do", [_side, "kill", [_x, "kia", _y], "Jeu", ""]] call comspec_atak_native_fnc_tocRoom; } forEach _q;
            }, [_side], 5] call CBA_fnc_waitAndExecute;
        };
        true
    };
    case "selfTest": {
        private _ok = 0;
        private _bad = [];
        // ---- CAS INTERVALLE (JSON) : [pistes, secondes attendues].
        private _casesInterval = [[0, 3], [50, 3], [51, 5], [100, 5], [150, 8], [200, 8], [201, 12], [400, 12], [900, 12]];
        // ---- FIN CAS INTERVALLE
        { if ((["interval", _x select 0] call _fn) isEqualTo (_x select 1)) then { _ok = _ok + 1; } else { _bad pushBack format ["intervalle %1", _x select 0]; }; } forEach _casesInterval;
        // ---- CAS FUSION (JSON) : [nom, téléphones [[clé, état]...], pistes [[priorité, clé, [membres]]...], réglage,
        //      plafond, nœuds attendus « clé:source:état du téléphone » (-1 : sans téléphone), affichés, total].
        private _casesMerge = [
            ["aucun téléphone, tout le monde est vu", [], [[0, "p1", ["p1"]], [4, "a1", ["a1"]]], "MIX", 150, ["p1:J:-1", "a1:J:-1"], 2, 2],
            ["un joueur et son téléphone : un seul nœud", [["p1", 1]], [[0, "p1", ["p1"]], [0, "p2", ["p2"]]], "MIX", 150, ["p1:M:1", "p2:J:-1"], 2, 2],
            ["équipage : le téléphone d'un passager rejoint le véhicule", [["p3", 0]], [[0, "p1", ["p1", "p2", "p3"]]], "MIX", 150, ["p1:M:0"], 1, 1],
            ["deux téléphones dans un véhicule : le meilleur état", [["p1", 2], ["p2", 0]], [[0, "p1", ["p1", "p2"]]], "MIX", 150, ["p1:M:0"], 1, 1],
            ["téléphone d'une unité non suivie : nœud ATAK en plus", [["x9", 3]], [[0, "p1", ["p1"]]], "MIX", 150, ["p1:J:-1", "x9:A:3"], 2, 2],
            ["réglage jeu : les téléphones ne sont pas lus", [["p1", 0], ["x9", 0]], [[0, "p1", ["p1"]]], "GAME", 150, ["p1:J:-1"], 1, 1],
            ["réglage réseau ATAK : seuls les porteurs", [["p1", 2]], [[0, "p1", ["p1"]], [0, "p2", ["p2"]]], "ATAK", 150, ["p1:A:2"], 1, 1],
            ["plafond : les joueurs d'abord", [], [[5, "a1", ["a1"]], [4, "a2", ["a2"]], [0, "p1", ["p1"]], [1, "a3", ["a3"]]], "MIX", 2, ["p1:J:-1", "a3:J:-1"], 2, 4],
            ["plafond : le téléphone d'une piste écartée ne revient pas en double", [["a1", 0]], [[0, "p1", ["p1"]], [5, "a1", ["a1"]]], "MIX", 1, ["p1:J:-1"], 1, 2],
            ["plafond en réglage réseau ATAK", [["p1", 0], ["p2", 0], ["p3", 0]], [], "ATAK", 2, ["p1:A:0", "p2:A:0"], 2, 3]
        ];
        // ---- FIN CAS FUSION
        {
            _x params ["_name", "_ph", "_tr", "_m", "_max", "_want", "_shown", "_total"];
            private _phone = _ph apply { [_x select 0, "", [0, 0], 0, _x select 1] };
            private _tracks = _tr apply { [_x select 0, [_x select 1, "", [0, 0], 0, 0, "inf", false, "", 1, "J", 0, [], 100, 0, [], ["", ""]], _x select 2, false] };
            (["merge", [_phone, _tracks, _m, _max]] call _fn) params ["_nodes", "_s", "_t"];
            private _got = _nodes apply { format ["%1:%2:%3", _x select 0, _x select 9, (_x select 14) param [0, -1]] };
            if (_got isEqualTo _want && {_s isEqualTo _shown} && {_t isEqualTo _total}) then { _ok = _ok + 1; } else { _bad pushBack _name; };
        } forEach _casesMerge;
        [_ok, _bad]
    };
    default { false };
};
