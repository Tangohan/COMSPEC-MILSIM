/*
    Menu Échap (SP / MP) : barre COMSPEC en haut à droite, couleurs de l'ATAK natif.
      état de la liaison | Dépannage liaison | Service Athéna : oui / non
    En haut à droite : le menu d'Arma (titre, boutons) est à gauche et au centre, rien n'est masqué.
    Pendant un pas à pas, le bouton du dépannage devient « Arrêter le pas à pas ».
*/
params ["_display"];

if (!hasInterface) exitWith {};
if (isNull _display) exitWith {};

// Anciens contrôles (boutons en haut à gauche) et réouverture du menu : on repart de zéro.
{
    private _old = _display displayCtrl _x;
    if (!isNull _old) then { ctrlDelete _old; };
} forEach [9605, 9606, 9607, 9608, 9609, 9610, 9611];

private _W = safezoneW;
private _H = safezoneH;
private _h = 0.034 * _H;
private _y = safezoneY + 0.02 * _H;
private _right = safezoneX + _W - 0.012 * _W;
// Police tirée de la hauteur de la barre (et non des unités d'interface) : avec la taille d'interface NORMALE ou plus
// grande, l'ancienne police débordait de la barre (« Liaison OK » coupé, boutons écrasés).
private _fontH = _h * 0.6;

private _wChip = 0.092 * _W;
private _wDiag = 0.125 * _W;
private _wAth = 0.108 * _W;
private _gap = 0.004 * _W;
private _pad = 0.004 * _W;
private _wPanel = _pad + 0.003 * _W + _gap + _wChip + _gap + _wDiag + _gap + _wAth + _pad;
private _x0 = _right - _wPanel;

// État affiché : couleur + libellé court.
private _enabled = missionNamespace getVariable ["comspec_overwatch_enabled", true];
private _mode = [] call comspec_overwatch_connect_fnc_athenaMode;
private _link = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _iso = missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false];
private _state = switch (true) do {
    case (_iso): { [[0.95, 0.67, 0.20, 1], "Pas à pas"] };
    case (_mode isEqualTo "off"): { [[0.35, 0.58, 0.72, 1], "ATAK local"] };
    case (!_enabled): { [[0.58, 0.64, 0.60, 1], "Overwatch coupé"] };
    case (_link isEqualTo "linked"): { [[0.36, 0.78, 0.42, 1], "Liaison OK"] };
    case (_link in ["connecting", "degraded"]): { [[0.95, 0.67, 0.20, 1], "Liaison instable"] };
    default { [[0.88, 0.25, 0.22, 1], "Hors ligne"] };
};
_state params ["_col", "_label"];
private _hex = {
    params ["_c"];
    private _d = "0123456789ABCDEF";
    "#" + ((_c select [0, 3]) apply { private _v = round ((_x max 0 min 1) * 255); (_d select [floor (_v / 16), 1]) + (_d select [_v % 16, 1]) } joinString "")
};

private _bg = _display ctrlCreate ["RscText", 9608];
_bg ctrlSetBackgroundColor [0.025, 0.030, 0.027, 0.92];
_bg ctrlSetPosition [_x0, _y - 0.005 * _H, _wPanel, _h + 0.010 * _H];
_bg ctrlCommit 0;

private _accent = _display ctrlCreate ["RscText", 9609];
_accent ctrlSetBackgroundColor _col;
_accent ctrlSetPosition [_x0, _y - 0.005 * _H, _wPanel, 0.002 * _H];
_accent ctrlCommit 0;

private _dot = _display ctrlCreate ["RscText", 9610];
_dot ctrlSetBackgroundColor _col;
_dot ctrlSetPosition [_x0 + _pad, _y + 0.006 * _H, 0.003 * _W, _h - 0.012 * _H];
_dot ctrlCommit 0;

private _chip = _display ctrlCreate ["RscStructuredText", 9611];
_chip ctrlSetStructuredText parseText ((format [
    "<t font='PuristaMedium' size='0.62' color='#94A399'>COMSPEC</t><br/><t font='PuristaSemibold' size='0.82' color='%1'>%2</t>",
    [_col] call _hex,
    (_label call comspec_overwatch_connect_fnc_tr)
]) call comspec_overwatch_connect_fnc_tr);
_chip ctrlSetFontHeight (_h * 0.5);
_chip ctrlSetPosition [_x0 + _pad + 0.003 * _W + _gap, _y - 0.002 * _H, _wChip, _h + 0.004 * _H];
_chip ctrlCommit 0;
_chip ctrlSetTooltip ((format ["Liaison Athena : %1", _link]) call comspec_overwatch_connect_fnc_tr);

private _xDiag = _x0 + _pad + 0.003 * _W + _gap + _wChip + _gap;
private _diag = _display ctrlCreate [["COMSPEC_RscButtonAtakPrimary", "COMSPEC_RscButtonAtakDanger"] select _iso, 9606];
_diag ctrlSetText ((["Dépannage liaison", "Arrêter le pas à pas"] select _iso) call comspec_overwatch_connect_fnc_tr);
_diag ctrlSetFont "PuristaSemibold";
_diag ctrlSetFontHeight _fontH;
_diag ctrlSetPosition [_xDiag, _y, _wDiag, _h];
_diag ctrlCommit 0;
_diag ctrlSetTooltip ((["Contrôle la liaison Athena, la DLL et les versions, propose une correction", "Arrête le dépannage pas à pas et remet la liaison comme avant"] select _iso) call comspec_overwatch_connect_fnc_tr);
_diag ctrlAddEventHandler ["ButtonClick", {
    if (missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false]) exitWith {
        ["INFO", "Esc", "Pas à pas arrêté depuis le menu Échap"] call comspec_overwatch_connect_fnc_log;
        [] call comspec_overwatch_connect_fnc_diagIsolateStop;
        (findDisplay 49) closeDisplay 2;
    };
    ["INFO", "Esc", "Bouton Dépannage liaison cliqué"] call comspec_overwatch_connect_fnc_log;
    [] call comspec_overwatch_connect_fnc_diagIsolateLaunch;
}];

// Service Athéna : revoir le choix « mon unité utilise Athéna » (Oui / Non).
private _ath = _display ctrlCreate ["COMSPEC_RscButtonAtak", 9607];
_ath ctrlSetText ((["Service Athéna : non", "Service Athéna : oui"] select (_mode isEqualTo "on")) call comspec_overwatch_connect_fnc_tr);
_ath ctrlSetFont "PuristaMedium";
_ath ctrlSetFontHeight _fontH;
_ath ctrlSetPosition [_xDiag + _wDiag + _gap, _y, _wAth, _h];
_ath ctrlCommit 0;
_ath ctrlSetTooltip (("Changer de choix : Athena (liaison avec le poste) ou ATAK local") call comspec_overwatch_connect_fnc_tr);
// ATAK natif avec téléphone : la question est dans Réglages > Service Athéna ; l'ancienne fenêtre reste pour qui n'a pas de téléphone.
_ath ctrlAddEventHandler ["ButtonClick", {
    if (isClass (configFile >> "CfgPatches" >> "comspec_atak_native_main") && {[player] call comspec_atak_native_fnc_hasDevice}) exitWith {
        (findDisplay 49) closeDisplay 2;
        [{ ["show"] call comspec_atak_native_fnc_athenaChoice; }] call CBA_fnc_execNextFrame;
    };
    [true] call comspec_overwatch_connect_fnc_athenaChoiceShow;
}];

["DEBUG", "Esc", "Barre COMSPEC injectée (haut droite)"] call comspec_overwatch_connect_fnc_log;
