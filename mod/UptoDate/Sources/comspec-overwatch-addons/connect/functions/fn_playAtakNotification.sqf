/*
    Joue le son de notification ATAK.
    Params optionnels: [_event]
      - "" / omis : son selon préférence CBA (silent_vib / stalker / health)
      - "start" | "disconnect" | "unconscious" | "death" | "cardiac_arrest" | "kia" | "dead"
      - "order" | "order_priority" (roger_simple / roger_prio)
        → son d’événement dédié (indépendant du style d'alerte, sauf Muet)

    Mode discret (quiet_mode) : n’empêche PAS le son — il ne masque que les bandeaux BIS / chat.
    Préférence « Silencieux — vibration seule » : : les sons d’urgence médicale (inconscient / mort)
    et les ordres restent audibles ; seuls les bips génériques restent silencieux.
    « Silencieux — sans vibration » : aucun son.

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
    case "order_prio";
    case "order-priority";
    case "urgent_order": { "order_priority" };
    default { _ev };
};

private _isMedicalCritical = _ev in ["unconscious", "death"];
private _isOrder = _ev in ["order", "order_priority"];

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
    case "order": { "COMSPEC_ATAK_Order" };
    case "order_priority": { "COMSPEC_ATAK_OrderPrio" };
    default {
        // Préférence joueur (bip générique) — silencieux en silent_vib
        switch (toLower _pref) do {
            case "stalker": { "COMSPEC_ATAK_Stalker" };
            case "health": { "COMSPEC_ATAK_Health" };
            default { "" }; // silencieux (vibration)
        }
    };
};

// Modes silence : pas de bip générique ; les ordres et urgences médicales restent audibles.
if ((toLower _pref) isEqualTo "silent_vib" && {!_isMedicalCritical} && {!_isOrder} && {_ev isEqualTo ""}) exitWith {};

if (_sound isEqualTo "") exitWith {};

playSound [_sound, true];
