params ["_channel"];

private _copy = call acre_main_fnc_fastHashCreate;

{
    _copy setVariable [_x, _channel getVariable _x];
} forEach [
    "mode",
    "frequencyTX",
    "frequencyRX",
    "power",
    "encryption",
    "channelMode",
    "description",
    "CTCSSTx",
    "CTCSSRx",
    "modulation",
    "TEK",
    "trafficRate",
    "syncLength",
    "phase",
    "squelch",
    "deviation",
    "optionCode",
    "rxOnly",
    "Iceman_WR_talkgroup",
    "Iceman_WR_frequencyBank",
    "Iceman_WR_bridgeEnabled",
    "Iceman_Bridge_enabled",
    "Iceman_Bridge_radioClass",
    "Iceman_Bridge_channelIndex",
    "Iceman_Bridge_channelLabel",
    "Iceman_Bridge_recordId",
    "Iceman_Bridge_legacyPower",
    "Iceman_Bridge_effectivePower",
    "Iceman_ROIP_enabled",
    "Iceman_ROIP_linkId",
    "Iceman_ROIP_gatewayRadioId",
    "Iceman_ROIP_gatewayOwner",
    "Iceman_ROIP_gatewayOwnerUID",
    "Iceman_ROIP_radioClass",
    "Iceman_ROIP_channelNumber",
    "Iceman_ROIP_channelLabel",
    "Iceman_ROIP_legacyPower",
    "Iceman_ROIP_effectivePower"
];

_copy
