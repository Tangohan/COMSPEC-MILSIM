/*
    Liaison facultative avec F-PANO ECOTI (Workshop 3759527903, aussi dans le pack S.O.A.R - CLIENT, classe
    FPANO_ECOTI_PATCH). Ce mod n'a ni fonction publique ni notion d'itinéraire : sous JVN, une fois par seconde, il relit
    allMapMarkers et affiche l'icône, le texte et la distance de chaque marqueur qui a un texte, sans regarder son opacité
    (scripts\FPANO_fnc_mapMarkers.sqf). On lui donne donc la route suivie sous cette forme.
      - Marqueurs locaux (createMarkerLocal), 8 prochaines étapes au plus, opacité 0 : invisibles sur les cartes.
      - Préfixe comspec_tabletmk_ : exclu de l'envoi vers Athena (fn_syncMapMarker, fn_markerWebSweep).
    Mod absent ou réglage comspec_atak_native_route_fpano décoché : rien n'est créé ; le trait et les repères de fn_route3d
    restent le rendu de la route. Appelée par fn_route3d à chaque préparation (une fois par seconde pendant un suivi, et à
    chaque étape). Rend true si F-PANO affiche les étapes (fn_route3d ne dessine alors que le trait).
*/
if (isNil "COMSPEC_ATAK_RteFpanoMod") then {
    COMSPEC_ATAK_RteFpanoMod = isClass (configFile >> "CfgPatches" >> "FPANO_ECOTI_PATCH");
};
if (!COMSPEC_ATAK_RteFpanoMod) exitWith { false };
private _f = missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
private _fid = _f getOrDefault ["id", ""];
private _r = createHashMap;
if (_fid isNotEqualTo "" && {missionNamespace getVariable ["comspec_atak_native_route_fpano", true]} && {alive player} && {[player] call comspec_atak_native_fnc_hasDevice}) then {
    _r = ["get", _fid] call comspec_atak_native_fnc_routeAction;
};
private _on = (count _r) > 0 && {(count (_r get "steps")) > 0};
private _fi = _f getOrDefault ["idx", 0];
private _key = if (_on) then { [_fid, _r get "rev", _fi] } else { [] };
if (_key isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_RteFpanoKey", []])) then {
    missionNamespace setVariable ["COMSPEC_ATAK_RteFpanoKey", _key];
    { deleteMarkerLocal _x; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_RteFpanoMk", []]);
    private _mk = [];
    if (_on) then {
        {
            _x params ["_p", "", "_n"];
            private _m = createMarkerLocal [format ["comspec_tabletmk_route_%1", _forEachIndex], [_p select 0, _p select 1, 0]];
            _m setMarkerTypeLocal "FPANO_marker_POI";
            _m setMarkerAlphaLocal 0;
            _m setMarkerTextLocal format ["%1. %2", _fi + _forEachIndex + 1, [_n] call comspec_atak_native_fnc_markerAutoName];
            _mk pushBack _m;
        } forEach ((_r get "steps") select [_fi, 8]);
    };
    missionNamespace setVariable ["COMSPEC_ATAK_RteFpanoMk", _mk];
};
// F-PANO éteint par le joueur (sa touche, ses réglages) : les repères de fn_route3d reprennent.
_on && {missionNamespace getVariable ["FPANO_HUD_Active", true]} && {missionNamespace getVariable ["FPANO_Setting_ShowMapMarkers", true]}
