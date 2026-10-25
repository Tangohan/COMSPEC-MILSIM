/*
    Pose un marqueur joueur, comme la carte Arma : il est partagé avec le canal choisi dans la palette (Général par
    défaut, fn_markerPlace "channel") et COMSPEC Link l'envoie à Athena (EH MarkerCreated). Canal refusé par le
    serveur (Général désactivé…) : posé sur le canal Camp, puis sur le canal courant.
    DURÉE de la palette (profil COMSPEC_ATAK_MarkerTtl, minutes, 0 permanent) : effacé à l'échéance. Le camp, le type et
    la couleur posés vont en tête des RÉCENTS de la palette (profil COMSPEC_ATAK_MarkerRecent).
    Nom : TEXTE de la palette (state markerText ; numéroté seulement avec « Plusieurs marqueurs »), sinon modèle du réglage
    d'addon « Nom des marqueurs posés » (comspec_atak_native_marker_name : {camp} {type} {n} {grille} {indicatif} {heure}),
    sinon « camp type n ». Numéro : à la suite (compteur de la mission) ou premier libre (réglage
    comspec_atak_native_marker_numbering « fill » : après « ENI INFANTERIE 1, 3 », le suivant est 2).
*/
params ["_pos", ["_kind", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Palette : camp + type + couleur + taille choisis dans le mode MARQUEUR.
([] call comspec_atak_native_fnc_markerPaletteData) params ["_affs", "_typesBy"];
private _aff = _s getOrDefault ["markerAff", "o"];
private _type = _s getOrDefault ["markerType", ""];
if !(isClass (configFile >> "CfgMarkers" >> _type)) then { _type = ((_typesBy getOrDefault [_aff, []]) param [0, ["mil_dot"]]) select 0; };
private _affRow = _affs select ((_affs findIf { (_x select 0) isEqualTo _aff }) max 0);
private _color = _s getOrDefault ["markerColor", "AUTO"];
if (_color isEqualTo "AUTO" || {!isClass (configFile >> "CfgMarkerColors" >> _color)}) then { _color = _affRow select 2; };
private _label = ((_typesBy getOrDefault [_aff, []]) select { (_x select 0) isEqualTo _type }) param [0, ["", getText (configFile >> "CfgMarkers" >> _type >> "name")]] select 1;
if (_aff isEqualTo "lib") then { _label = ([] call comspec_atak_native_fnc_markerLabels) getOrDefault [toLower _type, getText (configFile >> "CfgMarkers" >> _type >> "name")]; };
// Nom posé dans la langue du mod (anglais par défaut : « ENY INFANTRY 3 ») ; les autres joueurs le relisent dans la leur
// sur le téléphone (fn_markerAutoName).
private _isFr = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr";
_label = toUpper (_label call comspec_atak_native_fnc_tr);
private _prefix = if (_aff in ["mil", "lib"]) then { _label } else { format ["%1 %2", ([["ENY", "FRD", "NEU", "UNK"], ["ENI", "AMI", "NEU", "INC"]] select _isFr) select ((["o", "b", "n", "u"] find _aff) max 0), _label] };
private _size = _s getOrDefault ["markerSize", 1];
private _index = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _index];
// Texte posé : modèle {n} remplacé par le numéro (à la suite, ou premier numéro libre parmi les marqueurs de la carte).
private _custom = trim (_s getOrDefault ["markerText", ""]);
private _fmt = trim (missionNamespace getVariable ["comspec_atak_native_marker_name", ""]);
if !(_fmt isEqualType "") then { _fmt = ""; };
private _tpl = switch (true) do {
    case (_custom isNotEqualTo ""): { [_custom, _custom + " {n}"] select (profileNamespace getVariable ["COMSPEC_ATAK_MarkerSeries", false]) };
    case (_fmt isNotEqualTo ""): {
        private _t = _fmt;
        {
            _x params ["_k", "_v"];
            _t = _t regexReplace [_k, (_v regexReplace ["\$", ""])];
        } forEach [
            ["\{camp\}", ([["ENY", "FRD", "NEU", "UNK"], ["ENI", "AMI", "NEU", "INC"]] select _isFr) select ((["o", "b", "n", "u"] find _aff) max 0)],
            ["\{type\}", _label], ["\{grille\}|\{grid\}", mapGridPosition _pos], ["\{indicatif\}|\{callsign\}", [player] call comspec_atak_native_fnc_unitCallsign],
            ["\{heure\}|\{time\}", [dayTime, "HH:MM"] call BIS_fnc_timeToString]
        ];
        (trim (_t regexReplace ["\s+", " "])) regexReplace ["\{N\}", "{n}"]
    };
    default { _prefix + " {n}" };
};
private _num = _index;
if ((_tpl find "{n}") >= 0 && {(missionNamespace getVariable ["comspec_atak_native_marker_numbering", "next"]) isEqualTo "fill"}) then {
    private _taken = createHashMap;
    { private _t = toUpper (markerText _x); _taken set [_t, true]; _taken set [toUpper ([_t] call comspec_atak_native_fnc_markerAutoName), true]; } forEach allMapMarkers;
    _num = 1;
    while { _num < 999 && {(toUpper (_tpl regexReplace ["\{n\}", str _num])) in _taken} } do { _num = _num + 1; };
};
private _text = (_tpl regexReplace ["\{n\}", str _num]) select [0, 60];
private _want = ["channel"] call comspec_atak_native_fnc_markerPlace;
private _m = "";
private _channel = _want;
{
    _channel = _x;
    // Même schéma de nom que les marqueurs posés à la main : visible sur le canal, supprimable par le joueur.
    _m = createMarker [format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _index, _channel], _pos, _channel, player];
    if (_m isNotEqualTo "") exitWith {};
} forEach ([_want, 1, currentChannel] arrayIntersect [_want, 1, currentChannel]);
if (_m isEqualTo "") exitWith {
    ["WARNING", "Marqueur refusé sur ce canal", 3, 20] call comspec_atak_native_fnc_notify;
    ""
};
if (_channel isNotEqualTo _want) then {
    ["WARNING", format ["Canal %1 refusé par le serveur : marqueur posé sur le canal %2", ["channelName", _want] call comspec_atak_native_fnc_markerPlace, ["channelName", _channel] call comspec_atak_native_fnc_markerPlace], 5, 20] call comspec_atak_native_fnc_notify;
};
_m setMarkerTypeLocal _type;
_m setMarkerColorLocal _color;
_m setMarkerSizeLocal [_size, _size];
_m setMarkerText _text;
[_m] call comspec_atak_native_fnc_markerWeb;
// Durée choisie dans la palette (DURÉE) : le marqueur s'efface tout seul à l'échéance, pour tout le canal.
private _ttl = profileNamespace getVariable ["COMSPEC_ATAK_MarkerTtl", 0];
if !(_ttl isEqualType 0) then { _ttl = 0; };
if (_ttl > 0) then {
    [{ if ((markerShape _this) isNotEqualTo "") then { deleteMarker _this; }; }, _m, _ttl * 60] call CBA_fnc_waitAndExecute;
};
// Récents de la palette : ce camp, ce type et cette couleur en tête (6 au plus, gardés dans le profil).
private _rec = [_aff, _type, _s getOrDefault ["markerColor", "AUTO"]];
profileNamespace setVariable ["COMSPEC_ATAK_MarkerRecent", ([_rec] + ((profileNamespace getVariable ["COMSPEC_ATAK_MarkerRecent", []]) select { _x isNotEqualTo _rec })) select [0, 6]];
["SUCCESS", format ["%1 · %2 · canal %3%4", _text, mapGridPosition _pos, ["channelName", _channel] call comspec_atak_native_fnc_markerPlace,
    ["", format [" · effacé dans %1 min", _ttl]] select (_ttl > 0)], 3, 20] call comspec_atak_native_fnc_notify;
// TEXTE de la palette : pour ce marqueur seulement, sauf en pose de plusieurs marqueurs (numérotés).
if !(profileNamespace getVariable ["COMSPEC_ATAK_MarkerSeries", false]) then { _s set ["markerText", ""]; };
if (_s getOrDefault ["markerEditAfter", false]) then { [_m] call comspec_atak_native_fnc_markerEditOpen; };
_m
