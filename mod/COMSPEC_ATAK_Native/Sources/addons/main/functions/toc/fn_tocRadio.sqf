/*
    Activité radio du camp (demande de TTRD : « une UI PTT pour savoir qui parle, sur quelle fréquence »).
    Modèle de données seul ; l'affichage est dans fn_tocRadioUi (console du poste, écran mural RADIO, encadré à l'écran).
    Audit : docs/audit/2026-11-radio-ptt.md.

    D'où viennent les émissions (chaque joueur signale la sienne, aucun mod radio n'est requis) :
      - ACRE2 : événements CBA acre_startedSpeaking / acre_stoppedSpeaking (émission sur une radio seulement), fréquence
        et libellé du canal par fn_mpu5 « freq », volume de la radio ;
      - TFAR : événement OnTangent (gestionnaire d'événements de TFAR), fréquence de la radio de l'alternat (canal principal ou
        additionnel), canal, volume ;
      - ni l'un ni l'autre : touches d'alternat du jeu (canaux camp, commandement, groupe, véhicule, canaux créés par
        la mission), sans fréquence : le réseau porte le nom du canal ;
      - stations écrites par la mission ou le Zeus : « srvInject » (serveur).
    ACRE2 et TFAR ne sont lus que par fn_radioGuard (« api », « has », « read ») : tout lien coupé, rien ne part.
    Voix portée par le maillage MPU5 (fn_mpuVoice, COMSPEC_ATAK_VoiceMe) : l'émission le dit (« maillage »).

    Coût : événements seulement, aucune boucle. Rien ne circule tant que personne ne regarde : le serveur n'allume la
    collecte (COMSPEC_ATAK_TocRadioOn, variable publique : heure du serveur à l'allumage) qu'au premier abonné (console
    ouverte, écran RADIO dessiné, encadré activé) ou s'il existe un écran ou une salle TOC. Allumée, chaque début et
    chaque fin d'émission est un petit message au serveur ; le serveur regroupe ce qu'il renvoie (0,25 s) et ne l'envoie
    qu'aux abonnés du camp (Zeus et administrateur : tous les camps). Un joueur qui rejoint reçoit l'état complet en
    s'abonnant ; un message manqué (numéro de révision) redemande l'état complet.
    Ce que voit le poste : les réseaux de sa liste de veille (COMSPEC_ATAK_TocRadioNets_<CAMP>, variable publique, sinon
    le réglage serveur « Réseaux surveillés par défaut »), ou tous ceux du camp si le réglage serveur « Le TOC voit tous
    les réseaux du camp » est coché, ou pour le Zeus (« tout surveiller »). Le tri se fait à l'affichage.

    État d'un camp : [révision, émissions en cours, historique (50 dernières), réseaux entendus (40 au plus)]
      émission  [id, indicatif, nom, groupe, réseau (clé), libellé du canal, genre "SW" | "LR" | "VEH" | "VON" | "STN",
                 par le maillage, volume 0 à 1 (-1 : inconnu), début (serverTime), heure ZULU]
      historique [indicatif, groupe, réseau, libellé, genre, heure ZULU, durée en s, fin (serverTime), collision]
      réseau     [réseau, libellé, dernier indicatif, fin (serverTime), émissions, secondes]
    Clé d'un réseau : fréquence en MHz à 3 décimales (« 31.000 »), sinon un nom en majuscules (« VON-CAMP »).
    Liste de veille : [[nom, clé, contrôle radio attendu]...] ; texte « CMD NET=31.000*, AIR 40.500, 152.250 »
    (* : alerte quand le réseau reste silencieux plus longtemps que le réglage « Silence d'un réseau »).

    Modes sans effet (rejoués par tests/Unit/AtakTocRadioAssetTest.php sur la table _vectors, et en jeu par « selfCheck ») :
      ["key", valeur] -> clé ; ["isFreq", clé] ; ["parse", texte] -> liste de veille ; ["text", liste] -> texte
      ["netName", [clé, liste, libellé]] ; ["visible", [lignes, index de la clé, liste, tout voir]] ; ["clash", émissions] -> clés
      ["silence", [dernière fin, maintenant, seuil en min, début de veille]] -> minutes ou -1
      ["push", [liste, ligne, taille]] ; ["occupancy", [historique, émissions, clé, maintenant, fenêtre en s]] -> %
      ["apply", [état, message, taille]] -> état, ou [] s'il manque un message ; ["dur", s] -> « 1:05 » ; ["ago", s]
    Liste de veille : changée par qui a le droit « régler la salle » (fn_tocAccess : chef de poste, administrateur Zeus).
    Client : ["initClient"] ; ["canEdit", camp] ; ["sub", "" | "*"] abonnement (camp du joueur, tous les camps) ; ["state", camp]
      ["nets", camp] liste de veille ; ["setNets", [camp, liste]] ; ["seeAll"] ; ["tuned"] réseaux de mes radios
      ["model", [camp, tout voir, filtre de l'historique (clé), mes réseaux seulement]] -> table pour l'affichage
    Serveur : ["initServer"] ; ["srvInject", [camp, indicatif, réseau, secondes, libellé]] station de la mission.
      Exemple (serveur) : ["srvInject", ["WEST", "TOUR DE CONTRÔLE", 40.5, 8, "AIR"]] call comspec_atak_native_fnc_tocRadio;
*/
params [["_mode", "state"], ["_a", []]];
private _sides = ["WEST", "EAST", "GUER", "CIV"];
// Appel distant : seule la liste de veille arrive ainsi (l'émetteur fait foi, fn_tocAccess) ; le reste vient des événements.
if (isRemoteExecuted && {isMultiplayer} && {_mode isNotEqualTo "srvNets"}) exitWith { false };
private _max = 50;
private _rg = { ["api", _this] call comspec_atak_native_fnc_radioGuard };
private _blank = { [0, [], [], []] };
private _cut = {
    params ["_s", "_n"];
    forceUnicode 0;
    if ((count _s) <= _n) exitWith { _s };
    forceUnicode 0;
    _s select [0, _n]
};
// ---- Fonctions sans effet ----
private _isFreq = { _this regexMatch "^[0-9]+\.[0-9]{3}$" };
private _keyOf = {
    params [["_v", ""]];
    if (_v isEqualType 0) exitWith { ["", ((round (_v * 1000)) / 1000) toFixed 3] select (_v > 0) };
    if !(_v isEqualType "") exitWith { "" };
    private _s = toUpper (trim _v);
    if (_s regexMatch "^[0-9]+(\.[0-9]+)?( ?MHZ)?$") exitWith {
        private _n = parseNumber (_s regexReplace [" ?MHZ$", ""]);
        ["", ((round (_n * 1000)) / 1000) toFixed 3] select (_n > 0)
    };
    [_s, 24] call _cut
};
private _parse = {
    params [["_txt", ""]];
    if !(_txt isEqualType "") exitWith { [] };
    private _out = [];
    {
        private _it = trim _x;
        private _chk = _it regexMatch ".*\*$";
        if (_chk) then { _it = trim (_it regexReplace ["\*$", ""]); };
        if (_it isEqualTo "") then { continue };
        private _name = "";
        private _raw = _it;
        private _eq = _it find "=";
        if (_eq >= 0) then {
            _name = trim (_it select [0, _eq]);
            _raw = trim (_it select [_eq + 1]);
        } else {
            private _w = _it splitString " ";
            private _tail = _w param [(count _w) - 1, ""];
            if ((count _w) > 1 && {([_tail] call _keyOf) call _isFreq}) then {
                _raw = _tail;
                _name = (_w select [0, (count _w) - 1]) joinString " ";
            };
        };
        private _k = [_raw] call _keyOf;
        if (_k isEqualTo "" || {(_out findIf { (_x select 1) isEqualTo _k }) >= 0}) then { continue };
        if (_name isEqualTo "" && {!(_k call _isFreq)}) then { _name = _raw; };
        _out pushBack [[_name, 24] call _cut, _k, _chk];
    } forEach (_txt splitString (",;" + toString [10, 13]));
    _out select [0, 24]
};
private _toText = {
    (_this apply {
        _x params [["_n", ""], ["_k", ""], ["_c", false]];
        ([_k, format ["%1=%2", _n, _k]] select (_n isNotEqualTo "" && {_n isNotEqualTo _k})) + (["", "*"] select _c)
    }) joinString ", "
};
private _netName = {
    params [["_k", ""], ["_tbl", []], ["_lbl", ""]];
    private _i = _tbl findIf { (_x select 1) isEqualTo _k };
    private _n = "";
    if (_i >= 0) then { _n = (_tbl select _i) select 0; };
    if (_n isNotEqualTo "") exitWith { _n };
    if (_lbl isNotEqualTo "") exitWith { _lbl };
    [_k, _k + " MHz"] select (_k call _isFreq)
};
private _visible = {
    params [["_rows", []], ["_idx", 0], ["_tbl", []], ["_all", false]];
    if (_all) exitWith { +_rows };
    private _keys = _tbl apply { _x select 1 };
    _rows select { (_x param [_idx, ""]) in _keys }
};
private _clash = {
    private _act = _this;
    private _out = [];
    {
        private _k = _x select 4;
        if (!(_k in _out) && {({ (_x select 4) isEqualTo _k } count _act) > 1}) then { _out pushBack _k; };
    } forEach _act;
    _out
};
private _silence = {
    params [["_last", -1], ["_now", 0], ["_min", 0], ["_ref", 0]];
    if (_min <= 0) exitWith { -1 };
    private _m = floor ((_now - (_last max _ref)) / 60);
    [-1, _m] select (_m >= _min)
};
private _push = {
    params [["_list", []], ["_row", []], ["_n", 50]];
    private _out = _list + [_row];
    _out select [((count _out) - _n) max 0]
};
private _occupancy = {
    params [["_hist", []], ["_act", []], ["_k", ""], ["_now", 0], ["_win", 600]];
    private _from = _now - _win;
    private _sum = 0;
    {
        if ((_x select 2) isEqualTo _k) then {
            private _end = (_x select 7) min _now;
            private _d = _end - (((_x select 7) - (_x select 6)) max _from);
            if (_d > 0) then { _sum = _sum + _d; };
        };
    } forEach _hist;
    { if ((_x select 4) isEqualTo _k) then { _sum = _sum + ((_now - ((_x select 9) max _from)) max 0); }; } forEach _act;
    ((round (100 * _sum / (_win max 1))) min 100) max 0
};
private _apply = {
    params [["_s", []], ["_d", []], ["_n", 50]];
    if ((count _s) < 4) then { _s = call _blank; };
    if ((_d param [0, ""]) isEqualTo "S") exitWith { [_d select 1, +(_d select 2), +(_d select 3), +(_d select 4)] };
    private _rev = _d param [1, -1];
    if (_rev <= (_s select 0)) exitWith { _s };
    if (_rev > (_s select 0) + 1) exitWith { [] };
    _s params ["", "_act", "_hist", "_nets"];
    switch (_d select 0) do {
        case "B": {
            private _e = _d select 2;
            _act = (_act select { (_x select 0) isNotEqualTo (_e select 0) }) + [_e];
        };
        case "E": {
            _d params ["", "", "_id", ["_h", []], ["_net", []]];
            _act = _act select { (_x select 0) isNotEqualTo _id };
            if (_h isNotEqualTo []) then { _hist = [_hist, _h, _n] call _push; };
            if (_net isNotEqualTo []) then {
                _nets = (_nets select { (_x select 0) isNotEqualTo (_net select 0) }) + [_net];
                _nets = _nets select [((count _nets) - 40) max 0];
            };
        };
    };
    [_rev, _act, _hist, _nets]
};
private _dur = {
    private _t = (round _this) max 0;
    private _sec = _t mod 60;
    format ["%1:%2%3", floor (_t / 60), ["", "0"] select (_sec < 10), _sec]
};
private _ago = {
    private _t = (floor _this) max 0;
    if (_t < 60) exitWith { format ["%1 s", _t] };
    if (_t < 3600) exitWith { format ["%1 min", floor (_t / 60)] };
    private _mn = floor ((_t mod 3600) / 60);
    format ["%1 h %2%3", floor (_t / 3600), ["", "0"] select (_mn < 10), _mn]
};
// ---- TABLE _vectors (JSON) : [mode, argument, résultat attendu]. Rejoués par « selfCheck » en jeu et par
// tests/Unit/AtakTocRadioAssetTest.php (mêmes calculs écrits en PHP).
private _vectors = [
["key", 31, "31.000"], ["key", 148.525, "148.525"], ["key", "40.5", "40.500"], ["key", " 152.25 mhz ", "152.250"], ["key", "von-camp", "VON-CAMP"], ["key", 0, ""], ["key", "", ""],
["isFreq", "31.000", true], ["isFreq", "VON-CAMP", false], ["isFreq", "31.5", false],
["parse", "CMD NET=31.000*, AIR 40.500; 152.25, cmd=31, VON-CAMP, ,*", [["CMD NET", "31.000", true], ["AIR", "40.500", false], ["", "152.250", false], ["VON-CAMP", "VON-CAMP", false]]],
["parse", "ALPHA 1-1 = 45.125", [["ALPHA 1-1", "45.125", false]]],
["text", [["CMD NET", "31.000", true], ["", "152.250", false], ["VON-CAMP", "VON-CAMP", false]], "CMD NET=31.000*, 152.250, VON-CAMP"],
["netName", ["31.000", [["CMD NET", "31.000", true]], "Canal 1"], "CMD NET"], ["netName", ["40.500", [["", "40.500", false]], "Canal 3"], "Canal 3"],
["netName", ["40.500", [], ""], "40.500 MHz"], ["netName", ["VON-CAMP", [], ""], "VON-CAMP"],
["visible", [[["a", "31.000"], ["b", "40.500"], ["c", "31.000"]], 1, [["CMD", "31.000", false]], false], [["a", "31.000"], ["c", "31.000"]]],
["visible", [[["a", "31.000"], ["b", "40.500"]], 1, [], true], [["a", "31.000"], ["b", "40.500"]]],
["visible", [[["a", "31.000"]], 1, [], false], []],
["clash", [["1", "", "", "", "31.000"], ["2", "", "", "", "40.500"], ["3", "", "", "", "31.000"]], ["31.000"]], ["clash", [["1", "", "", "", "31.000"]], []],
["silence", [100, 900, 10, 0], 13], ["silence", [100, 600, 10, 0], -1], ["silence", [-1, 1300, 10, 500], 13], ["silence", [100, 9000, 0, 0], -1],
["push", [[1, 2, 3], 4, 3], [2, 3, 4]], ["push", [[], 1, 3], [1]],
["occupancy", [[["A", "", "31.000", "", "SW", "", 30, 1000, false], ["B", "", "40.500", "", "SW", "", 60, 1000, false], ["C", "", "31.000", "", "SW", "", 100, 450, false]], [["9", "", "", "", "31.000", "", "SW", false, -1, 1170]], "31.000", 1200, 600], 10],
["apply", [[3, [], [], []], ["B", 4, ["7", "ALPHA", "", "", "31.000"]], 50], [4, [["7", "ALPHA", "", "", "31.000"]], [], []]],
["apply", [[4, [["7", "ALPHA", "", "", "31.000"]], [], []], ["E", 5, "7", ["ALPHA", "", "31.000"], ["31.000", "", "ALPHA", 20, 1, 6]], 50], [5, [], [["ALPHA", "", "31.000"]], [["31.000", "", "ALPHA", 20, 1, 6]]]],
["apply", [[5, [], [], []], ["B", 5, ["7"]], 50], [5, [], [], []]], ["apply", [[5, [], [], []], ["B", 7, ["7"]], 50], []],
["apply", [[9, [["1"]], [], []], ["S", 2, [], [["h"]], []], 50], [2, [], [["h"]], []]],
["dur", 65, "1:05"], ["dur", 9.4, "0:09"], ["ago", 42, "42 s"], ["ago", 725, "12 min"], ["ago", 3900, "1 h 05"]
];
// ---- FIN
private _pure = createHashMapFromArray [["key", { [_this] call _keyOf }], ["isFreq", _isFreq], ["parse", { [_this] call _parse }], ["text", _toText], ["netName", _netName],
    ["visible", _visible], ["clash", _clash], ["silence", _silence], ["push", _push], ["occupancy", _occupancy], ["apply", _apply], ["dur", _dur], ["ago", _ago]];
