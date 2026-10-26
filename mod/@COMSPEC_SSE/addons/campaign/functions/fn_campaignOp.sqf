/*
    Campagnes SSE (serveur). Une campagne regroupe sa graine biométrique, ses
    personnages récurrents, ses organisations, ce que les joueurs en savent,
    les rencontres biométriques et le registre des pièces saisies.

    Trois types :
      permanente  la vraie campagne : tout est gardé d'une mission à l'autre ;
      recurrente  le terrain d'entraînement (bootcamp) : mêmes personnages et mêmes
                  organisations à chaque drill, mais tout redevient inconnu au chargement ;
      temporaire  drill SOT : bac à sable jetable, graine propre, rien n'est sauvegardé,
                  remise à zéro à la fin. Peut copier la structure d'une autre campagne.

    Sauvegarde dans le profil du serveur (profileNamespace), index comspec_sse_campIndex,
    une entrée comspec_sse_camp_<ID> par campagne (structures empaquetées bioPack).

    [_op, _args, _requester] call comspec_sse_fnc_campaignOp
      "load"    [id, nom, type, copier depuis, sauvegarde auto (min)]  crée si absente
      "save"    [motif]
      "reset"   []            redevient inconnu (récurrente) ou vide (temporaire)
      "end"     []            fin de drill : temporaire remise à zéro, sinon sauvegarde
      "delete"  [id]
      "export"  [id]          → texte envoyé au demandeur (presse-papiers)
      "import"  [texte, nouvel id]
      "bind"    [unité]       personnage récurrent apparu : identité et appartenance
      "publish" []            diffuse l'état (comspec_sse_campaign, comspec_sse_campIndex)
*/
params [["_op", "", [""]], ["_args", [], [[]]], ["_req", objNull, [objNull]]];
_op = toLower _op;
// Client : appel distant (liste blanche CfgRemoteExec) ; le serveur retrouve le demandeur par remoteExecutedOwner,
// jamais par un objet fourni dans la demande (un client pouvait y mettre l'unité du Zeus).
if (!isServer) exitWith { [_op, _args] remoteExecCall ["comspec_sse_fnc_campaignOp", 2]; false };
if (isRemoteExecuted && {remoteExecutedOwner != 2}) then {
    private _ro = remoteExecutedOwner;
    _req = (allPlayers select { owner _x == _ro }) param [0, objNull];
};
if (isRemoteExecuted && {remoteExecutedOwner != 2} && {isNull _req} && {_op isNotEqualTo "publish"}) exitWith {
    diag_log text format ["[COMSPEC SSE] Campagne : « %1 » refusé au poste %2 (demandeur introuvable).", _op, remoteExecutedOwner];
    false
};
// Demande venue d'un client (appel distant, demandeur retrouvé ci-dessus) : Zeus ou administrateur seulement, sauf la diffusion
// de l'état. Les appels du serveur lui-même (modules Eden, fin de mission, hôte) n'ont pas de demandeur.
if (!isNull _req && {_op isNotEqualTo "publish"} && {!([_req] call comspec_sse_fnc_isAuthority)}) exitWith {
    diag_log text format ["[COMSPEC SSE] Campagne : « %1 » refusé à %2 (ni Zeus ni administrateur).", _op, name _req];
    ["comspec_sse_campNote", ["action réservée au Zeus ou à l'administrateur."], _req] call CBA_fnc_targetEvent;
    false
};

