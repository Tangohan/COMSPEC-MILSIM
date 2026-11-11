/*
    Relève d'un poste du TOC (feuille de route TOC, lot 2, n° 16) : le sortant propose la relève de son poste avec une
    consigne ; le serveur y joint un résumé de la salle (demandes ouvertes, pertes, prochaines lignes de la liste
    d'exécution, chronos en marche, FLASH, verrou) ; l'entrant signe et prend le poste. Les verrous du sortant sont
    levés à la signature. Tenu par le serveur seul (localNamespace), gardé avec la salle (fn_tocPersist), 50 relèves
    par camp. Sans Athena ni téléphone.
    Relève : [id, heure ZULU, serverTime, camp, salle, rôle du poste, nom du poste, sortant, entrant ("" : en attente),
              consigne, résumé [lignes], heure ZULU de la signature, netId du PC, UID du sortant]
    Copie publique des relèves en attente (pour proposer « signer » près du PC) : missionNamespace
    COMSPEC_ATAK_TocHandoverPending = [[netId du PC, camp, salle, rôle, nom du poste, heure ZULU, id, sortant]...]
    Modes purs (rejoués par tests/Unit/AtakTocStationHandoverTest.php) :
      ["summary", état de la salle (fn_tocRoom)]      lignes du résumé [[modèle, valeurs]...], 12 au plus
      ["text", relève]                                texte d'une relève (export, fenêtre)
    Lecture : ["pending", PC]  relève en attente sur ce PC, ou []
    Demandes (client -> serveur, l'émetteur fait foi) :
      ["offer", [PC (objNull : mon poste), consigne]]  par le tenant du poste (ou un chef de poste)
      ["sign", [PC]]                                   par l'entrant, à moins de 6 m du PC
      ["cancel", [PC]]                                 par le sortant ou un chef de poste
      ["get", camp]                                    relèves du camp, envoyées à la demande : ["view"] et événement
                                                       local comspec_atak_toc_handoverList [camp, relèves]
      ["open"]                                         demande les relèves de mon camp et les montre dans une fenêtre
    Serveur : ["init"], ["dump"] / ["load", données], ["export", camp] texte de toutes les relèves du camp
*/
params [["_mode", "view"], ["_a", []]];
private _acc = comspec_atak_native_fnc_tocAccess;
private _sides = ["WEST", "EAST", "GUER", "CIV"];
private _inside = !isRemoteExecuted || {!isNil "_tocTrusted"};
private _T = { _this call comspec_atak_native_fnc_tr };
private _store = { localNamespace getVariable ["COMSPEC_ATAK_TocHandoverSrv", []] };
private _publish = {
    params ["_l"];
    localNamespace setVariable ["COMSPEC_ATAK_TocHandoverSrv", _l];
    missionNamespace setVariable ["COMSPEC_ATAK_TocHandoverPending", (_l select { (_x select 8) isEqualTo "" }) apply { [_x select 12, _x select 3, _x select 4, _x select 5, _x select 6, _x select 1, _x select 0, _x select 7] }, true];
    ["save"] call comspec_atak_native_fnc_tocPersist;
};
private _summary = {
    params [["_st", []]];
    if !(_st isEqualType [] && {(count _st) >= 9}) exitWith { [] };
    private _out = [];
    private _open = (_st select 5) select { (_x param [7, ""]) in ["RECUE", "PRISE"] };
    _out pushBack ["Demandes ouvertes : %1", [count _open]];
    { _out pushBack ["  %1 · %2 · %3 · %4", [_x param [1, ""], _x param [2, ""], _x param [3, ""], _x param [7, ""]]]; } forEach (_open select [0 max ((count _open) - 3)]);
    private _wia = 0; private _kia = 0;
    { _wia = _wia + (_x param [1, 0]); _kia = _kia + (_x param [2, 0]); } forEach (_st select 8);
    _out pushBack ["Pertes amies : %1 blessé(s), %2 tué(s)", [_wia, _kia]];
    private _todo = (_st select 4) select { (_x param [6, ""]) isEqualTo "" };
    _out pushBack ["Liste d'exécution : %1 ligne(s) à venir sur %2", [count _todo, count (_st select 4)]];
    { _out pushBack ["  %1 · %2 · %3 · %4", [_x param [1, ""], _x param [3, ""], _x param [2, ""], _x param [5, ""]]]; } forEach (_todo select [0, 3]);
    _out pushBack ["Chronos en marche : %1", [{ _x param [4, false] } count (_st select 1)]];
    if (((_st select 6) param [0, ""]) isNotEqualTo "") then { _out pushBack ["FLASH en cours : %1", [(_st select 6) select 0]]; };
    _out select [0, 12]
};
private _text = {
    params [["_id", ""], ["_z", ""], "", ["_sd", ""], ["_hall", ""], ["_kind", ""], ["_name", ""], ["_from", ""], ["_to", ""], ["_note", ""], ["_sum", []], ["_zs", ""]];
    private _l = [
        format [("RELÈVE %1 · %2 · %3Z" call _T), [_name, _kind] select (_name isEqualTo ""), _sd, _z],
        format [("Sortant : %1" call _T), _from],
        [format [("Entrant : %1, signée à %2Z" call _T), _to, _zs], ("Entrant : en attente de signature" call _T)] select (_to isEqualTo ""),
        format [("Consigne : %1" call _T), [_note, "-"] select (_note isEqualTo "")]
    ];
    // Lignes du résumé : [modèle, valeurs], traduites à l'affichage.
    (_l + ((_sum select { _x isEqualType [] && {(count _x) >= 2} }) apply { format ([(_x select 0) call _T] + (_x select 1)) })) joinString endl
};
switch (_mode) do {
    case "summary": { [_a] call _summary };
    case "text": { _a call _text };
    case "pending": {
        private _id = netId _a;
        ((missionNamespace getVariable ["COMSPEC_ATAK_TocHandoverPending", []]) select { (_x param [0, ""]) isEqualTo _id }) param [0, []]
    };
    case "init": {
        if (!isServer || {!isNil { localNamespace getVariable "COMSPEC_ATAK_TocHandoverSrv" }}) exitWith {};
        localNamespace setVariable ["COMSPEC_ATAK_TocHandoverSrv", []];
        missionNamespace setVariable ["COMSPEC_ATAK_TocHandoverPending", [], true];
    };
    case "offer": {
        _a params [["_pc", objNull], ["_note", ""], ["_claimed", objNull]];
        if (!isServer) exitWith { ["offer", [_pc, _note, player]] remoteExecCall ["comspec_atak_native_fnc_tocHandover", 2]; true };
        if !(_pc isEqualType objNull && {_note isEqualType ""}) exitWith { false };
        (["actor", _claimed] call _acc) params ["_u", "_tr"];
        if (isNull _u) exitWith { false };
        private _rec = if (isNull _pc) then { ["of", _u] call comspec_atak_native_fnc_tocStation } else { ["holder", _pc] call comspec_atak_native_fnc_tocStation };
        if (_rec isEqualTo [] || {(_rec select 6) isEqualTo ""}) exitWith { ["feedback", [_u, false, "Refusé : ce poste n'est tenu par personne", []]] call _acc; false };
        // Le tenant propose sa propre relève ; un chef de poste ou le Zeus peut la proposer pour lui.
        if ((_rec select 6) isNotEqualTo (getPlayerUID _u) && {(["level", [_u, _rec select 1, _tr, _rec select 2]] call _acc) < 2}) exitWith { ["feedback", [_u, false, "Refusé : seul le tenant du poste propose sa relève", []]] call _acc; false };
        _note = (trim _note) select [0, 300];
        if !(["check", [_u, "handover", _rec select 1, "handover.offer", format ["%1 %2", _rec select 3, _rec select 4], _rec select 2]] call _acc) exitWith { false };
        private _l = (call _store) select { !((_x select 12) isEqualTo (_rec select 0) && {(_x select 8) isEqualTo ""}) };
        private _sd = _rec select 2;
        if !(_sd in _sides) then { _sd = str side group _u; };
        private _sum = [["state", _sd] call comspec_atak_native_fnc_tocRoom] call _summary;
        private _lk = ["locked", _rec select 1] call _acc;
        if (_lk select 0) then { _sum pushBack ["Salle verrouillée par %1", [_lk select 1]]; };
        _l pushBack [format ["RLV%1", floor (serverTime * 10)], ["zulu"] call comspec_atak_native_fnc_tocRoom, serverTime, _sd, _rec select 1, _rec select 3, _rec select 4, _rec select 5, "", _note, _sum, "", _rec select 0, _rec select 6];
        // 50 relèves par camp : les plus anciennes sortent.
        private _mine = _l select { (_x select 3) isEqualTo _sd };
        if ((count _mine) > 50) then { _l = _l - (_mine select [0, (count _mine) - 50]); };
        [_l] call _publish;
        ["feedback", [_u, true, "Relève proposée : l'entrant la signe près du PC", []]] call _acc;
        ["comspec_atak_toc_handover", [_sd, _rec select 1, _rec select 3, _rec select 5, "", "offered"]] call CBA_fnc_globalEvent;
        true
    };
    case "sign";
    case "cancel": {
        _a params [["_pc", objNull], ["_claimed", objNull]];
        if (!isServer) exitWith { [_mode, [_pc, player]] remoteExecCall ["comspec_atak_native_fnc_tocHandover", 2]; true };
        if !(_pc isEqualType objNull && {!isNull _pc}) exitWith { false };
        (["actor", _claimed] call _acc) params ["_u", "_tr"];
        if (isNull _u) exitWith { false };
        private _l = +(call _store);
        private _i = _l findIf { (_x select 12) isEqualTo (netId _pc) && {(_x select 8) isEqualTo ""} };
        if (_i < 0) exitWith { ["feedback", [_u, false, "Refusé : aucune relève en attente sur ce poste", []]] call _acc; false };
        private _h = _l select _i;
        private _uid = getPlayerUID _u;
        private _lvl = ["level", [_u, _h select 4, _tr, _h select 3]] call _acc;
        private _tocTrusted = true;
        if (_mode isEqualTo "cancel") exitWith {
            if ((_h select 13) isNotEqualTo _uid && {_lvl < 2}) exitWith { false };
            _l deleteAt _i;
            [_l] call _publish;
            ["add", [_h select 3, _h select 4, _u, "handover.cancel", _h select 5, true]] call comspec_atak_native_fnc_tocJournal;
            ["comspec_atak_toc_handover", [_h select 3, _h select 4, _h select 5, _h select 7, "", "cancelled"]] call CBA_fnc_globalEvent;
            true
        };
        private _chief = (((["kinds"] call comspec_atak_native_fnc_tocStation) select { (_x select 0) isEqualTo (_h select 5) }) param [0, []]) param [2, false];
        private _no = switch (true) do {
            case ((_h select 13) isEqualTo _uid): { "Refusé : le sortant ne signe pas sa propre relève" };
            case ((_u distance _pc) > 6): { "Refusé : approchez-vous du PC du poste" };
            case (_chief && {!(["chiefOk", _u] call comspec_atak_native_fnc_tocStation)}): { "Refusé : votre grade ne permet pas de tenir le poste de chef de quart" };
            default { "" };
        };
        if (_no isNotEqualTo "") exitWith { ["feedback", [_u, false, _no, []]] call _acc; false };
        if !(["check", [_u, "handover", _h select 4, "handover.sign", format ["%1 : %2 -> %3", _h select 5, _h select 7, (["nameOf", _u] call comspec_atak_native_fnc_tocAccess)], _h select 3]] call _acc) exitWith { false };
        _h set [8, (["nameOf", _u] call comspec_atak_native_fnc_tocAccess)];
        _h set [11, ["zulu"] call comspec_atak_native_fnc_tocRoom];
        [_l] call _publish;
        ["assign", [_pc, _u]] call comspec_atak_native_fnc_tocStation;
        // Verrous du sortant levés : la salle ne reste pas bloquée après son départ.
        ["unlockBy", [_h select 13, "relève"]] call _acc;
        ["feedback", [_u, true, "Relève signée : vous tenez le poste", []]] call _acc;
        ["comspec_atak_toc_handover", [_h select 3, _h select 4, _h select 5, _h select 7, (["nameOf", _u] call comspec_atak_native_fnc_tocAccess), "signed"]] call CBA_fnc_globalEvent;
        true
    };
    case "get": {
        if (!hasInterface) exitWith { false };
        ["getSrv", [_a, player]] remoteExecCall ["comspec_atak_native_fnc_tocHandover", 2];
        true
    };
    case "getSrv": {
        if (!isServer) exitWith { false };
        _a params [["_sd", ""], ["_claimed", objNull]];
        (["actor", _claimed] call _acc) params ["_u", "_tr"];
        if (isNull _u) exitWith { false };
        // Chacun lit les relèves de son camp ; seul l'administrateur Zeus lit celles d'un autre camp.
        if ((["level", [_u, "", _tr]] call _acc) < 3 || {!(_sd in _sides)}) then { _sd = str side group _u; };
        if !(["check", [_u, "handover", "", "", ""]] call _acc) exitWith { false };
        ["recv", [_sd, ((call _store) select { (_x select 3) isEqualTo _sd }) apply { _x select [0, 12] }]] remoteExecCall ["comspec_atak_native_fnc_tocHandover", owner _u];
        true
    };
    case "recv": {
        if (!hasInterface || {isMultiplayer && {isRemoteExecuted} && {!(remoteExecutedOwner in [0, 2])}}) exitWith { false };
        _a params [["_sd", ""], ["_l", []]];
        missionNamespace setVariable ["COMSPEC_ATAK_TocHandoverView", [_sd, _l, diag_tickTime]];
        ["comspec_atak_toc_handoverList", [_sd, _l]] call CBA_fnc_localEvent;
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocHandoverShow", false]) then {
            missionNamespace setVariable ["COMSPEC_ATAK_TocHandoverShow", false];
            private _txt = if (_l isEqualTo []) then { "Aucune relève enregistrée pour ce camp." call _T } else { (_l apply { _x call _text }) joinString (endl + endl) };
            ["show", ["Relèves du TOC", (["text", _sd] call comspec_atak_native_fnc_tocStation) + endl + endl + _txt]] call comspec_atak_native_fnc_tocDiag;
        };
        true
    };
    case "view": { missionNamespace getVariable ["COMSPEC_ATAK_TocHandoverView", ["", [], 0]] };
    case "open": {
        missionNamespace setVariable ["COMSPEC_ATAK_TocHandoverShow", true];
        ["get", ""] call comspec_atak_native_fnc_tocHandover
    };
    case "export": {
        if (!isServer || {!_inside}) exitWith { "" };
        (((call _store) select { _a isEqualTo "" || {(_x select 3) isEqualTo _a} }) apply { _x call _text }) joinString (endl + endl)
    };
    case "dump": { if (!isServer || {!_inside}) exitWith { [] }; +(call _store) };
    case "load": {
        if (!isServer || {!_inside} || {!(_a isEqualType [])}) exitWith { false };
        // Relèves de la partie précédente : gardées pour lecture ; celles restées en attente sont closes (PC disparu).
        private _old = (_a select { _x isEqualType [] && {(count _x) >= 14} && {(_x select 8) isNotEqualTo ""} }) apply { private _h = +_x; _h set [12, ""]; _h set [13, ""]; _h };
        [_old + (call _store)] call _publish;
        true
    };
    default { false };
};
