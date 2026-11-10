/*
    Comptes rendus SALUTE et SPOTREP pré-remplis depuis le contrôle en cours (client, app Contrôle ID).
    Mêmes brouillons que les apps de l'ATAK, sans toucher à leur code :
      SALUTE   uiNamespace COMSPEC_ATAK_SaluteDraft (champs sS, sA, sL, sU, sT, sE), puis app SALUTE
      SPOTREP  missionNamespace COMSPEC_ATAK_RepDrafts « SPOTREP » ([valeurs, heure, propositions]), puis app Comptes
               rendus ouverte sur la fiche SPOTREP (comspec_atak_native_fnc_reportAction « type ») ; ATAK 3.0.2 et plus :
               aussi uiNamespace COMSPEC_ATAK_SpotrepDraft (entrée publique « prefill »), mêmes valeurs
    Le joueur relit, complète et envoie depuis l'app (rien ne part tout seul).
    ["SALUTE" | "SPOTREP"] call comspec_sse_fnc_idReport
*/
params [["_kind", "SALUTE", [""]]];
if (!hasInterface) exitWith { false };
private _rec = ["current"] call comspec_sse_fnc_idHistory;
if ((count _rec) isEqualTo 0) exitWith { ["Aucun contrôle en cours.", "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify; false };
if (isNil "comspec_atak_native_fnc_navigate") exitWith { false };
private _d = _rec get "doc";
private _subj = _rec getOrDefault ["subj", objNull];
if !(_subj isEqualType objNull) then { _subj = objNull; };
private _pos = if (isNull _subj) then { _rec getOrDefault ["pos", getPosASL player] } else { getPosASL _subj };
private _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef;
private _cfgName = { params ["_cls", ["_root", "CfgWeapons"]]; if (_cls isEqualTo "") exitWith { "" }; getText (configFile >> _root >> _cls >> "displayName") };
private _who = format ["%1 %2", toUpper (_d getOrDefault ["last", ""]), _d getOrDefault ["first", ""]];
private _type = ["label", _d getOrDefault ["type", ""]] call comspec_sse_fnc_idDocTypes;
private _paper = format ["%1 %2 n° %3", _type, _d getOrDefault ["country", ""], _d getOrDefault ["number", ""]];
private _verdict = toUpper (_rec getOrDefault ["verdict", "ras"]);
// Tenue et armes vues sur la personne (rien d'autre que ce qu'on voit).
private _dress = [];
private _arms = [];
if (!isNull _subj) then {
    { private _n = [_x] call _cfgName; if (_n isNotEqualTo "") then { _dress pushBack _n; }; } forEach [uniform _subj, vest _subj, headgear _subj];
    { private _n = [_x] call _cfgName; if (_n isNotEqualTo "") then { _arms pushBack _n; }; } forEach [primaryWeapon _subj, secondaryWeapon _subj, handgunWeapon _subj];
};
// Ce qu'il a fait au contrôle (dernière réponse reçue de cette personne).
private _last = missionNamespace getVariable ["comspec_sse_idLastAnswer", []];
private _attitude = if ((count _last) >= 3 && {(_last select 0) isEqualTo _subj} && {!isNull _subj}) then { _last select 2 } else { "" };
private _activity = format [("Contrôle d'identité, verdict %1" call comspec_sse_fnc_tr), _verdict] + (["", " · " + (_attitude call comspec_sse_fnc_tr)] select (_attitude isNotEqualTo ""));

if (_kind isEqualTo "SALUTE") exitWith {
    private _dr = uiNamespace getVariable ["COMSPEC_ATAK_SaluteDraft", createHashMap];
    _dr set ["sS", format ["1 PAX (%1)", _who]];
    _dr set ["sA", _activity];
    _dr set ["sL", _grid];
    _dr set ["sU", [(_dress joinString ", "), "tenue civile" call comspec_sse_fnc_tr] select (_dress isEqualTo [])];
    _dr set ["sT", _rec getOrDefault ["clock", [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
    _dr set ["sE", format [("%1 · papiers : %2" call comspec_sse_fnc_tr), [(_arms joinString ", "), "aucune arme visible" call comspec_sse_fnc_tr] select (_arms isEqualTo []), _paper]];
    uiNamespace setVariable ["COMSPEC_ATAK_SaluteDraft", _dr];
    ["INFO", ("SALUTE pré-rempli avec le contrôle : relisez puis envoyez" call comspec_sse_fnc_tr), 3, 20, "SALUTE"] call comspec_atak_native_fnc_notify;
    [{ ["SALUTE"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    true
};

// SPOTREP (app Comptes rendus) : groupe date-heure au format de l'app.
private _date = _rec getOrDefault ["date", date];
private _hm = (_rec getOrDefault ["clock", "00:00"]) splitString ":";
private _months = [["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"],
    ["JAN", "FÉV", "MAR", "AVR", "MAI", "JUN", "JUL", "AOÛ", "SEP", "OCT", "NOV", "DÉC"]] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr");
private _dtg = format ["%1%2%3J %4 %5", [_date select 2, 2] call CBA_fnc_formatNumber, _hm param [0, "00"], _hm param [1, "00"],
    _months select (((_date select 1) - 1) max 0 min 11), [(_date select 0) mod 100, 2] call CBA_fnc_formatNumber];
private _civ = !isNull _subj && {(side group _subj) isEqualTo civilian};
private _vals = createHashMapFromArray [
    ["what", ["Personnel", "Civils"] select _civ],
    ["count", "1"],
    ["grid", _grid],
    ["time", _dtg],
    ["action", ["SURVEILLANCE", "SIGNALÉ"] select (_verdict in ["FAUX", "INCONNU", "VOLE", "REVOQUE", "SIGNALE", "RECHERCHE", "HVT"])],
    ["remarks", (format ["Contrôle d'identité : %1, %2. Verdict %3.%4%5%6", _who, _paper, _verdict,
        ["", format [" %1.", _attitude]] select (_attitude isNotEqualTo ""),
        ["", format [" Tenue : %1.", _dress joinString ", "]] select (_dress isNotEqualTo []),
        ["", format [" Armes : %1.", _arms joinString ", "]] select (_arms isNotEqualTo [])]) select [0, 480]]
];
["INFO", ("SPOTREP pré-rempli avec le contrôle : relisez puis envoyez" call comspec_sse_fnc_tr), 3, 20, "REPORTS"] call comspec_atak_native_fnc_notify;
// ATAK 3.0.2 et plus : entrée publique du SPOTREP pré-rempli, lue une fois à l'ouverture de l'app Comptes rendus.
if ([3, 0, 2] call comspec_sse_fnc_idAtakAtLeast) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_SpotrepDraft", _vals];
    [{ ["REPORTS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    true
};
// ATAK 3.0.1 : brouillon SPOTREP de l'app, puis fiche ouverte.
private _drafts = missionNamespace getVariable ["COMSPEC_ATAK_RepDrafts", createHashMap];
_drafts set ["SPOTREP", [_vals, [dayTime, "HH:MM"] call BIS_fnc_timeToString, createHashMap]];
missionNamespace setVariable ["COMSPEC_ATAK_RepDrafts", _drafts];
[{
    ["REPORTS"] call comspec_atak_native_fnc_navigate;
    [{ ["type", "SPOTREP"] call comspec_atak_native_fnc_reportAction; }] call CBA_fnc_execNextFrame;
}] call CBA_fnc_execNextFrame;
true
