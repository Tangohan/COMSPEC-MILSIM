/*
    Rafraîchit les panneaux dynamiques du terminal SEEK :
    bandeau LCD, échantillons biométriques, bloc signature.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith {};

// --- Échantillons biométriques (9522) ---
private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
if (!(_samples isEqualType [])) then { _samples = []; };

private _lines = [];
{
    if ((_x isEqualType []) && {(count _x) >= 3}) then {
        _x params ["_kind", "_quality", "_ref"];
        private _lbl = switch (_kind) do {
            case "iris": { "IRIS" };
            case "adn": { "ADN" };
            default { "EMPREINTES" };
        };
        // Points caractéristiques : ordre de grandeur crédible par modalité.
        private _pts = switch (_kind) do {
            case "iris": { 96 + floor (random 64) };
            case "adn": { 16 };
            default { 24 + floor (random 40) };
        };
        private _col = if (_quality >= 80) then { "#7ee0a0" } else {
            if (_quality >= 60) then { "#e0d27e" } else { "#e09a7e" }
        };
        // Jauge en blocs — même lecture que la barre de qualité de référence.
        private _filled = round (_quality / 10);
        private _gauge = "";
        for "_i" from 1 to 10 do {
            _gauge = _gauge + (if (_i <= _filled) then { "▮" } else { "▯" });
        };

        // Une seule ligne par prélèvement : le panneau n'en affiche que quatre, et
        // trois modalités sur deux lignes débordaient — l'ADN était tronqué à l'écran.
        _lines pushBack format [
            "<t size='0.44' color='%1'>■ %2</t> <t size='0.42' color='%1'>%3</t>"
            + " <t size='0.42' color='#c8e8ff'>%4%5</t>"
            + " <t size='0.4' color='#7f95a8'>· %6 pts · %7</t>",
            _col, _lbl,
            _gauge,
            _quality, "%",
            _pts, _ref
        ];
    };
} forEach _samples;

// Verdict d'identité — remplace la note générique dès qu'une requête a abouti.
private _queryPending = uiNamespace getVariable ["COMSPEC_SsePerson_QueryPending", false];
private _query = uiNamespace getVariable ["COMSPEC_SsePerson_Query", []];
private _verdict = "<t size='0.42' color='#5f7383'>Analyse locale simulée — lancez une requête pour interroger la base.</t>";
if (_queryPending) then {
    _verdict = "<t size='0.44' color='#c8e8ff'>INTERROGATION DE LA BASE D’IDENTITÉS…</t>";
} else {
    if ((_query isEqualType []) && {(count _query) >= 3}) then {
        _query params ["_res", "_conf", "_ref"];
        _verdict = switch (_res) do {
            case "confirmed": {
                format [
                    "<t size='0.46' color='#ff6b5e'>CORRESPONDANCE CONFIRMÉE</t> <t size='0.44' color='#c8e8ff'>%1%2</t> <t size='0.42' color='#7f95a8'>· dossier %3</t>",
                    _conf toFixed 1, "%", _ref
                ]
            };
            case "possible": {
                format [
                    "<t size='0.46' color='#e0a233'>CORRESPONDANCE POSSIBLE</t> <t size='0.44' color='#c8e8ff'>%1%2</t> <t size='0.42' color='#7f95a8'>· dossier %3</t>",
                    _conf toFixed 1, "%", _ref
                ]
            };
            default {
                "<t size='0.46' color='#7ee0a0'>AUCUNE CORRESPONDANCE</t> <t size='0.42' color='#7f95a8'>· sujet inconnu de la base</t>"
            };
        };
    };
};

private _bioTxt = if ((count _lines) > 0) then {
    (_lines joinString "<br/>") + "<br/>" + _verdict
} else {
    "<t size='0.48' color='#5f7383'>Aucun prélèvement. Présentez la personne au lecteur.</t>"
};
(_disp displayCtrl 9522) ctrlSetStructuredText parseText _bioTxt;

// --- Signature (9520) ---
private _sig = uiNamespace getVariable ["COMSPEC_SsePerson_Signature", []];
private _sigTxt = if ((_sig isEqualType []) && {(count _sig) >= 4}) then {
    format [
        "<t size='0.5' color='#7ee0a0'>SIGNÉ</t> <t size='0.5' color='#c8e8ff'>%1</t><br/><t size='0.46' color='#7f95a8'>Terminal %2 · %3</t>",
        _sig select 0,
        _sig select 1,
        _sig select 3
    ]
} else {
    "<t size='0.5' color='#e0b07e'>NON SIGNÉ</t> <t size='0.5' color='#5f7383'>— la fiche partira sans procès-verbal.</t>"
};
(_disp displayCtrl 9520) ctrlSetStructuredText parseText _sigTxt;

// --- Bandeau LCD (9525) ---
private _photo = uiNamespace getVariable ["COMSPEC_SsePerson_PhotoPending", false];
private _bits = [];
_bits pushBack format ["%1 ÉCH.", count _samples];
if (_photo) then { _bits pushBack "PHOTO ARMÉE"; };
if ((_sig isEqualType []) && {(count _sig) >= 4}) then { _bits pushBack "SIGNÉ"; } else { _bits pushBack "NON SIGNÉ"; };
if (_queryPending) then {
    _bits pushBack "REQUÊTE…";
} else {
    if ((_query isEqualType []) && {(count _query) >= 1}) then {
        _bits pushBack (switch (_query select 0) do {
            case "confirmed": { "MATCH" };
            case "possible": { "MATCH ?" };
            default { "NO MATCH" };
        });
    };
};

(_disp displayCtrl 9525) ctrlSetStructuredText parseText format [
    "<t size='0.5' color='#9ed8b4' align='center'>%1</t>",
    _bits joinString "   ·   "
];

// --- Barre d’état de l’appareil (9526) : liaison, relevés, heure ---
private _link = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _linkTxt = switch (toLower (str _link)) do {
    case "linked": { "<t color='#9ed8b4'>LIAISON</t>" };
    case "degraded": { "<t color='#e0d27e'>DÉGRADÉE</t>" };
    default { "<t color='#e09a7e'>HORS LIAISON</t>" };
};
private _st = systemTime;
private _hh = _st select 3;
private _mm = _st select 4;
// Le dossier actif prime dans la barre : c'est le contexte de travail.
private _case = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase;
private _caseTxt = if (_case isEqualTo "") then {
    "<t color='#7f95a8'>HORS DOSSIER</t>"
} else {
    format ["<t color='#9ed8b4'>%1</t>", _case]
};
// Transmissions en attente : un tampon invisible ne vaut guère mieux qu'une
// perte. L'opérateur doit voir qu'il a des fiches non parties avant de quitter
// l'objectif, sinon il repart en croyant avoir rendu compte.
private _outbox = ["get"] call comspec_overwatch_connect_fnc_outboxState;
private _pending = _outbox getOrDefault ["count", 0];
private _pendingTxt = if (_pending > 0) then {
    format ["<t color='#e0a233'>%1 EN ATTENTE</t>  ·  ", _pending]
} else {
    ""
};

(_disp displayCtrl 9526) ctrlSetStructuredText parseText format [
    "<t size='0.38' align='right' color='#c8d4e0'>%1  ·  %5  ·  %6%2 éch.  ·  %3:%4</t>",
    _linkTxt,
    count _samples,
    if (_hh < 10) then { format ["0%1", _hh] } else { str _hh },
    if (_mm < 10) then { format ["0%1", _mm] } else { str _mm },
    _caseTxt,
    _pendingTxt
];
