/*
    Panneau Zeus : identité SSE (personne ou équipage).
    Params: [_obj, _delay]
*/
params [
    ["_obj", objNull, [objNull]],
    ["_delay", 0],
    ["_retried", false]
];
if (!hasInterface) exitWith { false };
if (_delay > 0 && {!isNil "CBA_fnc_waitAndExecute"}) exitWith {
    [{ [_this, 0] call comspec_overwatch_connect_fnc_zeusAttributesSse }, _obj, _delay] call CBA_fnc_waitAndExecute;
    true
};

private _unit = [_obj] call comspec_overwatch_connect_fnc_zeusAttributesPerson;
if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith {
    ["SSE : sélectionnez une personne, ou un véhicule avec un équipage.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

private _presets = ["list"] call comspec_overwatch_connect_fnc_sseProfilePreset;
private _labels = ["labels"] call comspec_overwatch_connect_fnc_sseProfilePreset;
private _alias = _unit getVariable ["COMSPEC_SSE_Alias", ""];
private _last = _unit getVariable ["COMSPEC_SSE_LastName", ""];
private _first = _unit getVariable ["COMSPEC_SSE_FirstName", ""];
private _nat = _unit getVariable ["COMSPEC_SSE_Nationality", ""];
private _lang = _unit getVariable ["COMSPEC_SSE_Language", ""];
private _ref = _unit getVariable ["COMSPEC_SSE_RecordRef", ""];
private _giveSeek = isPlayer _unit;

if (isNil "zen_dialog_fnc_create") exitWith {
    if (_giveSeek) then {
        [_unit, "COMSPEC_Item_SeekTerminal"] remoteExecCall ["comspec_overwatch_connect_fnc_giveSeekTerminal", _unit];
        [format ["Terminal de recueil transmis à %1.", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    } else {
        ["Zeus Enhanced est nécessaire pour régler l’identité ici.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    true
};

private _fields = [
    ["LIST", ["Ce que la base doit répondre", "Génération automatique : verdict stable dérivé de la personne."], [_presets, _labels, 0]],
    ["EDIT", ["Alias connu", "Souvent le seul élément dont dispose le terrain."], _alias],
    ["EDIT", ["Nom", "Vide = inchangé / génération automatique."], _last],
    ["EDIT", ["Prénom", "Vide = inchangé / génération automatique."], _first],
    ["EDIT", ["Nationalité déclarée", "Ce que la personne déclare."], _nat],
    ["EDIT", ["Langue parlée", "Pour savoir si un interprète est nécessaire."], _lang],
    ["EDIT", ["Dossier antérieur", "Affiché en cas de correspondance. Vide = automatique."], _ref]
];
if (_giveSeek) then {
    _fields pushBack ["CHECKBOX", ["Remettre un terminal de recueil", "La personne pourra ouvrir une fiche d’identité."], false];
};

private _opened = [
    format ["SSE — %1", name _unit],
    _fields,
    {
        params ["_values", "_args"];
        _args params ["_unit", "_giveSeek"];
        _values params ["_preset", "_alias", "_last", "_first", "_nat", "_lang", "_ref", ["_doSeek", false]];
        private _profile = [_preset] call comspec_overwatch_connect_fnc_sseProfilePreset;
        {
            _x params ["_key", "_v"];
            if ((trim _v) isNotEqualTo "") then { _profile pushBack [_key, trim _v]; };
        } forEach [
            ["alias", _alias],
            ["last_name", _last],
            ["first_name", _first],
            ["nationality", _nat],
            ["language", _lang],
            ["record_ref", _ref]
        ];
        [_unit, _profile] remoteExecCall ["comspec_overwatch_connect_fnc_sseApplyProfile", 2];
        if (_giveSeek && {_doSeek}) then {
            [_unit, "COMSPEC_Item_SeekTerminal"] remoteExecCall ["comspec_overwatch_connect_fnc_giveSeekTerminal", _unit];
        };
        [format ["Identité SSE réglée pour %1.", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    },
    {},
    [_unit, _giveSeek]
] call zen_dialog_fnc_create;

if (_opened isEqualTo false) exitWith {
    if (!_retried && {!isNil "CBA_fnc_waitAndExecute"}) then {
        [{ [_this, 0, true] call comspec_overwatch_connect_fnc_zeusAttributesSse }, _obj, 0.45] call CBA_fnc_waitAndExecute;
    } else {
        ["Fenêtre SSE indisponible — fermez l’édition puis réessayez, ou utilisez le menu Zeus clic droit.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        systemChat "[COMSPEC] SSE : la fenêtre ne s’est pas ouverte. Fermez l’édition puis recliquez.";
    };
    true
};
true
