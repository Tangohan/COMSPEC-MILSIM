/*
    Bibliothèque de l'atelier IA (fn_iarpProfile « preset », « generate ») :
      presets  [[identifiant, libellé FR, libellé EN, [[champ, valeur]…]]…]   profils prêts à l'emploi
      pools    réserves de tirage du bouton « Générer » (phrases types, sujets sensibles, renseignements…)
    Champs : voir fn_iarpProfile (role, nerve, talk, aggro, honesty, bribe, fear, drunk, lang, country, story, phrases,
    sensitive, secrets, know, unlock, unlockWord, react). Une mission peut ajouter des profils avec un fichier
    iarp_presets.sqf à sa racine (même format, seule la liste « presets » est lue).
*/
private _m = { createHashMapFromArray _this };
private _nl = toString [10];
[
    ["presets", [
        ["civil_apeure", "Civil apeuré", "Frightened civilian", [
            ["role", "civil"], ["nerve", 70], ["talk", 35], ["aggro", 5], ["honesty", 85], ["bribe", 15], ["fear", 90],
            ["phrases", ["Je n'ai rien fait.", "Je veux juste rentrer chez moi."]], ["unlock", "trust"],
            ["react", [["search", "coopere"], ["threat", "rendu"], ["bribe", "refuse"], ["order", "coopere"]]]
        ]],
        ["marchand", "Marchand bavard", "Chatty merchant", [
            ["role", "civil"], ["nerve", 30], ["talk", 90], ["aggro", 10], ["honesty", 60], ["bribe", 55], ["fear", 40],
            ["phrases", ["Les affaires vont mal.", "Vous voulez acheter quelque chose ?"]],
            ["know", "Des hommes achètent beaucoup d'essence depuis une semaine, toujours la nuit."], ["unlock", "trust"],
            ["react", [["search", "coopere"], ["threat", "auto"], ["bribe", "coopere"], ["order", "coopere"]]]
        ]],
        ["informateur", "Informateur corrompu", "Corrupt informant", [
            ["role", "civil"], ["nerve", 55], ["talk", 60], ["aggro", 15], ["honesty", 35], ["bribe", 90], ["fear", 60],
            ["sensitive", ["la milice", "le garage"]],
            // Savoir par paliers : vague sous la pression, précis payé, complet après deux pots-de-vin ou un aveu.
            ["know", "Vague : La milice a un chef, il ne dort jamais deux nuits au même endroit." + _nl
                + "Précis : Le chef de la milice dort au garage près de la mosquée, deux gardes la nuit." + _nl
                + "Complet : Il part chaque matin à cinq heures dans un pick-up gris, par la route du nord, sans escorte."], ["unlock", "bribe"],
            ["react", [["search", "coopere"], ["threat", "auto"], ["bribe", "coopere"], ["order", "coopere"]]]
        ]],
        ["milicien", "Milicien nerveux", "Nervous militiaman", [
            ["role", "insurge"], ["nerve", 80], ["talk", 25], ["aggro", 65], ["honesty", 20], ["bribe", 30], ["fear", 35],
            ["sensitive", ["armes", "la cache", "mon groupe"]], ["secrets", ["Il garde un fusil sous le siège de son pick-up."]],
            ["unlock", "never"], ["react", [["search", "refuse"], ["threat", "auto"], ["bribe", "refuse"], ["order", "auto"]]]
        ]],
        ["chef_village", "Chef de village", "Village elder", [
            ["role", "civil"], ["nerve", 15], ["talk", 70], ["aggro", 25], ["honesty", 75], ["bribe", 20], ["fear", 20],
            ["phrases", ["Ici, tout le monde me connaît.", "Je parle pour le village."]],
            ["know", "Trois étrangers sont passés hier, ils ont demandé la route du col."], ["unlock", "trust"],
            ["react", [["search", "refuse"], ["threat", "refuse"], ["bribe", "refuse"], ["order", "auto"]]]
        ]],
        ["garde_cp", "Garde de checkpoint", "Checkpoint guard", [
            ["role", "policier"], ["nerve", 25], ["talk", 30], ["aggro", 45], ["honesty", 65], ["bribe", 60], ["fear", 25],
            ["phrases", ["Avancez.", "Papiers."]], ["unlock", "bribe"],
            ["know", "La relève arrive à six heures, ils ne sont que deux la nuit."],
            ["react", [["search", "refuse"], ["threat", "agressif"], ["bribe", "coopere"], ["order", "refuse"]]]
        ]],
        ["agent", "Agent infiltré", "Undercover agent", [
            ["role", "agent"], ["nerve", 15], ["talk", 55], ["aggro", 20], ["honesty", 20], ["bribe", 5], ["fear", 15],
            ["sensitive", ["l'ambassade", "mon contact"]], ["secrets", ["Il rend compte chaque soir par téléphone."]],
            ["unlock", "keyword"], ["unlockWord", "faucon"],
            ["react", [["search", "coopere"], ["threat", "auto"], ["bribe", "refuse"], ["order", "coopere"]]]
        ]],
        ["pecheur", "Pêcheur du port", "Harbour fisherman", [
            ["role", "civil"], ["nerve", 25], ["talk", 75], ["aggro", 10], ["honesty", 80], ["bribe", 25], ["fear", 45], ["drunk", 40],
            ["know", "Un bateau sans feux accoste à la vieille jetée, certaines nuits."], ["unlock", "trust"]
        ]],
        ["hvt", "Cible de valeur", "High-value target", [
            ["role", "hvt"], ["nerve", 35], ["talk", 20], ["aggro", 75], ["honesty", 10], ["bribe", 5], ["fear", 10],
            ["sensitive", ["mon frère", "l'argent", "la frontière"]], ["unlock", "never"],
            ["react", [["search", "refuse"], ["threat", "agressif"], ["bribe", "refuse"], ["order", "refuse"]]]
        ]]
    ]],
    ["pools", [
        ["phrases", ["Je n'ai rien à cacher.", "Il fait chaud aujourd'hui.", "Ma femme m'attend.", "Ça fait trois contrôles ce matin.", "Je suis en retard.", "Dieu nous garde.", "Je ne fais pas de politique.", "Tout augmente, ici."]],
        ["sensitive", ["armes", "la mosquée", "le marché noir", "mon frère", "la nuit dernière", "la frontière", "l'essence", "les étrangers", "la milice"]],
        ["know", [
            "J'ai vu un pick-up blanc sans plaques partir vers le nord hier soir.",
            "Des hommes armés se retrouvent à la ferme abandonnée, après la prière du soir.",
            "Le garagiste répare des voitures la nuit, des gens que personne ne connaît.",
            "On dit qu'il y a des mines sur l'ancienne route du col.",
            "Un étranger loue une chambre au-dessus du café depuis une semaine."
        ]],
        ["secrets", ["Il cache de l'argent chez sa sœur.", "Il a un cousin dans la milice.", "Ses papiers ne sont pas à lui.", "Il a vu l'embuscade et n'a rien dit."]],
        ["travel", ["Je rentre chez moi après le travail", "Une livraison pour mon employeur", "Rendre visite à ma famille", "Un rendez-vous médical", "Chercher du travail"]],
        ["employer", ["Transports KARRAS", "Mairie de la ville", "Coopérative agricole", "Garage du port", "Hôtel central"]],
        ["job", ["Commerçant", "Chauffeur", "Mécanicien", "Enseignant", "Pêcheur", "Électricien", "Fonctionnaire"]],
        ["cities", ["Kavala", "Agia Marina", "Pyrgos", "Sofia", "Zaros", "Athira"]],
        ["unlockWord", ["faucon", "olivier", "marée", "cèdre"]]
    ] call _m]
] call _m
