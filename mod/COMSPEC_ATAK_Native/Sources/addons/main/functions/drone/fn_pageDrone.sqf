/*
    App Drone : pilotage d'un drone posé par le joueur (quadricoptère ou drone de sac à dos).
    Appairage au pied du pilote, puis : stationnaire, me suivre, suivre un joueur, retour, atterrissage,
    altitude et vitesse, caméra du drone, charge fixée depuis l'inventaire, tir et oublie (laser du groupe
    ou grille), recherche et frappe du premier ennemi vu. Ordres : fn_droneAction ; exécution : fn_droneCmd.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _fs = _l get "fontSmall";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_droneAction;", str _a, str _v] };
private _rows = [];

if (isNull _d) exitWith {
    // Drones à portée de main : posés au sol à moins de 15 m, sans pilote humain.
    private _near = (player nearEntities [["Air", "Mavic_drone_base_F"], 15]) select { alive _x && {unitIsUAV _x} && {(_x getVariable ["COMSPEC_DroneOwner", ""]) in ["", getPlayerUID player]} };
    _rows append [
        ["title", "Drone"],
        ["text", "<t color='#8a9a93'>Posez votre drone à vos pieds (sac à dos, puis Assembler), puis appairez-le. La liaison porte quelques kilomètres, moins derrière le relief ; perdue, le drone rentre seul à son point de décollage.</t>"]
    ];
    if ((count _near) isEqualTo 0) then { _rows pushBack ["text", "<t color='#f2ab33'>Aucun drone posé à moins de 15 m.</t>"]; };
    {
        _rows pushBack ["person", getText (configOf _x >> "picture"),
            format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 m · carburant %3 %%</t>", getText (configOf _x >> "displayName"), round (player distance _x), round (100 * fuel _x)],
            [["APPAIRER", compile format ["['pair', objectFromNetId %1] call comspec_atak_native_fnc_droneAction;", str netId _x], true]]];
    } forEach _near;
    _rows pushBack ["buttons", [["ACTUALISER", { [{ ["DRONE"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// Télémétrie en haut, rafraîchie chaque seconde.
private _osdH = _fs * 3.6;
private _osd = ["COMSPEC_RscMapPanel", [0, 0, _bw, _osdH]] call comspec_atak_native_fnc_pageCtrl;
_osd ctrlSetStructuredText parseText ([_d, ["link"] call comspec_atak_native_fnc_droneAction] call comspec_atak_native_fnc_droneOsd);
uiNamespace setVariable ["COMSPEC_ATAK_DroneOsd", _osd];

private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
private _alt = _s getOrDefault ["droneAlt", 40];
private _spd = _s getOrDefault ["droneSpd", 40];
private _rad = _s getOrDefault ["droneHuntRad", 250];
private _armed = (_d getVariable ["COMSPEC_DroneArmed", ""]) isNotEqualTo "";
private _camOn = !isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull]);
private _conf = _s getOrDefault ["droneConfirm", ["", 0]];
private _confirming = { params ["_k"]; (_conf select 0) isEqualTo _k && {diag_tickTime - (_conf select 1) < 5} };

if (_camOn) then { _rows pushBack ["image", "#(argb,512,512,1)r2t(comspec_dronecam,1.0)"]; };
if (_m isEqualTo "MANUAL") then { _rows pushBack ["text", "<t color='#f2ab33'>Pilotage manuel : vous seul pouvez connecter un terminal UAV à ce drone. Le moindre ordre du téléphone reprend la main et déconnecte le terminal.</t>"]; };
_rows append [
    ["segment", "Vol", [
        ["STATIONNAIRE", ["mode", "hover"] call _act, _m isEqualTo "HOVER"],
        ["ME SUIVRE", ["mode", "me"] call _act, _m isEqualTo "FOLLOW" && {(_d getVariable ["COMSPEC_DroneFollow", objNull]) isEqualTo player}],
        ["RETOUR", ["mode", "home"] call _act, _m in ["HOME", "RTH"]],
        ["ATTERRIR", ["mode", "land"] call _act, _m isEqualTo "LAND"]
    ]],
    ["segment", "Altitude", ([15, 30, 60, 100, 150] apply { [format ["%1 m", _x], ["alt", _x] call _act, _alt isEqualTo _x] })],
    ["segment", "Vitesse", ([20, 40, 60, 90] apply { [format ["%1 km/h", _x], ["speed", _x] call _act, _spd isEqualTo _x] })],
    ["buttons", [[["CAMÉRA", "COUPER LA CAMÉRA"] select _camOn, ["cam"] call _act, _camOn], ["PILOTAGE MANUEL", ["manual"] call _act, _m isEqualTo "MANUAL"]]],
    ["section", "Suivre un joueur", "Le drone se place 15 m derrière lui"]
];
private _others = (allPlayers select { alive _x && {_x isNotEqualTo player} && {(side group _x) isEqualTo (side group player)} && {(_x distance _d) < 2000} }) apply { [_x distance _d, _x] };
_others sort true;
if ((count _others) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun joueur allié à moins de 2 km du drone.</t>"]; };
{
    private _u = _x select 1;
    private _on = _m isEqualTo "FOLLOW" && {(_d getVariable ["COMSPEC_DroneFollow", objNull]) isEqualTo _u};
    private _pic = [_u] call comspec_atak_native_fnc_avatarPath;
    _rows pushBack ["person", [_pic, "\z\comspec_atak_native\addons\main\data\app_profile.paa"] select (_pic isEqualTo ""),
        format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 · à %3 m du drone</t>", name _u, [_u] call comspec_atak_native_fnc_unitGroup, round (_x select 0)],
        [[["SUIVRE", "SUIVI"] select _on, compile format ["['player', %1] call comspec_atak_native_fnc_droneAction; ['mode', 'player'] call comspec_atak_native_fnc_droneAction;", str getPlayerUID _u], !_on]]];
} forEach (_others select [0, 6]);

_rows pushBack ["section", "Frappe", ["Fixez une charge : drone à moins de 5 m", format ["Armé : %1", _d getVariable ["COMSPEC_DroneArmedLabel", "charge"]]] select _armed];
if !(missionNamespace getVariable ["comspec_atak_native_drone_strike", true]) then {
    _rows pushBack ["text", "<t color='#8a9a93'>Les drones armés sont désactivés sur ce serveur.</t>"];
} else {
    if (!_armed) then {
        _rows pushBack ["buttons", [["ARMER", ["arm"] call _act, true]]];
        _rows pushBack ["text", "<t size='0.85' color='#8a9a93'>Roquette RPG, charge de démolition ou grenade, prise dans votre inventaire.</t>"];
    } else {
        _rows append [
            ["buttons", [[["TIR ET OUBLIE : LASER", "CONFIRMER : LASER"] select (["LASER"] call _confirming), ["strike", "LASER"] call _act, true]]],
            ["edit", "droneGrid", "Grille de la cible (8 ou 10 chiffres)", _s getOrDefault ["droneGridVal", ""]],
            ["buttons", [[["TIR ET OUBLIE : GRILLE", "CONFIRMER : GRILLE"] select (["GRID"] call _confirming), { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["droneGridVal", ["droneGrid", ""] call comspec_atak_native_fnc_formValue]; ["strike", "GRID"] call comspec_atak_native_fnc_droneAction; }, true]]]
        ];
    };
    _rows append [
        ["section", "Recherche et frappe", ["Orbite et signale le premier ennemi vu", "Orbite et frappe le premier ennemi vu"] select _armed],
        ["segment", "Rayon", ([100, 250, 500] apply { [format ["%1 m", _x], ["radius", _x] call _act, _rad isEqualTo _x] })],
        ["buttons", [[["LANCER LA RECHERCHE", "RECHERCHE EN COURS"] select (_m isEqualTo "HUNT"), ["mode", "hunt"] call _act, _m isNotEqualTo "HUNT"]]]
    ];
};
_rows pushBack ["buttons", [["DÉSAPPAIRER", ["unpair"] call _act]]];
[_rows, [0, _osdH, _bw, _bh - _osdH]] call comspec_atak_native_fnc_formRender;
true
