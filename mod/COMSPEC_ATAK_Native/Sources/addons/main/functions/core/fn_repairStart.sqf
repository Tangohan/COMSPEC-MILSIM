/*
    Réparer le téléphone (action ACE « Réparer le téléphone ATAK », app Profil).
    Il faut un « Kit de réparation ATAK » (COMSPEC_ATAK_RepairKit, consommé, 12 s) ou une caisse à outils
    (ToolKit / ACE, gardée, 20 s). Barre de progression ACE si ACE est présent, sinon attente simple.
    Fin : écran, alimentation et appareil remis en état (fn_deviceRepair, y compris l'état du réalisme Overwatch),
    puis redémarrage (écran de démarrage).
    Params : [mode "start" (défaut) | "can" (renvoie true si une réparation est possible maintenant)]
*/
params [["_mode", "start"]];
if (!hasInterface) exitWith { false };
private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _state = _hp get "state";
private _needs = (((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["damage", 0]) > 0)
    || {_state in ["CRACKED", "BROKEN"]}
    || {((values (_hp getOrDefault ["parts", createHashMap])) findIf { _x > 0 }) >= 0}
    || {_state isEqualTo "OFF" && {(_hp get "reason") isNotEqualTo "Batterie vide"}};
private _items = (items player) apply { toLower _x };
private _kit = "comspec_atak_repairkit" in _items;
private _tool = (_items findIf { _x in ["toolkit", "ace_toolkit"] }) >= 0;
if (_mode isEqualTo "can") exitWith { _needs && {_kit || _tool} && {!(player getVariable ["COMSPEC_ATAK_Repairing", false])} };
if (!_needs) exitWith { ["INFO", "Le téléphone n'a rien à réparer", 3, 20] call comspec_atak_native_fnc_notify; false };
if (!_kit && !_tool) exitWith { ["WARNING", "Kit de réparation ATAK requis · (ou une caisse à outils)", 4, 30] call comspec_atak_native_fnc_notify; false };
if (player getVariable ["COMSPEC_ATAK_Repairing", false]) exitWith { false };
player setVariable ["COMSPEC_ATAK_Repairing", true];
private _time = [20, 12] select _kit;
uiNamespace setVariable ["COMSPEC_ATAK_RepairDone", {
    params [["_kit", false]];
    player setVariable ["COMSPEC_ATAK_Repairing", false];
    if (_kit) then {
        private _cls = (items player) select { (toLower _x) isEqualTo "comspec_atak_repairkit" };
        if ((count _cls) isEqualTo 0) exitWith { _kit = false; };
        player removeItem (_cls select 0);
    };
    ["repair"] call comspec_atak_native_fnc_deviceRepair;
    ["SUCCESS", format ["Téléphone ATAK réparé · %1", ["Caisse à outils utilisée.", "Kit de réparation consommé."] select _kit], 5, 40] call comspec_atak_native_fnc_notify;
}];
player playActionNow "Medic";
if (!isNil "ace_common_fnc_progressBar") then {
    // Le téléphone est entre les mains du réparateur : rangé pendant la réparation (la barre ACE est une fenêtre).
    // Sans ACE il reste ouvert : l'annonce et la fin de la réparation s'y lisent.
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
    [] call comspec_atak_native_fnc_close;
    [_time, [_kit], {
        (_this select 0) call (uiNamespace getVariable ["COMSPEC_ATAK_RepairDone", {}]);
    }, {
        player setVariable ["COMSPEC_ATAK_Repairing", false];
        ["WARNING", "Réparation du téléphone interrompue.", 4, 30] call comspec_atak_native_fnc_notify;
    }, ("Réparation du téléphone ATAK…" call comspec_atak_native_fnc_tr), { alive player }, ["isNotInside", "isNotSitting"]] call ace_common_fnc_progressBar;
} else {
    ["INFO", format ["Réparation du téléphone ATAK… (%1 s)", _time], 4, 20] call comspec_atak_native_fnc_notify;
    [{
        params ["_kit"];
        if (!alive player || {lifeState player isEqualTo "INCAPACITATED"}) exitWith {
            player setVariable ["COMSPEC_ATAK_Repairing", false];
            ["WARNING", "Réparation du téléphone interrompue.", 4, 30] call comspec_atak_native_fnc_notify;
        };
        [_kit] call (uiNamespace getVariable ["COMSPEC_ATAK_RepairDone", {}]);
    }, [_kit], _time] call CBA_fnc_waitAndExecute;
};
true
