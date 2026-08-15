params ["_channel"];

private _data = call acre_main_fnc_fastHashCreate;

_data setVariable ["mode", _channel getVariable ["mode", "singleChannel"]];
_data setVariable ["frequencyTX", _channel getVariable ["frequencyTX", 32]];
_data setVariable ["frequencyRX", _channel getVariable ["frequencyRX", 32]];
_data setVariable ["power", _channel getVariable ["power", 5000]];
_data setVariable ["CTCSSTx", _channel getVariable ["CTCSSTx", 0]];
_data setVariable ["CTCSSRx", _channel getVariable ["CTCSSRx", 0]];
_data setVariable ["modulation", _channel getVariable ["modulation", "FM"]];
_data setVariable ["encryption", _channel getVariable ["encryption", 0]];
_data setVariable ["TEK", _channel getVariable ["TEK", 1]];
_data setVariable ["trafficRate", _channel getVariable ["trafficRate", 16]];
_data setVariable ["syncLength", _channel getVariable ["syncLength", 256]];
_data setVariable ["optionCode", _channel getVariable ["optionCode", 201]];
_data setVariable ["rxOnly", _channel getVariable ["rxOnly", false]];
_data setVariable ["squelch", _channel getVariable ["squelch", 0]];
_data setVariable ["channelMode", _channel getVariable ["channelMode", "BASIC"]];
_data setVariable ["deviation", _channel getVariable ["deviation", 8.0]];
_data setVariable ["description", _channel getVariable ["description", "TG"]];
_data setVariable ["Iceman_WR_talkgroup", _channel getVariable ["Iceman_WR_talkgroup", -1]];
_data setVariable ["Iceman_WR_frequencyBank", _channel getVariable ["Iceman_WR_frequencyBank", ""]];
_data setVariable ["Iceman_Bridge_enabled", _channel getVariable ["Iceman_Bridge_enabled", false]];
_data setVariable ["Iceman_Bridge_radioClass", _channel getVariable ["Iceman_Bridge_radioClass", ""]];
_data setVariable ["Iceman_Bridge_channelIndex", _channel getVariable ["Iceman_Bridge_channelIndex", -1]];
_data setVariable ["Iceman_Bridge_channelLabel", _channel getVariable ["Iceman_Bridge_channelLabel", ""]];
_data setVariable ["Iceman_Bridge_recordId", _channel getVariable ["Iceman_Bridge_recordId", ""]];
_data setVariable ["Iceman_Bridge_legacyPower", _channel getVariable ["Iceman_Bridge_legacyPower", _channel getVariable ["power", 5000]]];
_data setVariable ["Iceman_Bridge_effectivePower", _channel getVariable ["Iceman_Bridge_effectivePower", _channel getVariable ["power", 5000]]];
_data setVariable ["Iceman_WR_bridgeEnabled", _channel getVariable ["Iceman_WR_bridgeEnabled", false]];
_data setVariable ["Iceman_ROIP_enabled", _channel getVariable ["Iceman_ROIP_enabled", false]];
_data setVariable ["Iceman_ROIP_linkId", _channel getVariable ["Iceman_ROIP_linkId", ""]];
_data setVariable ["Iceman_ROIP_gatewayRadioId", _channel getVariable ["Iceman_ROIP_gatewayRadioId", ""]];
_data setVariable ["Iceman_ROIP_gatewayOwner", _channel getVariable ["Iceman_ROIP_gatewayOwner", ""]];
_data setVariable ["Iceman_ROIP_gatewayOwnerUID", _channel getVariable ["Iceman_ROIP_gatewayOwnerUID", ""]];
_data setVariable ["Iceman_ROIP_radioClass", _channel getVariable ["Iceman_ROIP_radioClass", ""]];
_data setVariable ["Iceman_ROIP_channelNumber", _channel getVariable ["Iceman_ROIP_channelNumber", -1]];
_data setVariable ["Iceman_ROIP_channelLabel", _channel getVariable ["Iceman_ROIP_channelLabel", ""]];
_data setVariable ["Iceman_ROIP_legacyPower", _channel getVariable ["Iceman_ROIP_legacyPower", _channel getVariable ["power", 5000]]];
_data setVariable ["Iceman_ROIP_effectivePower", _channel getVariable ["Iceman_ROIP_effectivePower", _channel getVariable ["power", 5000]]];

_data
