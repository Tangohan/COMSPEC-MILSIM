/*
    Ouvre le terminal.
    _interactive = true  : en main (souris, clavier), mini ou plein écran selon le réglage.
    _interactive = false : porté (HUD), téléphone mini visible pendant que l'on joue.
*/
params [["_interactive", true]];
if (!hasInterface) exitWith { false };
disableSerialization;
if (isNil { uiNamespace getVariable "COMSPEC_ATAK_State" }) then { [] call comspec_atak_native_fnc_stateInit; };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Réouverture en main d'un téléphone déjà en main (remis au premier plan, fn_interactToggle) : l'interaction avec la
// miniature en cours (fn_miniInteract) continue, fn_displayUnload ne doit pas la terminer.
private _keepMini = [uiNamespace getVariable ["COMSPEC_ATAK_MiniPrevMode", ""], ""] select !(_interactive && {!isNull ([] call comspec_atak_native_fnc_display)} && {_s getOrDefault ["interactive", false]});
private _keepMode = profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"];
_s set ["suppressHudRestore", true];
[] call comspec_atak_native_fnc_close;
_s set ["suppressHudRestore", false];
if (_keepMini isNotEqualTo "") then {
    uiNamespace setVariable ["COMSPEC_ATAK_MiniPrevMode", _keepMini];
    profileNamespace setVariable ["COMSPEC_ATAK_Mode", _keepMode];
};
// Menu d'actions d'Arma masqué tant que le téléphone est affiché (COMSPEC_ATAK_ActionBlock, XEH_postInitClient).
// Réinstallé à chaque ouverture : une mission ou un autre mod a pu le remplacer. Remis à vide à la fermeture
// (fn_displayUnload) : téléphone rangé, le gestionnaire d'une mission ou d'un autre mod n'est plus écrasé pour la session.
uiNamespace setVariable ["COMSPEC_ATAK_OpenSeq", (uiNamespace getVariable ["COMSPEC_ATAK_OpenSeq", 0]) + 1];
inGameUISetEventHandler ["PrevAction", "[false] call (missionNamespace getVariable ['COMSPEC_ATAK_ActionBlock', { false }])"];
inGameUISetEventHandler ["NextAction", "[false] call (missionNamespace getVariable ['COMSPEC_ATAK_ActionBlock', { false }])"];
inGameUISetEventHandler ["Action", "[true] call (missionNamespace getVariable ['COMSPEC_ATAK_ActionBlock', { false }])"];
if (_interactive) exitWith {
    // Display de mission (46) ; à bord, la fenêtre la plus haute (écran de cockpit cliquable, MFD d'un autre mod) :
    // ouvert sur 46, le téléphone restait dessous, invisible, souris et commandes de vol prises (fn_vehicleKeys, 2.9.41).
    private _parent = ["parent"] call comspec_atak_native_fnc_vehicleKeys;
    if (isNull _parent) exitWith { ["ERROR", "UI", "No parent display"] call comspec_atak_native_fnc_log; false };
    private _disp = _parent createDisplay "COMSPEC_RscDisplayATAK";
    if (isNull _disp) then { ["WARNING", "ATAK", format ["Téléphone en main impossible à ouvrir sur la fenêtre IDD %1", ctrlIDD _parent]] call comspec_atak_native_fnc_log; };
    // Téléphone en main : les raccourcis CBA ne passent plus. Principal, orientation et position relus ici
    // (porter, prendre en main et zoom : fn_displayLoad).
    if (!isNull _disp) then {
        _disp displayAddEventHandler ["KeyDown", {
            params ["", "_key", "_shift", "_ctrl", "_alt"];
            // Touche prise pour un raccourci en cours de réglage (fn_keybinds) : rien d'autre ne part.
            if (["key", _this] call comspec_atak_native_fnc_keybinds) exitWith { true };
            private _hit = {
                params ["_action", "_default"];
                private _kb = ["COMSPEC ATAK", _action] call CBA_fnc_getKeybind;
                private _bind = if (isNil "_kb") then { _default } else { _kb select 5 };
                _bind params [["_k", -1], ["_mods", [false, false, false]]];
                _k > 0 && {_k isEqualTo _key} && {_mods isEqualTo [_shift, _ctrl, _alt]}
            };
            if (["PhoneMain", [0x16, [false, false, true]]] call _hit) exitWith { [{ [] call (missionNamespace getVariable ["COMSPEC_ATAK_KeyMain", {}]); }] call comspec_atak_native_fnc_nextFrame; true };
            if (["PhoneMiniUse", [0x16, [true, false, false]]] call _hit) exitWith { [{ [] call comspec_atak_native_fnc_miniInteract; }] call comspec_atak_native_fnc_nextFrame; true };
            if (["PhoneOrient", [0x16, [false, true, true]]] call _hit) exitWith { [{ [] call comspec_atak_native_fnc_orientationToggle; }] call comspec_atak_native_fnc_nextFrame; true };
            if (["PhonePosition", [0x16, [true, false, true]]] call _hit) exitWith { [{ [] call (missionNamespace getVariable ["COMSPEC_ATAK_KeyPosition", {}]); }] call comspec_atak_native_fnc_nextFrame; true };
            // Saisie dans un champ du téléphone : la touche ne part pas au jeu (fn_keyBlock).
            _this call comspec_atak_native_fnc_keyBlock
        }];
    };
    !isNull _disp
};
// Style « porté » JPC : l'ATAK s'affiche sur l'écran 3D de la tablette du gilet (fn_jpcScreen), sinon miniature.
if (["active"] call comspec_atak_native_fnc_jpcScreen) exitWith { ["open"] call comspec_atak_native_fnc_jpcScreen };
("COMSPEC_ATAK_Hud" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleATAK", "PLAIN", 0, false];
true
