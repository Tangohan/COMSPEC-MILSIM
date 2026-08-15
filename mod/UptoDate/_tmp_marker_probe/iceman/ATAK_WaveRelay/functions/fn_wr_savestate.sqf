private _state = call Iceman_fnc_wr_getState;
private _keys = ["frequency", "profileName", "activeTalkgroup", "txSlots", "txTalkgroups", "txEditSlot", "monitorTalkgroups", "monitorAudio", "monitorVolume", "txSlot", "freqBanks", "subscriptions", "gateway"];
private _stored = [];
{
    _stored pushBack [_x, _state getOrDefault [_x, ""]];
} forEach _keys;

profileNamespace setVariable ["Iceman_WR_profile", _stored];
saveProfileNamespace;
true
