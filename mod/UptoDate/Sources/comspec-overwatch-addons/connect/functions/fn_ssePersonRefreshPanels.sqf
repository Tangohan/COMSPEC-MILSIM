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
        // (Le nombre de points tiré au hasard à chaque rafraîchissement faisait
        // clignoter la ligne et la poussait sur deux lignes : retiré.)
        _lines pushBack format [
            "<t size='0.44' color='%1'>■ %2</t> <t size='0.42' color='%1'>%3</t>"
            + " <t size='0.42' color='#c8e8ff'>%4%5</t>"
            + " <t size='0.4' color='#7f95a8'>· %6</t>",
            _col, _lbl,
            _gauge,
            _quality, "%",
            _ref
        ];
    };
} forEach _samples;

// Verdict d'identité — remplace la note générique dès qu'une requête a abouti.
private _queryPending = uiNamespace getVariable ["COMSPEC_SsePerson_QueryPending", false];
private _query = uiNamespace getVariable ["COMSPEC_SsePerson_Query", []];
private _verdict = "<t size='0.42' color='#5f7383'>Touchez QUERY pour interroger la base d’identités.</t>";
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

// Sujet de la fiche : le relevé lui est rattaché (sans sujet, la personne la plus
// proche est prise au moment du relevé).
private _bt = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
private _subj = if (isNull _bt) then {
    "<t size='0.44' color='#e0d27e'>Aucun sujet visé : placez-vous à côté de la personne.</t>"
} else {
    private _n = trim format ["%1 %2", _bt getVariable ["COMSPEC_SSE_FirstName", ""], _bt getVariable ["COMSPEC_SSE_LastName", ""]];
    if (_n isEqualTo "") then { _n = "personne visée"; };
    format [
        "<t size='0.44' color='#8fb3a6'>Sujet : </t><t size='0.44' color='#f2fff8'>%1</t><t size='0.42' color='#8fb3a6'>%2  ·  %3 m</t>",
        _n,
        ["", " (décédé)"] select (!alive _bt),
        (player distance _bt) toFixed 1
    ]
};
private _bioMsg = uiNamespace getVariable ["COMSPEC_SsePerson_BioMsg", ""];
if (!(_bioMsg isEqualType "")) then { _bioMsg = ""; };

private _bioRows = [_subj];
if ((count _lines) > 0) then {
    _bioRows append _lines;
    _bioRows pushBack _verdict;
} else {
    _bioRows pushBack "<t size='0.5' color='#c8eadc'>Aucun relevé.</t> <t size='0.42' color='#8fb3a6'>Touchez EMPR., IRIS ou ADN.</t>";
};
if (_bioMsg isNotEqualTo "") then { _bioRows pushBack _bioMsg; };
(_disp displayCtrl 9522) ctrlSetStructuredText parseText (_bioRows joinString "<br/>");

// Platine (9566) : jauge des trois modalités (empreintes, iris, ADN) — elle était
// toujours pleine et passait pour un relevé terminé.
private _platen = _disp displayCtrl 9566;
private _bioCtrl = _disp displayCtrl 9522;
if (!isNull _platen && {!isNull _bioCtrl}) then {
    private _full = (ctrlPosition _bioCtrl) select 2;
    private _pp = ctrlPosition _platen;
    private _kinds = [];
    { if ((_x isEqualType []) && {(count _x) >= 1}) then { _kinds pushBackUnique (_x select 0); }; } forEach _samples;
    private _frac = ((count _kinds) min 3) / 3;
    _pp set [2, _full * (_frac max 0.04)];
    _platen ctrlSetPosition _pp;
    _platen ctrlCommit 0;
    _platen ctrlSetBackgroundColor ([[0.176, 0.784, 0.290, 0.25], [0.176, 0.784, 0.290, 0.85]] select (_frac > 0));
};

// --- Signature (9520) ---
private _sig = uiNamespace getVariable ["COMSPEC_SsePerson_Signature", []];
private _sigTxt = if ((_sig isEqualType []) && {(count _sig) >= 4}) then {
    format [
        "<t size='0.62' color='#7ee0a0'>Signé  %1</t><br/><t size='0.56' color='#c8eadc'>%2  ·  %3</t>",
        _sig select 0,
        _sig select 1,
        _sig select 3
    ]
} else {
    "<t size='0.64' color='#f0c070'>Non signé</t><br/><t size='0.56' color='#c8eadc'>La fiche partira sans procès-verbal.</t>"
};
(_disp displayCtrl 9520) ctrlSetStructuredText parseText _sigTxt;

