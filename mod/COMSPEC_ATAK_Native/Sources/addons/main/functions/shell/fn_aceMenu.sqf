/*
    Menu ACE du téléphone, rangé sous une seule entrée « Téléphone ATAK » (auto-interaction) :
      Voir l'arrière
      Antenne ▸ visser une antenne de l'inventaire / la retirer
      Personnaliser ▸ Coque (coyote, vert OD, noir) ▸ Module DL-7 (olive, noir, tan) ▸ Patch au dos
      Entretien ▸ batterie, nettoyage, réparation, téléphone de rechange (posés par XEH_postInitClient)
                  + rallumer, réparer l'écran, diagnostic d'Overwatch (repris ici et retirés de « Équipement »)
      Accessoires ▸ batterie externe, balise IR, filtre d'écran JVN
      Passerelle du véhicule ▸ relais, poste de commandement, couper (équipage) ; aussi sur les véhicules (dehors)
      Effacement d'urgence
      Débrancher le câble DL-7 (pendant une extraction)
    Appelé une fois depuis XEH_postInitClient, avant les actions d'entretien.
*/
if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"}) exitWith { false };
private _root = ["ACE_SelfActions", "COMSPEC_ATAK_Phone"];
private _add = {
    params ["_path", "_id", "_name", ["_icon", ""], ["_code", {}], ["_cond", { true }], ["_children", nil]];
    private _a = if (isNil "_children") then {
        [_id, _name, _icon, _code, _cond] call ace_interact_menu_fnc_createAction
    } else {
        [_id, _name, _icon, _code, _cond, _children] call ace_interact_menu_fnc_createAction
    };
    ["CAManBase", 1, _path, _a, true] call ace_interact_menu_fnc_addActionToClass;
};
[["ACE_SelfActions"], "COMSPEC_ATAK_Phone", "Téléphone ATAK", "\z\comspec_atak_native\addons\main\data\logo_atak.paa", {},
    { _player isEqualTo _target && {[_player] call comspec_atak_native_fnc_hasDevice} }] call _add;

[_root, "COMSPEC_ATAK_Back", "Voir l'arrière", "", { [{ [] call comspec_atak_native_fnc_backView; }] call CBA_fnc_execNextFrame; }] call _add;

// Antennes : une entrée par antenne portée, plus « Retirer » quand une est vissée.
[_root, "COMSPEC_ATAK_AntMenu", "Antenne", "\z\comspec_atak_native\addons\main\data\item_antenna_stub.paa", {},
    { (["owned"] call comspec_atak_native_fnc_antenna) isNotEqualTo [] },
    {
        private _cur = (["get"] call comspec_atak_native_fnc_antenna) param [0, ""];
        private _out = (["owned"] call comspec_atak_native_fnc_antenna) select { (_x select 0) isNotEqualTo _cur } apply {
            _x params ["_id", "", "_name"];
            [[format ["COMSPEC_ATAK_Ant_%1", _id], format ["Visser : %1", _name], format ["\z\comspec_atak_native\addons\main\data\item_antenna_%1.paa", _id],
                { ["mount", _this select 2] call comspec_atak_native_fnc_antenna; }, { true }, {}, _id] call ace_interact_menu_fnc_createAction, [], _target]
        };
        if (_cur isNotEqualTo "") then {
            _out pushBack [["COMSPEC_ATAK_AntOff", "Retirer l'antenne", "", { ["unmount"] call comspec_atak_native_fnc_antenna; }, { true }] call ace_interact_menu_fnc_createAction, [], _target];
        };
        _out
    }] call _add;

// Personnaliser : couleur de la coque et du module DL-7 (mêmes réglages que l'app Réglages).
[_root, "COMSPEC_ATAK_Perso", "Personnaliser", "\z\comspec_atak_native\addons\main\data\app_settings.paa"] call _add;
private _set = {
    params ["_var", "_val"];
    profileNamespace setVariable [_var, _val];
    saveProfileNamespace;
    if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_layoutApply; };
};
missionNamespace setVariable ["COMSPEC_ATAK_PersoSet", _set];
{
    _x params ["_var", "_def", "_id", "_label", "_opts"];
    [_root + ["COMSPEC_ATAK_Perso"], _id, _label] call _add;
    {
        _x params ["_txt", "_val"];
        [_root + ["COMSPEC_ATAK_Perso", _id], format ["%1_%2", _id, _val], _txt, "",
            compile format ["['%1', '%2'] call (missionNamespace getVariable 'COMSPEC_ATAK_PersoSet');", _var, _val],
            compile format ["(profileNamespace getVariable ['%1', '%2']) isNotEqualTo '%3'", _var, _def, _val]] call _add;
    } forEach _opts;
} forEach [
    ["COMSPEC_ATAK_ShellTint", "coyote", "COMSPEC_ATAK_PersoShell", "Couleur de la coque", [["Coyote", "coyote"], ["Vert OD", "od"], ["Noir", "black"]]],
    ["COMSPEC_ATAK_ModuleTint", "olive", "COMSPEC_ATAK_PersoModule", "Couleur du module DL-7", [["Olive", "olive"], ["Noir", "black"], ["Tan", "tan"]]],
    ["COMSPEC_ATAK_Patch", "none", "COMSPEC_ATAK_PersoPatch", "Patch au dos", [["Aucun", "none"], ["France", "fr"], ["France basse visibilité", "fr_sub"], ["USA", "us"], ["USA basse visibilité", "us_ir"],
        ["Crâne", "skull"], ["Croix", "crusader"], ["Alien", "alien"], ["COMSPEC", "comspec"], ["Nominatif", "name"],
        ["Insigne de l'uniforme", "uniform"]]],
    ["COMSPEC_ATAK_VestMount", "off", "COMSPEC_ATAK_PersoVest", "Téléphone sur le gilet", [["Non", "off"], ["Poitrine gauche", "left"], ["Centre", "center"], ["Poitrine droite", "right"]]]
];

