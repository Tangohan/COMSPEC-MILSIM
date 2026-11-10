/*
    « Relever » le document affiché (client) : ses champs imprimés et sa bande MRZ sont notés sur le relevé de contrôle du
    joueur (fiche de l'historique « Contrôles » de l'app Contrôle ID, texte de la FRM : fn_idFrm) et remis au point d'entrée
    unique du relevé de contrôle (téléphone › Fichiers, Athena « Contrôles subis ») : évènement CBA local
    « comspec_sse_controlRecord » et, si elle existe, comspec_sse_fnc_controlRecord, avec UN tableau de paires
    [clé, valeur] (clés ci-dessous, fn_idPhoto « pairs ») et la fiche. Rien n'est affiché par l'interface Arma : la ligne
    d'état de la visionneuse dit ce qui s'est passé, le téléphone reçoit une notification, le RPT garde une ligne.
    La commande screenshot d'Arma n'enregistre que la scène 3D, jamais l'interface : une image du document n'est possible
    qu'en la dessinant hors du jeu (site), à partir de ces mêmes champs ; le relevé reste la source.
    ["can"]    -> [possible, motif (vide si possible), texte court du bouton (vide : « RELEVER »)]
    ["shoot"]  visionneuse ouverte : relevé du document (contrôle en cours, sinon un contrôle est ouvert sur ce document)
    ["app"]    depuis l'app Contrôle ID : ouvre la visionneuse sur le document en cours, relevé fait tout seul, puis
               retour à l'app.
    ["pairs", fiche] -> tableau de paires du relevé (sans l'enregistrer)
    Paires : kind "document", docId, type, typeLabel, country, countryName, number, last, first, sex, dob, pob, issued,
    expires, authority, mrz (tableau de lignes), holder (objet, objNull si inconnu), holderName, holderNetId, holderUid,
    place (carroyage), visual (examen visuel, une ligne), clock, date, grid, pos, controller, controllerUid, callsign,
    verdict, n0 (lignes de l'examen visuel), case (référence de dossier), idem (clé d'idempotence).
    comspec_sse_fnc_controlRecord (chantier Fichiers / SEEK II) reçoit la HashMap de ces paires et renvoie la clé du relevé.
*/
params [["_op", "shoot", [""]], ["_x1", createHashMap, [createHashMap]]];
if (!hasInterface) exitWith { false };
private _st = uiNamespace getVariable ["comspec_sse_idView", createHashMap];
private _viewer = uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull];
private _hasPhone = !isNil "comspec_atak_native_fnc_hasDevice" && {[player] call comspec_atak_native_fnc_hasDevice};
private _recOf = {
    // Fiche du contrôle en cours si elle porte sur le document affiché, sinon celle de l'historique qui le porte.
    private _doc = _st getOrDefault ["doc", createHashMap];
    private _id = _doc getOrDefault ["id", ""];
    private _cur = ["current"] call comspec_sse_fnc_idHistory;
    if ((count _cur) > 0 && {(_cur getOrDefault ["docId", ""]) isEqualTo _id}) exitWith { _cur };
    private _i = (["list"] call comspec_sse_fnc_idHistory) findIf { (_x getOrDefault ["docId", ""]) isEqualTo _id };
    if (_i >= 0) exitWith { ["select", [_i]] call comspec_sse_fnc_idHistory };
    createHashMap
};
private _pairs = {
    params ["_rec"];
    private _d = _rec getOrDefault ["doc", createHashMap];
    private _mrz = _d getOrDefault ["mrz", []];
    if !(_mrz isEqualType []) then { _mrz = [_mrz]; };
    private _callsign = if (isNil "comspec_overwatch_connect_fnc_getCallsign") then { "" } else { [] call comspec_overwatch_connect_fnc_getCallsign };
    if (_callsign isEqualTo "") then { _callsign = groupId group player; };
    private _n0 = (_rec getOrDefault ["levels", createHashMap]) getOrDefault [0, createHashMap];
    [
        ["kind", "document"], ["docId", _d getOrDefault ["id", ""]], ["type", _d getOrDefault ["type", ""]],
        ["typeLabel", ["label", _d getOrDefault ["type", ""]] call comspec_sse_fnc_idDocTypes],
        ["country", _d getOrDefault ["country", ""]], ["countryName", _d getOrDefault ["countryName", ""]], ["number", _d getOrDefault ["number", ""]],
        ["last", _d getOrDefault ["last", ""]], ["first", _d getOrDefault ["first", ""]], ["sex", _d getOrDefault ["sex", ""]],
        ["dob", _d getOrDefault ["dob", ""]], ["pob", _d getOrDefault ["pob", ""]], ["issued", _d getOrDefault ["issued", ""]],
        ["expires", _d getOrDefault ["expires", ""]], ["authority", _d getOrDefault ["authority", ""]], ["mrz", _mrz],
        ["holder", _subj], ["holderName", _rec getOrDefault ["subjName", ""]], ["holderNetId", _rec getOrDefault ["subjNetId", ""]], ["holderUid", _rec getOrDefault ["subjUid", ""]],
        ["place", _rec getOrDefault ["grid", mapGridPosition player]], ["visual", (_n0 getOrDefault ["lines", []]) joinString " · "],
        ["clock", _rec getOrDefault ["clock", [dayTime, "HH:MM"] call BIS_fnc_timeToString]], ["date", _rec getOrDefault ["date", date]],
        ["grid", _rec getOrDefault ["grid", mapGridPosition player]], ["pos", _rec getOrDefault ["pos", getPosASL player]],
        ["controller", name player], ["controllerUid", getPlayerUID player], ["callsign", _callsign],
        ["verdict", _rec getOrDefault ["verdict", "ras"]], ["n0", _n0 getOrDefault ["lines", []]],
        ["case", _rec getOrDefault ["case", ""]], ["idem", _rec getOrDefault ["idem", ""]]
    ]
};

