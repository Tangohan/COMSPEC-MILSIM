# Prérequis automatiques possibles

Liste complète des conditions qu’ATHENA **mesure seul** pour délivrer (ou proposer) une qualification : d’où vient la donnée, comment elle est calculée et ce qu’elle ne sait pas faire. Toutes se règlent dans la règle d’obtention de la qualification (Référentiel › Qualifications › la qualification › Modifier, rubrique « Comment l’obtenir »), en mode **Automatique**. Le site vérifie les règles **toutes les heures**.

Rappel : une règle compte jusqu’à 8 blocs de 12 conditions ; chaque bloc est « toutes » ou « au moins une », et les blocs entre eux aussi. Les seuils datés acceptent une **fenêtre glissante** : depuis toujours, 30, 90, 180 ou 365 derniers jours.

## 1. Données mesurées en jeu et sur le site

| Condition | Source exacte | Comment c’est calculé | Périmètre possible | Fenêtre | Limites |
|---|---|---|---|---|---|
| Heures de jeu | Mod ATAK (temps de jeu relevé depuis son installation) | Total des secondes déclarées par le mod pour le membre, en heures | Tout le jeu, sur serveur, en Zeus | Non | Cumul depuis l’installation du mod ; compte tout Arma, pas seulement les séances de la communauté. |
| Heures de session validées | Site : sessions de jeu (présences validées par les responsables) | Somme du temps validé des sessions où la présence du membre est valide (hors exclusions RH) | Toutes les sessions ou une catégorie d’heures (ex. « Instruction ») | Oui | Dépend de la saisie et de la validation des sessions par l’encadrement. |
| Sessions de jeu validées | Site : sessions de jeu | Nombre de sessions où la présence est validée | — | Oui | Idem. |
| Heures de vol | Mod (temps aux commandes, appareil par appareil) + carnet de vol | Secondes de pilote relevées par le serveur Arma, plus les vols du carnet saisis par le pilote et contresignés | Total, hélicoptères, avions, une filière (transport, attaque, reconnaissance, avion à réaction, cargo, drone…), une famille (ex. AH-64, UH-60, CH-47) ou un appareil précis (classe Arma) | Non | Seule la place de pilote compte ; un appareil inconnu du site se rattache à une famille par son nom de classe ou à la main sur la page Cursus pilote. |
| Nombre de vols | Carnet de vol | Un vol par mission et par appareil relevé par le mod, plus les vols saisis et contresignés | Comme les heures de vol | Non | Une mission de plusieurs heures sur un même appareil compte un seul vol. |
| Heures de vol de nuit, aux instruments ou en patrouille | Carnet de vol, contresigné par un instructeur | Somme des heures notées dans la condition choisie | Nuit, IFR ou patrouille, en hélicoptère ou en avion | Non | Le mod ne détecte pas ces conditions : c’est le pilote qui les note, l’instructeur qui contresigne. |
| Missions jouées | Mod (relevés de mission envoyés par le serveur) | Nombre de lignes de relevé : une par mission (nom de mission et carte) et par jour | — | Oui | Rejouer la même mission le même jour compte une fois ; une mission qui passe minuit compte deux fois ; une mission sans serveur relié n’est relevée que si le téléphone du joueur l’envoie. |
| Présences aux événements | Site : calendrier (pointage à l’arrivée) | Nombre d’événements non annulés où le membre a été pointé | Tous, opérations, formations, événements, autres | Oui | Une inscription sans pointage ne compte pas. |
| Sauts en parachute | Mod (relevés de mission) | Un saut compte au passage en chute libre ou sous voile, si le départ se fait à plus de 150 m du sol ou directement dans un parachute | — | Oui | Ne distingue pas la chute libre de l’ouverture automatique ; un parachute ouvert au ras du sol compte aussi. |
| Distance parcourue à pied | Mod | Kilomètres à pied en mission (vitesses aberrantes écartées) | — | Oui | Ne mesure pas la charge portée ni le dénivelé. |
| Heures en véhicule terrestre | Mod | Temps à bord d’un véhicule terrestre, toutes places | — | Oui | Ne distingue pas conducteur, tireur et passager. |
| Heures sur l’eau | Mod | Temps à bord d’une embarcation, toutes places | — | Oui | Le temps sous l’eau (plongée) n’est pas relevé. |
| Heures en aéronef (toutes places) | Mod | Temps à bord d’un aéronef, pilote ou passager | — | Oui | Utile pour les chuteurs, équipages et chefs largueurs ; ne dit pas qui pilotait. |
| Comptes rendus de fin d’opération validés | Site : comptes rendus (AAR) | Nombre d’AAR rédigés par le membre et validés par un responsable | — | Oui | Un AAR non validé ne compte pas. |
| Ancienneté dans la communauté | Site : dossier du membre | Jours depuis la date d’engagement du dossier, sinon depuis l’arrivée dans la communauté | — | Non | Une date d’engagement mal saisie fausse le calcul. |
| Jours de jeu avec un équipement | Mod (équipement vu dans l’inventaire en jeu) | Nombre de jours distincts où l’objet ou la famille d’objets a été vu sur le membre | Un objet précis (classe) ou une famille : arme principale, lanceur, arme de poing, optique, silencieux, pointeur, bipied, munitions et grenades, tenue, gilet, sac, casque, lunettes, vision nocturne, jumelles et désignateurs, radio, matériel médical, autres | Oui | Porter n’est pas utiliser : un lance-roquettes dans le dos compte autant qu’un lance-roquettes tiré. |

