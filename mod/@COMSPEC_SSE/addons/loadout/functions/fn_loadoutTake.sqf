/*
    Caisses de kits : demandes du joueur, décisions du serveur, réponses au seul joueur concerné.
    Joueur (LOCAL) :
      ["take", [_crate, _kit]]   _kit : 0 = mon kit (attribué sur Athena, sinon tenue de la caisse, sinon mon seul kit),
                                 id d'un kit Athena, ou "crate" = tenue de la caisse. Demande envoyée au serveur.
      ["give", [_tenue, _nom]]   réponse du serveur : tenue posée (fn_loadoutApply)
      ["msg", [_texte, _type]]   réponse du serveur : refus motivé, compte rendu
      ["mine", [_liste]]         liste de mes kits Athena [[id, nom]…] (comspec_sse_loadoutMine, menu « Kits proposés »)
    Serveur :
      ["srvTake", [_unit, _crate, _kit]]  vérifie : réglage, joueur vivant, distance (réglage CBA), 3 s entre deux
                                 demandes, kit proposé à ce joueur (fn_loadoutRegistry) ou tenue de la caisse
      ["hello", [_unit]]         le joueur arrive : sa liste lui est envoyée
      ["pushAll"]                paquet chargé : chaque joueur connecté reçoit sa liste
      ["crate", [_obj, _nom, _texte]]  l'objet devient caisse de kits (variable publique comspec_sse_loadoutCrate) ;
                                 tenue facultative vérifiée puis posée en comspec_sse_loadoutCrateKit = [nom, texte].
                                 Appel distant : Zeus (curateur assigné) ou administrateur seulement.
    Chaque mode vérifie son appelant : réponses acceptées du serveur seulement, demandes rattachées au poste qui
    possède l'unité (remoteExecutedOwner). Aucune requête réseau : les kits viennent du paquet d'identité Athena.
*/
params [["_op", ""], ["_a", []]];
private _from = if (isRemoteExecuted) then { remoteExecutedOwner } else { clientOwner };
private _fromServer = !isRemoteExecuted || {remoteExecutedOwner == 2};
private _tell = {
    params ["_owner", "_txt", ["_type", "WARNING"]];
    if (_owner == clientOwner) then { ["msg", [_txt, _type]] call comspec_sse_fnc_loadoutTake; } else { ["msg", [_txt, _type]] remoteExecCall ["comspec_sse_fnc_loadoutTake", _owner]; };
};
switch (_op) do {
    // ---------------------------------------------------------------- joueur
    case "take": {
        if (!hasInterface || {isRemoteExecuted}) exitWith {};
        _a params [["_crate", objNull, [objNull]], ["_kit", 0]];
        if ((diag_tickTime - (missionNamespace getVariable ["comspec_sse_loAsked", -10])) < 3) exitWith {
            ["Équipement : patientez quelques secondes avant une nouvelle demande.", "INFO", "Équipement"] call comspec_sse_fnc_notify;
        };
        missionNamespace setVariable ["comspec_sse_loAsked", diag_tickTime];
        if (isServer) then { ["srvTake", [player, _crate, _kit]] call comspec_sse_fnc_loadoutTake; } else { ["srvTake", [player, _crate, _kit]] remoteExecCall ["comspec_sse_fnc_loadoutTake", 2]; };
    };
    case "give": {
        if (!hasInterface || {!_fromServer}) exitWith {};
        _a params [["_lo", []], ["_name", ""]];
        [player, _lo, _name] call comspec_sse_fnc_loadoutApply;
    };
    case "msg": {
        if (!hasInterface || {!_fromServer}) exitWith {};
        _a params [["_txt", "", [""]], ["_type", "WARNING", [""]]];
        // Notification du téléphone ; sans ATAK natif, RPT seul (jamais le chat ni une alerte d'Arma).
        if !([_txt, _type, "Équipement"] call comspec_sse_fnc_notify) then { diag_log text format ["[COMSPEC SSE] Équipement : %1", _txt]; };
    };
    case "mine": {
        if (!hasInterface || {!_fromServer}) exitWith {};
        _a params [["_list", [], [[]]]];
        missionNamespace setVariable ["comspec_sse_loadoutMine", _list];
    };
    // ---------------------------------------------------------------- serveur
    case "srvTake": {
        if (!isServer) exitWith {};
        _a params [["_unit", objNull, [objNull]], ["_crate", objNull, [objNull]], ["_kit", 0]];
        if (isNull _unit || {(owner _unit) != _from && {isRemoteExecuted}}) exitWith {
            diag_log text format ["[COMSPEC SSE] Équipement : demande de kit refusée (poste %1 n'est pas celui de %2).", _from, _unit];
        };
        if !(missionNamespace getVariable ["comspec_sse_lo_take", true]) exitWith { [_from, "Équipement : la prise de kit est désactivée sur ce serveur."] call _tell; };
        if (!alive _unit || {_unit getVariable ["ACE_isUnconscious", false]}) exitWith { [_from, "Équipement : impossible dans votre état."] call _tell; };
        if (isNull _crate || {!(_crate getVariable ["comspec_sse_loadoutCrate", false])}) exitWith { [_from, "Équipement : cet objet n'est pas une caisse de kits."] call _tell; };
        private _max = missionNamespace getVariable ["comspec_sse_lo_distance", 5];
        if ((_unit distance _crate) > _max) exitWith { [_from, format ["Équipement : trop loin de la caisse (%1 m au plus).", round _max]] call _tell; };
        private _uid = getPlayerUID _unit;
        private _last = missionNamespace getVariable ["comspec_sse_loLast", createHashMap];
        missionNamespace setVariable ["comspec_sse_loLast", _last];
        private _who = [_uid, str _from] select (_uid isEqualTo "");
        if ((diag_tickTime - (_last getOrDefault [_who, -10])) < 3) exitWith { [_from, "Équipement : patientez quelques secondes avant une nouvelle demande.", "INFO"] call _tell; };
        _last set [_who, diag_tickTime];
        private _loaded = ["loaded"] call comspec_sse_fnc_loadoutRegistry;
        private _mine = ["kitsFor", [_uid]] call comspec_sse_fnc_loadoutRegistry;
        private _crateKit = _crate getVariable ["comspec_sse_loadoutCrateKit", []];
        private _noPack = "Kits Athena non chargés : collez une tenue dans la caisse (attribut Eden) ou demandez au Zeus.";
        // [nom, tenue] à envoyer, ou texte du refus.
        private _pick = switch (true) do {
            case (_kit isEqualTo "crate"): {
                [_crateKit, "Équipement : cette caisse n'a pas de tenue propre."] select (_crateKit isEqualTo [])
            };
            case (_kit isEqualTo 0 || {_kit isEqualTo ""}): {
                private _def = ["default", [_uid]] call comspec_sse_fnc_loadoutRegistry;
                switch (true) do {
                    case (_def isNotEqualTo 0): { ["get", [_def]] call comspec_sse_fnc_loadoutRegistry };
                    case (_crateKit isNotEqualTo []): { _crateKit };
                    case (!_loaded): { _noPack };
                    case ((count _mine) == 1): { ["get", [(_mine select 0) select 0]] call comspec_sse_fnc_loadoutRegistry };
                    case (_mine isEqualTo []): { "Équipement : aucun kit Athena ne vous est proposé." };
                    default { "Équipement : pas de kit attribué par défaut, choisissez dans « Kits proposés »." };
                }
            };
            case (!_loaded): { _noPack };
            case ((_mine findIf { (str (_x select 0)) isEqualTo (str _kit) }) < 0): { "Équipement : ce kit ne vous est pas proposé." };
            default { ["get", [_kit]] call comspec_sse_fnc_loadoutRegistry };
        };
        if (_pick isEqualType "") exitWith { [_from, _pick] call _tell; };
        if (_pick isEqualTo []) exitWith { [_from, "Équipement : kit introuvable (paquet Athena rechargé ?)."] call _tell; };
        _pick params ["_name", "_lo"];
        diag_log text format ["[COMSPEC SSE] Équipement : kit « %1 » remis à %2 (%3).", _name, name _unit, _uid];
        if (_from == clientOwner) then { ["give", [_lo, _name]] call comspec_sse_fnc_loadoutTake; } else { ["give", [_lo, _name]] remoteExecCall ["comspec_sse_fnc_loadoutTake", _from]; };
    };
    case "hello": {
        if (!isServer) exitWith {};
        _a params [["_unit", objNull, [objNull]]];
        if (isNull _unit || {(owner _unit) != _from && {isRemoteExecuted}}) exitWith {};
        private _list = ["kitsFor", [getPlayerUID _unit]] call comspec_sse_fnc_loadoutRegistry;
        if (_from == clientOwner) then { ["mine", [_list]] call comspec_sse_fnc_loadoutTake; } else { ["mine", [_list]] remoteExecCall ["comspec_sse_fnc_loadoutTake", _from]; };
    };
    case "pushAll": {
        if (!isServer) exitWith {};
        {
            private _list = ["kitsFor", [getPlayerUID _x]] call comspec_sse_fnc_loadoutRegistry;
            if ((owner _x) == clientOwner) then { ["mine", [_list]] call comspec_sse_fnc_loadoutTake; } else { ["mine", [_list]] remoteExecCall ["comspec_sse_fnc_loadoutTake", owner _x]; };
        } forEach (allPlayers select { !(_x isKindOf "HeadlessClient_F") });
    };
    case "crate": {
        if (!isServer) exitWith {};
        _a params [["_obj", objNull, [objNull]], ["_name", "", [""]], ["_text", ""]];
        if (isRemoteExecuted && {_from != 2}) then {
            private _u = (allPlayers select { owner _x == _from }) param [0, objNull];
            if !((!isNull _u && {!isNull (getAssignedCuratorLogic _u)}) || {(admin _from) > 0}) then { _obj = objNull; };
        };
        if (isNull _obj) exitWith { diag_log text format ["[COMSPEC SSE] Équipement : caisse de kits refusée (objet nul, ou poste %1 ni Zeus ni administrateur).", _from]; };
        if (_text isEqualType []) then { _text = str _text; };
        if !(_text isEqualType "") then { _text = ""; };
        private _report = "";
        if (_text isNotEqualTo "") then {
            if (((count _text) > 65536) || {([_text] call comspec_sse_fnc_loadoutParse) isEqualTo []}) then {
                _report = format ["Équipement : tenue de la caisse illisible (%1 caractères) : la caisse ne propose que les kits Athena.", count _text];
                _obj setVariable ["comspec_sse_loadoutCrateKit", nil, true];
            } else {
                _obj setVariable ["comspec_sse_loadoutCrateKit", [[_name, "Tenue de la caisse"] select (_name isEqualTo ""), _text], true];
            };
        };
        _obj setVariable ["comspec_sse_loadoutCrate", true, true];
        if (_report isEqualTo "") then { _report = format ["Équipement : %1 est une caisse de kits%2.", getText (configOf _obj >> "displayName"), ["", " (tenue propre)"] select (_text isNotEqualTo "")]; };
        diag_log text format ["[COMSPEC SSE] %1", _report];
        if (isRemoteExecuted && {_from != 2}) then { [_from, _report, "INFO"] call _tell; };
    };
};