if (_mode in _pure) exitWith { _a call (_pure get _mode) };
// ---- Serveur : état des camps, file d'envoi ----
private _queue = {
    params ["_side", "_msg"];
    private _q = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioQ", createHashMap];
    missionNamespace setVariable ["COMSPEC_ATAK_TocRadioQ", _q];
    private _pending = _q getOrDefault [_side, []];
    _pending pushBack _msg;
    _q set [_side, _pending];
    if !(missionNamespace getVariable ["COMSPEC_ATAK_TocRadioFlush", false]) then {
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioFlush", true];
        [{ ["srvFlush"] call comspec_atak_native_fnc_tocRadio; }, [], 0.25] call comspec_atak_native_fnc_waitAndExec;
    };
};
private _store = { private _m = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioSrv", createHashMap]; missionNamespace setVariable ["COMSPEC_ATAK_TocRadioSrv", _m]; _m };
private _srvBegin = {
    params ["_side", "_e"];
    private _m = call _store;
    private _s = _m getOrDefault [_side, call _blank];
    // Deux émetteurs sur le même réseau : collision, notée pour les deux.
    private _same = (_s select 1) select { (_x select 4) isEqualTo (_e select 4) && {(_x select 0) isNotEqualTo (_e select 0)} };
    if (_same isNotEqualTo []) then {
        private _cl = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioClash", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioClash", _cl];
        { _cl set [_x select 0, true]; } forEach (_same + [_e]);
    };
    private _msg = ["B", (_s select 0) + 1, _e];
    _m set [_side, [_s, _msg, _max] call _apply];
    [_side, _msg] call _queue;
    // Fin jamais reçue (joueur parti, jeu figé) : l'émission est close d'office au bout de 3 min.
    [{ ["srvCap", _this] call comspec_atak_native_fnc_tocRadio; }, [_side, _e select 0, _e select 9], 180] call comspec_atak_native_fnc_waitAndExec;
};
private _srvEnd = {
    params ["_side", "_id"];
    private _m = call _store;
    private _s = _m getOrDefault [_side, call _blank];
    private _i = (_s select 1) findIf { (_x select 0) isEqualTo _id };
    if (_i < 0) exitWith { false };
    ((_s select 1) select _i) params ["", "_cs", "", "_grp", "_k", "_lbl", "_kind", "", "", "_t0", "_z"];
    private _len = (serverTime - _t0) max 0;
    private _cl = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioClash", createHashMap];
    private _hit = _cl getOrDefault [_id, false];
    _cl deleteAt _id;
    // Simple clic d'alternat (moins de 0,4 s) : ni historique ni compteur.
    private _h = [];
    private _net = [];
    if (_len >= 0.4) then {
        _h = [_cs, _grp, _k, _lbl, _kind, _z, (round (_len * 10)) / 10, serverTime, _hit];
        private _old = ((_s select 3) select { (_x select 0) isEqualTo _k }) param [0, [_k, _lbl, "", -1, 0, 0]];
        _net = [_k, [_old select 1, _lbl] select (_lbl isNotEqualTo ""), _cs, serverTime, (_old select 4) + 1, round ((_old select 5) + _len)];
    };
    private _msg = ["E", (_s select 0) + 1, _id, _h, _net];
    _m set [_side, [_s, _msg, _max] call _apply];
    [_side, _msg] call _queue;
    true
};
private _srvOn = {
    if ((missionNamespace getVariable ["COMSPEC_ATAK_TocRadioOn", -1]) < 0) then {
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioOn", serverTime max 0, true];
    };
};
private _zulu = { ["zulu"] call comspec_atak_native_fnc_tocRoom };
private _enabled = { (missionNamespace getVariable ["comspec_atak_native_toc_radio_enabled", true]) isEqualTo true };
switch (_mode) do {
    case "selfCheck": {
        private _bad = _vectors select { _x params ["_f", "_arg", "_want"]; ((_arg call (_pure get _f)) isNotEqualTo _want) };
        { diag_log format ["[COMSPEC ATAK NATIVE][WARNING][TOC] Activité radio, contrôle en échec : %1", _x]; } forEach _bad;
        _bad isEqualTo []
    };
    // ================= SERVEUR =================
    case "initServer": {
        if (!isServer) exitWith { false };
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocRadioInit", false]) exitWith { true };
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioInit", true];
        ["comspec_atak_native_tocRadio", {
            params [["_m", ""], ["_arg", []]];
            if (_m in ["srvTx", "srvSub"]) then { [_m, _arg] call comspec_atak_native_fnc_tocRadio; };
        }] call CBA_fnc_addEventHandler;
        // Joueur parti en pleine émission : elle se ferme, et son abonnement tombe.
        addMissionEventHandler ["PlayerDisconnected", {
            params ["", "", "", "", "_owner"];
            ["srvGone", _owner] call comspec_atak_native_fnc_tocRadio;
        }];
        // Écrans ou salle TOC déjà là au lancement : la collecte s'allume sans attendre un premier abonné.
        [{
            if ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) isNotEqualTo [] || {(missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]) isNotEqualTo []}) then {
                ["srvOn"] call comspec_atak_native_fnc_tocRadio;
            };
        }, [], 20] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "srvOn": { if (isServer && {call _enabled}) then { call _srvOn; }; true };
    case "srvSub": {
        if (!isServer || {!(call _enabled)}) exitWith { false };
        _a params [["_owner", -1], ["_u", objNull], ["_scope", ""]];
        if (!(_owner isEqualType 0) || {_owner < 0} || {!(_u isEqualType objNull)} || {isNull _u} || {!(_scope isEqualType "")}) exitWith { false };
        // Tous les camps : Zeus, administrateur ou hôte seulement. Sinon, le camp du joueur.
        private _wide = _scope isEqualTo "*" && {!isNull (getAssignedCuratorLogic _u) || {(admin _owner) > 0} || {_owner <= 2}};
        private _sc = ["*", str side group _u] select (!_wide);
        if (!_wide && {!(_sc in _sides)}) exitWith { false };
        private _subs = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioSubs", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioSubs", _subs];
        _subs set [_owner, _sc];
        call _srvOn;
        private _m = call _store;
        {
            private _s = _m getOrDefault [_x, call _blank];
            ["comspec_atak_native_tocRadioMsg", [_x, [["S", _s select 0, _s select 1, _s select 2, _s select 3]]], _owner] call CBA_fnc_ownerEvent;
        } forEach ([[_sc], _sides] select _wide);
        true
    };
    case "srvGone": {
        if (!isServer) exitWith { false };
        (missionNamespace getVariable ["COMSPEC_ATAK_TocRadioSubs", createHashMap]) deleteAt _a;
        private _own = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioOwn", createHashMap];
        {
            if (_y isEqualTo _a) then {
                private _id = _x;
                { [_x, _id] call _srvEnd; } forEach _sides;
                _own deleteAt _id;
            };
        } forEach +_own;
        true
    };
    case "srvTx": {
        if (!isServer || {!(call _enabled)} || {(missionNamespace getVariable ["COMSPEC_ATAK_TocRadioOn", -1]) < 0}) exitWith { false };
        _a params [["_u", objNull], ["_on", false], ["_k", ""], ["_lbl", ""], ["_kind", "SW"], ["_lvl", -1], ["_mesh", false], ["_cs", ""]];
        if (!(_u isEqualType objNull) || {isNull _u}) exitWith { false };
        private _id = netId _u;
        private _side = str side group _u;
        if (_on isNotEqualTo true) exitWith {
            (missionNamespace getVariable ["COMSPEC_ATAK_TocRadioOwn", createHashMap]) deleteAt _id;
            private _done = false;
            { if ([_x, _id] call _srvEnd) then { _done = true; }; } forEach _sides;
            _done
        };
        _k = [_k] call _keyOf;
        if (_k isEqualTo "" || {!(_side in _sides)}) exitWith { false };
        if !(_lbl isEqualType "") then { _lbl = ""; };
        if !(_cs isEqualType "") then { _cs = ""; };
        // Nom montré : résolveur commun du TOC (indicatif Athena, sinon groupe et rang), jamais le pseudo.
        if (_cs isEqualTo "") then { _cs = [_u] call comspec_atak_native_fnc_tocDisplayName; };
        // Réglage « Afficher seulement les entités équipées d'un ATAK » (fn_tocHasAtak, s'il est chargé) : les autres ne sont pas montrées.
        if !(["keep", _u] call (missionNamespace getVariable ["comspec_atak_native_fnc_tocHasAtak", { true }])) exitWith { false };
        if !(_kind in ["SW", "LR", "VEH", "VON"]) then { _kind = "SW"; };
        if !(_lvl isEqualType 0) then { _lvl = -1; };
        // Déjà en émission (changement de radio sans relâcher) : la précédente se ferme d'abord.
        { [_x, _id] call _srvEnd; } forEach _sides;
        private _own = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioOwn", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioOwn", _own];
        _own set [_id, owner _u];
        [_side, [_id, [_cs, 40] call _cut, "", [groupId group _u, 24] call _cut, _k, [_lbl, 24] call _cut, _kind, _mesh isEqualTo true, (_lvl min 1) max -1, serverTime, call _zulu]] call _srvBegin;
        true
    };
    case "srvCap": {
        if (!isServer) exitWith { false };
        _a params ["_side", "_id", "_t0"];
        private _s = (call _store) getOrDefault [_side, call _blank];
        if (((_s select 1) findIf { (_x select 0) isEqualTo _id && {(_x select 9) isEqualTo _t0} }) < 0) exitWith { false };
        [_side, _id] call _srvEnd
    };
    case "srvInject": {
        if (!isServer || {!(call _enabled)}) exitWith { false };
        _a params [["_side", "WEST"], ["_cs", "STATION"], ["_k", ""], ["_secs", 5], ["_lbl", ""]];
        _k = [_k] call _keyOf;
        if (!(_side in _sides) || {_k isEqualTo ""} || {!(_cs isEqualType "")} || {_cs isEqualTo ""}) exitWith { false };
        call _srvOn;
        private _id = "ST|" + _cs;
        [_side, _id] call _srvEnd;
        [_side, [_id, [_cs, 24] call _cut, "", "", _k, [_lbl, 24] call _cut, "STN", false, -1, serverTime, call _zulu]] call _srvBegin;
        [{ ["srvStop", _this] call comspec_atak_native_fnc_tocRadio; }, [_side, _id], (_secs max 0.5) min 170] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "srvStop": { if (isServer) then { _a call _srvEnd } else { false } };
    case "srvFlush": {
        if (!isServer) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioFlush", false];
        private _q = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioQ", createHashMap];
        private _subs = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioSubs", createHashMap];
        {
            private _side = _x;
            private _list = _y;
            { if (_y in [_side, "*"]) then { ["comspec_atak_native_tocRadioMsg", [_side, _list], _x] call CBA_fnc_ownerEvent; }; } forEach _subs;
        } forEach _q;
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioQ", createHashMap];
        true
    };
    case "srvNets": {
        // Liste de veille d'un camp : posée par un joueur de ce camp (console du poste), le Zeus ou l'administrateur.
        if (!isServer) exitWith { false };
        _a params [["_side", ""], ["_tbl", []], ["_u", objNull]];
        if (!(_side in _sides) || {!(_tbl isEqualType [])}) exitWith { false };
        // Repassée par le texte : mêmes règles que la saisie (clés refaites, doublons retirés, 24 réseaux au plus).
        private _clean = [(_tbl select { _x isEqualType [] }) call _toText] call _parse;
        // Droit « régler la salle » (fn_tocAccess : chef de poste ou administrateur Zeus selon le réglage) ; l'auteur est
        // l'émetteur de l'appel, jamais l'unité annoncée ; la demande et son refus vont au journal du TOC.
        if !(["check", [_u, "rooms", "", "radio.nets", format ["%1 · %2", _side, _clean call _toText], _side]] call comspec_atak_native_fnc_tocAccess) exitWith { false };
        missionNamespace setVariable [format ["COMSPEC_ATAK_TocRadioNets_%1", _side], _clean, true];
        true
    };
    // ================= CLIENT =================
    case "initClient": {
        if (!hasInterface) exitWith { false };
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocRadioCInit", false]) exitWith { true };
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioCInit", true];
        ["comspec_atak_native_tocRadioMsg", { ["msg", _this] call comspec_atak_native_fnc_tocRadio; }] call CBA_fnc_addEventHandler;
        private _hasAcre = isClass (configFile >> "CfgPatches" >> "acre_main");
        private _hasTfar = isClass (configFile >> "CfgPatches" >> "tfar_core") || {isClass (configFile >> "CfgPatches" >> "task_force_radio")};
        // ACRE2 : événements CBA, lecture seule. Sans ACRE2, ils ne partent jamais.
        ["acre_startedSpeaking", {
            params [["_u", objNull], ["_r", false], ["_id", ""]];
            if (_u isNotEqualTo player || {!(_r isEqualTo true || {_r isEqualTo 1} || {_r isEqualTo "1"})} || {!(_id isEqualType "")} || {_id isEqualTo ""}) exitWith {};
            ["txAcre", _id] call comspec_atak_native_fnc_tocRadio;
        }] call CBA_fnc_addEventHandler;
        ["acre_stoppedSpeaking", {
            params [["_u", objNull]];
            if (_u isEqualTo player) then { ["tx", [false]] call comspec_atak_native_fnc_tocRadio; };
        }] call CBA_fnc_addEventHandler;
        // TFAR : événement de l'alternat (appui et relâche), par la seule porte de fn_radioGuard.
        if (_hasTfar && {["has", ["TFAR_fnc_addEventHandler"]] call comspec_atak_native_fnc_radioGuard}) then {
            ["TFAR_fnc_addEventHandler", ["COMSPEC_ATAK_TocRadio", "OnTangent", { ["txTfar", _this] call comspec_atak_native_fnc_tocRadio; }], false] call _rg;
        };
        // Aucun mod radio : touches d'alternat du jeu.
        if (!_hasAcre && {!_hasTfar}) then {
            {
                _x params ["_key", "_ch"];
                addUserActionEventHandler [_key, "Activate", compile format ["['txVon', [true, %1]] call comspec_atak_native_fnc_tocRadio;", _ch]];
                addUserActionEventHandler [_key, "Deactivate", compile format ["['txVon', [false, %1]] call comspec_atak_native_fnc_tocRadio;", _ch]];
            } forEach [["pushToTalk", -1], ["pushToTalkSide", 1], ["pushToTalkCommand", 2], ["pushToTalkGroup", 3], ["pushToTalkVehicle", 4]];
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioSrc", ["VON", "TFAR", "ACRE2"] select (([_hasTfar, _hasAcre] find true) + 1)];
        true
    };
    case "source": { missionNamespace getVariable ["COMSPEC_ATAK_TocRadioSrc", "VON"] };
    case "txAcre": {
        if !(["read"] call comspec_atak_native_fnc_radioGuard) exitWith { false };
        private _id = _a;
        (["freq", [_id]] call comspec_atak_native_fnc_mpu5) params [["_tx", -1], "", ["_lbl", ""]];
        private _base = toLower (["acre_api_fnc_getBaseRadio", [_id], ""] call _rg);
        if (_lbl isEqualTo "") then {
            private _ch = ["acre_api_fnc_getRadioChannel", [_id], -1] call _rg;
            if (_ch isEqualType 0 && {_ch >= 0}) then { _lbl = format ["Canal %1", _ch]; };
        };
        private _kind = switch (true) do {
            case ((_base find "acre_vrc") isEqualTo 0): { "VEH" };
            case ((["acre_prc117f", "acre_prc77", "acre_sem70"] findIf { (_base find _x) isEqualTo 0 }) >= 0): { "LR" };
            default { "SW" };
        };
        private _vol = ["acre_api_fnc_getRadioVolume", [_id], -1] call _rg;
        ["tx", [true, [_tx, _base] select (!(_tx isEqualType 0) || {_tx <= 0}), _lbl, _kind, [-1, _vol] select (_vol isEqualType 0)]] call comspec_atak_native_fnc_tocRadio
    };
    case "txTfar": {
        _a params [["_u", objNull], ["_radio", ""], ["_type", 0], ["_add", false], ["_down", false]];
        if (_u isNotEqualTo (missionNamespace getVariable ["TFAR_currentUnit", player])) exitWith { false };
        if (_down isNotEqualTo true) exitWith { ["tx", [false]] call comspec_atak_native_fnc_tocRadio };
        if !(["read"] call comspec_atak_native_fnc_radioGuard) exitWith { false };
        private _isLr = _type isEqualTo 1;
        private _ch = [[["TFAR_fnc_getSwChannel", "TFAR_fnc_getLrChannel"], ["TFAR_fnc_getAdditionalSwChannel", "TFAR_fnc_getAdditionalLrChannel"]] select (_add isEqualTo true) select _isLr, _radio, -1] call _rg;
        if !(_ch isEqualType 0) then { _ch = -1; };
        private _f = if (_add isEqualTo true && {_ch >= 0}) then { ["TFAR_fnc_getChannelFrequency", [_radio, _ch + 1], ""] call _rg } else { [["TFAR_fnc_getSwFrequency", "TFAR_fnc_getLrFrequency"] select _isLr, _radio, ""] call _rg };
        private _vol = [["TFAR_fnc_getSwVolume", "TFAR_fnc_getLrVolume"] select _isLr, _radio, -1] call _rg;
        private _kind = "SW";
        if (_isLr) then {
            private _o = if (_radio isEqualType []) then { _radio param [0, objNull] } else { objNull };
            _kind = ["VEH", "LR"] select (!(_o isEqualType objNull) || {isNull _o} || {_o isKindOf "Bag_Base"});
        };
        private _lvl = -1;
        if (_vol isEqualType 0 && {_vol >= 0}) then { _lvl = (_vol / 10) min 1; };
        ["tx", [true, _f, ["", format ["Canal %1", _ch + 1]] select (_ch >= 0), _kind, _lvl]] call comspec_atak_native_fnc_tocRadio
    };
    case "txVon": {
        _a params [["_on", false], ["_ch", -1]];
        if (!_on) exitWith { ["tx", [false]] call comspec_atak_native_fnc_tocRadio };
        if (_ch < 0) then { _ch = currentChannel; };
        // Canal général (0) et voix directe (5) : pas un réseau radio.
        if (_ch in [0, 5]) exitWith { false };
        private _net = switch (_ch) do {
            case 1: { ["VON-CAMP", "Canal camp"] };
            case 2: { ["VON-CMDT", "Canal commandement"] };
            case 3: { ["VON-GRP " + groupId group player, format ["Canal groupe · %1", groupId group player]] };
            case 4: { ["VON-VEH", "Canal véhicule"] };
            default {
                private _n = (radioChannelInfo (_ch - 5)) param [1, ""];
                [["VON-" + str _ch, _n] select (_n isNotEqualTo ""), _n]
            };
        };
        ["tx", [true, _net select 0, _net select 1, "VON", -1]] call comspec_atak_native_fnc_tocRadio
    };
    case "tx": {
        if (!hasInterface || {isNull player}) exitWith { false };
        _a params [["_on", false], ["_raw", ""], ["_lbl", ""], ["_kind", "SW"], ["_lvl", -1]];
        private _was = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioMine", []];
        if (!_on && {_was isEqualTo []}) exitWith { false };
        private _k = [_raw] call _keyOf;
        if (_on && {_k isEqualTo ""}) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioMine", [[], [_k, diag_tickTime]] select _on];
        // Collecte éteinte (personne ne regarde), réglage décoché ou lien radio coupé : rien ne part.
        if ((missionNamespace getVariable ["COMSPEC_ATAK_TocRadioOn", -1]) < 0 || {!(call _enabled)}) exitWith { false };
        if (!_on) exitWith { ["comspec_atak_native_tocRadio", ["srvTx", [player, false]]] call CBA_fnc_serverEvent; true };
        private _mesh = ((missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) param [0, []]) isNotEqualTo [];
        ["comspec_atak_native_tocRadio", ["srvTx", [player, true, _k, _lbl, _kind, _lvl, _mesh, [player] call comspec_atak_native_fnc_tocDisplayName]]] call CBA_fnc_serverEvent;
        true
    };
    case "sub": {
        if (!hasInterface || {isNull player} || {!(call _enabled)}) exitWith { false };
        private _cur = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioScope", ""];
        private _sc = [str side group player, "*"] select (_a isEqualTo "*" || {_cur isEqualTo "*"});
        if (_cur isEqualTo _sc && {_a isNotEqualTo "resync"}) exitWith { true };
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioScope", _sc];
        ["comspec_atak_native_tocRadio", ["srvSub", [clientOwner, player, _sc]]] call CBA_fnc_serverEvent;
        true
    };
    case "msg": {
        _a params [["_side", ""], ["_list", []]];
        private _cl = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioCl", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioCl", _cl];
        private _s = _cl getOrDefault [_side, call _blank];
        private _gap = false;
        {
            private _n = [_s, _x, _max] call _apply;
            if (_n isEqualTo []) exitWith { _gap = true; };
            _s = _n;
        } forEach _list;
        _cl set [_side, _s];
        // Message manqué : état complet redemandé.
        if (_gap) then { ["sub", "resync"] call comspec_atak_native_fnc_tocRadio; };
        ["comspec_atak_native_tocRadioChanged", [_side]] call CBA_fnc_localEvent;
        true
    };
    case "state": { (missionNamespace getVariable ["COMSPEC_ATAK_TocRadioCl", createHashMap]) getOrDefault [_a, call _blank] };
    case "seeAll": { (missionNamespace getVariable ["comspec_atak_native_toc_radio_see_all", false]) isEqualTo true };
    case "nets": {
        private _t = missionNamespace getVariable (format ["COMSPEC_ATAK_TocRadioNets_%1", _a]);
        if (isNil "_t") exitWith { [missionNamespace getVariable ["comspec_atak_native_toc_radio_nets", ""]] call _parse };
        _t
    };
    case "setNets": {
        _a params [["_side", ""], ["_tbl", []]];
        if (!hasInterface || {isNull player}) exitWith { false };
        ["srvNets", [_side, _tbl, player]] remoteExecCall ["comspec_atak_native_fnc_tocRadio", 2];
        true
    };
    case "canEdit": {
        // Estimation pour l'affichage (le serveur fait foi) : droit « régler la salle » sur le camp montré.
        if (!hasInterface || {isNull player}) exitWith { false };
        (["level", [player, "", false, _a]] call comspec_atak_native_fnc_tocAccess) >= (["need", ["rooms", ["mode"] call comspec_atak_native_fnc_tocAccess]] call comspec_atak_native_fnc_tocAccess)
    };
    case "tuned": {
        // Réseaux de mes radios (encadré à l'écran : je ne vois que ce que je peux entendre). Gardé 5 s.
        (missionNamespace getVariable ["COMSPEC_ATAK_TocRadioTuned", [-10, []]]) params ["_at", "_keys"];
        if (diag_tickTime - _at < 5) exitWith { _keys };
        _keys = [];
        if (["read"] call comspec_atak_native_fnc_radioGuard) then {
            switch (["source"] call comspec_atak_native_fnc_tocRadio) do {
                case "ACRE2": { { _keys pushBackUnique ([_x param [4, -1]] call _keyOf); } forEach (["radios"] call comspec_atak_native_fnc_mpu5); };
                case "TFAR": {
                    private _sw = ["TFAR_fnc_activeSwRadio", [], ""] call _rg;
                    if (_sw isEqualType "" && {_sw isNotEqualTo ""}) then {
                        _keys pushBackUnique ([["TFAR_fnc_getSwFrequency", _sw, ""] call _rg] call _keyOf);
                        private _ac = ["TFAR_fnc_getAdditionalSwChannel", _sw, -1] call _rg;
                        if (_ac isEqualType 0 && {_ac >= 0}) then { _keys pushBackUnique ([["TFAR_fnc_getChannelFrequency", [_sw, _ac + 1], ""] call _rg] call _keyOf); };
                    };
                    private _lr = ["TFAR_fnc_activeLrRadio", [], []] call _rg;
                    if (_lr isEqualType [] && {(count _lr) > 1}) then {
                        _keys pushBackUnique ([["TFAR_fnc_getLrFrequency", _lr, ""] call _rg] call _keyOf);
                        private _al = ["TFAR_fnc_getAdditionalLrChannel", _lr, -1] call _rg;
                        if (_al isEqualType 0 && {_al >= 0}) then { _keys pushBackUnique ([["TFAR_fnc_getChannelFrequency", [_lr, _al + 1], ""] call _rg] call _keyOf); };
                    };
                };
                default { _keys = ["VON-CAMP", "VON-CMDT", "VON-VEH", "VON-GRP " + groupId group player] apply { [_x] call _keyOf }; };
            };
        };
        _keys = _keys - [""];
        missionNamespace setVariable ["COMSPEC_ATAK_TocRadioTuned", [diag_tickTime, _keys]];
        _keys
    };
    case "model": {
        // Tout ce que montre un affichage, déjà trié : en cours, couloirs des réseaux, historique, compteurs.
        _a params [["_side", ""], ["_all", false], ["_only", ""], ["_mine", false]];
        private _s = ["state", _side] call comspec_atak_native_fnc_tocRadio;
        _s params ["", "_act", "_hist", "_nets"];
        private _tbl = if (_mine) then { (["tuned"] call comspec_atak_native_fnc_tocRadio) apply { ["", _x, false] } } else { ["nets", _side] call comspec_atak_native_fnc_tocRadio };
        private _wide = !_mine && {_all || {["seeAll"] call comspec_atak_native_fnc_tocRadio}};
        private _now = serverTime;
        private _vAct = [_act, 4, _tbl, _wide] call _visible;
        private _vHist = [_hist, 2, _tbl, _wide] call _visible;
        private _hot = _vAct call _clash;
        private _min = missionNamespace getVariable ["comspec_atak_native_toc_radio_silence", 10];
        private _ref = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioOn", _now];
        private _keys = _tbl apply { _x select 1 };
        if (_wide) then {
            private _extra = [];
            { _extra pushBackUnique (_x select 0); } forEach _nets;
            { _extra pushBackUnique (_x select 4); } forEach _act;
            _extra = _extra - _keys;
            _extra sort true;
            _keys append _extra;
        };
        private _quiet = 0;
        private _lanes = _keys apply {
            private _k = _x;
            private _row = (_tbl select { (_x select 1) isEqualTo _k }) param [0, ["", _k, false]];
            private _net = (_nets select { (_x select 0) isEqualTo _k }) param [0, [_k, "", "", -1, 0, 0]];
            private _on = _vAct select { (_x select 4) isEqualTo _k };
            private _lbl = [_net select 1, (_on param [0, []]) param [5, ""]] select ((_net select 1) isEqualTo "");
            private _sil = [[_net select 3, _now, _min, _ref] call _silence, -1] select (!(_row select 2) || {_on isNotEqualTo []});
            if (_sil >= 0) then { _quiet = _quiet + 1; };
            private _state = switch (true) do {
                case ((count _on) > 1): { "CLASH" };
                case ((count _on) isEqualTo 1): { "TX" };
                case (_sil >= 0): { "SILENT" };
                default { "IDLE" };
            };
            [_k, [_k, _tbl, _lbl] call _netName, ["", _k] select (_k call _isFreq), _state, _sil,
                [(_on apply { _x select 1 }) joinString " + ", _net select 2] select (_on isEqualTo []), _net select 3, _net select 4,
                [_hist, _act, _k, _now, 600] call _occupancy, _k in (_tbl apply { _x select 1 }), _row select 2]
        };
        private _hl = +_vHist;
        reverse _hl;
        if (_only isNotEqualTo "") then { _hl = _hl select { (_x select 2) isEqualTo _only }; };
        createHashMapFromArray [
            ["tbl", _tbl], ["now", _now],
            ["live", _vAct apply { _x + [(_x select 4) in _hot, [_x select 4, _tbl, _x select 5] call _netName] }],
            ["lanes", _lanes], ["hist", _hl apply { _x + [[_x select 2, _tbl, _x select 3] call _netName] }],
            ["kpi", [count _vAct, count _tbl, count _hot, _quiet]], ["wide", _wide]
        ]
    };
    default { false };
};
