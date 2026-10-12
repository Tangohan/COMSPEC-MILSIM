/*
    Réglages CBA du moteur biométrique (COMSPEC SSE › Biométrie).

    Tous globaux (1) : le serveur décide des dossiers et des verdicts, les clients
    calculent la qualité des relevés ; les deux doivent lire les mêmes valeurs.
*/
if (isNil "CBA_fnc_addSetting") exitWith { false };

private _cat = ["COMSPEC SSE", "Biométrie"];
private _catPop = ["COMSPEC SSE", "Biométrie — population"];

private _add = {
    params ["_var", "_type", "_label", "_cat", "_value"];
    [_var, _type, _label, _cat, _value, 1, {}, false] call CBA_fnc_addSetting;
};

["comspec_sse_bio_enabled", "CHECKBOX", ["Moteur biométrique", "Identités biologiques générées, base de données, qualité de capture selon le terrain et vraie recherche 1:N. Désactivé : ancien comportement (verdict tiré de la graine)."], _cat, true] call _add;
["comspec_sse_bio_campaignSeed", "EDITBOX", ["Graine de campagne", "Vide = nom de la mission. Gardez la même graine d'une mission à l'autre et fixez l'identifiant biométrique des personnages récurrents : ils garderont les mêmes empreintes."], _cat, ""] call _add;
["comspec_sse_bio_matchThreshold", "SLIDER", ["Seuil de correspondance", "Score fusionné au-delà duquel le terminal annonce MATCH (et au moins deux doigts ou un iris concordants)."], _cat, [30, 95, 60, 0]] call _add;
["comspec_sse_bio_possibleThreshold", "SLIDER", ["Seuil de correspondance possible", "En dessous : NO MATCH."], _cat, [5, 60, 25, 0]] call _add;
["comspec_sse_bio_impostorRate", "SLIDER", ["Fausses alertes (%)", "Probabilité de base qu'un individu ressemblant ressorte en « possible ». Elle augmente quand les relevés sont mauvais ou limités au visage. 0 = jamais."], _cat, [0, 30, 4, 0]] call _add;
["comspec_sse_bio_strictness", "SLIDER", ["Rigueur des conditions de capture", "Multiplie les pénalités (nuit, pluie, mort, blessures, sujet non maîtrisé). 0 = conditions ignorées."], _cat, [0, 2, 1, 1]] call _add;
["comspec_sse_bio_injuryBlock", "SLIDER", ["Blessure bloquant le relevé", "Dégâts ACE d'une partie du corps (bras, tête) à partir desquels les doigts de cette main, le visage ou l'iris ne peuvent plus être relevés. Plus bas = plus strict."], _cat, [0.3, 3, 1.2, 1]] call _add;
["comspec_sse_bio_localTiers", "SLIDER", ["Base locale du terminal : tiers embarqués", "Hors liaison, le terminal ne connaît que la watchlist jusqu'à ce tier (1 = DETAIN seulement, 4 = toute la watchlist) et les rencontres de la mission."], _cat, [1, 4, 2, 0]] call _add;
["comspec_sse_bio_delayLocal", "SLIDER", ["Durée d'une recherche locale (s)", ""], _cat, [1, 30, 5, 0]] call _add;
["comspec_sse_bio_delayAbis", "SLIDER", ["Durée d'une recherche ABIS en liaison (s)", ""], _cat, [1, 60, 12, 0]] call _add;
["comspec_sse_bio_dnaDelay", "SLIDER", ["Délai du résultat ADN (s)", "L'ADN part au laboratoire : le résultat revient à l'opérateur après ce délai. 0 = immédiat."], _cat, [0, 3600, 900, 0]] call _add;
["comspec_sse_bio_latents", "CHECKBOX", ["Empreintes latentes", "Armes au sol près d'un corps et objets liés à un sujet portent ses empreintes, relevables et recherchables."], _cat, true] call _add;
["comspec_sse_bio_athena", "CHECKBOX", ["Avis de recherche Athena", "En liaison, les avis de recherche de la communauté entrent dans la base (correspondance faciale et nominative)."], _cat, true] call _add;
["comspec_sse_bio_faceStudio", "CHECKBOX", ["Photo du visage en studio", "Le SEEK cadre un double du sujet, droit et éclairé, sur fond de ciel : fini les clichés dans le sol, dans un mur ou de nuit noire. La qualité reste celle du vrai sujet (masque, blessures, décès)."], _cat, true] call _add;
["comspec_sse_headBagGoggles", "EDITBOX", ["Modèle du sac de tête", "Classe de lunettes (CfgGlasses) portée par le prisonnier. Vide = cagoule COMSPEC provisoire. Ex. : G_Blindfold_01_black_F (bandeau du DLC Laws of War) ou le sac d'un autre mod."], _cat, ""] call _add;
["comspec_sse_bio_campaignMemory", "CHECKBOX", ["Mémoriser les rencontres entre missions", "Le serveur conserve les rencontres des personnages dont l'identifiant biométrique est fixé (profil serveur)."], _cat, false] call _add;

["comspec_sse_bio_enrolled_civ", "SLIDER", ["Civils déjà enrôlés (%)", "Part des civils présents dans une base (carte d'identité biométrique, recensement, employés locaux)."], _catPop, [0, 100, 35, 0]] call _add;
["comspec_sse_bio_enrolled_east", "SLIDER", ["OPFOR déjà enrôlés (%)", ""], _catPop, [0, 100, 55, 0]] call _add;
["comspec_sse_bio_enrolled_indep", "SLIDER", ["Indépendants déjà enrôlés (%)", ""], _catPop, [0, 100, 40, 0]] call _add;
["comspec_sse_bio_enrolled_west", "SLIDER", ["BLUFOR déjà enrôlés (%)", ""], _catPop, [0, 100, 10, 0]] call _add;
["comspec_sse_bio_watch_civ", "SLIDER", ["Civils en watchlist (%)", "Part de la population totale du camp (inclus dans les enrôlés)."], _catPop, [0, 100, 3, 0]] call _add;
["comspec_sse_bio_watch_east", "SLIDER", ["OPFOR en watchlist (%)", ""], _catPop, [0, 100, 25, 0]] call _add;
["comspec_sse_bio_watch_indep", "SLIDER", ["Indépendants en watchlist (%)", ""], _catPop, [0, 100, 10, 0]] call _add;
["comspec_sse_bio_watch_west", "SLIDER", ["BLUFOR en watchlist (%)", ""], _catPop, [0, 100, 0, 0]] call _add;
["comspec_sse_bio_falseId_civ", "SLIDER", ["Civils sous faux papiers (%)", "L'identité déclarée diffère de l'identité réelle ; seule une correspondance biométrique le révèle."], _catPop, [0, 100, 2, 0]] call _add;
["comspec_sse_bio_falseId_other", "SLIDER", ["Combattants sous faux papiers (%)", ""], _catPop, [0, 100, 12, 0]] call _add;

true
