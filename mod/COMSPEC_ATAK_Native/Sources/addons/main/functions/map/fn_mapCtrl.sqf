/*
    Contrôle carte du téléphone à utiliser selon le fond choisi (fn_mapStyle) : TOPO, CLAIR, SOMBRE, NUIT et PHOTO sur la
    carte topographique (88530), SATELLITE sur la carte satellite (88532), HYBRIDE sur la carte hybride (88533)
    (ui/controls.hpp : la texture satellite d'Arma ne se règle que dans la classe du contrôle).
    Au changement de fond, la nouvelle carte reprend le centre et l'échelle de l'ancienne, s'affiche à sa place et
    l'ancienne se masque. Un écran sans cartes satellite (tablette JPC…) garde la carte 88530.
    Params : [écran (défaut : le téléphone), toutes (false)] -> contrôle ; avec toutes = true -> les trois contrôles.
*/
params [["_d", displayNull], ["_all", false]];
if (isNull _d) then { _d = [] call comspec_atak_native_fnc_display; };
if (_all) exitWith { [88530, 88532, 88533] apply { _d displayCtrl _x } };
private _idc = switch ([] call comspec_atak_native_fnc_mapStyle) do { case "SAT": { 88532 }; case "HYBRID": { 88533 }; default { 88530 }; };
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
