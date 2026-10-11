/*
    Batterie externe fixée sur la plaque du dos (objet COMSPEC_ATAK_PowerBank, 10 000 mAh) : plus d'autonomie,
    au prix du poids de l'objet, qui reste dans l'inventaire tant qu'il est fixé. Elle recharge le téléphone
    à 2 % par minute et contient deux charges complètes (pertes comprises). Vide, elle est jetée.
    État (joueur) : missionNamespace COMSPEC_ATAK_PowerBankOn (fixée), COMSPEC_ATAK_PowerBankCharge (0-100).
    Params : [action, arguments]
      "has"     : une batterie externe est-elle dans l'inventaire ?
      "attach"  : fixer (une batterie neuve si la précédente est vide)     "detach" : retirer
      "rate"    : [dt (s), niveau du téléphone] -> % / min apporté au téléphone (négatif = recharge, 0 sinon)
      "label"   : texte d'état pour les menus
*/
params [["_act", "has"], ["_a", []]];
private _item = "COMSPEC_ATAK_PowerBank";
private _on = missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false];
private _charge = missionNamespace getVariable ["COMSPEC_ATAK_PowerBankCharge", 100];
private _set = {
    params ["_v"];
    missionNamespace setVariable ["COMSPEC_ATAK_PowerBankOn", _v];
    // Visible par les autres (vue de dos, gilet) : variable publique du joueur.
    player setVariable ["COMSPEC_ATAK_PowerBank", _v, true];
};
switch (_act) do {
    case "has": { _item in (items player) };
    case "attach": {
        if !(_item in (items player)) exitWith { ["WARNING", "Aucune batterie externe dans l'inventaire", 3, 20] call comspec_atak_native_fnc_notify; false };
        if (_charge <= 0) then { missionNamespace setVariable ["COMSPEC_ATAK_PowerBankCharge", 100]; };
        [true] call _set;
        playSound "ClickSoft";
        ["INFO", format ["Batterie externe fixée (%1 %%)", round (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankCharge", 100])], 3, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "detach": {
        [false] call _set;
        playSound "ClickSoft";
        ["INFO", "Batterie externe retirée", 3, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "label": {
        if (!_on) exitWith { "" };
        format ["Batterie externe %1 %%", round _charge]
    };
    case "rate": {
        _a params [["_dt", 0], ["_level", 100]];
        if (!_on) exitWith { 0 };
        if !(_item in (items player)) exitWith { [false] call _set; ["WARNING", "Batterie externe perdue", 3, 30] call comspec_atak_native_fnc_notify; 0 };
        if (_level >= 99.5 || {_charge <= 0}) exitWith { 0 };
        // 2 % du téléphone par minute ; la batterie externe en perd 1,1 % (deux charges et 10 % de pertes).
        _charge = 0 max (_charge - 1.1 * _dt / 60);
        missionNamespace setVariable ["COMSPEC_ATAK_PowerBankCharge", _charge];
        if (_charge <= 0) then {
            player removeItem _item;
            [false] call _set;
            ["WARNING", "Batterie externe vide : jetée", 5, 40] call comspec_atak_native_fnc_notify;
        };
        -2
    };
    default { false };
};