// Entretien : les actions sont posées dans ce sous-menu par XEH_postInitClient. Celles d'Overwatch y sont reprises.
[_root, "COMSPEC_ATAK_Care", "Entretien", "\z\comspec_atak_native\addons\main\data\item_repairkit.paa"] call _add;
private _care = _root + ["COMSPEC_ATAK_Care"];
private _ow = { !isNil "comspec_overwatch_connect_fnc_isAtakFunctional" && {!isNil "comspec_overwatch_connect_fnc_repairAtak"} };
missionNamespace setVariable ["COMSPEC_ATAK_OwRepair", _ow];
[_care, "COMSPEC_ATAK_OwPower", "Rallumer le téléphone", "", { ["power"] call comspec_overwatch_connect_fnc_repairAtak; }, {
    call (missionNamespace getVariable "COMSPEC_ATAK_OwRepair") && {private _s = [] call comspec_overwatch_connect_fnc_isAtakFunctional; !(_s get "powered_on") && {!(_s get "device_destroyed")}}
}] call _add;
[_care, "COMSPEC_ATAK_OwScreen", "Réparer l'écran (caisse à outils)", "", { ["screen"] call comspec_overwatch_connect_fnc_repairAtak; }, {
    call (missionNamespace getVariable "COMSPEC_ATAK_OwRepair") && {private _s = [] call comspec_overwatch_connect_fnc_isAtakFunctional; !(_s get "screen_ok") && {!(_s get "device_destroyed")} && {"ToolKit" in (items player)}}
}] call _add;
[_care, "COMSPEC_ATAK_OwDiag", "Diagnostic", "", {
    private _s = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
    private _ok = { ["✗ ", "✓ "] select _this };
    hint format ["État de l'ATAK\n\nAlimentation : %1\nÉcran : %2\nConnexion : %3%4",
        ((_s get "powered_on") call _ok) + (["éteint", "OK"] select (_s get "powered_on")),
        ((_s get "screen_ok") call _ok) + (["détruit", "OK"] select (_s get "screen_ok")),
        ((_s get "connection_ok") call _ok) + (["coupée", "OK"] select (_s get "connection_ok")),
        ["", "\n\nTéléphone irréparable : remplacement nécessaire"] select (_s get "device_destroyed")];
}, { call (missionNamespace getVariable "COMSPEC_ATAK_OwRepair") }] call _add;
// Les mêmes actions posées par Overwatch dans « Équipement » sont retirées une fois en place (doublons).
[{ missionNamespace getVariable ["COMSPEC_AtakRepairReady", false] }, {
    {
        ["CAManBase", 1, ["ACE_SelfActions", "ACE_Equipment", _x]] call ace_interact_menu_fnc_removeActionFromClass;
        if (!isNull player) then { [typeOf player, 1, ["ACE_SelfActions", "ACE_Equipment", _x]] call ace_interact_menu_fnc_removeActionFromClass; };
    } forEach ["COMSPEC_RepairAtakPower", "COMSPEC_RepairAtakScreen", "COMSPEC_DiagnosticAtak"];
}, [], 120] call CBA_fnc_waitUntilAndExecute;

// Accessoires : batterie externe sur la plaque, balise IR de la coque, filtre d'écran JVN.
[_root, "COMSPEC_ATAK_Acc", "Accessoires", "\z\comspec_atak_native\addons\main\data\item_powerbank.paa"] call _add;
private _acc = _root + ["COMSPEC_ATAK_Acc"];
[_acc, "COMSPEC_ATAK_PbOn", "Fixer la batterie externe", "\z\comspec_atak_native\addons\main\data\item_powerbank.paa", { ["attach"] call comspec_atak_native_fnc_powerBank; },
    { !(missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false]) && {["has"] call comspec_atak_native_fnc_powerBank} }] call _add;
[_acc, "COMSPEC_ATAK_PbOff", "Retirer la batterie externe", "", { ["detach"] call comspec_atak_native_fnc_powerBank; },
    { missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false] }] call _add;
