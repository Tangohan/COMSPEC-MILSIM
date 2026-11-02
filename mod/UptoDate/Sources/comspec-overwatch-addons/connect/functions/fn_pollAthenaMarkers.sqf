/*
    Tire les marqueurs posés sur l’ATAK web vers la carte Arma (sens inverse de SendMarker).
    Ignore les repères déjà originaires du jeu (arma / cTab / BCE) pour éviter les doublons.
*/
if (!hasInterface) exitWith {};
if (isNull player || {!([player] call comspec_overwatch_connect_fnc_hasTerminal)}) exitWith {};
private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (
    !(missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false])
    && {!isNil "comspec_overwatch_connect_fnc_uplinkQuiet"}
    && {[] call comspec_overwatch_connect_fnc_uplinkQuiet}
) exitWith {};

// GET HTTP hors du thread de jeu : spawn + extAwait (un seul en vol, libéré au retour), puis relance
// de cette fonction avec la réponse, traitée en non planifié comme avant (isNil).
private _raw = _this param [0, nil, [""]];
if (isNil "_raw") exitWith {
    if ((diag_tickTime - (missionNamespace getVariable ["COMSPEC_WebMarkersBusyAt", -1e9])) < 60) exitWith { nil };
    // Un seul poste tire les marqueurs : le joueur lié et équipé de plus petit UID. Les marqueurs restent globaux, donc
    // visibles de tous (sans téléphone compris) ; avant, chaque poste équipé faisait la même requête et les mêmes écritures.
    private _cands = (allPlayers select { alive _x && {_x isEqualTo player || {_x getVariable ["COMSPEC_AtakReporterLinked", false]}} }) apply { [getPlayerUID _x, _x] };
    _cands sort true;
    private _first = _cands findIf { [_x select 1] call comspec_overwatch_connect_fnc_hasTerminal };
    if (_first >= 0 && {((_cands select _first) select 1) isNotEqualTo player}) exitWith { nil };
    missionNamespace setVariable ["COMSPEC_WebMarkersBusyAt", diag_tickTime, false];
    ["world:" + worldName] spawn {
        private _r = [["COMSPECExtension", "GetMarkers", _this] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
        missionNamespace setVariable ["COMSPEC_WebMarkersBusyAt", -1e9, false];
        isNil { [_r] call comspec_overwatch_connect_fnc_pollAthenaMarkers };
    };
    nil
};
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {
    ["marqueurs", 0, " (vide ou erreur)"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
};
private _body = _raw select [3, (count _raw) - 3];
if (_body isEqualTo "" || {_body isEqualTo "[]"}) exitWith {
    ["marqueurs", 0, " (aucun)"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
};

// Skip si payload inchangé (évite de rejouer tout l’historique à chaque poll).
// Corps complet comparé : longueur + 48 premiers caractères laissaient passer un déplacement
// (coordonnées de même longueur) ou toute modification après la première ligne.
private _sig = _body;
if (_sig isEqualTo (missionNamespace getVariable ["COMSPEC_WebMarkersSig", ""])) exitWith {
    ["marqueurs", 0, " (inchangé)"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
};
missionNamespace setVariable ["COMSPEC_WebMarkersSig", _sig, false];

private _seen = [];
private _created = 0;
private _capped = false;
private _nl = toString [10];
private _tab = toString [9];
private _lines = _body splitString _nl;
// Index des marqueurs existants (hashmap) : « in allMapMarkers » dans la boucle recopiait toute la liste à chaque ligne.
private _mkIndex = createHashMapFromArray (allMapMarkers apply { [_x, true] });
private _bootMk = missionNamespace getVariable ["COMSPEC_WebMarkersBootstrapped", false];
// Pierres tombales (1.6.30) : un repère web que ce poste avait créé puis qui a été supprimé en jeu (Suppr, clic droit),
// ou la copie web d'une forme retirée par l'ATAK natif (étapes de route, zones : COMSPEC_WebMkGoneSig, position et
// texte), ne revient plus à chaque relevé tant que la partie dure.
private _gone = missionNamespace getVariable ["COMSPEC_WebMkGone", createHashMap];
missionNamespace setVariable ["COMSPEC_WebMkGone", _gone, false];
private _made = missionNamespace getVariable ["COMSPEC_WebMkMade", createHashMap];
missionNamespace setVariable ["COMSPEC_WebMkMade", _made, false];
private _goneSig = missionNamespace getVariable ["COMSPEC_WebMkGoneSig", createHashMap];
if (!_bootMk) then {
    missionNamespace setVariable ["COMSPEC_WebMarkersBootstrapped", true, false];
};
{
    // splitKeepEmpty : libellé / couleur vides ne décalent plus la colonne source (sinon marqueur web ignoré).
    private _cols = [_x, _tab] call comspec_overwatch_connect_fnc_splitKeepEmpty;
    if ((count _cols) < 6) then { continue };
    if ((_cols select 0) != "M") then { continue };

    private _id = _cols select 1;
    private _xPos = parseNumber (_cols select 3);
    private _yPos = parseNumber (_cols select 4);
    private _type = _cols select 5;
    private _text = if ((count _cols) > 6) then { _cols select 6 } else { "" };
    private _color = if ((count _cols) > 7) then { _cols select 7 } else { "" };
    private _source = if ((count _cols) > 8) then { toLower (_cols select 8) } else { "" };

    if (_source in ["arma", "bce_widget", "ctab_user"]) then { continue };
    private _typeL = toLower _type;
    private _fromWeb = (_source in ["web", "manual"]) || {_typeL isEqualTo "manual"};
    if (!_fromWeb) then { continue };
    if ((abs _xPos) < 0.5 && {(abs _yPos) < 0.5}) then { continue };

    _seen pushBack _id;
    private _name = format ["comspec_webmk_%1", _id];
    if (_id in _gone) then { continue };
    if (_name in _made && {!(_name in _mkIndex)}) then { _gone set [_id, true]; diag_log format ["[COMSPEC Overwatch][INFO][MARKERS] Repère web %1 supprimé en jeu : plus recréé", _id]; continue };
    if ((format ["%1|%2|%3", round _xPos, round _yPos, _text]) in _goneSig) then { _gone set [_id, true]; diag_log format ["[COMSPEC Overwatch][INFO][MARKERS] Repère web %1 (%2) retiré par l'ATAK : plus recréé", _id, _text]; continue };
    if (_typeL isEqualTo "manual" || {!(isClass (configFile >> "CfgMarkers" >> _type))}) then {
        _type = "mil_dot";
    };
    if (_color isEqualTo "" || {(_color select [0, 1]) isEqualTo "#"}) then {
        _color = "ColorGreen";
    };
    if (_text isEqualTo "") then { _text = "Repère poste"; };

    if (!(_name in _mkIndex) && {_created >= 25}) then { _capped = true; };
    if (!(_name in _mkIndex) && {_created >= 25}) then { continue };

    private _muted = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _muted, false];
    if (_name in _mkIndex) then {
        // Marqueurs globaux conservés (visibles aussi des joueurs sans terminal, qui ne font pas ce poll),
        // mais seuls les champs qui diffèrent sont réécrits : chaque setMarker* global partait sur le réseau,
        // répété par chaque client équipé ; le premier à mettre à jour suffit, les autres ne voient plus d'écart.
        if (((getMarkerPos _name) distance2D [_xPos, _yPos]) > 0.5) then { _name setMarkerPos [_xPos, _yPos]; };
        if ((markerText _name) isNotEqualTo _text) then { _name setMarkerText _text; };
        if ((markerType _name) isNotEqualTo _type) then { _name setMarkerType _type; };
        if ((markerColor _name) isNotEqualTo _color) then { _name setMarkerColor _color; };
    } else {
        private _mk = createMarker [_name, [_xPos, _yPos]];
        if (_mk isEqualTo "") then { _mk = _name; };
        // Champs posés en local puis un seul setter global final : il diffuse l'état complet en un message
        // au lieu de cinq.
        _mk setMarkerTypeLocal _type;
        _mk setMarkerColorLocal _color;
        _mk setMarkerTextLocal _text;
        _mk setMarkerSizeLocal [0.85, 0.85];
        _mk setMarkerAlpha 1;
        _mkIndex set [_mk, true];
        _made set [_mk, true];
        _created = _created + 1;
    };
    private _unmute = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1;
    if (_unmute < 0) then { _unmute = 0; };
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _unmute, false];
} forEach _lines;
// Plafond de 25 créations atteint : signature oubliée pour créer le reste au passage suivant.
if (_capped) then { missionNamespace setVariable ["COMSPEC_WebMarkersSig", "", false]; };

["marqueurs", count _seen, format [
    " · lignes %1%2",
    count _lines,
    if (_bootMk) then { "" } else { " · première lecture" }
]] call comspec_overwatch_connect_fnc_noteUplinkReturn;

// Marqueurs web présents sur la carte, quel que soit le poste qui les a créés (le relais peut changer en cours de partie).
private _prev = (allMapMarkers select { (_x select [0, 14]) isEqualTo "comspec_webmk_" }) apply { _x select [14] };
if ((count _raw) < 7990) then {
    {
        if (!(_x in _seen)) then {
            private _n = format ["comspec_webmk_%1", _x];
            if (_n in _mkIndex) then {
                private _muted = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1;
                missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _muted, false];
                deleteMarker _n;
                private _unmute = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1;
                if (_unmute < 0) then { _unmute = 0; };
                missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _unmute, false];
            };
        };
    } forEach _prev;
};