private _note = {
    params ["_msg", ["_all", false]];
    diag_log text ("[COMSPEC SSE] Campagne : " + _msg);
    if (_all) exitWith { ["comspec_sse_campNote", [_msg]] call CBA_fnc_globalEvent; };
    if (!isNull _req) then { ["comspec_sse_campNote", [_msg], _req] call CBA_fnc_targetEvent; };
};
private _cur = missionNamespace getVariable ["comspec_sse_campaign", createHashMap];
private _index = profileNamespace getVariable ["comspec_sse_campIndex", []];
if (!(_index isEqualType [])) then { _index = []; };
private _slug = { params ["_s"]; toUpper (((trim _s) splitString " '/\.,:;") joinString "-") };
private _session = (missionNamespace getVariable ["comspec_sse_bioReg", createHashMap]) getOrDefault ["session", "#"];
// Clé stable d'une mission à l'autre : identifiant fixé, clé BII ou graine Eden ; pas une clé de partie.
private _stable = { params ["_k"]; _k isEqualType "" && {_k isNotEqualTo ""} && {(_k find ("S" + _session + "-")) != 0} };
// Graine biométrique unique au monde : deux campagnes (même d'une autre communauté, même avec
// le même identifiant) ne tirent jamais la même ; Athena signale toute graine reprise.
private _newSeed = {
    params ["_prefix"];
    private _h = hashValue [serverName, missionName, systemTimeUTC, diag_tickTime, random 1e9, _prefix];
    format ["%1-%2", _prefix, ((_h splitString "+/=") joinString "") select [0, 14]]
};
private _publish = {
    missionNamespace setVariable ["comspec_sse_campaign", _cur, true];
    missionNamespace setVariable ["comspec_sse_campIndex", _index, true];
};
private _wipe = {
    params [["_structure", true]];
    // Ce que les joueurs savent disparaît ; avec _structure = false, tout disparaît.
    if (_structure) then {
        { _y set ["known", 0]; _y set ["how", ""]; _y set ["when", ""]; } forEach (missionNamespace getVariable ["comspec_sse_orgMembers", createHashMap]);
        { _y set ["known", 0]; _y set ["how", ""]; } forEach (missionNamespace getVariable ["comspec_sse_orgs", createHashMap]);
    } else {
        comspec_sse_orgs = createHashMap;
        comspec_sse_orgMembers = createHashMap;
        { _x setVariable ["COMSPEC_SSE_Org", nil, true]; } forEach (allUnits + allDeadMen);
    };
    // Numéro de pièce effacé seulement sur les objets saisis (registre) : un setVariable public sur chaque objet
    // de la mission (allMissionObjects) envoyait des milliers de messages à chaque remise à zéro.
    {
        private _o = objectFromNetId (_y getOrDefault ["netId", ""]);
        if (!isNull _o && {(_o getVariable ["COMSPEC_SSE_PieceId", ""]) isNotEqualTo ""}) then { _o setVariable ["COMSPEC_SSE_PieceId", nil, true]; };
    } forEach (missionNamespace getVariable ["comspec_sse_custody", createHashMap]);
    comspec_sse_anaSeen = createHashMap;
    comspec_sse_custody = createHashMap;
    comspec_sse_custodyN = 0;
    missionNamespace setVariable ["comspec_sse_bioRuntime", createHashMap];
    missionNamespace setVariable ["comspec_sse_bioLatents", []];
    { publicVariable _x; } forEach ["comspec_sse_orgs", "comspec_sse_orgMembers", "comspec_sse_anaSeen", "comspec_sse_custody"];
    ["comspec_sse_anaChanged", []] call CBA_fnc_globalEvent;
};

