/*
    Formulaire Zeus : rendre les objets sélectionnés (ou l'objet visé) extractibles au DL-7.
    [_objects] call comspec_sse_fnc_dl7ZeusForm
*/
params [["_objs", [], [[]]]];
_objs = _objs select { !isNull _x };
if (_objs isEqualTo []) exitWith { ["Sélectionnez ou visez un objet.", "warn"] call comspec_sse_fnc_zeusNotify; };
if (isNil "zen_dialog_fnc_create") exitWith { ["Zeus Enhanced requis pour ce formulaire.", "warn"] call comspec_sse_fnc_zeusNotify; };
[
    format ["Extraction DL-7 (%1 objet%2)", count _objs, ["", "s"] select ((count _objs) > 1)],
    [
        ["COMBO", ["Contenu", "Données SSE générées dans l'appareil, fichiers saisis, ou les deux."], [["sse", "manual", "both"], ["Données SSE générées", "Fichiers saisis", "Les deux"], 0]],
        ["EDIT:MULTI", ["Fichiers saisis", "Blocs séparés par « --- » : « nom | type » puis le contenu."], ["", {}, 5]],
        ["EDIT", "Nom du support (vide = automatique)", ""],
        ["COMBO", "Écran de connexion", [["auto", "windows", "linux", "militaire", "scada", "telephone", "coffre"], ["Selon l'appareil", "Windows", "Linux", "Militaire", "SCADA", "Téléphone verrouillé", "Coffre chiffré"], 0]],
        ["EDIT", "Mot de passe (vide = libre)", ""],
        ["EDIT", "Indice", ""],
        ["SLIDER", "Essais permis (0 = illimité)", [0, 20, 3, 0]],
        ["COMBO", "Trop d'essais", [["lock", "wipe", "alarm", "none"], ["Verrouiller", "Effacer", "Alarme", "Rien"], 0]],
        ["CHECKBOX", "Forçage possible", true],
        ["SLIDER", "Durée du transfert (s)", [3, 600, 45, 0]],
        ["COMBO", "Alarme", [["none", "connect", "fail", "download"], ["Aucune", "Au branchement", "Mauvais mot de passe", "Fin de téléchargement"], 0]],
        ["CHECKBOX", "Effacé après le premier téléchargement", false]
    ],
    {
        params ["_v", "_objs"];
        _v params ["_content", "_files", "_name", "_theme", "_pw", "_hint", "_att", "_fail", "_crack", "_dur", "_alarm", "_one"];
        private _cfg = [["content", _content], ["files", _files], ["name", _name], ["theme", _theme], ["hint", _hint],
            ["attempts", round _att], ["onFail", _fail], ["crack", _crack], ["duration", round _dur], ["alarm", _alarm], ["oneShot", _one]];
        if (_pw isNotEqualTo "") then { _cfg pushBack ["password", _pw]; };
        { [_x, _cfg] remoteExecCall ["comspec_sse_fnc_dl7Apply", 2]; } forEach _objs;
        [format ["%1 support(s) extractible(s) au câble DL-7.", count _objs]] call comspec_sse_fnc_zeusNotify;
    },
    {},
    _objs
] call zen_dialog_fnc_create;
