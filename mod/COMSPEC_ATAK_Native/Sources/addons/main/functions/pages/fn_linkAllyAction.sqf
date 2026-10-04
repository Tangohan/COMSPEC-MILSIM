/*
    App Liaison ATAK : relier mon téléphone à celui d'un équipier (tué, inconscient, ou dont l'ATAK est détruit / vide).
    Réalisme : à 2 m au plus du corps (ou du téléphone tombé au sol), l'équipier doit porter un téléphone ;
    lien NFC de quelques secondes (câble USB-C, plus long, si son appareil est éteint ou détruit).
    Params : [action, argument]
      Mon téléphone : "candidates", "link", "linkAce", "linked", "reply", "useMirror", "unlink", "refresh",
                      "build" (bilan médical / décès), "deathReport", "injuryReport", "recover", "flushed"
      Téléphone de l'équipier (sa machine) : "snapshot", "query", "flush", "mirror", "killed"
    État local : uiNamespace COMSPEC_ATAK_LinkAlly (cible, phase, données lues, envois faits).
*/
params [["_action", "candidates"], ["_arg", objNull]];
private _st = uiNamespace getVariable ["COMSPEC_ATAK_LinkAlly", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_LinkAlly", _st];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "LINKALLY") then { ["LINKALLY"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _phoneItems = {
    params ["_o"];
    private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
    private _all = if (_o isKindOf "CAManBase") then { (assignedItems _o) + (items _o) } else { (itemCargo _o) + (weaponCargo _o) };
    (_all findIf { (toLower _x) in _cat }) >= 0
};
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _me = [player, true] call comspec_atak_native_fnc_unitCallsign;
if (_me isEqualTo "") then { _me = name player; };

