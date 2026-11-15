/*
    Applique à des sujets une identité choisie dans les listes du site de la communauté (SERVEUR).
    [_unités, _uidLore, _libelléLore, _réfAvis, _libelléAvis, _contexte, _dossier] call comspec_sse_fnc_siteIdentityApply
    (remoteExecCall [..., 2] depuis le poste d'un Zeus ou d'un administrateur ; module Eden : appel direct).

    Variables publiques de l'unité :
      comspec_sse_aiUid / COMSPEC_ID_AiUid   personne du lore de la session (ICD-SSE-LORE-001) : le lore déjà reçu est
                                             rattaché tout de suite, sinon à la prochaine redescente
      comspec_sse_wantedRef                  référence de l'avis de recherche associé (envoyée avec la fiche : wanted_ref)
      comspec_sse_acqContext                 contexte d'acquisition des fiches transmises (circumstances)
      comspec_sse_caseRef                    dossier de classement propre au sujet (case_code) ; vide : dossier actif
    Un libellé « RÉF · NOM Prénom » donne aussi le nom déclaré du sujet (applyProfile), pour que le terminal l'affiche
    même sans redescente du lore. Valeur vide : rien n'est changé pour ce champ.
*/
params [
    ["_units", [], [[]]],
    ["_uid", "", [""]], ["_uidLabel", "", [""]],
    ["_wanted", "", [""]], ["_wantedLabel", "", [""]],
    ["_context", "", [""]], ["_case", "", [""]]
];
if (!isServer) exitWith { false };
// Appel distant : réservé à un Zeus (ou à l'administrateur connecté), comme la référence de dossier.
private _refused = false;
if (isRemoteExecuted && {remoteExecutedOwner != 2}) then {
    private _caller = remoteExecutedOwner;
    private _isZeus = (allCurators findIf { owner (getAssignedCuratorUnit _x) == _caller }) >= 0;
    _refused = !_isZeus && {(admin _caller) <= 0};
};
if (_refused) exitWith {
    ["siteIdentityApply refusé : appelant ni Zeus ni administrateur", "WARN"] call comspec_sse_fnc_log;
    false
};

// Textes bornés : codes et références du site (lettres, chiffres, _ . : -), libellés d'une ligne.
private _code = {
    params ["_s", "_max"];
    (toString ((toArray _s) select { (_x >= 48 && {_x <= 58}) || {_x >= 65 && {_x <= 90}} || {_x >= 97 && {_x <= 122}} || {_x in [45, 46, 95]} })) select [0, _max]
};
_uid = [_uid, 64] call _code;
_wanted = [_wanted, 40] call _code;
_context = [_context, 64] call _code;
_case = toUpper ([_case, 32] call _code);

// « RÉF · NOM Prénom » -> [réf, nom, prénom] ; le nom est le premier mot, le reste le prénom.
private _split = {
    params ["_label"];
    private _i = _label find " · ";
    if (_i < 0) exitWith { [_label, "", ""] };
    private _name = trim (_label select [_i + 3]);
    private _sp = _name find " ";
    if (_sp < 0) exitWith { [_label select [0, _i], _name, ""] };
    [_label select [0, _i], _name select [0, _sp], trim (_name select [_sp + 1])]
};

private _done = 0;
{
    private _unit = _x;
    if (_unit isEqualType objNull && {!isNull _unit} && {_unit isKindOf "CAManBase"} && {!isPlayer _unit}) then {
        _done = _done + 1;
        private _profile = [];
        private _named = false;
        if (_uid isNotEqualTo "") then {
            _unit setVariable ["COMSPEC_ID_AiUid", _uid, true];
            _unit setVariable ["comspec_sse_aiUid", _uid, true];
            private _reg = missionNamespace getVariable ["comspec_sse_loreUids", createHashMap];
            if (_reg isEqualType createHashMap) then {
                _reg set [_uid, _unit];
                missionNamespace setVariable ["comspec_sse_loreUids", _reg];
            };
            ([_uidLabel select [0, 120]] call _split) params ["_ref", "_last", "_first"];
            if (_ref isNotEqualTo "") then { _profile pushBack ["record_ref", _ref]; };
            if (_last isNotEqualTo "") then { _profile pushBack ["last_name", _last]; _named = true; };
            if (_first isNotEqualTo "") then { _profile pushBack ["first_name", _first]; };
            if (!isNil "comspec_sse_fnc_loreServer") then { ["attach", [_unit]] call comspec_sse_fnc_loreServer; };
        };
        if (_wanted isNotEqualTo "") then {
            _unit setVariable ["comspec_sse_wantedRef", _wanted, true];
            // Sans personne du lore choisie : le sujet porte le nom de l'avis.
            if (!_named) then {
                ([_wantedLabel select [0, 120]] call _split) params ["", "_last", "_first"];
                if (_last isNotEqualTo "") then { _profile pushBack ["last_name", _last]; };
                if (_first isNotEqualTo "") then { _profile pushBack ["first_name", _first]; };
            };
        };
        if (_context isNotEqualTo "") then { _unit setVariable ["comspec_sse_acqContext", _context, true]; };
        if (_case isNotEqualTo "") then { _unit setVariable ["comspec_sse_caseRef", _case, true]; };
        if (_profile isNotEqualTo []) then { [_unit, _profile] call comspec_sse_fnc_applyProfile; };
    };
} forEach (_units select [0, 40]);

[format ["siteIdentityApply sujets=%1 lore=%2 avis=%3 contexte=%4 dossier=%5", _done, _uid, _wanted, _context, _case]] call comspec_sse_fnc_log;
_done > 0
