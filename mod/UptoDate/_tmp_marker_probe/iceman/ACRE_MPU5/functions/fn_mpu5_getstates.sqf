params ["_radioId", "_event", "_eventData", "_radioData"];

[
    ["channels", _radioData getVariable ["channels", []]],
    ["currentChannel", _radioData getVariable ["currentChannel", 0]],
    ["radioOn", _radioData getVariable ["radioOn", 1]],
    ["volume", _radioData getVariable ["volume", 0.8]],
    ["spatial", _radioData getVariable ["ACRE_INTERNAL_RADIOSPATIALIZATION", 0]]
]
