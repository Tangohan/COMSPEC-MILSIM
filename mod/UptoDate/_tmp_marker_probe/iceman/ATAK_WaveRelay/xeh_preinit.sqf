[
    "Iceman_WR_showRadioPopup",
    "CHECKBOX",
    ["Show TX/RX Pop-up", "Show the small MPU-5 TX/RX indicator when transmitting or receiving."],
    ["Iceman ATAK", "Wave Relay"],
    true
] call CBA_fnc_addSetting;

[
    "Iceman_WR_rangeM",
    "SLIDER",
    ["Node Link Range", "Maximum simulated one-hop Wave Relay link distance in meters."],
    ["Iceman ATAK", "Wave Relay"],
    [1000, 10000, 3000, 0]
] call CBA_fnc_addSetting;

[
    "Iceman_WR_spikeRangeM",
    "SLIDER",
    ["Spike Amplifier Range", "One-hop range when an ACRE VHF30108 ground spike or mast participates in the link."],
    ["Iceman ATAK", "Wave Relay"],
    [1000, 12000, 5000, 0]
] call CBA_fnc_addSetting;

[
    "Iceman_WR_baseMbps",
    "SLIDER",
    ["Base Throughput", "Point-to-point simulated throughput before hop loss."],
    ["Iceman ATAK", "Wave Relay"],
    [10, 150, 100, 0]
] call CBA_fnc_addSetting;

[
    "Iceman_WR_hopLossFactor",
    "SLIDER",
    ["Hop Loss Factor", "Throughput multiplier applied for each relay hop after the first."],
    ["Iceman ATAK", "Wave Relay"],
    [0.25, 0.95, 0.50, 2]
] call CBA_fnc_addSetting;

[
    "Iceman_WR_videoMbps",
    "SLIDER",
    ["Video Stream Cost", "Estimated bandwidth cost of a subscribed camera feed."],
    ["Iceman ATAK", "Wave Relay"],
    [1, 25, 8, 0]
] call CBA_fnc_addSetting;
