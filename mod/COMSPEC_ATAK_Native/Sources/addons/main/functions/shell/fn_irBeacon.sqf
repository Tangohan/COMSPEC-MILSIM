/*
    Balise IR clipsable (objet COMSPEC_ATAK_IrMarker, type MS-2000 / Manta ; la balise IR d'ACE convient aussi) :
    boîtier à part avec sa propre pile CR123, allumé à la main (menu ACE « Accessoires » ou établi), jamais depuis
    le téléphone. Clignotant infrarouge invisible à l'œil nu, vu par tous aux JVN (identification ami).
    Clipsée sur la plaque MOLLE du téléphone s'il est porté sur le gilet, sinon sur l'épaule gauche.
    Pile : environ 8 h de clignotement (12,5 % / h) ; vide, la balise est jetée. Fonctionne téléphone éteint ou absent.
    Effet : stroboscope IR d'ACE si chargé, sinon celui du jeu (B_IRStrobe), recréé s'il s'éteint.
    État (joueur) : missionNamespace COMSPEC_ATAK_IrOn, COMSPEC_ATAK_IrCharge (0-100).
    Params : [action]  "toggle" | "on" | "off" | "tick" (client, toutes les 2 s) | "state" | "has" | "charge"
*/
params [["_act", "tick"]];
private _items = ["COMSPEC_ATAK_IrMarker", "ACE_IR_Strobe_Item"];
private _on = missionNamespace getVariable ["COMSPEC_ATAK_IrOn", false];
private _cur = missionNamespace getVariable ["COMSPEC_ATAK_IrObj", objNull];
private _kill = { if (!isNull _cur) then { deleteVehicle _cur; }; missionNamespace setVariable ["COMSPEC_ATAK_IrObj", objNull]; };
private _owned = { private _inv = items player; _items select { _x in _inv } };
switch (_act) do {
    case "state": { _on };
    case "has": { (call _owned) isNotEqualTo [] };
    case "charge": { missionNamespace getVariable ["COMSPEC_ATAK_IrCharge", 100] };
    case "toggle": { [["on", "off"] select _on] call comspec_atak_native_fnc_irBeacon };
    case "on": {
        if ((call _owned) isEqualTo []) exitWith { ["WARNING", "Aucune balise IR dans l'inventaire", 3, 20] call comspec_atak_native_fnc_notify; false };
        if ((missionNamespace getVariable ["COMSPEC_ATAK_IrCharge", 100]) <= 0) then { missionNamespace setVariable ["COMSPEC_ATAK_IrCharge", 100]; };
        missionNamespace setVariable ["COMSPEC_ATAK_IrOn", true];
        missionNamespace setVariable ["COMSPEC_ATAK_IrLast", time];
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
        if (!_on) exitWith { call _kill; false };
        private _own = call _owned;
        if (_own isEqualTo []) exitWith {
            missionNamespace setVariable ["COMSPEC_ATAK_IrOn", false];
            call _kill;
            ["WARNING", "Balise IR perdue", 3, 30] call comspec_atak_native_fnc_notify;
            false
        };
        // Pile CR123 : 12,5 % par heure de clignotement, décomptée même en véhicule (la balise reste allumée).
        private _dt = (time - (missionNamespace getVariable ["COMSPEC_ATAK_IrLast", time])) max 0;
        missionNamespace setVariable ["COMSPEC_ATAK_IrLast", time];
        private _charge = 0 max ((missionNamespace getVariable ["COMSPEC_ATAK_IrCharge", 100]) - 12.5 * _dt / 3600);
        missionNamespace setVariable ["COMSPEC_ATAK_IrCharge", _charge];
        if (_charge <= 0) exitWith {
            player removeItem (_own select 0);
            missionNamespace setVariable ["COMSPEC_ATAK_IrOn", false];
            call _kill;
            ["WARNING", "Pile de la balise IR vide : balise jetée", 5, 40] call comspec_atak_native_fnc_notify;
            false
        };
        if (!alive player || {!isNull objectParent player}) exitWith { call _kill; false };
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
