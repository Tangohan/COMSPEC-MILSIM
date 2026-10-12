/*
    App Photo : s'ouvre directement sur l'appareil photo, viseur en direct dans l'écran du téléphone (fn_photoCam),
    comme sur un vrai téléphone. Les photos prises se revoient, s'envoient et se suivent dans l'app Galerie
    (fn_pageGallery).
      Viseur      : image en direct de l'objectif (vue du joueur, téléphone tenu devant soi) ; grille, cap, heure,
                    objectif et zoom incrustés. Glisser : décaler l'objectif ; molette : zoom ; double clic : recentrer.
                    Boutons sur l'image : FLASH (AUTO / FORCÉ / COUPÉ), PLEIN ÉCRAN (mode photo, fn_photoMode),
                    zoom − / + .
      Commandes   : GALERIE (ouvre l'app Galerie, nombre de photos), déclencheur au centre, SELFIE (objectif avant).
      Légende     : facultative, envoyée avec la photo (clé photoCaption de l'état, gardée au redessin).
    Portrait : commandes sous le viseur ; paysage : commandes en colonne à droite.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _land = _l getOrDefault ["landscape", false];
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _st = uiNamespace getVariable ["COMSPEC_ATAK_PhotoSt", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_PhotoSt", _st];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _net = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 0]) > 0;
private _canShoot = _bridge && {_ready};
private _flash = _st getOrDefault ["flash", "AUTO"];
private _selfie = _st getOrDefault ["camSelfie", false];
private _zoom = _st getOrDefault ["camZoom", 1];
([] call comspec_atak_native_fnc_accent) params ["_acc"];
// Légende tapée : relue du champ avant chaque action qui redessine la page.
private _keep = "private _e = uiNamespace getVariable ['COMSPEC_ATAK_PhotoCaptionEdit', controlNull]; if (!isNull _e) then { (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['photoCaption', ctrlText _e]; };";

// Ligne d'état : où part la photo.
private _stH = _fs * 1.5;
private _stat = ["COMSPEC_RscStructuredText", [_pad, _pad / 4, _bw - _pad * 2, _stH]] call _mk;
_stat ctrlSetStructuredText parseText ((switch (true) do {
    case (!_bridge): { "<t size='0.8' color='#e5483a'>● COMSPEC Overwatch absent : ni prise de vue ni envoi possibles</t>" };
    case (!_ready): { "<t size='0.8' color='#f2ab33'>● Athena non connecté : connectez-vous dans l'app Athena pour photographier</t>" };
    case (!_net): { "<t size='0.8' color='#f2ab33'>● Hors réseau : la photo reste sur le téléphone (app Galerie pour l'envoyer)</t>" };
    default { "<t size='0.8' color='#5cc76b'>● Prêt : la photo part directement vers Athena</t>" };
}) call comspec_atak_native_fnc_tr);

// Zones : viseur et commandes (sous le viseur en portrait, colonne à droite en paysage).
private _capH = _font * 1.3;
private _ctlW = (_bw * 0.24) max (_font * 4.5);
private _ctlH = (_font * 4) max (_bh * 0.16);
private _vx = _pad;
private _vy = _stH + _pad / 2;
private _vw = if (_land) then { _bw - _ctlW - _pad * 3 } else { _bw - _pad * 2 };
private _vh = if (_land) then { _bh - _vy - _capH - _pad * 1.5 } else { _bh - _vy - _ctlH - _capH - _pad * 2 };
_vh = _vh max (_font * 6);

// Viseur : fond noir, image en direct.
private _bg = ["COMSPEC_RscText", [_vx, _vy, _vw, _vh]] call _mk;
_bg ctrlSetBackgroundColor [0, 0, 0, 1];
private _tex = ["bind", _vw / (_vh * _ratio)] call comspec_atak_native_fnc_photoCam;
if (_tex isEqualTo "") then {
    private _t = ["COMSPEC_RscStructuredText", [_vx + _pad, _vy + _vh * 0.4, _vw - _pad * 2, _font * 3]] call _mk;
    _t ctrlSetStructuredText parseText (("<t align='center' font='RobotoCondensedBold' color='#f2ab33'>VISEUR INDISPONIBLE</t><br/><t align='center' size='0.8' color='#a9b8b1'>Activez « Image dans l'image » (PiP) dans Options > Vidéo d'Arma, ou passez en PLEIN ÉCRAN.</t>") call comspec_atak_native_fnc_tr);
} else {
    ["COMSPEC_RscPhone", [_vx, _vy, _vw, _vh], _tex] call _mk;
};
// Grille des tiers, discrète, et réticule.
private _thin = pixelH;
{
    _x params ["_gx", "_gy", "_gw", "_gh"];
    private _g = ["COMSPEC_RscText", [_gx, _gy, _gw, _gh]] call _mk;
    _g ctrlSetBackgroundColor [1, 1, 1, 0.18];
    _g ctrlEnable false;
} forEach [
    [_vx + _vw / 3, _vy, pixelW, _vh], [_vx + _vw * 2 / 3, _vy, pixelW, _vh],
    [_vx, _vy + _vh / 3, _vw, _thin], [_vx, _vy + _vh * 2 / 3, _vw, _thin]
];
private _rc = (_vh min (_vw / _ratio)) * 0.05;
{
    private _r = ["COMSPEC_RscText", _x] call _mk;
    _r ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.85];
    _r ctrlEnable false;
} forEach [
    [_vx + _vw / 2 - _rc * _ratio, _vy + _vh / 2, _rc * 2 * _ratio, pixelH * 2],
    [_vx + _vw / 2, _vy + _vh / 2 - _rc, pixelW * 2, _rc * 2]
];
// Éclair du déclencheur (fn_photoCam l'anime).
private _fl = ["COMSPEC_RscText", [_vx, _vy, _vw, _vh]] call _mk;
_fl ctrlSetBackgroundColor [1, 1, 1, 0];
_fl ctrlEnable false;
uiNamespace setVariable ["COMSPEC_ATAK_PhotoCamFlash", _fl];
// Surface de visée : glisser décale l'objectif, molette zoome, double clic recentre.
private _hit = ["COMSPEC_RscButtonInvisible", [_vx, _vy, _vw, _vh]] call _mk;
_hit setVariable ["img", [_vw, _vh]];
_hit ctrlSetTooltip (("Glisser : orienter l'objectif · molette : zoom · double clic : recentrer") call comspec_atak_native_fnc_tr);
_hit ctrlAddEventHandler ["MouseButtonDown", { params ["", "_b", "_mx", "_my"]; if (_b isEqualTo 0) then { uiNamespace setVariable ["COMSPEC_ATAK_PhotoDrag", [_mx, _my]]; }; }];
_hit ctrlAddEventHandler ["MouseMoving", {
    params ["_c", "_mx", "_my"];
    private _d = uiNamespace getVariable ["COMSPEC_ATAK_PhotoDrag", []];
    if ((count _d) < 2) exitWith {};
    (_c getVariable ["img", [1, 1]]) params ["_w", "_h"];
    ["aim", [(_mx - (_d select 0)) / _w, (_my - (_d select 1)) / _h]] call comspec_atak_native_fnc_photoCam;
    uiNamespace setVariable ["COMSPEC_ATAK_PhotoDrag", [_mx, _my]];
}];
_hit ctrlAddEventHandler ["MouseButtonUp", { uiNamespace setVariable ["COMSPEC_ATAK_PhotoDrag", []]; }];
_hit ctrlAddEventHandler ["MouseExit", { uiNamespace setVariable ["COMSPEC_ATAK_PhotoDrag", []]; }];
_hit ctrlAddEventHandler ["MouseButtonDblClick", { ["aimReset"] call comspec_atak_native_fnc_photoCam; }];
_hit ctrlAddEventHandler ["MouseZChanged", compile format ["%1 ['zoom', [-1, 1] select ((_this select 1) > 0)] call comspec_atak_native_fnc_photoCam; true", _keep]];
// Incrustation : grille, cap, heure, objectif, zoom (rafraîchie par la boucle de la caméra).
private _osd = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _vy + _pad / 4, _vw - _pad, _fs * 2.6]] call _mk;
_osd ctrlEnable false;
uiNamespace setVariable ["COMSPEC_ATAK_PhotoCamOsd", _osd];
["osd"] call comspec_atak_native_fnc_photoCam;

// Boutons posés sur l'image : flash (haut gauche), plein écran (haut droite), zoom (bas, centré).
private _chipH = _font * 1.15;
private _chip = {
    params ["_txt", "_pos", "_code", "_tip", ["_on", false]];
    private _b = ["COMSPEC_RscButton", _pos, _txt] call _mk;
    _b ctrlSetFontHeight (_fs * 0.95);
    _b ctrlSetBackgroundColor ([[0, 0, 0, 0.55], [0.10, 0.28, 0.14, 0.85]] select _on);
    _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", _code];
    _b
};
private _flTxt = format ["FLASH %1", createHashMapFromArray [["AUTO", "AUTO"], ["ON", "FORCÉ"], ["OFF", "COUPÉ"]] getOrDefault [_flash, "AUTO"]];
private _cy = _vy + _fs * 2.8;
[_flTxt, [_vx + _pad / 2, _cy, (_vw * 0.34) max (_font * 4.5), _chipH], compile format ["%1 ['flash'] call comspec_atak_native_fnc_photoCam;", _keep],
    "Flash : AUTO (la nuit et dans un intérieur sombre), FORCÉ (à chaque photo) ou COUPÉ (discrétion)", _flash isEqualTo "ON"] call _chip;
private _fsW = (_vw * 0.36) max (_font * 5);
private _full = ["PLEIN ÉCRAN", [_vx + _vw - _fsW - _pad / 2, _cy, _fsW, _chipH],
    compile format ["%1 private _c = (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) getOrDefault ['photoCaption', '']; [{ ['ENTER', _this] call comspec_atak_native_fnc_photoMode; }, _c] call CBA_fnc_execNextFrame;", _keep],
    "Viseur plein écran : on se déplace normalement, clic gauche pour photographier, Espace pour revenir au téléphone"] call _chip;
_full ctrlEnable _canShoot;
private _zw = _chipH * _ratio * 1.2;
private _zy = _vy + _vh - _chipH - _pad / 2;
private _zx = _vx + _vw / 2;
["−", [_zx - _zw * 2.1, _zy, _zw, _chipH], compile format ["%1 ['zoom', -1] call comspec_atak_native_fnc_photoCam;", _keep], "Dézoomer"] call _chip;
private _zl = ["COMSPEC_RscStructuredText", [_zx - _zw * 1.05, _zy, _zw * 2.1, _chipH]] call _mk;
_zl ctrlSetBackgroundColor [0, 0, 0, 0.55];
_zl ctrlEnable false;
_zl ctrlSetStructuredText parseText ((format ["<t align='center' font='RobotoCondensedBold' color='#5cc76b'>x%1</t>", [_zoom toFixed 1, str _zoom] select ((_zoom mod 1) isEqualTo 0)]) call comspec_atak_native_fnc_tr);
["+", [_zx + _zw * 1.1, _zy, _zw, _chipH], compile format ["%1 ['zoom', 1] call comspec_atak_native_fnc_photoCam;", _keep], "Zoomer (numérique, jusqu'à x8)"] call _chip;
if (!_canShoot) then {
    private _w = ["COMSPEC_RscStructuredText", [_vx + _pad, _vy + _vh * 0.58, _vw - _pad * 2, _font * 1.6]] call _mk;
    _w ctrlSetBackgroundColor [0, 0, 0, 0.55];
    _w ctrlEnable false;
    _w ctrlSetStructuredText parseText (("<t align='center' size='0.9' font='RobotoCondensedBold' color='#f2ab33'>CONNECTEZ-VOUS À ATHENA POUR PHOTOGRAPHIER</t>") call comspec_atak_native_fnc_tr);
};

// Commandes : galerie, déclencheur, selfie.
private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
private _nPh = (count _lib) + ({ _x isEqualType [] } count (profileNamespace getVariable ["COMSPEC_ATAK_DronePhotos", []]));
private _sh = if (_land) then { (_ctlW / _ratio * 0.62) min (_bh * 0.24) } else { _ctlH * 0.78 };
private _sw = _sh * _ratio;
private _side = _sh * 0.62;
private _sideW = _side * _ratio;
// Centres : [déclencheur, galerie, selfie].
private _centers = if (_land) then {
    private _cx = _bw - _pad - _ctlW / 2;
    [[_cx, _vy + _vh / 2], [_cx, _vy + _vh / 2 + _sh * 1.25], [_cx, _vy + _vh / 2 - _sh * 1.25]]
} else {
    private _cyc = _vy + _vh + _pad / 2 + _ctlH / 2;
    [[_bw / 2, _cyc], [_bw * 0.17, _cyc], [_bw * 0.83, _cyc]]
};
(_centers select 0) params ["_scx", "_scy"];
private _ring = ["COMSPEC_RscPicture", [_scx - _sw / 2, _scy - _sh / 2, _sw, _sh], _dir + "ui_disc.paa"] call _mk;
_ring ctrlSetTextColor [0.95, 0.97, 0.96, 1];
private _inner = ["COMSPEC_RscPicture", [_scx - _sw * 0.40, _scy - _sh * 0.40, _sw * 0.80, _sh * 0.80], _dir + "ui_disc.paa"] call _mk;
_inner ctrlSetTextColor ([[0.45, 0.5, 0.47, 1], _acc] select _canShoot);
private _cam = ["COMSPEC_RscPicture", [_scx - _sw * 0.2, _scy - _sh * 0.2, _sw * 0.4, _sh * 0.4], _dir + "ui_photocam.paa"] call _mk;
_cam ctrlSetTextColor [0.05, 0.08, 0.06, 1];
private _shoot = ["COMSPEC_RscButtonInvisible", [_scx - _sw / 2, _scy - _sh / 2, _sw, _sh]] call _mk;
_shoot ctrlSetTooltip (("Prendre la photo (ce que montre le viseur)") call comspec_atak_native_fnc_tr);
_shoot ctrlAddEventHandler ["ButtonClick", compile format ["%1 ['shoot', (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) getOrDefault ['photoCaption', '']] call comspec_atak_native_fnc_photoCam;", _keep]];
_shoot ctrlEnable _canShoot;
// Boutons ronds de part et d'autre : icône et libellé dessous.
{
    _x params ["_ctr", "_icon", "_lbl", "_code", "_tip", ["_on", false]];
    _ctr params ["_cx", "_cy"];
    private _d = ["COMSPEC_RscPicture", [_cx - _sideW / 2, _cy - _side / 2 - _fs * 0.5, _sideW, _side], _dir + "ui_disc.paa"] call _mk;
    _d ctrlSetTextColor ([[0.13, 0.16, 0.14, 1], [0.10, 0.28, 0.14, 1]] select _on);
    private _i = ["COMSPEC_RscPicture", [_cx - _sideW * 0.28, _cy - _side * 0.28 - _fs * 0.5, _sideW * 0.56, _side * 0.56], _dir + _icon] call _mk;
    _i ctrlSetTextColor [0.92, 0.95, 0.93, 1];
    private _t = ["COMSPEC_RscStructuredText", [_cx - _sideW * 1.1, _cy + _side / 2 - _fs * 0.45, _sideW * 2.2, _fs * 1.4]] call _mk;
    _t ctrlEnable false;
    _t ctrlSetStructuredText parseText ((format ["<t align='center' size='0.75' color='#a9b8b1'>%1</t>", _lbl]) call comspec_atak_native_fnc_tr);
    private _b = ["COMSPEC_RscButtonInvisible", [_cx - _sideW / 2, _cy - _side / 2 - _fs * 0.5, _sideW, _side + _fs * 1.2]] call _mk;
    _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", _code];
} forEach [
    [_centers select 1, "ui_gallery.paa", format ["GALERIE (%1)", _nPh], compile format ["%1 ['GALLERY'] call comspec_atak_native_fnc_navigate;", _keep], "Ouvrir l'app Galerie : revoir, envoyer ou supprimer les photos"],
    [_centers select 2, "ui_rotate.paa", ["SELFIE", "ARRIÈRE"] select _selfie, compile format ["%1 ['selfie'] call comspec_atak_native_fnc_photoCam;", _keep], ["Passer à l'objectif avant (selfie)", "Revenir à l'objectif arrière"] select _selfie, _selfie]
];

// Légende facultative, envoyée avec la photo.
private _ey = if (_land) then { _vy + _vh + _pad / 2 } else { _bh - _capH - _pad / 2 };
private _ew = if (_land) then { _vw } else { _bw - _pad * 2 };
private _lab = ["COMSPEC_RscStructuredText", [_pad, _ey, _ew * 0.22, _capH]] call _mk;
_lab ctrlEnable false;
_lab ctrlSetStructuredText parseText (("<t size='0.8' color='#8a9a93'>Légende</t>") call comspec_atak_native_fnc_tr);
private _ed = ["COMSPEC_RscEdit", [_pad + _ew * 0.22, _ey, _ew * 0.78, _capH], _s getOrDefault ["photoCaption", ""]] call _mk;
_ed ctrlSetFontHeight _fs;
_ed ctrlSetTooltip (("Facultatif : texte envoyé avec la photo (sinon indicatif, grille et cap)") call comspec_atak_native_fnc_tr);
_ed ctrlAddEventHandler ["KeyUp", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["photoCaption", ctrlText (_this select 0)]; }];
uiNamespace setVariable ["COMSPEC_ATAK_PhotoCaptionEdit", _ed];
true
