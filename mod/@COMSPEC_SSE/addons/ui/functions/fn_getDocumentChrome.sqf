/*
    Chrome de présentation des fiches / documents SSE (bandeau, titres, pied, papier).
    MissionNamespace : COMSPEC_SSE_DocChrome (HashMap).

    Modes :
      ["get"] call …
      ["set", _hashMap] call …
      ["apply_prefab", "terrain_tache"] call …
      ["prefabs"] call …
      ["defaults"] call …
*/
params [
    ["_mode", "get", [""]],
    ["_payload", createHashMap, [createHashMap, ""]]
];

private _defaults = createHashMapFromArray [
    ["prefab", "standard_restreint"],
    ["paper_style", "clean"],
    ["banner", "DIFFUSION RESTREINTE — EXPLOITATION TERRAIN"],
    ["title_person", "DOSSIER SSE"],
    ["title_docs", "DOSSIER DOCUMENTAIRE"],
    ["subtitle_dossier", "Compte rendu d’exploitation"],
    ["subtitle_feuille", "Feuille de consultation — lecture détaillée"],
    ["subtitle_docs", "Pièces saisies sur le terrain"],
    ["footer", "Ne constitue pas une preuve judiciaire — usage RP / renseignement uniquement."],
    ["quality_prefix", "Qualité d’exploitation"],
    ["btn_consult", "FEUILLE"],
    ["btn_transmit", "TRANSMETTRE"],
    ["btn_close", "FERMER"]
];

private _prefabs = createHashMapFromArray [
    ["standard_restreint", createHashMapFromArray [
        ["paper_style", "clean"],
        ["banner", "DIFFUSION RESTREINTE — EXPLOITATION TERRAIN"],
        ["title_person", "DOSSIER SSE"],
        ["title_docs", "DOSSIER DOCUMENTAIRE"],
        ["subtitle_dossier", "Compte rendu d’exploitation"],
        ["subtitle_feuille", "Feuille de consultation — lecture détaillée"],
        ["subtitle_docs", "Pièces saisies sur le terrain"],
        ["footer", "Ne constitue pas une preuve judiciaire — usage RP / renseignement uniquement."],
        ["quality_prefix", "Qualité d’exploitation"],
        ["btn_consult", "FEUILLE"],
        ["btn_transmit", "TRANSMETTRE"],
        ["btn_close", "FERMER"]
    ]],
    ["terrain_tache", createHashMapFromArray [
        ["paper_style", "stained"],
        ["banner", "SAISIE TERRAIN — EXPLOITATION"],
        ["title_person", "FICHE D’EXPLOITATION"],
        ["title_docs", "PIÈCES SAISIES"],
        ["subtitle_dossier", "Relevé d’exploitation sur site"],
        ["subtitle_feuille", "Lecture détaillée — pièce terrain"],
        ["subtitle_docs", "Documents récupérés sur place"],
        ["footer", "Document de travail — ne vaut pas preuve judiciaire. Usage RP / renseignement."],
        ["quality_prefix", "Qualité de saisie"],
        ["btn_consult", "DÉTAIL"],
        ["btn_transmit", "ENVOYER"],
        ["btn_close", "FERMER"]
    ]],
    ["brouillon_froisse", createHashMapFromArray [
        ["paper_style", "crumpled"],
        ["banner", "BROUILLON — NE PAS DIFFUSER"],
        ["title_person", "NOTES D’EXPLOITATION"],
        ["title_docs", "PIÈCES EN COURS"],
        ["subtitle_dossier", "Brouillon de compte rendu"],
        ["subtitle_feuille", "Relecture — brouillon"],
        ["subtitle_docs", "Documents non encore classés"],
        ["footer", "Brouillon RP — non opposable. À ne pas confondre avec un acte officiel."],
        ["quality_prefix", "Avancement"],
        ["btn_consult", "LIRE"],
        ["btn_transmit", "TRANSMETTRE"],
        ["btn_close", "FERMER"]
    ]],
    ["bureau_jauni", createHashMapFromArray [
        ["paper_style", "aged"],
        ["banner", "ARCHIVES — CONSULTATION CONTRÔLÉE"],
        ["title_person", "FICHE D’ARCHIVES"],
        ["title_docs", "DOSSIER DOCUMENTAIRE"],
        ["subtitle_dossier", "Compte rendu classé"],
        ["subtitle_feuille", "Consultation d’archives"],
        ["subtitle_docs", "Pièces versées au dossier"],
        ["footer", "Exemplaire d’archives — usage renseignement / RP. Ne constitue pas une preuve judiciaire."],
        ["quality_prefix", "Qualité d’exploitation"],
        ["btn_consult", "FEUILLE"],
        ["btn_transmit", "TRANSMETTRE"],
        ["btn_close", "FERMER"]
    ]],
    ["formel_propre", createHashMapFromArray [
        ["paper_style", "clean"],
        ["banner", "DIFFUSION CONTRÔLÉE — BUREAU SSE"],
        ["title_person", "FICHE D’IDENTITÉ"],
        ["title_docs", "ANNEXE DOCUMENTAIRE"],
        ["subtitle_dossier", "Compte rendu d’exploitation"],
        ["subtitle_feuille", "Feuille officielle"],
        ["subtitle_docs", "Pièces jointes"],
        ["footer", "Document de renseignement — usage RP uniquement. Ne constitue pas une preuve judiciaire."],
        ["quality_prefix", "Qualité d’exploitation"],
        ["btn_consult", "FEUILLE"],
        ["btn_transmit", "TRANSMETTRE"],
        ["btn_close", "FERMER"]
    ]]
];

_mode = toLower _mode;

if (_mode isEqualTo "prefabs") exitWith { _prefabs };
if (_mode isEqualTo "defaults") exitWith { +_defaults };

if (_mode isEqualTo "apply_prefab") exitWith {
    private _code = if (_payload isEqualType "") then { _payload } else {
        _payload getOrDefault ["prefab", "standard_restreint"]
    };
    _code = toLower (trim _code);
    private _base = +_defaults;
    private _from = _prefabs getOrDefault [_code, createHashMap];
    {
        _base set [_x, _y];
    } forEach _from;
    _base set ["prefab", _code];
    missionNamespace setVariable ["COMSPEC_SSE_DocChrome", _base, true];
    _base
};

if (_mode isEqualTo "set") exitWith {
    if (_payload isEqualType "") exitWith {
        ["apply_prefab", _payload] call comspec_sse_fnc_getDocumentChrome
    };
    private _cur = missionNamespace getVariable ["COMSPEC_SSE_DocChrome", +_defaults];
    if (!(_cur isEqualType createHashMap)) then { _cur = +_defaults; };
    {
        if (_y isEqualType "" && {(trim _y) isNotEqualTo ""}) then {
            _cur set [_x, _y];
        };
    } forEach _payload;
    missionNamespace setVariable ["COMSPEC_SSE_DocChrome", _cur, true];
    _cur
};

private _chrome = missionNamespace getVariable ["COMSPEC_SSE_DocChrome", nil];
if (isNil "_chrome" || {!(_chrome isEqualType createHashMap)}) then {
    _chrome = +_defaults;
};
private _out = +_defaults;
{
    _out set [_x, _y];
} forEach _chrome;
_out
