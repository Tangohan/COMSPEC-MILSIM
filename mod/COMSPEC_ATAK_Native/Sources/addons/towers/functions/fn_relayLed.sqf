/*
    Voyants d'état des relais 3D (armoires des pylônes, radios des mâts, des antennes murales et des kits) : la sélection
    « led » (hiddenSelections index 1) prend la couleur de l'état réel du relais. Client seulement, relais à moins de
    250 m de la caméra, un passage toutes les 3 s, texture posée seulement quand l'état change.
      vert : en service        ambre : dégradé (dégâts ≥ 10 %) ou batterie faible (< 25 %)
      rouge : critique (dégâts ≥ 50 %)        rouge clignotant : brouillé        éteint : arrêté, batterie vide, détruit
      ["init"] call comspec_atak_native_fnc_relayLed    (postInit client, towers/config.cpp)
*/
params [["_act", "init", [""]]];
if (!hasInterface) exitWith {};
if (_act isEqualTo "init") exitWith {
    if (missionNamespace getVariable ["COMSPEC_ATAK_RelayLedLoop", false]) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_RelayLedLoop", true];
    [{ ["tick"] call comspec_atak_native_fnc_relayLed; }, 3, []] call CBA_fnc_addPerFrameHandler;
};
private _off = "#(argb,8,8,3)color(0.02,0.02,0.02,1,co)";
private _green = "#(argb,8,8,3)color(0.1,0.9,0.2,1,co)";
private _amber = "#(argb,8,8,3)color(1,0.55,0.05,1,co)";
private _red = "#(argb,8,8,3)color(1,0.06,0.03,1,co)";
private _cam = positionCameraToWorld [0, 0, 0];
private _blinkOn = ((floor (diag_tickTime / 3)) mod 2) isEqualTo 0;
{
    if (!isNull _x && {(_x distance _cam) < 250} && {"led" in ((getArray (configOf _x >> "hiddenSelections")) apply { toLower _x })}) then {
        private _dmg = damage _x;
        private _batt = _x getVariable ["COMSPEC_AtakRelayBatt", -1];
        private _col = _off;
        if (alive _x && {_dmg < 0.95} && {_x getVariable ["COMSPEC_AtakRelayOn", true]} && {_batt isNotEqualTo 0}) then {
            _col = _green;
            if (_dmg >= 0.1 || {_batt >= 0 && {_batt < 25}}) then { _col = _amber; };
            if (_dmg >= 0.5) then { _col = _red; };
            // Brouillage : connu par l'entrée du relais dans fn_relayNet (cache de 2 s, quelques relais proches seulement).
            private _entry = ["entry", _x] call comspec_atak_native_fnc_relayNet;
            if (!isNil "_entry" && {_entry isEqualType []} && {(_entry param [7, ""]) isEqualTo "brouillé"}) then { _col = [_off, _red] select _blinkOn; };
        };
        if ((_x getVariable ["COMSPEC_ATAK_RelayLedTex", ""]) isNotEqualTo _col) then {
            _x setVariable ["COMSPEC_ATAK_RelayLedTex", _col];
            _x setObjectTexture [1, _col];
        };
    };
} forEach (missionNamespace getVariable ["COMSPEC_AtakRelays", []]);
