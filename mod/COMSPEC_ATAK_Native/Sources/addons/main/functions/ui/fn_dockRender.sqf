/*
    Raccourcis d'apps : dock en bas en mode mini, rail vertical à gauche en plein écran.
    Perf (lot 3) : rendu incrémental. Chaque rendu de page repassait ici (fn_layoutApply) et supprimait puis recréait
    toutes les tuiles (une trentaine de contrôles). Les tuiles ne sont plus recréées que si ce qu'elles montrent change :
    signature (COMSPEC_ATAK_DockSig) faite de la taille du dock, des apps (page, icône, nom, pastille, teinte), du thème,
    du style d'icône, de la police et de la langue. Signature identique : les tuiles restent, seul le fond de l'app
    ouverte est recoloré sur place quand la page active change. Même apparence qu'un rendu complet.
    Mesure : « dock (dessin) » (rendu complet) et « dock (gardé) » au RPT.
*/
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith { false };
private _dockT0 = diag_tickTime;
// Tuiles retirées (verrouillage, téléphone d'un autre, carte en mode PETIT, rendu complet) : signature oubliée avec elles.
private _dockClear = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_DockControls", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_DockControls", []];
    uiNamespace setVariable ["COMSPEC_ATAK_DockSig", ""];
    uiNamespace setVariable ["COMSPEC_ATAK_DockTiles", []];
};
// 4.2.1 : tuiles posées dans un groupe créé une fois avec l'écran (avant les calques d'écran) : Arma dessine les enfants
// d'un groupe à la place du groupe, les fêlures et le filtre de nuit restent donc devant le dock sans être recréés à
// chaque page (fn_pageRender). Groupe masqué tant qu'il est vide (il ne prend aucun clic à la carte en mode PETIT).
private _grp = uiNamespace getVariable ["COMSPEC_ATAK_DockGroup", controlNull];
if (isNull _grp || {(ctrlParent _grp) isNotEqualTo _d}) then {
    _grp = _d ctrlCreate ["RscControlsGroupNoScrollbars", -1];
    uiNamespace setVariable ["COMSPEC_ATAK_DockGroup", _grp];
};

