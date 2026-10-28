/*
    Menu ACE du téléphone, rangé sous une seule entrée « Téléphone ATAK » (auto-interaction) :
      Voir l'arrière
      Antenne ▸ visser une antenne de l'inventaire / la retirer
      Personnaliser ▸ Coque (coyote, vert OD, noir) ▸ Module DL-7 (olive, noir, tan) ▸ Patch au dos
      Entretien ▸ batterie, nettoyage, réparation, téléphone de rechange (posés par XEH_postInitClient)
                  + rallumer, réparer l'écran, diagnostic d'Overwatch (repris ici et retirés de « Équipement »)
      Accessoires ▸ batterie externe, filtre d'écran JVN
      Passerelle du véhicule ▸ relais, poste de commandement, couper (équipage) ; aussi sur les véhicules (dehors)
      Relais ATAK (sur l'objet relais) ▸ état, couper, allumer, changer la batterie
      Effacement d'urgence
      Débrancher le câble DL-7 (pendant une extraction)
    Appelé une fois depuis XEH_postInitClient, avant les actions d'entretien.
*/
if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"}) exitWith { false };
private _root = ["ACE_SelfActions", "COMSPEC_ATAK_Phone"];
private _add = {
    params ["_path", "_id", "_name", ["_icon", ""], ["_code", {}], ["_cond", { true }], ["_children", nil]];
    private _a = if (isNil "_children") then {
        [_id, _name, _icon, _code, _cond] call comspec_atak_native_fnc_aceAction
    } else {
        [_id, _name, _icon, _code, _cond, _children] call comspec_atak_native_fnc_aceAction
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
                { ["mount", _this select 2] call comspec_atak_native_fnc_antenna; }, { true }, {}, _id] call comspec_atak_native_fnc_aceAction, [], _target]
        };
        if (_cur isNotEqualTo "") then {
            _out pushBack [["COMSPEC_ATAK_AntOff", "Retirer l'antenne", "", { ["unmount"] call comspec_atak_native_fnc_antenna; }, { true }] call comspec_atak_native_fnc_aceAction, [], _target];
        };
        _out
    }] call _add;

// Personnaliser : couleur de la coque et du module DL-7 (mêmes réglages que l'app Réglages).
[_root, "COMSPEC_ATAK_Perso", "Personnaliser", "\z\comspec_atak_native\addons\main\data\app_settings.paa"] call _add;
private _set = {
    params ["_var", "_val"];
    profileNamespace setVariable [_var, _val];
    [] call comspec_atak_native_fnc_saveProfile;
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
    ["COMSPEC_ATAK_ModuleTint", "olive", "COMSPEC_ATAK_PersoModule", "Couleur du module DL-7", [["Olive", "olive"], ["Noir", "black"], ["Tan", "tan"]]],
    ["COMSPEC_ATAK_Patch", "none", "COMSPEC_ATAK_PersoPatch", "Patch au dos", [["Aucun", "none"], ["France", "fr"], ["France basse visibilité", "fr_sub"], ["USA", "us"], ["USA basse visibilité", "us_ir"],
        ["Crâne", "skull"], ["Croix", "crusader"], ["Alien", "alien"], ["COMSPEC", "comspec"], ["Nominatif", "name"],
        ["Insigne de l'uniforme", "uniform"]]],
    ["COMSPEC_ATAK_VestMount", "off", "COMSPEC_ATAK_PersoVest", "Téléphone sur le gilet", [["Non", "off"], ["Poitrine gauche", "left"], ["Centre", "center"], ["Poitrine droite", "right"]]]
];

// Gilet : compatibilité avec les gilets qui ont déjà un ATAK modélisé (fn_vestBuiltin, réglages serveur « ATAK · Gilet »).
[_root, "COMSPEC_ATAK_Vest", "Gilet", "\z\comspec_atak_native\addons\main\data\item_phone.paa", {}, { vest _player isNotEqualTo "" }] call _add;
private _vestPath = _root + ["COMSPEC_ATAK_Vest"];
private _vestUse = {
    params ["_use"];
    profileNamespace setVariable ["COMSPEC_ATAK_VestBuiltinUse", _use];
    if (_use isEqualTo "ours" && {(profileNamespace getVariable ["COMSPEC_ATAK_VestMount", "off"]) isEqualTo "off"}) then { profileNamespace setVariable ["COMSPEC_ATAK_VestMount", "left"]; };
    [] call comspec_atak_native_fnc_saveProfile;
    ["tick"] call comspec_atak_native_fnc_vestMount;
};
missionNamespace setVariable ["COMSPEC_ATAK_VestUse", _vestUse];
[_vestPath, "COMSPEC_ATAK_VestOurs", "Mettre aussi notre téléphone sur ce gilet", "", { ["ours"] call (missionNamespace getVariable "COMSPEC_ATAK_VestUse"); },
    { [_player] call comspec_atak_native_fnc_vestBuiltin && {(profileNamespace getVariable ["COMSPEC_ATAK_VestBuiltinUse", "vest"]) isNotEqualTo "ours"} }] call _add;
