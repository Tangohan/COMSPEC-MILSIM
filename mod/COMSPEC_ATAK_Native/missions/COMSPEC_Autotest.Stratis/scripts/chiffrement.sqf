// Chiffrement plus réaliste (fn_meshKeys) : chargeur de clés, période cryptographique, effacement d'urgence, interception, coût, barre d'état.
// Temps simulé : l'heure de chargement de la clé (COMSPEC_ATAK_MeshKeysAt) est reculée, rien n'attend deux heures.
// Aucune radio ACRE2 ni TFAR n'est touchée : la voix par le maillage et le code TFAR restent coupés.
if !("chiffrement" call AT_fnc_groupe) exitWith {};
AT_ChVars = ["comspec_atak_native_mesh_keys", "comspec_atak_native_mpu_real", "comspec_atak_native_mesh_keys_fill", "comspec_atak_native_mesh_intercept"];
AT_ChNet = {
    private _s = str side group player;
    private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) findIf { (_x param [4, ""]) isEqualTo _s && {_x param [5, false]} };
    if (_i < 0) exitWith { [] };
    (missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs") select _i
};
AT_ChState = { private _r = call AT_ChNet; if (_r isEqualTo []) exitWith { "aucune clé" }; ["state", [_r select 0]] call comspec_atak_native_fnc_meshKeys };
AT_ChLoad = {
    ["request", [true]] call comspec_atak_native_fnc_meshKeys;
    [{ (call AT_ChState) isEqualTo "valide" }, 10] call AT_fnc_wait
};