switch (_action) do {
    // Équipiers ou téléphones à portée de lien : [[objet relié, unité propriétaire, distance, étiquette]...]
    case "candidates": {
        private _out = [];
        {
            private _u = _x;
            if (_u isNotEqualTo player && {[_u] call comspec_atak_native_fnc_hasDevice}) then {
                private _down = !alive _u || {lifeState _u isEqualTo "INCAPACITATED"} || {_u getVariable ["ACE_isUnconscious", false]};
                private _friend = alive _u && {(side group _u) isEqualTo (side group player)};
                if (_down || _friend) then { _out pushBack [_u, _u, player distance _u, "corps"]; };
            };
        } forEach (nearestObjects [player, ["CAManBase"], 2.2]);
        // Téléphone posé ou tombé : son propriétaire est connu (événement Put, fn_linkAllyInit).
        {
            private _o = _x getVariable ["COMSPEC_ATAK_PhoneOwner", objNull];
            if (!isNull _o && {_o isNotEqualTo player} && {[_x] call _phoneItems} && {(_out findIf { (_x select 1) isEqualTo _o }) < 0}) then {
                _out pushBack [_x, _o, player distance _x, "téléphone au sol"];
            };
        } forEach (nearestObjects [player, ["WeaponHolder", "GroundWeaponHolder", "WeaponHolderSimulated"], 2.2]);
        _out
    };

    // Lien lancé depuis l'app : barre de progression dans le téléphone (tick de fn_linkAllyInit).
    case "link";
    case "linkAce": {
        private _c = ["candidates"] call comspec_atak_native_fnc_linkAllyAction;
        private _i = _c findIf { (_x select 0) isEqualTo _arg || {(_x select 1) isEqualTo _arg} };
        if (_i < 0) exitWith { ["WARNING", "Liaison impossible : approchez-vous à moins de 2 m (l'équipier doit porter un téléphone).", 4, 30] call comspec_atak_native_fnc_notify; false };
        (_c select _i) params ["_obj", "_owner"];
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { ["WARNING", "Il vous faut votre propre téléphone pour vous relier.", 4, 30] call comspec_atak_native_fnc_notify; false };
        // Appareil de l'équipier éteint, vide ou détruit : lecture de la mémoire par câble, plus lente.
        private _pub = _owner getVariable ["COMSPEC_ATAK_Pub", []];
        private _dead = ((_pub param [2, "OK"]) in ["OFF", "BROKEN"]) || {(_pub param [0, 100]) <= 0};
        private _mode = ["NFC", "CÂBLE"] select _dead;
        private _dur = [4, 8] select _dead;
        _st set ["target", _owner];
        _st set ["holder", _obj];
        _st set ["mode", _mode];
        _st set ["data", createHashMap];
        _st set ["src", ""];
        _st set ["sent", createHashMap];
        if (_action isEqualTo "linkAce" && {!isNil "ace_common_fnc_progressBar"}) exitWith {
            _st set ["phase", "ACE"];
            [_dur, [_obj], { ["linked", (_this select 0) select 0] call comspec_atak_native_fnc_linkAllyAction; },
                { (uiNamespace getVariable ["COMSPEC_ATAK_LinkAlly", createHashMap]) set ["phase", ""]; ["WARNING", "Liaison ATAK interrompue", 3, 20] call comspec_atak_native_fnc_notify; },
                format ["Liaison ATAK (%1)…", _mode], { (player distance ((_this select 0) select 0)) < 2.6 }] call ace_common_fnc_progressBar;
            true
        };
        _st set ["phase", "LINKING"];
        _st set ["start", diag_tickTime];
        _st set ["dur", _dur];
        call _render;
        true
    };

    // Lien établi : on lit l'appareil. Joueur encore connecté (même mort) : lecture directe de sa mémoire ;
    // sinon, dernière copie de sauvegarde publiée sur son unité (toutes les 30 s).
    case "linked": {
        private _owner = _st getOrDefault ["target", objNull];
        if (isNull _owner) exitWith { false };
        _st set ["phase", "LINKED"];
        _st set ["linkedAt", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
        private _uid = _owner getVariable ["COMSPEC_ATAK_LinkUid", ""];
        if (_uid isEqualTo "") then { _uid = getPlayerUID _owner; };
        private _li = if (_uid isEqualTo "") then { -1 } else { allPlayers findIf { getPlayerUID _x isEqualTo _uid } };
        private _live = [objNull, allPlayers select (_li max 0)] select (_li >= 0);
        if (isNull _live) exitWith { ["useMirror"] call comspec_atak_native_fnc_linkAllyAction; };
        private _req = format ["%1_%2", getPlayerUID player, round (diag_tickTime * 100)];
        _st set ["req", _req];
        _st set ["live", _live];
        _st set ["src", "WAIT"];
        ["comspec_atak_native_linkAllyQuery", [player, _req], _live] call CBA_fnc_targetEvent;
        // Pas de réponse en 5 s (machine figée, déconnexion en cours) : copie de sauvegarde.
        [{ params ["_req"]; private _s = uiNamespace getVariable ["COMSPEC_ATAK_LinkAlly", createHashMap]; if ((_s getOrDefault ["req", ""]) isEqualTo _req && {(_s getOrDefault ["src", ""]) isEqualTo "WAIT"}) then { ["useMirror"] call comspec_atak_native_fnc_linkAllyAction; }; }, [_req], 5] call CBA_fnc_waitAndExecute;
        ["SUCCESS", format ["Liaison %1 établie avec l'ATAK de %2", _st getOrDefault ["mode", "NFC"], name _owner], 3, 30] call comspec_atak_native_fnc_notify;
        if (!isNull ([] call comspec_atak_native_fnc_display)) then { ["LINKALLY"] call comspec_atak_native_fnc_navigate; };
        call _render;
        true
    };
    case "reply": {
        _arg params [["_req", ""], ["_pairs", []]];
        if (_req isNotEqualTo (_st getOrDefault ["req", "-"])) exitWith { false };
        _st set ["data", createHashMapFromArray _pairs];
        _st set ["src", "LIVE"];
        call _render;
        true
    };
    case "useMirror": {
        private _owner = _st getOrDefault ["target", objNull];
        private _m = _owner getVariable ["COMSPEC_ATAK_LinkMirror", []];
        _st set ["data", createHashMapFromArray (_m param [0, []])];
        _st set ["src", ["NONE", "MIRROR"] select ((count _m) > 0)];
        _st set ["mirrorAt", _m param [1, ""]];
        call _render;
        true
    };
    case "refresh": {
        if ((_st getOrDefault ["phase", ""]) isEqualTo "LINKED") then { ["linked"] call comspec_atak_native_fnc_linkAllyAction; } else { call _render; };
    };
    case "unlink": {
        { _st deleteAt _x; } forEach ["target", "holder", "phase", "data", "src", "req", "live", "sent", "start", "dur"];
        call _render;
        true
    };

    // ---------- Machine de l'équipier ----------
    // Ce que contient mon téléphone : identité Athena, plaque, file d'envoi, photos refusées, brouillons.
    case "snapshot": {
        private _small = _arg isEqualTo true;
        private _tag = if (isNil "ace_dogtags_fnc_getDogtagData") then { [] } else { [player] call ace_dogtags_fnc_getDogtagData };
        if !(_tag isEqualType []) then { _tag = []; };
        private _blood = if (isNil "comspec_overwatch_connect_fnc_getBloodType") then { _tag param [2, ""] } else { [] call comspec_overwatch_connect_fnc_getBloodType };
        private _str = { params ["_v"]; if (_v isEqualType "") then { _v } else { "" } };
        private _queue = (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []]) apply { [_x param [2, "Envoi"], _x param [3, 1]] };
        private _failed = ((missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []]) select { _x isEqualType "" }) apply { private _p = _x splitString "\/"; _p select ((count _p) - 1) };
        private _pending = count (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]);
        private _frs = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
        private _reco = uiNamespace getVariable ["COMSPEC_ATAK_RecoDraft", createHashMap];
        private _cut = [2000, 160] select _small;
        private _frsBody = [_frs getOrDefault ["body", ""]] call _str;
        private _recoText = [_reco getOrDefault ["text", ""]] call _str;
        if (_small) then { _queue = _queue select [0, 8]; _failed = _failed select [0, 5]; };
        [
            ["name", [missionNamespace getVariable ["comspec_profile_name", ""]] call _str],
            ["grade", [missionNamespace getVariable ["comspec_profile_grade", ""]] call _str],
            ["unit", [missionNamespace getVariable ["comspec_profile_unit", ""]] call _str],
            ["role", [missionNamespace getVariable ["comspec_profile_role", ""]] call _str],
            ["function", [missionNamespace getVariable ["comspec_profile_function", ""]] call _str],
            ["callsign", [player, true] call comspec_atak_native_fnc_unitCallsign],
            ["blood", [_blood] call _str],
            ["matricule", [_tag param [1, ""]] call _str],
            ["tagName", [_tag param [0, ""]] call _str],
            ["queue", _queue],
            ["photosFailed", _failed],
            ["photosPending", _pending],
            ["frs", [[_frs getOrDefault ["kind", ""]] call _str, [_frs getOrDefault ["place", ""]] call _str, [_frs getOrDefault ["grid", ""]] call _str, _frsBody select [0, _cut]]],
            ["reco", [[_reco getOrDefault ["tag", ""]] call _str, [_reco getOrDefault ["grid", ""]] call _str, _recoText select [0, _cut]]],
            ["battery", round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100])],
            ["readAt", [dayTime, "HH:MM"] call BIS_fnc_timeToString]
        ]
    };
    case "query": {
        _arg params [["_from", objNull], ["_req", ""]];
        if (isNull _from) exitWith { false };
        ["comspec_atak_native_linkAllyReply", [_req, ["snapshot", false] call comspec_atak_native_fnc_linkAllyAction], _from] call CBA_fnc_targetEvent;
        ["INFO", format ["%1 lit votre ATAK (liaison de proximité)", name _from], 5, 30] call comspec_atak_native_fnc_notify;
        true
    };
    // Copie de sauvegarde légère publiée sur mon unité (lue si je me déconnecte) : seulement quand elle change.
    case "mirror": {
        if (isNull player) exitWith { false };
        if ((player getVariable ["COMSPEC_ATAK_LinkUid", ""]) isNotEqualTo getPlayerUID player) then { player setVariable ["COMSPEC_ATAK_LinkUid", getPlayerUID player, true]; };
        private _snap = ["snapshot", true] call comspec_atak_native_fnc_linkAllyAction;
        private _sig = _snap select { (_x select 0) isNotEqualTo "readAt" };
        if (_sig isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_LinkMirrorSig", []])) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_LinkMirrorSig", _sig];
        player setVariable ["COMSPEC_ATAK_LinkMirror", [_snap, [dayTime, "HH:MM"] call BIS_fnc_timeToString], true];
        true
    };
    // Mes envois en souffrance, repartis par l'ATAK d'un équipier : exécutés ici (le code ne voyage pas sur le réseau).
    case "flush": {
        _arg params [["_by", "un équipier"], ["_from", objNull]];
        private _queue = missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []];
        missionNamespace setVariable ["COMSPEC_ATAK_NetQueue", []];
        private _n = 0;
        { _x params ["_code", ["_args", []]]; if (_code isEqualType {}) then { _args call _code; _n = _n + 1; }; } forEach _queue;
        // Photos refusées par Athena : nouvelle tentative avec la mention de la récupération.
        private _photos = 0;
        if ([] call comspec_atak_native_fnc_bridge && {!isNil "comspec_overwatch_connect_fnc_captureReconImage"}) then {
            private _failed = (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []]) select { _x isEqualType "" };
            missionNamespace setVariable ["COMSPEC_Athena_PhotoFailed", []];
            {
                private _p = _x splitString "\/";
                [_x, format ["%1 · récupérée sur l'ATAK de %2 par %3 · %4", [player, true] call comspec_atak_native_fnc_unitCallsign, name player, _by, _p select ((count _p) - 1)], "CTAB", "", true, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
                _photos = _photos + 1;
            } forEach (_failed select [0, 20]);
        };
        missionNamespace setVariable ["COMSPEC_ATAK_LinkMirrorSig", []];
        ["mirror"] call comspec_atak_native_fnc_linkAllyAction;
        ["INFO", format ["%1 a renvoyé vos données non transmises (%2 envoi(s), %3 photo(s))", _by, _n, _photos], 6, 40] call comspec_atak_native_fnc_notify;
        if (!isNull _from) then { ["comspec_atak_native_linkAllyFlushed", [_n, _photos], _from] call CBA_fnc_targetEvent; };
        true
    };
    case "flushed": {
        _arg params [["_n", 0], ["_photos", 0]];
        ["SUCCESS", format ["Données récupérées renvoyées : %1 envoi(s), %2 photo(s)", _n, _photos], 5, 40] call comspec_atak_native_fnc_notify;
        (_st getOrDefault ["sent", createHashMap]) set ["queueDone", true];
        call _render;
        true
    };
    // Heure et circonstances de la mort, vues par chaque machine (EntityKilled, fn_linkAllyInit).
    case "killed": {
        _arg params ["_unit", "_killer", "_inst"];
        if (!(_unit isKindOf "CAManBase")) exitWith {};
        private _src = [_inst, _killer] select (isNull _inst);
        private _desc = "";
        if (!isNull _src && {_src isNotEqualTo _unit}) then {
            private _veh = vehicle _src;
            private _w = if (_veh isNotEqualTo _src) then { getText (configOf _veh >> "displayName") } else { getText (configFile >> "CfgWeapons" >> currentWeapon _src >> "displayName") };
            _desc = format ["%1%2 à ~%3 m", ["tir", "tir ami"] select ((side group _src) isEqualTo (side group _unit)), ["", format [" (%1)", _w]] select (_w isNotEqualTo ""), round (_src distance _unit)];
        };
        _unit setVariable ["COMSPEC_ATAK_KillInfo", [[dayTime, "HH:MM"] call BIS_fnc_timeToString, _desc]];
    };

    // ---------- Rapports vers le poste ----------
    // Bilan lu sur l'équipier relié : [KIA|WIA, résumé, détails, champs EAGLE_DOWN, position ASL, lignes à afficher [libellé, valeur]].
    case "build": {
        private _u = _st getOrDefault ["target", objNull];
        if (isNull _u) exitWith { [] };
        private _data = _st getOrDefault ["data", createHashMap];
        private _name = _data getOrDefault ["name", ""];
        if (_name isEqualTo "") then { _name = _data getOrDefault ["tagName", ""]; };
        if (_name isEqualTo "") then { _name = name _u; };
        private _cs = _data getOrDefault ["callsign", ""];
        if (_cs isEqualTo "") then { _cs = [_u, true] call comspec_atak_native_fnc_unitCallsign; };
        private _who = [format ["%1 (%2)", _name, _cs], _name] select (_cs in ["", _name]);
        private _pos = getPosASL _u;
        private _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef;
        private _now = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        // Blessures ACE : HashMap partie → [[classe, nombre, saignement, dégâts]...] (ACE 3.16+) ou ancien tableau.
        private _partsFr = createHashMapFromArray [["head", "tête"], ["body", "torse"], ["leftarm", "bras gauche"], ["rightarm", "bras droit"], ["leftleg", "jambe gauche"], ["rightleg", "jambe droite"]];
        private _partKeys = ["head", "body", "leftarm", "rightarm", "leftleg", "rightleg"];
        private _classFr = createHashMapFromArray [["abrasion", "éraflure"], ["avulsion", "arrachement"], ["contusion", "contusion"], ["crush", "écrasement"], ["cut", "coupure"], ["laceration", "lacération"], ["velocitywound", "plaie par balle"], ["puncturewound", "perforation"]];
        private _classNames = missionNamespace getVariable ["ace_medical_damage_woundClassNames", []];
        private _byPart = createHashMap;
        private _byType = createHashMap;
        private _addWound = {
            params ["_part", "_class", "_n"];
            if (_n <= 0) exitWith {};
            private _cn = toLower (_classNames param [floor (_class / 10), ""]);
            private _t = _classFr getOrDefault [_cn, "plaie"];
            _byPart set [_part, (_byPart getOrDefault [_part, 0]) + _n];
            _byType set [_t, (_byType getOrDefault [_t, 0]) + _n];
        };
        private _ow = _u getVariable ["ace_medical_openWounds", []];
        if (_ow isEqualType createHashMap) then {
            { private _k = _x; { _x params [["_c", 0], ["_n", 1]]; [_k, _c, ceil _n] call _addWound; } forEach _y; } forEach _ow;
        } else {
            if (_ow isEqualType []) then { { _x params [["_c", 0], ["_bp", 0], ["_n", 1]]; [_partKeys param [_bp, "body"], _c * 10, ceil _n] call _addWound; } forEach _ow; };
        };
        private _wounds = ((keys _byPart) apply { format ["%1 ×%2", _partsFr getOrDefault [_x, _x], _byPart get _x] }) joinString ", ";
        private _types = ((keys _byType) apply { format ["%1 ×%2", _x, _byType get _x] }) joinString ", ";
        private _tq = _u getVariable ["ace_medical_tourniquets", [0, 0, 0, 0, 0, 0]];
        private _tqTxt = ((_partKeys select { (_tq param [_partKeys find _x, 0]) isNotEqualTo 0 }) apply { _partsFr get _x }) joinString ", ";
        private _fr = _u getVariable ["ace_medical_fractures", [0, 0, 0, 0, 0, 0]];
        private _frTxt = ((_partKeys select { (_fr param [_partKeys find _x, 0]) isEqualTo 1 }) apply { _partsFr get _x }) joinString ", ";
        private _bleed = _u getVariable ["ace_medical_woundBleeding", 0];
        private _bv = _u getVariable ["ace_medical_bloodVolume", 6];
        private _hr = _u getVariable ["ace_medical_heartRate", 80];
        private _arrest = _u getVariable ["ace_medical_inCardiacArrest", false];
        private _uncon = lifeState _u isEqualTo "INCAPACITATED" || {_u getVariable ["ACE_isUnconscious", false]};
        private _ace = isClass (configFile >> "CfgPatches" >> "ace_medical");
        if (!alive _u) exitWith {
            (_u getVariable ["COMSPEC_ATAK_KillInfo", []]) params [["_tod", ""], ["_kill", ""]];
            if (_tod isEqualTo "") then { _tod = format ["avant %1 (constaté)", _now]; };
            private _causeRaw = _u getVariable ["ace_medical_causeOfDeath", ""];
            if !(_causeRaw isEqualType "") then { _causeRaw = str _causeRaw; };
            private _cl = toLower _causeRaw;
            private _cause = switch (true) do {
                case ((_cl find "cardiac") >= 0): { "arrêt cardiaque prolongé" };
                case ((_cl find "blood") >= 0 || {(_cl find "bleed") >= 0}): { "hémorragie" };
                case ((_cl find "fatal") >= 0): { "blessure mortelle" };
                default { "" };
            };
            private _parts = [_cause, _kill, ["", format ["blessures : %1", _types]] select (_types isNotEqualTo "")] select { _x isNotEqualTo "" };
            private _causeTxt = [(_parts joinString " · "), "indéterminée"] select ((count _parts) isEqualTo 0);
            private _sum = format ["KIA — %1 · %2", _who, _grid];
            private _det = format ["Décès de %1 constaté par %2 (liaison ATAK). Heure : %3. Grille : %4. Cause probable : %5.%6", _who, _me, _tod, _grid, _causeTxt, ["", format [" Localisation des blessures : %1.", _wounds]] select (_wounds isNotEqualTo "")];
            ["KIA", _sum, _det, [["casualty", _who], ["status", "KIA"], ["mechanism", _causeTxt], ["callsign", _me], ["grid", _grid], ["dtg", _tod], ["remarks", format ["Témoin : %1", _me]]], _pos,
                [["Défunt", _who], ["Heure du décès", _tod], ["Grille", _grid], ["Cause probable", _causeTxt], ["Blessures", [_wounds, "aucune relevée"] select (_wounds isEqualTo "")], ["Témoin", format ["%1 (moi)", _me]]]]
        };
        private _consc = switch (true) do { case (_arrest): { "arrêt cardiaque" }; case (_uncon): { "inconscient" }; default { "conscient" }; };
        private _bleedTxt = if (!_ace) then { "non mesurable" } else { switch (true) do { case (_bleed <= 0): { "aucune" }; case (_bleed < 0.02): { "légère" }; case (_bleed < 0.08): { "importante" }; default { "massive" }; } };
        private _sum = format ["BLESSÉ — %1 · %2 · %3", _who, _consc, _grid];
        private _det = format ["Blessé : %1, relevé par %2 (liaison ATAK) à %3, grille %4. État : %5. Hémorragie : %6. Garrots : %7. Fractures : %8. Pouls : %9. Volume sanguin : %10 %%. Blessures : %11.",
            _who, _me, _now, _grid, _consc, _bleedTxt, [_tqTxt, "aucun"] select (_tqTxt isEqualTo ""), [_frTxt, "aucune"] select (_frTxt isEqualTo ""),
            [format ["%1/min", round _hr], "non mesurable"] select !_ace, round ((_bv / 6) * 100), [format ["%1 (%2)", _wounds, _types], "aucune relevée"] select (_wounds isEqualTo "")];
        ["WIA", _sum, _det, [["casualty", _who], ["status", toUpper _consc], ["mechanism", [_types, "inconnu"] select (_types isEqualTo "")], ["callsign", _me], ["grid", _grid], ["treatment", ["", format ["garrots : %1", _tqTxt]] select (_tqTxt isNotEqualTo "")], ["remarks", _det]], _pos,
            [["Conscience", _consc], ["Hémorragie", _bleedTxt], ["Garrots", [_tqTxt, "aucun"] select (_tqTxt isEqualTo "")], ["Fractures", [_frTxt, "aucune"] select (_frTxt isEqualTo "")],
             ["Pouls", [format ["%1/min", round _hr], "non mesurable"] select !_ace], ["Volume sanguin", format ["%1 %%", round ((_bv / 6) * 100)]], ["Blessures", [_wounds, "aucune relevée"] select (_wounds isEqualTo "")], ["Grille", _grid]]]
    };
    case "deathReport";
    case "injuryReport": {
        private _owner = _st getOrDefault ["target", objNull];
        if (isNull _owner || {(_st getOrDefault ["phase", ""]) isNotEqualTo "LINKED"}) exitWith { false };
        private _r = ["build"] call comspec_atak_native_fnc_linkAllyAction;
        if ((count _r) isEqualTo 0) exitWith { false };
        _r params ["_kind", "_summary", "_details", "_fields", "_pos"];
        private _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef;
        // Camp : alerte par le réseau simulé (marche sans Athena) ; poste : rapport structuré + alerte « opérateur à terre ».
        [{
            params ["_kind", "_summary", "_details", "_fields", "_pos", "_grid", "_me"];
            ["comspec_atak_native_alert", [_kind, _me, _pos, _grid, _summary, str side group player]] call CBA_fnc_globalEvent;
            if ([] call comspec_atak_native_fnc_bridge) then {
                [{
                    params ["_kind", "_summary", "_details", "_fields", "_pos"];
                    if (!isNil "comspec_overwatch_connect_fnc_submitTacticalReport") then {
                        ["EAGLE_DOWN", ["PRIORITY", "ROUTINE"] select (_kind isEqualTo "KIA"), _summary, _details, createHashMapFromArray _fields, ASLToATL _pos] call comspec_overwatch_connect_fnc_submitTacticalReport;
                    };
                    if (!isNil "comspec_overwatch_connect_fnc_sendTacticalAlert") then { ["EAGLE_DOWN", _summary, ASLToATL _pos] call comspec_overwatch_connect_fnc_sendTacticalAlert; };
                }, [_kind, _summary, _details, _fields, _pos]] call CBA_fnc_execNextFrame;
            };
        }, [_kind, _summary, _details, _fields, _pos, _grid, _me], ["Rapport de blessure", "Rapport de décès"] select (_kind isEqualTo "KIA"), 2] call comspec_atak_native_fnc_netSend;
        [_kind, "Moi", _pos, _grid, _summary] call comspec_atak_native_fnc_alertsLog;
        (_st getOrDefault ["sent", createHashMap]) set [_action, [dayTime, "HH:MM"] call BIS_fnc_timeToString];
        ["SUCCESS", format ["%1 envoyé %2", ["Rapport de blessure", "Rapport de décès"] select (_kind isEqualTo "KIA"), ["au camp", "au camp et au poste"] select _bridge], 4, 30] call comspec_atak_native_fnc_notify;
        call _render;
        true
    };
    // Données non transmises : la file de son téléphone repart par le mien ; brouillons envoyés comme rapports.
    case "recover": {
        private _owner = _st getOrDefault ["target", objNull];
        private _data = _st getOrDefault ["data", createHashMap];
        if (isNull _owner || {(_st getOrDefault ["phase", ""]) isNotEqualTo "LINKED"}) exitWith { false };
        private _who = [_data getOrDefault ["name", ""], name _owner] select ((_data getOrDefault ["name", ""]) isEqualTo "");
        private _credit = format ["récupéré sur l'ATAK de %1 par %2", _who, _me];
        private _queue = _data getOrDefault ["queue", []];
        private _failed = _data getOrDefault ["photosFailed", []];
        private _live = _st getOrDefault ["live", objNull];
        private _done = 0;
        if (((count _queue) + (count _failed)) > 0) then {
            private _kb = 0;
            { _kb = _kb + (_x param [1, 1]); } forEach _queue;
            _kb = _kb + 600 * (count _failed);
            if ((_st getOrDefault ["src", ""]) isEqualTo "LIVE" && {!isNull _live}) then {
                // Transfert par mon réseau, puis exécution chez lui (son téléphone n'a plus de lien propre).
                [{ params ["_me", "_live"]; ["comspec_atak_native_linkAllyFlush", [_me, player], _live] call CBA_fnc_targetEvent; }, [_me, _live], format ["Données de %1", _who], _kb max 1] call comspec_atak_native_fnc_netSend;
            } else {
                // Joueur déconnecté : seule la liste est connue ; le poste est prévenu de ce qui est perdu.
                if (_bridge && {!isNil "comspec_overwatch_connect_fnc_submitTacticalReport"}) then {
                    private _list = ((_queue apply { _x select 0 }) + (_failed apply { format ["photo %1", _x] })) joinString ", ";
                    [{
                        params ["_who", "_list", "_credit"];
                        ["SITREP", "ROUTINE", format ["Envois non transmis de %1 (%2)", _who, _credit], format ["Éléments restés dans la file de son ATAK, non récupérables (joueur déconnecté) : %1", _list]] call comspec_overwatch_connect_fnc_submitTacticalReport;
                    }, [_who, _list, _credit], "Liste des envois perdus", 1] call comspec_atak_native_fnc_netSend;
                };
            };
            _done = _done + (count _queue) + (count _failed);
        };
        // Brouillons FRS et note de reco jamais envoyés : partent de mon téléphone, crédités.
        (_data getOrDefault ["frs", []]) params [["_fk", ""], ["_fp", ""], ["_fg", ""], ["_fb", ""]];
        (_data getOrDefault ["reco", []]) params [["_rt", ""], ["_rg", ""], ["_rx", ""]];
        private _drafts = [];
        if ((trim _fb) isNotEqualTo "") then { _drafts pushBack ["SPOTREP", format ["Brouillon FRS %1 (%2)", _fk, _credit], format ["%1%2%3", _fb, ["", format [" · lieu : %1", _fp]] select (_fp isNotEqualTo ""), ["", format [" · grille %1", _fg]] select (_fg isNotEqualTo "")]]; };
        if ((trim _rx) isNotEqualTo "") then { _drafts pushBack ["SPOTREP", format ["Note de reco %1 (%2)", _rt, _credit], format ["%1%2", _rx, ["", format [" · grille %1", _rg]] select (_rg isNotEqualTo "")]]; };
        {
            _x params ["_type", "_sum", "_det"];
            if (_bridge && {!isNil "comspec_overwatch_connect_fnc_submitTacticalReport"}) then {
                [{ params ["_type", "_sum", "_det"]; [_type, "ROUTINE", _sum, _det] call comspec_overwatch_connect_fnc_submitTacticalReport; }, [_type, _sum, _det], "Brouillon récupéré", 1] call comspec_atak_native_fnc_netSend;
            };
            _done = _done + 1;
        } forEach _drafts;
        if (_done isEqualTo 0) exitWith { ["INFO", "Rien à renvoyer : son ATAK n'avait aucune donnée en attente.", 4, 20] call comspec_atak_native_fnc_notify; false };
        if (!_bridge && {(count _drafts) > 0}) then { ["WARNING", "Brouillons lus mais non envoyés : Athena n'est pas joignable sur ce serveur.", 5, 30] call comspec_atak_native_fnc_notify; };
        (_st getOrDefault ["sent", createHashMap]) set ["recover", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
        ["INFO", format ["%1 élément(s) remis en route (%2)", _done, _credit], 5, 30] call comspec_atak_native_fnc_notify;
        call _render;
        true
    };
};
