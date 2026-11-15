/*
    Catalogue SSE du site de la communauté (ICD-SSE-CATALOGUE-001) : passe la main à l'ATAK natif, qui lit Athena par
    COMSPEC Link et garde les listes (comspec_overwatch_connect_fnc_sseCatalogue : mêmes opérations, mêmes retours).
    Sans l'ATAK natif : aucune liste du site, chaque écran garde sa liste locale.
      ["choices", [section, codes, libellés]] -> [codes, libellés, source]   source : "site" | "poste" | "local"
      ["rows", section] · ["ensure"] · ["refresh"] · ["state"] · ["status"]
*/
params [["_op", "rows", [""]], ["_arg", []]];
if (!isNil "comspec_overwatch_connect_fnc_sseCatalogue") exitWith { _this call comspec_overwatch_connect_fnc_sseCatalogue };
switch (_op) do {
    case "choices": { [_arg param [1, []], _arg param [2, []], "local"] };
    case "rows": { [] };
    case "state": { ["local", "", ""] };
    case "status": { ("Listes locales du mod (ATAK natif absent : pas de lecture du site)") call comspec_sse_fnc_tr };
    default { false };
}
