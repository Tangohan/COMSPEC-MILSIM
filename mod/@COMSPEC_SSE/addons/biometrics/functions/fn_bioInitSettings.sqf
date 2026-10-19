/*
    Réglages CBA du moteur biométrique (COMSPEC SSE › Biométrie).

    Tous globaux (1) : le serveur décide des dossiers et des verdicts, les clients
    calculent la qualité des relevés ; les deux doivent lire les mêmes valeurs.
*/
if (isNil "CBA_fnc_addSetting") exitWith { false };

private _cat = ["COMSPEC SSE", "Biométrie"];
private _catPop = ["COMSPEC SSE", "Biométrie : population"];

private _add = {
    params ["_var", "_type", "_label", "_cat", "_value"];
    [_var, _type, _label, _cat, _value, 1, {}, false] call comspec_sse_fnc_addSetting;
};

["comspec_sse_bio_enabled", "CHECKBOX", ["Biométrie réaliste", "Chaque personnage a ses propres empreintes, iris et ADN, enregistrés dans une base ; la qualité du relevé dépend des conditions et la recherche compare vraiment le relevé à toute la base. Décoché : ancien système, résultat tiré au hasard."], _cat, true] call _add;
["comspec_sse_bio_campaignSeed", "EDITBOX", ["Code de campagne", "Vide : nom de la mission. Gardez le même code d'une mission à l'autre et donnez un identifiant biométrique fixe aux personnages récurrents : ils garderont les mêmes empreintes."], _cat, ""] call _add;
["comspec_sse_bio_matchThreshold", "SLIDER", ["Score pour une correspondance (MATCH)", "Score à partir duquel le terminal annonce MATCH (il faut aussi au moins deux doigts ou un iris identiques)."], _cat, [30, 95, 60, 0]] call _add;
["comspec_sse_bio_possibleThreshold", "SLIDER", ["Score pour une correspondance possible", "En dessous de ce score, le terminal annonce NO MATCH."], _cat, [5, 60, 25, 0]] call _add;
["comspec_sse_bio_impostorRate", "SLIDER", ["Fausses alertes (%)", "Risque qu'une personne qui se ressemble sorte en « correspondance possible ». Il augmente si les relevés sont mauvais ou faits seulement sur le visage. 0 = jamais."], _cat, [0, 30, 4, 0]] call _add;
["comspec_sse_bio_strictness", "SLIDER", ["Effet des conditions sur les relevés", "Plus c'est haut, plus la nuit, la pluie, la mort, les blessures ou un sujet qui se débat dégradent les relevés. 0 = aucun effet."], _cat, [0, 2, 1, 1]] call _add;
["comspec_sse_bio_injuryBlock", "SLIDER", ["Blessure qui empêche le relevé", "Niveau de blessure ACE (bras, tête) à partir duquel on ne peut plus relever les empreintes de cette main, le visage ou l'iris. Plus c'est bas, plus c'est strict."], _cat, [0.3, 3, 1.2, 1]] call _add;
["comspec_sse_bio_localTiers", "SLIDER", ["Base hors ligne du terminal", "Sans liaison, le terminal ne connaît que les personnes recherchées jusqu'à ce niveau (1 = seulement « à arrêter », 4 = toute la liste) et celles déjà croisées pendant la mission."], _cat, [1, 4, 2, 0]] call _add;
["comspec_sse_bio_delayLocal", "SLIDER", ["Durée d'une recherche hors ligne (s)", ""], _cat, [1, 30, 5, 0]] call _add;
["comspec_sse_bio_delayAbis", "SLIDER", ["Durée d'une recherche en ligne (s)", ""], _cat, [1, 60, 12, 0]] call _add;
["comspec_sse_bio_dnaDelay", "SLIDER", ["Délai du résultat ADN (s)", "L'ADN est envoyé au labo : le résultat arrive après ce délai. 0 = tout de suite."], _cat, [0, 3600, 900, 0]] call _add;
["comspec_sse_bio_latents", "CHECKBOX", ["Empreintes laissées sur les objets", "Les armes au sol près d'un corps et les objets d'une personne portent ses empreintes : on peut les relever et les rechercher."], _cat, true] call _add;
["comspec_sse_bio_athena", "CHECKBOX", ["Avis de recherche Athena", "Avec la liaison, les avis de recherche de votre communauté sur Athena sont ajoutés à la base (reconnaissance par visage et par nom)."], _cat, true] call _add;
["comspec_sse_bio_faceStudio", "CHECKBOX", ["Photo du visage en studio", "Le SEEK prend la photo du visage comme en studio, sujet droit et bien éclairé : plus de photo ratée dans le sol, dans un mur ou en pleine nuit. La qualité dépend quand même du vrai sujet (masque, blessures, mort)."], _cat, true] call _add;
["comspec_sse_headBagGoggles", "EDITBOX", ["Modèle de la cagoule de prisonnier", "Item de visage (classe CfgGlasses) mis sur la tête du prisonnier. Vide : cagoule COMSPEC. Ex. : G_Blindfold_01_black_F (bandeau du DLC Laws of War) ou le sac d'un autre mod."], _cat, ""] call _add;
["comspec_sse_bio_campaignMemory", "CHECKBOX", ["Garder les rencontres d'une mission à l'autre", "Le serveur se souvient des personnages déjà croisés, s'ils ont un identifiant biométrique fixe."], _cat, false] call _add;

["comspec_sse_bio_enrolled_civ", "SLIDER", ["Civils déjà fichés (%)", "Part des civils déjà fichés (carte d'identité biométrique, recensement, employés locaux)."], _catPop, [0, 100, 35, 0]] call _add;
["comspec_sse_bio_enrolled_east", "SLIDER", ["OPFOR déjà fichés (%)", ""], _catPop, [0, 100, 55, 0]] call _add;
["comspec_sse_bio_enrolled_indep", "SLIDER", ["Indépendants déjà fichés (%)", ""], _catPop, [0, 100, 40, 0]] call _add;
["comspec_sse_bio_enrolled_west", "SLIDER", ["BLUFOR déjà fichés (%)", ""], _catPop, [0, 100, 10, 0]] call _add;
["comspec_sse_bio_watch_civ", "SLIDER", ["Civils recherchés (%)", "Part du camp sur la liste des personnes recherchées (compris dans les fichés)."], _catPop, [0, 100, 3, 0]] call _add;
["comspec_sse_bio_watch_east", "SLIDER", ["OPFOR recherchés (%)", ""], _catPop, [0, 100, 25, 0]] call _add;
["comspec_sse_bio_watch_indep", "SLIDER", ["Indépendants recherchés (%)", ""], _catPop, [0, 100, 10, 0]] call _add;
["comspec_sse_bio_watch_west", "SLIDER", ["BLUFOR recherchés (%)", ""], _catPop, [0, 100, 0, 0]] call _add;
["comspec_sse_bio_falseId_civ", "SLIDER", ["Civils sous faux papiers (%)", "Leur nom déclaré n'est pas leur vrai nom ; seule la biométrie permet de le découvrir."], _catPop, [0, 100, 2, 0]] call _add;
["comspec_sse_bio_falseId_other", "SLIDER", ["Combattants sous faux papiers (%)", ""], _catPop, [0, 100, 12, 0]] call _add;

true
