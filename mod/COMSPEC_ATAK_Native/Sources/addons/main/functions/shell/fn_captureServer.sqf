/*
    Téléphones ATAK ramassés par l'IA ennemie (serveur).
    - Ramassage : une IA ennemie à moins de comspec_atak_native_capture_radius m d'un téléphone de joueur (corps,
      joueur inconscient ou menotté, téléphone posé au sol, corps d'une IA qui en portait un) va le chercher et le prend.
      Le téléphone passe dans son inventaire et garde son numéro et son IMEI (on peut le retrouver par GÉOLOC).
    - Exploitation : tant que la compromission n'est pas validée, le téléphone volé reste sur le réseau du camp et voit
      le BFT : le groupe du porteur (et jusqu'à 3 groupes alliés à moins de comspec_atak_native_capture_share m)
      apprend la position des joueurs dont la balise BFT est active à moins de comspec_atak_native_capture_hunt_radius m
      et part les traquer (point de passage « chercher et détruire », réglage comspec_atak_native_capture_hunt).
      Code de verrouillage : l'IA met comspec_atak_native_capture_crack s à le casser. Téléphone effacé avant la capture :
      rien d'exploitable. Le réseau voit le téléphone se connecter : dernière position connue envoyée au camp.
    - Alerte : les ATAK du camp reçoivent « Compromission suspectée » (fn_compromise), avec l'IMEI et le numéro.
      Un chef de groupe, le propriétaire ou un Zeus valide (ou rejette) depuis l'app Alertes. Validée : le téléphone
      est exclu du réseau (plus d'exploitation), Athena révoque le certificat du terminal (COMSPEC Link
      « CompromiseTerminal », état « compromised »), et le propriétaire reçoit un nouvel IMEI sur son prochain téléphone.
    Mission : groupe ou unité IA avec la variable COMSPEC_ATAK_NoPickup (ne ramasse pas) ou COMSPEC_ATAK_NoHunt (ne traque pas).
    État partagé : missionNamespace COMSPEC_ATAK_CaptureList (public) = [[id, camp, indicatif, numéro, IMEI, grille de capture,
      heure, état ("suspected" | "validated" | "dismissed"), validé par, Athena ("" | "pending" | "ok" | "offline"),
      UID du propriétaire, dernière grille vue, dernière position vue, effacé, porteur actif, position de capture]...]
    Params : [action, arguments]
      "init" (postInit serveur)   "tick" (3 s)   "exploit" (5 s)   "dropped" [porte-objet]
      "validate" [id, unité]   "dismiss" [id, unité]   "athena" [id, ok, unité]
        Ces trois-là sont appelées par un client (remoteExecCall vers 2, CfgRemoteExec) : l'unité passée y est
        REMPLACÉE par celle du poste émetteur, retrouvée par remoteExecutedOwner (fn_reqUnit). Un appel du serveur
        hors de toute pile distante (hôte, solo) garde l'unité qu'il passe. fn_adminServer (app Admin) tourne dans la
        pile du remoteExec de l'admin : l'admin y est recalculé à l'identique. « athena » (résultat de la révocation)
        n'est accepté que du joueur à qui le serveur l'a demandée (UID retenu dans « asked »), tant qu'elle est en attente.
      "local" [mode, objet, valeur] : sur la machine où l'objet est local ("strip" | "give" | "move" | "pick" | "hunt")
      "giveCheck" [IA, classe, nombre avant, porte-objets proches avant, position, heure] : lancée par « tick » après
        l'ordre « give ». Le serveur constate lui-même si l'IA a reçu le téléphone ; sinon (inventaire plein sur la
        machine de l'IA) il cherche le porte-objet posé à côté d'elle et y reporte la capture (« giveFail » [IA,
        porte-objet, position]). Aucun client ne les envoie : absentes de la liste des actions distantes.
    Debug (app Admin, fn_adminServer) : chaque capture garde « targets » (joueurs repérés) et « hunters » (groupes en traque).
*/
params [["_act", "tick"], ["_arg", []]];
// Demande venue d'un client : seul remoteExecutedOwner fait foi. L'acteur annoncé dans la demande est écarté (un
// client passait n'importe quel chef de groupe du camp du téléphone pour faire valider la compromission, « validate »
// plus bas, ce qui coupe le téléphone du réseau, impose un nouvel IMEI au propriétaire et demande la révocation du
// certificat du terminal chez Athena). « isServer » dans le test : la branche « local » tourne aussi chez les clients
// (événement capLocal), elle n'y est pas filtrée. Sur le serveur, « local » est refusée dans la pile d'un remoteExec
// client, même quand c'est le serveur qui lève capLocal dans cette pile : fn_adminServer « recover » retire donc
// lui-même le téléphone d'une IA locale au serveur.
private _remote = isServer && {isMultiplayer} && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2};
private _req = objNull;
if (_remote) then { _req = [remoteExecutedOwner] call comspec_atak_native_fnc_reqUnit; };
if (_remote && {!(_act in ["dropped", "validate", "dismiss", "athena"])}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][CAPTURE] Action « %1 » refusée au poste %2 (réservée au serveur)", _act, remoteExecutedOwner];
    false
};
if (_remote && {isNull _req}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][CAPTURE] « %1 » refusé au poste %2 (demandeur introuvable)", _act, remoteExecutedOwner];
    false
};
// Acteur des actions qui portent un droit : le demandeur, jamais l'argument.
if (_remote && {_act in ["validate", "dismiss"]}) then { _arg = [_arg param [0, ""], _req]; };
if (_remote && {_act isEqualTo "athena"}) then { _arg = [_arg param [0, ""], _arg param [1, false], _req]; };
private _now = [time, serverTime] select isMultiplayer;
private _sideOf = {
    params ["_s"];
    switch (_s) do { case "WEST": { west }; case "EAST": { east }; case "GUER": { resistance }; case "CIV": { civilian }; default { sideUnknown }; }
};
private _publish = {
    private _l = [];
    {
        private _c = _y;
        private _t = _c get "tag";
        _l pushBack [_c get "id", _t param [1, ""], _t param [2, ""], _t param [3, ""], _t param [4, ""], _c get "grid", _c get "hour",
            _c get "state", _c get "by", _c get "athena", _t param [0, ""], _c get "lastGrid", _c get "lastPos", _c get "wiped",
            !isNull (_c get "carrier") && {alive (_c get "carrier")}, _c get "pos"];
    } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap]);
    _l sort true;
    missionNamespace setVariable ["COMSPEC_ATAK_CaptureList", _l select [((count _l) - 20) max 0, 20], true];
};
private _toSide = {
    params ["_side", "_kind", "_id"];
    private _l = missionNamespace getVariable ["COMSPEC_ATAK_CaptureList", []];
    private _i = _l findIf { (_x select 0) isEqualTo _id };
    private _row = if (_i >= 0) then { _l select _i } else { [] };
    private _to = allPlayers select { (str side group _x) isEqualTo _side };
    // remoteExecCall (et non l'événement CBA comspec_atak_native_compromise, que tout client pouvait lever) : le joueur
    // voit que l'ordre vient du serveur et n'accepte que celui-là (fn_compromise). Même voie pour « revoke » et « ownerReset ».
    if (_to isNotEqualTo [] && {_row isNotEqualTo []}) then { ["event", [_kind, _row]] remoteExecCall ["comspec_atak_native_fnc_compromise", _to]; };
};
private _items = {
    params ["_o", "_kind"];
    private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
    if (_kind isEqualTo "holder") exitWith { (itemCargo _o) select { (toLower _x) in _cat } };
    ((assignedItems _o) + (items _o)) select { (toLower _x) in _cat }
};