## 2. Conditions vérifiées par le site (hors jeu)

| Condition | Source | Calcul | Limites |
|---|---|---|---|
| Formation Academy réussie | Academy (cours intégrés comme le JTAC, cours de la communauté) | Le membre a réussi le cours et son évaluation ; une attestation délivrée crée la qualification en mode Formation Academy | Une réussite ancienne compte, sauf si la qualification a une validité. |
| Qualification détenue | Référentiel des qualifications | Le membre détient une autre qualification valide (non expirée, non retirée) | Une qualification préalable expirée bloque. |
| Grade minimum | Réglage de la qualification | Le grade du dossier du membre est au moins celui réglé | Se règle dans la fiche, rubrique « Prérequis et affichage », pas dans les blocs de la règle. |
| Fonction tenue | Réglage de la qualification | Le membre tient la fonction (emploi) réglée | Même rubrique « Prérequis et affichage ». |

## 3. Données remontées mais pas (encore) utilisables comme seuil

- **Temps par rôle** (rôle tenu en jeu) : visible dans Métriques joueurs, pas encore critère d’obtention.
- **9-LINE, 5-LINE et demandes MEDEVAC** envoyés par l’app JTAC : conservés avec leur statut, consultables par l’instructeur ; à attester.
- **Heures d’écran du téléphone ATAK** et pas de la montre : affichés, sans critère.

## 4. Exemples de règles

**Brevet parachutiste (palier initial)**
- Bloc « toutes » : cours Academy « Saut à ouverture automatique » ; Sauts en parachute ≥ 5.
- Bloc « toutes » : Sauts à ouverture automatique (attestés) ≥ 5.

**Maintien du brevet parachutiste**
- Validité 12 mois ; Sauts en parachute ≥ 2 sur les 365 derniers jours.

**Pilote UH-60 qualifié**
- Bloc « toutes » : Heures de vol, famille UH-60, ≥ 15 h ; Nombre de vols, famille UH-60, ≥ 10 ; Heures de vol de nuit en hélicoptère ≥ 2 h.
- Bloc « au moins une » : détenir « Pilote d’hélicoptère — base » ; ou Heures de vol, hélicoptères, ≥ 40 h.

**Chef de groupe**
- Bloc « toutes » : Présences aux événements — Opérations ≥ 10 sur les 180 derniers jours ; Comptes rendus validés ≥ 3 ; Ancienneté ≥ 120 jours ; détenir « Chef d’équipe ».

**Radio / transmissions**
- Bloc « toutes » : Jours de jeu avec un équipement — Radios ≥ 15 ; cours Academy « Procédures radio ACP 125 ».

**Équipage de bateau**
- Bloc « toutes » : Heures sur l’eau ≥ 6 h sur les 180 derniers jours ; Missions jouées ≥ 8.

## 5. Bon à savoir

- Le site **ne retire jamais** seul une qualification : une baisse sous le seuil n’annule rien, la validité et le renouvellement servent à cela.
- Une qualification retirée, refusée ou suspendue n’est **jamais redonnée** automatiquement.
- La fiche de la qualification montre, pour chaque membre « En progression », la valeur actuelle et le seuil de chaque condition.
- Les seuils trouvés dans le texte du catalogue (« 5 sauts », « 200 heures de vol ») sont proposés dans l’encart « Prérequis détectés » ; le guide « Prérequis manuels » explique les conditions qui restent à attester.
