/*
    Contrôle carte du téléphone à utiliser selon le fond choisi (fn_mapStyle) : TOPO, CLAIR, SOMBRE, NUIT et PHOTO sur la
    carte topographique (88530), SATELLITE sur la carte satellite (88532), HYBRIDE sur la carte hybride (88533), ARDOISE sur sa carte (88534)
    (ui/controls.hpp : la texture satellite et les couleurs d'Arma ne se règlent que dans la classe du contrôle).
    Au changement de fond, la nouvelle carte reprend le centre et l'échelle de l'ancienne, s'affiche à sa place et
    l'ancienne se masque. Un écran sans cartes satellite (tablette JPC…) garde la carte 88530.
    Params : [écran (défaut : le téléphone), toutes (false)] -> contrôle ; avec toutes = true -> les quatre contrôles.
*/
params [["_d", displayNull], ["_all", false]];
// PC du poste TOC ouvert (fn_tocLaptop, fn_tocDraw) : sa carte sert de carte aux outils de dessin partagés avec le
// téléphone (fn_markerStroke) le temps qu'il est ouvert.
private _ov = uiNamespace getVariable ["COMSPEC_ATAK_MapCtrlOverride", controlNull];
if (isNull _d && {!_all} && {!isNull _ov}) exitWith { _ov };
if (isNull _d) then { _d = [] call comspec_atak_native_fnc_display; };
if (_all) exitWith { [88530, 88532, 88533, 88534] apply { _d displayCtrl _x } };
private _idc = switch ([] call comspec_atak_native_fnc_mapStyle) do { case "SAT": { 88532 }; case "HYBRID": { 88533 }; case "SLATE": { 88534 }; default { 88530 }; };
private _c = _d displayCtrl _idc;
if (isNull _c) exitWith { _d displayCtrl 88530 };
private _prev = _d getVariable ["COMSPEC_ATAK_MapIdc", 88530];
if (_prev isNotEqualTo _idc) then {
    _d setVariable ["COMSPEC_ATAK_MapIdc", _idc];
    private _o = _d displayCtrl _prev;
    if (!isNull _o) then {
        (ctrlPosition _o) params ["_ox", "_oy", "_ow", "_oh"];
        _c ctrlSetPosition [_ox, _oy, _ow, _oh];
        _c ctrlCommit 0;
        _c ctrlMapAnimAdd [0, ctrlMapScale _o, _o ctrlMapScreenToWorld [_ox + _ow / 2, _oy + _oh / 2]];
        ctrlMapAnimCommit _c;
        _c ctrlShow (ctrlShown _o);
        _o ctrlShow false;
    };
};
_c
