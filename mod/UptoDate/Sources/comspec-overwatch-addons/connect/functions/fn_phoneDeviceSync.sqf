/*
    Téléphone en jeu ↔ Athena (back-office, Parc de terminaux). Lancé une fois au démarrage (postInit).
    Toutes les 15 s, sur un client avec interface et une session Athena prête :
    Le numéro attribué arrive par le rappel PhoneHandset (fn_extensionCallback), pas par une commande.
    Remonte l'état du téléphone (télémétrie « phone », lot /api/atak/telemetry/batch) quand il change,
       sinon toutes les 2 minutes : batterie, état matériel, barres de signal, modèle, numéro / IMEI / MAC
       affichés, nombre de changements d'appareil. Vue d'administration hors jeu : le téléphone n'en sait rien.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_PhoneDeviceSyncPfh") exitWith {};

COMSPEC_PhoneDeviceSyncPfh = [{
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    if !([] call comspec_overwatch_connect_fnc_isReady) exitWith {};
    if (isNull player) exitWith {};

    // État du téléphone → Athena (seulement avec le mod téléphone natif).
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
    // Version de l'ATAK natif jointe au modèle (affichée, floutée, sous les photos publiées sur Discord).
    private _ver = getText (configFile >> "CfgPatches" >> "comspec_atak_native_main" >> "versionStr");
    if (_ver isNotEqualTo "") then { _model = [format ["%1 · ATAK %2", _model, _ver], format ["ATAK %1", _ver]] select (_model isEqualTo ""); };

    // Envoi au changement (batterie par paliers de 5 %) ou toutes les 2 minutes.
    private _gen = player getVariable ["COMSPEC_ATAK_PhoneGen", 0];
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
