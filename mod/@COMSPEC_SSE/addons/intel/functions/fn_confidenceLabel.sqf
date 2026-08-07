params [
    ["_kind", "EXTRACTED", [""]]
];
switch (toUpper _kind) do {
    case "OBSERVED": { "Fait observé" };
    case "EXTRACTED": { "Donnée extraite" };
    case "PROBABLE": { "Association probable" };
    case "HYPOTHESIS": { "Hypothèse" };
    default { _kind };
};
