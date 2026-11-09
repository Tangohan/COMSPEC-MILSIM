// Notification MPU à l'écran pendant l'alternat (fn_mpuHud). Sans ACRE2 réel : aucun événement acre_* n'est émis (le vrai ACRE2 les écouterait),
// l'état est posé à la main (uiNamespace COMSPEC_ATAK_PttMine / PttHeard, missionNamespace COMSPEC_ATAK_VoiceMe) et le mode test de fn_mpuHud
// (uiNamespace COMSPEC_ATAK_MpuHudTest) ne demande pas le réglage serveur ni les relais. Aucune radio réglée, la voix par le maillage reste coupée.
if !("mpuhud" call AT_fnc_groupe) exitWith {};
["mpu_hud", {
    if (["comspec_atak_native_fnc_mpuHud", "comspec_atak_native_fnc_mpuVoice", "comspec_atak_native_fnc_mpuDiag"] call AT_fnc_nil) exitWith {};
    call AT_fnc_nettoie;
    private _tr = { _this call comspec_atak_native_fnc_tr };
    private _keep = [
        uiNamespace getVariable ["COMSPEC_ATAK_PttMine", ["", 0]], uiNamespace getVariable ["COMSPEC_ATAK_PttHeard", []], missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []],
        missionNamespace getVariable ["comspec_atak_native_mpu_hud", true], missionNamespace getVariable ["comspec_atak_native_mpu_hud_why", false]
    ];
    private _cba = {
        params ["_k", "_v"];
        [_k, _v, false, "client"] call CBA_settings_fnc_set;
        uiSleep 0.2;
        if ((missionNamespace getVariable [_k, !_v]) isNotEqualTo _v) then { missionNamespace setVariable [_k, _v]; };
    };
    private _shown = { missionNamespace getVariable ["COMSPEC_ATAK_MpuHudShown", false] };
    private _ctl = { (uiNamespace getVariable ["COMSPEC_ATAK_MpuHudDisplay", displayNull]) displayCtrl 7774 };
    ["comspec_atak_native_mpu_hud", true] call _cba;
    ["comspec_atak_native_mpu_hud_why", false] call _cba;
    uiNamespace setVariable ["COMSPEC_ATAK_MpuHudTest", true];
    uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", []];
    private _me = ["acre", "ACRE_PRC152_ID_1", "", 0, "L2:15", 1, "AN/PRC-152"];
    private _peers = [["Alpha 2", 2, 1200, true], ["Alpha 3", 3, 2400, true], ["Bravo 1", 4, 3000, true]];

    // 1. Repos : rien d'affiché, aucune boucle.
    uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["", 0]];
    missionNamespace setVariable ["COMSPEC_ATAK_VoiceMe", [_me, createHashMap, "", _peers, "lead", [], 0]];
    ["kick"] call comspec_atak_native_fnc_mpuHud;
    uiSleep 0.8;
    [!(call _shown), "affiché sans alternat"] call AT_fnc_check;
    [(missionNamespace getVariable ["COMSPEC_ATAK_MpuHudPfh", -1]) < 0, "boucle restée posée au repos"] call AT_fnc_check;

    // 2. Émission portée par le maillage : 2 sauts (le plus court), 3 correspondants.
    uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["ACRE_PRC152_ID_1", diag_tickTime]];
    ["kick"] call comspec_atak_native_fnc_mpuHud;
    [_shown, 3] call AT_fnc_wait;
    (["model"] call comspec_atak_native_fnc_mpuHud) params ["_mo", "_l1", "_l2"];
    private _want2 = format ["%1 · %2", format [("%1 sauts") call _tr, 2], format [("%1 correspondants") call _tr, 3]];
    [_mo isEqualTo "tx" && {_l1 isEqualTo (("MPU · voix par le maillage") call _tr)} && {_l2 isEqualTo _want2}, format ["émission : %1 / %2 / %3", _mo, _l1, _l2]] call AT_fnc_check;
    [call _shown && {!isNull (call _ctl)} && {ctrlShown (call _ctl)}, "émission : encadré absent"] call AT_fnc_check;
    uiSleep 0.4;
    [ctrlFade (call _ctl) < 0.1, format ["émission : encadré transparent (%1)", ctrlFade (call _ctl)]] call AT_fnc_check;
    // Position : bas droit, au-dessus du cadre ACRE (ou, sans ACRE2, à 12 % du bord bas), sans dépasser de l'écran.
    (missionNamespace getVariable ["COMSPEC_ATAK_MpuHudPos", [0, 0, 0, 0]]) params ["_px", "_py", "_pw", "_ph"];
    private _acre = isClass (configFile >> "CfgPatches" >> "acre_main");
    private _limit = if (_acre) then { profileNamespace getVariable ["IGUI_grid_ACRE_notification_Y", safeZoneY + safeZoneH - 0.128] } else { safeZoneY + safeZoneH * 0.88 };
    [_px + _pw <= safeZoneX + safeZoneW + 0.001 && {_px >= safeZoneX + safeZoneW * 0.5}, format ["émission : pas à droite (x %1, w %2)", _px, _pw]] call AT_fnc_check;
    [_py + _ph <= _limit + 0.001 && {_py >= safeZoneY + safeZoneH * 0.4}, format ["émission : pas juste au-dessus du bas droit (y %1, h %2, limite %3)", _py, _ph, _limit]] call AT_fnc_check;
    "notification MPU, émission : encadré sombre en bas à droite, juste au-dessus de l'emplacement du cadre ACRE, filet et icône verts, « MPU · voix par le maillage », puis « 2 sauts · 3 correspondants »" call AT_fnc_visuel;
    ["mpu_hud_emission"] call AT_fnc_shot;
    // Radio non pontée (autre radio ACRE2) : pas d'encadré.
    uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["ACRE_PRC343_ID_1", diag_tickTime]];
    uiSleep 1;
    [!(call _shown), "autre radio que celle reliée : encadré affiché"] call AT_fnc_check;

    // 3. Voix non portée : rien, ou la raison si « Dire pourquoi » est coché (fn_mpuDiag : aucun relais).
    uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["ACRE_PRC152_ID_1", diag_tickTime]];
    missionNamespace setVariable ["COMSPEC_ATAK_VoiceMe", [_me, createHashMap, "", [], "lead", [], 0]];
    uiSleep 1;
    [!(call _shown), "aucun correspondant joint : encadré affiché"] call AT_fnc_check;
    ["comspec_atak_native_mpu_hud_why", true] call _cba;
    missionNamespace setVariable ["COMSPEC_ATAK_VoiceMe", []];
    private _sim = ["sample"] call comspec_atak_native_fnc_mpuDiag;
    _sim set ["relayNeed", true];
    _sim set ["relays", 0];
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagSim", _sim];
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", []];
    ["kick"] call comspec_atak_native_fnc_mpuHud;
    [_shown, 3] call AT_fnc_wait;
    (["model"] call comspec_atak_native_fnc_mpuHud) params ["_wm", "_wl"];
    private _wantW = format [("MPU : non utilisée (%1)") call _tr, ("aucun relais") call _tr];
    [_wm isEqualTo "why" && {_wl isEqualTo _wantW}, format ["raison : %1 / %2 au lieu de %3", _wm, _wl, _wantW]] call AT_fnc_check;
    "notification MPU, raison : une seule ligne grise « MPU : non utilisée (aucun relais) », icône MPU grise" call AT_fnc_visuel;
    ["mpu_hud_raison"] call AT_fnc_shot;
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagSim", nil];
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", []];
    ["comspec_atak_native_mpu_hud_why", false] call _cba;

    // 4. Fin de l'alternat : l'encadré disparaît (0,4 s), la boucle est retirée.
    uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["", 0]];
    uiSleep 1.2;
    [!(call _shown), "fin de l'alternat : encadré resté"] call AT_fnc_check;
    [(missionNamespace getVariable ["COMSPEC_ATAK_MpuHudPfh", -1]) < 0, "fin de l'alternat : boucle restée posée"] call AT_fnc_check;

    // 5. Réception d'un correspondant ponté joint par le cœur de réseau (1 + 2 sauts) ; deux émetteurs : le plus récent, « +1 ».
    missionNamespace setVariable ["COMSPEC_ATAK_VoiceMe", [_me, createHashMap, "", _peers, "lead", [], 0]];
    private _grp = createGroup [side group player, true];
    private _mk = {
        params ["_id", "_dx"];
        private _u = _grp createUnit [typeOf player, (getPosATL player) vectorAdd [_dx, 0, 0], [], 0, "NONE"];
        _u allowDamage false;
        _u disableAI "ALL";
        _u setVariable ["COMSPEC_ATAK_MeshNode", [[], [], ["acre", _id, "", 0, "L2:15", 2, "AN/PRC-152"], [], []]];
        _u
    };
    private _a = ["ACRE_PRC152_ID_9", 6] call _mk;
    private _b = ["ACRE_PRC152_ID_10", 8] call _mk;
    uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", [[_a, "ACRE_PRC152_ID_9", diag_tickTime]]];
    ["kick"] call comspec_atak_native_fnc_mpuHud;
    [_shown, 3] call AT_fnc_wait;
    private _cs = [_a, false] call comspec_atak_native_fnc_unitCallsign;
    if (_cs isEqualTo "") then { _cs = name _a; };
    (["model"] call comspec_atak_native_fnc_mpuHud) params ["_rm", "_r1", "_r2"];
    private _wantR = format [("%1 via MPU") call _tr, toUpper _cs];
    [_rm isEqualTo "rx" && {_r1 isEqualTo _wantR} && {_r2 isEqualTo format [("%1 sauts") call _tr, 3]}, format ["réception : %1 / %2 / %3 (attendu %4)", _rm, _r1, _r2, _wantR]] call AT_fnc_check;
    "notification MPU, réception : filet et icône bleus, « <INDICATIF> via MPU », puis « 3 sauts »" call AT_fnc_visuel;
    ["mpu_hud_reception"] call AT_fnc_shot;
    uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", [[_a, "ACRE_PRC152_ID_9", diag_tickTime - 2], [_b, "ACRE_PRC152_ID_10", diag_tickTime]]];
    uiSleep 0.6;
    (["model"] call comspec_atak_native_fnc_mpuHud) params ["", "_r3"];
    private _cs2 = [_b, false] call comspec_atak_native_fnc_unitCallsign;
    if (_cs2 isEqualTo "") then { _cs2 = name _b; };
    [_r3 isEqualTo (format [("%1 via MPU") call _tr, toUpper _cs2] + "  +1"), format ["deux émetteurs : %1", _r3]] call AT_fnc_check;
    // Émetteur sur une autre radio que sa radio pontée : pas « via MPU ».
    uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", [[_a, "ACRE_PRC343_ID_7", diag_tickTime]]];
    uiSleep 1.2;
    [!(call _shown), "émetteur sur une radio non pontée : encadré affiché"] call AT_fnc_check;

    // 6. Réglage CBA décoché : plus rien, même alternat pressé ; recoché : revient.
    uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", []];
    uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["ACRE_PRC152_ID_1", diag_tickTime]];
    ["kick"] call comspec_atak_native_fnc_mpuHud;
    [_shown, 3] call AT_fnc_wait;
    ["comspec_atak_native_mpu_hud", false] call _cba;
    ["kick"] call comspec_atak_native_fnc_mpuHud;
    uiSleep 0.8;
    [!(call _shown) && {(missionNamespace getVariable ["COMSPEC_ATAK_MpuHudPfh", -1]) < 0}, "réglage CBA décoché : encadré ou boucle restés"] call AT_fnc_check;
    ["comspec_atak_native_mpu_hud", true] call _cba;
    ["kick"] call comspec_atak_native_fnc_mpuHud;
    [[_shown, 3] call AT_fnc_wait, "réglage CBA recoché : encadré non revenu"] call AT_fnc_check;

    // 7. Interrupteur du téléphone (Réglages > Matériel et réseau) : même valeur que le réglage CBA.
    uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["", 0]];
    if ([] call AT_fnc_tel) then {
        (call AT_fnc_etat) set ["setCat", "HW"];
        [["SETTINGS"] call AT_fnc_page, "app Réglages pas ouverte"] call AT_fnc_check;
        private _row = (uiNamespace getVariable ["COMSPEC_ATAK_FormIds", createHashMap]) getOrDefault ["cba_comspec_atak_native_mpu_hud", createHashMap];
        [!isNull (_row getOrDefault ["pill", controlNull]), "interrupteur « Notification MPU à l'écran » absent de Réglages > Matériel et réseau"] call AT_fnc_check;
        private _tg = uiNamespace getVariable ["COMSPEC_ATAK_CbaToggle", {}];
        ["comspec_atak_native_mpu_hud"] call _tg;
        uiSleep 0.5;
        [(missionNamespace getVariable ["comspec_atak_native_mpu_hud", true]) isEqualTo false, "interrupteur du téléphone : réglage CBA non décoché"] call AT_fnc_check;
        call AT_fnc_nettoie;
        uiSleep 0.5;
        uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["ACRE_PRC152_ID_1", diag_tickTime]];
        ["kick"] call comspec_atak_native_fnc_mpuHud;
        uiSleep 0.8;
        [!(call _shown), "interrupteur du téléphone coupé : encadré affiché"] call AT_fnc_check;
        ["comspec_atak_native_mpu_hud", true] call _cba;
    } else { "téléphone absent : interrupteur non testé" call AT_fnc_info; };

    // Remise en état.
    uiNamespace setVariable ["COMSPEC_ATAK_MpuHudTest", nil];
    ["stop"] call comspec_atak_native_fnc_mpuHud;
    { deleteVehicle _x; } forEach [_a, _b];
    uiNamespace setVariable ["COMSPEC_ATAK_PttMine", _keep select 0];
    uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", _keep select 1];
    missionNamespace setVariable ["COMSPEC_ATAK_VoiceMe", _keep select 2];
    ["comspec_atak_native_mpu_hud", _keep select 3] call _cba;
    ["comspec_atak_native_mpu_hud_why", _keep select 4] call _cba;
    call AT_fnc_done;
}, 90] call AT_fnc_run;
