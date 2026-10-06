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
    || {_state isEqualTo "OFF" && {(_hp get "reason") isNotEqualTo "Batterie vide"}};
private _items = (items player) apply { toLower _x };
private _kit = "comspec_atak_repairkit" in _items;
private _tool = (_items findIf { _x in ["toolkit", "ace_toolkit"] }) >= 0;
if (_mode isEqualTo "can") exitWith { _needs && {_kit || _tool} && {!(player getVariable ["COMSPEC_ATAK_Repairing", false])} };
if (!_needs) exitWith { ["INFO", "Le téléphone n'a rien à réparer", 3, 20] call comspec_atak_native_fnc_notify; hintSilent "Le téléphone ATAK n'a rien à réparer."; false };
if (!_kit && !_tool) exitWith { hintSilent parseText "<t color='#f2ab33'>Kit de réparation ATAK requis</t><br/>(ou une caisse à outils)"; false };
if (player getVariable ["COMSPEC_ATAK_Repairing", false]) exitWith { false };
player setVariable ["COMSPEC_ATAK_Repairing", true];
private _time = [20, 12] select _kit;
// Le téléphone est entre les mains du réparateur : rangé pendant la réparation (la barre ACE est une fenêtre).
uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
[] call comspec_atak_native_fnc_close;
missionNamespace setVariable ["COMSPEC_ATAK_RepairDone", {
    params [["_kit", false]];
    player setVariable ["COMSPEC_ATAK_Repairing", false];
    if (_kit) then {
        private _cls = (items player) select { (toLower _x) isEqualTo "comspec_atak_repairkit" };
        if ((count _cls) isEqualTo 0) exitWith { _kit = false; };
        player removeItem (_cls select 0);
    };
    ["repair"] call comspec_atak_native_fnc_deviceRepair;
    hintSilent parseText format ["<t color='#5cc76b'>Téléphone ATAK réparé</t><br/>%1", ["Caisse à outils utilisée.", "Kit de réparation consommé."] select _kit];
}];
player playActionNow "Medic";
if (!isNil "ace_common_fnc_progressBar") then {
    [_time, [_kit], {
        (_this select 0) call (missionNamespace getVariable ["COMSPEC_ATAK_RepairDone", {}]);
    }, {
        player setVariable ["COMSPEC_ATAK_Repairing", false];
        hintSilent "Réparation du téléphone interrompue.";
    }, "Réparation du téléphone ATAK…", { alive player }, ["isNotInside", "isNotSitting"]] call ace_common_fnc_progressBar;
} else {
    hintSilent format ["Réparation du téléphone ATAK… (%1 s)", _time];
    [{
        params ["_kit"];
        if (!alive player || {lifeState player isEqualTo "INCAPACITATED"}) exitWith {
            player setVariable ["COMSPEC_ATAK_Repairing", false];
            hintSilent "Réparation du téléphone interrompue.";
        };
        [_kit] call (missionNamespace getVariable ["COMSPEC_ATAK_RepairDone", {}]);
    }, [_kit], _time] call CBA_fnc_waitAndExecute;
};
true
