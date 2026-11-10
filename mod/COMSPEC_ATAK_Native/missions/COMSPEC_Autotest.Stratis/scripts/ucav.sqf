// UCAV commandé depuis le téléphone (fn_ucav, fn_ucavCmd, vue UCAV de l'app Drone) : Greyhawk armé pris en main et mis
// en orbite, véhicule ennemi marqué puis frappé (tir et impact vérifiés), puis Rechercher et tuer en « Proposer » (la
// cible détectée attend la validation : aucun tir sans elle). Chaque étape retire ce qu'elle a posé.
if !("ucav" call AT_fnc_groupe) exitWith {};
AT_fnc_ucavPose = {
    // Greyhawk armé en vol à 1 000 m, 2 km au sud du joueur : pylône 1 missiles guidés, pylône 2 bombe guidée.
    params [["_cote", 180]];
    private _p = (getPosATL player) getPos [2000, _cote];
    private _u = createVehicle ["B_UAV_02_dynamicLoadout_F", [_p select 0, _p select 1, 1000], [], 0, "FLY"];
    createVehicleCrew _u;
    _u setPosATL [_p select 0, _p select 1, 1000];
    _u setDir (_cote + 180);
    _u setVelocityModelSpace [0, 70, 0];
    {
        private _i = _forEachIndex + 1;
        private _cle = _x select 0;
        private _ok = (_u getCompatiblePylonMagazines _i) select { ((toLower _x) find _cle) >= 0 };
        if ((count _ok) > 0) then { _u setPylonLoadout [_i, _ok select 0, true, [0]]; };
    } forEach [["scalpel"], ["bomb_04"]];
    _u
};
AT_fnc_ucavCible = {
    // Ifrit ennemi avec équipage, immobilisé (sans carburant), sur un terrain plat à la distance et au cap donnés.
    params ["_dist", "_cap"];
    private _p = (getPosATL player) getPos [_dist, _cap];
    _p = [_p, 0, 200, 8, 0, 0.3, 0] call BIS_fnc_findSafePos;
    private _v = createVehicle ["O_MRAP_02_F", [_p select 0, _p select 1, 0], [], 0, "NONE"];
    createVehicleCrew _v;
    _v setFuel 0;
    _v
};
AT_fnc_ucavNettoie = {
    params ["_objs"];
    if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_Ucav", objNull])) then {
        ["release"] call comspec_atak_native_fnc_ucav;
        uiSleep 0.3;
        ["release"] call comspec_atak_native_fnc_ucav;
    };
    { if (!isNull _x) then { { deleteVehicle _x; } forEach (crew _x); deleteVehicle _x; }; } forEach _objs;
    (call AT_fnc_etat) set ["droneUcav", false];
};

