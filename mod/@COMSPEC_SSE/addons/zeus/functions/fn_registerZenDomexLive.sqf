/*
    Menus Zeus live DOMEX (ZEN) : renseignement, palier, point carte.
    Aucun moteur technique — injection scénarisée uniquement.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["comspec_sse_zenDomexLiveRegistered", false]) exitWith {};

uiNamespace setVariable ["COMSPEC_SSE_DomexResolveZenArgs", {
    params [["_a", objNull], ["_b", []], ["_c", []], ["_d", []], ["_e", []]];
    private _asObject = {
        params ["_v"];
        if (_v isEqualType objNull) exitWith { _v };
        if (!(_v isEqualType []) || {count _v == 0}) exitWith { objNull };
        private _first = _v select 0;
        if (_first isEqualType objNull) then { _first } else { objNull }
    };
    private _asPos = {
        params ["_v"];
        if (_v isEqualType [] && {count _v >= 2} && {(_v select 0) isEqualType 0}) exitWith { _v };
        []
    };
    private _pos = [_a] call _asPos;
    if (_pos isEqualTo []) then { _pos = [_b] call _asPos; };
    if (_pos isEqualTo []) then { _pos = [_e] call _asPos; };
    private _obj = [_b] call _asObject;
    if (isNull _obj) then { _obj = [_a] call _asObject; };
    [_obj, _pos]
}];

private _openAddIntel = {
    params [["_obj", objNull], ["_pos", []]];
    private _entity = [_obj, _pos] call comspec_sse_fnc_domexPickObject;
    if (isNull _entity) exitWith {
        hint "Sélectionnez un objet (ordinateur, téléphone, radio…) — pas une personne.";
    };
    if (isNil "zen_dialog_fnc_create") exitWith {
        hint "Zeus Enhanced est nécessaire pour ce menu.";
    };

    private _types = ["message", "document", "photo", "contact", "coordinate", "frequency", "schedule", "manifest", "objective"];
    private _typeLabs = ["Message", "Document", "Photographie", "Contact", "Coordonnée / point", "Fréquence", "Horaire", "Manifeste", "Objectif"];
    private _qVals = ["complet", "fragment", "leurre_possible"];
    private _qLabs = ["Complet", "Fragment (à croiser)", "Peut être un leurre"];

    [
        format ["Ajouter un renseignement — %1", _entity getVariable ["comspec_sse_domex_nodeId", "support"]],
        [
            ["LIST", ["Type", "Ce que le bureau lira dans la file."], [_types, _typeLabs, 0]],
            ["EDIT", ["Texte", "Renseignement scénarisé. Ce n’est pas une preuve."], ""],
            ["LIST", ["Qualité", "Un fragment ou un leurre devra être corroboré."], [_qVals, _qLabs, 0]],
            ["EDIT", ["Entités (une par ligne)", "Format : Nom | type (lieu, personne, organisation…)."], ""]
        ],
        {
            params ["_values", "_args"];
            _values params ["_type", "_text", "_quality", "_entities"];
            _args params ["_entity"];
            if ((trim _text) isEqualTo "") exitWith {
                hint "Saisissez le texte du renseignement.";
            };
            private _packet = createHashMapFromArray [
                ["type", _type],
                ["packet_type", _type],
                ["text", trim _text],
                ["body_text", trim _text],
                ["quality", _quality],
                ["entities", _entities],
                ["origin", "zeus_live"],
                ["channel", "zeus_live"],
                ["reveal", "immediat"]
            ];
            if (_type isEqualTo "coordinate") then {
                private _p = getPosATL _entity;
                _packet set ["position", _p];
                _packet set ["pos_x", _p select 0];
                _packet set ["pos_y", _p select 1];
                _packet set ["show_on_map", true];
                _packet set ["grid_reference", mapGridPosition _p];
            };
            [_entity, _packet, true] call comspec_sse_fnc_domexAddLivePacket;
            hint "Renseignement ajouté. Il rejoint la file du laboratoire.";
        },
        {},
        [_entity]
    ] call zen_dialog_fnc_create;
};

private _openStage = {
    params [["_obj", objNull], ["_pos", []]];
    private _entity = [_obj, _pos] call comspec_sse_fnc_domexPickObject;
    if (isNull _entity) exitWith {
        hint "Sélectionnez un support numérique (objet), pas une personne.";
    };
    if (isNil "zen_dialog_fnc_create") exitWith {
        hint "Zeus Enhanced est nécessaire pour ce menu.";
    };

    private _stages = ["non_identifie", "decouvert", "acces_en_cours", "acces_etabli", "exploite"];
    private _stageLabs = ["Non identifié", "Découvert", "Accès en cours", "Accès établi", "Exploité"];
    private _cur = _entity getVariable ["comspec_sse_domex_stage", "non_identifie"];
    private _idx = _stages find _cur;
    if (_idx < 0) then { _idx = 0; };

    [
        format ["Palier d’accès — %1", _entity getVariable ["comspec_sse_domex_nodeId", "support"]],
        [
            ["LIST", ["Palier", "Progression scénarisée. Au palier « accès établi », les contenus prévus pour ce palier rejoignent la file."], [_stages, _stageLabs, _idx]]
        ],
        {
            params ["_values", "_args"];
            _values params ["_stage"];
            _args params ["_entity"];
            [_entity, _stage, true] call comspec_sse_fnc_domexSetStage;
            private _labs = createHashMapFromArray [
                ["non_identifie", "Non identifié"],
                ["decouvert", "Découvert"],
                ["acces_en_cours", "Accès en cours"],
                ["acces_etabli", "Accès établi"],
                ["exploite", "Exploité"]
            ];
            hint format ["Palier mis à jour : %1.", _labs getOrDefault [_stage, _stage]];
        },
        {},
        [_entity]
    ] call zen_dialog_fnc_create;
};

private _openMapPoint = {
    params [["_obj", objNull], ["_pos", []]];
    if (!(_obj isEqualType objNull)) then {
        if (_obj isEqualType [] && {count _obj > 0} && {(_obj select 0) isEqualType objNull}) then {
            _obj = _obj select 0;
        } else {
            _obj = objNull;
        };
    };
    if (!(_pos isEqualType []) || {count _pos < 2} || {!((_pos select 0) isEqualType 0)}) then {
        if (_obj isEqualType objNull && {!isNull _obj}) then { _pos = getPosATL _obj; } else { _pos = []; };
    };
    if (!(_pos isEqualType []) || {count _pos < 2} || {!((_pos select 0) isEqualType 0)}) exitWith {
        hint "Posez le module sur la carte, à l’endroit du point.";
    };
    if (isNil "zen_dialog_fnc_create") exitWith {
        [_pos, "Point de renseignement", _obj, "complet"] call comspec_sse_fnc_domexPlaceMapPoint;
        hint "Point posé sur la carte du bureau.";
    };

    private _qVals = ["complet", "fragment", "leurre_possible"];
    private _qLabs = ["Complet", "Fragment (à croiser)", "Peut être un leurre"];
    private _entity = objNull;
    if (_obj isEqualType objNull && {!isNull _obj} && {!(_obj isKindOf "CAManBase")}) then {
        _entity = _obj;
    };

    [
        "Poser un point carte",
        [
            ["EDIT", ["Libellé", "Ce que le bureau verra. Le point n’apparaît pas sur la carte des joueurs."], ""],
            ["LIST", ["Qualité", "Un fragment ou un leurre devra être corroboré."], [_qVals, _qLabs, 0]]
        ],
        {
            params ["_values", "_args"];
            _values params ["_text", "_quality"];
            _args params ["_pos", "_entity"];
            [_pos, _text, _entity, _quality] call comspec_sse_fnc_domexPlaceMapPoint;
            hint "Point posé. Il apparaît sur la carte du bureau, pas sur celle des joueurs.";
        },
        {},
        [_pos, _entity]
    ] call zen_dialog_fnc_create;
};

uiNamespace setVariable ["COMSPEC_SSE_DomexOpenAddIntel", _openAddIntel];
uiNamespace setVariable ["COMSPEC_SSE_DomexOpenStage", _openStage];
uiNamespace setVariable ["COMSPEC_SSE_DomexOpenMapPoint", _openMapPoint];

private _invokeOpener = {
    params ["_key"];
    private _resolved = _this select [1, 5];
    if (_resolved isEqualTo []) then { _resolved = _this };
    _resolved = _resolved call (uiNamespace getVariable ["COMSPEC_SSE_DomexResolveZenArgs", { [objNull, []] }]);
    _resolved params ["_obj", "_pos"];
    [_obj, _pos] call (uiNamespace getVariable [_key, {}]);
};
uiNamespace setVariable ["COMSPEC_SSE_DomexInvokeOpener", _invokeOpener];

if (!isNil "zen_custom_modules_fnc_register") then {
    private _icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";
    [
        "COMSPEC SSE",
        "Ajouter un renseignement",
        {
            (["COMSPEC_SSE_DomexOpenAddIntel"] + _this) call (uiNamespace getVariable ["COMSPEC_SSE_DomexInvokeOpener", {}]);
        },
        _icon
    ] call zen_custom_modules_fnc_register;

    [
        "COMSPEC SSE",
        "Fixer le palier d’accès",
        {
            (["COMSPEC_SSE_DomexOpenStage"] + _this) call (uiNamespace getVariable ["COMSPEC_SSE_DomexInvokeOpener", {}]);
        },
        "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa"
    ] call zen_custom_modules_fnc_register;

    [
        "COMSPEC SSE",
        "Poser un point carte",
        {
            (["COMSPEC_SSE_DomexOpenMapPoint"] + _this) call (uiNamespace getVariable ["COMSPEC_SSE_DomexInvokeOpener", {}]);
        },
        "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa"
    ] call zen_custom_modules_fnc_register;
};

if (!isNil "zen_context_menu_fnc_createAction" && {!isNil "zen_context_menu_fnc_addAction"}) then {
    private _hasObj = {
        private _pool = [] call comspec_sse_fnc_curatorSelectedObjects;
        ({ !(_x isKindOf "CAManBase") } count _pool) > 0
    };
    private _icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";

    private _root = [
        "comspec_sse_domex_live",
        "Intelligence numérique",
        _icon,
        {},
        { true }
    ] call zen_context_menu_fnc_createAction;
    [_root, [], 5] call zen_context_menu_fnc_addAction;

    private _add = [
        "comspec_sse_domex_add",
        "Ajouter un renseignement",
        _icon,
        {
            (["COMSPEC_SSE_DomexOpenAddIntel"] + _this) call (uiNamespace getVariable ["COMSPEC_SSE_DomexInvokeOpener", {}]);
        },
        _hasObj
    ] call zen_context_menu_fnc_createAction;
    [_add, ["comspec_sse_domex_live"], 0] call zen_context_menu_fnc_addAction;

    private _stg = [
        "comspec_sse_domex_stage",
        "Fixer le palier d’accès",
        "",
        {
            (["COMSPEC_SSE_DomexOpenStage"] + _this) call (uiNamespace getVariable ["COMSPEC_SSE_DomexInvokeOpener", {}]);
        },
        _hasObj
    ] call zen_context_menu_fnc_createAction;
    [_stg, ["comspec_sse_domex_live"], 1] call zen_context_menu_fnc_addAction;

    private _pin = [
        "comspec_sse_domex_pin",
        "Poser un point carte",
        "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa",
        {
            (["COMSPEC_SSE_DomexOpenMapPoint"] + _this) call (uiNamespace getVariable ["COMSPEC_SSE_DomexInvokeOpener", {}]);
        },
        { true }
    ] call zen_context_menu_fnc_createAction;
    [_pin, ["comspec_sse_domex_live"], 2] call zen_context_menu_fnc_addAction;
};

missionNamespace setVariable ["comspec_sse_zenDomexLiveRegistered", true];
["ZEN DOMEX live menus registered"] call comspec_sse_fnc_log;
