/*
    Panneau SITUATION de la carte : sans sélection, résumé de la mission ; avec une unité sélectionnée (clic),
    sa fiche ATAK : indicatif, nom, grade et fonction, groupe, état, position, distance et gisement, déplacement,
    téléphone (en ligne, batterie, signal), identité ORBAT. Le texte vit dans la section « Situation » du volet
    (fn_pageMap, contrôle COMSPEC_ATAK_InspTextCtrl) ; fond de carte et calques sont les sections suivantes.
    Params : [texte seul] : true renvoie le texte (non traduit) sans rien afficher, pour le rendu du volet.
*/
params [["_build", false]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d && {!_build}) exitWith {};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _e = _s getOrDefault ["selectedEntity", createHashMap];
private _dim = { format ["<t color='#8a9a93'>%1</t>", _this] };
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _text = "";
// Texte venu d'ailleurs (indicatif, nom et fonction du joueur, groupe, véhicule, ORBAT publié par l'autre client) :
// &, < et > échappés pour le texte structuré (un « Tom & Jerry » ou « <TAG> Bob » vidait ou coupait le panneau).
private _esc = {
    private _v = [str _this, _this] select (_this isEqualType "");
    _v = _v regexReplace ["&(?!(amp|lt|gt|quot|apos|#[0-9]+);)", "&amp;"];
    [[_v, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace
};
if ((count _e) isEqualTo 0) then {
    private _units = values ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]);
    private _fr = { (_x getOrDefault ["affiliation", ""]) isEqualTo "friend" } count _units;
    private _ho = { (_x getOrDefault ["affiliation", ""]) isEqualTo "hostile" } count _units;
    // Résumé : une ligne par mesure, libellé à gauche, valeur alignée à droite.
    private _net = _s getOrDefault ["networkState", "OFFLINE"];
    private _netTxt = createHashMapFromArray [["CONNECTED", "<t color='#5cc76b'>connecté</t>"], ["DEGRADED", "<t color='#f2ab33'>dégradé</t>"]] getOrDefault [_net, "<t color='#e5483a'>hors ligne</t>"];
    private _w = wind;
    private _from = (((_w select 0) atan2 (_w select 1)) + 180) mod 360;
    private _kv = { params ["_k", "_v"]; format ["<t align='left' color='#8a9a93'>%1</t><t align='right'>%2</t>", _k, _v] };
    private _tile = { params ["_n", "_lab", "_col"]; format ["<t size='1.6' font='COMSPEC_BarlowBold' color='%3'>%1</t><t size='0.8' color='#8a9a93'> %2</t>", _n, _lab, _col] };
    _text = format ["<t size='0.85'>%1   %2<br/><br/>%3<br/>%4<br/>%5<br/>%6<br/>%7<br/>%8</t><br/><br/><t size='0.75' color='#8a9a93'>Cliquez sur une unité pour sa fiche.</t>",
        [_fr, "alliés", "#47b3ff"] call _tile, [_ho, "contacts", ["#8a9a93", "#e5483a"] select (_ho > 0)] call _tile,
        ["Réseau", _netTxt] call _kv,
        ["Groupe", ([player, true] call comspec_atak_native_fnc_unitGroup) call _esc] call _kv,
        ["Terrain", worldName] call _kv,
        ["Heure", [dayTime, "HH:MM"] call BIS_fnc_timeToString] call _kv,
        ["Pluie", format ["%1 %%", round (rain * 100)]] call _kv,
        ["Vent", format ["%1 m/s du %2", round (vectorMagnitude [_w select 0, _w select 1, 0]), [_from] call _card]] call _kv];
} else {
    private _p = _e getOrDefault ["position", [0, 0, 0]];
    private _o = _e getOrDefault ["object", objNull];
    private _aff = _e getOrDefault ["affiliation", "unknown"];
    private _affTxt = createHashMapFromArray [["friend", "<t color='#47b3ff'>ami</t>"], ["hostile", "<t color='#e5483a'>ennemi</t>"], ["neutral", "<t color='#5cc76b'>neutre</t>"]] getOrDefault [_aff, "inconnu"];
    private _fresh = createHashMapFromArray [["LIVE", "<t color='#5cc76b'>en direct</t>"], ["STALE", "<t color='#f2ab33'>retardée</t>"], ["LOST", "<t color='#e5483a'>perdue</t>"], ["OFFLINE", "<t color='#8a9a93'>hors ligne</t>"]] getOrDefault [_e getOrDefault ["freshness", "LIVE"], "?"];
    private _lines = [format ["<t size='1.15' font='COMSPEC_BarlowBold' color='#5cc76b'>%1</t>  %2", (_e getOrDefault ["callsign", "CONTACT"]) call _esc, _affTxt]];
    if (!isNull _o) then {
        if (isPlayer _o && {_aff isEqualTo "friend"}) then { _lines pushBack format ["%1 %2", "Nom :" call _dim, (name _o) call _esc]; };
        private _role = roleDescription _o;
        if (_role isEqualTo "") then { _role = getText (configOf _o >> "displayName"); };
        _lines pushBack format ["%1 %2 · %3", "Rang :" call _dim, createHashMapFromArray [["PRIVATE", "Soldat"], ["CORPORAL", "Caporal"], ["SERGEANT", "Sergent"], ["LIEUTENANT", "Lieutenant"], ["CAPTAIN", "Capitaine"], ["MAJOR", "Commandant"], ["COLONEL", "Colonel"]] getOrDefault [rank _o, rank _o], _role call _esc];
        if (_aff isEqualTo "friend") then { _lines pushBack format ["%1 %2%3", "Groupe :" call _dim, ([_o, true] call comspec_atak_native_fnc_unitGroup) call _esc, ["", " (chef)"] select (leader group _o isEqualTo _o)]; };
        private _life = switch (true) do {
            case (!alive _o): { "<t color='#e5483a'>mort</t>" };
            case !(["state", "allies"] call comspec_atak_native_fnc_medShow): { "<t color='#8a9a93'>—</t>" };
            case ((lifeState _o) isEqualTo "INCAPACITATED"): { "<t color='#e5483a'>inconscient</t>" };
            case ((damage _o) > 0.4): { "<t color='#f2ab33'>blessé</t>" };
            default { "<t color='#5cc76b'>valide</t>" };
        };
        _lines pushBack format ["%1 %2", "État :" call _dim, _life];
    };
    _lines pushBack format ["%1 <t font='EtelkaMonospacePro'>%2</t> · %3 m", "Position :" call _dim, [_p, 8] call comspec_atak_native_fnc_gridRef, round (_p select 2)];
    _lines pushBack format ["%1 %2 · %3° %4", "Distance :" call _dim, [player distance2D _p] call _fmt, round (player getDir _p), [player getDir _p] call _card];
    if (!isNull _o && {alive _o}) then {
        private _v = vehicle _o;
        _lines pushBack format ["%1 %2 km/h · cap %3°%4", "Mouvement :" call _dim, round ((speed _v) max 0), round getDir _v,
            ["", format [" · à bord : %1", (getText (configOf _v >> "displayName")) call _esc]] select (_v isNotEqualTo _o)];
    };
    if (!isNull _o && {_aff isEqualTo "friend"} && {isPlayer _o}) then {
        (_o getVariable ["COMSPEC_ATAK_Pub", []]) params [["_bat", -1], ["_bars", -1], ["_st", ""]];
        private _on = _o getVariable ["COMSPEC_ATAK_Beacon", true];
        _lines pushBack format ["%1 %2%3%4", "Téléphone :" call _dim, ["<t color='#e5483a'>hors ligne</t>", "<t color='#5cc76b'>en ligne</t>"] select _on,
            ["", format [" · batterie %1 %%", _bat]] select (_bat >= 0), ["", format [" · signal %1/4", _bars]] select (_bars >= 0)];
        if (_st in ["DAMAGED", "BROKEN"]) then { _lines pushBack format ["%1 %2", "Appareil :" call _dim, ["écran fêlé", "détruit"] select (_st isEqualTo "BROKEN")]; };
        private _orbat = _o getVariable ["COMSPEC_ATAK_Orbat", ""];
        if (_orbat isNotEqualTo "") then { _lines pushBack format ["%1 %2", "ORBAT :" call _dim, _orbat call _esc]; };
    };
    _lines pushBack format ["%1 %2 · il y a %3 s", "Liaison :" call _dim, _fresh, round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]))];
    _text = format ["<t size='0.9'>%1</t>", _lines joinString "<br/>"];
};
if (_build) exitWith { _text };
// Autre unité cliquée (ou sélection levée) : en-tête de section et fiche changent, la carte est redessinée.
if ((uiNamespace getVariable ["COMSPEC_ATAK_InspSelKey", ""]) isNotEqualTo (["", _e getOrDefault ["callsign", "CONTACT"]] select ((count _e) > 0))
    && {missionNamespace getVariable ["COMSPEC_ATAK_InspOpen", false]}
    && {(_s getOrDefault ["pageDrawn", ""]) isEqualTo "MAP"} && {!(([] call comspec_atak_native_fnc_layoutGet) get "mini")}) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_InspSelKey", ["", _e getOrDefault ["callsign", "CONTACT"]] select ((count _e) > 0)];
    [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
private _ctl = uiNamespace getVariable ["COMSPEC_ATAK_InspTextCtrl", controlNull];
if (isNull _ctl) exitWith {};
_ctl ctrlSetStructuredText parseText ((_text) call comspec_atak_native_fnc_tr);
// Le volet est posé à la hauteur du texte du rendu : une fiche qui gagne ou perd des lignes (montée en véhicule,
// téléphone abîmé) redessine la carte pour que rien ne soit coupé ni ne laisse un trou (au plus toutes les 2 s).
private _dh = (ctrlTextHeight _ctl) - ((ctrlPosition _ctl) select 3);
if ((abs _dh) > (([] call comspec_atak_native_fnc_layoutGet) get "fontSmall") * 0.5 && {diag_tickTime > (uiNamespace getVariable ["COMSPEC_ATAK_InspReflow", 0])}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_InspReflow", diag_tickTime + 2];
    [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
// Pas de notification ici : ce panneau est redessiné chaque seconde (fn_statusUpdate). Le bandeau « indicatif · grille »
// de l'écran réduit part une seule fois, au clic sur l'unité (fn_mapSelect).
