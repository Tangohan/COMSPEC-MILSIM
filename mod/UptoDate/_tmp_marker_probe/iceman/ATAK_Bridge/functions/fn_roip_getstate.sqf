if (isNil "Iceman_ROIP_state") then {
    Iceman_ROIP_state = createHashMapFromArray [
        ["radioSelection", 0],
        ["selectedRadioId", ""],
        ["tgSelection", profileNamespace getVariable ["Iceman_ROIP_lastTG", 1]],
        ["lastRadios", []],
        ["connectedRadioId", ""],
        ["connectedTalkgroup", 0],
        ["localLink", []],
        ["lastPublishedSignature", ""],
        ["appliedSignature", "__INIT__"],
        ["activeLinks", []],
        ["uiRadioSignature", ""],
        ["uiTgSignature", ""],
        ["updating", false]
    ];
};

Iceman_ROIP_state
