#include "..\script_component.hpp"

private _group = uiNamespace getVariable ["Iceman_ATAK_Group_group", controlNull];
if (isNull _group) exitWith {};

private _historyGroup = controlNull;
{
    switch (ctrlIDC _x) do {
        case 9902: {_historyGroup = _x};
    };
} forEach allControls _group;

if (isNull _historyGroup) exitWith {};

{
    if (_x getVariable ["IcemanGroupBubble", false]) then {
        ctrlDelete _x;
    };
} forEach allControls _historyGroup;

private _escape = {
    params [["_text", ""]];
    if !(_text isEqualType "") then {
        _text = str _text;
    };

    private _out = [];
    {
        switch (_x) do {
            case 38: {_out append (toArray "&amp;")};
            case 60: {_out append (toArray "&lt;")};
            case 62: {_out append (toArray "&gt;")};
            default {_out pushBack _x};
        };
    } forEach toArray _text;
    toString _out
};

private _messages = missionNamespace getVariable ["Iceman_ATAK_Group_messages", []];
private _historyPos = ctrlPosition _historyGroup;
private _historyW = _historyPos # 2;
private _gap = (_historyPos # 3) * 0.01;
private _bubbleW = _historyW * 0.74;
private _y = _gap;

if (_messages isEqualTo []) exitWith {
    private _empty = (ctrlParent _group) ctrlCreate ["Iceman_ReportsDetailText", 9910, _historyGroup];
    _empty setVariable ["IcemanGroupCtrl", true];
    _empty setVariable ["IcemanGroupBubble", true];
    _empty ctrlSetPosition [_gap, _gap, _historyW - (_gap * 4), 0.2];
    _empty ctrlSetStructuredText parseText "<t align='center' color='#9aa4aa'>No group messages yet.</t>";
    _empty ctrlCommit 0;
};

private _receiver = missionNamespace getVariable ["cTab_player", player];
private _receiverName = if (isNull _receiver) then {name player} else {name _receiver};

for "_i" from 0 to ((count _messages) - 1) do {
    (_messages # _i) params ["_time", "_sender", "_groupId", "_grid", "_text", "_pos", ["_isMine", false]];
    _isMine = _isMine || {_sender == _receiverName};

    private _x = [_gap, (_historyW - _bubbleW - (_gap * 3)) max _gap] select _isMine;
    private _speaker = ["<t color='#aeb8bf'>%1</t>", "<t color='#cfeeff'>You</t>"] select _isMine;
    private _body = [_text] call _escape;
    private _meta = format [_speaker + " <t color='#7f8a91'>%2  %3</t>", _sender, _time, _grid];
    private _align = ["left", "right"] select _isMine;
    private _bubbleText = format ["<t align='%1' size='0.72'>%2</t><br/><t align='%1' size='0.9' color='#ffffff'>%3</t>", _align, _meta, _body];

    private _bubble = (ctrlParent _group) ctrlCreate ["Iceman_ReportsDetailText", 9910 + _i, _historyGroup];
    _bubble setVariable ["IcemanGroupCtrl", true];
    _bubble setVariable ["IcemanGroupBubble", true];
    _bubble ctrlSetBackgroundColor ([[0.10, 0.12, 0.13, 0.95], [0.05, 0.24, 0.35, 0.95]] select _isMine);
    _bubble ctrlSetPosition [_x, _y, _bubbleW, 0.18];
    _bubble ctrlSetStructuredText parseText _bubbleText;
    _bubble ctrlCommit 0;

    private _bubblePos = ctrlPosition _bubble;
    private _neededH = ((ctrlTextHeight _bubble) + 0.025) max 0.12;
    _bubble ctrlSetPosition [_bubblePos # 0, _bubblePos # 1, _bubblePos # 2, _neededH];
    _bubble ctrlCommit 0;
    _y = _y + _neededH + (_gap * 2);
};

_historyGroup spawn {
    uiSleep 0.1;
    _this ctrlSetScrollValues [0, -1];
};