// --- Viseur du visage (9577) : sujet, cadrage, qualité estimée, dernier cliché ---
private _face = uiNamespace getVariable ["COMSPEC_SseFace", createHashMap];
private _ft = _face getOrDefault ["target", objNull];
if (isNull _ft) then { _ft = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull]; };
private _faceLines = [];
if (isNull _ft) then {
    _faceLines pushBack "<t size='0.5' color='#c8eadc'>Aucun sujet.</t><br/><t size='0.4' color='#8fb3a6'>Visez la personne (4 m) puis rouvrez le SEEK.</t>";
} else {
    private _who = trim format ["%1 %2", _ft getVariable ["COMSPEC_SSE_FirstName", ""], _ft getVariable ["COMSPEC_SSE_LastName", ""]];
    if (_who isEqualTo "") then { _who = "Sujet non identifié"; };
    private _info = _face getOrDefault ["info", createHashMap];
    private _angleTxt = switch (_face getOrDefault ["angle", "face"]) do {
        case "trois_quarts": { "Trois quarts" };
        case "profil": { "Profil" };
        default { "Face" };
    };
    private _dist = player distance _ft;
    _faceLines pushBack format ["<t size='0.54' color='#f2fff8'>%1</t>", _who];
    _faceLines pushBack format [
        "<t size='0.44' color='#9ed8b4'>%1  ·  zoom x%2  ·  %3 m%4</t>",
        _angleTxt, _face getOrDefault ["zoom", 1], _dist toFixed 1,
        ["", "  ·  au sol"] select ((_info getOrDefault ["couche", ""]) isEqualTo "sol")
    ];
    if (_dist > 4) then {
        _faceLines pushBack "<t size='0.44' color='#e0a233'>Trop loin : approchez-vous à 4 m au plus.</t>";
    } else {
        private _q = _info getOrDefault ["qualite", -1];
        if (_q >= 0) then {
            private _qc = ["#e09a7e", "#e0d27e", "#7ee0a0"] select ((parseNumber (_q >= 55)) + (parseNumber (_q >= 80)));
            private _note = _info getOrDefault ["note", ""];
            _faceLines pushBack format [
                "<t size='0.44' color='%1'>Qualité estimée %2</t>%3",
                _qc, _q, ["", format [" <t size='0.4' color='#8fb3a6'>· %1</t>", _note]] select (_note isNotEqualTo "")
            ];
        };
    };
    private _meta = _ft getVariable ["comspec_sse_facePhotoMeta", []];
    if ((_meta isEqualType []) && {(count _meta) >= 3}) then {
        _faceLines pushBack format [
            "<t size='0.44' color='#7ee0a0'>Cliché de %1 · qualité %2</t>",
            _meta select 2, _meta select 1
        ];
    };
};
private _faceCtrl = _disp displayCtrl 9577;
if (!isNull _faceCtrl) then { _faceCtrl ctrlSetStructuredText parseText (_faceLines joinString "<br/>"); };

// --- Bandeau LCD (9525) ---
private _photo = uiNamespace getVariable ["COMSPEC_SsePerson_PhotoPending", false];
private _bits = [];
_bits pushBack format ["%1 ÉCH.", count _samples];
if (_photo) then { _bits pushBack "PHOTO ARMÉE"; };
if ((_face getOrDefault ["frozen", false])) then { _bits pushBack "CLICHÉ AFFICHÉ"; } else {
    if (!isNull (_face getOrDefault ["cam", objNull])) then { _bits pushBack "VISEUR"; };
};
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
    case "linked": { "<t color='#9ed8b4'>Liaison</t>" };
    case "degraded": { "<t color='#e0d27e'>Dégradée</t>" };
    default { "<t color='#e09a7e'>Hors liaison</t>" };
};
private _st = systemTime;
private _hh = _st select 3;
private _mm = _st select 4;
private _case = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase;
private _caseTxt = if (_case isEqualTo "") then {
    "<t color='#7f95a8'>Sans dossier</t>"
} else {
    format ["<t color='#9ed8b4'>%1</t>", _case]
};
private _outbox = ["get"] call comspec_overwatch_connect_fnc_outboxState;
private _pending = _outbox getOrDefault ["count", 0];
private _extra = [];
if (_pending > 0) then {
    _extra pushBack (format ["<t color='#e0a233'>%1 att.</t>", _pending]);
};
private _nSamples = count _samples;
if (_nSamples > 0) then {
    _extra pushBack (format ["%1 rel.", _nSamples]);
};
_extra pushBack format [
    "%1:%2",
    if (_hh < 10) then { format ["0%1", _hh] } else { str _hh },
    if (_mm < 10) then { format ["0%1", _mm] } else { str _mm }
];

// Barre d'état compacte : police réduite et libellés courts — à 0.56 avec des
// séparateurs doubles, la ligne débordait de la moitié droite de la barre.
(_disp displayCtrl 9526) ctrlSetStructuredText parseText format [
    "<t size='0.48' align='right' color='#d8f0e6'>%1 · %2 · %3</t>",
    _linkTxt,
    _caseTxt,
    _extra joinString " · "
];
