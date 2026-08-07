/*
    Scripts de test SSE — console debug Arma :
    [] execVM "z\comspec_sse\addons\..\..\tools\test_scenarios.sqf";
    ou copier le contenu dans la console.

    Activer les logs :
    comspec_sse_debug = true;
*/

diag_log "[COMSPEC SSE] === TEST SUITE V0.1 ===";
comspec_sse_debug = true;

// Scenario 1 — personne avec identité
private _g1 = createGroup civilian;
private _u1 = _g1 createUnit ["C_man_1", player modelToWorld [0, 3, 0], [], 0, "NONE"];
[_u1, "INSURGENT", "DETAILED", "TEST"] call comspec_sse_fnc_generateData;
private _id1 = [_u1, "identity"] call comspec_sse_fnc_getSection;
diag_log format ["S1 identity=%1", _id1];

// Scenario 2 — téléphone avec messages
private _phone = createVehicle ["Land_MobilePhone_smart_F", player modelToWorld [2, 3, 0], [], 0, "CAN_COLLIDE"];
_phone setVariable ["comspec_sse_forcedType", "PHONE", true];
[_phone, "INSURGENT", "DETAILED", "TEST"] call comspec_sse_fnc_generateData;
private _sum = [_phone] call comspec_sse_fnc_getDeviceSummary;
diag_log format ["S2 phone summary=%1", _sum];

// Scenario 3 — objet document lié
private _doc = createVehicle ["Land_File1_F", player modelToWorld [3, 3, 0], [], 0, "CAN_COLLIDE"];
[_doc, "DOCUMENT", "INSURGENT", "STANDARD"] call comspec_sse_fnc_makeSearchable;
[_doc, "INSURGENT", "STANDARD", "TEST"] call comspec_sse_fnc_generateData;
[_phone, _u1, "OWNER", 0.9, "TEST"] call comspec_sse_fnc_linkEntities;
[_doc, _phone, "REFERENCES", 0.7, "TEST"] call comspec_sse_fnc_linkEntities;
diag_log format ["S3 links=%1", count ([_u1] call comspec_sse_fnc_getLinks)];

// Scenario 4 — site / cellule
private _u2 = _g1 createUnit ["C_man_polo_1_F", player modelToWorld [5, 5, 0], [], 0, "NONE"];
private _u3 = _g1 createUnit ["C_man_polo_2_F", player modelToWorld [6, 5, 0], [], 0, "NONE"];
private _site = [player modelToWorld [5, 5, 0], 15, "INSURGENT", "DETAILED"] call comspec_sse_fnc_generateSite;
diag_log format ["S4 site entities=%1", count _site];

// Scenario 5 — HVT biométrie
private _hvt = _g1 createUnit ["C_man_p_fugitive_F", player modelToWorld [-3, 3, 0], [], 0, "NONE"];
[_hvt, "COMMANDER", "HIGH_VALUE", "TEST"] call comspec_sse_fnc_generateData;
private _bio = [_hvt, "biometrics"] call comspec_sse_fnc_getSection;
diag_log format ["S5 bio=%1", _bio];

// Scenario 6 — collecte + transmission
private _fog = [_u1, "extract", 87] call comspec_sse_fnc_revealFog;
[
    _fog get "uid",
    "digital",
    "person",
    name player,
    getPosATL player,
    87,
    _fog
] call comspec_sse_fnc_submitRecord;
diag_log "S6 submit done";

// Scenario 7 — offline queue
missionNamespace setVariable ["COMSPEC_AthenaReady", false];
[
    "SSE-TEST-OFFLINE",
    "test",
    "offline",
    name player,
    getPosATL player,
    50,
    createHashMapFromArray [["note", "offline test"]]
] call comspec_sse_fnc_submitRecord;
diag_log format ["S7 queue=%1", count (missionNamespace getVariable ["comspec_sse_txQueue", []])];

// Scenario 8 — seed déterministe
private _seed = [_u1] call comspec_sse_fnc_getSeed;
private _a = [_seed, "INSURGENT", "DETAILED", createHashMap] call comspec_sse_fnc_generatePerson;
private _b = [_seed, "INSURGENT", "DETAILED", createHashMap] call comspec_sse_fnc_generatePerson;
private _same = ((_a get "identity") get "name") isEqualTo ((_b get "identity") get "name");
diag_log format ["S8 deterministic=%1 name=%2", _same, (_a get "identity") get "name"];

// Scenario 9 — double exploitation (état partagé)
["setstate", _phone, ["PARTIALLY_EXPLOITED"]] call comspec_sse_fnc_requestServerOp;
diag_log format ["S9 state=%1", [_phone] call comspec_sse_fnc_getState];

// Scenario 10 — regen type Zeus
[_u1, "FINANCIER", "LIGHT", "ZEUS"] call comspec_sse_fnc_generateData;
private _d10 = [_u1] call comspec_sse_fnc_getData;
diag_log format ["S10 regen profile=%1", [_d10, "profile", "?"] call BIS_fnc_getFromPairs];

hint "COMSPEC SSE tests terminés — voir RPT.";
diag_log "[COMSPEC SSE] === TEST SUITE DONE ===";
