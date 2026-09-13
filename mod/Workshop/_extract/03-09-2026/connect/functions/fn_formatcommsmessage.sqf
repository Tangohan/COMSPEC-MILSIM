/*
    Formate un message structuré radio.
*/
params [
    ["_author", "Unknown"],
    ["_channel", "SQUAD"],
    ["_priority", "ROUTINE"],
    ["_text", ""],
    ["_kind", "FREE"]
];

private _validChannels = ["GLOBAL", "COMMAND", "SQUAD", "JTAC", "AIR"];
private _validPriority = ["ROUTINE", "IMPORTANT", "URGENT", "CONTACT"];

if !(_channel in _validChannels) then { _channel = "SQUAD"; };
if !(_priority in _validPriority) then { _priority = "ROUTINE"; };

private _stamp = [floor (serverTime / 3600), floor ((serverTime mod 3600) / 60), floor (serverTime mod 60)] apply {
    if (_x < 10) then { format ["0%1", _x] } else { str _x };
};
private _t = _stamp joinString ":";

format ["[%1][%2][%3][%4] %5", _t, _channel, _priority, _kind, _text]
