/*
    Téléphones personnels (client) : chaque téléphone objet porte son propriétaire.
    Arma ne distingue pas deux objets d'inventaire de même classe : l'identité est une fiche posée sur ce qui contient
    le téléphone, unité, corps, porte-objet ou caisse, déplacée par les événements Take et Put.
      Variable publique COMSPEC_ATAK_Phones : [[id, classe en minuscules, clé du propriétaire, nom]...]
      Variable publique COMSPEC_ATAK_PhoneForeign (unité) : nombre de téléphones d'autrui sur elle (fn_hasDevice).
    Lue par _read : type et bornes vérifiés, le contenu n'est pas prouvé (mécanique de jeu).
    Le code et les données restent sur le serveur (fn_phoneServer) : lire un téléphone donne ses données, figées quand
    il a quitté son propriétaire (mort, Put, Take). Jamais d'appel à Athena avec le compte du lecteur, sauf la
    synchronisation web demandée par le lecteur pour le téléphone d'une IA (fn_phoneSync, jamais celui d'un joueur).
    Limites : deux téléphones de même classe : en posant, celui d'autrui part d'abord. Un téléphone déplacé dans un
    uniforme ou un sac ne déclenche aucun événement : sa fiche est perdue, il devient celui de son nouveau porteur.
    Un joueur parti sans mourir n'a pas écrit ses données : seule la fiche reste.
    Activation : comspec_atak_native_phone_owner. Le code est une mécanique de jeu, pas une sécurité.
    Params : [action, argument]
      "init" "tick" (2 s) "take" [unité, conteneur, objet] "put" idem "killed" unité "records" objet
      "foreign" téléphones d'autrui sur moi       "prepare" à l'ouverture en main : "" | "*PICK*" | id
      "active" téléphone d'autrui affiché ?       "activeRec" sa fiche
      "pick" "" (le mien) | id                    "try" chiffres    "claim"    "state" réponse du serveur
      "snap" données de mon téléphone             "hash" [clé, chiffres]
      "setPin" [chiffres, indice]    "setHint" indice    "needPin"    "hasPin"    "ownPad"    "ownTry" chiffres    "forget"
      "cleared"    "padForeign"    "pinSetup" "set" | "remove" (Réglages : au clavier, code actuel, nouveau, confirmation)
      "pinStep" [étape, chiffres]
*/
params [["_act", ""], ["_arg", []]];
private _myKey = {
    private _k = getPlayerUID player;
    if (_k isEqualTo "") then { _k = netId player; };
    _k
};
private _read = {
    params [["_o", objNull]];
    if (isNull _o) exitWith { [] };
    private _v = _o getVariable ["COMSPEC_ATAK_Phones", []];
    if !(_v isEqualType []) exitWith { [] };
    (+(_v select [0, 12])) select { _x isEqualTypeArray ["", "", "", ""] && {(count (_x select 0)) <= 80} && {(count (_x select 3)) <= 80} }
};
// Sur mon unité : aussi le nombre de téléphones d'autrui. Écrit seulement s'il a changé (variable publique).
private _write = {
    params ["_o", "_recs"];
    if (([_o] call _read) isEqualTo _recs) exitWith {};
    _o setVariable ["COMSPEC_ATAK_Phones", _recs, true];
    if (_o isEqualTo player) then {
        private _me = call _myKey;
        private _n = { (_x select 2) isNotEqualTo _me } count _recs;
        if ((player getVariable ["COMSPEC_ATAK_PhoneForeign", 0]) isNotEqualTo _n) then { player setVariable ["COMSPEC_ATAK_PhoneForeign", _n, true]; };
    };
};
private _newId = {
    params ["_key"];
    private _n = (missionNamespace getVariable ["COMSPEC_ATAK_PhoneSeq", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_PhoneSeq", _n];
    format ["%1_%2_%3_%4", _key, clientOwner, round time, _n]
};
// Le conteneur d'un uniforme, d'un gilet ou d'un sac renvoie à l'unité qui le porte.
private _holderOf = {
    params ["_c"];
    private _p = objectParent _c;
    [_c, _p] select !isNull _p
};
private _isPhone = {
    params ["_item"];
    [] call comspec_atak_native_fnc_deviceCatalog;
    (toLower _item) in (missionNamespace getVariable ["COMSPEC_ATAK_DeviceCatalogSet", createHashMap])
};
private _srv = { params ["_a", "_xa"]; ["comspec_atak_native_phoneSrv", [_a, _xa]] call CBA_fnc_serverEvent; };
private _on = missionNamespace getVariable ["comspec_atak_native_phone_owner", true];
private _view = uiNamespace getVariable ["COMSPEC_ATAK_PhoneView", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_PhoneView", _view];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PHONES") then { ["PHONES"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
private _digitsOk = { params ["_d"]; _d isEqualType "" && {_d regexMatch "^[0-9]{4,8}$"} };

switch (_act) do {
    case "init": {
        if (!hasInterface) exitWith {};
        ["comspec_atak_native_phoneCli", { params [["_kind", ""], ["_data", []]]; [_kind, _data] call comspec_atak_native_fnc_phoneOwn; }] call CBA_fnc_addEventHandler;
        ["CAManBase", "Take", { params ["_unit", "_container", "_item"]; if (_unit isEqualTo player) then { ["take", [_unit, _container, _item]] call comspec_atak_native_fnc_phoneOwn; }; }] call CBA_fnc_addClassEventHandler;
        ["CAManBase", "Put", { params ["_unit", "_container", "_item"]; if (_unit isEqualTo player) then { ["put", [_unit, _container, _item]] call comspec_atak_native_fnc_phoneOwn; }; }] call CBA_fnc_addClassEventHandler;
        ["CAManBase", "Killed", { params ["_unit"]; if (_unit isEqualTo player) then { ["killed", _unit] call comspec_atak_native_fnc_phoneOwn; }; }] call CBA_fnc_addClassEventHandler;
        [{ ["register"] call comspec_atak_native_fnc_phoneOwn; }, [], 5] call comspec_atak_native_fnc_waitAndExec;
    };
    case "register": {
        // Un ancien code de lettres (câble DL-7) ne se tape pas au clavier du téléphone : il ne verrouille pas la lecture.
        private _code = profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""];
        private _hint = profileNamespace getVariable ["COMSPEC_ATAK_LockHint", ""];
        if !(_hint isEqualType "") then { _hint = ""; };
        private _hash = if ([_code] call _digitsOk) then { ["hash", [call _myKey, _code]] call comspec_atak_native_fnc_phoneOwn } else { "" };
        ["pin", [player, _hash, _hint]] call _srv;
    };
    case "hash": {
        // Empreinte de jeu, pas de chiffrement : ne protège pas un vrai code (Réglages le rappelle).
        _arg params [["_key", ""], ["_pin", ""]];
        private _a = 7;
        private _b = 11;
        {
            _a = (_a * 31 + _x) mod 65521;
            _b = (_b * 131 + _a + _x) mod 65519;
        } forEach (toArray format ["%1|%2", _key, _pin]);
        format ["%1-%2", _a, _b]
    };
    case "records": { [_arg] call _read };
    case "foreign": {
        private _me = call _myKey;
        ([player] call _read) select { (_x select 2) isNotEqualTo _me }
    };
    case "active": { missionNamespace getVariable ["COMSPEC_ATAK_PhoneActive", ""] };
    case "activeRec": {
        private _a = missionNamespace getVariable ["COMSPEC_ATAK_PhoneActive", ""];
        (([player] call _read) select { (_x select 0) isEqualTo _a }) param [0, []]
    };
    case "tick": {
        if (!hasInterface || {isNull player} || {!alive player} || {!_on}) exitWith {};
        // Code oublié : après le délai, le propriétaire retrouve son téléphone, sans admin ni Zeus.
        private _fa = missionNamespace getVariable ["COMSPEC_ATAK_PinForgotAt", -1];
        if (_fa >= 0 && {diag_tickTime >= _fa}) then { ["cleared", "délai écoulé"] call comspec_atak_native_fnc_phoneOwn; };
        [] call comspec_atak_native_fnc_deviceCatalog;
        private _set = missionNamespace getVariable ["COMSPEC_ATAK_DeviceCatalogSet", createHashMap];
        private _held = (((assignedItems player) + (items player)) apply { toLower _x }) select { _x in _set };
        _held sort true;
        private _sig = [player, _held];
        if (_sig isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_PhoneSig", []])) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_PhoneSig", _sig];
        private _left = +_held;
        private _keep = [];
        private _orphans = [];
        {
            private _i = _left find (_x select 1);
            if (_i >= 0) then { _left deleteAt _i; _keep pushBack _x; } else { _orphans pushBack _x; };
        } forEach ([player] call _read);
        // Classe changée (téléphone d'un autre mod rendu au ramassage, fn_droppedPhone) : la fiche suit l'objet restant.
        { if (_left isNotEqualTo []) then { _x set [1, _left deleteAt 0]; _keep pushBack _x; }; } forEach _orphans;
        // Objet sans fiche (Arsenal, caisse, mission) : il est à celui qui l'a.
        private _me = call _myKey;
        { _keep pushBack [[_me] call _newId, _x, _me, name player]; } forEach _left;
        [player, _keep] call _write;
        // Téléphone d'autrui affiché mais plus sur moi : rangé.
        private _a = missionNamespace getVariable ["COMSPEC_ATAK_PhoneActive", ""];
        if (_a isNotEqualTo "" && {_a isNotEqualTo "*PICK*"} && {(_keep findIf { (_x select 0) isEqualTo _a }) < 0}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
            [] call comspec_atak_native_fnc_close;
        };
    };
    case "take": {
        if (!_on) exitWith {};
        _arg params ["_unit", "_container", "_item"];
        if !([_item] call _isPhone) exitWith {};
        private _cls = toLower _item;
        private _from = [_container] call _holderOf;
        if (_from isEqualTo _unit) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_PhoneSig", nil];
        private _find = {
            params ["_recs"];
            private _i = _recs findIf { (_x select 1) isEqualTo _cls };
            // Téléphone d'un autre mod échangé au sol contre notre modèle (fn_droppedPhone) : la fiche garde sa classe d'origine.
            if (_i < 0 && {_cls isEqualTo "comspec_atak_phone"} && {_recs isNotEqualTo []}) then { _i = 0; };
            _i
        };
        // Le conteneur annoncé n'a pas de fiche (porte-objet déjà vidé, corps) : les voisins à moins de 4 m.
        private _src = _from;
        if (([[_src] call _read] call _find) < 0) then {
            private _near = (nearestObjects [_unit, ["WeaponHolder", "GroundWeaponHolder", "WeaponHolderSimulated", "CAManBase"], 4]) select {
                _x isNotEqualTo _unit && {([[_x] call _read] call _find) >= 0}
            };
            if (_near isNotEqualTo []) then { _src = _near select 0; };
        };
        private _srcRecs = [_src] call _read;
        private _i = [_srcRecs] call _find;
        private _me = call _myKey;
        private _rec = [];
        if (_i >= 0) then {
            _rec = _srcRecs deleteAt _i;
            _src setVariable ["COMSPEC_ATAK_Phones", _srcRecs, true];
            _rec set [1, _cls];
            if ((_rec select 2) isNotEqualTo _me) then { ["adopt", [_rec select 0, _rec select 2, objNull]] call _srv; };
        } else {
            // Pris sur une unité sans fiche (IA, joueur tombé avant le premier relevé) : le téléphone reste le sien.
            if !(_from isKindOf "CAManBase") exitWith {};
            private _key = _from getVariable ["COMSPEC_ATAK_LinkUid", ""];
            if !(_key isEqualType "") then { _key = ""; };
            if (_key isEqualTo "") then { _key = getPlayerUID _from; };
            if (_key isEqualTo "") then { _key = "AI_" + netId _from; };
            _rec = [[_key] call _newId, _cls, _key, name _from];
            if (_key isNotEqualTo _me) then { ["adopt", [_rec select 0, _key, _from]] call _srv; };
        };
        if (_rec isEqualTo []) exitWith {};
        private _mine = [player] call _read;
        _mine pushBack _rec;
        [player, _mine] call _write;
    };
    case "put": {
        if (!_on) exitWith {};
        _arg params ["_unit", "_container", "_item"];
        if !([_item] call _isPhone) exitWith {};
        private _to = [_container] call _holderOf;
        if (_to isEqualTo _unit) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_PhoneSig", nil];
        private _cls = toLower _item;
        private _me = call _myKey;
        private _mine = [player] call _read;
        private _i = _mine findIf { (_x select 1) isEqualTo _cls && {(_x select 2) isNotEqualTo _me} };
        if (_i < 0) then { _i = _mine findIf { (_x select 1) isEqualTo _cls }; };
        if (_i < 0) exitWith {};
        private _rec = _mine deleteAt _i;
        [player, _mine] call _write;
        private _there = [_to] call _read;
        _there pushBack _rec;
        _to setVariable ["COMSPEC_ATAK_Phones", _there, true];
        if ((_rec select 2) isEqualTo _me) then { ["store", [player, [_rec select 0], ["snap"] call comspec_atak_native_fnc_phoneOwn]] call _srv; };
    };
    case "killed": {
        if (!_on) exitWith {};
        private _me = call _myKey;
        private _ids = (([_arg] call _read) select { (_x select 2) isEqualTo _me }) apply { _x select 0 };
        if (_ids isNotEqualTo []) then { ["store", [_arg, _ids, ["snap"] call comspec_atak_native_fnc_phoneOwn]] call _srv; };
    };
    // Le serveur demande le contenu d'un téléphone qu'on vient de me prendre vivant (fouille, capture).
    case "want": {
        _arg params [["_id", ""]];
        if !(_id isEqualType "") exitWith {};
        ["store", [player, [_id], ["snap"] call comspec_atak_native_fnc_phoneOwn]] call _srv;
    };
    // Contenu de mon téléphone : numéro, IMEI, messages et notes reçus par ce téléphone, sans jeton ni compte Athena.
    case "snap": {
        if (["wiped"] call comspec_atak_native_fnc_phoneSecurity) exitWith { [["wiped", true]] };
        private _str = { params ["_v"]; [str _v, _v] select (_v isEqualType "") };
        ([player] call comspec_atak_native_fnc_phoneIdent) params ["_num", "_imei"];
        private _msgs = (([""] call comspec_atak_native_fnc_messagesAll) select [0, 30]) apply {
            [[_x getOrDefault ["time", ""]] call _str, [_x getOrDefault ["author", ""]] call _str, ([_x getOrDefault ["body", ""]] call _str) select [0, 240]]
        };
        private _notes = profileNamespace getVariable ["COMSPEC_ATAK_Notes", []];
        if !(_notes isEqualType []) then { _notes = []; };
        _notes = ((_notes select { _x isEqualType [] && {(count _x) >= 3} }) select [0, 15]) apply { [([_x select 1] call _str) select [0, 60], ([_x select 2] call _str) select [0, 500]] };
        private _marks = [];
        {
            private _p = _y getOrDefault ["position", []];
            if (_p isEqualType [] && {(count _p) >= 2} && {(count _marks) < 15}) then { _marks pushBack [mapGridPosition _p, ([_y getOrDefault ["text", ""]] call _str) select [0, 60]]; };
        } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["markers", createHashMap]);
        [["wiped", false], ["number", _num], ["imei", _imei], ["callsign", [player, true] call comspec_atak_native_fnc_unitCallsign], ["msgs", _msgs], ["notes", _notes], ["marks", _marks],
            ["at", [dayTime, "HH:MM"] call BIS_fnc_timeToString]]
    };
    // À l'ouverture en main : que proposer ? Rien à choisir sans téléphone d'autrui.
    case "prepare": {
        uiNamespace setVariable ["COMSPEC_ATAK_PhoneNext", ""];
        if (!_on || {!alive player}) exitWith { "" };
        private _foreign = ["foreign"] call comspec_atak_native_fnc_phoneOwn;
        if (_foreign isEqualTo []) exitWith { "" };
        private _n = ["*PICK*", (_foreign select 0) select 0] select (!([player] call comspec_atak_native_fnc_hasDevice) && {(count _foreign) isEqualTo 1});
        uiNamespace setVariable ["COMSPEC_ATAK_PhoneNext", _n];
        _view set ["id", ""];
        _n
    };
    case "pick": {
        if (_arg isEqualTo "") exitWith {
            // Mon téléphone : le lanceur et l'écran de verrouillage habituels.
            missionNamespace setVariable ["COMSPEC_ATAK_PhoneActive", ""];
            ["close"] call comspec_atak_native_fnc_phonePad;
            ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
            [] call comspec_atak_native_fnc_dockRender;
            ["check"] call comspec_atak_native_fnc_lockScreen;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_PhoneActive", _arg];
        _view set ["id", ""];
        ["PHONES"] call comspec_atak_native_fnc_navigate;
    };
    case "try": {
        private _rec = ["activeRec"] call comspec_atak_native_fnc_phoneOwn;
        if (_rec isEqualTo []) exitWith {};
        // Premier appel de la page : empreinte vide, le serveur dit si un code est demandé.
        private _h = if (_arg isEqualTo "") then { "" } else { ["hash", [_rec select 2, _arg]] call comspec_atak_native_fnc_phoneOwn };
        _view set ["id", _rec select 0];
        if (_arg isEqualTo "") then { _view set ["status", "WAIT"]; };
        private _seq = (_view getOrDefault ["seq", 0]) + 1;
        _view set ["seq", _seq];
        ["unlock", [player, _rec select 0, _rec select 2, _h]] call _srv;
        // Serveur sans le mod ou sans réponse : on le dit au lieu d'attendre.
        [{
            params ["_s"];
            private _v = uiNamespace getVariable ["COMSPEC_ATAK_PhoneView", createHashMap];
            if ((_v getOrDefault ["seq", 0]) isEqualTo _s && {(_v getOrDefault ["status", ""]) isEqualTo "WAIT"}) then {
                _v set ["status", "NONE"];
                if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PHONES") then { ["PHONES"] call comspec_atak_native_fnc_pageRender; };
            };
        }, [_seq], 6] call comspec_atak_native_fnc_waitAndExec;
    };
    case "state": {
        _arg params [["_id", ""], ["_status", ""], ["_hint", ""], ["_left", 0], ["_wait", 0], ["_pairs", []]];
        if (_id isNotEqualTo (_view getOrDefault ["id", ""])) exitWith {};
        _view set ["seq", (_view getOrDefault ["seq", 0]) + 1];
        _view set ["hint", _hint];
        _view set ["left", _left];
        switch (_status) do {
            case "open": {
                _view set ["status", "OPEN"];
                _view set ["data", createHashMapFromArray _pairs];
                ["close"] call comspec_atak_native_fnc_phonePad;
                call _render;
            };
            case "bad": {
                _view set ["status", "LOCKED"];
                ["msg", format ["Code faux. %1 essai(s) restant(s).", _left]] call comspec_atak_native_fnc_phonePad;
            };
            case "blocked": {
                private _was = _view getOrDefault ["status", ""];
                _view set ["status", "LOCKED"];
                _view set ["blockMsg", format ["Téléphone bloqué pendant %1 s.", ceil _wait]];
                if (_was isEqualTo "WAIT") then { call _render; } else { ["count", [diag_tickTime + _wait, "Téléphone bloqué pendant %1 s."]] call comspec_atak_native_fnc_phonePad; };
            };
            default { _view set ["status", "LOCKED"]; _view set ["blockMsg", ""]; call _render; };
        };
    };
    // Effacer ce téléphone et le garder : il devient le mien, ses données sont perdues (une fois ouvert seulement).
    case "claim": {
        private _rec = ["activeRec"] call comspec_atak_native_fnc_phoneOwn;
        if (_rec isEqualTo [] || {(_view getOrDefault ["status", ""]) isNotEqualTo "OPEN"}) exitWith {};
        private _me = call _myKey;
        ["forget", [_rec select 0]] call _srv;
        private _mine = [player] call _read;
        {
            if ((_x select 0) isEqualTo (_rec select 0)) then { _x set [0, [_me] call _newId]; _x set [2, _me]; _x set [3, name player]; };
        } forEach _mine;
        [player, _mine] call _write;
        ["pick", ""] call comspec_atak_native_fnc_phoneOwn;
    };
    case "padForeign": {
        private _rec = ["activeRec"] call comspec_atak_native_fnc_phoneOwn;
        if (_rec isEqualTo []) exitWith {};
        private _hint = _view getOrDefault ["hint", ""];
        private _btns = [];
        if ((count (["foreign"] call comspec_atak_native_fnc_phoneOwn)) > 1 || {[player] call comspec_atak_native_fnc_hasDevice}) then {
            _btns pushBack ["AUTRE TÉLÉPHONE", { ["close"] call comspec_atak_native_fnc_phonePad; missionNamespace setVariable ["COMSPEC_ATAK_PhoneActive", "*PICK*"]; ["PHONES"] call comspec_atak_native_fnc_navigate; }];
        };
        _btns pushBack ["RANGER", { uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false]; [] call comspec_atak_native_fnc_close; }];
        // Admin ou Zeus (fn_adminAllowed, revérifié par le serveur) : remise à zéro du code de ce téléphone.
        if ([player] call comspec_atak_native_fnc_adminAllowed) then {
            _btns pushBack ["ADMIN : RETIRER LE CODE", compile format ["['server', ['phonePin', [%1]]] call comspec_atak_native_fnc_adminClient; [{ params ['_p']; ['try', _p] call comspec_atak_native_fnc_phoneOwn; }, [''], 1.5] call comspec_atak_native_fnc_waitAndExec;", str (_rec select 2)]];
        };
        private _left = _view getOrDefault ["left", 0];
        private _bm = _view getOrDefault ["blockMsg", ""];
        ["show", createHashMapFromArray [
            ["title", format ["Téléphone de %1", _rec select 3]],
            ["sub", if (_hint isEqualTo "") then { "Pas d'indice" } else { format ["Indice : %1", _hint] }],
            ["msg", if (_bm isNotEqualTo "") then { _bm } else { if (_left > 0) then { format ["%1 essai(s) restant(s).", _left] } else { "" } }],
            ["ok", { params ["_d"]; ["try", _d] call comspec_atak_native_fnc_phoneOwn; }],
            ["buttons", _btns]
        ]] call comspec_atak_native_fnc_phonePad;
    };

    // ---------- Mon téléphone ----------
    case "setPin": {
        _arg params [["_code", ""], ["_hint", ""]];
        if !(_code isEqualType "" && {_hint isEqualType ""}) exitWith { false };
        _code = trim _code;
        _hint = (trim _hint) select [0, 40];
        if (_code isNotEqualTo "" && {!([_code] call _digitsOk)}) exitWith {
            ["Le code fait 4 à 8 chiffres", 3] call comspec_atak_native_fnc_toast;
            false
        };
        profileNamespace setVariable ["COMSPEC_ATAK_LockCode", _code];
        profileNamespace setVariable ["COMSPEC_ATAK_LockHint", _hint];
        profileNamespace setVariable ["COMSPEC_ATAK_PinAsk", _code isNotEqualTo ""];
        [] call comspec_atak_native_fnc_saveProfile;
        missionNamespace setVariable ["COMSPEC_ATAK_PinTries", 0];
        missionNamespace setVariable ["COMSPEC_ATAK_PinForgotAt", -1];
        ["register"] call comspec_atak_native_fnc_phoneOwn;
        [["Code retiré", "Code enregistré"] select (_code isNotEqualTo ""), 2] call comspec_atak_native_fnc_toast;
        true
    };
    case "setHint": {
        if !(_arg isEqualType "") exitWith { false };
        private _hint = (trim _arg) select [0, 40];
        profileNamespace setVariable ["COMSPEC_ATAK_LockHint", _hint];
        [] call comspec_atak_native_fnc_saveProfile;
        ["register"] call comspec_atak_native_fnc_phoneOwn;
        ["Indice enregistré", 2] call comspec_atak_native_fnc_toast;
        true
    };
    case "hasPin": { [profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""]] call _digitsOk };
    // Réglages : le code se choisit au clavier, sans jamais s'afficher ; le code actuel d'abord s'il y en a un.
    case "pinSetup": {
        uiNamespace setVariable ["COMSPEC_ATAK_PinFlow", [_arg, ""]];
        ["pinStep", [["new", "verify"] select (["hasPin"] call comspec_atak_native_fnc_phoneOwn), ""]] call comspec_atak_native_fnc_phoneOwn;
    };
    case "pinStep": {
        _arg params [["_step", ""], ["_dg", ""]];
        (uiNamespace getVariable ["COMSPEC_ATAK_PinFlow", ["set", ""]]) params ["_mode", "_first"];
        private _show = {
            params ["_st", "_title", "_sub", ["_msg", ""]];
            ["show", createHashMapFromArray [
                ["title", _title], ["sub", _sub], ["msg", _msg], ["setup", true],
                ["ok", compile format ["params ['_d']; ['pinStep', ['%1', _d]] call comspec_atak_native_fnc_phoneOwn;", _st]],
                ["buttons", [["ANNULER", { ["close"] call comspec_atak_native_fnc_phonePad; }]]]
            ]] call comspec_atak_native_fnc_phonePad;
        };
        private _newPad = { params [["_msg", ""]]; ["new", "Nouveau code", "4 à 8 chiffres", _msg] call _show; };
        private _done = {
            ["close"] call comspec_atak_native_fnc_phonePad;
            [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        };
        switch (_step) do {
            case "verify": {
                if (_dg isEqualTo "") exitWith { ["verify", "Code actuel", ["Pour le changer", "Pour le retirer"] select (_mode isEqualTo "remove")] call _show; };
                private _until = missionNamespace getVariable ["COMSPEC_ATAK_PinBlock", -1];
                if (diag_tickTime < _until) exitWith { ["count", [_until, "Bloqué pendant %1 s."]] call comspec_atak_native_fnc_phonePad; };
                if (_dg isNotEqualTo (profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""])) exitWith { ["fail"] call comspec_atak_native_fnc_phoneOwn; };
                missionNamespace setVariable ["COMSPEC_ATAK_PinTries", 0];
                if (_mode isEqualTo "remove") exitWith {
                    ["setPin", ["", ""]] call comspec_atak_native_fnc_phoneOwn;
                    call _done;
                };
                [""] call _newPad;
            };
            case "new": {
                if (_dg isEqualTo "") exitWith { [""] call _newPad; };
                if !([_dg] call _digitsOk) exitWith { ["msg", "Le code fait 4 à 8 chiffres"] call comspec_atak_native_fnc_phonePad; };
                uiNamespace setVariable ["COMSPEC_ATAK_PinFlow", [_mode, _dg]];
                ["confirm", "Confirmez le code", "Saisissez-le une seconde fois"] call _show;
            };
            case "confirm": {
                if (_dg isNotEqualTo _first) exitWith { ["Les deux codes diffèrent"] call _newPad; };
                private _hint = profileNamespace getVariable ["COMSPEC_ATAK_LockHint", ""];
                if !(_hint isEqualType "") then { _hint = ""; };
                ["setPin", [_dg, _hint]] call comspec_atak_native_fnc_phoneOwn;
                call _done;
            };
        };
    };
    // Code faux sur mon téléphone : essais comptés, puis blocage avec décompte à l'écran.
    case "fail": {
        private _n = (missionNamespace getVariable ["COMSPEC_ATAK_PinTries", 0]) + 1;
        private _max = (missionNamespace getVariable ["comspec_atak_native_pin_attempts", 5]) max 1;
        if (_n >= _max) then {
            private _b = (missionNamespace getVariable ["comspec_atak_native_pin_block", 120]) max 10;
            missionNamespace setVariable ["COMSPEC_ATAK_PinTries", 0];
            missionNamespace setVariable ["COMSPEC_ATAK_PinBlock", diag_tickTime + _b];
            ["count", [diag_tickTime + _b, "Bloqué pendant %1 s."]] call comspec_atak_native_fnc_phonePad;
        } else {
            missionNamespace setVariable ["COMSPEC_ATAK_PinTries", _n];
            ["msg", format ["Code faux. %1 essai(s) restant(s).", _max - _n]] call comspec_atak_native_fnc_phonePad;
        };
    };
    case "needPin": {
        (profileNamespace getVariable ["COMSPEC_ATAK_PinAsk", false]) isEqualTo true
            && {[profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""]] call _digitsOk}
            && {!(missionNamespace getVariable ["COMSPEC_ATAK_PinOk", false])}
    };
    case "ownPad": {
        private _hint = profileNamespace getVariable ["COMSPEC_ATAK_LockHint", ""];
        if !(_hint isEqualType "") then { _hint = ""; };
        private _fa = missionNamespace getVariable ["COMSPEC_ATAK_PinForgotAt", -1];
        ["show", createHashMapFromArray [
            ["title", "Code du téléphone"],
            ["sub", if (_hint isEqualTo "") then { "" } else { format ["Indice : %1", _hint] }],
            ["msg", ""],
            ["ok", { params ["_d"]; ["ownTry", _d] call comspec_atak_native_fnc_phoneOwn; }],
            ["buttons", [
                ["CODE OUBLIÉ", { ["forget"] call comspec_atak_native_fnc_phoneOwn; }],
                ["RETOUR", { uiNamespace setVariable ["COMSPEC_ATAK_LockAfter", ""]; ["close"] call comspec_atak_native_fnc_phonePad; }]
            ]]
        ]] call comspec_atak_native_fnc_phonePad;
        // Blocage ou code oublié en cours (mission) : le décompte reprend à la réouverture.
        private _bl = missionNamespace getVariable ["COMSPEC_ATAK_PinBlock", -1];
        if (diag_tickTime < _bl) then { ["count", [_bl, "Bloqué pendant %1 s."]] call comspec_atak_native_fnc_phonePad; } else {
            if (_fa >= 0) then { ["count", [_fa, "Code retiré dans %1 s."]] call comspec_atak_native_fnc_phonePad; };
        };
    };
    case "ownTry": {
        private _until = missionNamespace getVariable ["COMSPEC_ATAK_PinBlock", -1];
        if (diag_tickTime < _until) exitWith { ["count", [_until, "Bloqué pendant %1 s."]] call comspec_atak_native_fnc_phonePad; };
        if (_arg isEqualTo (profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""])) exitWith {
            missionNamespace setVariable ["COMSPEC_ATAK_PinOk", true];
            missionNamespace setVariable ["COMSPEC_ATAK_PinTries", 0];
            missionNamespace setVariable ["COMSPEC_ATAK_PinForgotAt", -1];
            ["close"] call comspec_atak_native_fnc_phonePad;
            ["unlock"] call comspec_atak_native_fnc_lockScreen;
        };
        ["fail"] call comspec_atak_native_fnc_phoneOwn;
    };
    // Le propriétaire ne reste jamais dehors : sans admin ni Zeus, le code tombe après un délai.
    case "forget": {
        if ((missionNamespace getVariable ["COMSPEC_ATAK_PinForgotAt", -1]) < 0) then { missionNamespace setVariable ["COMSPEC_ATAK_PinForgotAt", diag_tickTime + 300]; };
        ["count", [missionNamespace getVariable ["COMSPEC_ATAK_PinForgotAt", 0], "Code retiré dans %1 s. Un admin ou un Zeus peut le retirer tout de suite."]] call comspec_atak_native_fnc_phonePad;
    };
    // Code retiré : délai du « code oublié », ou admin et Zeus (fn_phoneServer « reset »).
    case "cleared": {
        missionNamespace setVariable ["COMSPEC_ATAK_PinForgotAt", -1];
        missionNamespace setVariable ["COMSPEC_ATAK_PinTries", 0];
        missionNamespace setVariable ["COMSPEC_ATAK_PinBlock", -1];
        profileNamespace setVariable ["COMSPEC_ATAK_LockCode", ""];
        profileNamespace setVariable ["COMSPEC_ATAK_PinAsk", false];
        [] call comspec_atak_native_fnc_saveProfile;
        ["register"] call comspec_atak_native_fnc_phoneOwn;
        ["INFO", "Le code de votre téléphone est retiré.", 6, 40] call comspec_atak_native_fnc_notify;
        if ((["up"] call comspec_atak_native_fnc_phonePad) && {(["active"] call comspec_atak_native_fnc_phoneOwn) isEqualTo ""}) then {
            ["close"] call comspec_atak_native_fnc_phonePad;
            ["unlock"] call comspec_atak_native_fnc_lockScreen;
        };
    };
    default { false };
};
