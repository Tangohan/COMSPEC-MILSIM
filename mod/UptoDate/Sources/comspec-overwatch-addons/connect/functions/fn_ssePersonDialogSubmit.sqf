/*
    Soumet la fiche SSE (identité + armes) puis photo / biométrie optionnelles.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith {};

private _last = trim (ctrlText (_disp displayCtrl 9501));
private _first = trim (ctrlText (_disp displayCtrl 9502));
private _alias = trim (ctrlText (_disp displayCtrl 9503));
private _ageStr = trim (ctrlText (_disp displayCtrl 9504));
private _nat = trim (ctrlText (_disp displayCtrl 9507));
private _lang = trim (ctrlText (_disp displayCtrl 9508));
private _marks = trim (ctrlText (_disp displayCtrl 9509));
private _affil = trim (ctrlText (_disp displayCtrl 9510));
private _stmt = trim (ctrlText (_disp displayCtrl 9512));

if (_last isEqualTo "" && {_first isEqualTo ""} && {_alias isEqualTo ""}) exitWith {
    ["Indiquez au moins un nom, un prénom ou un alias.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    (_disp displayCtrl 9513) ctrlSetStructuredText parseText "<t size='0.55' color='#ff8a4a' align='center'>Nom, prénom ou alias requis.</t>";
};

private _statusCtrl = _disp displayCtrl 9505;
private _statusIdx = lbCurSel _statusCtrl;
private _status = if (_statusIdx < 0) then { "civil" } else { _statusCtrl lbData _statusIdx };
if (_status isEqualTo "") then { _status = "civil"; };

private _circCtrl = _disp displayCtrl 9506;
private _circIdx = lbCurSel _circCtrl;
private _circ = if (_circIdx < 0) then { "controle" } else { _circCtrl lbData _circIdx };
if (_circ isEqualTo "") then { _circ = "controle"; };

private _age = -1;
if (_ageStr isNotEqualTo "") then {
    _age = floor (parseNumber _ageStr);
};

private _pos = getPosASL player;
private _grid = mapGridPosition player;
private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callsign isEqualTo "") then { _callsign = groupId (group player); };
private _steam = getPlayerUID player;

private _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
private _netId = if (!isNull _target) then { netId _target } else { "" };

private _weapons = uiNamespace getVariable ["COMSPEC_SsePerson_WeaponsCache", []];
private _equipment = uiNamespace getVariable ["COMSPEC_SsePerson_EquipmentCache", []];
if (!(_weapons isEqualType [])) then { _weapons = []; };
if (!(_equipment isEqualType [])) then { _equipment = []; };

// Échappement JSON.
// L'ancienne version passait par « str » puis découpait « select [1, count - 2] » pour
// retirer les guillemets ajoutés. Sur une chaîne accentuée — « Décédée » apparaît
// systématiquement dans le constat d'une personne décédée — ce découpage tronquait la
// valeur et produisait un corps JSON invalide : le serveur décodait null, ne voyait plus
// aucune identité et répondait 422. Les caractères de contrôle n'étaient pas non plus
// échappés, alors qu'un retour à la ligne dans « Déclarations » suffit à casser le JSON.
private _escape = {
    params ["_s"];
    if (isNil "_s") exitWith { "" };
    if (!(_s isEqualType "")) then { _s = str _s; };
    if (_s isEqualTo "") exitWith { "" };

    private _o = "";
    private _dq = toString [34];
    private _bs = toString [92];
    {
        switch (true) do {
            case (_x == 34): { _o = _o + _bs + _dq; };
            case (_x == 92): { _o = _o + _bs + _bs; };
            case (_x == 10): { _o = _o + _bs + "n"; };
            case (_x == 13): { _o = _o + _bs + "r"; };
            case (_x == 9):  { _o = _o + _bs + "t"; };
            // Autres caractères de contrôle : remplacés par une espace — invalides en
            // JSON, et sans valeur métier dans un champ de saisie.
            case (_x < 32):  { _o = _o + " "; };
            default { _o = _o + toString [_x]; };
        };
    } forEach toArray _s;
    _o
};

// Point décimal invariant (évite virgule locale FR → JSON invalide).
private _fnc_num = { (_this select 0) toFixed (_this select 1) };

private _wArr = [];
{
    _wArr pushBack format ["{""name"":""%1"",""type"":""weapon""}", [_x] call _escape];
} forEach _weapons;
private _eArr = [];
{
    _eArr pushBack format ["{""name"":""%1"",""type"":""item""}", [_x] call _escape];
} forEach (_equipment select [0, (count _equipment) min 12]);

// --- Classement, procès-verbal, constat, échantillons ---
private _caseCode = toUpper (trim (ctrlText (_disp displayCtrl 9518)));
if (_caseCode isNotEqualTo "") then {
    profileNamespace setVariable ["COMSPEC_SseLastCaseCode", _caseCode];
    saveProfileNamespace;
};

private _sig = uiNamespace getVariable ["COMSPEC_SsePerson_Signature", []];
if (!(_sig isEqualType [])) then { _sig = []; };
private _sigJson = "null";
if ((count _sig) >= 4) then {
    _sigJson = format [
        '{"callsign":"%1","terminal_uid":"%2","atak_id":"%3","signed_at":"%4"}',
        [_sig select 0] call _escape,
        [_sig select 1] call _escape,
        [_sig select 2] call _escape,
        [_sig select 3] call _escape
    ];
};

private _med = uiNamespace getVariable ["COMSPEC_SsePerson_Medical", createHashMap];
private _medJson = "null";
if ((_med isEqualType createHashMap) && {(count _med) > 0}) then {
    private _les = _med getOrDefault ["lesions", []];
    if (!(_les isEqualType [])) then { _les = []; };
    private _lesArr = [];
    { _lesArr pushBack format ['"%1"', [_x] call _escape]; } forEach _les;
    _medJson = format [
        '{"etat":"%1","etat_label":"%2","sang":%3,"pouls":%4,"douleur":%5,"arret_cardiaque":%6,"lesions":[%7],"resume":"%8"}',
        [_med getOrDefault ["etat", "inconnu"]] call _escape,
        [_med getOrDefault ["etat_label", "Inconnu"]] call _escape,
        _med getOrDefault ["sang", -1],
        _med getOrDefault ["pouls", -1],
        if (_med getOrDefault ["douleur", false]) then { "true" } else { "false" },
        if (_med getOrDefault ["arret_cardiaque", false]) then { "true" } else { "false" },
        _lesArr joinString ",",
        [_med getOrDefault ["resume", ""]] call _escape
    ];
};

private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
if (!(_samples isEqualType [])) then { _samples = []; };
private _sampleArr = [];
{
    if ((_x isEqualType []) && {(count _x) >= 3}) then {
        _x params ["_k", "_q", "_r"];
        _sampleArr pushBack format [
            '{"kind":"%1","quality":%2,"lab_reference":"%3"}',
            [_k] call _escape, _q, [_r] call _escape
        ];
    };
} forEach _samples;

// Verdict de la requête d'identité, s'il y en a eu une.
private _q = uiNamespace getVariable ["COMSPEC_SsePerson_Query", []];
if (!(_q isEqualType [])) then { _q = []; };
private _queryJson = "null";
if ((count _q) >= 3) then {
    _queryJson = format [
        '{"result":"%1","confidence":%2,"record_ref":"%3"}',
        [_q select 0] call _escape,
        (_q select 1) toFixed 1,
        [_q select 2] call _escape
    ];
};

private _bio = uiNamespace getVariable ["COMSPEC_SsePerson_BioPending", false];
if ((count _samples) > 0) then { _bio = true; };
private _photoPending = uiNamespace getVariable ["COMSPEC_SsePerson_PhotoPending", false];
private _ageJson = if (_age >= 0) then { str _age } else { "null" };
private _posX = [_pos select 0, 2] call _fnc_num;
private _posY = [_pos select 1, 2] call _fnc_num;
private _posZ = [_pos select 2, 2] call _fnc_num;

(_disp displayCtrl 9513) ctrlSetStructuredText parseText "<t size='0.55' color='#8aa0b4' align='center'>Transmission en cours…</t>";

private _json = format [
    '{"mapId":1,"status":"%1","last_name":"%2","first_name":"%3","alias":"%4","age_estimated":%5,"nationality":"%6","language_spoken":"%7","distinguishing_marks":"%8","affiliation":"%9","circumstances":"%10","statements":"%11","confidence_level":"moyenne","weapons":[%12],"equipment":[%13],"biometrics_simulated":%14,"consent_recorded":true,"pos_x":%15,"pos_y":%16,"pos_z":%17,"grid_reference":"%18","submitter_callsign":"%19","submitter_steam_id":"%20","target_unit_netid":"%21","case_code":"%22","signature":%23,"medical_context":%24,"biometric_samples":[%25],"identity_query":%26}',
    _status,
    [_last] call _escape,
    [_first] call _escape,
    [_alias] call _escape,
    _ageJson,
    [_nat] call _escape,
    [_lang] call _escape,
    [_marks] call _escape,
    [_affil] call _escape,
    _circ,
    [_stmt] call _escape,
    _wArr joinString ",",
    _eArr joinString ",",
    if (_bio) then { "true" } else { "false" },
    _posX,
    _posY,
    _posZ,
    [_grid] call _escape,
    [_callsign] call _escape,
    [_steam] call _escape,
    [_netId] call _escape,
    [_caseCode] call _escape,
    _sigJson,
    _medJson,
    _sampleArr joinString ",",
    _queryJson
];

private _parsed = [
    "SubmitSsePerson",
    [_json],
    "SSE fiche personne",
    true,
    true,
    "system"
] call comspec_overwatch_connect_fnc_callExtLogged;
_parsed params ["_ok", "", "_detail"];

if (!_ok) exitWith {
    // Le serveur renvoie un motif exploitable (identité manquante, dossier inconnu…).
    // L'afficher évite de faire chercher une panne de liaison qui n'existe pas.
    private _reason = "Échec de transmission — vérifiez la liaison.";
    private _d = toLower (str _detail);
    if ((_d find "identity_required") >= 0) then {
        _reason = "Refus du poste de commandement : indiquez au moins un nom, un prénom ou un alias.";
    };
    if ((_d find "maintenance") >= 0) then {
        _reason = "Renseignement suspendu par le commandement — réessayez plus tard.";
    };
    if ((_d find "unauthorized") >= 0 || {(_d find "tenant_context_required") >= 0}) then {
        _reason = "Terminal non habilité — reliez le compte Athena puis réessayez.";
    };
    if ((_d find "http 5") >= 0) then {
        _reason = "Le poste de commandement est en erreur — signalez-le à l’administrateur.";
    };
    (_disp displayCtrl 9513) ctrlSetStructuredText parseText format [
        "<t size='0.55' color='#ff8a4a' align='center'>%1</t>",
        _reason
    ];
    [([
        _detail,
        "Impossible d’enregistrer la personne — vérifiez la liaison Athena."
    ] call comspec_overwatch_connect_fnc_atakExtFailMessage), "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

// Détail OK = id personne (PostSsePersonSync) ou "Success"
private _personId = "";
if (_detail isEqualType "" && { _detail != "Success" } && { (parseNumber _detail) > 0 }) then {
    _personId = str (floor (parseNumber _detail));
};

// Voie héritée 1.4.0 : uniquement si aucun échantillon détaillé n’accompagne la fiche
// (sinon l’enregistrement serait compté deux fois).
if (_bio && { _personId isNotEqualTo "" } && { (count _samples) == 0 }) then {
    private _bioJson = format [
        '{"kind":"empreintes","submitter_callsign":"%1"}',
        [_callsign] call _escape
    ];
    private _bioParsed = [
        "SubmitSseBiometricsSim",
        [_personId, _bioJson],
        "SSE biométrie simulée",
        true,
        true,
        "system"
    ] call comspec_overwatch_connect_fnc_callExtLogged;
    _bioParsed params ["_bioOk", "", "_bioDetail"];
    if (!_bioOk) then {
        ["ssePersonDialogSubmit", format ["Biométrie non transmise — %1", _bioDetail], _bioDetail, "Fn", "WARN"] call comspec_overwatch_connect_fnc_logFnError;
    };
};

if (_photoPending && { _personId isNotEqualTo "" }) then {
    ["UploadSsePhoto", "attempt", format ["personne %1", _personId], nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
    private _shot = [
        "COMSPECExtension" callExtension ["UploadSsePhoto", [
            _personId,
            "",
            _callsign,
            "face",
            _posX,
            _posY,
            _posZ,
            "Photo du visage"
        ]]
    ] call comspec_overwatch_connect_fnc_extResult;
    if (((toUpper _shot) find "OK") != 0) then {
        ["UploadSsePhoto", "fail", _shot, _shot, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        ["Fiche enregistrée. Aucune capture récente trouvée pour la photo du visage — refaites une capture d’écran face à la personne puis réessayez.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    } else {
        ["UploadSsePhoto", "ok", format ["personne %1", _personId], nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
    };
};

["Personne enregistrée — fiche transmise au poste de commandement.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;

if (!isNull _disp) then {
    _disp closeDisplay 1;
} else {
    closeDialog 0;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
