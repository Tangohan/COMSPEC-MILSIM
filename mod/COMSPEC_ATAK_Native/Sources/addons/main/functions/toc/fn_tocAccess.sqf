/*
    Droits de la salle TOC (feuille de route TOC, lot 2, n° 11, 14 et 15) : qui peut faire quoi dans une salle, et le
    verrou de salle. Tenu par le serveur seul, sans Athena ni téléphone. Mode d'emploi : docs/TOC-ACCESS.md.
    Trois rôles : opérateur (tout joueur du camp de la salle), chef de poste (tient le poste « chef de quart » de la
    salle, ou désigné par le réglage ou la mission), administrateur Zeus (Zeus, hôte, admin connecté, gestionnaires du
    serveur : fn_adminAllowed). Règle par défaut validée par TTRD : seuls le chef de poste et l'administrateur Zeus
    changent les écrans et les salles. Réglage « Droits de la salle TOC » : rôles (défaut), ouvert (comme avant la
    4.10.0), administrateurs seulement.
    Le serveur ne croit jamais le client : l'auteur d'une demande est retrouvé par remoteExecutedOwner (mode « actor »),
    son camp et son identifiant sont lus sur le serveur. L'état qui fait foi vit dans localNamespace du serveur (un
    client ne peut pas l'écraser) ; missionNamespace COMSPEC_ATAK_TocAccess n'en est qu'une copie pour l'affichage :
      [verrous [[salle, nom, UID, heure ZULU, serverTime, motif]...], rôles donnés [[UID, rôle]...]]
    Modes purs (mêmes tables que tests/Unit/AtakTocAccessMatrixTest.php) :
      ["roles"] / ["matrix"]                    tables ci-dessous
      ["need", [droit, mode]]                   niveau minimal (99 : droit inconnu)
      ["allowed", [niveau, droit, mode, salle verrouillée, auteur du verrou]]   vrai ou faux
    Lecture (serveur : fait foi ; client : estimation pour l'affichage) :
      ["mode"]                                  "roles" | "open" | "admin"
      ["level", [unité, salle, de confiance, camp de l'objet]]   0 à 3
      ["roleOf", [unité, salle]]                "none" | "operator" | "chief" | "admin"
      ["can", [unité, droit, salle]]            vrai ou faux
      ["locked", salle]                         [verrouillée, nom, heure ZULU, motif, UID]
    Points d'accroche pour les autres fichiers du TOC (serveur) :
      ["actor", unité annoncée]                 [unité réelle, de confiance] : dans un appel distant, l'unité est celle de
                                                l'émetteur (jamais celle annoncée) ; appel local du serveur : celle
                                                annoncée, objNull = script de mission (de confiance, niveau 3)
      ["check", [unité annoncée, droit, salle, action, détail, camp de l'objet]]
                                                vrai si permis ; écrit le journal (fn_tocJournal) dans les deux cas et
                                                dit le refus au joueur (événement comspec_atak_toc_feedback)
      ["nameOf", unité]                         nom montré dans le TOC : indicatif (résolveur commun
                                                comspec_atak_native_fnc_tocDisplayName s'il est chargé), sinon groupe et
                                                rang du jeu (« Alpha 1-1 · 2 ») ; jamais le pseudo Steam
      ["feedback", [unité, réussi, texte, valeurs]]   retour vers le joueur : variable locale COMSPEC_ATAK_TocFeedback
                                                [diag_tickTime, réussi, texte], événement comspec_atak_toc_feedback ; un
                                                refus ouvre la fenêtre du TOC (fn_tocDiag « show ») sauf si une console
                                                ouverte a posé COMSPEC_ATAK_TocFeedbackUi à vrai. Jamais d'alerte du jeu.
    Demandes (client -> serveur par remoteExecCall, l'émetteur fait foi) :
      ["lock", [salle, verrouiller, motif]]     verrou : droits « écrans », « salles » et « préréglages » gelés pour tous
                                                sauf l'auteur du verrou et l'administrateur Zeus
      ["grant", [unité ou UID, rôle]]           "chief" | "admin" | "none" (interdit) | "" (retire) ; mission ou admin
      ["send", [genre, charge]]                 client : envoie une demande d'écran ("screen") ou de salle ("hall") par
                                                la porte du serveur (ou par l'ancien événement si le serveur est plus ancien)
      ["gate", [genre, charge]]                 serveur : contrôle, journal, puis fn_tocScreen / fn_tocHall
      ["legacy", [genre, charge]]               serveur : demande arrivée par l'ancien événement (émetteur inconnu) ;
                                                vrai seulement si un simple opérateur y a droit
      ["init"] serveur, ["initClient"] client, ["unlockBy", [UID, motif]] serveur (relève, déconnexion)
*/
params [["_mode", "can"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocAccess;
private _sides = ["WEST", "EAST", "GUER", "CIV"];
// ---- TABLE _roles (JSON) : [rôle, niveau, libellé].
private _roles = [
["none", 0, "Sans accès"], ["operator", 1, "Opérateur"], ["chief", 2, "Chef de poste"], ["admin", 3, "Administrateur Zeus"]
];
// ---- FIN
// ---- TABLE _matrix (JSON) : [droit, libellé, niveau minimal en mode « roles », « open », « admin », gelé par le verrou].
private _matrix = [
["view", "Lire la console et les écrans", 1, 1, 1, false],
["station", "Tenir un poste", 1, 1, 1, false],
["entry", "Écrire dans les registres du poste", 1, 1, 1, false],
["handover", "Proposer et signer une relève", 1, 1, 1, false],
["screens", "Changer l'affichage des écrans", 2, 1, 3, true],
["rooms", "Régler la salle, ses écrans liés et ses scènes", 2, 1, 3, true],
["presets", "Enregistrer et poser un préréglage de salle", 2, 1, 3, true],
["lock", "Verrouiller et déverrouiller la salle", 2, 2, 3, false],
["journal", "Lire le journal des actions du TOC", 2, 2, 3, false],
["diag", "Exporter le diagnostic du TOC", 2, 2, 3, false],
["build", "Poser ou retirer une salle complète", 3, 1, 3, true],
["grant", "Donner un rôle à un joueur", 3, 3, 3, false]
];
// ---- FIN
// ---- TABLE _hallActs (JSON) : [action de fn_tocHall, droit, où lire la salle : "none" | "id" | "id0" | "obj" | "obj0"].
private _hallActs = [
["create", "rooms", "none"], ["rename", "rooms", "id0"], ["link", "rooms", "id0"], ["unlink", "rooms", "obj"], ["linkNear", "rooms", "id0"],
["power", "screens", "obj0"], ["powerAll", "screens", "id0"], ["blink", "view", "obj"],
["sceneSave", "rooms", "id0"], ["sceneDel", "rooms", "id0"], ["sceneApply", "screens", "id0"],
["spawn", "build", "none"], ["roomRemove", "build", "id"]
];
// ---- FIN
// ---- TABLE _screenActs (JSON) : [mode de fn_tocScreen, droit]. Les autres modes passent sans contrôle (désignation, pose).
private _screenActs = [["view", "screens"], ["remove", "rooms"]];
// ---- FIN
private _inside = !isRemoteExecuted || {!isNil "_tocTrusted"};
private _state = {
    private _s = if (isServer) then { localNamespace getVariable ["COMSPEC_ATAK_TocAccessSrv", [[], []]] } else { missionNamespace getVariable ["COMSPEC_ATAK_TocAccess", [[], []]] };
    if !(_s isEqualType [] && {(count _s) >= 2}) then { _s = [[], []]; };
    _s
};
private _publish = {
    params ["_s"];
    localNamespace setVariable ["COMSPEC_ATAK_TocAccessSrv", _s];
    missionNamespace setVariable ["COMSPEC_ATAK_TocAccess", +_s, true];
};
private _modeNow = {
    private _m = missionNamespace getVariable ["comspec_atak_native_toc_access_mode", "roles"];
    if !(_m in ["roles", "open", "admin"]) then { _m = "roles"; };
    _m
};
private _need = {
    params ["_perm", "_m"];
    private _i = _matrix findIf { (_x select 0) isEqualTo _perm };
    if (_i < 0) exitWith { 99 };
    (_matrix select _i) select (2 + (["roles", "open", "admin"] find _m) max 2)
};
private _allowed = {
    params ["_lvl", "_perm", "_m", ["_locked", false], ["_holder", false]];
    private _i = _matrix findIf { (_x select 0) isEqualTo _perm };
    if (_i < 0) exitWith { false };
    if (_lvl < ([_perm, _m] call _need)) exitWith { false };
    // Salle verrouillée : les droits gelés ne restent qu'à l'auteur du verrou et à l'administrateur Zeus.
    !(_locked && {(_matrix select _i) select 5} && {!_holder} && {_lvl < 3})
};
private _lockOf = {
    params ["_hall"];
    private _l = ((call _state) select 0) select { (_x param [0, ""]) isEqualTo _hall };
    _l param [0, []]
};
private _actor = {
    params [["_claimed", objNull]];
    if !(_claimed isEqualType objNull) then { _claimed = objNull; };
    // Appel local (serveur, mission, événement) : l'unité annoncée fait foi ; aucune = script de mission, de confiance.
    if (!isRemoteExecuted || {!isMultiplayer}) exitWith { [_claimed, isNull _claimed] };
    private _o = remoteExecutedOwner;
    if (_o in [0, 2]) exitWith { if (hasInterface) then { [player, false] } else { [_claimed, isNull _claimed] } };
    private _i = allPlayers findIf { (owner _x) isEqualTo _o };
    [[objNull, allPlayers param [_i max 0, objNull]] select (_i >= 0), false]
};
private _levelOf = {
    params [["_u", objNull], ["_hall", ""], ["_trusted", false], ["_objSide", ""]];
    if (isNull _u) exitWith { [0, 3] select _trusted };
    private _uid = getPlayerUID _u;
    private _given = (((call _state) select 1) select { (_x param [0, ""]) isEqualTo _uid }) param [0, []];
    private _role = _given param [1, ""];
    if (_role isEqualTo "none") exitWith { 0 };
    if (_role isEqualTo "admin" || {[_u] call comspec_atak_native_fnc_adminAllowed}) exitWith { 3 };
    private _sd = str side group _u;
    if !(_sd in _sides) exitWith { 0 };
    // Salle ou écran d'un autre camp : aucun droit (camp lu sur le serveur, jamais reçu du client).
    private _hs = (["get", _hall] call comspec_atak_native_fnc_tocHall) param [2, ""];
    if (_hs isEqualTo "") then { _hs = _objSide; };
    if (_hs isNotEqualTo "" && {_hs isNotEqualTo _sd}) exitWith { 0 };
    if (_role isEqualTo "chief") exitWith { 2 };
    if (_uid in (((missionNamespace getVariable ["comspec_atak_native_toc_chief_uids", ""]) splitString ", ;") apply { trim _x })) exitWith { 2 };
    if (["chiefOf", [_u, _hall]] call comspec_atak_native_fnc_tocStation) exitWith { 2 };
    1
};
private _feedback = {
    params [["_u", objNull], ["_ok", true], ["_txt", ""], ["_vals", []]];
    if (isNull _u || {!isPlayer _u}) exitWith {};
    ["comspec_atak_toc_feedback", [_ok, _txt, _vals], _u] call CBA_fnc_targetEvent;
};
private _check = {
    params [["_claimed", objNull], ["_perm", ""], ["_hall", ""], ["_act", ""], ["_det", ""], ["_objSide", ""]];
    ([_claimed] call _actor) params ["_u", "_tr"];
    private _lvl = [_u, _hall, _tr, _objSide] call _levelOf;
    private _lk = [_hall] call _lockOf;
    private _ok = [_lvl, _perm, call _modeNow, _lk isNotEqualTo [], (_lk param [2, "?"]) isEqualTo (getPlayerUID _u)] call _allowed;
    private _tocTrusted = true;
    if (_act isNotEqualTo "") then {
        private _sd = [_objSide, str side group _u] select (!isNull _u);
        ["add", [_sd, _hall, _u, _act, _det, _ok]] call comspec_atak_native_fnc_tocJournal;
    };
    if (!_ok) then {
        private _why = if (_lk isNotEqualTo [] && {_lvl >= ([_perm, call _modeNow] call _need)}) then { ["Refusé : la salle est verrouillée par %1", [_lk param [1, "?"]]] } else { ["Refusé : ce droit demande le rôle « %1 »", [(_roles select ((([_perm, call _modeNow] call _need) min 3) max 0)) select 2]] };
        [_u, false, _why select 0, _why select 1] call _feedback;
        ["comspec_atak_toc_denied", [_u, _hall, _perm, _act]] call CBA_fnc_localEvent;
    };
    _ok
};
switch (_mode) do {
    case "roles": { +_roles };
    case "matrix": { +_matrix };
    case "mode": { call _modeNow };
    case "need": { _a params [["_perm", ""], ["_m", "roles"]]; [_perm, _m] call _need };
    case "allowed": { _a call _allowed };
    case "actor": { [_a] call _actor };
    case "level": { _a call _levelOf };
    case "roleOf": {
        _a params [["_u", objNull], ["_hall", ""]];
        (_roles select ((([_u, _hall] call _levelOf) min 3) max 0)) select 0
    };
    case "can": {
        _a params [["_u", objNull], ["_perm", ""], ["_hall", ""]];
        private _lk = [_hall] call _lockOf;
        [[_u, _hall] call _levelOf, _perm, call _modeNow, _lk isNotEqualTo [], (_lk param [2, "?"]) isEqualTo (getPlayerUID _u)] call _allowed
    };
    case "locked": {
        private _lk = [_a] call _lockOf;
        [_lk isNotEqualTo [], _lk param [1, ""], _lk param [3, ""], _lk param [5, ""], _lk param [2, ""]]
    };
    case "nameOf": {
        // Nom d'un joueur montré dans le TOC (journal, postes, relèves, verrou) : jamais le pseudo Steam. L'indicatif
        // vient du résolveur commun du TOC quand il est chargé (comspec_atak_native_fnc_tocDisplayName, lot « sans
        // téléphone ») ; sinon, indicatif du groupe du jeu et rang dans le groupe (« Alpha 1-1 · 2 »).
        if !(_a isEqualType objNull) exitWith { "" };
        if (isNull _a) exitWith { "MISSION" };
        private _n = "";
        if (!isNil "comspec_atak_native_fnc_tocDisplayName") then { _n = [_a] call comspec_atak_native_fnc_tocDisplayName; };
        if (isNil "_n" || {!(_n isEqualType "")} || {_n isEqualTo ""}) then { _n = format ["%1 · %2", groupId group _a, ((units group _a) find _a) + 1]; };
        _n select [0, 48]
    };
    case "check": { if (!isServer) exitWith { false }; _a call _check };
    case "feedback": { if (isServer && {_inside}) then { _a call _feedback; }; true };
    case "lock": {
        _a params [["_hall", ""], ["_on", true], ["_why", ""], ["_claimed", objNull]];
        if (!isServer) exitWith { ["lock", [_hall, _on, _why, player]] remoteExecCall ["comspec_atak_native_fnc_tocAccess", 2]; true };
        if !(_hall isEqualType "" && {_on isEqualType true} && {_why isEqualType ""}) exitWith { false };
        if ((["get", _hall] call comspec_atak_native_fnc_tocHall) isEqualTo []) exitWith { false };
        ([_claimed] call _actor) params ["_u", "_tr"];
        _why = (trim _why) select [0, 80];
        private _s = +(call _state);
        private _lk = [_hall] call _lockOf;
        private _lvl = [_u, _hall, _tr] call _levelOf;
        private _uid = getPlayerUID _u;
        // Déjà dans l'état demandé, ou verrou d'un autre que seul un chef de poste ou le Zeus lève.
        if (_on isEqualTo (_lk isNotEqualTo [])) exitWith { false };
        if !([_claimed, "lock", _hall, ["lock.off", "lock.on"] select _on, _why] call _check) exitWith { false };
        private _locks = (_s select 0) select { (_x param [0, ""]) isNotEqualTo _hall };
        private _nm = [(["nameOf", _u] call comspec_atak_native_fnc_tocAccess), "MISSION"] select (isNull _u);
        if (_on) then { _locks pushBack [_hall, _nm, _uid, ["zulu"] call comspec_atak_native_fnc_tocRoom, serverTime, _why]; };
        _s set [0, _locks];
        [_s] call _publish;
        [_u, true, ["Salle déverrouillée", "Salle verrouillée : les écrans et la salle ne changent plus"] select _on, []] call _feedback;
        ["comspec_atak_toc_locked", [_hall, _on, _nm, _why]] call CBA_fnc_globalEvent;
        _lvl >= 0
    };
    case "unlockBy": {
        // Verrous d'un joueur levés d'office : relève signée, déconnexion (un verrou oublié ne bloque pas la salle).
        if (!isServer || {!_inside}) exitWith { false };
        _a params [["_uid", ""], ["_why", ""]];
        private _s = +(call _state);
        private _mine = (_s select 0) select { (_x param [2, ""]) isEqualTo _uid };
        if (_uid isEqualTo "" || {_mine isEqualTo []}) exitWith { false };
        _s set [0, (_s select 0) - _mine];
        [_s] call _publish;
        private _tocTrusted = true;
        {
            ["add", ["", _x select 0, _x param [1, "?"], "lock.off", _why, true]] call comspec_atak_native_fnc_tocJournal;
            ["comspec_atak_toc_locked", [_x select 0, false, _x param [1, "?"], _why]] call CBA_fnc_globalEvent;
        } forEach _mine;
        true
    };
    case "grant": {
        _a params [["_who", objNull], ["_role", ""], ["_claimed", objNull]];
        if (!isServer) exitWith { ["grant", [_who, _role, player]] remoteExecCall ["comspec_atak_native_fnc_tocAccess", 2]; true };
        private _uid = if (_who isEqualType objNull) then { getPlayerUID _who } else { [str _who, _who] select (_who isEqualType "") };
        if (_uid isEqualTo "" || {!(_role in ["", "none", "chief", "admin"])}) exitWith { false };
        private _nm = if (_who isEqualType objNull) then { (["nameOf", _who] call comspec_atak_native_fnc_tocAccess) } else { "UID" };
        if !([_claimed, "grant", "", "access.grant", format ["%1 : %2", _nm, [_role, "retiré"] select (_role isEqualTo "")]] call _check) exitWith { false };
        private _s = +(call _state);
        private _g = (_s select 1) select { (_x param [0, ""]) isNotEqualTo _uid };
        if (_role isNotEqualTo "") then { _g pushBack [_uid, _role]; };
        _s set [1, _g];
        [_s] call _publish;
        true
    };
    case "send": {
        _a params [["_kind", ""], ["_load", []]];
        // Serveur à jour : par la porte (l'émetteur fait foi). Serveur plus ancien : ancien événement, comme avant.
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocAccessOn", false]) exitWith { ["gate", [_kind, _load]] remoteExecCall ["comspec_atak_native_fnc_tocAccess", 2]; true };
        [["comspec_atak_native_tocHall", "comspec_atak_native_tocScreen"] select (_kind isEqualTo "screen"), _load] call CBA_fnc_serverEvent;
        true
    };
    case "gate": {
        if (!isServer) exitWith { false };
        _a params [["_kind", ""], ["_load", []]];
        if !(_load isEqualType [] && {(count _load) >= 1} && {(_load select 0) isEqualType ""}) exitWith { false };
        _load params ["_act", ["_args", []]];
        ([objNull] call _actor) params ["_u"];
        if (_kind isEqualTo "screen") exitWith {
            private _perm = ((_screenActs select { (_x select 0) isEqualTo _act }) param [0, []]) param [1, ""];
            private _o = if (_args isEqualType objNull) then { _args } else { [objNull, _args param [0, objNull]] select (_args isEqualType []) };
            if !(_o isEqualType objNull) then { _o = objNull; };
            // Seuls les modes de la table passent par la porte (jamais un mode interne de fn_tocScreen).
            if (_perm isEqualTo "" || {isNull _o}) exitWith { false };
            private _det = if (_act isEqualTo "view") then { format ["%1 · %2", (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "?"], _args param [1, ""]] } else { (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "?"] };
            // Cadrage de la carte (zoom, centre) : contrôlé comme le reste, mais pas écrit au journal (trop fréquent).
            private _quiet = _act isEqualTo "view" && {(_args param [1, ""]) in ["zoom", "center", "auto"]};
            if (!([_u, _perm, ["of", _o] call comspec_atak_native_fnc_tocHall, ["screen." + _act, ""] select _quiet, _det, (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]] call _check)) exitWith { false };
            [_act, _args] call comspec_atak_native_fnc_tocScreen;
            ["comspec_atak_toc_screenChanged", [_o, _act, _args]] call CBA_fnc_localEvent;
            true
        };
        if (_kind isNotEqualTo "hall") exitWith { false };
        private _row = (_hallActs select { (_x select 0) isEqualTo _act }) param [0, []];
        if (_row isEqualTo []) exitWith { false };
        _row params ["", "_perm", "_how"];
        private _hall = switch (_how) do {
            case "id": { _args };
            case "id0": { _args param [0, ""] };
            case "obj": { if (_args isEqualType objNull) then { ["of", _args] call comspec_atak_native_fnc_tocHall } else { "" } };
            case "obj0": { private _o = _args param [0, objNull]; if (_o isEqualType objNull) then { ["of", _o] call comspec_atak_native_fnc_tocHall } else { "" } };
            default { "" };
        };
        if !(_hall isEqualType "") then { _hall = ""; };
        // Salle créée ou posée : toujours pour le camp de l'émetteur, sauf administrateur Zeus.
        private _sd = if (isNull _u) then { "" } else { str side group _u };
        if (_how isEqualTo "none" && {_args isEqualType []} && {([_u, "", false] call _levelOf) < 3}) then {
            _args = +_args;
            _args set [[4, 1] select (_act isEqualTo "create"), _sd];
        };
        if !([_u, _perm, _hall, "hall." + _act, [str _hall, ""] select (_hall isEqualTo "")] call _check) exitWith { false };
        ["do", [_act, _args]] call comspec_atak_native_fnc_tocHall;
        ["comspec_atak_toc_roomChanged", [_hall, _act]] call CBA_fnc_localEvent;
        true
    };
    case "legacy": {
        // Ancien événement CBA (client d'avant la 4.10.0) : l'émetteur est inconnu, il n'a donc que les droits d'un
        // simple opérateur, et rien dans une salle verrouillée. Écrit au journal.
        if (!isServer) exitWith { false };
        _a params [["_kind", ""], ["_load", []]];
        if !(_load isEqualType [] && {(count _load) >= 1}) exitWith { false };
        private _act = _load select 0;
        private _args = _load param [1, []];
        private _perm = "";
        private _hall = "";
        if (_kind isEqualTo "screen") then {
            _perm = ((_screenActs select { (_x select 0) isEqualTo _act }) param [0, []]) param [1, ""];
            private _o = if (_args isEqualType objNull) then { _args } else { [objNull, _args param [0, objNull]] select (_args isEqualType []) };
            if (_o isEqualType objNull && {!isNull _o}) then { _hall = ["of", _o] call comspec_atak_native_fnc_tocHall; };
        } else {
            private _row = (_hallActs select { (_x select 0) isEqualTo _act }) param [0, []];
            _perm = _row param [1, "rooms"];
            if ((_row param [2, ""]) in ["id", "id0"]) then { _hall = if (_args isEqualType "") then { _args } else { _args param [0, ""] }; };
            if !(_hall isEqualType "") then { _hall = ""; };
        };
        if (_perm isEqualTo "") exitWith { true };
        private _ok = [1, _perm, call _modeNow, ([_hall] call _lockOf) isNotEqualTo [], false] call _allowed;
        if (_perm isNotEqualTo "view") then {
            private _tocTrusted = true;
            ["add", ["", _hall, "?", "legacy", format ["%1.%2", _kind, _act], _ok]] call comspec_atak_native_fnc_tocJournal;
        };
        _ok
    };
    case "init": {
        if (!isServer || {missionNamespace getVariable ["COMSPEC_ATAK_TocAccessInit", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocAccessInit", true];
        [[[], []]] call _publish;
        // Dit aux clients que ce serveur contrôle les demandes (sinon ils gardent l'ancien événement).
        missionNamespace setVariable ["COMSPEC_ATAK_TocAccessOn", true, true];
        private _tocTrusted = true;
        ["init"] call comspec_atak_native_fnc_tocJournal;
        ["init"] call comspec_atak_native_fnc_tocStation;
        ["init"] call comspec_atak_native_fnc_tocHandover;
    };
    case "initClient": {
        if (!hasInterface) exitWith {};
        // Retour d'une demande (accordée, refusée, verrou) : gardé pour la console du poste, jamais en fenêtre du jeu.
        ["comspec_atak_toc_feedback", {
            params [["_ok", true], ["_txt", ""], ["_vals", []]];
            if !(_vals isEqualType []) then { _vals = []; };
            private _s = format ([_txt call comspec_atak_native_fnc_tr] + (_vals apply { if (_x isEqualType "") then { _x call comspec_atak_native_fnc_tr } else { _x } }));
            missionNamespace setVariable ["COMSPEC_ATAK_TocFeedback", [diag_tickTime, _ok, _s]];
            ["INFO", "TOC", format ["Retour du TOC : %1", _s]] call comspec_atak_native_fnc_log;
            // Refus : dit dans une fenêtre du TOC (jamais une alerte du jeu), sauf si une console ouverte l'affiche
            // elle-même (elle pose COMSPEC_ATAK_TocFeedbackUi à vrai tant qu'elle est ouverte).
            if (!_ok && {!(missionNamespace getVariable ["COMSPEC_ATAK_TocFeedbackUi", false])} && {diag_tickTime > ((missionNamespace getVariable ["COMSPEC_ATAK_TocFeedbackWin", -10]) + 5)}) then {
                missionNamespace setVariable ["COMSPEC_ATAK_TocFeedbackWin", diag_tickTime];
                ["show", ["Salle TOC", _s + endl + endl + ("Pour changer les écrans ou la salle : prenez le poste de chef de quart (action ACE sur soi, Poste TOC) ou demandez au Zeus." call comspec_atak_native_fnc_tr)]] call comspec_atak_native_fnc_tocDiag;
            };
        }] call CBA_fnc_addEventHandler;
        ["menu"] call comspec_atak_native_fnc_tocStation;
        ["initClient"] call comspec_atak_native_fnc_tocDiag;
    };
    default { false };
};
