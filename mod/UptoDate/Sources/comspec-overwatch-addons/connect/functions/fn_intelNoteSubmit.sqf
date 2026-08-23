/*
    Transmet la fiche de renseignement au bureau SSE, puis ses pièces jointes.

    Ordre imposé par le serveur : la fiche d'abord (elle rend son identifiant),
    les pièces ensuite. Les captures d'écran sont prises après la fermeture du
    rédacteur, sinon elles ne montreraient que l'interface.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };
if (isNull _disp) exitWith {};

private _catalog = [] call comspec_overwatch_connect_fnc_intelNoteCatalog;
private _bodyMax = _catalog getOrDefault ["body_max", 1000];

private _fnc_say = {
    params ["_html"];
    private _d = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
    if (isNull _d) exitWith {};
    private _c = _d displayCtrl 9618;
    if (!isNull _c) then { _c ctrlSetStructuredText parseText _html; };
};

// --- Contrôles de saisie ---
// Toutes les valeurs passent par la mémoire des champs : la validation se fait
// depuis n'importe quel volet, et un champ masqué peut se lire vide.
private _body = trim (["value", "body"] call comspec_overwatch_connect_fnc_intelNoteCache);
if (count _body > _bodyMax) then { _body = _body select [0, _bodyMax]; };

private _themes = uiNamespace getVariable ["COMSPEC_IntelNote_Themes", []];
if (!(_themes isEqualType [])) then { _themes = []; };

if (count _body < 10) exitWith {
    ["<t size='0.42' color='#ff8a4a'>Écrivez le renseignement dans le cadre avant de valider.</t>"] call _fnc_say;
    ['redaction'] call comspec_overwatch_connect_fnc_intelNotePane;
    ["Fiche vide ou trop courte — précisez ce que vous avez constaté.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (_themes isEqualTo []) exitWith {
    ["<t size='0.42' color='#ff8a4a'>Choisissez au moins un thème dans le volet contexte.</t>"] call _fnc_say;
    ['contexte'] call comspec_overwatch_connect_fnc_intelNotePane;
    ["Aucun thème retenu — la fiche ne saurait pas vers quel analyste partir.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

// --- Contexte ---
private _kindCombo = _disp displayCtrl 9656;
private _kindIdx = lbCurSel _kindCombo;
private _kind = if (_kindIdx >= 0) then { _kindCombo lbData _kindIdx } else { "FRM" };
if (_kind isEqualTo "") then { _kind = "FRM"; };

private _urgencyCombo = _disp displayCtrl 9657;
private _urgencyIdx = lbCurSel _urgencyCombo;
private _urgency = if (_urgencyIdx >= 0) then { _urgencyCombo lbData _urgencyIdx } else { "routine" };
if (_urgency isEqualTo "") then { _urgency = "routine"; };

private _place = trim (["value", "place"] call comspec_overwatch_connect_fnc_intelNoteCache);
private _grid = trim (["value", "grid"] call comspec_overwatch_connect_fnc_intelNoteCache);
if (_grid isEqualTo "") then { _grid = mapGridPosition player; };
private _caseCode = toUpper (trim (["value", "case"] call comspec_overwatch_connect_fnc_intelNoteCache));
if (_caseCode isEqualTo "") then {
    _caseCode = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase;
};

// Date saisie « JJ/MM/AAAA HH:MM » → format attendu par le serveur.
private _observedRaw = trim (["value", "date"] call comspec_overwatch_connect_fnc_intelNoteCache);
private _observed = "";
private _dateParts = (_observedRaw splitString " ") select {_x isNotEqualTo ""};
if ((count _dateParts) >= 1) then {
    private _dmy = ((_dateParts select 0) splitString "/-.") select {_x isNotEqualTo ""};
    if ((count _dmy) >= 3) then {
        private _time = if ((count _dateParts) >= 2) then { _dateParts select 1 } else { "00:00" };
        private _hm = (_time splitString ":h") select {_x isNotEqualTo ""};
        private _hh = if ((count _hm) >= 1) then { _hm select 0 } else { "00" };
        private _mm = if ((count _hm) >= 2) then { _hm select 1 } else { "00" };
        private _pad = {
            params ["_s"];
            if (count _s < 2) then { "0" + _s } else { _s select [0, 2] }
        };
        _observed = format [
            "%1-%2-%3 %4:%5:00",
            _dmy select 2,
            [_dmy select 1] call _pad,
            [_dmy select 0] call _pad,
            [_hh] call _pad,
            [_mm] call _pad
        ];
    };
};

private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callsign isEqualTo "") then { _callsign = groupId (group player); };
private _steam = getPlayerUID player;
private _unit = groupId (group player);
private _pos = getPosASL player;

// Échappement JSON : SQF n'en a pas, et un accent ou un retour à la ligne dans
// une fiche rédigée à la main suffit à casser le corps de la requête.
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
            case (_x < 32):  { _o = _o + " "; };
            default { _o = _o + toString [_x]; };
        };
    } forEach toArray _s;
    _o
};

private _themeArr = [];
{ _themeArr pushBack format ['"%1"', [_x] call _escape]; } forEach _themes;

// Clé d'idempotence : une double validation (double clic, retransmission après
// coupure) ne doit pas créer deux fiches identiques côté bureau.
private _idempotency = uiNamespace getVariable ["COMSPEC_IntelNote_Idempotency", ""];
if (!(_idempotency isEqualType "") || {_idempotency isEqualTo ""}) then {
    _idempotency = format ["fiche-%1-%2", _steam, floor (diag_tickTime * 1000)];
    uiNamespace setVariable ["COMSPEC_IntelNote_Idempotency", _idempotency];
};

private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;

private _parts = [
    '"mapId":1',
    format ['"body":"%1"', [_body] call _escape],
    format ['"note_kind":"%1"', [_kind] call _escape],
    format ['"themes":[%1]', _themeArr joinString ","],
    format ['"urgency":"%1"', [_urgency] call _escape],
    format ['"place_label":"%1"', [_place] call _escape],
    format ['"grid_reference":"%1"', [_grid] call _escape],
    format ['"pos_x":%1', (_pos select 0) toFixed 2],
    format ['"pos_y":%1', (_pos select 1) toFixed 2],
    format ['"pos_z":%1', (_pos select 2) toFixed 2],
    format ['"author_label":"%1"', [_callsign] call _escape],
    format ['"author_steam_id":"%1"', [_steam] call _escape],
    format ['"author_unit":"%1"', [_unit] call _escape],
    format ['"submitter_callsign":"%1"', [_callsign] call _escape],
    format ['"case_code":"%1"', [_caseCode] call _escape],
    format ['"idempotency_key":"%1"', [_idempotency] call _escape],
    '"origin":"atak"',
    '"source_reliability":"C"',
    '"info_credibility":3',
    '"mod_name":"COMSPEC Overwatch"',
    format ['"mod_version":"%1"', [_modVersion] call _escape],
    '"mod_cfg":"comspec_overwatch_connect"'
];
if (_observed isNotEqualTo "") then {
    _parts pushBack (format ['"observed_at":"%1"', [_observed] call _escape]);
};

private _json = "{" + (_parts joinString ",") + "}";

["<t size='0.42' color='#8aa0b4'>Transmission en cours…</t>"] call _fnc_say;

private _parsed = [
    "SubmitSseFieldNote",
    [_json],
    "Fiche de renseignement",
    true,
    true,
    "system",
    true
] call comspec_overwatch_connect_fnc_callExtLogged;
_parsed params ["_ok", "_status", "_detail"];

// Liaison coupée : la fiche est en file, pas perdue. Le dire comme un échec
// pousserait l'opérateur à ressaisir, et le bureau récupérerait deux fiches du
// même constat au rétablissement.
if (!_ok && {_status isEqualTo "QUEUED"}) exitWith {
    ["<t size='0.42' color='#e0a233'>Liaison coupée — fiche conservée, elle partira au rétablissement. Ne la ressaisissez pas.</t>"] call _fnc_say;
    [true] call comspec_overwatch_connect_fnc_intelNoteSaveDraft;
    uiNamespace setVariable ["COMSPEC_IntelNote_Pieces", []];
    uiNamespace setVariable ["COMSPEC_IntelNote_Idempotency", ""];
    [
        "Fiche conservée hors ligne — transmission au rétablissement de la liaison.",
        "tactical",
        "warn"
    ] call comspec_overwatch_connect_fnc_announce;
};

if (!_ok) exitWith {
    private _reason = "Échec de transmission — vérifiez la liaison.";
    private _d = toLower (str _detail);
    if ((_d find "body_required") >= 0) then {
        _reason = "La fiche est vide : écrivez le renseignement puis revalidez.";
    };
    if ((_d find "theme_required") >= 0) then {
        _reason = "Choisissez au moins un thème dans le volet contexte.";
    };
    if ((_d find "maintenance") >= 0) then {
        _reason = "Renseignement suspendu par le commandement — réessayez plus tard.";
    };
    if ((_d find "unauthorized") >= 0 || {(_d find "tenant_context_required") >= 0}) then {
        _reason = "ATAK non habilité — reliez le compte Athena puis réessayez.";
    };
    if ((_d find "http 5") >= 0) then {
        _reason = "Le poste de commandement est en erreur — signalez-le à l’administrateur.";
    };
    [format ["<t size='0.42' color='#ff8a4a'>%1</t>", _reason]] call _fnc_say;
    [([
        _detail,
        "Impossible de transmettre la fiche — vérifiez la liaison Athena."
    ] call comspec_overwatch_connect_fnc_atakExtFailMessage), "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

// Détail transmis par l'extension : « id » ou « id|référence ».
private _noteId = "";
private _reference = "";
if ((_detail isEqualType "") && {_detail isNotEqualTo "Success"}) then {
    private _bits = _detail splitString "|";
    if ((count _bits) >= 1) then {
        private _first = trim (_bits select 0);
        if ((parseNumber _first) > 0) then { _noteId = str (floor (parseNumber _first)); };
    };
    if ((count _bits) >= 2) then { _reference = trim (_bits select 1); };
};

if (_caseCode isNotEqualTo "") then {
    ["set", _caseCode] call comspec_overwatch_connect_fnc_sseActiveCase;
};

private _pieces = uiNamespace getVariable ["COMSPEC_IntelNote_Pieces", []];
if (!(_pieces isEqualType [])) then { _pieces = []; };

[true] call comspec_overwatch_connect_fnc_intelNoteSaveDraft;
uiNamespace setVariable ["COMSPEC_IntelNote_Pieces", []];
uiNamespace setVariable ["COMSPEC_IntelNote_Themes", []];
uiNamespace setVariable ["COMSPEC_IntelNote_Idempotency", ""];

[
    if (_reference isEqualTo "") then {
        "Fiche de renseignement transmise au bureau SSE."
    } else {
        format ["Fiche %1 transmise au bureau SSE.", _reference]
    },
    "tactical",
    "info"
] call comspec_overwatch_connect_fnc_announce;

// Referme avant les captures : c'est la scène qui doit être photographiée.
if (!isNull _disp) then {
    _disp closeDisplay 1;
} else {
    closeDialog 0;
};

if (_noteId isNotEqualTo "" && {_pieces isNotEqualTo []}) then {
    [_noteId, _pieces, _callsign, _grid, _pos] spawn {
        params ["_noteId", "_pieces", "_callsign", "_grid", "_pos"];
        private _sent = 0;
        {
            _x params [["_kind", "capture"], ["_path", ""], ["_name", ""], ["_pgrid", ""], ["_pauthor", ""], ["_caption", ""]];
            if (_pgrid isEqualTo "") then { _pgrid = _grid; };
            if (_pauthor isEqualTo "") then { _pauthor = _callsign; };

            // Sans fichier de départ (capture demandée, relevé sans image), la
            // scène courante fait la pièce jointe : le rédacteur est refermé,
            // c'est donc bien le terrain qui est photographié.
            private _target = _path;
            if (_target isEqualTo "") then {
                _target = format ["COMSPEC_Fiche_%1_%2.png", _noteId, floor (diag_tickTime * 1000)];
                screenshot _target;
                uiSleep 1.25;
            };

            private _res = [
                "UploadSseNoteAttachment",
                [
                    _noteId,
                    _target,
                    _pauthor,
                    _kind,
                    (_pos select 0) toFixed 2,
                    (_pos select 1) toFixed 2,
                    (_pos select 2) toFixed 2,
                    _caption,
                    _pgrid
                ],
                "Pièce jointe de fiche",
                true,
                true,
                "system",
                false
            ] call comspec_overwatch_connect_fnc_callExtLogged;
            _res params ["_pieceOk", "", "_pieceDetail"];
            if (_pieceOk) then {
                _sent = _sent + 1;
            } else {
                ["intelNoteSubmit", format ["Pièce jointe non transmise — %1", _pieceDetail], _pieceDetail, "Fn", "WARN"] call comspec_overwatch_connect_fnc_logFnError;
            };
            uiSleep 0.75;
        } forEach _pieces;

        if (_sent < (count _pieces)) then {
            [
                "Fiche enregistrée, mais toutes les pièces jointes ne sont pas parties. Rejoignez-les depuis le portail si besoin.",
                "tactical",
                "warn"
            ] call comspec_overwatch_connect_fnc_announce;
        } else {
            [
                format ["%1 pièce(s) jointe(s) transmise(s) avec la fiche.", _sent],
                "tactical",
                "info"
            ] call comspec_overwatch_connect_fnc_announce;
        };
    };
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
