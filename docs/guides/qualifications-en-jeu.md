# Exploiter les qualifications en jeu (Arma 3)

Ce guide explique comment une communauté organise la formation et la délivrance des qualifications avec ATHENA et le mod ATAK : quelles données le jeu fait remonter, comment les combiner avec un cours Academy et la validation d’un instructeur, et comment faire vivre une qualification dans le temps.

Les qualifications des catalogues (États-Unis, France, Allemagne, Royaume-Uni, OTAN…) sont des **qualifications de jeu inspirées de brevets réels, sans valeur réelle**. Elles servent à structurer la progression des joueurs, pas à reproduire un cursus militaire à l’identique.

## 1. Le principe en trois couches

Une qualification bien construite combine, selon le besoin :

1. **Ce que le jeu mesure tout seul** : le mod ATAK et le site comptent les heures de jeu, les heures de vol par appareil, les missions jouées, les sauts en parachute, les présences, les comptes rendus validés, l’équipement vu en jeu. Voir le guide « Prérequis automatiques possibles ».
2. **Ce que l’Academy vérifie** : un cours (théorie, cas pratiques, évaluation chronométrée) réussi par le membre. Le cours JTAC de l’Academy en est l’exemple type.
3. **Ce que seul un instructeur peut juger** : un tir au stand, une épreuve physique, un saut en chute libre plutôt qu’à ouverture automatique, une plongée. L’instructeur l’atteste membre par membre. Voir le guide « Prérequis manuels (attestation) ».

Le site assemble ces conditions dans la **règle d’obtention** de la qualification : jusqu’à 8 blocs de 12 conditions, chaque bloc « toutes » ou « au moins une ». La règle se règle dans Référentiel › Qualifications › la qualification › Modifier, rubrique « Comment l’obtenir ».

## 2. Les quatre modes d’obtention

| Mode | Quand l’utiliser | Ce qui se passe |
|---|---|---|
| Délivrée par un responsable | Qualification ponctuelle, décision d’encadrement | Un instructeur ou un gestionnaire la délivre à la main, avec la date et un commentaire ou une preuve. |
| Formation Academy | La qualification sanctionne un cours | La réussite du cours crée la qualification (attestation de l’Academy). |
| Automatique | Les seuils sont mesurables | Le site vérifie la règle toutes les heures et délivre dès que tout est rempli, ou **propose** la délivrance (liste « À valider » de la fiche) si vous préférez garder la main. |
| Cursus pilote | Paliers de pilote | Paliers fondés sur les heures de vol, réglés sur la page Cursus pilote. |

Conseil : commencez en « Automatique » avec délivrance **proposée**. Les instructeurs voient arriver les membres qui remplissent la règle et valident d’un clic ; passez en délivrance directe quand la règle a fait ses preuves.

## 3. Importer et préparer la qualification

1. Academy › Catalogue › onglet Qualifications : ouvrez l’**assistant d’importation** (tout un pays, une catégorie, un niveau ou un émetteur) ou importez une fiche seule. Les qualifications préalables citées en prérequis peuvent être importées avec elle.
2. Ouvrez la qualification importée : l’encart **« Prérequis détectés »** propose, cochés, les prérequis lus dans le texte du catalogue (« 5 sauts », « 50 sauts en chute libre », « Basic Airborne Course », grade minimum). Relisez et validez : rien n’est appliqué sans vous.
3. Complétez la règle : seuils mesurés, cours Academy, qualification préalable, conditions attestées.
4. Réglez la **validité** (en mois) si la qualification doit être renouvelée.

## 4. Exemples concrets

### Brevet parachutiste (type Basic Airborne Course)

- **Bloc 1 (toutes)** : réussir un cours Academy « Saut à ouverture automatique » (procédures à bord, sortie, atterrissage, incidents) ; **5 sauts en parachute** mesurés par le mod.
- **Bloc 2 (toutes)** : condition attestée « Sauts à ouverture automatique », 5 sauts, validée par un chef largueur (le mod compte les sauts sans distinguer la chute libre de l’ouverture automatique).
- Mode : automatique, délivrance proposée.

En jeu : une session de saut avec un C-130 ou un hélicoptère au-dessus d’une zone de mise à terre, cinq passages, un chef largueur qui note les sorties propres.

### JTAC

- **Bloc 1 (toutes)** : réussir le cours Academy **« JTAC — Licence ATH-ACADEMY »** (six chapitres, évaluations chronométrées, validation instructeur) ; détenir l’« Observateur des feux interarmées (JFO) » si vous l’utilisez comme palier.
- **Bloc 2 (toutes)** : condition attestée « Contrôles terminaux en jeu » avec un libellé du type « 6 guidages 9-LINE / 5-LINE complets transmis par l’app JTAC », validée par un JTAC instructeur qui a relu les messages.
- Validité conseillée : 6 à 12 mois, avec renouvellement sur une nouvelle attestation de guidages.