["ucav_frappe", {
    if (["comspec_atak_native_fnc_ucav", "comspec_atak_native_fnc_ucavCmd", "comspec_atak_native_fnc_pageUcav"] call AT_fnc_nil) exitWith {};
    if !(call AT_fnc_tel) exitWith { "téléphone absent" call AT_fnc_skip; };
    private _u = [180] call AT_fnc_ucavPose;
    private _v = [1500, 200] call AT_fnc_ucavCible;
    uiSleep 2;
    [["is", _u] call comspec_atak_native_fnc_ucav, "Greyhawk armé non reconnu comme UCAV"] call AT_fnc_check;
    private _ws = ["weapons", _u] call comspec_atak_native_fnc_ucav;
    [(count _ws) > 0, "aucune arme guidée trouvée sur le Greyhawk"] call AT_fnc_check;
    format ["armes : %1", _ws apply { [_x select 0, _x select 2, _x select 4] }] call AT_fnc_info;
    [(["can", player] call comspec_atak_native_fnc_ucav) isEqualTo "", "joueur seul non autorisé à commander"] call AT_fnc_check;
    // Prise en main puis orbite au-dessus de la cible.
    ["take", netId _u] call comspec_atak_native_fnc_ucav;
    [{ (missionNamespace getVariable ["COMSPEC_ATAK_Ucav", objNull]) isEqualTo _u }, 10] call AT_fnc_wait;
    [(missionNamespace getVariable ["COMSPEC_ATAK_Ucav", objNull]) isEqualTo _u, "prise en main sans réponse"] call AT_fnc_check;
    [((_u getVariable ["COMSPEC_UcavCtl", []]) param [0, ""]) isEqualTo (getPlayerUID player), "contrôleur non publié sur l'UCAV"] call AT_fnc_check;
    (call AT_fnc_etat) set ["ucavAlt", 1000];
    (call AT_fnc_etat) set ["ucavRad", 1200];
    ["orbit", getPosASL _v] call comspec_atak_native_fnc_ucav;
    uiSleep 3;
    [(_u getVariable ["COMSPEC_UcavMode", ""]) isEqualTo "ORBIT", "mode orbite non posé"] call AT_fnc_check;
    private _wps = waypoints (group driver _u);
    [(count _wps) > 0 && {(waypointType (_wps select ((count _wps) - 1))) isEqualTo "LOITER"}, "pas de point de passage LOITER"] call AT_fnc_check;
    (call AT_fnc_etat) set ["droneUcav", true];
    ["DRONE", 2] call AT_fnc_page;
    ["ucav_orbite"] call AT_fnc_shot;
    // Cible marquée (marquage suivi), sinon désignée comme depuis la carte ; frappe à deux appuis, arme choisie par l'UCAV.
    private _src = "PEND";
    private _k = if (isNil "comspec_atak_native_fnc_lockAction") then { "" } else { ["mark", "PHONE", _v] call comspec_atak_native_fnc_lockAction };
    if (_k isEqualType "" && {_k isNotEqualTo ""}) then { _src = "LOCK:" + _k; } else { (call AT_fnc_etat) set ["ucavPend", [_v, getPosASL _v, "Ifrit de test"]]; };
    format ["source de la frappe : %1", _src] call AT_fnc_info;
    ["strike", _src] call comspec_atak_native_fnc_ucav;
    [(count (_u getVariable ["COMSPEC_UcavQ", []])) isEqualTo 0, "frappe partie sans le second appui"] call AT_fnc_check;
    uiSleep 0.5;
    ["strike", _src] call comspec_atak_native_fnc_ucav;
    [{ (count (_u getVariable ["COMSPEC_UcavQ", []])) > 0 || {((_u getVariable ["COMSPEC_UcavSt", []]) param [0, ""]) in ["ENROUTE", "INPOS", "LOCK", "FIRE"]} }, 8] call AT_fnc_wait;
    [((_u getVariable ["COMSPEC_UcavSt", []]) param [0, ""]) in ["ENROUTE", "INPOS", "LOCK", "FIRE", "IMPACT", "BDA"], "frappe non mise en file"] call AT_fnc_check;
    private _ammo0 = (_ws apply { _x select 2 }) call { private _n = 0; { _n = _n + _x; } forEach _this; _n };
    // Tir : la munition part (état TIR, une munition de moins), puis impact et BDA.
    [{ ((_u getVariable ["COMSPEC_UcavSt", []]) param [0, ""]) in ["FIRE", "IMPACT", "BDA", "FAIL"] }, 300] call AT_fnc_wait;
    ["ucav_tir"] call AT_fnc_shot;
    private _st = (_u getVariable ["COMSPEC_UcavSt", []]) param [0, ""];
    format ["état après l'approche : %1 (%2)", _st, (_u getVariable ["COMSPEC_UcavSt", []]) param [1, ""]] call AT_fnc_info;
    [_st in ["FIRE", "IMPACT", "BDA"], format ["pas de tir (état %1)", _st]] call AT_fnc_check;
    private _ammo1 = ((["weapons", _u] call comspec_atak_native_fnc_ucav) apply { _x select 2 }) call { private _n = 0; { _n = _n + _x; } forEach _this; _n };
    [_ammo1 < _ammo0, "aucune munition consommée"] call AT_fnc_check;
    [{ ((_u getVariable ["COMSPEC_UcavSt", []]) param [0, ""]) in ["BDA", "FAIL"] }, 120] call AT_fnc_wait;
    (_u getVariable ["COMSPEC_UcavSt", []]) params [["_k2", ""], ["_t2", ""], ["_imp", []]];
    format ["fin de frappe : %1 (%2), cible vivante %3, dégâts %4", _k2, _t2, alive _v, damage _v] call AT_fnc_info;
    [_k2 isEqualTo "BDA", format ["pas d'impact suivi d'un BDA (état %1)", _k2]] call AT_fnc_check;
    private _ecart = if ((count _imp) >= 2) then { round (_imp distance2D _v) } else { -1 };
    [_ecart >= 0 && {_ecart < 60}, format ["impact loin de la cible (%1 m)", _ecart]] call AT_fnc_check;
    [!alive _v || {(damage _v) > 0.2}, "cible intacte après l'impact"] call AT_fnc_check;
    [(count (missionNamespace getVariable ["COMSPEC_ATAK_UcavBda", []])) >= 7, "BDA non reçu au téléphone"] call AT_fnc_check;
    ["ucav_bda"] call AT_fnc_shot;
    [[_u, _v]] call AT_fnc_ucavNettoie;
    call AT_fnc_done;
}, 480] call AT_fnc_run;

