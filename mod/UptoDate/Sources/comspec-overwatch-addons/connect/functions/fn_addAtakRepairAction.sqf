/*
    Ajoute l'action ACE pour réparer l'ATAK.
    Appelé au postInit.
*/

if (!hasInterface) exitWith {};
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {};
if (isNil "ace_interact_menu_fnc_addActionToObject") exitWith {};

// Joueur pas encore prêt (postInit trop tôt) : reporter.
if (isNull player) exitWith {
    [{ [] call comspec_overwatch_connect_fnc_addAtakRepairAction }, [], 1] call CBA_fnc_waitAndExecute;
};

// Évite double enregistrement (spawn postInit + respawn)
if (missionNamespace getVariable ["COMSPEC_AtakRepairReady", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_AtakRepairReady", true, false];

// insertChildren ACE : DOIT retourner un tableau (jamais nil via {}).
private _noChildren = { [] };

// Action pour rallumer l'ATAK
private _actionPower = [
    "COMSPEC_RepairAtakPower",
    "Rallumer l'ATAK",
    "",
    {
        ["power"] call comspec_overwatch_connect_fnc_repairAtak;
    },
    {
        private _status = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
        !(_status get "powered_on") && {!(_status get "device_destroyed")}
    },
    _noChildren
] call comspec_overwatch_connect_fnc_aceAction;

// Action pour réparer l'écran
private _actionScreen = [
    "COMSPEC_RepairAtakScreen",
    "Réparer écran ATAK",
    "",
    {
        ["screen"] call comspec_overwatch_connect_fnc_repairAtak;
    },
    {
        private _status = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
        !(_status get "screen_ok") && {!(_status get "device_destroyed")} && {"ToolKit" in (items player)}
    },
    _noChildren
] call comspec_overwatch_connect_fnc_aceAction;

// Action pour diagnostic
private _actionDiag = [
    "COMSPEC_DiagnosticAtak",
    "Diagnostic ATAK",
    "",
    {
        private _status = [] call comspec_overwatch_connect_fnc_isAtakFunctional;

        // Vrais retours à la ligne (un « \n » littéral arrivait tel quel dans le téléphone et le journal HTML).
        private _nl = toString [10];
        private _msg = "État de l'ATAK :" + _nl + _nl;
        _msg = _msg + format ["Alimentation : %1%2", if (_status get "powered_on") then {"✓ OK"} else {"✗ Éteint"}, _nl];
        _msg = _msg + format ["Écran : %1%2", if (_status get "screen_ok") then {"✓ OK"} else {"✗ Détruit"}, _nl];
        _msg = _msg + format ["Connexion : %1%2", if (_status get "connection_ok") then {"✓ OK"} else {"✗ Coupée"}, _nl];

        if (!(_status get "device_destroyed")) then {
            if (!(_status get "powered_on")) then {
                _msg = _msg + _nl + "Rallumage possible (gratuit)";
            };
            if (!(_status get "screen_ok") && {"ToolKit" in (items player)}) then {
                _msg = _msg + _nl + "Réparation écran possible (Toolkit)";
            };
        } else {
            _msg = _msg + _nl + "ATAK irréparable, remplacement nécessaire";
        };

        // ATAK natif : notification du téléphone (le journal HTML d'ambientHint n'y est jamais lu).
        // Sinon : journal Alertes de la tablette ; hint écran seulement si les notifications écran sont autorisées.
        if (!isNil "comspec_atak_native_fnc_notify") then {
            ["INFO", (_msg) call comspec_overwatch_connect_fnc_tr, 8, 30] call comspec_atak_native_fnc_notify;
        } else {
            [_msg, "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        };
    },
    { true },
    _noChildren
] call comspec_overwatch_connect_fnc_aceAction;

// Ajouter au self-interact (copie isolée + gardes)
[_actionPower, ["ACE_SelfActions", "ACE_Equipment"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;
[_actionScreen, ["ACE_SelfActions", "ACE_Equipment"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;
[_actionDiag, ["ACE_SelfActions", "ACE_Equipment"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;
