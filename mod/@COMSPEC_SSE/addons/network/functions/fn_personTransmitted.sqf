/*
    Suite d'un envoi PERSON réussi (direct ou depuis la file offline) : entité TRANSMITTED, id Athena lu dans
    comspec_sse_lastExtRaw (détail OK), biométries en file recâblées sur cet id.
    [_entity, _uid] call comspec_sse_fnc_personTransmitted
      _entity : sujet (objNull accepté : file restaurée après redémarrage, entité introuvable)
      _uid    : sse_uid de la fiche envoyée
    Renvoie l'id Athena ("" s'il est inconnu).
*/
params [
    ["_entity", objNull, [objNull]],
    ["_uid", "?", [""]]
];

if (!isNull _entity) then {
    [_entity, "TRANSMITTED"] call comspec_sse_fnc_markTransmitted;
};

// Mémoriser l’id Athena (détail OK) pour biométrie / photo.
private _aid = "";
private _raw = missionNamespace getVariable ["comspec_sse_lastExtRaw", ""];
if (_raw isEqualType "" && {_raw isNotEqualTo ""}) then {
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
                                if (_u isEqualTo _uid || {_u isEqualTo "?"} || {_u isEqualTo "any"} || {_u isEqualTo ""}) then {
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