Les 9-LINE, 5-LINE et demandes MEDEVAC envoyés depuis l’app JTAC de l’ATAK sont conservés par le site (avec leur statut, jusqu’au BDA), mais **ils ne sont pas encore comptés comme critère automatique** : l’instructeur les consulte et les atteste.

### Pilote AH-64 (ou toute autre famille d’appareils)

- **Bloc 1 (toutes)** : « Heures de vol », périmètre **famille AH-64**, 20 h ; « Heures de vol de nuit, en hélicoptère », 3 h (carnet contresigné) ; détenir la qualification de pilote d’hélicoptère de base de votre cursus.
- **Bloc 2 (au moins une)** : réussir le cours Academy d’emploi de l’appareil, ou attestation « Vol de contrôle réussi » par un instructeur pilote.
- Les heures viennent du mod (temps aux commandes, appareil par appareil) **et** du carnet de vol : le pilote saisit les vols manqués (serveur non relié, mission privée) et un instructeur les contresigne.

### Tireur d’élite

- **Bloc 1 (toutes)** : condition attestée **« Qualification de tir »** : « Stand : 8 impacts sur 10 à 600 m, de jour, avec le fusil de précision de dotation » ; « Jours de jeu avec un équipement », périmètre l’arme de précision (classe vue en jeu) ou la famille « Optiques », 10 jours.
- **Bloc 2 (toutes)** : réussir le cours Academy « Tireur d’élite » (balistique, estimation des distances, comptes rendus) ; 2 comptes rendus de fin d’opération validés comme binôme tireur-observateur.

Le mod ne relève pas encore les tirs et les impacts : la séance de stand reste une attestation.

### Infirmier de combat (TCCC / MEDEVAC)

- Cours Academy TCCC ; « Jours de jeu avec un équipement », famille « Matériel médical », 15 jours ; condition attestée « Épreuve pratique : MARCH-PAWS sur deux blessés et demande MEDEVAC 9-LINE en moins de 5 minutes ».

## 5. Scénarios de missions d’entraînement

| Scénario | Objectif | Ce qui remonte automatiquement | Ce que l’instructeur atteste |
|---|---|---|---|
| Stand de tir | Qualification de tir, tireur d’élite | Présence (pointage de l’événement), session validée, équipement porté | Score, distances, position, temps |
| Saut | Brevet parachutiste, chute libre, chef largueur | Sauts (départ ≥ 150 m), heures à bord d’un aéronef, mission jouée | Chute libre ou ouverture automatique, sortie, regroupement, atterrissage |
| CQB / maison de tir | Équipe d’assaut, breacher | Présence, session validée, temps de jeu sur serveur | Enchaînement des entrées, sécurité des angles, communication |
| MEDEVAC | Infirmier, équipage d’hélicoptère, JTAC | Heures de vol (pilotes), présence, matériel médical vu | Qualité de la demande 9-LINE, soins, délai d’évacuation |
| Navigation / marche | Reconnaissance, montagne | Kilomètres à pied, mission jouée | Respect de l’itinéraire, points de passage, charge portée |

Créez ces séances comme **événements « Formation »** du calendrier : le pointage à l’arrivée alimente le critère « Présences aux événements — Formations », et une **session de jeu** validée par les responsables alimente « Heures de session validées » (on peut filtrer par catégorie d’heures, par exemple « Instruction »).

## 6. Le rôle de l’instructeur

- **Concevoir la règle** avec l’encadrement : peu de conditions, toutes vérifiables.
- **Conduire la séance** en jeu et prendre des preuves : capture d’écran, enregistrement, compte rendu (AAR), relevé de l’app JTAC.
- **Attester** dans la fiche de la qualification (encart « Conditions attestées par un instructeur ») : la valeur (nombre de sauts, d’heures) et une note qui renvoie à la preuve.
- **Valider les propositions** (« À valider ») quand la délivrance n’est pas directe.
- **Retirer ou suspendre** une qualification si besoin : une qualification retirée, refusée ou suspendue n’est jamais redonnée automatiquement.

## 7. Renouvellement

Une qualification avec une validité expire à la date prévue. Quand elle arrive à échéance (bientôt expirée, expirée en délai de grâce ou expirée) **et que la règle est toujours remplie**, le membre passe « à renouveler » dans la fiche. Pour une règle réellement exigeante au renouvellement, utilisez les **fenêtres glissantes** (30, 90, 180 ou 365 derniers jours) : par exemple « 2 sauts sur les 365 derniers jours » pour garder le brevet actif, ou « 5 h de vol sur les 90 derniers jours » pour un pilote.

## 8. Bonnes pratiques

- Une qualification = une compétence lisible. Préférez trois paliers (initial, qualifié, instructeur) à une seule qualification surchargée.
- Mesurez ce qui est mesurable, attestez le reste, et dites-le dans la description de la qualification.
- Gardez les preuves au même endroit (AAR de la séance, dossier partagé) et citez-les dans la note d’attestation.
- Vérifiez de temps en temps l’encart « En progression » : il montre qui est proche de la qualification et ce qui lui manque.
