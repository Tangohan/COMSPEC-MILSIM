/*
    Journal des actions du TOC (feuille de route TOC, lot 2, n° 17) : qui a fait quoi, à quel poste, dans quelle salle.
    Tenu par le serveur seul (localNamespace : un client ne peut ni l'écrire ni l'effacer), en anneau par camp (réglage
    « Lignes gardées au journal du TOC », 500 par défaut), gardé avec la salle (fn_tocPersist). Il n'est envoyé qu'à la
    demande, à qui a le droit « journal » (chef de poste, administrateur Zeus : fn_tocAccess). Chaque ligne est aussi
    écrite une fois dans le journal COMSPEC (fn_log, module TOC), si le réglage « Journal du TOC dans le journal
    COMSPEC » est coché.
    Ligne : [n°, heure ZULU, serverTime, camp, salle, auteur, rôle, action, détail, accordée]
    Modes purs (rejoués par tests/Unit/AtakTocJournalTest.php) :
      ["push", [tampon, ligne, taille]]          tampon en anneau : les plus anciennes sortent
      ["line", ligne]                            texte d'une ligne (export, fenêtre)
      ["text", lignes]                           texte de plusieurs lignes
      ["acts"]                                   table des actions
    Serveur :
      ["add", [camp, salle, auteur (unité ou texte), action, détail, accordée]]
                                                 point d'accroche des autres fichiers du TOC. Dans un appel distant,
                                                 l'auteur est l'émetteur réel (fn_tocAccess « actor »), jamais l'annoncé.
      ["read", [camp, nombre]]                   lignes (les plus récentes à la fin)
      ["dump"] / ["load", données] / ["init"]
    Client :
      ["get", [camp, nombre]]                    demande au serveur ; la réponse arrive dans ["view"] et par l'événement
                                                 local comspec_atak_toc_journal [camp, lignes]
      ["view"]                                   dernière réponse reçue [camp, lignes, diag_tickTime]
*/
params [["_mode", "view"], ["_a", []]];
private _sides = ["WEST", "EAST", "GUER", "CIV"];
// ---- TABLE _acts (JSON) : [action, libellé].
private _acts = [
["screen.view", "Écran : affichage changé"], ["screen.remove", "Écran retiré"],
["hall.create", "Salle créée"], ["hall.rename", "Salle renommée"], ["hall.link", "Écran lié à la salle"], ["hall.unlink", "Écran délié de la salle"],
["hall.linkNear", "Écrans proches liés à la salle"], ["hall.power", "Écran allumé ou éteint"], ["hall.powerAll", "Salle allumée ou éteinte"],
["hall.blink", "Écran identifié"], ["hall.sceneSave", "Scène enregistrée"], ["hall.sceneDel", "Scène supprimée"], ["hall.sceneApply", "Scène appliquée"],
["hall.spawn", "Salle complète posée"], ["hall.roomRemove", "Salle complète retirée"],
["lock.on", "Salle verrouillée"], ["lock.off", "Salle déverrouillée"],
["station.set", "Poste réglé"], ["station.take", "Poste pris"], ["station.leave", "Poste quitté"], ["station.kick", "Poste libéré d'office"], ["station.lost", "Poste libéré (déconnexion)"],
["handover.offer", "Relève proposée"], ["handover.sign", "Relève signée"], ["handover.cancel", "Relève annulée"],
["preset.save", "Préréglage enregistré"], ["preset.apply", "Préréglage posé"], ["preset.delete", "Préréglage supprimé"], ["preset.import", "Préréglage importé"],
["access.grant", "Rôle donné"], ["journal.read", "Journal consulté"], ["diag.export", "Diagnostic exporté"], ["save.import", "Sauvegarde importée"],
["api", "Action de la mission"], ["legacy", "Demande d'un joueur dont le mod est plus ancien"]
];
// ---- FIN
private _inside = !isRemoteExecuted || {!isNil "_tocTrusted"};
private _max = { (round (missionNamespace getVariable ["comspec_atak_native_toc_journal_max", 500])) max 50 min 2000 };
private _push = {
    params ["_buf", "_line", "_size"];
    _buf pushBack _line;
    if ((count _buf) > _size) then { _buf deleteRange [0, (count _buf) - _size]; };
    _buf
};
private _line = {
    params [["_n", 0], ["_z", ""], "", ["_sd", ""], ["_hall", ""], ["_who", ""], ["_role", ""], ["_act", ""], ["_det", ""], ["_ok", true]];
    private _lbl = ((_acts select { (_x select 0) isEqualTo _act }) param [0, ["", _act]]) select 1;
    format ["%1Z  %2  %3%4  %5%6%7%8", _z, [_sd, "-"] select (_sd isEqualTo ""), _who, ["", " (" + _role + ")"] select (_role isNotEqualTo ""),
        _lbl call comspec_atak_native_fnc_tr, ["", " : " + _det] select (_det isNotEqualTo ""), ["", " [" + _hall + "]"] select (_hall isNotEqualTo ""),
        ["  " + ("REFUSÉ" call comspec_atak_native_fnc_tr), ""] select _ok]
};
switch (_mode) do {
    case "acts": { +_acts };
    case "push": { _a call _push };
    case "line": { _a call _line };
    case "text": { (_a apply { _x call _line }) joinString endl };
    case "init": {
        if (!isServer || {!isNil { localNamespace getVariable "COMSPEC_ATAK_TocJournalSrv" }}) exitWith {};
        localNamespace setVariable ["COMSPEC_ATAK_TocJournalSrv", createHashMap];
    };
    case "add": {
        if (!isServer) exitWith { false };
        _a params [["_sd", ""], ["_hall", ""], ["_who", ""], ["_act", ""], ["_det", ""], ["_ok", true]];
        // Appel distant sans passer par un fichier du TOC : l'auteur écrit est l'émetteur réel, jamais celui annoncé.
        if (!_inside) then { _who = (["actor", objNull] call comspec_atak_native_fnc_tocAccess) select 0; _sd = ""; };
        private _role = "";
        if (_who isEqualType objNull) then {
            if (!isNull _who) then {
                if !(_sd in _sides) then { _sd = str side group _who; };
                _role = ["roleOf", [_who, [_hall, ""] select !(_hall isEqualType "")]] call comspec_atak_native_fnc_tocAccess;
            };
            _who = if (isNull _who) then { "MISSION" } else { (["nameOf", _who] call comspec_atak_native_fnc_tocAccess) };
        };
        if !(_who isEqualType "") then { _who = str _who; };
        if !(_hall isEqualType "") then { _hall = ""; };
        if !(_act isEqualType "") then { _act = str _act; };
        if !(_det isEqualType "") then { _det = str _det; };
        if !(_ok isEqualType true) then { _ok = true; };
        if !(_sd in _sides) then { _sd = "ALL"; };
        private _all = localNamespace getVariable ["COMSPEC_ATAK_TocJournalSrv", createHashMap];
        private _n = (localNamespace getVariable ["COMSPEC_ATAK_TocJournalSeq", 0]) + 1;
        localNamespace setVariable ["COMSPEC_ATAK_TocJournalSeq", _n];
        private _l = [_n, ["zulu"] call comspec_atak_native_fnc_tocRoom, serverTime, [_sd, ""] select (_sd isEqualTo "ALL"), _hall select [0, 40], _who select [0, 48], _role, _act select [0, 32], _det select [0, 160], _ok];
        _all set [_sd, [_all getOrDefault [_sd, []], _l, call _max] call _push];
        localNamespace setVariable ["COMSPEC_ATAK_TocJournalSrv", _all];
        // Une ligne, une fois, dans le journal COMSPEC (celui que les joueurs envoient au support).
        if (missionNamespace getVariable ["comspec_atak_native_toc_journal_log", true]) then {
            ["INFO", "TOC", format ["Journal du TOC n° %1 · %2", _n, _l call _line]] call comspec_atak_native_fnc_log;
        };
        ["comspec_atak_toc_journalAdded", [_l]] call CBA_fnc_localEvent;
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "read": {
        if (!isServer || {!_inside}) exitWith { [] };
        _a params [["_sd", ""], ["_n", 200]];
        private _all = localNamespace getVariable ["COMSPEC_ATAK_TocJournalSrv", createHashMap];
        private _l = if (_sd isEqualTo "") then {
            private _m = [];
            { _m append _y; } forEach _all;
            _m sort true;
            _m
        } else { +(_all getOrDefault [_sd, []]) };
        _l select [0 max ((count _l) - ((_n max 1) min 500))]
    };
    case "get": {
        _a params [["_sd", ""], ["_n", 200]];
        if (!hasInterface) exitWith { false };
        ["getSrv", [_sd, _n, player]] remoteExecCall ["comspec_atak_native_fnc_tocJournal", 2];
        true
    };
    case "getSrv": {
        if (!isServer) exitWith { false };
        _a params [["_sd", ""], ["_n", 200], ["_claimed", objNull]];
        if !(_sd isEqualType "" && {_n isEqualType 0}) exitWith { false };
        (["actor", _claimed] call comspec_atak_native_fnc_tocAccess) params ["_u", "_tr"];
        if (isNull _u) exitWith { false };
        // Un chef de poste lit le journal de son camp ; seul l'administrateur Zeus lit un autre camp ou tous ("").
        private _lvl = ["level", [_u, "", _tr]] call comspec_atak_native_fnc_tocAccess;
        if (_lvl < 3 || {!(_sd in (_sides + [""]))}) then { _sd = str side group _u; };
        if !(["check", [_u, "journal", "", "", ""]] call comspec_atak_native_fnc_tocAccess) exitWith { false };
        private _tocTrusted = true;
        private _l = ["read", [_sd, _n]] call comspec_atak_native_fnc_tocJournal;
        ["recv", [_sd, _l]] remoteExecCall ["comspec_atak_native_fnc_tocJournal", owner _u];
        true
    };
    case "recv": {
        // Réponse du serveur seulement (un autre joueur ne peut pas glisser un faux journal).
        if (!hasInterface || {isMultiplayer && {isRemoteExecuted} && {!(remoteExecutedOwner in [0, 2])}}) exitWith { false };
        _a params [["_sd", ""], ["_l", []]];
        missionNamespace setVariable ["COMSPEC_ATAK_TocJournalView", [_sd, _l, diag_tickTime]];
        ["comspec_atak_toc_journal", [_sd, _l]] call CBA_fnc_localEvent;
        true
    };
    case "view": { missionNamespace getVariable ["COMSPEC_ATAK_TocJournalView", ["", [], 0]] };
    case "dump": {
        if (!isServer || {!_inside}) exitWith { [] };
        private _out = [];
        // 200 lignes par camp dans la sauvegarde (plafond de taille du profil) ; l'anneau en mémoire garde le reste.
        { _out pushBack [_x, _y select [0 max ((count _y) - 200)]]; } forEach (localNamespace getVariable ["COMSPEC_ATAK_TocJournalSrv", createHashMap]);
        _out
    };
    case "load": {
        if (!isServer || {!_inside} || {!(_a isEqualType [])}) exitWith { false };
        private _all = localNamespace getVariable ["COMSPEC_ATAK_TocJournalSrv", createHashMap];
        private _seq = localNamespace getVariable ["COMSPEC_ATAK_TocJournalSeq", 0];
        {
            _x params [["_sd", ""], ["_l", []]];
            if !(_sd in (_sides + ["ALL"]) && {_l isEqualType []}) then { continue };
            _l = _l select { _x isEqualType [] && {(count _x) >= 10} };
            { _seq = _seq max (_x select 0); } forEach _l;
            // Lignes de la partie précédente d'abord, celles de cette partie ensuite.
            private _buf = _l + (_all getOrDefault [_sd, []]);
            _all set [_sd, _buf select [0 max ((count _buf) - (call _max))]];
        } forEach _a;
        localNamespace setVariable ["COMSPEC_ATAK_TocJournalSrv", _all];
        localNamespace setVariable ["COMSPEC_ATAK_TocJournalSeq", _seq];
        true
    };
    default { false };
};
