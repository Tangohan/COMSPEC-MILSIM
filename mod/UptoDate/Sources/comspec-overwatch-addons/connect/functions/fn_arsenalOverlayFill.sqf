/*
    Redessine le panneau Tenues Athena depuis les données chargées : onglets (avec compteurs),
    liste de collections, liste filtrée (recherche + collection), état et actions selon l'onglet.
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};
private _tab = _grp getVariable ["COMSPEC_ArsenalTab", "cloud"];
private _cloud = _grp getVariable ["COMSPEC_ArsenalCloudRows", []];
private _local = _grp getVariable ["COMSPEC_ArsenalLocalRows", []];
private _isCloud = _tab isEqualTo "cloud";
private _rows = [_local, _cloud] select _isCloud;
private _collIdx = [2, 3] select _isCloud;

// Onglets.
(_grp getVariable ["COMSPEC_ArsenalTabs", []]) params [["_tc", controlNull], ["_tl", controlNull]];
{
    _x params ["_c", "_on", "_label"];
    if (!isNull _c) then {
        _c ctrlSetText _label;
        _c ctrlSetBackgroundColor ([[0.10, 0.17, 0.20, 1], [0.16, 0.48, 0.38, 1]] select _on);
    };
} forEach [[_tc, _isCloud, format ["COMMUNAUTÉ (%1)", count _cloud]], [_tl, !_isCloud, format ["MES TENUES (%1)", count _local]]];

// Collections de l'onglet, la sélection est gardée si elle existe encore.
private _combo = _grp getVariable ["COMSPEC_ArsenalCombo", controlNull];
private _cur = if (isNull _combo || {(lbCurSel _combo) < 0}) then { "" } else { _combo lbData (lbCurSel _combo) };
if ((_grp getVariable ["COMSPEC_ArsenalComboTab", ""]) isNotEqualTo _tab) then { _cur = ""; };
_grp setVariable ["COMSPEC_ArsenalComboTab", _tab];
private _counts = createHashMap;
{ private _k = _x select _collIdx; _counts set [_k, (_counts getOrDefault [_k, 0]) + 1]; } forEach _rows;
private _keys = keys _counts;
_keys sort true;
if (!isNull _combo) then {
    _grp setVariable ["COMSPEC_ArsenalUiLock", true];
    lbClear _combo;
    private _i0 = _combo lbAdd format ["Toutes les collections (%1)", count _rows];
    _combo lbSetData [_i0, ""];
    private _sel = 0;
    { private _i = _combo lbAdd format ["%1 (%2)", _x, _counts get _x]; _combo lbSetData [_i, _x]; if (_x isEqualTo _cur) then { _sel = _i; }; } forEach _keys;
    _combo lbSetCurSel _sel;
    _grp setVariable ["COMSPEC_ArsenalUiLock", false];
    if (_sel isEqualTo 0) then { _cur = ""; };
};

// Filtre : collection puis recherche (plusieurs mots, tous requis).
private _search = _grp getVariable ["COMSPEC_ArsenalSearch", controlNull];
private _q = if (isNull _search) then { "" } else { toLower trim (ctrlText _search) };
private _ph = _grp getVariable ["COMSPEC_ArsenalSearchPh", controlNull];
if (!isNull _ph) then { _ph ctrlShow (_q isEqualTo ""); };
private _words = _q splitString " ";
private _shown = _rows select {
    private _r = _x;
    (_cur isEqualTo "" || {(_r select _collIdx) isEqualTo _cur})
    && { private _hay = toLower ([_r select 1, _r select _collIdx, [_r param [4, ""], ""] select !_isCloud] joinString " "); (_words findIf { (_hay find _x) < 0 }) < 0 }
};
if (_isCloud) then {
    _shown = [_shown, [], { format ["%1|%2|%3", if ((_x param [6, false]) isEqualTo true) then { "0" } else { "1" }, toLower (_x param [3, ""]), toLower (_x param [2, ""])] }, "ASCEND"] call BIS_fnc_sortBy;
} else {
    _shown = [_shown, [], { format ["%1|%2", toLower (_x select 2), toLower (_x select 1)] }, "ASCEND"] call BIS_fnc_sortBy;
};
_grp setVariable ["COMSPEC_ArsenalShown", _shown];

private _list = _grp getVariable ["COMSPEC_ArsenalList", controlNull];
if (!isNull _list) then {
    _grp setVariable ["COMSPEC_ArsenalUiLock", true];
    lnbClear _list;
    {
        private _r = if (_isCloud) then {
            // Favori : étoile devant le nom (jamais de « select » sur une valeur qui peut manquer : affichait « any »).
            private _star = if ((_x param [6, false]) isEqualTo true) then { "★ " } else { "" };
            _list lnbAddRow [_star + (_x param [2, ""]), _x param [3, ""], _x param [4, ""]]
        } else {
            _list lnbAddRow [_x select 1, _x select 2, ""]
        };
        _list lnbSetValue [[_r, 0], _forEachIndex];
        _list lnbSetColor [[_r, 1], [0.55, 0.68, 0.64, 1]];
        _list lnbSetColor [[_r, 2], [0.55, 0.68, 0.64, 1]];
    } forEach _shown;
    if ((count _shown) isEqualTo 0) then {
        private _msg = switch (true) do {
            case (_isCloud && {_grp getVariable ["COMSPEC_ArsenalOffline", false]}): { "Hors ligne : reliez votre compte Athena (ATAK, app Athena)" };
            case ((count _rows) isEqualTo 0): { ["Aucune tenue dans votre arsenal : enregistrez la tenue portée en bas", "Aucune tenue partagée par l'organisation"] select _isCloud };
            default { "Aucune tenue ne correspond à la recherche" };
        };
        private _r = _list lnbAddRow [_msg, "", ""];
        _list lnbSetValue [[_r, 0], -1];
        _list lnbSetColor [[_r, 0], [0.55, 0.62, 0.6, 1]];
    };
    _grp setVariable ["COMSPEC_ArsenalUiLock", false];
};

// État, actions et aide selon l'onglet.
private _status = _grp getVariable ["COMSPEC_ArsenalStatus", controlNull];
if (!isNull _status) then {
    _status ctrlSetStructuredText parseText format ["<t align='right' size='0.8' color='#7f9c94'>%1<br/>%2 affichée(s) sur %3</t>",
        if (_grp getVariable ["COMSPEC_ArsenalOffline", false]) then { "<t color='#e0a050'>Athena hors ligne</t>" } else { format ["Chargé à %1", _grp getVariable ["COMSPEC_ArsenalLoadedAt", "--:--"]] },
        count _shown, count _rows];
};
private _bulk = _grp getVariable ["COMSPEC_ArsenalBulk", controlNull];
if (!isNull _bulk) then {
    _bulk ctrlSetText format [["PARTAGER %1 TENUE(S) AFFICHÉE(S)", "IMPORTER %1 TENUE(S) AFFICHÉE(S)"] select _isCloud, count _shown];
    _bulk ctrlEnable ((count _shown) > 0);
};
_grp setVariable ["COMSPEC_ArsenalSelected", -1];
[_display, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
[_grp, ["Mes tenues : cliquez pour l'aperçu, double-clic pour enfiler, puis Partager pour la donner à l'organisation.",
    "Communauté : cliquez pour l'aperçu, double-clic pour enfiler ; Importer la garde dans votre arsenal ACE."] select _isCloud] call (_grp getVariable ["COMSPEC_ArsenalSetHint", {}]);
