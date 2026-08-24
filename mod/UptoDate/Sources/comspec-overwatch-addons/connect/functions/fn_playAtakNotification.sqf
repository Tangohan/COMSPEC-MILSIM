/*
    Joue le son de notification ATAK.
    Params optionnels: [_event]
      - "" / omis : son selon préférence CBA (silent_vib / stalker / health)
      - "start" | "disconnect" | "unconscious" | "death" | "cardiac_arrest" | "kia" | "dead"
      - "order" | "order_priority" (réception d’ordre)
      - "order_ack" | "ack" | "accept" (acceptation)
      - "intel" (transmission de renseignement)
      - "chat" | "ping" | "marker" | "beep" (bip court)
        → son d’événement dédié (indépendant du style d'alerte, sauf Muet)

    Mode discret (quiet_mode) : n’empêche PAS le son — il ne masque que les bandeaux BIS / chat.
    Préférence « Silencieux — vibration seule » : les sons d’urgence médicale (inconscient / mort)
    et les ordres / renseignement restent audibles ; seuls les bips génériques restent silencieux.
    « Silencieux — sans vibration » : aucun son.

    Signal médical (health) : le son Motorola est rejoué 3 fois.
    Coupé hors jeu actif : les PFH CBA tournent encore pendant Esc / carte /
    inventaire, donc sans ce garde la vibration continue dans les menus.
    Exception : alertes médicales critiques (inconscient / mort) — son prioritaire.
*/
params [["_event", "", [""]]];

if (!hasInterface) exitWith {};

private _ev = toLower (trim _event);
// Alias métier → événement sonore
_ev = switch (_ev) do {
    case "boot";
    case "connect";
    case "client_init": { "start" };
    case "crash": { "disconnect" };
    case "cardiac_arrest";
    case "cardiac-arrest";
    case "kia";
    case "dead";
    case "killed";
    case "mort": { "death" };
    case "panic";
    case "eagle_down";
    case "eagle-down";
    case "eagledown";
    case "operator_down";
    case "a_terre": { "unconscious" };
    case "order_prio";
    case "order-priority";
    case "urgent_order": { "order_priority" };
    case "ack";
    case "accept";
    case "accepted";
    case "wilco": { "order_ack" };
    case "chat";
    case "ping";
    case "marker";
    case "urgent": { "beep" };
    default { _ev };
};

private _isMedicalCritical = _ev in ["unconscious", "death"];
private _isOrder = _ev in ["order", "order_priority", "order_ack", "intel"];

// Pas de `dialog` global : la tablette / hub Overwatch sont des dialogs « in game ».
// Les urgences médicales passent quand même (carte / inventaire ouverts).
private _uiBlocked =
    isNull findDisplay 46
    || {isGamePaused}
    || {!isNull findDisplay 49}   // menu pause Esc (RscDisplayInterrupt)
    || {!_isMedicalCritical && {visibleMap}}
    || {!_isMedicalCritical && {!isNull findDisplay 12}}   // carte plein écran
    || {!_isMedicalCritical && {!isNull findDisplay 602}}; // inventaire

if (_uiBlocked) exitWith {};

private _pref = missionNamespace getVariable ["comspec_overwatch_notif_sound", "silent_vib"];
if (!(_pref isEqualType "")) then { _pref = "silent_vib"; };
if ((toLower _pref) isEqualTo "mute") exitWith {};

private _sound = switch (_ev) do {
    case "start": { "COMSPEC_ATAK_Start" };
    case "disconnect": { "COMSPEC_ATAK_Disconnect" };
    case "unconscious": { "COMSPEC_ATAK_Unconscious" };
    case "death": { "COMSPEC_ATAK_Death" };
    case "order";
    case "order_priority": { "COMSPEC_ATAK_Order" };
    case "order_ack": { "COMSPEC_ATAK_OrderAck" };
    case "intel": { "COMSPEC_ATAK_Intel" };
    case "beep": { "COMSPEC_ATAK_Beep" };
    default {
        // Préférence joueur (bip générique) — silencieux en silent_vib
        switch (toLower _pref) do {
            case "stalker": { "COMSPEC_ATAK_Stalker" };
            case "health": { "COMSPEC_ATAK_Health" };
            default { "" }; // silencieux (vibration)
        }
    }
};

// Modes silence : pas de bip générique ; les ordres, le renseignement et les urgences médicales restent audibles.
if ((toLower _pref) isEqualTo "silent_vib" && {!_isMedicalCritical} && {!_isOrder} && {_ev isEqualTo "" || {_ev isEqualTo "beep"}}) exitWith {};

if (_sound isEqualTo "") exitWith {};

private _vol = ["notif"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
if (_vol <= 0.01) exitWith {};

// Anti-spam : un même événement ne se recouvre pas (surtout le signal médical ×3).
private _now = diag_tickTime;
private _lastMap = missionNamespace getVariable ["COMSPEC_AtakNotifEventAt", createHashMap];
if (!(_lastMap isEqualType createHashMap)) then { _lastMap = createHashMap; };
private _coolKey = if (_ev isEqualTo "") then { format ["pref:%1", toLower _pref] } else { _ev };
private _cool = if (_sound isEqualTo "COMSPEC_ATAK_Health") then { 7 } else { 1.2 };
private _prev = _lastMap getOrDefault [_coolKey, -1e9];
if ((_now - _prev) < _cool) exitWith {};
_lastMap set [_coolKey, _now];
missionNamespace setVariable ["COMSPEC_AtakNotifEventAt", _lastMap, false];

private _repeats = if (_sound isEqualTo "COMSPEC_ATAK_Health") then { 3 } else { 1 };
if (_repeats > 1) then {
    [_sound, _vol, _repeats] spawn {
        params ["_s", "_v", "_n"];
        for "_i" from 1 to _n do {
            playSoundUI [_s, _v, 1];
            if (_i < _n) then { uiSleep 2.2; };
        };
    };
} else {
    playSoundUI [_sound, _vol, 1];
};
