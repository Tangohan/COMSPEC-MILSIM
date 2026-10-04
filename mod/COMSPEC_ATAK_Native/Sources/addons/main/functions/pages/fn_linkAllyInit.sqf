/*
    App Liaison ATAK : branchements côté client (appelé une fois depuis XEH_postInitClient).
    - événements CBA : lecture de mon téléphone par un équipier, réponse, renvoi de ma file d'envoi ;
    - copie de sauvegarde légère sur mon unité toutes les 30 s (lue si je me déconnecte) ;
    - heure et circonstances des morts (EntityKilled), propriétaire d'un téléphone posé au sol (Put) ;
    - progression du lien dans l'app et coupure si l'on s'éloigne ;
    - action ACE « Relier mon ATAK » sur un équipier.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_ATAK_LinkAllyInitDone") exitWith {};
COMSPEC_ATAK_LinkAllyInitDone = true;

["comspec_atak_native_linkAllyQuery", { ["query", _this] call comspec_atak_native_fnc_linkAllyAction; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_linkAllyReply", { ["reply", _this] call comspec_atak_native_fnc_linkAllyAction; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_linkAllyFlush", { ["flush", _this] call comspec_atak_native_fnc_linkAllyAction; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_linkAllyFlushed", { ["flushed", _this] call comspec_atak_native_fnc_linkAllyAction; }] call CBA_fnc_addEventHandler;

// Sauvegarde de mon appareil sur mon unité (seulement si elle a changé : quelques centaines d'octets).
[{ if ([player] call comspec_atak_native_fnc_hasDevice) then { ["mirror"] call comspec_atak_native_fnc_linkAllyAction; }; }, 30] call CBA_fnc_addPerFrameHandler;

addMissionEventHandler ["EntityKilled", { params ["_unit", "_killer", "_inst"]; ["killed", [_unit, _killer, _inst]] call comspec_atak_native_fnc_linkAllyAction; }];

// Téléphone posé dans une caisse ou au sol : on retient son propriétaire pour pouvoir s'y relier.
["CAManBase", "Put", {
    params ["_unit", "_container", "_item"];
    if ((toLower _item) in ([] call comspec_atak_native_fnc_deviceCatalog)) then { _container setVariable ["COMSPEC_ATAK_PhoneOwner", _unit, true]; };
}] call CBA_fnc_addClassEventHandler;

// Tick : barre de progression, lien coupé si l'on s'éloigne, liste à portée remise à jour.
[{
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_LinkAlly", createHashMap];
    private _phase = _st getOrDefault ["phase", ""];
    private _active = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "LINKALLY" && {!isNull ([] call comspec_atak_native_fnc_display)};
    private _holder = _st getOrDefault ["holder", objNull];
    if (_phase in ["LINKING", "LINKED"] && {isNull _holder || {(player distance _holder) > ([2.6, 3.5] select (_phase isEqualTo "LINKED"))} || {!alive player}}) exitWith {
        ["unlink"] call comspec_atak_native_fnc_linkAllyAction;
        ["WARNING", "Liaison ATAK coupée : trop loin de l'appareil", 4, 30] call comspec_atak_native_fnc_notify;
    };
    if (_phase isEqualTo "LINKING") exitWith {
        if (diag_tickTime >= (_st getOrDefault ["start", 0]) + (_st getOrDefault ["dur", 4])) then {
            ["linked"] call comspec_atak_native_fnc_linkAllyAction;
        } else {
            if (_active) then { ["LINKALLY"] call comspec_atak_native_fnc_pageRender; };
        };
    };
    if (_phase isEqualTo "" && {_active}) then {
        private _now = (["candidates"] call comspec_atak_native_fnc_linkAllyAction) apply { _x select 0 };
        if (_now isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LinkAllySeen", []])) then { ["LINKALLY"] call comspec_atak_native_fnc_pageRender; };
    };
}, 0.5] call CBA_fnc_addPerFrameHandler;

// Action ACE sur un équipier (vivant, inconscient ou mort) : lien avec la barre de progression ACE.
if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _act = ["COMSPEC_ATAK_LinkAlly", "Relier mon ATAK à son téléphone", "\z\comspec_atak_native\addons\main\data\app_linkally.paa", {
        params ["_target"];
        ["linkAce", _target] call comspec_atak_native_fnc_linkAllyAction;
    }, {
        params ["_target"];
        _target isNotEqualTo player && {(player distance _target) < 2.2} && {[player] call comspec_atak_native_fnc_hasDevice} && {[_target] call comspec_atak_native_fnc_hasDevice}
        && {((uiNamespace getVariable ["COMSPEC_ATAK_LinkAlly", createHashMap]) getOrDefault ["phase", ""]) isEqualTo ""}
    }] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions"], _act, true] call ace_interact_menu_fnc_addActionToClass;
};
true