[_vestPath, "COMSPEC_ATAK_VestTheirs", "Garder seulement l'ATAK du gilet", "", { ["vest"] call (missionNamespace getVariable "COMSPEC_ATAK_VestUse"); },
    { [_player] call comspec_atak_native_fnc_vestBuiltin && {(profileNamespace getVariable ["COMSPEC_ATAK_VestBuiltinUse", "vest"]) isEqualTo "ours"} }] call _add;
// Gilet non reconnu (ou reconnu à tort) : le joueur le marque lui-même, retenu dans son profil.
private _vestMark = {
    params ["_on"];
    private _v = toLower vest player;
    private _mine = (profileNamespace getVariable ["COMSPEC_ATAK_VestBuiltinMine", []]) select { (toLower _x) isNotEqualTo _v };
    private _not = (profileNamespace getVariable ["COMSPEC_ATAK_VestBuiltinNot", []]) select { (toLower _x) isNotEqualTo _v };
    if (_on) then { _mine pushBack _v; } else { _not pushBack _v; };
    profileNamespace setVariable ["COMSPEC_ATAK_VestBuiltinMine", _mine];
    profileNamespace setVariable ["COMSPEC_ATAK_VestBuiltinNot", _not];
    [] call comspec_atak_native_fnc_saveProfile;
    ["tick"] call comspec_atak_native_fnc_vestMount;
};
missionNamespace setVariable ["COMSPEC_ATAK_VestMark", _vestMark];
[_vestPath, "COMSPEC_ATAK_VestMarkOn", "Mon gilet a déjà un ATAK", "", { [true] call (missionNamespace getVariable "COMSPEC_ATAK_VestMark"); },
    { !([_player] call comspec_atak_native_fnc_vestBuiltin) }] call _add;
[_vestPath, "COMSPEC_ATAK_VestMarkOff", "Mon gilet n'a pas d'ATAK", "", { [false] call (missionNamespace getVariable "COMSPEC_ATAK_VestMark"); },
    { [_player] call comspec_atak_native_fnc_vestBuiltin }] call _add;

// Tablette durcie sur le rabat du JPC, écran ATAK en 3D (fn_jpcScreen). En chantier (W.I.P) : actions masquées.
[_root, "COMSPEC_ATAK_JpcOpen", "Ouvrir l'ATAK sur le JPC", "", { ["toggle"] call comspec_atak_native_fnc_jpcScreen; },
    { !(["wip"] call comspec_atak_native_fnc_jpcScreen) && {vest _player isNotEqualTo ""} && {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["texture", false]) || {isNull ([] call comspec_atak_native_fnc_display)}} }] call _add;
[_root, "COMSPEC_ATAK_JpcClose", "Rabattre la tablette du JPC", "", { ["toggle"] call comspec_atak_native_fnc_jpcScreen; },
    { !isNull ([] call comspec_atak_native_fnc_display) && {(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["texture", false]} }] call _add;

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
    // Phrase source en français, traduite à l'affichage (fn_notificationsRender) : chaque « Libellé : valeur » est dans le
    // dictionnaire (« Power: ✗ Off »). Traduite morceau par morceau ici, elle gardait l'espace français avant les deux-points.
    private _ok = { params ["_on", "_bad"]; ["✗ " + _bad, "✓ OK"] select _on };
    // Dans le centre de notifications du téléphone, pas sur l'écran du jeu.
    ["INFO", format ["ATAK · État · Alimentation : %1 · Écran : %2 · Liaison : %3%4",
        [_s get "powered_on", "Éteint"] call _ok, [_s get "screen_ok", "Détruit"] call _ok, [_s get "connection_ok", "Coupée"] call _ok,
        ["", " · Téléphone détruit"] select (_s get "device_destroyed")], 8, 40] call comspec_atak_native_fnc_notify;
}, { call (missionNamespace getVariable "COMSPEC_ATAK_OwRepair") }] call _add;
// Les mêmes actions posées par Overwatch dans « Équipement » sont retirées une fois en place (doublons).
[{ missionNamespace getVariable ["COMSPEC_AtakRepairReady", false] }, {
    {
        ["CAManBase", 1, ["ACE_SelfActions", "ACE_Equipment", _x]] call ace_interact_menu_fnc_removeActionFromClass;
        if (!isNull player) then { [typeOf player, 1, ["ACE_SelfActions", "ACE_Equipment", _x]] call ace_interact_menu_fnc_removeActionFromClass; };
    } forEach ["COMSPEC_RepairAtakPower", "COMSPEC_RepairAtakScreen", "COMSPEC_DiagnosticAtak"];
}, [], 120] call CBA_fnc_waitUntilAndExecute;

// Accessoires : batterie externe sur la plaque, filtre d'écran JVN.
[_root, "COMSPEC_ATAK_Acc", "Accessoires", "\z\comspec_atak_native\addons\main\data\item_powerbank.paa"] call _add;
private _acc = _root + ["COMSPEC_ATAK_Acc"];
[_acc, "COMSPEC_ATAK_PbOn", "Fixer la batterie externe", "\z\comspec_atak_native\addons\main\data\item_powerbank.paa", { ["attach"] call comspec_atak_native_fnc_powerBank; },
    { !(missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false]) && {["has"] call comspec_atak_native_fnc_powerBank} }] call _add;