["chiffrement_charge", {
    if (["comspec_atak_native_fnc_meshKeys", "comspec_atak_native_fnc_mpuLink"] call AT_fnc_nil) exitWith {};
    { missionNamespace setVariable [_x, true, true]; } forEach AT_ChVars;
    missionNamespace setVariable ["comspec_atak_native_mesh_keys_period", 120, true];
    missionNamespace setVariable ["comspec_atak_native_mesh_crypto_cost", 1, true];
    { if !(_x in (items player)) then { player addItem _x; }; } forEach ["COMSPEC_ATAK_Phone", "COMSPEC_ATAK_MPU5", "COMSPEC_ATAK_EudCable", "COMSPEC_ATAK_KeyLoader", "COMSPEC_ATAK_Goniometer"];
    uiSleep 2;
    ["link", ["cable"]] call comspec_atak_native_fnc_mpuLink;
    [[{ missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysTake", false] }, 10] call AT_fnc_wait, "tick des clés jamais lancé"] call AT_fnc_check;
    [call AT_ChLoad, format ["clé de réseau non chargée : %1", call AT_ChState]] call AT_fnc_check;
    private _d = ["diag"] call comspec_atak_native_fnc_meshKeys;
    [(_d get "period") isEqualTo 7200, format ["période %1 s au lieu de 7200", _d get "period"]] call AT_fnc_check;
    [(_d get "left") > 7000, format ["clé fraîche : reste %1 s", _d get "left"]] call AT_fnc_check;
    [(_d get "fill") && {_d get "intercept"}, "chargeur ou interception non pris en compte"] call AT_fnc_check;
    private _code = _d get "code";
    [_code in ["enc", "clear"], format ["cadenas %1 avec une clé valide", _code]] call AT_fnc_check;
    if (_code isEqualTo "clear") then { (format ["clé valide hors maillage : %1", _d get "why"]) call AT_fnc_info; };
    // Coût : seulement liaison chiffrée.
    private _cost = ["cost"] call comspec_atak_native_fnc_meshKeys;
    [[(_cost select 0) isEqualTo 0, (_cost select 0) > 0] select (_code isEqualTo "enc"), format ["coût %1 pour un cadenas %2", _cost, _code]] call AT_fnc_check;
    // Clés portées publiées : un appareil ramassé compromet celles-là.
    [((player getVariable ["COMSPEC_ATAK_MeshKeysHeld", -1]) isEqualType []) && {(count (player getVariable ["COMSPEC_ATAK_MeshKeysHeld", []])) > 0}, "clés portées non publiées"] call AT_fnc_check;
    if (call AT_fnc_tel) then { uiSleep 2; ["chiffrement_barre"] call AT_fnc_shot; };
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["chiffrement_periode", {
    if (["comspec_atak_native_fnc_meshKeys"] call AT_fnc_nil) exitWith {};
    private _row = call AT_ChNet;
    if (_row isEqualTo []) exitWith { "aucune clé de réseau (étape précédente)" call AT_fnc_skip; };
    private _id = _row select 0;
    private _now = [time, serverTime] select isMultiplayer;
    private _at = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysAt", createHashMap];
    // Plus que 5 min : clé encore valide, à renouveler.
    _at set [_id, _now - 6900];
    private _d = ["diag"] call comspec_atak_native_fnc_meshKeys;
    [(call AT_ChState) isEqualTo "valide" && {(_d get "left") > 0} && {(_d get "left") <= 600}, format ["T-5 min : %1, reste %2 s", call AT_ChState, _d get "left"]] call AT_fnc_check;
    [(_d get "code") in ["soon", "clear"], format ["T-5 min : cadenas %1", _d get "code"]] call AT_fnc_check;
    // Expirée.
    _at set [_id, _now - 7300];
    [(call AT_ChState) isEqualTo "périmée", format ["après 2 h : %1", call AT_ChState]] call AT_fnc_check;
    private _l = ["link"] call comspec_atak_native_fnc_meshKeys;
    [(_l select 0) isEqualTo "expired" && {(_l select 1) isEqualTo "PÉRIMÉE"} && {(_l select 2) isEqualTo "CLÉ PÉRIMÉE"}, format ["cadenas %1", _l select [0, 3]]] call AT_fnc_check;
    [(["nodeKey"] call comspec_atak_native_fnc_meshKeys) isEqualTo -1, "clé périmée : nœud encore dans le graphe"] call AT_fnc_check;
    [((["open", [[_id, _row select 2], "secret"]] call comspec_atak_native_fnc_meshKeys) find "[CHIFFRÉ") isEqualTo 0, "clé périmée : message encore lisible"] call AT_fnc_check;
    [((["cost"] call comspec_atak_native_fnc_meshKeys) select 0) isEqualTo 0, "clé périmée : coût du chiffrement compté"] call AT_fnc_check;
    if (call AT_fnc_tel) then {
        uiSleep 2.5;
        private _txt = uiNamespace getVariable ["COMSPEC_ATAK_LockTxt", controlNull];
        [ctrlText _txt isEqualTo ("PÉRIMÉE" call comspec_atak_native_fnc_tr), format ["barre d'état : « %1 »", ctrlText _txt]] call AT_fnc_check;
        private _tt = (uiNamespace getVariable ["COMSPEC_ATAK_LockCtrl", controlNull]) getVariable ["cs_tt", ""];
        [(_tt find ("Ex." call comspec_atak_native_fnc_tr)) > 0, format ["infobulle sans exemple : %1", _tt]] call AT_fnc_check;
        [_txt, "libellé du cadenas"] call AT_fnc_tient;
        ["chiffrement_barre_perimee"] call AT_fnc_shot;
    };
    // Chargeur obligatoire : sans lui, refusé.
    player removeItem "COMSPEC_ATAK_KeyLoader";
    ["fill"] call comspec_atak_native_fnc_meshKeys;
    uiSleep 1;
    [(call AT_ChState) isEqualTo "périmée", "rechargée sans chargeur de clés"] call AT_fnc_check;
    player addItem "COMSPEC_ATAK_KeyLoader";
    [call AT_ChLoad, format ["rechargement : %1", call AT_ChState]] call AT_fnc_check;
    [((["diag"] call comspec_atak_native_fnc_meshKeys) get "left") > 7000, "rechargement : la période ne repart pas"] call AT_fnc_check;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["chiffrement_zeroize_interception", {
    if (["comspec_atak_native_fnc_meshKeys", "comspec_atak_native_fnc_meshEW"] call AT_fnc_nil) exitWith {};
    private _row = call AT_ChNet;
    if (_row isEqualTo []) exitWith { "aucune clé de réseau (étape précédente)" call AT_fnc_skip; };
    player setVariable ["COMSPEC_ATAK_EwOperator", true];
    uiNamespace setVariable ["COMSPEC_ATAK_MeshIntercepts", []];
    // En clair : position et contenu partiel ; le texte est échappé à l'affichage.
    private _far = AT_Base getPos [800, 90];
    ["heard", [player, _far, 3, [], "ALPHA 1 : contact <au nord> du pont & on décroche", "ALPHA"]] call comspec_atak_native_fnc_meshKeys;
    private _log = uiNamespace getVariable ["COMSPEC_ATAK_MeshIntercepts", []];
    private _e = _log param [(count _log) - 1, []];
    [(_e param [2, false]) && {(_e param [5, ""]) isNotEqualTo ""} && {(_e param [6, ""]) isNotEqualTo ""}, format ["interception en clair : %1", _e]] call AT_fnc_check;
    private _b = missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []];
    [((_b param [(count _b) - 1, []]) param [4, ""]) isEqualTo "EN CLAIR", "relèvement en clair absent de l'app Guerre électronique"] call AT_fnc_check;
    private _rows = ["interceptRows"] call comspec_atak_native_fnc_meshKeys;
    [((str _rows) find "<au nord>") < 0, "contenu intercepté non échappé"] call AT_fnc_check;
    // Effacement d'urgence : plus aucune clé, rien à compromettre.
    ["zeroize"] call comspec_atak_native_fnc_meshKeys;
    [(call AT_ChState) isEqualTo "absente", format ["après effacement : %1", call AT_ChState]] call AT_fnc_check;
    [(player getVariable ["COMSPEC_ATAK_MeshKeysHeld", -1]) isEqualTo [], "après effacement : clés encore publiées"] call AT_fnc_check;
    // Chiffré, clé inconnue du récepteur : émission et direction seulement.
    ["heard", [player, _far, 3, [_row select 0, _row select 2], "ALPHA 1 : en position", "ALPHA"]] call comspec_atak_native_fnc_meshKeys;
    _log = uiNamespace getVariable ["COMSPEC_ATAK_MeshIntercepts", []];
    _e = _log param [(count _log) - 1, []];
    [!(_e param [2, true]) && {(_e param [5, "x"]) isEqualTo ""} && {(_e param [6, "x"]) isEqualTo ""}, format ["interception chiffrée : %1", _e]] call AT_fnc_check;
    if ("EW" call AT_fnc_page) then {
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["ewTab", "DF"];
        ["EW"] call comspec_atak_native_fnc_pageRender;
        ["chiffrement_interceptions"] call AT_fnc_shot;
    };
    [call AT_ChLoad, "rechargement après effacement"] call AT_fnc_check;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["chiffrement_arcade", {
    if (["comspec_atak_native_fnc_meshKeys", "comspec_atak_native_fnc_netCond"] call AT_fnc_nil) exitWith {};
    private _old = missionNamespace getVariable ["comspec_atak_native_realism_profile", 3];
    missionNamespace setVariable ["comspec_atak_native_realism_profile", 1];
    ["refresh"] call comspec_atak_native_fnc_netCond;
    private _d = ["diag"] call comspec_atak_native_fnc_meshKeys;
    [(_d get "period") isEqualTo 0 && {!(_d get "fill")} && {!(_d get "intercept")} && {(["cost"] call comspec_atak_native_fnc_meshKeys) isEqualTo [0, 0, 0]}, format ["Arcade : %1", _d]] call AT_fnc_check;
    missionNamespace setVariable ["comspec_atak_native_realism_profile", _old];
    ["refresh"] call comspec_atak_native_fnc_netCond;
    call AT_fnc_done;
}, 20] call AT_fnc_run;

{ missionNamespace setVariable [_x, false, true]; } forEach AT_ChVars;
missionNamespace setVariable ["comspec_atak_native_mesh_keys_period", 0, true];
player setVariable ["COMSPEC_ATAK_EwOperator", nil];
