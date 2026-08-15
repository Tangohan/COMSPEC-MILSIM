params [["_feed", []], ["_pinMap", false]];

if !(_feed isEqualType [] && {(count _feed) >= 4}) exitWith {false};

private _obj = [_feed] call Iceman_fnc_wr_feedObject;
if (isNull _obj || {!alive _obj}) exitWith {
    ["WAVE RELAY", "Feed source is not available.", 2] call cTab_fnc_addNotification;
    false
};

private _type = _feed # 1;
private _data = str _obj;

if (_type == "helmet") then {
    ["cTab_Android_dlg", [["hcam", _data]], false] call cTab_fnc_setSettings;
    player setVariable ["TGP_View_Selected_Optic", [[], objNull], true];
    if !(isNil "BCE_fnc_set_TaskCurUnit") then {
        [objNull, "AIR" call BCE_fnc_get_TaskCateIndex] call BCE_fnc_set_TaskCurUnit;
    };
} else {
    if !(isNil "BCE_fnc_set_TaskCurUnit") then {
        [_obj, "AIR" call BCE_fnc_get_TaskCateIndex] call BCE_fnc_set_TaskCurUnit;
    };
    ["cTab_Android_dlg", [["hcam", ""]], false] call cTab_fnc_setSettings;
};

private _displayName = "cTab_Android_dlg";
private _settings = [_displayName, "showMenu"] call cTab_fnc_getSettings;
if !(_settings isEqualType [] && {(count _settings) >= 4}) then {
    _settings = ["VideoFeeds", false, ["", -1], createHashMap];
};
_settings set [0, "VideoFeeds"];

private _components = _settings param [3, createHashMap];
private _pageData = _components getOrDefault ["VideoFeeds", []];
_pageData set [0, 0];
_pageData set [1, [0, 1] select (_type == "helmet")];
_components set ["VideoFeeds", _pageData];
_settings set [3, _components];

uiNamespace setVariable ["BCE_ATAK_VideoFeed_FullATAK", false];
uiNamespace setVariable ["BCE_ATAK_VideoFeed_MapATAK", _pinMap];
[_displayName, [["showMenu", _settings]], true, true] call cTab_fnc_setSettings;

if (_pinMap && {!(isNil "Iceman_fnc_ATAK_setMapFeedOverlay")}) then {
    [true] call Iceman_fnc_ATAK_setMapFeedOverlay;
};

if !(isNil "BCE_fnc_cTab_UpdateInterface") then {
    "showMenu" call BCE_fnc_cTab_UpdateInterface;
};

["WAVE RELAY", format ["%1 %2.", ["Viewing", "Pinned"] select _pinMap, _feed # 0], 2] call cTab_fnc_addNotification;
true
