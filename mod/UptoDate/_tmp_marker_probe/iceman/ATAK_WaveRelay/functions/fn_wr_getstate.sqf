if (isNil "Iceman_WR_state") then {
    Iceman_WR_state = createHashMapFromArray [
        ["tab", "home"],
        ["selection", 0],
        ["frequency", "32.0"],
        ["profileName", "Default"],
        ["activeTalkgroup", 1],
        ["txSlots", [1, 0, 0, 0]],
        ["txTalkgroups", [1]],
        ["txEditSlot", 1],
        ["monitorTalkgroups", [1, 2]],
        ["monitorAudio", [[1, "BOTH"], [2, "BOTH"]]],
        ["monitorVolume", [[1, 1], [2, 1]]],
        ["txSlot", 1],
        ["txKeysDown", []],
        ["ctabPttActive", []],
        ["pttKeyBindings", []],
        ["acrePttSlot", -1],
        ["acreChannelSignature", ""],
        ["freqBanks", []],
        ["subscriptions", []],
        ["gateway", false],
        ["lastNodes", []],
        ["lastFeeds", []],
        ["lastFeedInfo", []],
        ["lastHealthRows", []],
        ["updating", false]
    ];
};

Iceman_WR_state
