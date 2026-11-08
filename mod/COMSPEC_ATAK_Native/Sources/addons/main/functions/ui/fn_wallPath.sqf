/*
    Fonds d'écran du téléphone : chemin de la texture, liste connue ici seulement (fn_layoutApply, fn_lockScreen, fn_phonePad).
    Les fonds d'origine sont dans main.pbo ; les images du site (logo Athena, bannières, tools/gen_lock_assets.py) dans
    items.pbo, qui a la place : main.pbo approche la limite de 100 Mo de GitHub.
    Params : ["path", [clé, flou, paysage]] -> chemin .paa, "" pour « aucun » ou une clé inconnue
*/
params [["_act", "path"], ["_arg", []]];
_arg params [["_key", ""], ["_blur", false], ["_land", true]];
private _dir = switch (true) do {
    case (_key in ["topo", "night", "desert", "olive", "dark", "soar", "athena", "ops"]): { "\z\comspec_atak_native\addons\main\data\" };
    case (_key in ["eagle", "logo", "atak", "sse", "sof"]): { "\z\comspec_atak_native\addons\items\data\walls\" };
    default { "" };
};
if (_dir isEqualTo "") exitWith { "" };
format ["%1wall_%2%3_%4.paa", _dir, _key, ["", "_blur"] select (_blur isEqualTo true), ["port", "land"] select _land]
