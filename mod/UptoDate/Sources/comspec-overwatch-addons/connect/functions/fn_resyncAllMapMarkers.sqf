/*
    Relance l’envoi Athena de tous les marqueurs carte visibles.
    Couvre les marqueurs posés avant la liaison Athena (MarkerCreated manqué)
    et ceux du Marker Dropper ATAK Enhanced / Arma.
    Diff par signature pour éviter de spammer SendMarker.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ServerHubActive", false]) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {};

private _markers = +allMapMarkers;
if (!(_markers isEqualType [])) exitWith {};

// Budget : ne pas rejouer toute la carte d’un coup après une longue session
private _budget = 40;
private _spent = 0;

private _prev = missionNamespace getVariable ["COMSPEC_Athena_MapMarkerSnap", createHashMap];
if (!(_prev isEqualType createHashMap)) then { _prev = createHashMap; };
private _next = createHashMap;

private _mirroredElsewherePrefixes = [
    "poi_local_", "qrf_contact_", "medevac_lz_", "vehicle_service_",
    "comspec_tabletmk_", "comspec_webmk_", "comspec_shape_",
    "comspec_relay_", "comspec_recon_", "_comspec_po_ring_", "_comspec_det_ring_", "comspec_gps_", "ctab_u_",
    // Calques locaux du mod (zones tactiques, plan de mission, ordres, portée).
    "comspec_tz_", "comspec_mpgfx_", "comspec_ordwp_", "comspec_lk_", "comspec_reach_"
];
private _hubOwns = isMultiplayer && {missionNamespace getVariable ["COMSPEC_ServerHubOwnsMissionPoll", false]};

{
    private _name = _x;
    if ((_name select [0, 1]) == "_" && {!([_name] call comspec_overwatch_connect_fnc_isSyncableMapMarker)}) then { continue };

    private _nameLower = toLower _name;
    if (({ (_nameLower find _x) == 0 } count _mirroredElsewherePrefixes) > 0) then { continue };

    // Repère d'une autre machine : son poseur (repère joueur) ou le hub serveur (Zeus, mission) l'envoie déjà.
    // Le renvoyer depuis chaque client donnait N+1 envois attribués au mauvais joueur (comme les EH Marker*).
    private _foreign = false;
    if (isMultiplayer) then {
        // Seule la fiche posée par l'EH MarkerCreated local compte : userMapMarkerMeta en écrit une (5e champ true)
        // pour tout repère envoyé, ce qui faisait passer un repère Zeus / mission pour local.
        private _mkMeta = missionNamespace getVariable [format ["COMSPEC_UserMkMeta_%1", _name], []];
        private _hasLocalMeta = (_mkMeta isEqualType []) && {(count _mkMeta) >= 1} && {!((_mkMeta param [4, false]) isEqualTo true)};
        if (((_name find "#") >= 0 || {(_name find "__H__") >= 0}) && {!isNil "comspec_overwatch_connect_fnc_userMapMarkerMeta"}) then {
            private _ownId = ([_name] call comspec_overwatch_connect_fnc_userMapMarkerMeta) getOrDefault ["owner_id", -1];
            if (_ownId isEqualType 0 && {_ownId >= 0} && {_ownId isNotEqualTo clientOwner}) then { _foreign = true; };
        } else {
            if (_hubOwns && {!_hasLocalMeta}) then { _foreign = true; };
        };
    };
    if (_foreign) then {
        // Retiré de l'ancienne photo : pas de faux « supprimé » envoyé par le passage des suppressions.
        _prev deleteAt _name;
        continue
    };

    private _shape = toUpper (markerShape _name);
    private _type = markerType _name;
    if (_type isEqualTo "" && {!(_shape isEqualTo "POLYLINE")}) then {
        if (_shape isEqualTo "ELLIPSE" || {_shape isEqualTo "RECTANGLE"}) then {
            _type = "mil_circle";
        } else {
            _type = "mil_dot";
        };
    };
    if (!(_shape isEqualTo "POLYLINE") && {_type isEqualTo ""}) then { continue };

    private _pos = markerPos _name;
    private _text = markerText _name;
    private _color = markerColor _name;
    private _dir = markerDir _name;
    private _alpha = markerAlpha _name;
    private _size = markerSize _name;
    if (!(_size isEqualType []) || {(count _size) < 2}) then { _size = [1, 1]; };
    private _brush = markerBrush _name;
    private _sig = format [
        "%1|%2|%3|%4|%5|%6|%7|%8|%9|%10|%11",
        _pos select 0, _pos select 1, _type, _text, _color, _dir, _alpha, _shape,
        _size select 0, _size select 1, _brush
    ];
    if ((_prev getOrDefault [_name, ""]) isEqualTo _sig) then {
        _next set [_name, _sig];
        continue
    };

    if (_spent >= _budget) then {
        // Reporter : garder l’ancienne signature pour retenter au prochain passage
        _next set [_name, _prev getOrDefault [_name, ""]];
        continue
    };
    _spent = _spent + 1;
    _next set [_name, _sig];

    // force=true : rattrapage même si la liaison est momentanément dégradée
    // (fiab. < 100 %) — sinon les INF / Widget restent coincés côté téléphone.
    // Ne figer la signature qu’après un envoi réussi, sinon plus aucun essai.
    private _ok = [_name, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
    // Échec définitif (position nulle, polyligne dégénérée) : signature gardée, sinon le repère
    // reprenait une place du budget à chaque passage et pouvait bloquer les suppressions.
    if (!_ok && {!((missionNamespace getVariable ["COMSPEC_LastMarkerSyncSkip", ""]) in ["no_pos", "bad_polyline"])}) then { _next set [_name, ""]; };
} forEach _markers;

// Suppressions : seulement si le budget n’a pas été saturé (sinon on risque des faux positifs)
// OPT-13 : un spawn + extAwait (SendMarker AsyncLocal) — N callExtension sync figeaient.
if (_spent < _budget) then {
    private _dels = [];
    {
        if (!(_x in _next)) then {
            private _delName = _x;
            if ((_delName find "#") >= 0) then {
                _delName = (_delName splitString "#" joinString "__H__");
            };
            _dels pushBack _delName;
        };
    } forEach (keys _prev);
    if ((count _dels) > 0) then {
        [_dels] spawn {
            params ["_dels"];
            {
                if (!isNil "comspec_overwatch_connect_fnc_extAwait") then {
                    ["COMSPECExtension", "SendMarker", [_x, "{}", "1", "1"]] call comspec_overwatch_connect_fnc_extAwait;
                } else {
                    "COMSPECExtension" callExtension ["SendMarker", [_x, "{}", "1", "1"]];
                };
            } forEach _dels;
        };
    };
};

missionNamespace setVariable ["COMSPEC_Athena_MapMarkerSnap", _next, false];