if (_op isEqualTo "pairs") exitWith { [_x1] call _pairs };
if (_op isEqualTo "can") exitWith {
    if (isNull _viewer || {(count _st) isEqualTo 0}) exitWith { [false, "", ""] };
    if (_st getOrDefault ["own", false]) exitWith { [false, "Votre propre document : rien à relever.", ""] };
    if (!_hasPhone) exitWith { [false, "Pas de téléphone ATAK sur vous : le relevé n'a nulle part où aller.", "SANS TÉLÉPHONE"] };
    private _rec = call _recOf;
    if ((_rec getOrDefault ["photo", ""]) isEqualTo "releve") exitWith { [false, format ["Déjà relevé à %1.", _rec getOrDefault ["photoClock", ""]], format ["RELEVÉ À %1", _rec getOrDefault ["photoClock", ""]]] };
    [true, "", ""]
};
if (_op isEqualTo "app") exitWith {
    private _rec = ["current"] call comspec_sse_fnc_idHistory;
    if ((count _rec) isEqualTo 0) exitWith { ["Aucun contrôle en cours : rien à relever.", "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify; false };
    [_rec get "doc", false, true] call comspec_sse_fnc_idViewerOpen
};
// « shoot » sans visionneuse (app) : on l'ouvre sur le contrôle en cours, le relevé part tout seul.
if (isNull _viewer || {(count _st) isEqualTo 0}) exitWith {
    private _rec = ["current"] call comspec_sse_fnc_idHistory;
    if ((count _rec) isEqualTo 0) exitWith { ["Aucun contrôle en cours : rien à relever.", "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify; false };
    [_rec get "doc", false, true] call comspec_sse_fnc_idViewerOpen
};
private _status = { ["status", _this select 0, _this select 1] call comspec_sse_fnc_idViewerOnLoad; };
(["can"] call comspec_sse_fnc_idPhoto) params ["_ok", "_why"];
if (!_ok) exitWith { [_why, "#f2c94c"] call _status; false };

// Fiche du contrôle : celle en cours, sinon une nouvelle sur ce document (papiers tendus et ouverts tout seuls).
private _rec = call _recOf;
if ((count _rec) isEqualTo 0) then {
    private _subj = _st getOrDefault ["subj", objNull];
    if !(_subj isEqualType objNull) then { _subj = objNull; };
    _rec = ["start", [_st get "doc", _subj, "", createHashMap]] call comspec_sse_fnc_idHistory;
};
private _d = _rec get "doc";
private _clock = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
private _type = ["label", _d getOrDefault ["type", ""]] call comspec_sse_fnc_idDocTypes;
private _mrz = _d getOrDefault ["mrz", []];
if !(_mrz isEqualType []) then { _mrz = [_mrz]; };
private _lines = [
    format [("Relevé du document à %1 : %2 %3 n° %4." call comspec_sse_fnc_tr), _clock, _type, _d getOrDefault ["country", ""], _d getOrDefault ["number", ""]],
    format [("Titulaire : %1 %2, %3, né(e) le %4 à %5 ; délivré le %6, expire le %7." call comspec_sse_fnc_tr), toUpper (_d getOrDefault ["last", ""]), _d getOrDefault ["first", ""],
        _d getOrDefault ["sex", ""], _d getOrDefault ["dob", ""], _d getOrDefault ["pob", ""], _d getOrDefault ["issued", ""], _d getOrDefault ["expires", ""]]
];
if ((_d getOrDefault ["rank", ""]) isNotEqualTo "") then { _lines pushBack format [("Grade et force : %1, %2." call comspec_sse_fnc_tr), _d get "rank", _d getOrDefault ["force", ""]]; };
if (_mrz isNotEqualTo []) then { _lines pushBack format ["MRZ : %1", _mrz joinString " / "]; };
_rec set ["releve", _lines];
_rec set ["photo", "releve"];
_rec set ["photoClock", _clock];
_rec set ["sent", false];
// Point d'entrée unique du relevé de contrôle (téléphone › Fichiers, Athena) : paires [clé, valeur] et la fiche.
private _pairsOut = [_rec] call _pairs;
_rec set ["record", _pairsOut];
["comspec_sse_controlRecord", [_pairsOut, _rec]] call CBA_fnc_localEvent;
// Chantier « Fichiers et SEEK II » (comspec_sse_fnc_controlRecord : fiche complétée, fichier de mission, envoi id_record à
// Athena, évènement comspec_sse_controlRecorded) : HashMap des mêmes paires (type, country, number, last, first, dob,
// expires, holder (objet), place, clock, visual, docId…) ; renvoie la clé du relevé, gardée sur la fiche.
if (!isNil "comspec_sse_fnc_controlRecord") then {
    private _key = [createHashMapFromArray _pairsOut] call comspec_sse_fnc_controlRecord;
    if (_key isEqualType "" && {_key isNotEqualTo ""}) then { _rec set ["recordKey", _key]; };
};
// Dossier SSE de la personne contrôlée : photo de preuve (section photos, journal de la pièce).
private _subj = _rec getOrDefault ["subj", objNull];
if (_subj isEqualType objNull && {!isNull _subj} && {_subj getVariable ["comspec_sse_enabled", false]} && {!isNil "comspec_sse_fnc_evidencePhoto"}) then {
    [[[_subj, player distance _subj]], 1] call comspec_sse_fnc_evidencePhoto;
};
diag_log text format ["[COMSPEC SSE] Identité : relevé du document %1 (%2 %3 n° %4) à %5 par %6.", _d getOrDefault ["id", ""], _type, _d getOrDefault ["country", ""], _d getOrDefault ["number", ""], _clock, name player];
[format ["Relevé : %1 noté dans votre téléphone (Contrôles).", _d getOrDefault ["number", ""]], "#cfe8cf"] call _status;
[format ["Document relevé : %1 %2 n° %3 (%4 %5).", _type, _d getOrDefault ["country", ""], _d getOrDefault ["number", ""], toUpper (_d getOrDefault ["last", ""]), _d getOrDefault ["first", ""]], "SUCCESS", "", "IDCHECK"] call comspec_sse_fnc_notify;
// Relevé demandé depuis l'app : retour à l'app Contrôle ID.
if (_st getOrDefault ["auto", false]) then {
    [] spawn {
        uiSleep 0.8;
        closeDialog 2;
        uiSleep 0.1;
        if (!isNil "comspec_atak_native_fnc_open" && {[player] call comspec_atak_native_fnc_hasDevice}) then {
            [true] call comspec_atak_native_fnc_open;
            [{ ["IDCHECK"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        };
    };
};
true
