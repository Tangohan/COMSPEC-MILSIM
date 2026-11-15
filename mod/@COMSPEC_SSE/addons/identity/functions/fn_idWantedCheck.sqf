/*
    Avis de recherche de la personne contrôlée, lu sur le site (ICD-SSE-FICHES-001, kind « sse_fiches », op « wanted ») une fois le
    contrôle transmis à Athena : « RECHERCHÉ » s'affiche dans le contrôle d'identité (SEEK II / NCIC) même quand l'avis n'était pas
    encore dans le paquet d'identités du poste. COMSPEC Link ne relaie que « OK|id|réf » pour un contrôle : ce second appel passe par
    le canal « kind » déjà ouvert, sans nouvelle DLL. Aucun toast : le verdict de la fiche change et l'écran du contrôle se redessine.

    [_rec, idContrôle] call comspec_sse_fnc_idWantedCheck       (fiche de fn_idHistory ; id = celui de « OK|id|réf »)
    Renvoie true si le site connaît un avis actif pour la personne. L'avis est gardé sur la fiche (clé « wanted »)
    [référence, type, menace, armé, mesure courte, motifs codifiés, mesures codifiées, service demandeur, signalement court] (les
    quatre derniers viennent des éléments 8 à 11 de la réponse, absents d'un site plus ancien : listes et textes vides) ; le verdict de la fiche devient « recherche » (« hvt » pour un type HVT) s'il
    était moins grave (fn_idHistory « verdict »). À appeler depuis un contexte suspendable (spawn de fn_idAthenaSubmit).
*/
params [["_rec", createHashMap, [createHashMap]], ["_checkId", 0, [0, ""]]];
if ((count _rec) isEqualTo 0) exitWith { false };
if (_checkId isEqualType "") then { _checkId = parseNumber _checkId; };
if (!(_checkId isEqualType 0) || {_checkId < 1}) exitWith { false };
private _json = format ['{"kind":"sse_fiches","op":"wanted","check":%1}', floor _checkId];
private _raw = ["SubmitIdCheck", [_json]] call comspec_sse_fnc_extensionCall;
if (!(_raw isEqualType "") || {(_raw select [0, 3]) isNotEqualTo "OK|"}) exitWith { false };
// OK|id|page : la page (tableau parseSimpleArray) commence après le deuxième « | ».
private _rest = _raw select [3];
private _cut = _rest find "|";
if (_cut < 0) exitWith { false };
private _text = _rest select [_cut + 1];
if ((_text select [0, 1]) isNotEqualTo "[") exitWith { false };
private _p = parseSimpleArray _text;
// [version, "wanted", avis ?, réf, type, menace, armé, mesure, motifs, mesures, service, signalement]
if (!(_p isEqualType []) || {(count _p) < 8} || {(_p select 1) isNotEqualTo "wanted"} || {(_p select 2) isNotEqualTo true}) exitWith { false };
private _dec = { params ["_t"]; if !(_t isEqualType "") exitWith { "" }; (_t regexReplace ["\\p", "|"]) regexReplace ["\\n", " "] };
private _wanted = [[_p select 3] call _dec, [_p select 4] call _dec, [_p select 5] call _dec, [_p select 6] call _dec, [_p select 7] call _dec];
if ((_wanted select 0) isEqualTo "") exitWith { false };
// Motifs et mesures codifiés (« CODE libellé »), service demandeur et signalement court : additifs, tolérés absents.
private _list = { params ["_a"]; if !(_a isEqualType []) exitWith { [] }; ((_a select { _x isEqualType "" }) apply { [_x] call _dec }) select { _x isNotEqualTo "" } };
if ((count _p) >= 12) then {
    _wanted append [[_p select 8] call _list, [_p select 9] call _list, [_p select 10] call _dec, [_p select 11] call _dec];
};
_rec set ["wanted", _wanted];
_rec set ["verdict", ["verdict", _rec] call comspec_sse_fnc_idHistory];
// Écran du contrôle redessiné (jamais d'alerte à l'écran : la bannière du verdict et la carte de l'avis changent).
[{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCHECK") then { ["IDCHECK"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
true