[_acc, "COMSPEC_ATAK_PbOff", "Retirer la batterie externe", "", { ["detach"] call comspec_atak_native_fnc_powerBank; },
    { missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false] }] call _add;
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
            _out pushBack [[format ["COMSPEC_ATAK_Gw_%1", _mode], _txt, "", { (_this select 2) params ["_m", "_veh"]; [_m, _veh] call comspec_atak_native_fnc_vehicleGateway; }, { true }, {}, [_mode, _v]] call comspec_atak_native_fnc_aceAction, [], _v];
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
        { [_target] call (missionNamespace getVariable "COMSPEC_ATAK_GwKids") }] call comspec_atak_native_fnc_aceAction;
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
        }, {}, ("Installation du terminal ATAK…" call comspec_atak_native_fnc_tr)] call ace_common_fnc_progressBar;
    }, {
        (missionNamespace getVariable ["comspec_atak_native_vehicle_terminal", "all"]) isEqualTo "kit" && {alive _target}
            && {!(_target getVariable ["COMSPEC_ATAK_VehTerm", false])} && {"COMSPEC_ATAK_VehicleKit" in (items _player)}
    }] call comspec_atak_native_fnc_aceAction;
    [_x, 0, ["ACE_MainActions"], _k, true] call ace_interact_menu_fnc_addActionToClass;
} forEach ["LandVehicle", "Ship", "Helicopter"];

// Relais du réseau ATAK (module « ATAK · Relais » ou Overwatch), sur l'objet relais : état, couper, allumer, batterie.
// Tout le monde peut couper un relais trouvé sur le terrain, ami comme ennemi.
{
    private _cls = _x;
    private _r = ["COMSPEC_ATAK_Relay", "Relais ATAK", "\A3\ui_f\data\map\mapcontrol\transmitter_CA.paa", {},
        { _target getVariable ["COMSPEC_AtakRelay", false] && {alive _target} }] call comspec_atak_native_fnc_aceAction;
    [_cls, 0, ["ACE_MainActions"], _r, true] call ace_interact_menu_fnc_addActionToClass;
    private _path = ["ACE_MainActions", "COMSPEC_ATAK_Relay"];
    {
        _x params ["_id", "_name", "_code", "_cond"];
        [_cls, 0, _path, [_id, _name, "", _code, _cond] call comspec_atak_native_fnc_aceAction, true] call ace_interact_menu_fnc_addActionToClass;
    } forEach [
        ["COMSPEC_ATAK_RelayInfo", "État du relais", { ["info", _target] call comspec_atak_native_fnc_relayNet; }, { true }],
        ["COMSPEC_ATAK_RelayOff", "Couper le relais", { ["power", [_target, "off"]] call comspec_atak_native_fnc_relayNet; }, { _target getVariable ["COMSPEC_AtakRelayOn", true] }],
        ["COMSPEC_ATAK_RelayOn", "Allumer le relais", { ["power", [_target, "on"]] call comspec_atak_native_fnc_relayNet; }, { !(_target getVariable ["COMSPEC_AtakRelayOn", true]) }],
        ["COMSPEC_ATAK_RelayBatt", "Changer la batterie", {
            [10, _target, { ["power", [_this select 0, "battery"]] call comspec_atak_native_fnc_relayNet; }, {}, ("Changement de la batterie du relais…" call comspec_atak_native_fnc_tr)] call ace_common_fnc_progressBar;
        }, { private _b = _target getVariable ["COMSPEC_AtakRelayBatt", -1]; _b isEqualType 0 && {_b >= 0} && {_b < 95} }]
    ];
} forEach ["Static", "Thing", "LandVehicle", "Ship", "Air"];

[_root, "COMSPEC_ATAK_Wipe", "<t color='#e5483a'>Effacement d'urgence</t>", "", { ["wipe"] call comspec_atak_native_fnc_phoneSecurity; },
    { !(["wiped"] call comspec_atak_native_fnc_phoneSecurity) }] call _add;

[_root, "COMSPEC_ATAK_CableOff", "Débrancher le câble DL-7", "\z\comspec_atak_native\addons\main\data\app_datalink.paa",
    { ["disconnect"] call comspec_atak_native_fnc_dataAction; },
    { ((uiNamespace getVariable ["COMSPEC_ATAK_DataLink", createHashMap]) getOrDefault ["phase", ""]) isNotEqualTo "" }] call _add;

// Caméras IP fixes : « Caméras > Poser une caméra » et actions sur la caméra posée (fn_camAction).
["aceInit"] call comspec_atak_native_fnc_camAction;
// Écrans du jeu (TV, trépied, projecteur…) : TOC, PIP du poste et diapositives via ACE (fn_tocScreen).
["aceInit"] call comspec_atak_native_fnc_tocScreen;
true
