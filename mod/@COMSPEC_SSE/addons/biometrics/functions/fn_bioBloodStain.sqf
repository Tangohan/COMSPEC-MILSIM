/*
    Taches de sang au sol : traces latentes et ADN (retour de jeu ATAK 1.97.0).

    Le serveur relève où chaque blessé saigne (gouttes tous les 1,5 m) et où un corps tombe (flaque), avec la
    personne la plus proche à ce moment (soignant, camarade qui l'a tenu). Liste publique COMSPEC_SSE_BloodTraces :
      [id, posASL, clé du saignant, unité, heure (time), clé du témoin, unité témoin, type "drop" | "pool"]
    Au sol, action ACE personnelle COMSPEC SSE › « Tache de sang », seulement à moins de 2,5 m d'une trace :
      - Examiner : âge, type, empreintes sanglantes visibles ;
      - Relever les latentes : empreintes de celui qui a touché le sang (le blessé ou son soignant) -> base SSE ;
      - Prélever l'ADN : le sang est celui du blessé -> laboratoire (comme un écouvillon).
    La pluie et le temps dégradent les traces.

    Params : [mode, arguments]
      "server"            (XEH_postInit serveur) suivi des saignements
      "client"            (initBiometricsACE) action ACE personnelle
      "near" [unité]      -> trace la plus proche à moins de 2,5 m, [] sinon
      "examine" / "latent" / "dna" [unité]
      "say" [texte, type]  -> notification ATAK (retours de l'action, rien sur l'écran du jeu)
*/
params [["_mode", "near"], ["_args", []]];
private _now = { [time, serverTime] select isMultiplayer };

// "near" renvoie la trace (le « true » final de la fonction l'écrasait : menu toujours affiché, examen « any … true »).
if (_mode isEqualTo "near") exitWith {
    _args params [["_u", player]];
    private _pos = getPosASL _u;
    private _best = [];
    private _bd = 2.5;
    {
        private _d = (_x select 1) distance2D _pos;
        if (_d < _bd && {abs (((_x select 1) select 2) - (_pos select 2)) < 3}) then { _bd = _d; _best = _x; };
    } forEach (missionNamespace getVariable ["COMSPEC_SSE_BloodTraces", []]);
    _best
};
// Retour à l'opérateur : notification du téléphone ATAK natif. Téléphone fermé (le joueur regarde le sol), le toast
// de l'ATAK ne s'affiche pas : comspec_sse_fnc_notify met alors le même texte dans une bulle d'aide discrète.
// Texte en français : chaque ligne est traduite par notify (une ligne déjà traduite reste telle quelle).
if (_mode isEqualTo "say") exitWith {
    _args params [["_txt", ""], ["_type", "INFO"]];
    if (!hasInterface || {_txt isEqualTo ""}) exitWith {};
    [_txt, _type, "SSE · Tache de sang"] call comspec_sse_fnc_notify;
};

