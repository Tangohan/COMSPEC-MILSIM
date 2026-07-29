/*
    Retourne le libellé d'un marqueur BCE / Marker Dropper à partir des listes cTab.
    Params: [_markerName] — ex. _USER_DEFINED #0/1/-1/0/0/3
*/
params [["_markerName", "", [""]]];

private _result = "";
if (_markerName isEqualTo "") exitWith { _result };

private _ul = toLower _markerName;
if ((_ul find "_defined #") < 0) exitWith { _result };

private _hashParts = _markerName splitString "#";
if ((count _hashParts) < 2) exitWith { _result };

private _seg = (_hashParts select 1) splitString "/";
if ((count _seg) < 2) exitWith { _result };

private _markerId = parseNumber (_seg select 1);
if (_markerId < 0) exitWith { _result };

private _extractText = {
    params ["_entry", "_wantedId"];
    if (!(_entry isEqualType []) || {(count _entry) < 2}) exitWith { "" };
    private _id = _entry select 0;
    private _idMatch = false;
    if (_id isEqualType 0) then {
        _idMatch = (_id isEqualTo _wantedId);
    } else {
        _idMatch = (str _id isEqualTo str _wantedId);
    };
    if (!_idMatch) exitWith { "" };

    private _data = _entry select 1;
    if (!(_data isEqualType [])) exitWith { "" };

    // Liste traduite cTab : texte en index 5
    private _pos0 = _data select 0;
    if (_pos0 isEqualType [] && {(count _pos0) >= 2} && {(_pos0 select 0) isEqualType 0}) then {
        if ((count _data) > 5) then {
            private _t = _data select 5;
            if (_t isEqualType "") exitWith { trim _t };
        };
        if ((count _data) > 4) then {
            private _t2 = _data select 4;
            if (_t2 isEqualType "") exitWith { trim _t2 };
        };
        exitWith { "" };
    };

    // Brut cTab : [pos, iconIdx, sizeIdx, dir, text, creator?]
    if ((count _data) > 4) then {
        private _rawText = _data select 4;
        if (_rawText isEqualType "") exitWith { trim _rawText };
    };
    ""
};

private _lists = [];
if (!isNil "cTabUserMarkerList") then { _lists pushBack cTabUserMarkerList; };
_lists pushBack (missionNamespace getVariable ["Iceman_ATAK_UserMarkers", []]);
_lists pushBack (missionNamespace getVariable ["cTab_userMarkerList", []]);
_lists pushBack (uiNamespace getVariable ["cTabUserMarkerList", []]);
_lists pushBack (uiNamespace getVariable ["Iceman_ATAK_UserMarkers", []]);

if (!isNil "cTab_userMarkerLists") then {
    private _pairs = missionNamespace getVariable ["cTab_userMarkerLists", []];
    if (_pairs isEqualType []) then {
        {
            if (!(_x isEqualType []) || {(count _x) < 2}) then { continue };
            private _rawList = _x select 1;
            if (_rawList isEqualType []) then { _lists pushBack _rawList; };
        } forEach _pairs;
    };
};

{
    if (!(_x isEqualType [])) then { continue };
    {
        private _txt = [_x, _markerId] call _extractText;
        if (_txt isNotEqualTo "") exitWith { _result = _txt; };
    } forEach _x;
    if (_result isNotEqualTo "") exitWith {};
} forEach _lists;

_result