switch (_op) do {
    case "publish": { call _publish; true };

    case "load": {
        _args params [["_id", "", [""]], ["_name", "", [""]], ["_type", "permanente", [""]], ["_copy", "", [""]], ["_auto", 0, [0]]];
        _id = [_id] call _slug;
        if (_id isEqualTo "") exitWith { ["identifiant de campagne requis."] call _note; false };
        private _saved = [profileNamespace getVariable [format ["comspec_sse_camp_%1", _id], []]] call comspec_sse_fnc_bioUnpack;
        private _isNew = !(_saved isEqualType createHashMap) || {count _saved == 0};
        if (_isNew) then {
            _saved = createHashMapFromArray [
                ["id", _id], ["name", [_name, _id] select (_name isEqualTo "")], ["type", toLower _type],
                ["seed", [_id] call _newSeed], ["created", [systemTimeUTC] call comspec_sse_fnc_campaignDate],
                ["missions", 0], ["log", []], ["roster", createHashMap]
            ];
        };
        private _type = _saved getOrDefault ["type", "permanente"];
        if (!_isNew && {_name isNotEqualTo ""}) then { _saved set ["name", _name]; };

        // Structure de départ copiée d'une autre campagne (drill sur un réseau réaliste).
        private _source = _saved;
        if (_copy isNotEqualTo "" && {_isNew || {_type isEqualTo "temporaire"}}) then {
            private _c = [profileNamespace getVariable [format ["comspec_sse_camp_%1", [_copy] call _slug], []]] call comspec_sse_fnc_bioUnpack;
            if (_c isEqualType createHashMap && {count _c > 0}) then { _source = _c; _saved set ["copyFrom", [_copy] call _slug]; };
        };

        // Graine biométrique : la même pour toute la campagne ; propre à la partie pour un drill jetable.
        private _seed = if (_type isEqualTo "temporaire") then { ["TMP-" + _id] call _newSeed } else { _saved get "seed" };
        missionNamespace setVariable ["comspec_sse_bio_campaignSeed", _seed, true];
        missionNamespace setVariable ["comspec_sse_bio_campaignMemory", false, true];

        // Organisations et appartenances (clés stables seulement).
        comspec_sse_orgs = _source getOrDefault ["orgs", createHashMap];
        comspec_sse_orgMembers = _source getOrDefault ["members", createHashMap];
        comspec_sse_anaSeen = createHashMap;
        comspec_sse_custody = createHashMap;
        comspec_sse_custodyN = 0;
        if (_type isEqualTo "permanente" && {_source isEqualTo _saved}) then {
            comspec_sse_anaSeen = _saved getOrDefault ["seen", createHashMap];
            comspec_sse_custody = _saved getOrDefault ["custody", createHashMap];
            comspec_sse_custodyN = _saved getOrDefault ["custodyN", 0];
            if ((_saved getOrDefault ["sealN", 0]) > 0) then { comspec_sse_custodySealN = _saved get "sealN"; };
            missionNamespace setVariable ["comspec_sse_bioRuntime", _saved getOrDefault ["bio", createHashMap]];
            // Pièces des missions précédentes : archivées (objets absents de cette carte).
            { _y set ["netId", ""]; _y set ["archived", true]; } forEach comspec_sse_custody;
        } else {
            // Récurrente, temporaire : mêmes personnages, rien de connu au départ.
            { _y set ["known", 0]; _y set ["how", ""]; _y set ["when", ""]; } forEach comspec_sse_orgMembers;
            { _y set ["known", 0]; _y set ["how", ""]; } forEach comspec_sse_orgs;
            missionNamespace setVariable ["comspec_sse_bioRuntime", createHashMap];
        };
        { _y set ["netId", ""]; } forEach comspec_sse_orgMembers;
        { publicVariable _x; } forEach ["comspec_sse_orgs", "comspec_sse_orgMembers", "comspec_sse_anaSeen", "comspec_sse_custody"];

        _cur = createHashMapFromArray [
            ["id", _id], ["name", _saved get "name"], ["type", _type], ["seed", _seed],
            ["missions", (_saved getOrDefault ["missions", 0]) + 1], ["isNew", _isNew],
            ["copyFrom", _saved getOrDefault ["copyFrom", ""]], ["auto", _auto], ["loadedAt", [systemTimeUTC] call comspec_sse_fnc_campaignDate],
            ["lastSave", ""], ["roster", _source getOrDefault ["roster", createHashMap]],
            ["log", _saved getOrDefault ["log", []]], ["created", _saved getOrDefault ["created", ""]]
        ];
        call _publish;
        // Personnages déjà présents : identité et appartenance rattachées ; les
        // appartenances saisies dans Eden s'ajoutent (sans rien faire oublier).
        { ["bind", [_x]] call comspec_sse_fnc_campaignOp; } forEach (allUnits + allDeadMen);
        if (!isNil "comspec_sse_fnc_orgApplyEden") then { [] call comspec_sse_fnc_orgApplyEden; };
        ["comspec_sse_anaChanged", []] call CBA_fnc_globalEvent;

        // Sauvegarde automatique.
        private _h = missionNamespace getVariable ["comspec_sse_campAutoHandle", -1];
        if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; };
        if (_auto > 0 && {_type isNotEqualTo "temporaire"}) then {
            missionNamespace setVariable ["comspec_sse_campAutoHandle", [{ ["save", ["sauvegarde automatique"]] call comspec_sse_fnc_campaignOp; }, _auto * 60] call CBA_fnc_addPerFrameHandler];
        };
        [format ["%1 « %2 » (%3) %4.", ["Campagne", "Drill"] select (_type isEqualTo "temporaire"), _cur get "name",
            ["permanente", "récurrente", "temporaire, rien ne sera gardé"] select (((["permanente", "recurrente", "temporaire"] find _type) max 0)),
            ["chargée", "créée"] select _isNew], true] call _note;
        true
    };

    case "save": {
        _args params [["_why", "sauvegarde", [""]]];
        if (count _cur == 0) exitWith { ["aucune campagne chargée."] call _note; false };
        private _type = _cur get "type";
        if (_type isEqualTo "temporaire") exitWith { ["drill temporaire : rien n'est sauvegardé (c'est voulu)."] call _note; false };
        private _id = _cur get "id";
        private _old = [profileNamespace getVariable [format ["comspec_sse_camp_%1", _id], []]] call comspec_sse_fnc_bioUnpack;
        if (!(_old isEqualType createHashMap)) then { _old = createHashMap; };

        // Membres et personnes rencontrées : seulement les clés stables.
        private _members = createHashMap;
        { if ([_x] call _stable) then { private _m = +_y; _m set ["netId", ""]; _members set [_x, _m]; }; } forEach (missionNamespace getVariable ["comspec_sse_orgMembers", createHashMap]);
        private _seen = createHashMap;
        { if ([_x] call _stable) then { _seen set [_x, ""]; }; } forEach (missionNamespace getVariable ["comspec_sse_anaSeen", createHashMap]);
        private _bio = createHashMap;
        { if ([_x] call _stable) then { _bio set [_x, _y]; }; } forEach (missionNamespace getVariable ["comspec_sse_bioRuntime", createHashMap]);

        // Personnages récurrents : identité telle que vue cette mission.
        private _roster = +(_cur getOrDefault ["roster", createHashMap]);
        {
            private _k = _x getVariable ["COMSPEC_SSE_BioKey", ""];
            if ([_k] call _stable) then {
                private _lbl = [_x] call comspec_sse_fnc_anaLabel;
                private _r = _roster getOrDefault [_k, createHashMap];
                _r set ["name", _lbl select 0];
                if ((_lbl select 1) isNotEqualTo "") then { _r set ["alias", _lbl select 1]; };
                _r set ["nationality", _x getVariable ["comspec_sse_personNationality", _r getOrDefault ["nationality", ""]]];
                _r set ["lastSeen", format ["%1 (%2)", missionName, worldName]];
                _r set ["status", ["vivant", "décédé"] select (!alive _x)];
                _roster set [_k, _r];
            };
        } forEach (allUnits + allDeadMen);

        private _log = +(_cur getOrDefault ["log", []]);
        private _nKnown = 0;
        { if ((_y getOrDefault ["known", 0]) >= 1) then { _nKnown = _nKnown + 1; }; } forEach _members;
        private _entry = [[systemTimeUTC] call comspec_sse_fnc_campaignDate, missionName, worldName, _why,
            format ["%1 organisation(s), %2 membre(s) connu(s), %3 pièce(s)", count (missionNamespace getVariable ["comspec_sse_orgs", createHashMap]), _nKnown, count (missionNamespace getVariable ["comspec_sse_custody", createHashMap])]];
        // Une ligne par mission : la dernière sauvegarde de la partie remplace la précédente.
        if (_log isNotEqualTo [] && {((_log select -1) select 1) isEqualTo missionName} && {(_cur getOrDefault ["lastSave", ""]) isNotEqualTo ""}) then { _log set [count _log - 1, _entry]; } else { _log pushBack _entry; };
        _log = _log select [((count _log) - 60) max 0];

        private _snap = createHashMapFromArray [
            ["id", _id], ["name", _cur get "name"], ["type", _type], ["seed", _cur get "seed"],
            ["created", _cur getOrDefault ["created", _old getOrDefault ["created", ""]]],
            ["updated", [systemTimeUTC] call comspec_sse_fnc_campaignDate],
            ["missions", _cur get "missions"], ["log", _log], ["roster", _roster],
            ["orgs", +(missionNamespace getVariable ["comspec_sse_orgs", createHashMap])],
            ["members", _members], ["copyFrom", _cur getOrDefault ["copyFrom", ""]]
        ];
        if (_type isEqualTo "permanente") then {
            _snap set ["seen", _seen];
            _snap set ["bio", _bio];
            _snap set ["custody", +(missionNamespace getVariable ["comspec_sse_custody", createHashMap])];
            _snap set ["custodyN", missionNamespace getVariable ["comspec_sse_custodyN", 0]];
            _snap set ["sealN", missionNamespace getVariable ["comspec_sse_custodySealN", 0]];
        } else {
            // Récurrente : la structure est gardée, jamais ce que les joueurs ont découvert.
            { _y set ["known", 0]; _y set ["how", ""]; _y set ["when", ""]; } forEach _members;
            { _y set ["known", 0]; _y set ["how", ""]; } forEach (_snap get "orgs");
        };
        profileNamespace setVariable [format ["comspec_sse_camp_%1", _id], [_snap] call comspec_sse_fnc_bioPack];
        private _row = [_id, _cur get "name", _type, _snap get "updated", _cur get "missions", worldName];
        private _i = _index findIf { (_x select 0) isEqualTo _id };
        if (_i >= 0) then { _index set [_i, _row]; } else { _index pushBack _row; };
        profileNamespace setVariable ["comspec_sse_campIndex", _index];
        saveProfileNamespace;
        _cur set ["lastSave", _snap get "updated"];
        _cur set ["log", _log];
        _cur set ["roster", _roster];
        call _publish;
        [format ["« %1 » sauvegardée (%2).", _cur get "name", _why], _why isNotEqualTo "sauvegarde automatique"] call _note;
        // Copie dans Athena (Bureau SSE › Campagnes) quand le serveur est relié.
        if (missionNamespace getVariable ["comspec_sse_camp_athenaSync", true] && {!isNil "comspec_sse_fnc_campaignAthena"} && {!isNil "comspec_sse_fnc_isOnline"} && {[] call comspec_sse_fnc_isOnline}) then {
            ["push", [_id], [objNull, _req] select (_why isNotEqualTo "sauvegarde automatique")] call comspec_sse_fnc_campaignAthena;
        };
        true
    };

    case "reset": {
        if (count _cur == 0) exitWith { ["aucune campagne chargée."] call _note; false };
        if ((_cur get "type") isEqualTo "permanente") exitWith { ["une campagne permanente ne se remet pas à zéro : chargez une autre campagne ou supprimez la sauvegarde."] call _note; false };
        // Récurrente : mêmes personnages, tout redevient inconnu. Temporaire : vide (sauf structure copiée), nouvelle graine.
        [(_cur get "type") isEqualTo "recurrente" || {(_cur getOrDefault ["copyFrom", ""]) isNotEqualTo ""}] call _wipe;
        if ((_cur get "type") isEqualTo "temporaire") then {
            _cur set ["seed", ["TMP-" + (_cur get "id")] call _newSeed];
            missionNamespace setVariable ["comspec_sse_bio_campaignSeed", _cur get "seed", true];
        };
        call _publish;
        ["remise à zéro : tout redevient inconnu des joueurs.", true] call _note;
        true
    };

    case "end": {
        if (count _cur == 0) exitWith { false };
        if ((_cur get "type") isEqualTo "temporaire") exitWith {
            [false] call _wipe;
            ["fin du drill : bac à sable vidé, rien n'a été gardé.", true] call _note;
            true
        };
        ["save", ["fin de mission"], _req] call comspec_sse_fnc_campaignOp
    };

    case "delete": {
        _args params [["_id", "", [""]]];
        _id = [_id] call _slug;
        profileNamespace setVariable [format ["comspec_sse_camp_%1", _id], nil];
        _index = _index select { (_x select 0) isNotEqualTo _id };
        profileNamespace setVariable ["comspec_sse_campIndex", _index];
        saveProfileNamespace;
        call _publish;
        [format ["sauvegarde « %1 » supprimée.", _id]] call _note;
        true
    };

    case "export": {
        _args params [["_id", "", [""]]];
        if (_id isEqualTo "") then { _id = _cur getOrDefault ["id", ""]; };
        private _raw = profileNamespace getVariable [format ["comspec_sse_camp_%1", [_id] call _slug], []];
        if (_raw isEqualTo []) exitWith { ["rien à exporter : sauvegardez d'abord."] call _note; false };
        if (!isNull _req) then { ["comspec_sse_campExport", [str ["COMSPEC_SSE_CAMPAIGN", 1, _raw]], _req] call CBA_fnc_targetEvent; };
        true
    };

    case "import": {
        _args params [["_text", "", [""]], ["_newId", "", [""]]];
        private _arr = parseSimpleArray _text;
        if (!(_arr isEqualType []) || {(_arr param [0, ""]) isNotEqualTo "COMSPEC_SSE_CAMPAIGN"}) exitWith { ["texte d'import non reconnu."] call _note; false };
        private _c = [_arr select 2] call comspec_sse_fnc_bioUnpack;
        if (!(_c isEqualType createHashMap)) exitWith { ["campagne illisible."] call _note; false };
        private _id = [[_newId, _c getOrDefault ["id", "IMPORT"]] select (_newId isEqualTo "")] call _slug;
        _c set ["id", _id];
        profileNamespace setVariable [format ["comspec_sse_camp_%1", _id], [_c] call comspec_sse_fnc_bioPack];
        private _row = [_id, _c getOrDefault ["name", _id], _c getOrDefault ["type", "permanente"], _c getOrDefault ["updated", ""], _c getOrDefault ["missions", 0], "import"];
        private _i = _index findIf { (_x select 0) isEqualTo _id };
        if (_i >= 0) then { _index set [_i, _row]; } else { _index pushBack _row; };
        profileNamespace setVariable ["comspec_sse_campIndex", _index];
        saveProfileNamespace;
        call _publish;
        [format ["campagne « %1 » importée sous l'identifiant %2. Chargez-la pour l'utiliser.", _c getOrDefault ["name", _id], _id]] call _note;
        true
    };

    case "bind": {
        _args params [["_u", objNull, [objNull]]];
        if (isNull _u || {!(_u isKindOf "CAManBase")}) exitWith { false };
        private _k = [_u] call comspec_sse_fnc_bioPersonKey;
        if !([_k] call _stable) exitWith { false };
        // Personnage récurrent : il garde son nom et son alias d'une mission à l'autre.
        private _r = (_cur getOrDefault ["roster", createHashMap]) getOrDefault [_k, createHashMap];
        if (count _r > 0 && {!(_u getVariable ["COMSPEC_SSE_NameAuthored", false])} && {!isNil "comspec_sse_fnc_setIdentity"}) then {
            private _pairs = [["name", _r getOrDefault ["name", ""]]];
            if ((_r getOrDefault ["alias", ""]) isNotEqualTo "") then { _pairs pushBack ["alias", _r get "alias"]; };
            [_u, _pairs] call comspec_sse_fnc_setIdentity;
        };
        private _m = (missionNamespace getVariable ["comspec_sse_orgMembers", createHashMap]) getOrDefault [_k, createHashMap];
        if (count _m > 0) then {
            _m set ["netId", netId _u];
            _u setVariable ["COMSPEC_SSE_Org", [_m get "org", _m get "role"], true];
            publicVariable "comspec_sse_orgMembers";
        };
        if (_k in (missionNamespace getVariable ["comspec_sse_anaSeen", createHashMap])) then {
            comspec_sse_anaSeen set [_k, netId _u];
            publicVariable "comspec_sse_anaSeen";
        };
        true
    };
    default { false };
}
