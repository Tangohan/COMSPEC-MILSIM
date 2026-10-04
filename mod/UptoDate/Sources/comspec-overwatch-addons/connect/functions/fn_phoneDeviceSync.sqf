/*
    Téléphone en jeu ↔ Athena (back-office, Parc de terminaux). Lancé une fois au démarrage (postInit).
    Toutes les 15 s, sur un client avec interface et une session Athena prête :
    1. Relit l'identité gardée dans Athena (GetAuthState, cellules 26 à 29 : numéro, IMEI, MAC, type de numéro ;
       l'extension les rafraîchit par SyncProfile chaque minute) et met à jour comspec_profile_phone, que
       fn_phoneIdent du téléphone préfère au calcul local. Si l'IMEI ou la MAC changent côté Athena, l'appareil
       en main reprend ces valeurs (COMSPEC_ATAK_PhoneGen remis à 0).
    2. Remonte l'état du téléphone (télémétrie « phone », lot /api/atak/telemetry/batch) quand il change,
       sinon toutes les 2 minutes : batterie, état matériel, barres de signal, modèle, numéro / IMEI / MAC
       affichés, nombre de changements d'appareil. Vue d'administration hors jeu : le téléphone n'en sait rien.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_PhoneDeviceSyncPfh") exitWith {};

COMSPEC_PhoneDeviceSyncPfh = [{
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    if !([] call comspec_overwatch_connect_fnc_isReady) exitWith {};
    if (isNull player) exitWith {};

    // 1. Identité Athena → téléphone.
    private _gen = player getVariable ["COMSPEC_ATAK_PhoneGen", 0];
    private _auth = [] call comspec_overwatch_connect_fnc_authStateCells;
    private _db = ["phone_number", "phone_imei", "phone_mac", "phone_format"] apply { _auth getOrDefault [_x, ""] };
    if ((_db select 0) isNotEqualTo "") then {
        private _old = missionNamespace getVariable ["comspec_profile_phone", []];
        if !(_old isEqualType []) then { _old = []; };
        if (_old isNotEqualTo _db) then {
            missionNamespace setVariable ["comspec_profile_phone", _db, false];
            if ((count _old) > 0) then {
                private _devChanged = ((_old param [1, ""]) isNotEqualTo (_db select 1)) || {(_old param [2, ""]) isNotEqualTo (_db select 2)};
                // Un appareil changé en jeu vient d'être adopté par Athena : rien à annoncer au joueur.
                private _fromSwap = _devChanged && {_gen > 0} && {(_old param [0, ""]) isEqualTo (_db select 0)};
                if (_devChanged) then { player setVariable ["COMSPEC_ATAK_PhoneGen", 0, true]; _gen = 0; };
                if (!_fromSwap && {!isNil "comspec_atak_native_fnc_notify"}) then {
                    ["INFO", format ["Téléphone mis à jour par Athena : %1", _db select 0], 5, 20] call comspec_atak_native_fnc_notify;
                };
            };
        };
    };

    // 2. État du téléphone → Athena (seulement avec le mod téléphone natif).
    if (isNil "comspec_atak_native_fnc_phoneIdent" || {isNil "comspec_atak_native_fnc_deviceHealth"}) exitWith {};
    private _has = if (isNil "comspec_atak_native_fnc_hasDevice") then { true } else { [player] call comspec_atak_native_fnc_hasDevice };
    private _health = [] call comspec_atak_native_fnc_deviceHealth;
    private _state = if (_has) then { _health getOrDefault ["state", "OK"] } else { "ABSENT" };
    private _reason = if (_has) then { _health getOrDefault ["reason", ""] } else { "" };
    private _battery = round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
    private _bars = if (isNil "comspec_atak_native_fnc_linkQuality") then { -1 } else { ([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 0] };
    private _model = "";
    if (_has && {!isNil "comspec_atak_native_fnc_deviceCatalog"}) then {
        private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
        private _it = ((assignedItems player) + (items player)) select { (toLower _x) in _cat };
        if ((count _it) > 0) then {
            private _cls = _it select 0;
            _model = getText (configFile >> "CfgWeapons" >> _cls >> "displayName");
            if (_model isEqualTo "") then { _model = _cls; };
        };
    };
    private _ident = [player] call comspec_atak_native_fnc_phoneIdent;

    // Envoi au changement (batterie par paliers de 5 %) ou toutes les 2 minutes.
    private _sig = [_state, floor (_battery / 5), _bars, _model, _ident, _gen];
    private _now = diag_tickTime;
    private _last = missionNamespace getVariable ["COMSPEC_PhoneDeviceSyncLast", [[], -1e9]];
    if ((_last select 0) isEqualTo _sig && {(_now - (_last select 1)) < 120}) exitWith {};
    missionNamespace setVariable ["COMSPEC_PhoneDeviceSyncLast", [_sig, _now]];

    private _payload = createHashMapFromArray [
        ["battery", _battery],
        ["state", _state],
        ["reason", _reason],
        ["model", _model],
        ["number", _ident param [0, ""]],
        ["imei", _ident param [1, ""]],
        ["mac", _ident param [2, ""]],
        ["gen", _gen],
        ["steam_uid", getPlayerUID player]
    ];
    if (_bars >= 0) then { _payload set ["bars", _bars]; };
    ["phone", _payload, 1] call comspec_overwatch_connect_fnc_emitTelemetryEvent;
}, 15] call CBA_fnc_addPerFrameHandler;
