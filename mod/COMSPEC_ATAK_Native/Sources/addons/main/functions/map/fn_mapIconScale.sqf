/*
    Taille des symboles de la carte du téléphone : la seule règle, pour le téléphone en main (grand) comme pour la
    miniature portée. Avant, fn_mapOnDraw multipliait chaque taille par la hauteur de la carte avec un plancher à 0,35 :
    en miniature les symboles tombaient à 9 px, sous le seuil où le pion garde sa lettre, et les réglages d'icônes
    (forme, tailles, réglette « Icônes de la carte ») ne se voyaient plus qu'en grand.
    Désormais le facteur de vue ne descend plus sous 0,6 : la miniature garde des symboles lisibles (à peu près ceux du
    téléphone en main), et chaque réglage y pèse autant qu'en grand. Réglages relus à chaque image : effet immédiat.
    Params : [opération, argument]
      "frame" carte     -> [facteur de vue, réglage « Icônes de la carte », échelle des marqueurs de la mission,
                            facteur des repères d'outil (1 au réglage par défaut), hauteur relative de la carte,
                            taille des alliés, taille des contacts] ; pose uiNamespace COMSPEC_ATAK_MapIs et
                            COMSPEC_ATAK_MkScale (fn_mapPing, fn_mapSelect, fn_wpAt, fn_mapMarkerText). Une fois par image.
      "view" hauteur relative (hauteur de la carte / (safeZoneH * 0,7)) -> facteur de vue, borné de 0,6 à 1,35
      "param" valeur du profil -> réglage borné de 0,8 à 2,2 (1,25 si la valeur est illisible)
      "role" valeur du profil  -> taille des alliés ou des contacts, bornée de 0,5 à 2,5 (1 si illisible)
      "size" [taille de base px, hauteur relative, réglage, facteur de rôle] -> taille dessinée en px (fonction pure)
      "check" -> true si la table de contrôle est respectée (la même table est recalculée par les tests du site)
*/
params [["_op", ""], ["_a", []]];
private _vMin = 0.6;
private _vMax = 1.35;
private _pMin = 0.8;
private _pMax = 2.2;
private _pDef = 1.25;
private _rMin = 0.5;
private _rMax = 2.5;
private _view = { if (_this isEqualType 0) then { (_this max _vMin) min _vMax } else { 1 } };
private _param = { if (_this isEqualType 0) then { (_this max _pMin) min _pMax } else { _pDef } };
private _role = { if (_this isEqualType 0) then { (_this max _rMin) min _rMax } else { 1 } };
switch (_op) do {
    case "frame": {
        private _kRaw = ((ctrlPosition _a) select 3) / (safeZoneH * 0.7);
        private _vk = _kRaw call _view;
        private _is = (profileNamespace getVariable ["COMSPEC_ATAK_MapIconScale", _pDef]) call _param;
        private _mk = 0.62 * (_vk min 1.2) * _is;
        uiNamespace setVariable ["COMSPEC_ATAK_MapIs", _is];
        uiNamespace setVariable ["COMSPEC_ATAK_MkScale", _mk];
        [_vk, _is, _mk, _is / _pDef, _kRaw, (profileNamespace getVariable ["COMSPEC_ATAK_AllyScale", 1]) call _role, (profileNamespace getVariable ["COMSPEC_ATAK_ContactScale", 1]) call _role]
    };
    case "view": { _a call _view };
    case "param": { _a call _param };
    case "role": { _a call _role };
    case "size": {
        _a params [["_base", 16], ["_kRaw", 1], ["_p", _pDef], ["_rk", 1]];
        _base * (_kRaw call _view) * (_p call _param) * (_rk call _role)
    };
    case "check": {
        private _ok = true;
        // [taille de base, hauteur relative de la carte, réglage, facteur de rôle, taille attendue en px]
        {
            _x params ["_base", "_kRaw", "_p", "_rk", "_want"];
            if (abs ((["size", [_base, _kRaw, _p, _rk]] call comspec_atak_native_fnc_mapIconScale) - _want) > 0.001) then { _ok = false; };
        } forEach [
            [16, 0.646, 1.25, 1, 12.92],
            [16, 0.545, 1.25, 1, 12],
            [16, 0.24, 1.25, 1, 12],
            [16, 0.24, 2.2, 1, 21.12],
            [16, 0.24, 0.8, 1, 7.68],
            [16, 0.24, 5, 1, 21.12],
            [16, 0.24, 0.1, 1, 7.68],
            [16, 3, 1.25, 1, 27],
            [21, 1, 1.25, 2.5, 65.625],
            [21, 1, 1.25, 9, 65.625],
            [21, 0.24, 1.25, 0.1, 7.875],
            [16, 1, "x", 1, 20]
        ];
        // En miniature (carte très petite), une réglette plus haute donne toujours un symbole plus grand.
        private _prev = 0;
        {
            private _sz = ["size", [16, 0.24, _x, 1]] call comspec_atak_native_fnc_mapIconScale;
            if (_sz <= _prev) then { _ok = false; };
            _prev = _sz;
        } forEach [0.8, 1, 1.25, 1.6, 2.2];
        _ok
    };
    default { 1 };
};
