/*
    Recherche du lanceur sur place (retour de jeu 2.9.27 : « la page se recharge à chaque lettre ») : les en-têtes et les
    tuiles sont créés une fois par fn_launcherRender (modèle uiNamespace COMSPEC_ATAK_LauncherModel) ; ici on ne fait que
    les masquer, les afficher et les repositionner (ctrlShow, ctrlSetPosition, ctrlCommit 0). Aucun contrôle créé, le
    champ garde le focus et le curseur.
    Recherche sans casse ni accents sur le nom français, le nom traduit et le nom de classe de l'app. Recherche active :
    une section par en-tête (épinglées, récentes et groupes masqués, sections repliées ouvertes), en-têtes sans résultat
    masqués, « Aucune app ne correspond. » si rien.
    Params : [texte, garder le défilement (rendu de la page)]. Retour : page de la première app trouvée (hors W.I.P), "" sinon.
*/
params [["_filter", ""], ["_keepScroll", false]];
disableSerialization;
private _m = uiNamespace getVariable ["COMSPEC_ATAK_LauncherModel", createHashMap];
if ((count _m) isEqualTo 0 || {isNull (_m getOrDefault ["group", controlNull])}) exitWith { "" };
// Frappe différée arrivée après avoir quitté l'accueil : rien à placer.
if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo "LAUNCHER") exitWith { "" };
private _f = ["fold", trim _filter] call comspec_atak_native_fnc_patchCatalog;
private _on = _f isNotEqualTo "";
(_m get "geo") params ["_y0", "_pad", "_tw", "_th", "_cols", "_secH", "_font"];
// Décale une liste de contrôles ; les tuiles gardent leur position de repos pour l'effet d'appui (fn_tileCreate).
private _mv = {
    params ["_cs", "_dx", "_dy", ["_btn", controlNull]];
    if (_dx isEqualTo 0 && {_dy isEqualTo 0}) exitWith {};
    {
        (ctrlPosition _x) params ["_px", "_py", "_pw", "_ph"];
        _x ctrlSetPosition [_px + _dx, _py + _dy, _pw, _ph];
        _x ctrlCommit 0;
    } forEach _cs;
    if (!isNull _btn) then {
        _btn setVariable ["press", (_btn getVariable ["press", []]) apply { _x params ["_k", "_p"]; [_k, [(_p select 0) + _dx, (_p select 1) + _dy, _p select 2, _p select 3]] }];
    };
};
private _y = _y0;
private _first = "";
private _total = 0;
{
    private _gr = _x;
    private _tiles = _gr get "tiles";
    private _folded = _gr get "folded";
    private _hits = if (_on) then { _tiles select { ((_x select 2) find _f) >= 0 } } else { [_tiles, []] select _folded };
    private _vis = [_gr get "showNF", (_gr get "showF") && {(count _hits) > 0}] select _on;
    // En-tête
    private _hy = [_y0, _y] select _vis;
    [_gr get "hdr", 0, _hy - (_gr get "hy")] call _mv;
    _gr set ["hy", _hy];
    { _x ctrlShow _vis; } forEach (_gr get "hdr");
    if (_vis) then {
        private _lbl = _gr get "lbl";
        if (!isNull _lbl) then {
            private _thm = [] call comspec_atak_native_fnc_theme;
            _lbl ctrlSetStructuredText parseText ((format ["<t size='0.85' font='COMSPEC_BarlowBold' color='%4'>%1</t>  <t size='0.75' color='%5'>%2</t>%3", toUpper (_gr get "sec"), [_gr get "cnt", count _hits] select _on, ["", _gr get "extra"] select (!_on && {_folded}), _thm get "hexText", _thm get "hexTextDim"]) call comspec_atak_native_fnc_tr);
        };
        _y = _y + _secH + _pad * 0.6;
    };
    // Groupe vide (puce ÉPINGLÉES, section sans app) hors recherche.
    private _none = _gr getOrDefault ["none", controlNull];
    if (!isNull _none) then {
        private _sn = _vis && {!_on} && {!_folded};
        (ctrlPosition _none) params ["_nx", "_ny"];
        [[_none], 0, ([_y0, _y] select _sn) - _ny] call _mv;
        _none ctrlShow _sn;
        if (_sn) then { _y = _y + _font * 1.4 + _pad; };
    };
    // Tuiles : celles trouvées en grille, les autres masquées en haut (hors de l'étendue de défilement).
    private _i = 0;
    {
        _x params ["_cs", "_btn", "", "_page", "_wip", "_at"];
        private _show = _vis && {_x in _hits};
        private _to = if (_show) then { [_pad + (_i mod _cols) * (_tw + _pad), _y + (floor (_i / _cols)) * (_th + _pad)] } else { [_at select 0, _y0] };
        [_cs, (_to select 0) - (_at select 0), (_to select 1) - (_at select 1), _btn] call _mv;
        _x set [5, _to];
        { _x ctrlShow _show; } forEach _cs;
        if (_show) then {
            _i = _i + 1;
            if (_on && {_first isEqualTo ""} && {!_wip}) then { _first = _page; };
        };
    } forEach _tiles;
    if (_vis) then {
        if (_on) then { _total = _total + (count _hits); };
        _y = _y + ([(ceil (_i / _cols)) * (_th + _pad) + _pad * 0.6, _pad * 0.2] select (!_on && {_folded} && {_i isEqualTo 0}));
    };
} forEach (_m get "groups");
private _empty = _m getOrDefault ["empty", controlNull];
if (!isNull _empty) then { _empty ctrlShow (_on && {_total isEqualTo 0}); };
private _hint = _m getOrDefault ["hint", controlNull];
if (!isNull _hint) then { _hint ctrlShow (_filter isEqualTo ""); };
private _clr = _m getOrDefault ["clear", controlNull];
if (!isNull _clr) then { _clr ctrlShow (_filter isNotEqualTo ""); };
// Frappe : retour en haut de la liste (les résultats y sont, le champ reste visible).
if (!_keepScroll) then {
    private _g = _m getOrDefault ["group", controlNull];
    if (!isNull _g) then { _g ctrlSetScrollValues [0, -1]; };
};
_m set ["first", _first];
_first
