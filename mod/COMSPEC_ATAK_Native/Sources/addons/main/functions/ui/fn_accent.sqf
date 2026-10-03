/* Couleur d'accent choisie par le joueur (Réglages > Personnalisation) : [rgba, hexadécimal]. */
switch (profileNamespace getVariable ["COMSPEC_ATAK_Accent", "GREEN"]) do {
    case "BLUE": { [[0.30, 0.62, 0.98, 1], "#4d9ffa"] };
    case "ORANGE": { [[0.98, 0.58, 0.20, 1], "#fa9433"] };
    case "RED": { [[0.92, 0.30, 0.26, 1], "#eb4d42"] };
    case "PURPLE": { [[0.68, 0.45, 0.95, 1], "#ad73f2"] };
    case "SAND": { [[0.85, 0.75, 0.50, 1], "#d9bf80"] };
    default { [[0.36, 0.78, 0.42, 1], "#5cc76b"] };
}
