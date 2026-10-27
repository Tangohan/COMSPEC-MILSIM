/*
    « Voir l'arrière » : le téléphone retourné au centre de l'écran (coque à la couleur choisie, plaque de fixation,
    appareil photo, étiquette, antenne vissée, batterie externe fixée, patch). Clic, touche ou 12 s : fermé.
    Modèles XIAOMI X3D, V2 Enhanced, SONIM XP8, SAMSUNG S23, IPHONE 17 PRO et tablette (profil COMSPEC_ATAK_PhoneModel) : leur dos seul, sans la coque S7 et ses ajouts.
*/
disableSerialization;
private _parent = findDisplay 46;
if (isNull _parent || {!isNull (uiNamespace getVariable ["COMSPEC_ATAK_BackDisplay", displayNull])}) exitWith { false };
private _d = _parent createDisplay "RscDisplayEmpty";
if (isNull _d) exitWith { false };
uiNamespace setVariable ["COMSPEC_ATAK_BackDisplay", _d];
([player] call comspec_atak_native_fnc_phoneCarried) params ["_model", "_tint"];
private _suffix = ["", "_" + _tint] select (_tint in ["od", "black"]);
private _bg = _d ctrlCreate ["COMSPEC_RscPanel", -1];
_bg ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
_bg ctrlSetBackgroundColor [0, 0, 0, 0.65];
_bg ctrlCommit 0;
// Carré de la texture : la coque occupe 82 % de sa largeur ; centrée à l'écran (centre de la coque à 57 % de la hauteur).
private _w = safeZoneW * 0.78;
private _h = _w * pixelH / pixelW;
if (_h > safeZoneH * 1.5) then { _h = safeZoneH * 1.5; _w = _h * pixelW / pixelH; };
private _x = safeZoneX + (safeZoneW - _w) / 2;
private _y = safeZoneY + safeZoneH / 2 - _h * 0.57;
// Dos, batterie et antenne étirés sur leur cadre (COMSPEC_RscPhone, style 48) : avec « garder les proportions », l'antenne,
// dans un cadre bien plus haut que large, était recentrée autrement que le dos et flottait au-dessus de la coque.
private _img = _d ctrlCreate ["COMSPEC_RscPhone", -1];
private _s7 = !(_model in ["rugged", "iphone", "sonim", "keys", "s23", "ip17"]);
_img ctrlSetText (((["\z\comspec_atak_native\addons\main\data\phone_back_" + _model + ".paa", format ["\z\comspec_atak_native\addons\main\data\phone_back%1.paa", _suffix]] select _s7)) call comspec_atak_native_fnc_tr);
_img ctrlSetTextColor [1, 1, 1, 1];
_img ctrlSetPosition [_x, _y, _w, _h];
_img ctrlCommit 0;
private _ant = ["get"] call comspec_atak_native_fnc_antenna;
// Batterie externe sur la plaque (fn_powerBank) : calque carré de la texture.
if (_s7 && {missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false]}) then {
    private _pb = _d ctrlCreate ["COMSPEC_RscPhone", -1];
    _pb ctrlSetText (("\z\comspec_atak_native\addons\main\data\back_powerbank.paa") call comspec_atak_native_fnc_tr);
    _pb ctrlSetTextColor [1, 1, 1, 1];
    _pb ctrlSetPosition [_x, _y, _w, _h];
    _pb ctrlCommit 0;
};
if (_s7 && {_ant isNotEqualTo []}) then {
    private _a = _d ctrlCreate ["COMSPEC_RscPhone", -1];
    _a ctrlSetText ((format ["\z\comspec_atak_native\addons\main\data\antenna_%1_back.paa", _ant select 0]) call comspec_atak_native_fnc_tr);
    _a ctrlSetTextColor [1, 1, 1, 1];
    _a ctrlSetPosition [_x + _w * 1426 / 2048, _y + _h * -832 / 2048, _w * 384 / 2048, _h * 1536 / 2048];
    _a ctrlCommit 0;
};
// Patch sur le velcro (réglage Personnaliser) : patch 300 x 200 px au centre de la texture 320 x 320, x 440, y 790.
// Nos patchs ("fr", "fr_sub", "us", "us_ir", "skull", "crusader", "alien", "comspec", "name") ou un insigne d'unité
// des mods chargés (CfgUnitInsignia, carré : posé au centre du velcro), fn_patchTexture.
private _patch = profileNamespace getVariable ["COMSPEC_ATAK_Patch", "none"];
([player, _patch] call comspec_atak_native_fnc_patchTexture) params ["_ptex", "_psq"];
if (_s7 && {_ptex isNotEqualTo ""}) then {
    private _pc = _d ctrlCreate ["COMSPEC_RscPicture", -1];
    _pc ctrlSetText ((_ptex) call comspec_atak_native_fnc_tr);
    _pc ctrlSetTextColor [1, 1, 1, 1];
    _pc ctrlSetPosition ([[_x + _w * 440 / 2048, _y + _h * 790 / 2048, _w * 320 / 2048, _h * 320 / 2048], [_x + _w * 485 / 2048, _y + _h * 835 / 2048, _w * 230 / 2048, _h * 230 / 2048]] select _psq);
    _pc ctrlCommit 0;
    if (_patch isEqualTo "name") then {
        // Bande nominative : indicatif choisi, sinon nom du joueur, brodé en majuscules.
        private _txt = profileNamespace getVariable ["COMSPEC_ATAK_PatchText", ""];
        if (_txt isEqualTo "") then { _txt = name player; };
        private _tc = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
        private _th = _h * 200 / 2048;
        _tc ctrlSetPosition [_x + _w * 470 / 2048, _y + _h * 850 / 2048 + _th * 0.27, _w * 260 / 2048, _th * 0.6];
        _tc ctrlCommit 0;
        _tc ctrlSetStructuredText parseText ((format ["<t align='center' font='PuristaBold' size='%1' color='#1d1b14'>%2</t>",
            (_th * 0.32) / (0.024 * safeZoneH), toUpper ((_txt select [0, 14]) regexReplace ["[<>&]", ""])]) call comspec_atak_native_fnc_tr);
    };
};
private _ident = [player] call comspec_atak_native_fnc_phoneIdent;
private _cap = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
_cap ctrlSetPosition [safeZoneX, safeZoneY + safeZoneH * 0.86, safeZoneW, safeZoneH * 0.1];
_cap ctrlCommit 0;
_cap ctrlSetStructuredText parseText ((format [
    "<t align='center' size='1.1' color='#e8eadf'>Dos du téléphone ATAK</t><br/><t align='center' size='0.85' color='#9aa59f'>%1 · IMEI %2 · Antenne : %3<br/>Batterie externe : %4<br/>Clic ou touche pour fermer</t>",
    [createHashMapFromArray [["rugged", "XIAOMI X3D"], ["iphone", "V2 Enhanced"], ["sonim", "SONIM XP8 + goTenna Pro"], ["s23", "Samsung S23 durci"], ["ip17", "iPhone 17 Pro durci"], ["keys", "Tablette durcie"]] getOrDefault [_model, ""], "Coque " + (createHashMapFromArray [["coyote", "coyote"], ["od", "vert OD"], ["black", "noire"]] getOrDefault [_tint, "coyote"])] select _s7,
    _ident param [1, "?"], [_ant param [2, ""], "aucune (port SMA bouché)"] select (_ant isEqualTo []),
    ["aucune", (["label"] call comspec_atak_native_fnc_powerBank) select [17]] select (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false])
]) call comspec_atak_native_fnc_tr);
// Apparition en fondu.
{ _x ctrlSetFade 1; _x ctrlCommit 0; _x ctrlSetFade 0; _x ctrlCommit 0.25; } forEach (allControls _d);
_d displayAddEventHandler ["MouseButtonDown", { (_this select 0) closeDisplay 1; }];
_d displayAddEventHandler ["KeyDown", { (_this select 0) closeDisplay 1; true }];
[{ (_this select 0) closeDisplay 1; }, [_d], 12] call CBA_fnc_waitAndExecute;
true
