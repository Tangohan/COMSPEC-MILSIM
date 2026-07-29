/*
    Contexte médical d’une personne au moment du relevé SSE.
    Source de vérité : ACE Medical, via fn_getMedicalState (déjà conforme à la règle
    roleplay 1.4.8 — SpO2 / voies aériennes / pneumothorax ne sont jamais exposés).

    La lecture des blessures ACE est défensive : la structure change entre versions,
    toute forme inattendue rend une liste vide plutôt qu’une erreur.

    Params: [_unit]
    Returns: HashMap — etat, etat_label, sang, pouls, douleur, arret_cardiaque,
                       lesions (array de libellés), resume (String)
*/
params [["_unit", objNull, [objNull]]];

private _out = createHashMap;
_out set ["etat", "inconnu"];
_out set ["etat_label", "Inconnu"];
_out set ["sang", -1];
_out set ["pouls", -1];
_out set ["douleur", false];
_out set ["arret_cardiaque", false];
_out set ["lesions", []];
_out set ["resume", ""];

if (isNull _unit) exitWith { _out };

// --- État général (ACE Medical) ---
private _raw = "";
if (!isNil "comspec_overwatch_connect_fnc_getMedicalState") then {
    _raw = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
};
if (!(_raw isEqualType "")) then { _raw = ""; };

private _etat = "stable";
if (_raw isNotEqualTo "") then {
    private _p = _raw splitString "|";
    if ((count _p) >= 1) then { _etat = _p select 0; };
    if ((count _p) >= 2) then { _out set ["sang", round (parseNumber (_p select 1))]; };
    if ((count _p) >= 3) then { _out set ["douleur", (_p select 2) isEqualTo "1"]; };
    if ((count _p) >= 4) then { _out set ["pouls", round (parseNumber (_p select 3))]; };
    if ((count _p) >= 5) then { _out set ["arret_cardiaque", (_p select 4) isEqualTo "1"]; };
};
if (!alive _unit) then { _etat = "decede"; };
_out set ["etat", _etat];

_out set ["etat_label", switch (_etat) do {
    case "decede": { "Décédée" };
    case "cardiac_arrest": { "Arrêt cardiaque" };
    case "unconscious": { "Inconsciente" };
    case "critical": { "Blessée — état critique" };
    case "wounded": { "Blessée" };
    case "stable": { "Consciente, stable" };
    default { "Inconnu" };
}];

// --- Localisation des lésions (alimente « signes distinctifs ») ---
private _parts = ["Tête", "Torse", "Bras gauche", "Bras droit", "Jambe gauche", "Jambe droite"];
private _lesions = [];

private _fnc_partLabel = {
    params ["_i"];
    if (!(_i isEqualType 0)) exitWith { "" };
    private _idx = floor _i;
    if (_idx < 0 || {_idx > 5}) exitWith { "" };
    _parts select _idx
};

private _wounds = _unit getVariable ["ace_medical_openWounds", nil];
if (!isNil "_wounds") then {
    // ACE récent : HashMap { id -> [classID, bodyPartIndex, amountOf, damage] }
    if (_wounds isEqualType createHashMap) then {
        {
            private _w = _y;
            if ((_w isEqualType []) && {(count _w) >= 2}) then {
                private _lbl = [_w select 1] call _fnc_partLabel;
                if (_lbl isNotEqualTo "") then { _lesions pushBackUnique _lbl; };
            };
        } forEach _wounds;
    };
    // ACE plus ancien : Array d’entrées de même forme.
    if (_wounds isEqualType []) then {
        {
            if ((_x isEqualType []) && {(count _x) >= 2}) then {
                private _lbl = [_x select 1] call _fnc_partLabel;
                if (_lbl isNotEqualTo "") then { _lesions pushBackUnique _lbl; };
            };
        } forEach _wounds;
    };
};

// Repli sans ACE (ou structure inattendue) : dégâts par sélection Arma.
if ((count _lesions) == 0 && {alive _unit}) then {
    {
        _x params ["_sel", "_lbl"];
        if ((_unit getHitPointDamage _sel) > 0.25) then { _lesions pushBackUnique _lbl; };
    } forEach [
        ["HitHead", "Tête"],
        ["HitBody", "Torse"],
        ["HitLeftArm", "Bras gauche"],
        ["HitRightArm", "Bras droit"],
        ["HitLeftLeg", "Jambe gauche"],
        ["HitRightLeg", "Jambe droite"]
    ];
};
_out set ["lesions", _lesions];

// --- Résumé lisible (constat de terrain, pas un bilan médical) ---
private _bits = [_out getOrDefault ["etat_label", "Inconnu"]];
private _pouls = _out getOrDefault ["pouls", -1];
if (_pouls > 0) then { _bits pushBack format ["pouls %1/min", _pouls]; };
private _sang = _out getOrDefault ["sang", -1];
if (_sang >= 0 && {_sang < 100}) then { _bits pushBack format ["volémie ≈ %1%2", _sang, "%"]; };
if (_out getOrDefault ["douleur", false]) then { _bits pushBack "douleur exprimée"; };
if ((count _lesions) > 0) then { _bits pushBack format ["lésions : %1", _lesions joinString ", "]; };

_out set ["resume", _bits joinString " · "];
_out
