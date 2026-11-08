/*
    Transmission d'un contrôle à Athena (POST /api/sse/identity/checks via la DLL COMSPEC Link : SubmitIdCheck).
    [_rec] call comspec_sse_fnc_idAthenaSubmit       (fiche de fn_idHistory)
    Clé d'idempotence par fiche : un renvoi (file hors ligne, double clic) ne crée pas de doublon côté site.
    Sans la fonction dans la DLL (Link trop ancien) ou sans liaison : file hors ligne du SSE (fn_queueOffline),
    renvoyée par fn_flushQueue quand la liaison revient. Réponse « OK|id|réf » : référence CTRL-… gardée sur la fiche.
    JSON d'un seul bloc (constats en codes, sans phrases) : au-delà de 4 000 caractères, envoyé en morceaux de 4 000
    (arguments successifs de SubmitIdCheck, recollés par la DLL).
*/
params [["_rec", createHashMap, [createHashMap]]];
if ((count _rec) isEqualTo 0) exitWith { false };
if (_rec getOrDefault ["sending", false]) exitWith { false };
private _d = _rec get "doc";
private _lv = _rec get "levels";
private _results = createHashMap;
{
    private _r = _lv getOrDefault [_x, createHashMap];
    if ((count _r) > 0) then {
        _results set [format ["N%1", _x], createHashMapFromArray [["state", _r getOrDefault ["state", ""]], ["verdict", _r getOrDefault ["verdict", ""]], ["codes", _r getOrDefault ["codes", []]]]];
    };
} forEach [0, 1, 2, 3, 4];
private _athenaId = ((_lv getOrDefault [3, createHashMap]) getOrDefault ["athenaId", ""]);
// Fiche Athena de la personne contrôlée : celle de l'unité, sinon celle trouvée par le serveur (fn_idServer « check »).
private _subjUnit = _rec getOrDefault ["subj", objNull];
private _personId = if (_subjUnit isEqualType objNull && {!isNull _subjUnit}) then { _subjUnit getVariable ["comspec_sse_athenaPersonId", ""] } else { "" };
if (_personId isEqualTo "") then {
    { _personId = (_lv getOrDefault [_x, createHashMap]) getOrDefault ["personId", ""]; if (_personId isNotEqualTo "") then { break; }; } forEach [3, 4, 2, 1, 0];
};
private _payload = createHashMapFromArray [
    ["idempotency_key", _rec get "idem"],
    ["map", str (missionNamespace getVariable ["COMSPEC_MapId", 1])],
    ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]],
    ["document", createHashMapFromArray [["id", _athenaId], ["local_id", _d getOrDefault ["id", ""]], ["type", _d getOrDefault ["type", ""]], ["country", _d getOrDefault ["country", ""]], ["number", _d getOrDefault ["number", ""]]]],
    ["presented_name", createHashMapFromArray [["last", _d getOrDefault ["last", ""]], ["first", _d getOrDefault ["first", ""]], ["dob", _d getOrDefault ["dob", ""]]]],
    ["subject", createHashMapFromArray [["person_id", _personId], ["netid", _rec getOrDefault ["subjNetId", ""]], ["bio_key", _rec getOrDefault ["subjKey", ""]], ["steam_id", _rec getOrDefault ["subjUid", ""]]]],
    ["controller", createHashMapFromArray [["callsign", name player], ["steam_id", getPlayerUID player]]],
    ["levels", (keys _lv) apply { _x }],
    ["results", _results],
    // Verdicts du contrat : ras | signale | recherche | hvt | inconnu | revoque | vole | expire. « faux » part en
    // « inconnu » (un faux est inconnu des fichiers), « doute » sans verdict ; le détail reste dans results.
    ["verdict", switch (_rec getOrDefault ["verdict", "ras"]) do { case "faux": { "inconnu" }; case "doute": { "" }; default { _rec getOrDefault ["verdict", "ras"] }; }],
    ["grid", _rec getOrDefault ["grid", ""]],
    ["pos", (_rec getOrDefault ["pos", [0, 0, 0]]) apply { round _x }],
    ["game_time", format ["%1-%2-%3 %4", (_rec get "date") select 0, (_rec get "date") select 1, (_rec get "date") select 2, _rec get "clock"]],
    ["case", _rec getOrDefault ["case", ""]]
];
// N4 (visage) : taux de confiance et dossier biométrique reconnu, gardés avec le résultat du niveau.
private _n4 = _lv getOrDefault [4, createHashMap];
if ((count _n4) > 0) then { (_results get "N4") set ["score", _n4 getOrDefault ["score", 0]]; (_results get "N4") set ["ref", _n4 getOrDefault ["ref", ""]]; };
(_payload get "levels") sort true;
_rec set ["sending", true];
[_rec, _payload] spawn {
    params ["_rec", "_payload"];
    private _json = [_payload] call comspec_sse_fnc_toJsonApprox;
    private _ok = false;
    if (["has"] call comspec_sse_fnc_idAthenaPack) then {
        private _parts = [];
        for "_i" from 0 to ((count _json) - 1) step 4000 do { _parts pushBack (_json select [_i, 4000]); };
        private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
        if ((_raw select [0, 3]) isEqualTo "OK|") then {
            private _p = _raw splitString "|";
            _rec set ["ref", _p param [2, _p param [1, ""]]];
            _ok = true;
        };
    };
    _rec set ["sending", false];
    if (_ok) exitWith {
        _rec set ["sent", true];
        [format ["Contrôle transmis à Athena (%1).", _rec get "ref"], "SUCCESS", "", "IDCHECK"] call comspec_sse_fnc_notify;
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCHECK") then { ["IDCHECK"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
    };
    // Sans liaison ou sans la fonction : file hors ligne du SSE (renvoyée par fn_flushQueue).
    [createHashMapFromArray [
        ["kind", "IDCHECK"], ["command", "SubmitIdCheck"], ["payload", _payload],
        ["idempotency_key", _payload get "idempotency_key"], ["record_id", _payload get "idempotency_key"], ["txStatus", "PENDING"]
    ]] call comspec_sse_fnc_queueOffline;
    _rec set ["sent", true];
    _rec set ["ref", "file d'attente"];
    ["Pas de liaison Athena : contrôle mis en file d'attente, envoyé au retour de la liaison.", "INFO", "", "IDCHECK"] call comspec_sse_fnc_notify;
};
true
