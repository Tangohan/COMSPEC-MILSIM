params [["_talkgroup", 1], ["_frequency", 32]];

private _tg = (round _talkgroup) max 1 min 16;
private _channel = call acre_main_fnc_fastHashCreate;

_channel setVariable ["mode", "singleChannel"];
_channel setVariable ["frequencyTX", _frequency];
_channel setVariable ["frequencyRX", _frequency];
_channel setVariable ["power", 5000];
_channel setVariable ["encryption", 0];
_channel setVariable ["channelMode", "BASIC"];
_channel setVariable ["description", format ["TG%1", _tg]];
_channel setVariable ["Iceman_WR_talkgroup", _tg];
_channel setVariable ["Iceman_WR_frequencyBank", str (round (_frequency * 10) / 10)];
_channel setVariable ["CTCSSTx", 0];
_channel setVariable ["CTCSSRx", 0];
_channel setVariable ["modulation", "FM"];
_channel setVariable ["TEK", 1];
_channel setVariable ["trafficRate", 16];
_channel setVariable ["syncLength", 256];
_channel setVariable ["phase", 256];
_channel setVariable ["squelch", 0];
_channel setVariable ["deviation", 8.0];
_channel setVariable ["optionCode", 201];
_channel setVariable ["rxOnly", false];
_channel setVariable ["Iceman_ROIP_enabled", false];

_channel
