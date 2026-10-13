/*
    Langues disponibles en plus du français (langue du code) : classes de CfgComspecLanguages, déclarées par ce mod
    (en, i18n/en.txt) et par tout mod de traduction chargé (mod Workshop communautaire, voir localisation/README.md).
    Une classe = un code de langue : name (nom dans sa langue), file (dictionnaire [["français", "traduction"], ...]),
    gameLanguage[] (valeurs de la commande language qui la choisissent en Auto, par exemple "Spanish").
    Renvoie [[code, nom, fichier, langues du jeu], ...], anglais en premier, puis par code. Résultat gardé.
*/
private _list = uiNamespace getVariable "COMSPEC_ATAK_LangList";
if (!isNil "_list") exitWith { _list };
_list = [];
{
    private _code = toLower (configName _x);
    private _file = getText (_x >> "file");
    if (_code isNotEqualTo "fr" && {_file isNotEqualTo ""} && {(_list findIf { (_x select 0) isEqualTo _code }) < 0}) then {
        private _games = if (isArray (_x >> "gameLanguage")) then { getArray (_x >> "gameLanguage") } else { [getText (_x >> "gameLanguage")] - [""] };
        private _name = getText (_x >> "name");
        _list pushBack [_code, [_name, toUpper _code] select (_name isEqualTo ""), _file, _games];
    };
} forEach ("true" configClasses (configFile >> "CfgComspecLanguages"));
// Anglais d'abord, puis les autres par code (sort trie les tableaux sur leur premier élément).
private _en = _list select { (_x select 0) isEqualTo "en" };
private _others = _list select { (_x select 0) isNotEqualTo "en" };
_others sort true;
_list = _en + _others;
uiNamespace setVariable ["COMSPEC_ATAK_LangList", _list];
_list
