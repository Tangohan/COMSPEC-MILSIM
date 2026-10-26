/*
    PC portable de TOC : poste de commandement local, sans Athena ni site web.
    Un joueur à pied à moins de 2,5 m d'un PC de TOC a l'ATAK du poste (fn_vehicleTerminal "toc", tablette agrandie,
    alimentée par le secteur) et l'app Poste TOC : message à tout le camp, ordres, situation des groupes, comptes rendus.
    PC de TOC : objet COMSPEC_ATAK_TocLaptop (Eden, Zeus), tout objet avec this setVariable ["COMSPEC_ATAK_Toc", true, true],
    ou n'importe quel ordinateur portable posé (réglage « Tous les ordinateurs portables servent de PC de TOC »).
    Camp réservé (attribut Eden ou variable COMSPEC_ATAK_TocSide "WEST" | "EAST" | "GUER" | "CIV") : vide = tous les camps.
    Params : [action, argument]
      "tick"          (1 s, client) cherche le PC de TOC le plus proche -> missionNamespace COMSPEC_ATAK_TocNear ;
                      publie COMSPEC_ATAK_TocOn (indicatif du poste) sur mon unité tant que j'y suis (réponses des joueurs)
      "near"          -> PC de TOC à portée (objNull sinon)
      "isToc" objet   -> true si l'objet sert de PC de TOC pour moi
      "open" objet    ouvre l'ATAK du poste en grand, sur l'app Poste TOC
      "callsign"      -> indicatif du poste (config.json, toc.callsign)
      "menu"          actions ACE / molette (appelé une fois par XEH_postInitClient) ; "action" : action de la molette
*/
params [["_act", "near"], ["_arg", objNull]];
private _laptops = ["Land_Laptop_unfolded_F", "Land_Laptop_device_F", "Land_Laptop_F", "Land_Laptop_02_unfolded_F", "Land_Laptop_03_black_F", "Land_Laptop_03_olive_F", "Land_Laptop_03_sand_F", "Land_MultiScreenComputer_01_black_F", "Land_MultiScreenComputer_01_olive_F", "Land_MultiScreenComputer_01_sand_F", "Land_PCSet_01_screen_F"];
private _isToc = {
    params ["_o"];
    if (isNull _o || {!alive _o}) exitWith { false };
    private _ok = _o isKindOf "COMSPEC_ATAK_TocLaptop" || {_o getVariable ["COMSPEC_ATAK_Toc", false]}
        || {(missionNamespace getVariable ["comspec_atak_native_toc_anylaptop", false]) && {(typeOf _o) in _laptops}};
    if !(_ok) exitWith { false };
    private _side = toUpper (_o getVariable ["COMSPEC_ATAK_TocSide", ""]);
    _side isEqualTo "" || {_side isEqualTo str side group player}
};
switch (_act) do {
    case "isToc": { [_arg] call _isToc };
    case "near": {
        private _o = missionNamespace getVariable ["COMSPEC_ATAK_TocNear", objNull];
        [objNull, _o] select (!isNull _o && {alive player} && {isNull objectParent player} && {(player distance _o) < 3.5})
    };
    case "callsign": { ((["get"] call comspec_atak_native_fnc_localCfg) get "toc") get "callsign" };
    case "tick": {
        if (!hasInterface || {isNull player}) exitWith {};
        private _near = objNull;
        if (alive player && {isNull objectParent player} && {missionNamespace getVariable ["comspec_atak_native_toc_enabled", true]}) then {
            private _cands = (nearestObjects [player, [], 2.5, true]) select { [_x] call _isToc };
            if (_cands isNotEqualTo []) then { _near = _cands select 0; };
        };
        private _was = missionNamespace getVariable ["COMSPEC_ATAK_TocNear", objNull];
        missionNamespace setVariable ["COMSPEC_ATAK_TocNear", _near];
        private _cs = ["", ["callsign"] call comspec_atak_native_fnc_tocPost] select !isNull _near;
        if ((player getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo _cs) then { player setVariable ["COMSPEC_ATAK_TocOn", _cs, true]; };
        if (isNull _near && {!isNull _was}) then {
            // Parti du poste : l'app Poste TOC se ferme.
            if ((((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "TOC") && {!isNull ([] call comspec_atak_native_fnc_display)}) then {
                ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
            };
        };
    };
    case "open": {
        if !([_arg] call _isToc) exitWith { ["WARNING", "Ce PC n'est pas un poste TOC de votre camp", 3, 30] call comspec_atak_native_fnc_notify; };
        missionNamespace setVariable ["COMSPEC_ATAK_TocNear", _arg];
        player setVariable ["COMSPEC_ATAK_TocOn", ["callsign"] call comspec_atak_native_fnc_tocPost, true];
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if (isNull ([] call comspec_atak_native_fnc_display) || {!(_s getOrDefault ["interactive", false])}) then {
            if (isNull ([] call comspec_atak_native_fnc_display)) then { [true] call comspec_atak_native_fnc_open; } else { [] call comspec_atak_native_fnc_interactToggle; };
        };
        [{ ["TOC"] call comspec_atak_native_fnc_navigate; }, [], 0.2] call CBA_fnc_waitAndExecute;
    };
    case "menu": {
        if (!hasInterface) exitWith {};
        private _icon = "\z\comspec_atak_native\addons\main\data\app_toc.paa";
        if (!isNil "ace_interact_menu_fnc_createAction") then {
            private _a = ["COMSPEC_ATAK_TocOpen", "Poste TOC (ATAK)", _icon, { ["open", _target] call comspec_atak_native_fnc_tocPost; },
                { ["isToc", _target] call comspec_atak_native_fnc_tocPost && {isNull objectParent _player} }] call comspec_atak_native_fnc_aceAction;
            ["COMSPEC_ATAK_TocLaptop", 0, ["ACE_MainActions"], _a, true] call ace_interact_menu_fnc_addActionToClass;
            // Ordinateurs portables du jeu (réglage) et objets désignés par la mission : même action.
            { [_x, 0, ["ACE_MainActions"], _a] call ace_interact_menu_fnc_addActionToClass; } forEach _laptops;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocLaptops", _laptops];
        // Sans ACE (ou objet désigné par la mission, sans menu ACE) : action de la molette, remise à chaque réapparition.
        ["action"] call comspec_atak_native_fnc_tocPost;
        player addEventHandler ["Respawn", { [{ ["action"] call comspec_atak_native_fnc_tocPost; }, [], 1] call CBA_fnc_waitAndExecute; }];
    };
    case "action": {
        player addAction ["<t color='#7fd0b0'>Poste TOC (ATAK)</t>", { ["open", cursorObject] call comspec_atak_native_fnc_tocPost; }, nil, 1.3, false, true, "",
            "isNull objectParent _this && {(_this distance cursorObject) < 2.5} && {['isToc', cursorObject] call comspec_atak_native_fnc_tocPost} && {isNil 'ace_interact_menu_fnc_createAction' || {!(cursorObject isKindOf 'COMSPEC_ATAK_TocLaptop') && {!((typeOf cursorObject) in (missionNamespace getVariable ['COMSPEC_ATAK_TocLaptops', []]))}}}"];
    };
};
