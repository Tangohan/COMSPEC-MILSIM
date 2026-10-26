/*
    Suite d'un envoi PERSON réussi (direct ou depuis la file offline) : entité TRANSMITTED, id Athena lu dans
    la réponse extension de CET envoi (txRaw de l'envelope, détail OK), biométries en file recâblées sur cet id.
    [_entity, _uid, _raw] call comspec_sse_fnc_personTransmitted
      _entity : sujet (objNull accepté : file restaurée après redémarrage, entité introuvable)
      _uid    : sse_uid de la fiche envoyée
      _raw    : réponse extension de l'envoi (envelope get "txRaw") ; jamais le global comspec_sse_lastExtRaw,
                écrasé par les envois parallèles (file 45 s, autre transmission)
    Renvoie l'id Athena ("" s'il est inconnu).
*/
params [
    ["_entity", objNull, [objNull]],
    ["_uid", "?", [""]],
    ["_raw", "", [""]]
];

if (!isNull _entity) then {
    [_entity, "TRANSMITTED"] call comspec_sse_fnc_markTransmitted;
};

// Mémoriser l’id Athena (détail OK) pour biométrie / photo.
private _aid = "";
// Biométries au sse_uid inconnu ("?", "", "any") : rattachées par netId du sujet, jamais à la fiche qui vient de partir.
private _nid = if (isNull _entity) then { "" } else { netId _entity };
private _unknownUids = ["?", "", "any"];
if (_raw isNotEqualTo "") then {
    private _parsed = parseSimpleArray _raw;
    if (_parsed isEqualType [] && {(count _parsed) >= 2}) then {
        private _detail = _parsed select 1;
        if (_detail isEqualType "" && {_detail isNotEqualTo ""} && {_detail isNotEqualTo "Success"}) then {
            private _n = parseNumber _detail;
            if (_n > 0) then {
                _aid = str (floor _n);
                if (!isNull _entity) then {
                    _entity setVariable ["comspec_sse_athenaPersonId", _aid, true];
                };
                // File offline : les biométries en attente doivent reprendre avec cet id.
                private _q = missionNamespace getVariable ["comspec_sse_txQueue", []];
                {
                    if (_x isEqualType createHashMap) then {
                        if ((toUpper (_x getOrDefault ["kind", ""])) isEqualTo "BIOMETRICS") then {
                            private _p = _x getOrDefault ["payload", createHashMap];
                            if (_p isEqualType createHashMap) then {
                                private _u = _p getOrDefault ["sse_uid", ""];
                                private _match = if (_u in _unknownUids) then {
                                    _nid isNotEqualTo "" && {(_x getOrDefault ["entity_netid", ""]) isEqualTo _nid}
                                } else {
                                    !(_uid in _unknownUids) && {_u isEqualTo _uid}
                                };
                                if (_match) then {
                                    _p set ["athena_person_id", _aid];
                                    _p set ["person_id", _aid];
                                    _x set ["payload", _p];
                                    _x set ["txRetryAfter", 0];
                                    // Tentatives repartent de zéro : les échecs « fiche pas encore connue » ne comptent pas.
                                    _x set ["txAttempts", 0];
                                    _x set ["txStatus", "QUEUED"];
                                };
                            };
                        };
                    };
                } forEach _q;
                missionNamespace setVariable ["comspec_sse_txQueue", _q];
            };
        };
    };
};
_aid