switch (_act) do {
    case "init": {
        if (!isServer) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_Captures", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_CapGo", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_CapHolders", []];
        missionNamespace setVariable ["COMSPEC_ATAK_CaptureList", [], true];
        [{ ["tick"] call comspec_atak_native_fnc_captureServer; }, 3] call CBA_fnc_addPerFrameHandler;
        [{ ["exploit"] call comspec_atak_native_fnc_captureServer; }, 5] call CBA_fnc_addPerFrameHandler;
    };
    case "dropped": {
        _arg params [["_h", objNull]];
        if (isNull _h) exitWith {};
        private _l = missionNamespace getVariable ["COMSPEC_ATAK_CapHolders", []];
        _l pushBackUnique _h;
        missionNamespace setVariable ["COMSPEC_ATAK_CapHolders", _l select { !isNull _x }];
    };
    case "giveCheck": {
        // Après l'ordre « give » (tick) : l'IA a-t-elle reçu le téléphone ? Le serveur le constate lui-même, sans
        // message de la machine de l'IA. Avant, celle-ci levait l'événement CBA comspec_atak_native_captureSrv
        // « giveFail », que tout client pouvait lever, et que ce lot retire (un client y validait une compromission
        // au nom d'un chef ou posait le résultat de la révocation chez Athena).
        if (!isServer) exitWith {};
        _arg params [["_ai", objNull], ["_cls", ""], ["_had", 0], ["_old", []], ["_p0", []], ["_t0", 0]];
        if (isNull _ai || {_cls isEqualTo ""} || {_p0 isEqualTo []}) exitWith {};
        // Reçu : un exemplaire de plus qu'avant l'ordre.
        if (({ _x == _cls } count ((assignedItems _ai) + (items _ai))) > _had) exitWith {};
        // Pas reçu : porte-objet apparu depuis l'ordre à moins de 10 m, qui contient cette classe et ne porte ni
        // capture ni téléphone de joueur (un téléphone lâché par un joueur garde son propriétaire).
        private _gh = (nearestObjects [_p0, ["GroundWeaponHolder"], 10]) select {
            !(_x in _old) && {((itemCargo _x) findIf { _x == _cls }) >= 0}
            && {(_x getVariable ["COMSPEC_ATAK_CapOf", ""]) isEqualTo ""} && {(_x getVariable ["COMSPEC_ATAK_PhoneTag", []]) isEqualTo []}
            && {isNull (_x getVariable ["COMSPEC_ATAK_PhoneOwner", objNull])}
        };
        if (_gh isNotEqualTo []) exitWith { ["giveFail", [_ai, _gh select 0, _p0]] call comspec_atak_native_fnc_captureServer; };
        // Ni l'un ni l'autre encore : inventaire d'une IA distante (client sans interface, groupe d'un joueur) ou
        // porte-objet pas encore arrivés au serveur. Nouvel essai toutes les 2 s, pendant 20 s.
        if ((_now - _t0) < 20) exitWith { [{ ["giveCheck", _this] call comspec_atak_native_fnc_captureServer; }, _arg, 2] call CBA_fnc_waitAndExecute; };
        diag_log format ["[COMSPEC ATAK NATIVE][WARN][CAPTURE] %1 : %2 ni reçu par %3 ni posé au sol en %4 s", _ai getVariable ["COMSPEC_ATAK_CapOf", ""], _cls, typeOf _ai, round (_now - _t0)];
    };
    case "giveFail": {
        // « give » sans place chez l'IA (constaté par « giveCheck ») : le téléphone a été posé au sol (porte-objet _gh).
        // La capture déjà ouverte passe sur ce porte-objet (même id, reprise possible par une autre IA) et l'IA n'en
        // est plus le porteur. Action interne du serveur : aucun client ne l'envoie (absente de la liste distante).
        if (!isServer) exitWith {};
        _arg params [["_ai", objNull], ["_gh", objNull], ["_p0", []]];
        if (isNull _ai || {isNull _gh} || {_p0 isEqualTo []}) exitWith {};
        if (((_gh distance2D _p0) > 10) || {([_gh, "holder"] call _items) isEqualTo []}) exitWith {};
        private _caps = missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap];
        private _cid = _ai getVariable ["COMSPEC_ATAK_CapOf", ""];
        if !(_cid isEqualType "" && {_cid in _caps}) exitWith {};
        private _c = _caps get _cid;
        _gh setVariable ["COMSPEC_ATAK_PhoneTag", _c get "tag", true];
        _gh setVariable ["COMSPEC_ATAK_CapOf", _cid, true];
        ["dropped", [_gh]] call comspec_atak_native_fnc_captureServer;
        if ((_c get "carrier") isEqualTo _ai) then { _c set ["carrier", objNull]; };
        _ai setVariable ["COMSPEC_ATAK_CapOf", nil, true];
        _ai setVariable ["COMSPEC_ATAK_Ident", nil, true];
        call _publish;
        ["INFO", "CAPTURE", format ["%1 : inventaire de %2 plein, téléphone posé au sol (%3)", _cid, typeOf _ai, [getPosATL _gh, 8] call comspec_atak_native_fnc_gridRef]] call comspec_atak_native_fnc_log;
    };
    case "local": {
        _arg params [["_mode", ""], ["_o", objNull], ["_v", ""]];
        if (isNull _o || {!local _o}) exitWith {};
        switch (_mode) do {
            case "strip": {
                if (_v in (assignedItems _o)) then { _o unlinkItem _v; } else { _o removeItem _v; };
            };
            case "give": {
                // Plus de place (inventaire changé depuis la vérification du serveur) : le téléphone est posé au sol à
                // côté de l'IA plutôt que perdu. Le serveur le constate lui-même (« giveCheck ») et y reporte la capture
                // (« giveFail ») pour qu'il reste ramassable et que l'IA sans téléphone ne l'exploite plus. Cette
                // machine n'envoie rien au serveur.
                if (_o canAdd _v) then { _o addItem _v; } else {
                    private _gh = createVehicle ["GroundWeaponHolder", getPosATL _o, [], 0.5, "CAN_COLLIDE"];
                    _gh addItemCargoGlobal [_v, 1];
                };
                _o playActionNow "PutDown";
                _o doFollow (leader group _o);
            };
            case "move": { _o doMove _v; };
            case "hunt": {
                _v params [["_targets", []], ["_hunt", true]];
                private _g = group _o;
                { _g reveal [_x select 0, 2.5]; } forEach _targets;
                if (!_hunt || {_targets isEqualTo []} || {_g getVariable ["COMSPEC_ATAK_NoHunt", false]}) exitWith {};
                private _best = _targets apply { [(_x select 1) distance2D (leader _g), _x select 1] };
                _best sort true;
                private _p = (_best select 0) select 1;
                if (((_g getVariable ["COMSPEC_ATAK_HuntPos", [-1e5, -1e5, 0]]) distance2D _p) < 80) exitWith {};
                _g setVariable ["COMSPEC_ATAK_HuntPos", _p];
                [_g] call CBA_fnc_clearWaypoints;
                private _wp = _g addWaypoint [_p, 25];
                _wp setWaypointType "SAD";
                _wp setWaypointBehaviour "AWARE";
                _wp setWaypointCombatMode "RED";
                _wp setWaypointSpeed "FULL";
                _g setCurrentWaypoint _wp;
            };
        };
    };
    case "tick": {
        if (!isServer || {!(missionNamespace getVariable ["comspec_atak_native_capture_enabled", true])}) exitWith {};
        private _caps = missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap];
        // Révocation Athena sans réponse dans les délais (joueur sollicité parti, liaison morte) : « offline » plutôt
        // qu'en attente pour le reste de la mission.
        private _late = false;
        {
            if ((_y getOrDefault ["athena", ""]) isEqualTo "pending" && {_now > (_y getOrDefault ["revokeBy", 1e9])}) then { _y set ["athena", "offline"]; _late = true; };
        } forEach _caps;
        if (_late) then { call _publish; };
        private _go = missionNamespace getVariable ["COMSPEC_ATAK_CapGo", createHashMap];
        private _R = (missionNamespace getVariable ["comspec_atak_native_capture_radius", 100]) max 5;
        // Sources : [objet, genre, étiquette du téléphone, id de capture déjà ouverte]
        private _src = [];
        {
            private _tag = _x getVariable ["COMSPEC_ATAK_PhoneTag", []];
            if (_tag isNotEqualTo []) then { _src pushBack [_x, "unit", _tag, ""]; continue };
            // Corps d'une IA qui portait un téléphone volé : un autre peut le reprendre.
            private _cid = _x getVariable ["COMSPEC_ATAK_CapOf", ""];
            if (_cid isNotEqualTo "" && {_cid in _caps}) then { _src pushBack [_x, "unit", (_caps get _cid) get "tag", _cid]; };
        } forEach allDeadMen;
        {
            if (alive _x && {(_x getVariable ["ACE_isUnconscious", false]) || {_x getVariable ["ace_captives_isHandcuffed", false]} || {(lifeState _x) isEqualTo "INCAPACITATED"}}) then {
                private _tag = _x getVariable ["COMSPEC_ATAK_PhoneTag", []];
                if (_tag isNotEqualTo []) then { _src pushBack [_x, "unit", _tag, ""]; };
            };
        } forEach allPlayers;
        {
            private _own = _x getVariable ["COMSPEC_ATAK_PhoneOwner", objNull];
            private _tag = _x getVariable ["COMSPEC_ATAK_PhoneTag", []];
            if (_tag isEqualTo [] && {!isNull _own}) then { _tag = _own getVariable ["COMSPEC_ATAK_PhoneTag", []]; };
            // Porte-objet d'un téléphone déjà capturé (« giveFail ») : la reprise garde la même capture.
            private _hc = _x getVariable ["COMSPEC_ATAK_CapOf", ""];
            if !(_hc isEqualType "" && {_hc in _caps}) then { _hc = ""; };
            if (_tag isNotEqualTo []) then { _src pushBack [_x, "holder", _tag, _hc]; };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_CapHolders", []]) select { !isNull _x });
        {
            _x params ["_o", "_kind", "_tag", "_cid"];
            private _it = [_o, _kind] call _items;
            if (_it isEqualTo []) then { continue };
            private _key = netId _o;
            private _sideO = [_tag param [1, ""]] call _sideOf;
            if (_sideO isEqualTo sideUnknown) then { continue };
            private _pos = getPosATL _o;
            (_go getOrDefault [_key, []]) params [["_ai", objNull], ["_t0", 0]];
            if (!isNull _ai && {!alive _ai || {(_now - _t0) > 120} || {(_ai distance _o) > _R * 1.5} || {(vehicle _ai) isNotEqualTo _ai}}) then {
                _ai setVariable ["COMSPEC_ATAK_CapGoing", nil];
                _ai = objNull;
                _go deleteAt _key;
            };
            if (isNull _ai) then {
                private _cand = (_pos nearEntities [["CAManBase"], _R]) select {
                    alive _x && {!isPlayer _x} && {(vehicle _x) isEqualTo _x} && {(lifeState _x) in ["HEALTHY", "INJURED"]}
                    && {!(_x getVariable ["ACE_isUnconscious", false])} && {!captive _x}
                    && {[_sideO, side group _x] call BIS_fnc_sideIsEnemy}
                    && {!(_x getVariable ["COMSPEC_ATAK_NoPickup", false])} && {!((group _x) getVariable ["COMSPEC_ATAK_NoPickup", false])}
                    && {isNil { _x getVariable "COMSPEC_ATAK_CapGoing" }}
                    && {_x canAdd (_it select 0)}
                };
                if (_cand isEqualTo []) exitWith {};
                _cand = _cand apply { [_x distance _o, _x] };
                _cand sort true;
                _ai = (_cand select 0) select 1;
                _go set [_key, [_ai, _now]];
                _ai setVariable ["COMSPEC_ATAK_CapGoing", _key];
                ["comspec_atak_native_capLocal", ["move", _ai, _pos], _ai] call CBA_fnc_targetEvent;
            };
            if (isNull _ai || {(_ai distance _o) > 4}) then { continue };
            // Ramassé.
            private _cls = _it select 0;
            _go deleteAt _key;
            _ai setVariable ["COMSPEC_ATAK_CapGoing", nil];
            // Inventaire plein arrivé sur place : le téléphone reste où il est (sinon retiré puis jamais donné, perdu)
            // et aucune capture n'est ouverte ; une autre IA (ou celle-ci, avec de la place) pourra le prendre.
            if !(_ai canAdd _cls) then { continue };
            if (_kind isEqualTo "holder") then {
                // Sans vider les uniformes et gilets posés dans le même porte-objet (fn_droppedPhone "remove").
                ["remove", [_o, _cls]] call comspec_atak_native_fnc_droppedPhone;
                // Porte-objet d'un « giveFail » : la capture repart avec l'IA, le porte-objet ne la porte plus.
                if (_cid isNotEqualTo "") then { _o setVariable ["COMSPEC_ATAK_CapOf", nil, true]; _o setVariable ["COMSPEC_ATAK_PhoneTag", nil, true]; };
            } else {
                ["comspec_atak_native_capLocal", ["strip", _o, _cls], _o] call CBA_fnc_targetEvent;
                // Le corps n'a plus de téléphone à donner.
                if !(alive _o) then { _o setVariable ["COMSPEC_ATAK_PhoneTag", nil, true]; _o setVariable ["COMSPEC_ATAK_CapOf", nil, true]; };
            };
            // État relevé avant l'ordre « give », pour que le serveur constate lui-même s'il a abouti (« giveCheck ») :
            // exemplaires de cette classe chez l'IA, porte-objets déjà là, position de l'IA.
            private _p0 = ASLToAGL (getPosASL _ai);
            private _chk = [_ai, _cls, { _x == _cls } count ((assignedItems _ai) + (items _ai)), nearestObjects [_p0, ["GroundWeaponHolder"], 12], _p0, _now];
            ["comspec_atak_native_capLocal", ["give", _ai, _cls], _ai] call CBA_fnc_targetEvent;
            [{ ["giveCheck", _this] call comspec_atak_native_fnc_captureServer; }, _chk, 2] call CBA_fnc_waitAndExecute;
            // Le téléphone garde son identité : on peut le géolocaliser par son numéro ou son IMEI.
            _ai setVariable ["COMSPEC_ATAK_Ident", [_tag param [3, ""], _tag param [4, ""], ""], true];
            private _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef;
            if (_cid isNotEqualTo "") then {
                private _c = _caps get _cid;
                _c set ["carrier", _ai];
                _ai setVariable ["COMSPEC_ATAK_CapOf", _cid, true];
                ["INFO", "CAPTURE", format ["%1 : téléphone repris par %2 (%3)", _cid, typeOf _ai, _grid]] call comspec_atak_native_fnc_log;
            } else {
                private _seq = (missionNamespace getVariable ["COMSPEC_ATAK_CapSeq", 0]) + 1;
                missionNamespace setVariable ["COMSPEC_ATAK_CapSeq", _seq];
                private _id = format ["CAP%1", _seq];
                private _crack = [0, (missionNamespace getVariable ["comspec_atak_native_capture_crack", 120]) max 0] select (_tag param [6, false]);
                private _c = createHashMapFromArray [
                    ["id", _id], ["tag", _tag], ["carrier", _ai], ["state", "suspected"], ["by", ""], ["athena", ""],
                    ["grid", _grid], ["hour", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["pos", [_pos select 0, _pos select 1, 0]],
                    ["t", _now], ["exploitAt", _now + 10 + _crack], ["wiped", _tag param [7, false]],
                    ["lastGrid", ""], ["lastPos", []], ["lastAct", -1e9], ["tried", []]
                ];
                _caps set [_id, _c];
                _ai setVariable ["COMSPEC_ATAK_CapOf", _id, true];
                call _publish;
                [_tag param [1, ""], "new", _id] call _toSide;
                ["INFO", "CAPTURE", format ["%1 : téléphone de %2 (IMEI %3) ramassé par %4 %5 (%6)", _id, _tag param [2, ""], _tag param [4, ""], side group _ai, typeOf _ai, _grid]] call comspec_atak_native_fnc_log;
            };
        } forEach _src;
    };
    case "exploit": {
        if (!isServer) exitWith {};
        private _caps = missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap];
        if ((count _caps) isEqualTo 0) exitWith {};
        private _every = (missionNamespace getVariable ["comspec_atak_native_capture_refresh", 30]) max 5;
        private _huntR = (missionNamespace getVariable ["comspec_atak_native_capture_hunt_radius", 3000]) max 100;
        private _share = missionNamespace getVariable ["comspec_atak_native_capture_share", 1500];
        private _hunt = missionNamespace getVariable ["comspec_atak_native_capture_hunt", true];
        private _changed = false;
        {
            private _c = _y;
            if ((_c get "state") isEqualTo "validated") then { continue };
            private _ai = _c get "carrier";
            if (isNull _ai || {!alive _ai}) then { continue };
            if (_now < (_c get "exploitAt") || {_c get "wiped"}) then { continue };
            if ((_now - (_c getOrDefault ["lastRun", -1e9])) < _every) then { continue };
            _c set ["lastRun", _now];
            private _side = (_c get "tag") param [1, ""];
            // Le téléphone volé se connecte : le réseau du camp voit sa cellule.
            private _p = getPosATL _ai;
            _c set ["lastPos", [_p select 0, _p select 1, 0]];
            _c set ["lastGrid", [_p, 6] call comspec_atak_native_fnc_gridRef];
            _changed = true;
            if ((_now - (_c get "lastAct")) >= 300) then {
                _c set ["lastAct", _now];
                call _publish;
                [_side, "activity", _c get "id"] call _toSide;
            };
            private _targets = (allPlayers select {
                alive _x && {(str side group _x) isEqualTo _side} && {_x getVariable ["COMSPEC_ATAK_Beacon", false]}
                && {(_x distance2D _ai) < _huntR} && {[_x] call comspec_atak_native_fnc_hasDevice}
            }) apply { [_x, getPosATL _x] };
            // Vu par l'app Admin (debug) : joueurs repérés et groupes lancés à leur poursuite.
            _c set ["targets", count _targets];
            if (_targets isEqualTo []) then { continue };
            private _grps = [group _ai];
            if (_share > 0) then {
                private _near = (allGroups select {
                    _x isNotEqualTo (group _ai) && {(side _x) isEqualTo (side group _ai)} && {alive leader _x} && {!isPlayer leader _x}
                    && {((leader _x) distance2D _ai) < _share} && {!(_x getVariable ["COMSPEC_ATAK_NoHunt", false])}
                }) apply { [(leader _x) distance2D _ai, _x] };
                _near sort true;
                _grps append ((_near select [0, 3]) apply { _x select 1 });
            };
            { ["comspec_atak_native_capLocal", ["hunt", leader _x, [_targets, _hunt]], leader _x] call CBA_fnc_targetEvent; } forEach _grps;
            _c set ["hunters", [[], _grps select { !(_x getVariable ["COMSPEC_ATAK_NoHunt", false]) }] select _hunt];
        } forEach _caps;
        if (_changed) then { call _publish; };
    };
    case "validate";
    case "dismiss": {
        // Droits vérifiés ici aussi (fn_compromise « can ») : chef de groupe, propriétaire du téléphone, Zeus, ou
        // réglage capture_validate ouvert à tous. Le camp est vérifié plus bas.
        private _rb = _arg param [1, objNull];
        private _rc = (missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap]) getOrDefault [_arg param [0, ""], createHashMap];
        if ((count _rc) > 0 && {!isNull _rb} && {!((missionNamespace getVariable ["comspec_atak_native_capture_validate", 0]) isEqualTo 1
            || {(leader group _rb) isEqualTo _rb}
            || {(getPlayerUID _rb) isEqualTo ((_rc get "tag") param [0, ""])}
            || {!isNull (getAssignedCuratorLogic _rb)}
            || {_rb getVariable ["COMSPEC_ATAK_AdminOk", false]})}) exitWith {
            ["WARNING", "CAPTURE", format ["%1 : %2 refusé à %3 (ni chef, ni propriétaire, ni Zeus)", _arg param [0, ""], _act, name _rb]] call comspec_atak_native_fnc_log;
        };
        _arg params [["_id", ""], ["_by", objNull]];
        private _c = (missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap]) getOrDefault [_id, createHashMap];
        if ((count _c) isEqualTo 0 || {isNull _by}) exitWith {};
        private _side = (_c get "tag") param [1, ""];
        // Camp du téléphone, Zeus, ou gestionnaire passé par l'app Admin (fn_adminServer, droit déjà vérifié).
        if ((str side group _by) isNotEqualTo _side && {isNull (getAssignedCuratorLogic _by)} && {!(_by getVariable ["COMSPEC_ATAK_AdminOk", false])}) exitWith {};
        if ((_c get "state") isEqualTo "validated") exitWith {};
        private _who = [_by] call comspec_atak_native_fnc_unitCallsign;
        _c set ["by", _who];
        if (_act isEqualTo "dismiss") exitWith {
            _c set ["state", "dismissed"];
            call _publish;
            [_side, "dismissed", _id] call _toSide;
        };
        _c set ["state", "validated"];
        // Sans identifiant de terminal, aucun client ne peut révoquer : « offline » tout de suite, sans chaîne d'essais.
        private _term = (_c get "tag") param [5, ""];
        _c set ["athena", ["offline", "pending"] select (_term isNotEqualTo "")];
        _c set ["revokeBy", _now + 45];
        _c set ["tried", [getPlayerUID _by]];
        // Joueur sollicité pour la révocation : seul à pouvoir en rendre le résultat (« athena »).
        _c set ["asked", getPlayerUID _by];
        call _publish;
        [_side, "validated", _id] call _toSide;
        if (_term isNotEqualTo "") then { ["revoke", [_id, _term, (_c get "tag") param [2, ""]]] remoteExecCall ["comspec_atak_native_fnc_compromise", _by]; };
        // Propriétaire : nouvel IMEI sur son prochain téléphone.
        private _uid = (_c get "tag") param [0, ""];
        private _i = allPlayers findIf { (getPlayerUID _x) isEqualTo _uid };
        if (_i >= 0) then { ["ownerReset", [_id]] remoteExecCall ["comspec_atak_native_fnc_compromise", allPlayers select _i]; };
        ["INFO", "CAPTURE", format ["%1 : compromission validée par %2", _id, _who]] call comspec_atak_native_fnc_log;
    };
    case "athena": {
        _arg params [["_id", ""], ["_ok", false], ["_from", objNull]];
        private _c = (missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap]) getOrDefault [_id, createHashMap];
        if ((count _c) isEqualTo 0) exitWith {};
        // Déjà réglée (révoquée, ou délai dépassé) : une réponse tardive ne change rien. Sinon, seul le joueur sollicité
        // (UID retenu à chaque envoi de « revoke ») répond. Avant, n'importe quel client posait « ok » (même avant toute
        // validation ; en attente, cela coupait la chaîne de reprise) ou passait la révocation au candidat suivant (« false »).
        if ((_c getOrDefault ["athena", ""]) isNotEqualTo "pending") exitWith {};
        private _asked = _c getOrDefault ["asked", ""];
        if (_asked isEqualTo "" || {(getPlayerUID _from) isNotEqualTo _asked}) exitWith {
            diag_log format ["[COMSPEC ATAK NATIVE][WARN][CAPTURE] %1 : résultat Athena de %2 refusé (joueur non sollicité)", _id, name _from];
        };
        if (_ok) exitWith { _c set ["athena", "ok"]; call _publish; };
        // Sans identifiant de terminal : inutile.
        if (((_c get "tag") param [5, ""]) isEqualTo "") exitWith { _c set ["athena", "offline"]; call _publish; };
        // Pas de liaison Athena chez celui qui a validé : on essaie chez le propriétaire, puis chez un chef du camp.
        private _tried = _c getOrDefault ["tried", []];
        if (!isNull _from) then { _tried pushBackUnique getPlayerUID _from; };
        _c set ["tried", _tried];
        private _side = (_c get "tag") param [1, ""];
        private _uid = (_c get "tag") param [0, ""];
        private _cands = allPlayers select { (str side group _x) isEqualTo _side && {!((getPlayerUID _x) in _tried)} };
        private _k = _cands findIf { (getPlayerUID _x) isEqualTo _uid };
        if (_k < 0) then { _k = _cands findIf { (leader group _x) isEqualTo _x }; };
        if (_k < 0) exitWith { _c set ["athena", "offline"]; call _publish; };
        private _next = _cands select _k;
        _c set ["revokeBy", _now + 45];
        _c set ["asked", getPlayerUID _next];
        ["revoke", [_id, (_c get "tag") param [5, ""], (_c get "tag") param [2, ""]]] remoteExecCall ["comspec_atak_native_fnc_compromise", _next];
    };
};
true
