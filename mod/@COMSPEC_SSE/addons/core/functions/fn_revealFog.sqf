/*
    Fog of war SSE — révèle progressivement selon l'action.
    [_entity, _action, _quality] call comspec_sse_fnc_revealFog
    Retourne un HashMap {title, lines, quality, level}
*/
params [
    ["_entity", objNull, [objNull]],
    ["_action", "inspect", [""]],
    ["_quality", 50, [0]]
];

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith {
    createHashMapFromArray [
        ["title", "SSE"],
        ["lines", ["Aucune donnée SSE sur cette cible."]],
        ["quality", 0],
        ["level", "none"]
    ]
};

// Lazy generation au premier examen
if (!([_data, "lazyReady", false] call BIS_fnc_getFromPairs)) then {
    if (!isNil "comspec_sse_fnc_ensureGenerated") then {
        [_entity] call comspec_sse_fnc_ensureGenerated;
        _data = [_entity] call comspec_sse_fnc_getData;
    };
};

private _type = [_data, "type", "OBJECT"] call BIS_fnc_getFromPairs;
private _uid = [_data, "uid", "?"] call BIS_fnc_getFromPairs;
private _identity = [_entity, "identity"] call comspec_sse_fnc_getSection;
private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
private _actionL = toLower _action;
private _lines = [];
private _level = "surface";

switch (_actionL) do {
    case "inspect": {
        _level = "surface";
        private _desc = switch (_type) do {
            case "PERSON": { "Sujet humain — traces et effets personnels possibles." };
            case "PHONE";
            case "SMARTPHONE": { "Appareil mobile — état à confirmer." };
            case "COMPUTER";
            case "LAPTOP": { "Support informatique." };
            case "DOCUMENT": { "Document papier." };
            case "VEHICLE": { "Véhicule — plaques, habitacle et soute à examiner." };
            case "RADIO": { "Radio / satcom — fréquences et trafic possibles." };
            case "WEAPON": { "Armement — marquages et provenance à relever." };
            case "BUILDING": { "Bâtiment / structure — indices d'occupation." };
            case "CONTAINER": { "Conteneur / caisse — contenu potentiellement sensible." };
            case "MEDIA": { "Support de stockage numérique." };
            default { format ["Objet de type %1.", _type] };
        };
        _lines pushBack _desc;
        if (!isNil "_identity" && {_identity isEqualType createHashMap}) then {
            private _alias = _identity getOrDefault ["alias", ""];
            if (_alias != "") then {
                _lines pushBack format ["Indication terrain : connu sous « %1 ».", _alias];
            };
            private _plate = _identity getOrDefault ["plate", ""];
            if (_plate != "") then {
                _lines pushBack format ["Plaque apparente : %1", _plate];
            };
        };
    };
    case "search";
    case "examine": {
        _level = "search";
        if (!isNil "_identity" && {_identity isEqualType createHashMap}) then {
            private _name = _identity getOrDefault ["name", ""];
            if (_name != "") then { _lines pushBack format ["Identité apparente : %1", _name]; };
            private _nat = _identity getOrDefault ["nationality", ""];
            if (_nat != "") then { _lines pushBack format ["Nationalité déclarée : %1", _nat]; };
        };
        if (!isNil "_devices" && {_devices isEqualType []} && {count _devices > 0}) then {
            private _d = _devices select 0;
            _lines pushBack format ["Support numérique détecté (%1).", _d getOrDefault ["deviceType", "DEVICE"]];
            if ((_d getOrDefault ["sim", ""]) != "") then {
                _lines pushBack "SIM présente.";
            };
        };
        private _docs = [_entity, "documents"] call comspec_sse_fnc_getSection;
        if (!isNil "_docs" && {_docs isEqualType []} && {count _docs > 0}) then {
            _lines pushBack format ["Documents trouvés : %1", count _docs];
        };
        private _veh = [_entity, "vehicle"] call comspec_sse_fnc_getSection;
        if (!isNil "_veh" && {_veh isEqualType createHashMap}) then {
            _lines pushBack format ["Véhicule : %1", _veh getOrDefault ["summary", "?"]];
            private _cargo = _veh getOrDefault ["cargoNotes", []];
            if (count _cargo > 0) then {
                _lines pushBack format ["Indices soute : %1", (_cargo select 0)];
            };
        };
        private _radio = [_entity, "radio"] call comspec_sse_fnc_getSection;
        if (!isNil "_radio" && {_radio isEqualType createHashMap}) then {
            _lines pushBack format ["Radio : %1 (%2)", _radio getOrDefault ["model", "?"], _radio getOrDefault ["netName", "?"]];
        };
        private _wpn = [_entity, "weapon"] call comspec_sse_fnc_getSection;
        if (!isNil "_wpn" && {_wpn isEqualType createHashMap}) then {
            _lines pushBack format ["Arme : %1", _wpn getOrDefault ["summary", "?"]];
        };
        private _site = [_entity, "site"] call comspec_sse_fnc_getSection;
        if (!isNil "_site" && {_site isEqualType createHashMap}) then {
            _lines pushBack format ["Occupation : %1", _site getOrDefault ["occupancyHint", "?"]];
            private _traces = _site getOrDefault ["traces", []];
            if (count _traces > 0) then {
                private _t0 = _traces select 0;
                if (_t0 isEqualType createHashMap) then {
                    _lines pushBack format ["%1 — %2", _t0 getOrDefault ["area", ""], _t0 getOrDefault ["note", ""]];
                };
            };
        };
    };
    case "extract";
    case "exploit": {
        _level = "extract";
        if (!isNil "_devices" && {_devices isEqualType []} && {count _devices > 0}) then {
            private _d = _devices select 0;
            private _contacts = _d getOrDefault ["contacts", []];
            private _sms = _d getOrDefault ["sms", []];
            private _photos = _d getOrDefault ["photos", []];
            private _locs = _d getOrDefault ["locations", []];
            _lines pushBack format ["Contacts récupérés : %1", count _contacts];
            _lines pushBack format ["Messages : %1", count _sms];
            _lines pushBack format ["Images : %1", count _photos];
            _lines pushBack format ["Positions : %1", count _locs];
            if (_quality >= 70 && {count _contacts > 0}) then {
                private _shown = _contacts select [0, (count _contacts) min 5];
                _lines pushBack format ["Contacts (extrait) : %1", _shown joinString ", "];
            };
            if (_quality >= 80 && {count _sms > 0}) then {
                private _m = _sms select 0;
                if (_m isEqualType createHashMap) then {
                    _lines pushBack format ["SMS : « %1 »", _m getOrDefault ["text", ""]];
                } else {
                    if (_m isEqualType "") then { _lines pushBack format ["SMS : « %1 »", _m]; };
                };
            };
        };
        if (!isNil "_identity" && {_identity isEqualType createHashMap} && {_quality >= 60}) then {
            {
                private _k = _x;
                private _v = _identity getOrDefault [_k, ""];
                if (_v isEqualType "" && {_v != ""}) then {
                    _lines pushBack format ["%1 : %2", toUpper _k, _v];
                };
            } forEach ["name", "alias", "nationality", "role", "phone"];
        };
    };
    default {
        _lines pushBack format ["Action « %1 » — observation limitée.", _action];
    };
};

        // Mémoriser ce qui a été révélé au joueur local
