/*
    Marqueurs natifs des tracés du téléphone (routes, plans de zones, itinéraire) : un seul chemin de création et de
    suppression, et le lien marqueur -> élément du tracé.
      - nom public réservé : _USER_DEFINED #<client>/77000<n>/<canal> (forme d'un marqueur de joueur : carte d'Arma, Athena,
        suppression à la main) ; la carte du téléphone dessine déjà le tracé elle-même (fn_routeDraw, fn_zoneDraw) et saute
        ces marqueurs (fn_mapGameMarkers, fn_mapMarkerText, fn_mapOnDraw, fn_markerAt) : plus de nom écrit deux fois ;
      - tracé privé (« Moi seul ») : comspec_shape_prv_<client>_<n>, marqueur local, jamais diffusé ni envoyé au site (ce
        préfixe est ignoré par les relevés de COMSPEC Link et par fn_markerWebSweep) ;
      - marqueur retiré hors du téléphone (carte d'Arma, Athena) : l'élément du tracé suit (« gone »), sinon le prochain
        envoi le faisait revenir.
    Table locale COMSPEC_ATAK_MkOwned : nom -> [app ROUTE | ZONE | WP, id, genre, élément].
    Params : [opération, argument]
      "make" [portée (0 privé), canal, position]    -> nom du marqueur créé ("" : refusé)
      "paint" [nom, couleur]                        couleur, dernier réglage (diffuse l'état, sauf marqueur local)
      "reg" [nom, [app, id, genre, élément]]        "unreg" nom (itinéraire : pas de lien, reposé à son prochain envoi)
      "del" nom                                     retiré ici, de la carte d'Arma et d'Athena, sans toucher au tracé
      "gone" nom                                    EH MarkerDeleted : retiré par quelqu'un d'autre, le tracé suit
*/
params [["_op", ""], ["_arg", []]];
private _t = missionNamespace getVariable "COMSPEC_ATAK_MkOwned";
if (isNil "_t") then { _t = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MkOwned", _t]; };
private _isPrv = { (_this find "comspec_shape_prv_") isEqualTo 0 };
switch (_op) do {
    case "make": {
        _arg params ["_scope", "_ch", "_pos"];
        private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
        missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _i];
        if (_scope <= 0) exitWith { createMarkerLocal [format ["comspec_shape_prv_%1_%2", clientOwner, _i], _pos] };
        ["create", [format ["_USER_DEFINED #%1/77000%2/%3", clientOwner, _i, _ch], _pos, _ch]] call comspec_atak_native_fnc_markerNet
    };
    case "paint": {
        _arg params ["_m", "_col"];
        // Mode « réseau ATAK » ou tracé privé : marqueur local, un réglage global le rendrait public.
        if ((_m call _isPrv) || {["on"] call comspec_atak_native_fnc_markerNet}) then { _m setMarkerColorLocal _col; } else { _m setMarkerColor _col; };
    };
    case "reg": { _arg params ["_m", "_e"]; _t set [_m, _e]; };
    case "unreg": { _t deleteAt _arg; };
    case "del": {
        _t deleteAt _arg;
        if (_arg call _isPrv) exitWith { deleteMarkerLocal _arg; };
        [_arg, true] call comspec_atak_native_fnc_markerWeb;
        deleteMarker _arg;
    };
    case "gone": {
        private _e = _t getOrDefault [_arg, []];
        if ((count _e) isEqualTo 0) exitWith {};
        _t deleteAt _arg;
        _e params ["_app", "_id", "_kind", "_el"];
        ["INFO", "MARKERS", format ["Marqueur %1 retiré hors du téléphone : %2 %3 %4 %5", _arg, _app, _id, _kind, _el]] call comspec_atak_native_fnc_log;
        // À l'image suivante : le moteur est encore dans la suppression du marqueur.
        [{
            params ["_app", "_id", "_kind", "_el"];
            switch (_app) do {
                case "ROUTE": { ["goneEl", [_id, _kind, _el]] call comspec_atak_native_fnc_routeAction; };
                case "ZONE": { ["goneEl", [_id, _kind, _el]] call comspec_atak_native_fnc_zoneAction; };
            };
        }, _e] call comspec_atak_native_fnc_nextFrame;
    };
};
