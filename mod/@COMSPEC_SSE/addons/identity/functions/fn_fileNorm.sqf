/*
    Normalisation des critères des fichiers : même règle sur le poste (fn_fileQuery) et sur le serveur (fn_fileServer),
    pour qu'un numéro recopié à la main retrouve celui imprimé sur le document.
      ["number", texte]   -> numéro en capitales, sans espaces, tirets, points ni barres (« alz 036-925 » -> ALZ036925)
      ["plate", texte]    -> plaque, même règle
      ["country", texte]  -> code du pays tel que le registre l'écrit : pays fictifs ALT, TNA, LVN, MLD (alias TAN, LIV,
                             MAL, AL, LV, noms ALTIS, TANOA, LIVONIA, LIVONIE, MALDEN) ; pays réels en ISO alpha-3 (alpha-2
                             accepté : GB, DE, FR… ; code MRZ D de l'Allemagne) ; inconnu : capitales sans espaces
                             ("" si vide). Un code d'un pays ajouté par Athena reste tel quel.
*/
params [["_op", ""], ["_v", ""]];
if !(_v isEqualType "") then { _v = str _v; };
switch (_op) do {
    case "number";
    case "plate": { (toUpper _v) regexReplace ["[^A-Z0-9]", ""] };
    case "country": {
        private _c = (toUpper (trim _v)) regexReplace ["[^A-Z]", ""];
        if (_c isEqualTo "") exitWith { "" };
        private _alias = missionNamespace getVariable "comspec_sse_fileCountryAlias";
        if (isNil "_alias") then {
            _alias = createHashMapFromArray [
                ["AL", "ALT"], ["ALTIS", "ALT"],
                ["TAN", "TNA"], ["TANOA", "TNA"],
                ["LIV", "LVN"], ["LV", "LVN"], ["LIVONIA", "LVN"], ["LIVONIE", "LVN"],
                ["MAL", "MLD"], ["MALDEN", "MLD"],
                ["GB", "GBR"], ["UK", "GBR"], ["GR", "GRC"], ["US", "USA"], ["DE", "DEU"], ["D", "DEU"], ["AT", "AUT"],
                ["CO", "COL"], ["FR", "FRA"], ["BE", "BEL"], ["ES", "ESP"], ["PL", "POL"], ["UA", "UKR"], ["RU", "RUS"],
                ["IQ", "IRQ"], ["SY", "SYR"], ["AF", "AFG"]
            ];
            missionNamespace setVariable ["comspec_sse_fileCountryAlias", _alias];
        };
        _alias getOrDefault [_c, _c]
    };
    default { toUpper (trim _v) };
}
