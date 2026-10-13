/*
    Messagerie : liste des canaux et messages non lus (pastilles de la liste, de l'icône d'app et du dock).
      ["channels"]     -> [[clé, libellé, personnalisé]...] : "CH:<canal>" avec COMSPEC Link (canaux Athena puis
                          "CH:alertes"), sinon [["ATHENA", "TOC — canal Athena", false]].
      ["counts"]       -> HashMap : clé de conversation (canal, ou nom du joueur pour les SMS) -> non-lus,
                          et "_total". Recalculé au plus une fois par seconde.
      ["read", clé]    -> la conversation est lue (ouverte à l'écran).
    Non-lu d'un canal : message d'un autre que moi, pas encore vu (mémorisé par identifiant ou auteur|heure|texte,
    donc juste même quand la liste est plafonnée ou dédoublonnée). L'historique déjà là au chargement (premières
    90 s, relecture d'Athena) compte comme lu ; ce qui arrive ensuite, téléphone fermé compris, compte comme non lu.
    Non-lu d'un SMS : reçu et pas encore affiché dans sa conversation.
*/
params [["_mode", "counts"], ["_key", ""]];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _now = diag_tickTime;
private _t0 = uiNamespace getVariable ["COMSPEC_ATAK_ChatT0", -1];
if (_t0 < 0) then { _t0 = _now; uiNamespace setVariable ["COMSPEC_ATAK_ChatT0", _t0]; };
private _warm = (_now - _t0) < 90;

private _channels = {
    if !(_bridge) exitWith { [["ATHENA", "TOC — canal Athena", false]] };
    // Canaux système (non supprimables), comme dans COMSPEC Link.
    private _system = ["groupe", "commandement", "general", "jtac", "air", "squad", "global", "hq", "c2", "command", "group", "alertes"];
    private _rows = [];
    {
        _x params [["_k", ""], ["_lbl", ""], ["_kind", "custom"]];
        if (_k isEqualType "" && {_k isNotEqualTo ""} && {_k isNotEqualTo "alertes"}) then {
            _rows pushBack ["CH:" + _k, [_lbl, _k] select (_lbl isEqualTo ""), _kind isEqualTo "custom" && {!(_k in _system)}];
        };
    } forEach (missionNamespace getVariable ["COMSPEC_Comms_Channels", []]);
    if (_rows isEqualTo []) then { _rows = [["CH:general", "Général", false], ["CH:commandement", "Commandement", false], ["CH:groupe", "Groupe", false]]; };
    _rows pushBack ["CH:alertes", "Alertes TOC", false];
    _rows
};
private _msgKey = {
    params ["_m"];
    private _id = _m getOrDefault ["id", ""];
    if (_id isEqualType "" && {_id isNotEqualTo ""}) exitWith { _id };
    toLower format ["%1|%2|%3", _m getOrDefault ["author", ""], _m getOrDefault ["time", ""], _m getOrDefault ["body", ""]]
};
private _msgsOf = { params ["_k"]; [[_k select [3], ""] select (_k isEqualTo "ATHENA")] call comspec_atak_native_fnc_messagesAll };
private _readMap = uiNamespace getVariable ["COMSPEC_ATAK_ChatRead", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_ChatRead", _readMap];

switch (_mode) do {
    case "channels": { call _channels };
    case "read": {
        if (_key isEqualTo "") exitWith { false };
        if (_key isEqualTo "ATHENA" || {(_key select [0, 3]) isEqualTo "CH:"}) then {
            private _set = _readMap getOrDefault [_key, createHashMap];
            { _set set [[_x] call _msgKey, true]; } forEach ([_key] call _msgsOf);
            _readMap set [_key, _set];
        } else {
            { if ((_x getOrDefault ["peer", ""]) isEqualTo _key && {(_x getOrDefault ["dir", ""]) isEqualTo "in"}) then { _x set ["read", true]; }; }
                forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["p2p", []]);
        };
        uiNamespace setVariable ["COMSPEC_ATAK_ChatUnreadCache", nil];
        true
    };
    default {
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_ChatUnreadCache", []];
        if ((count _cache) isEqualTo 2 && {_now - (_cache select 0) < 1}) exitWith { _cache select 1 };
        private _out = createHashMap;
        private _total = 0;
        {
            private _k = _x select 0;
            private _msgs = [_k] call _msgsOf;
            if !(_k in _readMap) then {
                // Premier passage : l'historique chargé au départ est lu ; un canal vide l'est aussi après l'attente.
                if (_msgs isNotEqualTo [] || {!_warm}) then {
                    private _set = createHashMap;
                    { _set set [[_x] call _msgKey, true]; } forEach _msgs;
                    _readMap set [_k, _set];
                };
            };
            private _set = _readMap getOrDefault [_k, createHashMap];
            private _n = { !(_x getOrDefault ["mine", false]) && {!(([_x] call _msgKey) in _set)} } count _msgs;
            if (_warm && {!(_k in _readMap)}) then { _n = 0; };
            _out set [_k, _n];
            _total = _total + _n;
        } forEach (call _channels);
        {
            if ((_x getOrDefault ["dir", ""]) isEqualTo "in" && {!(_x getOrDefault ["read", false])}) then {
                private _p = _x getOrDefault ["peer", ""];
                _out set [_p, (_out getOrDefault [_p, 0]) + 1];
                _total = _total + 1;
            };
        } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["p2p", []]);
        _out set ["_total", _total];
        uiNamespace setVariable ["COMSPEC_ATAK_ChatUnreadCache", [_now, _out]];
        _out
    };
};