["ucav_rechercher_proposer", {
    if (["comspec_atak_native_fnc_ucav"] call AT_fnc_nil) exitWith {};
    if !(call AT_fnc_tel) exitWith { "téléphone absent" call AT_fnc_skip; };
    private _u = [90] call AT_fnc_ucavPose;
    private _v = [1500, 80] call AT_fnc_ucavCible;
    uiSleep 2;
    ["take", netId _u] call comspec_atak_native_fnc_ucav;
    [{ (missionNamespace getVariable ["COMSPEC_ATAK_Ucav", objNull]) isEqualTo _u }, 10] call AT_fnc_wait;
    if !((missionNamespace getVariable ["COMSPEC_ATAK_Ucav", objNull]) isEqualTo _u) exitWith { ["prise en main sans réponse"] call AT_fnc_check; [[_u, _v]] call AT_fnc_ucavNettoie; call AT_fnc_done; };
    ["roe", "PROPOSE"] call comspec_atak_native_fnc_ucav;
    (call AT_fnc_etat) set ["ucavRad", 1200];
    ["hunt", getPosASL _v] call comspec_atak_native_fnc_ucav;
    uiSleep 3;
    [(_u getVariable ["COMSPEC_UcavMode", ""]) isEqualTo "HUNT", "mode Rechercher et tuer non posé"] call AT_fnc_check;
    [(count (_u getVariable ["COMSPEC_UcavZone", []])) >= 5, "zone de recherche non publiée"] call AT_fnc_check;
    // La cible détectée par les capteurs est proposée, jamais frappée sans validation.
    [{ ((_u getVariable ["COMSPEC_UcavDet", []]) findIf { (_x select 1) isEqualTo _v && {(_x select 6) isEqualTo "PROPOSED"} }) >= 0 }, 240] call AT_fnc_wait;
    private _e = (_u getVariable ["COMSPEC_UcavDet", []]) select { (_x select 1) isEqualTo _v };
    format ["détections : %1", (_u getVariable ["COMSPEC_UcavDet", []]) apply { [_x select 0, _x select 2, _x select 4, _x select 6] }] call AT_fnc_info;
    [(count _e) > 0 && {((_e select 0) select 6) isEqualTo "PROPOSED"}, "véhicule ennemi non proposé par Rechercher et tuer"] call AT_fnc_check;
    [(count (_u getVariable ["COMSPEC_UcavQ", []])) isEqualTo 0, "frappe partie sans validation en mode Proposer"] call AT_fnc_check;
    (call AT_fnc_etat) set ["droneUcav", true];
    (call AT_fnc_etat) set ["ucavTab", "TARGETS"];
    ["DRONE", 2] call AT_fnc_page;
    ["ucav_proposition"] call AT_fnc_shot;
    // Validation (deux appuis) : la frappe entre en file.
    if ((count _e) > 0) then {
        private _key = (_e select 0) select 0;
        ["strike", "DET:" + _key] call comspec_atak_native_fnc_ucav;
        uiSleep 0.5;
        ["strike", "DET:" + _key] call comspec_atak_native_fnc_ucav;
        [{ (count (_u getVariable ["COMSPEC_UcavQ", []])) > 0 }, 8] call AT_fnc_wait;
        [(count (_u getVariable ["COMSPEC_UcavQ", []])) > 0, "validation de la cible proposée sans effet"] call AT_fnc_check;
    };
    [[_u, _v]] call AT_fnc_ucavNettoie;
    call AT_fnc_done;
}, 330] call AT_fnc_run;
