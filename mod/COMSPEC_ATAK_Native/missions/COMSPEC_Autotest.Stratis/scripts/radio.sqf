// Radio ACRE2 reconnue par l'app Wave Relay (4.1.0 : « MPU5 canal 1 (câble EUD) » au-dessus de « Radio : téléphone seul … (sans MPU5) »).
// Donne au joueur de test une ACRE_MPU5 (mod « MPU-5 Persistent Systems », chargé seul par tools/ingame_test.ps1), une 152, puis une 343,
// et vérifie la ligne « Radio » (fn_mpu5 "detected"), le facteur de débit et le message ACRE2. Lectures seulement : aucune radio réglée,
// la voix par le maillage reste coupée.
if !("acre" call AT_fnc_groupe) exitWith {};
["acre_radio_detectee", {
    if !(isClass (configFile >> "CfgPatches" >> "acre_main")) exitWith { "ACRE non chargé" call AT_fnc_skip; };
    if (["comspec_atak_native_fnc_mpu5", "comspec_atak_native_fnc_mpuLink", "comspec_atak_native_fnc_mpuVoice", "comspec_atak_native_fnc_linkQuality"] call AT_fnc_nil) exitWith {};
    private _probe = ["detected"] call comspec_atak_native_fnc_mpu5;
    if (isNil "_probe" || {!(_probe isEqualType [])}) exitWith { "fn_mpu5 detected absent de ce build" call AT_fnc_skip; };
    private _realAvant = missionNamespace getVariable ["comspec_atak_native_mpu_real", true];
    missionNamespace setVariable ["comspec_atak_native_mpu_real", true, true];
    [!((missionNamespace getVariable ["comspec_atak_native_mpu_voice", false]) isEqualTo true), "voix par le maillage active par défaut"] call AT_fnc_check;
    // Retire toute radio ACRE2 et toute MPU5 du joueur de test (objets de la mission, pas de réglage ACRE).
    private _vider = {
        {
            private _l = toLower _x;
            if ((_l find "acre_") isEqualTo 0 || {["isMpu", [_x]] call comspec_atak_native_fnc_mpu5}) then { player removeItem _x; };
        } forEach (items player);
    };
    private _relire = {
        { uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_GearC", "COMSPEC_ATAK_Mpu5Has", "COMSPEC_ATAK_LinkQ"];
        ["tick"] call comspec_atak_native_fnc_mpuLink;
        uiSleep 0.3;
        { uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_GearC", "COMSPEC_ATAK_Mpu5Has", "COMSPEC_ATAK_LinkQ"];
        ["tick"] call comspec_atak_native_fnc_mpuLink;
    };
    // [classe, type attendu, reliée au téléphone toute seule]
    {
        _x params ["_cls", "_type", "_auto"];
        if !(isClass (configFile >> "CfgWeapons" >> _cls)) then {
            (format ["%1 absente de cette partie (mod non chargé) : non testée", _cls]) call AT_fnc_info;
            continue
        };
        call _vider;
        (uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap]) set ["autoOff", false];
        call _relire;
        player addItem _cls;
        // ACRE2 remplace l'objet par une instance (ACRE_PRC152_ID_3, ACRE_MPU5_ID_1) : la détection doit la reconnaître aussi.
        [{ ((items player) findIf { ((toLower _x) find (toLower _cls)) isEqualTo 0 }) >= 0 }, 5] call AT_fnc_wait;
        [{ ((items player) findIf { ((toLower _x) find ((toLower _cls) + "_id_")) isEqualTo 0 }) >= 0 }, 15] call AT_fnc_wait;
        private _obj = ((items player) select { ((toLower _x) find (toLower _cls)) isEqualTo 0 }) param [0, ""];
        [_obj isNotEqualTo "", format ["%1 non ajoutée à l'inventaire", _cls]] call AT_fnc_check;
        (format ["%1 : objet dans l'inventaire %2", _cls, _obj]) call AT_fnc_info;
        call _relire;
        private _det = ["detected"] call comspec_atak_native_fnc_mpu5;
        (format ["%1 : detected = %2", _cls, _det]) call AT_fnc_info;
        [_det isNotEqualTo [], format ["%1 : aucune radio détectée (ligne Radio « téléphone seul »)", _cls]] call AT_fnc_check;
        if (_det isNotEqualTo []) then {
            _det params ["_dn", "_dc", "_dr", "_dl", "_dm", "_dt"];
            [_dm isEqualTo "acre", format ["%1 : mod %2 au lieu d'acre", _cls, _dm]] call AT_fnc_check;
            [_dt isEqualTo _type, format ["%1 : type %2 au lieu de %3", _cls, _dt, _type]] call AT_fnc_check;
            [_dr > 0, format ["%1 : portée %2", _cls, _dr]] call AT_fnc_check;
            [(_dl isEqualTo "cable") isEqualTo _auto, format ["%1 : liaison « %2 »", _cls, _dl]] call AT_fnc_check;
            if (_type isEqualTo "RADIOMPU") then { [["isMpu", [_obj]] call comspec_atak_native_fnc_mpu5, format ["%1 : instance %2 pas reconnue comme MPU5", _cls, _obj]] call AT_fnc_check; };
        };
        // Facteur de débit : même nom que la ligne Radio, une fois le maillage relu (liaison reliée seulement).
        if (_auto) then {
            [{ ((["status"] call comspec_atak_native_fnc_mpuLink) getOrDefault ["ok", false]) }, 12] call AT_fnc_wait;
            uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", nil];
            private _f = ([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["factors", []];
            (format ["%1 : facteurs %2", _cls, _f]) call AT_fnc_info;
            if ((["status"] call comspec_atak_native_fnc_mpuLink) getOrDefault ["ok", false]) then {
                [(_f findIf { ((_x select 0) find (_det param [0, "?"])) isEqualTo 0 }) >= 0, format ["%1 : facteur de débit sans le nom de la radio détectée", _cls]] call AT_fnc_check;
            };
        };
        private _vs = ["summary"] call comspec_atak_native_fnc_mpuVoice;
        [(_vs select 0) isEqualTo "off" && {(_vs select 2) isNotEqualTo ""}, format ["voix par le réseau : %1", _vs]] call AT_fnc_check;
        if ([] call AT_fnc_tel) then {
            private _st = call AT_fnc_etat;
            _st set ["wrTab", "NODE"];
            _st set ["wrTuto", -1];
            ["WAVERELAY", 2] call AT_fnc_page;
            "ligne Radio : nom de la radio, portée, liaison ; section ACRE2 : « Radio ACRE détectée » et « Voix par le réseau : désactivée (réglage admin) » ; jamais « sans MPU5 » sous un facteur MPU5" call AT_fnc_visuel;
            [format ["acre_radio_%1", toLower _cls]] call AT_fnc_shot;
            _st set ["wrTab", "TG"];
            ["WAVERELAY"] call comspec_atak_native_fnc_pageRender;
            uiSleep 1;
            [format ["acre_voix_%1", toLower _cls]] call AT_fnc_shot;
            _st set ["wrTab", "NODE"];
        };
    } forEach [["ACRE_MPU5", "RADIOMPU", true], ["ACRE_PRC152", "SW", true], ["ACRE_PRC343", "PERSO", false]];
    call _vider;
    call _relire;
    missionNamespace setVariable ["comspec_atak_native_mpu_real", _realAvant, true];
    call AT_fnc_done;
}, 180] call AT_fnc_run;
