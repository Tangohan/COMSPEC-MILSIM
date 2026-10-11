/*
    Balise IR intégrée à la coque : clignotant infrarouge, invisible à l'œil nu, vu par tous aux JVN (identification
    ami). Alimentée par le téléphone (fn_battery). Accrochée au téléphone du gilet s'il y est, sinon à l'épaule gauche.
    Effet : stroboscope IR d'ACE si chargé, sinon celui du jeu (B_IRStrobe), recréé s'il s'éteint.
    Params : [action]  "toggle" | "on" | "off" | "tick" (client, toutes les 2 s) | "state"
*/
params [["_act", "tick"]];
private _on = missionNamespace getVariable ["COMSPEC_ATAK_IrOn", false];
private _cur = missionNamespace getVariable ["COMSPEC_ATAK_IrObj", objNull];
private _kill = { if (!isNull _cur) then { deleteVehicle _cur; }; missionNamespace setVariable ["COMSPEC_ATAK_IrObj", objNull]; };
switch (_act) do {
    case "state": { _on };
    case "toggle": { [["on", "off"] select _on] call comspec_atak_native_fnc_irBeacon };
    case "on": {
        missionNamespace setVariable ["COMSPEC_ATAK_IrOn", true];
        playSound "ClickSoft";
        ["INFO", "Balise IR allumée (visible aux JVN)", 3, 20] call comspec_atak_native_fnc_notify;
        ["tick"] call comspec_atak_native_fnc_irBeacon;
        true
    };
    case "off": {
        missionNamespace setVariable ["COMSPEC_ATAK_IrOn", false];
        call _kill;
        playSound "ClickSoft";
        ["INFO", "Balise IR éteinte", 3, 20] call comspec_atak_native_fnc_notify;
        false
    };
    case "tick": {
        private _want = _on && {alive player} && {isNull objectParent player} && {[player] call comspec_atak_native_fnc_hasDevice}
            && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) > 0};
        if (!_want) exitWith { call _kill; false };
        private _vest = !isNull (missionNamespace getVariable ["COMSPEC_ATAK_VestObj", objNull]);
        private _key = [player, _vest];
        if (isNull _cur || {(missionNamespace getVariable ["COMSPEC_ATAK_IrKey", []]) isNotEqualTo _key}) then {
            call _kill;
            private _cls = ["B_IRStrobe", "ACE_IR_Strobe_Effect"] select (isClass (configFile >> "CfgAmmo" >> "ACE_IR_Strobe_Effect"));
            _cur = _cls createVehicle (getPosATL player);
            if (_vest) then {
                private _depth = profileNamespace getVariable ["COMSPEC_ATAK_VestDepth", 0.19];
                private _x = switch (profileNamespace getVariable ["COMSPEC_ATAK_VestMount", "off"]) do { case "left": { -0.12 }; case "right": { 0.12 }; default { 0 }; };
                _cur attachTo [player, [_x - 0.07, _depth + 0.02, 0.09], "spine3", true];
            } else {
                _cur attachTo [player, [0, -0.03, 0.07], "LeftShoulder"];
            };
            missionNamespace setVariable ["COMSPEC_ATAK_IrObj", _cur];
            missionNamespace setVariable ["COMSPEC_ATAK_IrKey", _key];
        };
        true
    };
    default { false };
};