// Écran de verrouillage affiché : ni dock ni rail par-dessus (il revient au déverrouillage, fn_lockScreen "unlock").
if (uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) exitWith { _grp ctrlShow false; call _dockClear; true };
// Téléphone d'un autre à l'écran (fn_phoneOwn) : pas de raccourcis vers les apps du lecteur.
if ((missionNamespace getVariable ["COMSPEC_ATAK_PhoneActive", ""]) isNotEqualTo "") exitWith { _grp ctrlShow false; call _dockClear; true };
private _l = [] call comspec_atak_native_fnc_layoutGet;
// Carte en mode PETIT : dock et rail retirés (fn_layoutGet).
if (((_l get "dockRect") select 3) <= 0 && {((_l get "rail") select 2) <= 0}) exitWith { _grp ctrlShow false; call _dockClear; true };
private _active = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "LAUNCHER"];
// Dock choisi par le joueur (Réglages > Personnalisation), sinon celui des apps déclarées « dock ».
private _mine = profileNamespace getVariable ["COMSPEC_ATAK_DockApps", []];
// Apps en chantier (wip=1) : jamais au dock, même choisies avant.
private _all = ([] call comspec_atak_native_fnc_appList) select { !(_x getOrDefault ["wip", false]) };
private _apps = if ((count _mine) > 0) then { (_mine apply { private _id = _x; (_all select { (_x get "id") isEqualTo _id }) param [0, createHashMap] }) select { (count _x) > 0 && {[_x] call comspec_atak_native_fnc_appVisible} } } else { _all select { (_x get "dock") && {[_x] call comspec_atak_native_fnc_appVisible} } };
private _n = (count _apps) max 1;
private _made = [];
// Groupe à la place du dock (portrait) ou du rail (paysage) : positions des tuiles relatives à son coin.
private _gRect = [_l get "rail", _l get "dockRect"] select (_l get "dock");
_gRect params ["_gx", "_gy"];
private _th = [] call comspec_atak_native_fnc_theme;
private _activeBg = (((_th get "accent") select [0, 3]) apply { _x * 0.45 }) + [1];
// Signature de ce que les tuiles montrent. La place du groupe (x, y) n'en fait pas partie : les tuiles sont posées
// par rapport à son coin, le groupe seul se déplace (glisser du téléphone).
private _badges = _apps apply { [_x get "page"] call comspec_atak_native_fnc_appBadge };
private _sig = str [
    _gRect select 2, _gRect select 3, _l get "dock", _l get "fontSmall", uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW], pixelW, pixelH,
    _apps apply { [_x get "page", _x get "icon", _x get "name", ["color", _x get "page"] call comspec_atak_native_fnc_iconTint] }, _badges,
    _th get "name", _th get "chrome", _th get "accent", ["on"] call comspec_atak_native_fnc_iconTint,
    missionNamespace getVariable ["COMSPEC_ATAK_FontSet", []], missionNamespace getVariable ["COMSPEC_ATAK_FontAlt", false],
    missionNamespace getVariable ["COMSPEC_ATAK_Lang", ""], missionNamespace getVariable ["COMSPEC_ATAK_I18nLoaded", ""]
];
private _old = uiNamespace getVariable ["COMSPEC_ATAK_DockControls", []];
private _tiles = uiNamespace getVariable ["COMSPEC_ATAK_DockTiles", []];
// Tuiles gardées : même signature, toutes encore là, dans ce groupe (un écran recréé repart de zéro).
if ((uiNamespace getVariable ["COMSPEC_ATAK_DockSig", ""]) isEqualTo _sig
    && {(count _old) > 0 || {(count _apps) isEqualTo 0}}
    && {(_old findIf { isNull _x || {(ctrlParentControlsGroup _x) isNotEqualTo _grp} }) < 0}) exitWith {
    _grp ctrlSetPosition _gRect;
    _grp ctrlCommit 0;
    _grp ctrlShow true;
    // App ouverte : fond à l'accent assombri ; les autres retrouvent le fond des barres système (fn_tileCreate).
    if ((uiNamespace getVariable ["COMSPEC_ATAK_DockActive", ""]) isNotEqualTo _active) then {
        uiNamespace setVariable ["COMSPEC_ATAK_DockActive", _active];
        {
            _x params ["_tilePage", "_tileBg"];
            if (!isNull _tileBg) then { _tileBg ctrlSetBackgroundColor ([_th get "chrome", _activeBg] select (_tilePage isEqualTo _active)); };
        } forEach _tiles;
    };
    ["dock (gardé)", _dockT0] call comspec_atak_native_fnc_perfAcc;
    true
};
call _dockClear;
_tiles = [];
_grp ctrlSetPosition _gRect;
_grp ctrlCommit 0;
_grp ctrlShow true;

{
    private _app = _x;
    private _pos = if (_l get "dock") then {
        (_l get "dockRect") params ["_x0", "_y0", "_w", "_h"];
        private _cw = _w / _n;
        [_x0 + _forEachIndex * _cw + _cw * 0.08, _y0 + _h * 0.1, _cw * 0.84, _h * 0.8]
    } else {
        (_l get "rail") params ["_x0", "_y0", "_w", "_h"];
        private _ch = (_w * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]) * 0.8) min (_h / _n * 0.9);
        [_x0 + _w * 0.1, _y0 + _w * 0.1 + _forEachIndex * (_ch + _w * 0.1), _w * 0.8, _ch]
    };
    _pos = [(_pos select 0) - _gx, (_pos select 1) - _gy, _pos select 2, _pos select 3];
    private _tile = [_d, _grp, _pos, _app get "icon", "", _badges select _forEachIndex, _app get "page", _l get "fontSmall"] call comspec_atak_native_fnc_tileCreate;
    // App ouverte : fond à l'accent assombri (fn_theme), comme l'onglet choisi d'une barre d'onglets.
    if ((_app get "page") isEqualTo _active) then { (_tile select 0) ctrlSetBackgroundColor _activeBg; };
    (_tile select (count _tile - 1)) ctrlSetTooltip (((_app get "name")) call comspec_atak_native_fnc_tr);
    _tiles pushBack [_app get "page", _tile select 0];
    _made append _tile;
} forEach _apps;

uiNamespace setVariable ["COMSPEC_ATAK_DockControls", _made];
uiNamespace setVariable ["COMSPEC_ATAK_DockTiles", _tiles];
uiNamespace setVariable ["COMSPEC_ATAK_DockSig", _sig];
uiNamespace setVariable ["COMSPEC_ATAK_DockActive", _active];
["dock (dessin)", _dockT0] call comspec_atak_native_fnc_perfAcc;
true