switch (_mode) do {
    case "server": {
        if (!isServer) exitWith {};
        missionNamespace setVariable ["COMSPEC_SSE_BloodTraces", [], true];
        private _add = {
            params ["_u", "_pos", "_kind"];
            private _list = missionNamespace getVariable ["COMSPEC_SSE_BloodTraces", []];
            private _key = [_u] call comspec_sse_fnc_bioPersonKey;
            if (_key isEqualTo "") exitWith {};
            // Pas deux gouttes du même blessé à moins de 1,5 m.
            if (_kind isEqualTo "drop" && {(_list findIf { (_x select 2) isEqualTo _key && {((_x select 1) distance _pos) < 1.5} }) >= 0}) exitWith {};
            private _near = (_pos nearEntities [["CAManBase"], 2.5]) select { _x != _u && {alive _x} };
            private _w = _near param [0, objNull];
            private _n = (missionNamespace getVariable ["COMSPEC_SSE_BloodN", 0]) + 1;
            missionNamespace setVariable ["COMSPEC_SSE_BloodN", _n];
            _list pushBack [format ["BLD-%1", 1000 + _n], _pos, _key, _u, [time, serverTime] select isMultiplayer, [_w] call comspec_sse_fnc_bioPersonKey, _w, _kind];
            // 150 traces au plus : les plus anciennes partent.
            if ((count _list) > 150) then { _list deleteRange [0, (count _list) - 150]; };
            missionNamespace setVariable ["COMSPEC_SSE_BloodTraces", _list];
            missionNamespace setVariable ["COMSPEC_SSE_BloodDirty", true];
        };
        missionNamespace setVariable ["COMSPEC_SSE_BloodAdd", _add];
        [{
            private _add = missionNamespace getVariable ["COMSPEC_SSE_BloodAdd", {}];
            {
                private _u = _x;
                private _b = _u getVariable ["ace_medical_woundBleeding", 0];
                if !(_b isEqualType 0) then { _b = 0; };
                if (_b > 0 || {isBleeding _u}) then {
                    private _p = getPosASL _u;
                    // Hauteur des pieds (getPosASL) : la trace reste à l'étage où l'unité saigne, pas au niveau du terrain.
                    if (isNull objectParent _u && {!surfaceIsWater _p}) then { [_u, _p, "drop"] call _add; };
                };
            } forEach (allUnits select { _x isKindOf "CAManBase" });
            // Publication groupée (pas une diffusion réseau par goutte) et au plus toutes les 12 s : la liste entière
            // (150 traces) repartait vers chaque client toutes les 3 s tant qu'un blessé se déplaçait.
            if ((missionNamespace getVariable ["COMSPEC_SSE_BloodDirty", false])
                && {diag_tickTime >= (missionNamespace getVariable ["COMSPEC_SSE_BloodPubAt", -100]) + 12}) then {
                missionNamespace setVariable ["COMSPEC_SSE_BloodDirty", false];
                missionNamespace setVariable ["COMSPEC_SSE_BloodPubAt", diag_tickTime];
                publicVariable "COMSPEC_SSE_BloodTraces";
            };
        }, 3] call CBA_fnc_addPerFrameHandler;
        addMissionEventHandler ["EntityKilled", {
            params ["_u"];
            if !(_u isKindOf "CAManBase") exitWith {};
            private _p = getPosASL _u;
            if (!isNull objectParent _u || {surfaceIsWater _p}) exitWith {};
            [_u, _p, "pool"] call (missionNamespace getVariable ["COMSPEC_SSE_BloodAdd", {}]);
        }];
    };
    case "client": {
        if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"}) exitWith {};
        private _p = [false, false, false, false, true];
        private _root = ["COMSPEC_SSE_Blood", "Tache de sang", "\a3\ui_f\data\igui\cfg\simpleTasks\types\heal_ca.paa", {},
            { (missionNamespace getVariable ["comspec_sse_bio_enabled", true]) && {isNull objectParent (_this select 0)} && {(["near", [_this select 0]] call comspec_sse_fnc_bioBloodStain) isNotEqualTo []} },
            {}, [], [0, 0, 0], 2.5, _p] call comspec_sse_fnc_aceAction;
        private _kids = [
            ["COMSPEC_SSE_BloodLook", "Examiner la tache", { ["examine", [_this select 0]] call comspec_sse_fnc_bioBloodStain }, { true }],
            // Relevés : seulement avec le kit porté (un usage consommé), l'examen à l'œil reste libre.
            ["COMSPEC_SSE_BloodLatent", "Relever les empreintes sanglantes", {
                    if (([_this select 0, "COMSPEC_SSE_FingerprintKit", 10] call comspec_sse_fnc_kitUse) > 0) then { ["latent", [_this select 0]] call comspec_sse_fnc_bioBloodStain; };
                },
                { (missionNamespace getVariable ["comspec_sse_bio_latents", true]) && {"COMSPEC_SSE_FingerprintKit" in (items (_this select 0))} }],
            ["COMSPEC_SSE_BloodDna", "Prélever l'ADN (sang)", {
                    private _cls = ["COMSPEC_SSE_SwabKit", "COMSPEC_SSE_DNKit"] select ("COMSPEC_SSE_DNKit" in (items (_this select 0)));
                    if (([_this select 0, _cls, 12] call comspec_sse_fnc_kitUse) > 0) then { ["dna", [_this select 0]] call comspec_sse_fnc_bioBloodStain; };
                },
                { ((items (_this select 0)) findIf { _x in ["COMSPEC_SSE_SwabKit", "COMSPEC_SSE_DNKit"] }) >= 0 }]
        ];
        // À la racine des actions personnelles, visible seulement près d'une trace (posée sur le joueur,
        // reposée à la réapparition) : il n'y a plus de menu personnel « COMSPEC SSE ».
        private _acts = [_root] + (_kids apply {
            _x params ["_id", "_label", "_code", "_cond"];
            [_id, _label, "", _code, _cond, {}, [], [0, 0, 0], 2.5, _p] call comspec_sse_fnc_aceAction
        });
        missionNamespace setVariable ["COMSPEC_SSE_BloodActs", _acts];
        private _install = {
            params ["_unit"];
            if (_unit getVariable ["comspec_sse_bloodSelf", false]) exitWith {};
            _unit setVariable ["comspec_sse_bloodSelf", true];
            private _acts = missionNamespace getVariable ["COMSPEC_SSE_BloodActs", []];
            [_unit, 1, ["ACE_SelfActions"], _acts select 0] call ace_interact_menu_fnc_addActionToObject;
            { [_unit, 1, ["ACE_SelfActions", "COMSPEC_SSE_Blood"], _x] call ace_interact_menu_fnc_addActionToObject; } forEach (_acts select [1]);
        };
        missionNamespace setVariable ["COMSPEC_SSE_BloodInstall", _install];
        [{ !isNull player }, { [player] call (missionNamespace getVariable "COMSPEC_SSE_BloodInstall"); }] call CBA_fnc_waitUntilAndExecute;
        player addEventHandler ["Respawn", { params ["_unit"]; [_unit] call (missionNamespace getVariable "COMSPEC_SSE_BloodInstall"); }];
    };
    case "examine";
    case "latent";
    case "dna": {
        _args params [["_u", player]];
        private _t = ["near", [_u]] call comspec_sse_fnc_bioBloodStain;
        if (_t isEqualTo []) exitWith { ["say", ["Plus de tache de sang à portée."]] call comspec_sse_fnc_bioBloodStain; };
        _t params ["_id", "_pos", "_key", "_src", "_at", "_wKey", "_wit", "_kind"];
        private _ageMin = (((call _now) - _at) max 0) / 60;
        // Sous la pluie et à découvert, le sang se lave.
        private _wet = rain > 0.2 && {(count (lineIntersectsSurfaces [_pos vectorAdd [0, 0, 0.2], _pos vectorAdd [0, 0, 12], _u, objNull, true, 1])) == 0};
        private _what = ["Goutte de sang", "Flaque de sang"] select (_kind isEqualTo "pool");
        private _age = switch (true) do { case (_ageMin < 5): { "fraîche, encore liquide" }; case (_ageMin < 30): { "en train de sécher" }; case (_ageMin < 120): { "sèche, brune" }; default { "ancienne, noircie" }; };
        // Empreintes sanglantes : celui qui a touché le sang (soignant, camarade) ou le blessé lui-même.
        private _toucher = [[_key, _src], [_wKey, _wit]] select (_wKey isNotEqualTo "" && {([_id, "who"] call comspec_sse_fnc_bioRand) < 0.6});
        private _prints = !_wet && {([_id, "prints"] call comspec_sse_fnc_bioRand) < ([0.45, 0.8] select (_kind isEqualTo "pool"))};
        switch (_mode) do {
            case "examine": {
                player playActionNow "Medic";
                // Chaque ligne traduite à part (pas de seconde traduction du texte assemblé).
                ["say", [[
                    format ["%1 (%2)%3", _what call comspec_sse_fnc_tr, _age call comspec_sse_fnc_tr, ["", (", lavée par la pluie") call comspec_sse_fnc_tr] select _wet],
                    (["Aucune empreinte visible dans le sang.", "Empreintes sanglantes visibles : relevé de latentes possible."] select _prints) call comspec_sse_fnc_tr,
                    format ["%1 %2", ("Référence") call comspec_sse_fnc_tr, _id]
                ] joinString endl]] call comspec_sse_fnc_bioBloodStain;
            };
            case "latent": {
                if !([_u, "fingerprint"] call comspec_sse_fnc_hasEquipment) exitWith {
                    ["say", ["Kit d'empreintes, SEEK II ou kit de relevé compatible requis.", "WARNING"]] call comspec_sse_fnc_bioBloodStain;
                };
                [8, "Relevé des empreintes sanglantes (photo, ruban)...", {
                    params ["_t", "_toucher", "_prints"];
                    _t params ["_id", "_pos"];
                    _toucher params ["_okey", "_ounit"];
                    if (!_prints || {_okey isEqualTo ""}) exitWith { ["say", ["Aucune crête exploitable dans le sang (étalé, lavé ou piétiné)."]] call comspec_sse_fnc_bioBloodStain; };
                    private _n = (missionNamespace getVariable [format ["COMSPEC_SSE_BloodTry_%1", _id], 0]) + 1;
                    missionNamespace setVariable [format ["COMSPEC_SSE_BloodTry_%1", _id], _n];
                    private _r = { params ["_s"]; [_id, format ["lat|%1|%2", _n, _s]] call comspec_sse_fnc_bioRand };
                    private _fingers = ([_ounit, _okey] call comspec_sse_fnc_bioProfile) getOrDefault ["fingers", []];
                    private _pool = [0, 1, 2, 5, 6, 7] select { ((_fingers param [_x, ["", "", "normal"]]) select 2) isNotEqualTo "ampute" };
                    private _picked = [];
                    for "_i" from 0 to 4 do {
                        if ((count _picked) < 1 + floor ((["cnt"] call _r) * 2) && {_pool isNotEqualTo []}) then { _picked pushBackUnique (_pool select (floor ((["p" + str _i] call _r) * (count _pool)))); };
                    };
                    // Le sang marque bien les crêtes : NFIQ 2 à 4.
                    private _nfiqs = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
                    { _nfiqs set [_x, 2 + floor ((["q" + str _x] call _r) * 2.99)]; } forEach _picked;
                    if (_picked isEqualTo []) exitWith { ["say", ["Aucune crête exploitable dans le sang."]] call comspec_sse_fnc_bioBloodStain; };
                    private _st = toLower ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["networkState", "offline"]);
                    private _link = toLower (missionNamespace getVariable ["COMSPEC_LinkState", ""]);
                    if (!isNil "comspec_overwatch_connect_fnc_sseLinkState") then { _link = toLower ([] call comspec_overwatch_connect_fnc_sseLinkState); };
                    private _online = _link isEqualTo "linked" || {_link isEqualTo "" && {_st in ["connected", "linked"]}};
                    ["say", [format ["Empreintes sanglantes relevées (%1 doigt(s)). Recherche de l'auteur…", count _picked]]] call comspec_sse_fnc_bioBloodStain;
                    ["latent", createHashMapFromArray [
                        ["ownerKey", _okey], ["ownerUnit", _ounit], ["fingers", _picked], ["nfiqs", _nfiqs],
                        ["label", format ["tache de sang %1", _id]], ["grid", mapGridPosition (ASLToAGL _pos)], ["attempt", _n],
                        ["scope", ["local", "abis"] select _online]
                    ], {
                        params ["_res"];
                        private _lines = [format ["Latente %1 (sang) versée à la base de la mission.", _res getOrDefault ["latentId", "LAT"]]];
                        _lines append ([_res] call comspec_sse_fnc_bioDescribe);
                        ["say", [_lines joinString endl, "SUCCESS"]] call comspec_sse_fnc_bioBloodStain;
                        if (!isNil "comspec_sse_fnc_addJournalEntry") then {
                            ["LATENT", "biometrics", "latent", _lines select 1, _res getOrDefault ["score", 0], "LOCAL"] call comspec_sse_fnc_addJournalEntry;
                        };
                    }] call comspec_sse_fnc_bioRequest;
                }, [_t, _toucher, _prints], _pos] call comspec_sse_fnc_progressAction;
                player playActionNow "Medic";
            };
            case "dna": {
                if !([_u, "dna"] call comspec_sse_fnc_hasEquipment) exitWith {
                    ["say", ["Kit ADN SSE (ou SEEK II / kit médical ACE compatible) requis.", "WARNING"]] call comspec_sse_fnc_bioBloodStain;
                };
                // Qualité : sang frais excellent, puis dégradé par le temps, la pluie (lavé) et la chaleur.
                private _q = round ((92 - (_ageMin * 0.15) - ([0, 35] select _wet) - ([0, 8] select (_kind isEqualTo "drop"))) max 15);
                [10, "Écouvillonnage de la tache de sang...", {
                    params ["_id", "_key", "_src", "_q"];
                    ["dna", createHashMapFromArray [["unit", _src], ["key", _key], ["dna", _q], ["dnaRef", _id]], {
                        ["say", ["Écouvillon de sang expédié au laboratoire : résultat à venir.", "SUCCESS"]] call comspec_sse_fnc_bioBloodStain;
                    }] call comspec_sse_fnc_bioRequest;
                }, [_id, _key, _src, _q], _pos] call comspec_sse_fnc_progressAction;
                player playActionNow "Medic";
            };
        };
    };
};
true
