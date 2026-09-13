/*
    Visage réellement utilisé par l’unité (classname + texture config si disponible).
    Ne plante pas si la texture n’est pas récupérable.
    Params: [_unit]
    Retour: HashMap
*/
params [["_unit", objNull, [objNull]]];

private _out = createHashMap;
_out set ["face_class", ""];
_out set ["face_display", ""];
_out set ["face_texture", ""];
_out set ["face_material", ""];
_out set ["head", ""];
_out set ["identity_types", []];
_out set ["speaker", ""];
_out set ["pitch", -1];

if (isNull _unit) exitWith { _out };

private _face = "";
try {
    _face = face _unit;
} catch {
    _face = "";
};
if (!(_face isEqualType "")) then { _face = str _face; };
_face = trim _face;
if (_face in ["", "Default", "default"]) then {
    private _idFace = _unit getVariable ["BIS_identityFace", ""];
    if (_idFace isEqualType "" && {trim _idFace isNotEqualTo ""}) then { _face = trim _idFace; };
};
_out set ["face_class", _face];

if (_face isEqualTo "") exitWith { _out };

private _cfg = configNull;
private _direct = configFile >> "CfgFaces" >> "Man_A3" >> _face;
if (isClass _direct) then {
    _cfg = _direct;
} else {
    {
        private _c = _x >> _face;
        if (isClass _c) exitWith { _cfg = _c };
    } forEach ("true" configClasses (configFile >> "CfgFaces"));
};

if (isClass _cfg) then {
    _out set ["face_display", getText (_cfg >> "displayName")];
    _out set ["face_texture", getText (_cfg >> "texture")];
    _out set ["face_material", getText (_cfg >> "material")];
    _out set ["head", getText (_cfg >> "head")];
    private _types = getArray (_cfg >> "identityTypes");
    if (_types isEqualType []) then {
        _out set ["identity_types", _types select { _x isEqualType "" }];
    };
};

try {
    private _spk = speaker _unit;
    if (_spk isEqualType "") then { _out set ["speaker", _spk]; };
} catch {};
try {
    private _pt = pitch _unit;
    if (_pt isEqualType 0) then { _out set ["pitch", _pt]; };
} catch {};

_out
