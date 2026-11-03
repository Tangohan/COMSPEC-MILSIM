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
true
