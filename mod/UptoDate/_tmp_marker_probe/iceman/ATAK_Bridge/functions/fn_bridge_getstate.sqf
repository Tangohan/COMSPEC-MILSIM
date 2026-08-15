if (isNil "Iceman_Bridge_state") then {
    Iceman_Bridge_state = createHashMapFromArray [
        ["radioClass", "ACRE_PRC152"],
        ["selection", 0],
        ["lastChannels", []],
        ["txChannels", []],
        ["monitorChannels", []],
        ["activeRecord", []],
        ["txKeysDown", []],
        ["acrePttSlot", -1],
        ["acrePreviousPttAssignment", []],
        ["updating", false]
    ];
};

Iceman_Bridge_state
