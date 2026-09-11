/*
    Formate un message structuré radio.
    Canaux système + custom (clé dynamique).
*/
params [
    ["_author", "Unknown"],
    ["_channel", "SQUAD"],
    ["_priority", "ROUTINE"],
    ["_text", ""],
    ["_kind", "FREE"]
];

private _validPriority = ["ROUTINE", "IMPORTANT", "URGENT", "CONTACT"];
private _chRaw = toUpper (trim _channel);
if (_chRaw isEqualTo "") then { _chRaw = "SQUAD"; };

// Alias métier → jeton radio
private _alias = createHashMapFromArray [
    ["GROUPE", "GROUPE"],
    ["GROUP", "GROUPE"],
    ["GENERAL", "SQUAD"],
    ["COMMANDEMENT", "COMMAND"],
    ["HQ", "COMMAND"],
    ["C2", "COMMAND"],
    ["COMMAND", "COMMAND"],
    ["SQUAD", "SQUAD"],
    ["GLOBAL", "SQUAD"],
    ["JTAC", "JTAC"],
    ["AIR", "AIR"]
];
private _channelTok = _alias getOrDefault [_chRaw, ""];
if (_channelTok isEqualTo "") then {
    // Custom : garder alphanum, max 12
    private _clean = "";
    private _i = 0;
    private _chars = toArray _chRaw;
    {
        if ((_x >= 48 && {_x <= 57}) || {(_x >= 65 && {_x <= 90})} || {_x == 95}) then {
            _clean = _clean + (toString [_x]);
        };
    } forEach _chars;
    if (_clean isEqualTo "") then { _clean = "SQUAD"; };
    if ((count _clean) > 12) then { _clean = _clean select [0, 12]; };
    _channelTok = _clean;
};

if !(_priority in _validPriority) then { _priority = "ROUTINE"; };

private _stamp = [floor (serverTime / 3600), floor ((serverTime mod 3600) / 60), floor (serverTime mod 60)] apply {
    if (_x < 10) then { format ["0%1", _x] } else { str _x };
};
private _t = _stamp joinString ":";

format ["[%1][%2][%3][%4] %5", _t, _channelTok, _priority, _kind, _text]
