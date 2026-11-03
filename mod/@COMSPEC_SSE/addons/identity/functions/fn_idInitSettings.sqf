/*
    Réglages CBA du contrôle d'identité (COMSPEC SSE › Identité). Tous globaux : le serveur décide (papiers des IA,
    réactions, évaluation), les clients affichent les mêmes durées et la même difficulté.
*/
if (isNil "CBA_fnc_addSetting") exitWith { false };
private _cat = ["COMSPEC SSE", "Identité"];
private _add = {
    params ["_var", "_type", "_label", "_value"];
    [_var, _type, _label, _cat, _value, 1, {}, false] call comspec_sse_fnc_addSetting;
};

["comspec_sse_id_enabled", "CHECKBOX", ["Contrôle d'identité", "Papiers d'identité fictifs, contrôle par le menu ACE et l'app Contrôle ID du téléphone."], true] call _add;
["comspec_sse_id_difficulty", "LIST", ["Difficulté des contrôles", "Arcade : les anomalies se voient facilement. Réaliste : un bon faux passe souvent l'examen visuel, la nuit tout est plus dur."], [[0, 1, 2], ["Arcade", "Standard", "Réaliste"], 1]] call _add;
["comspec_sse_id_country", "LIST", ["Pays des papiers", "Pays fictif des papiers délivrés. Selon la carte : Altis et Stratis → Altis, Tanoa → Tanoa, Livonia → Livonie, Malden → Malden."], [["auto", "ALT", "TNA", "LVN", "MLD"], ["Selon la carte", "République d'Altis", "Fédération de Tanoa", "République de Livonie", "République de Malden"], 0]] call _add;
["comspec_sse_id_aiPapers", "CHECKBOX", ["Papiers des IA", "Les IA ont des papiers selon leur camp (civils, insurgés, cibles). Les joueurs n'en ont que par l'attribut Eden, le kit ou le module Zeus."], true] call _add;
["comspec_sse_id_kitRespawn", "CHECKBOX", ["Rendre les papiers du kit au respawn", "Les joueurs qui ont un kit de papiers sur Athena (fiche membre) le retrouvent à chaque réapparition. Décoché : seulement à l'arrivée en mission."], true] call _add;
["comspec_sse_id_civComply", "SLIDER", ["Civils qui présentent leurs papiers (%)", "Part des civils qui tendent leurs papiers quand on les leur demande."], [0, 100, 90, 0]] call _add;
["comspec_sse_id_forgedRate", "SLIDER", ["Insurgés porteurs de faux papiers (%)", "Part des combattants ennemis qui ont de faux papiers. Les autres n'en ont pas."], [0, 100, 60, 0]] call _add;
["comspec_sse_id_tokenTime", "SLIDER", ["Durée d'une présentation (s)", "Temps pendant lequel un document présenté peut être contrôlé par celui à qui on l'a tendu."], [30, 600, 120, 0]] call _add;
["comspec_sse_id_autoTransmit", "CHECKBOX", ["Transmission automatique à Athena", "Chaque contrôle terminé part tout seul vers Athena. Décoché : bouton Transmettre de l'app Contrôle ID."], false] call _add;
["comspec_sse_id_athenaPack", "CHECKBOX", ["Documents et avis d'Athena", "Avec la liaison, les documents délivrés et les personnes signalées de votre communauté sont chargés au début de la mission."], true] call _add;
["comspec_sse_id_opKey", "EDITBOX", ["Opération Athena", "Facultatif : clé de l'opération Athena dont on charge les documents. Vide : documents de la carte seulement."], ""] call _add;
// IA réalistes et checkpoints IA (Lot 3).
["comspec_sse_id_aiFsm", "CHECKBOX", ["IA réalistes au contrôle", "Une IA contrôlée réagit selon son profil, son état et la situation : elle coopère, ment, devient nerveuse, refuse, s'enfuit, se rend, menace ou appelle des renforts. Décoché : réponses simples."], true] call _add;
["comspec_sse_id_aiAggro", "SLIDER", ["Agressivité des IA (%)", "Plus haut : les suspects contrôlés sont plus tendus, fuient, menacent et appellent des renforts plus souvent. 50 : réaliste."], [0, 100, 50, 0]] call _add;
["comspec_sse_id_cpRadius", "SLIDER", ["Distance des checkpoints (m)", "Rayon de la zone contrôlée par un checkpoint IA quand le module n'en impose pas."], [10, 100, 25, 0]] call _add;
["comspec_sse_id_cpDelay", "SLIDER", ["Délai pour présenter ses papiers (s)", "Temps laissé à un joueur arrêté par un checkpoint IA pour présenter ses papiers (menu ACE personnel › Mes papiers)."], [15, 60, 45, 0]] call _add;
["comspec_sse_id_cpAmbient", "CHECKBOX", ["Les checkpoints contrôlent aussi les IA", "Ambiance : de temps en temps, les gardes arrêtent une IA qui passe, lisent ses papiers, puis la laissent partir ou la retiennent."], true] call _add;
["comspec_sse_id_curfew", "CHECKBOX", ["Couvre-feu", "Les civils dehors sont plus nerveux quand on les contrôle. Le Zeus peut aussi l'imposer ou le lever en cours de mission."], false] call _add;
// Couvertures et infiltrés (Lot 4).
["comspec_sse_id_coverCaptive", "CHECKBOX", ["Infiltré captif tant que sa couverture tient", "Les ennemis ne prennent pas pour cible un infiltré (setCaptive) tant que sa couverture n'est pas brûlée."], true] call _add;
["comspec_sse_id_coverLimit", "SLIDER", ["Soupçons avant que la couverture saute", "Plus bas : une couverture se brûle plus vite (tenue, équipement, zone interdite, mauvaises réponses, contrôle raté)."], [30, 300, 100, 0]] call _add;
["comspec_sse_id_coverWitness", "SLIDER", ["Distance des témoins (m)", "Distance à laquelle un ennemi remarque une arme à la main chez un infiltré ; tenue et équipement se voient de moins loin."], [10, 150, 50, 0]] call _add;
["comspec_sse_id_questions", "CHECKBOX", ["Questions de légende", "Les checkpoints IA posent des questions (« D'où venez-vous ? »…) ; réponses au choix dans l'app Ma couverture ou le menu ACE personnel."], true] call _add;
["comspec_sse_id_questionTime", "SLIDER", ["Délai pour répondre (s)", "Temps pour répondre à la première question d'un contrôleur (10 s de plus par question suivante)."], [10, 60, 20, 0]] call _add;
// HVT et biométrie (Lot 5).
["comspec_sse_id_bio", "CHECKBOX", ["Comparaison du visage (N4)", "App Contrôle ID : photo du visage comparée à la base biométrique et à la photo du document (téléphone ou SEEK)."], true] call _add;
["comspec_sse_id_bioTime", "SLIDER", ["Durée de la comparaison du visage (s)", "Temps de la photo et de la recherche biométrique N4."], [4, 20, 8, 0]] call _add;
["comspec_sse_id_boloNotify", "CHECKBOX", ["Notifier les BOLO", "Chaque BOLO ou HVT diffusé par le Zeus s'affiche sur l'ATAK de tous les joueurs (sinon seulement dans l'onglet BOLO)."], true] call _add;
true
