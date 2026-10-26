/*
    Importe les variables BII_Identifi_* d’une unité vers le record SSE.
    [_entity] call comspec_sse_fnc_biiImportEntityVars
*/
params [["_entity", objNull, [objNull]]];

if (isNull _entity) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith { false };
if !([] call comspec_sse_fnc_biiIsPresent) exitWith { false };

private _name = _entity getVariable ["BII_Identifi_name", ""];
private _alias = _entity getVariable ["BII_Identifi_alias", ""];
private _nat = _entity getVariable ["BII_Identifi_nationality", ""];
private _org = _entity getVariable ["BII_Identifi_org", ""];
private _threat = _entity getVariable ["BII_Identifi_threat", ""];
private _family = _entity getVariable ["BII_Identifi_family", ""];
private _associates = _entity getVariable ["BII_Identifi_associates", ""];
private _leads = _entity getVariable ["BII_Identifi_leads", ""];
private _notes = _entity getVariable ["BII_Identifi_notes", ""];
private _bioKey = _entity getVariable ["BII_Identifi_bioKey", ""];
private _watchlist = _entity getVariable ["BII_Identifi_watchlist", false];

if (
    _name isEqualTo ""
    && {_alias isEqualTo ""}
    && {_nat isEqualTo ""}
    && {_org isEqualTo ""}
    && {_bioKey isEqualTo ""}
) exitWith { false };

// Éviter la récursion avec le wrap ensureGenerated → import → ensureGenerated
private _data0 = [_entity] call comspec_sse_fnc_getData;

// ensureGenerated relance le pont à chaque rafraîchissement d'UI : sans changement des BII_Identifi_* (ni de fiche),
// on ne réimporte pas (sinon commit public de toute la fiche + variables de pont rediffusées à chaque passage).
private _sig = [
    if (isNil "_data0" || {!(_data0 isEqualType [])}) then { "" } else { [_data0, "uid", ""] call comspec_sse_fnc_getPair },
    _name, _alias, _nat, _org, _threat, _family, _associates, _leads, _notes, _bioKey, _watchlist
];
if (
    _entity getVariable ["comspec_sse_biiImported", false]
    && {(_entity getVariable ["comspec_sse_biiImportSig", []]) isEqualTo _sig}
) exitWith { false };

if (isNil "_data0") then {
    [_entity, "PERSON"] call comspec_sse_fnc_makeSearchable;
};

private _pairs = [];
if (_name isNotEqualTo "") then { _pairs pushBack ["name", _name]; };
if (_alias isNotEqualTo "") then { _pairs pushBack ["alias", _alias]; };
if (_nat isNotEqualTo "") then { _pairs pushBack ["nationality", _nat]; };
if (_org isNotEqualTo "") then {
    _pairs pushBack ["organization", _org];
    _pairs pushBack ["role", _org];
};
// Fiche montée en local (_public = false) puis une seule écriture publique à la fin : chaque écriture publique
// diffusait la fiche entière (7 à 8 fois par unité, pour toutes les unités BII au démarrage du serveur).
if (_pairs isNotEqualTo []) then {
    [_entity, _pairs, false] call comspec_sse_fnc_setIdentity;
};

if (_threat isNotEqualTo "") then {
    private _profile = [_threat] call comspec_sse_fnc_biiThreatToProfile;
    private _data = [_entity] call comspec_sse_fnc_getData;
    if (!isNil "_data" && {_data isEqualType []}) then {
        _data = [_data, "profile", _profile] call comspec_sse_fnc_setPair;
        [_entity, _data, false] call comspec_sse_fnc_setData;
    };
};

private _bio = [_entity, "biometrics"] call comspec_sse_fnc_getSection;
if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
if (_bioKey isNotEqualTo "") then {
    _bio set ["biiBioKey", _bioKey];
    if ((_bio getOrDefault ["fingerprintId", ""]) isEqualTo "") then {
        _bio set ["fingerprintId", _bioKey];
    };
};
_bio set ["biiWatchlist", _watchlist];
[_entity, "biometrics", _bio, false] call comspec_sse_fnc_setSection;

private _intel = [_entity, "intel"] call comspec_sse_fnc_getSection;
if (isNil "_intel" || {!(_intel isEqualType createHashMap)}) then { _intel = createHashMap; };
if (_family isNotEqualTo "") then { _intel set ["family", _family]; };
if (_associates isNotEqualTo "") then { _intel set ["associates", _associates]; };
if (_leads isNotEqualTo "") then { _intel set ["leads", _leads]; };
if (_notes isNotEqualTo "") then { _intel set ["biiNotes", _notes]; };
_intel set ["source", "BII"];
[_entity, "intel", _intel, false] call comspec_sse_fnc_setSection;

if (_associates isNotEqualTo "" || {_family isNotEqualTo ""}) then {
    private _assoc = [_entity, "associations"] call comspec_sse_fnc_getSection;
    if (isNil "_assoc" || {!(_assoc isEqualType [])}) then { _assoc = []; };
    if (_family isNotEqualTo "") then { _assoc pushBackUnique format ["Famille: %1", _family]; };
    if (_associates isNotEqualTo "") then { _assoc pushBackUnique format ["Associés: %1", _associates]; };
    [_entity, "associations", _assoc, false] call comspec_sse_fnc_setSection;
};

private _final = [_entity] call comspec_sse_fnc_getData;
if (!isNil "_final") then {
    [_entity, _final, true] call comspec_sse_fnc_setData;
    // Fiche née ici : comspec_sse_enabled déjà posé en local par les écritures locales, commitData ne le diffuse plus.
    if (isServer && {isNil "_data0"}) then { _entity setVariable ["comspec_sse_enabled", true, true]; };
};
// Variables de pont (nom Eden / SEEK) posées en local par setIdentity : diffusées une fois, fiche déjà publiée.
if (_pairs isNotEqualTo []) then {
    if (_name isNotEqualTo "") then { _entity setVariable ["COMSPEC_SSE_NameAuthored", true, true]; };
    if (!isNil "comspec_sse_fnc_syncIdentityBridgeVars") then { [_entity, true] call comspec_sse_fnc_syncIdentityBridgeVars; };
};

// Signature locale (non diffusée) : uid de la fiche telle qu'elle existe après l'import.
private _dataS = [_entity] call comspec_sse_fnc_getData;
_sig set [0, if (isNil "_dataS" || {!(_dataS isEqualType [])}) then { "" } else { [_dataS, "uid", ""] call comspec_sse_fnc_getPair }];
_entity setVariable ["comspec_sse_biiImportSig", _sig];
if !(_entity getVariable ["comspec_sse_biiImported", false]) then { _entity setVariable ["comspec_sse_biiImported", true, true]; };
true
