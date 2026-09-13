/*
    Prépare le rédacteur de fiche : listes déroulantes, brouillon conservé,
    date et repère du moment, puis affichage du volet de rédaction.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };
if (isNull _disp) exitWith {};

[] call comspec_overwatch_connect_fnc_intelNoteApplyGeometry;

// Mémoire des champs de la fiche précédente : elle ne doit pas déteindre.
["clear"] call comspec_overwatch_connect_fnc_intelNoteCache;

private _catalog = [] call comspec_overwatch_connect_fnc_intelNoteCatalog;
private _kinds = _catalog getOrDefault ["kinds", []];
private _urgencies = _catalog getOrDefault ["urgencies", []];
private _sources = _catalog getOrDefault ["sources", []];

// --- Type de fiche ---
private _kindCombo = _disp displayCtrl 9656;
lbClear _kindCombo;
{
    _x params ["_code", "_label"];
    private _i = _kindCombo lbAdd _label;
    _kindCombo lbSetData [_i, _code];
    _kindCombo lbSetTooltip [_i, _x param [2, ""]];
} forEach _kinds;

// --- Urgence ---
private _urgencyCombo = _disp displayCtrl 9657;
lbClear _urgencyCombo;
{
    _x params ["_code", "_label"];
    private _i = _urgencyCombo lbAdd _label;
    _urgencyCombo lbSetData [_i, _code];
} forEach _urgencies;

// --- Recueil ---
private _sourceCombo = _disp displayCtrl 9658;
if (!isNull _sourceCombo) then {
    lbClear _sourceCombo;
    private _none = _sourceCombo lbAdd "Non précisé";
    _sourceCombo lbSetData [_none, ""];
    {
        _x params ["_code", "_label"];
        private _i = _sourceCombo lbAdd (_code + " — " + _label);
        _sourceCombo lbSetData [_i, _code];
    } forEach _sources;
};

// --- Brouillon : [texte, lieu, type, urgence, thèmes, recueil] ---
private _draft = profileNamespace getVariable ["COMSPEC_IntelNote_Draft", []];
if (!(_draft isEqualType [])) then { _draft = []; };
private _draftBody = _draft param [0, "", [""]];
private _draftPlace = _draft param [1, "", [""]];
private _draftKind = _draft param [2, "FRM", [""]];
private _draftUrgency = toLower (_draft param [3, "routine", [""]]);
if (_draftUrgency isEqualTo "immediate") then { _draftUrgency = "critique"; };
if (_draftUrgency isEqualTo "priorite") then { _draftUrgency = "urgent"; };
private _draftThemes = _draft param [4, [], [[]]];
private _draftSource = toUpper (_draft param [5, "", [""]]);

// Type demandé à l’ouverture (menu dédié, action ACE) : prioritaire sur le brouillon.
private _pendingKind = uiNamespace getVariable ["COMSPEC_IntelNote_PendingKind", ""];
if ((_pendingKind isEqualType "") && {_pendingKind isNotEqualTo ""}) then {
    _draftKind = _pendingKind;
    uiNamespace setVariable ["COMSPEC_IntelNote_PendingKind", ""];
};

private _kindIndex = 0;
{
    if ((_x select 0) isEqualTo _draftKind) exitWith { _kindIndex = _forEachIndex; };
} forEach _kinds;
_kindCombo lbSetCurSel _kindIndex;

private _urgencyIndex = 0;
{
    if ((_x select 0) isEqualTo _draftUrgency) exitWith { _urgencyIndex = _forEachIndex; };
} forEach _urgencies;
_urgencyCombo lbSetCurSel _urgencyIndex;

if (!isNull _sourceCombo) then {
    private _sourceIndex = 0;
    if (_draftSource isNotEqualTo "") then {
        for "_i" from 0 to (lbSize _sourceCombo - 1) do {
            if ((_sourceCombo lbData _i) isEqualTo _draftSource) exitWith { _sourceIndex = _i; };
        };
    };
    _sourceCombo lbSetCurSel _sourceIndex;
};

// Thèmes valides seulement : un référentiel qui évolue ne doit pas ressortir
// des codes inconnus d’un vieux brouillon. Les anciens intitulés sont traduits.
private _themes = _catalog getOrDefault ["themes", []];
private _themeCodes = _themes apply { _x select 0 };
private _legacyThemes = createHashMapFromArray [
    ["securite_publique", "SECUR"],
    ["menace_armee", "INSURG"],
    ["engins_explosifs", "CBRNE"],
    ["ordre_public", "INSURG"],
    ["trafics", "FINANCE"],
    ["mouvements", "MOUV"],
    ["population", "CIVIL"],
    ["infrastructures", "INFRA"],
    ["communications", "COMMS"],
    ["logistique", "LOGIST"],
    ["environnement", "METEO"],
    ["divers", "GENERAL"]
];
private _selected = [];
{
    if (!(_x isEqualType "")) then { continue };
    private _code = _legacyThemes getOrDefault [toLower _x, toUpper _x];
    if ((_code in _themeCodes) && {!(_code in _selected)}) then {
        _selected pushBack _code;
    };
} forEach _draftThemes;
uiNamespace setVariable ["COMSPEC_IntelNote_Themes", _selected];
uiNamespace setVariable ["COMSPEC_IntelNote_Pieces", []];

// --- Texte, lieu, date, repère ---
(_disp displayCtrl 9616) ctrlSetText _draftBody;
(_disp displayCtrl 9653) ctrlSetText _draftPlace;

// L’heure de mission est la référence : c’est elle que lira l’analyste.
private _pad = {
    params ["_n"];
    if (_n < 10) then { format ["0%1", floor _n] } else { str (floor _n) }
};
date params ["_year", "_month", "_day", "_hour", "_minute"];
(_disp displayCtrl 9652) ctrlSetText format [
    "%1/%2/%3 %4:%5",
    [_day] call _pad,
    [_month] call _pad,
    _year,
    [_hour] call _pad,
    [_minute] call _pad
];

private _grid = mapGridPosition player;
(_disp displayCtrl 9654) ctrlSetText _grid;

private _lastCase = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase;
if (_lastCase isEqualType "") then {
    (_disp displayCtrl 9655) ctrlSetText _lastCase;
};

// Compteur vivant : sans handler, l’opérateur écrit à l’aveugle et découvre la
// limite en perdant la fin de sa phrase.
(_disp displayCtrl 9616) ctrlAddEventHandler ["KeyUp", {
    [] call comspec_overwatch_connect_fnc_intelNoteRefresh;
}];

// Tous les contrôles sont encore visibles à ce stade : c'est le bon moment pour
// amorcer la mémoire, avant que le premier volet n'en masque une partie.
["capture"] call comspec_overwatch_connect_fnc_intelNoteCache;

['redaction'] call comspec_overwatch_connect_fnc_intelNotePane;
ctrlSetFocus (_disp displayCtrl 9616);