private _advLines = [];
if (!isNil "comspec_sse_fnc_advanceExploitation" && {_actionL in ["inspect", "search", "examine"]}) then {
    // Inspect démarre au moins Tactical
    if (([_entity] call comspec_sse_fnc_getExploitationLevel) == "NONE") then {
        private _adv = [_entity, player] call comspec_sse_fnc_advanceExploitation;
        _advLines = _adv getOrDefault ["lines", []];
    };
};
{ _lines pushBack _x } forEach _advLines;

if (count _lines == 0) then {
    _lines pushBack "Rien d'exploitable à ce niveau d'examen.";
};

// Mémoriser ce qui a été révélé au joueur local
private _revealed = [_data, "revealed", createHashMap] call BIS_fnc_getFromPairs;
if !(_revealed isEqualType createHashMap) then { _revealed = createHashMap; };
private _prev = _revealed getOrDefault [_actionL, []];
_revealed set [_actionL, _lines];
_data = [_data, ["revealed", _revealed]] call BIS_fnc_setToPairs;
[_entity, _data, false] call comspec_sse_fnc_setData;

createHashMapFromArray [
    ["title", format ["SSE — %1", _uid]],
    ["uid", _uid],
    ["type", _type],
    ["lines", _lines],
    ["quality", _quality],
    ["qualityLabel", [_quality] call comspec_sse_fnc_qualityLabel],
    ["level", _level]
]
