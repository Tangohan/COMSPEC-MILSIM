/*
    Choix du téléphone et lecture du téléphone d'un autre. Seule page permise tant qu'un téléphone d'autrui est affiché
    (fn_navigate, COMSPEC_ATAK_PhoneActive) : ni lanceur, ni dock, ni app. Elle ne montre que la fiche et les données
    renvoyées par le serveur (fn_phoneServer), en lecture seule, et n'appelle jamais Athena : le compte du lecteur
    n'est jamais utilisé sous un autre nom.
      "*PICK*" : mon téléphone (si j'en ai un) et ceux d'autrui.
      id       : code demandé si ce téléphone en a un (fn_phonePad), sinon son contenu.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _active = ["active"] call comspec_atak_native_fnc_phoneOwn;
private _foreign = ["foreign"] call comspec_atak_native_fnc_phoneOwn;
private _hasOwn = [player] call comspec_atak_native_fnc_hasDevice;
private _icon = "\z\comspec_atak_native\addons\main\data\app_profile.paa";
private _label = {
    params ["_cls"];
    private _n = getText (configFile >> "CfgWeapons" >> _cls >> "displayName");
    [_n, _cls] select (_n isEqualTo "")
};
private _rows = [];
private _close = ["RANGER", { uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false]; [] call comspec_atak_native_fnc_close; }];
private _other = ["AUTRE TÉLÉPHONE", { ["close"] call comspec_atak_native_fnc_phonePad; missionNamespace setVariable ["COMSPEC_ATAK_PhoneActive", "*PICK*"]; ["PHONES"] call comspec_atak_native_fnc_navigate; }];

if (_active isEqualTo "*PICK*") exitWith {
    ["close"] call comspec_atak_native_fnc_phonePad;
    _rows pushBack ["section", "Quel téléphone ouvrir ?", "Vous portez plusieurs téléphones"];
    if (_hasOwn) then {
        _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold'>%1</t>", "Mon téléphone"], [["OUVRIR", { ["pick", ""] call comspec_atak_native_fnc_phoneOwn; }, true]]];
    };
    {
        _x params ["_id", "_cls", "", "_name"];
        _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold'>Téléphone de %1</t><br/><t size='0.8'>%2</t>", [_name] call _esc, [[_cls] call _label] call _esc],
            [["OUVRIR", compile format ["['pick', %1] call comspec_atak_native_fnc_phoneOwn;", str _id], false]]];
    } forEach _foreign;
    _rows pushBack ["buttons", [_close]];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

private _rec = ["activeRec"] call comspec_atak_native_fnc_phoneOwn;
if (_rec isEqualTo []) exitWith {
    [[["empty", _icon, "Ce téléphone n'est plus sur vous", ""], ["buttons", [_close]]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
_rec params ["_id", "_cls", "", "_owner"];
private _view = uiNamespace getVariable ["COMSPEC_ATAK_PhoneView", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_PhoneView", _view];
private _several = _hasOwn || {(count _foreign) > 1};
if ((_view getOrDefault ["id", ""]) isNotEqualTo _id) then {
    _view set ["data", createHashMap];
    _view set ["tab", "ID"];
    _view set ["confirm", false];
    ["try", ""] call comspec_atak_native_fnc_phoneOwn;
};
private _status = _view getOrDefault ["status", "WAIT"];
_rows pushBack ["section", format ["Téléphone de %1", [_owner] call _esc], "Lecture seule : uniquement ce que contient ce téléphone"];
switch (_status) do {
    case "WAIT": { _rows pushBack ["text", "Lecture du téléphone…"]; };
    case "NONE": {
        _rows pushBack ["text", "Le téléphone ne répond pas : le serveur n'a pas renvoyé son contenu."];
        _rows pushBack ["buttons", [["RÉESSAYER", { ["try", ""] call comspec_atak_native_fnc_phoneOwn; ["PHONES"] call comspec_atak_native_fnc_pageRender; }]]];
    };
    case "LOCKED": { _rows pushBack ["text", "Ce téléphone est verrouillé."]; };
    default {
        private _data = _view getOrDefault ["data", createHashMap];
        if (_data getOrDefault ["wiped", false]) then {
            _rows pushBack ["text", "Ce téléphone a été effacé : plus rien à lire."];
        } else {
            private _tab = _view getOrDefault ["tab", "ID"];
            private _seg = {
                params ["_t", "_k"];
                [_t, compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_PhoneView') set ['tab', %1]; ['PHONES'] call comspec_atak_native_fnc_pageRender;", str _k], _tab isEqualTo _k]
            };
            _rows pushBack ["segment", "", [["FICHE", "ID"] call _seg, ["MESSAGES", "MSG"] call _seg, ["NOTES", "NOTES"] call _seg, ["REPÈRES", "MARKS"] call _seg]];
            private _dash = { params ["_v"]; if (_v isEqualTo "") then { "—" } else { _v } };
            switch (_tab) do {
                case "MSG": {
                    private _msgs = _data getOrDefault ["msgs", []];
                    if (_msgs isEqualTo []) then { _rows pushBack ["text", "Aucun message."]; };
                    { _x params [["_at", ""], ["_who", ""], ["_body", ""]];
                        _rows pushBack ["text", format ["<t size='0.8'>%1 · %2</t><br/>%3", [_at] call _esc, [_who] call _esc, [_body] call _esc]];
                    } forEach _msgs;
                };
                case "NOTES": {
                    private _notes = _data getOrDefault ["notes", []];
                    if (_notes isEqualTo []) then { _rows pushBack ["text", "Aucune note."]; };
                    { _x params [["_t", ""], ["_b", ""]];
                        _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/>%2", [_t] call _esc, [_b] call _esc]];
                    } forEach _notes;
                };
                case "MARKS": {
                    private _marks = _data getOrDefault ["marks", []];
                    if (_marks isEqualTo []) then { _rows pushBack ["text", "Aucun repère."]; };
                    { _x params [["_g", ""], ["_t", ""]]; _rows pushBack ["info", _g, _t]; } forEach _marks;
                };
                default {
                    _rows pushBack ["info", "Modèle", [_cls] call _label];
                    _rows pushBack ["info", "Numéro", [_data getOrDefault ["number", ""]] call _dash];
                    _rows pushBack ["info", "IMEI", [_data getOrDefault ["imei", ""]] call _dash];
                    _rows pushBack ["info", "Indicatif", [_data getOrDefault ["callsign", ""]] call _dash];
                    _rows pushBack ["info", "Données figées à", [_data getOrDefault ["at", ""]] call _dash];
                    if ((count _data) isEqualTo 0) then { _rows pushBack ["text", "Mémoire non lue : son propriétaire a quitté la partie sans qu'elle soit écrite."]; };
                };
            };
        };
        // Effacer pour le garder : sinon un joueur sans autre téléphone ne pourrait pas s'en servir.
        private _armed = _view getOrDefault ["confirm", false];
        _rows pushBack ["buttons", [[["EFFACER ET GARDER", "CONFIRMER : EFFACER"] select _armed,
            [{ private _v = uiNamespace getVariable ["COMSPEC_ATAK_PhoneView", createHashMap]; _v set ["confirm", true]; ["PHONES"] call comspec_atak_native_fnc_pageRender; },
             { ["claim"] call comspec_atak_native_fnc_phoneOwn; }] select _armed,
            [false, "red"] select _armed, true, "Efface ses données : ce téléphone devient le vôtre"]]];
    };
};
_rows pushBack ["buttons", ([[_other], []] select !_several) + [_close]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
if (_status isEqualTo "LOCKED") then { ["padForeign"] call comspec_atak_native_fnc_phoneOwn; } else { ["close"] call comspec_atak_native_fnc_phonePad; };
true