[_acc, "COMSPEC_ATAK_IrOnA", "Allumer la balise IR", "", { ["on"] call comspec_atak_native_fnc_irBeacon; }, { !(["state"] call comspec_atak_native_fnc_irBeacon) }] call _add;
[_acc, "COMSPEC_ATAK_IrOffA", "Éteindre la balise IR", "", { ["off"] call comspec_atak_native_fnc_irBeacon; }, { ["state"] call comspec_atak_native_fnc_irBeacon }] call _add;
[_acc, "COMSPEC_ATAK_FilterOn", "Poser le filtre d'écran JVN", "\z\comspec_atak_native\addons\main\data\item_nvgfilter.paa", { ["toggle"] call comspec_atak_native_fnc_screenGlow; },
    { !(profileNamespace getVariable ["COMSPEC_ATAK_NvgFilter", false]) && {"COMSPEC_ATAK_NvgFilter" in (items _player)} }] call _add;
[_acc, "COMSPEC_ATAK_FilterOff", "Retirer le filtre d'écran JVN", "", { ["toggle"] call comspec_atak_native_fnc_screenGlow; },
    { profileNamespace getVariable ["COMSPEC_ATAK_NvgFilter", false] }] call _add;

// Passerelle du véhicule où l'on est (équipage) : relais, poste de commandement, coupure.
private _gwKids = {
    params ["_v"];
    private _st = ["state", _v] call comspec_atak_native_fnc_vehicleGateway;
    private _out = [];
    {
        _x params ["_mode", "_txt"];
        if (_mode isNotEqualTo _st && {_mode isNotEqualTo "off" || {_st isNotEqualTo ""}}) then {
            _out pushBack [[format ["COMSPEC_ATAK_Gw_%1", _mode], _txt, "", { (_this select 2) params ["_m", "_veh"]; [_m, _veh] call comspec_atak_native_fnc_vehicleGateway; }, { true }, {}, [_mode, _v]] call ace_interact_menu_fnc_createAction, [], _v];
        };
    } forEach [["relay", "Activer la passerelle (relais)"], ["cp", "Déployer le poste de commandement"], ["off", "Couper la passerelle"]];
    _out
};
missionNamespace setVariable ["COMSPEC_ATAK_GwKids", _gwKids];
[_root, "COMSPEC_ATAK_GwSelf", "Passerelle du véhicule", "\A3\ui_f\data\map\markers\nato\b_hq.paa", {},
    { !isNull objectParent _player && {["can", objectParent _player] call comspec_atak_native_fnc_vehicleGateway} },
    { [objectParent _player] call (missionNamespace getVariable "COMSPEC_ATAK_GwKids") }] call _add;
{
    private _a = ["COMSPEC_ATAK_GwExt", "Passerelle ATAK", "\A3\ui_f\data\map\markers\nato\b_hq.paa", {},
        { ["can", _target] call comspec_atak_native_fnc_vehicleGateway },
        { [_target] call (missionNamespace getVariable "COMSPEC_ATAK_GwKids") }] call ace_interact_menu_fnc_createAction;
    [_x, 0, ["ACE_MainActions"], _a, true] call ace_interact_menu_fnc_addActionToClass;
    // Kit terminal (réglage serveur « avec le kit installé ») : tablette durcie posée dans le véhicule.
    private _k = ["COMSPEC_ATAK_VehKit", "Installer le terminal ATAK", "\z\comspec_atak_native\addons\main\data\item_vehiclekit.paa", {
        params ["_target", "_player"];
        [8, [_target, _player], {
            (_this select 0) params ["_v", "_p"];
            if !("COMSPEC_ATAK_VehicleKit" in (items _p)) exitWith {};
            _p removeItem "COMSPEC_ATAK_VehicleKit";
            _v setVariable ["COMSPEC_ATAK_VehTerm", true, true];
            ["INFO", "Terminal ATAK installé : l'équipage a la tablette de bord", 4, 30] call comspec_atak_native_fnc_notify;
        }, {}, "Installation du terminal ATAK…"] call ace_common_fnc_progressBar;
    }, {
        (missionNamespace getVariable ["comspec_atak_native_vehicle_terminal", "all"]) isEqualTo "kit" && {alive _target}
            && {!(_target getVariable ["COMSPEC_ATAK_VehTerm", false])} && {"COMSPEC_ATAK_VehicleKit" in (items _player)}
    }] call ace_interact_menu_fnc_createAction;
    [_x, 0, ["ACE_MainActions"], _k, true] call ace_interact_menu_fnc_addActionToClass;
} forEach ["LandVehicle", "Ship", "Helicopter"];

[_root, "COMSPEC_ATAK_Wipe", "<t color='#e5483a'>Effacement d'urgence</t>", "", { ["wipe"] call comspec_atak_native_fnc_phoneSecurity; },
    { !(["wiped"] call comspec_atak_native_fnc_phoneSecurity) }] call _add;

[_root, "COMSPEC_ATAK_CableOff", "Débrancher le câble DL-7", "\z\comspec_atak_native\addons\main\data\app_datalink.paa",
    { ["disconnect"] call comspec_atak_native_fnc_dataAction; },
    { ((uiNamespace getVariable ["COMSPEC_ATAK_DataLink", createHashMap]) getOrDefault ["phase", ""]) isNotEqualTo "" }] call _add;
true
