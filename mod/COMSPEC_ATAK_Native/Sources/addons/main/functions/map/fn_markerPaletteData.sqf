/*
    Données de la palette de marqueurs (mises en cache) :
    [camps [[clé, libellé, couleur Arma, rgba]], HashMap clé du camp → [[classe CfgMarkers, libellé]]].
    Les classes absentes de la config (mods, versions) sont ignorées.
*/
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_MarkerPalette", []];
if ((count _cache) > 0) exitWith { _cache };
private _rgba = { params ["_cls"]; (getArray (configFile >> "CfgMarkerColors" >> _cls >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } } };
private _affs = [
    ["o", "ENNEMI", "ColorEAST"], ["b", "AMI", "ColorWEST"], ["n", "NEUTRE", "ColorGUER"], ["u", "INCONNU", "ColorUNKNOWN"], ["mil", "TACTIQUE", "ColorBlack"], ["lib", "TOUS", "ColorBlack"]
] apply { _x + [[_x select 2] call _rgba] };
_affs = _affs apply { if ((count (_x select 3)) isEqualTo 4) then { _x } else { [_x select 0, _x select 1, _x select 2, [0.9, 0.9, 0.9, 1]] } };
private _unit = [
    ["inf", "Infanterie"], ["motor_inf", "Motorisé"], ["mech_inf", "Mécanisé"], ["armor", "Blindé"], ["recon", "Reconnaissance"],
    ["art", "Artillerie"], ["mortar", "Mortier"], ["antiair", "Anti-aérien"], ["air", "Hélicoptère"], ["plane", "Avion"],
    ["uav", "Drone"], ["naval", "Naval"], ["hq", "Poste de commandement"], ["support", "Soutien"], ["maint", "Maintenance"],
    ["service", "Logistique"], ["med", "Médical"], ["installation", "Installation"], ["unknown", "Inconnu"]
];
private _mil = [
    ["mil_objective", "Objectif"], ["mil_warning", "Danger"], ["mil_destroy", "À détruire"], ["mil_ambush", "Embuscade"],
    ["mil_pickup", "LZ / PZ"], ["mil_flag", "Drapeau"], ["mil_start", "Départ"], ["mil_end", "Arrivée"], ["mil_join", "Jonction"],
    ["mil_dot", "Point"], ["mil_marker", "Repère"], ["mil_circle", "Cercle"], ["mil_box", "Carré"], ["mil_triangle", "Triangle"],
    ["mil_arrow", "Flèche"], ["mil_arrow2", "Flèche pleine"], ["mil_unknown", "Inconnu"], ["mil_triangle_noShadow", "Triangle plat"],
    ["hd_dot", "Point (HD)"], ["Minefield", "Champ de mines"], ["loc_Hospital", "Hôpital"], ["respawn_inf", "Point de ralliement"]
];
private _by = createHashMap;
{
    private _k = _x select 0;
    private _list = switch (_k) do {
        case "mil": { _mil };
        // Bibliothèque : tous les marqueurs chargés, filtrés par catégorie dans la palette.
        case "lib": { (([] call comspec_atak_native_fnc_markerCatalog) select 0) apply { [_x select 1, _x select 0, _x select 3] } };
        default { _unit apply { [format ["%1_%2", _k, _x select 0], _x select 1] } };
    };
    _by set [_k, _list select { isClass (configFile >> "CfgMarkers" >> (_x select 0)) }];
} forEach _affs;
_cache = [_affs, _by];
uiNamespace setVariable ["COMSPEC_ATAK_MarkerPalette", _cache];
_cache
