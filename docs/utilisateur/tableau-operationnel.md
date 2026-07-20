# Mur & tableau opérationnel

## Présentation générale

Le mur opérationnel est le tableau de situation de votre communauté : l'équivalent numérique du panneau d'affichage de PC (poste de commandement) sur lequel l'état-major punaise permanences, consignes, ordres de mission et informations flash. Il donne en un coup d'œil la photographie de « ce qui se passe et ce qu'il faut savoir » à un instant donné, sans avoir à fouiller dans le forum, les messages ou les fils de discussion épars.

Prenons un exemple concret. La communauté Athena prépare un week-end d'exercice : une permanence radio doit être tenue le vendredi soir, une mission d'infiltration est programmée le samedi matin, une formation « chef de groupe » se déroule en parallèle, et une manifestation publique (journée portes ouvertes) est annoncée pour le dimanche. Sans outil centralisé, chaque information circulerait par un canal différent — un message ici, un post là — avec le risque que certains membres passent à côté d'une consigne importante. Le mur opérationnel réunit tout cela dans un seul écran, organisé par colonnes, avec un niveau de tension (la « posture ») affiché en permanence en haut de la page.

L'outil repose sur deux logiques distinctes mais complémentaires, qui structurent l'ensemble de cette page :

- **Piloter** : préparer une fiche, la compléter, la faire valider, la publier, suivre son exécution puis la clôturer. C'est le travail de l'état-major ou de toute personne habilitée à gérer le mur.
- **Consulter** : lire ce qui a été publié, pour se situer et savoir quoi faire. C'est ce que voit un membre ordinaire, sans aucune action de gestion possible.

Cette séparation est volontaire : elle évite qu'une fiche mal préparée, incomplète ou encore en discussion n'apparaisse par erreur devant tous les membres. Rien n'atteint le mur des membres tant qu'une personne habilitée ne l'a pas explicitement publiée — même si cette fiche existe déjà, complète, dans le pilotage depuis plusieurs jours.

Le mur opérationnel s'articule avec d'autres modules du portail : une entrée du tableau peut être créée automatiquement à partir d'un événement d'agenda, d'une formation ou d'une mission de coopération inter-unités, pour éviter de ressaisir deux fois la même information (voir la section *La publication liée* plus bas). Il s'appuie également sur l'annuaire des personnels et des unités pour proposer des affectations, sur les qualifications enregistrées dans les dossiers opérateurs pour signaler des écarts, et sur la gestion des rôles de l'organisation pour déterminer qui peut faire quoi.

À la différence d'un simple agenda partagé, le mur opérationnel distingue explicitement ce qui est encore en préparation de ce qui est officiellement diffusé, applique des niveaux de sensibilité et des audiences précises, garde la mémoire des amendements successifs d'une même action, et conditionne la clôture d'une fiche à la validation de ses points de contrôle. C'est cette rigueur supplémentaire, calquée sur les usages réels d'un poste de commandement, qui en fait un outil de suivi et pas seulement un tableau d'affichage.

Cette page couvre, dans l'ordre : le fonctionnement général de l'outil (les deux écrans, la posture, les types de fiches, le contenu détaillé d'une fiche, le cycle de vie, la mise à jour opérationnelle, les points de contrôle, les rubriques et modèles, l'historique, la publication liée), son utilisation concrète au quotidien (parcours type, scénario complet, lecture d'une carte, filtres, audiences), puis les cas particuliers à connaître (droits exacts, limites, bonnes pratiques, lexique et dépannage).

---

## Fonctionnement

### Le mur et le tableau : deux vues d'un même outil

Concrètement, deux écrans distincts donnent accès au même ensemble de données, mais avec des droits et des objectifs différents :

| Écran | Où le trouver | Ce qu'il montre | Qui y accède |
|---|---|---|---|
| **Mur opérationnel** | Menu du portail · « Mur opérationnel » | Uniquement les fiches déjà publiées, filtrées selon la période affichée, l'unité et le niveau de sensibilité du visiteur | Tout membre disposant du droit de consultation |
| **Tableau opérationnel** (pilotage) | Back-office · « Pilotage du mur » | Toutes les fiches : brouillons, publiées, retirées ; outils de création, de validation, de suivi et de gestion | Personnes habilitées à créer et modifier les entrées |

Un lien public en lecture seule peut également être activé par le pilotage pour diffuser le mur à l'extérieur du portail (par exemple pour un partenaire, un site vitrine, ou un affichage sur écran dédié pendant un exercice). Ce lien ne donne jamais accès aux outils de gestion : il reproduit uniquement l'affichage du mur, avec la posture et les fiches actives du moment, sans nécessiter la moindre identification de la personne qui le consulte. Il peut être régénéré à tout moment (l'ancien lien cesse alors immédiatement de fonctionner) ou désactivé complètement depuis le pilotage.

Retenez la règle simple qui gouverne tout le reste de cette page : le pilotage prépare, le mur montre. Si vous voyez le mur mais pas les outils de gestion (bouton **Ouvrir le pilotage**, actions **Approuver**, **Clôturer**, etc.), c'est que votre rôle est en lecture, ce qui est un fonctionnement normal et non une anomalie.

### La posture de la communauté

En haut du mur comme du tableau de pilotage, un badge **Posture** affiche le niveau de tension global retenu par l'état-major pour l'ensemble de la communauté. Il ne s'agit pas d'un indicateur calculé automatiquement à partir des fiches en cours : c'est un choix humain, changé volontairement par une personne habilitée, qui donne le ton de lecture de tout le mur.

| Posture | Signification | Comportement attendu des membres |
|---|---|---|
| **Normale** | Fonctionnement courant, rien de particulier à signaler | Suivre le rythme habituel |
| **Vigilance** | Attention renforcée demandée ; un point sensible est en cours de traitement | Lire les consignes en priorité, rester disponible |
| **Alerte** | Situation dégradée ou événement important en cours | Suivre les flashs et les missions actives, limiter les initiatives non coordonnées |
| **Crise** | Mode dégradé généralisé | Se concentrer sur l'essentiel, suivre strictement les consignes de l'encadrement |

Exemple : pendant un exercice de simulation d'attaque sur la base Athena, l'état-major bascule la posture sur **Alerte** au moment du top départ, publie plusieurs flashs successifs au fil du scénario, puis repasse en **Normale** dès le débriefing terminé. Les membres voient ce changement en temps réel sur le mur, sans qu'aucun message supplémentaire ne soit nécessaire pour l'annoncer.

Seul le pilotage peut changer la posture, via une liste déroulante suivie d'un bouton **Appliquer**. Les membres consultent le niveau affiché mais ne peuvent jamais le modifier depuis le mur, quel que soit leur rôle par ailleurs.

### Les types de fiches

Chaque fiche du tableau appartient à un type, choisi à la création, qui détermine dans quelle colonne (ou zone) du mur elle apparaîtra. Ce choix structure la lecture : un membre qui cherche une permanence ne va pas fouiller dans les mêmes cases qu'un membre qui cherche une mission.

La **permanence** décrit une astreinte, une veille ou un poste fixe tenu à un moment donné : une permanence radio le vendredi soir, un poste de garde pendant un exercice, une veille administrative hebdomadaire. Les permanences sont en plus regroupées automatiquement selon leur échéance — **Aujourd'hui**, **En cours**, **À venir** — pour que le lecteur retrouve immédiatement celle qui le concerne sans avoir à comparer des dates lui-même.

L'**information pratique** rassemble ce qui doit rester visible durablement sans être une action à proprement parler : horaires d'ouverture du local, contact du référent logistique, consigne de sécurité permanente, procédure à suivre en cas d'incident.

La **manifestation** couvre un dispositif public ou un événement structuré nécessitant une organisation dédiée : une journée portes ouvertes, une présence sur un salon, un défilé commémoratif encadré par la communauté.

La **mission** décrit une opération avec des objectifs et des moyens engagés : une infiltration nocturne, un exercice de reconnaissance, une opération de sécurisation de zone lors d'un scénario milsim. C'est le type le plus riche en informations de commandement (chef, adjoint, effectifs, zone d'opération, fenêtre d'action) — voir la section suivante pour le détail de ces blocs.

La **tâche** correspond à une action interne de coordination qui n'a pas la dimension d'une mission complète : préparer le matériel radio avant l'exercice, mettre à jour l'ORBAT, relancer les inscriptions à une formation.

La **formation** signale une séance pédagogique cadrée dans le temps : un stage chef de groupe, une session d'initiation aux communications tactiques, une remise à niveau sur les gestes de premiers secours.

Le **flash information** est le format le plus court et le plus visible : un message urgent affiché en bandeau, destiné à être lu immédiatement — changement d'horaire de dernière minute, alerte météo affectant un exercice extérieur, information de sécurité à diffuser sans délai. Ce type de fiche propose volontairement un formulaire allégé : l'essentiel, la période de validité et le public visé, sans les blocs de commandement réservés aux missions.

Les missions, tâches et formations sont regroupées visuellement sur le mur dans une même zone « Missions et activités », tandis que permanences, informations pratiques, manifestations et flashs disposent chacun de leur propre colonne. Le tableau ci-dessous récapitule cette organisation :

| Type de fiche | Colonne / zone sur le mur | Exemple Athena |
|---|---|---|
| Permanence | Permanences (regroupées Aujourd'hui / En cours / À venir) | Permanence radio du vendredi soir |
| Information pratique | Infos pratiques | Contact du référent logistique |
| Manifestation | Manifestations | Journée portes ouvertes |
| Mission | Missions et activités | Infiltration nocturne de reconnaissance |
| Tâche | Missions et activités | Mise à jour de l'ORBAT avant l'exercice |
| Formation | Missions et activités | Stage chef de groupe |
| Flash information | Flash infos (bandeau) | Changement d'horaire de dernière minute |

### Le contenu d'une fiche : commandement, terrain, personnel et moyens

Au-delà du titre, de la description et des dates, une fiche de type mission, permanence, manifestation, tâche ou formation peut être enrichie de plusieurs blocs facultatifs mais très utiles pour un suivi opérationnel sérieux. Ces blocs ne sont pas proposés pour les flashs, dont la vocation reste d'être lus en quelques secondes.

**Responsabilités.** Trois rôles peuvent être désignés parmi les membres de la communauté : un **chef désigné**, un **adjoint**, et un **remplaçant**. Une case à cocher permet d'**activer le remplacement automatiquement lorsque les conditions sont réunies** — utile si l'on sait par avance que le chef titulaire pourrait être indisponible et que son remplaçant doit prendre le relais sans intervention manuelle supplémentaire. Un champ libre de **chaîne de commandement** permet de préciser en texte la ligne hiérarchique complète si elle dépasse ces trois rôles, et un champ **responsabilité engagée** documente qui répond de l'action en cas de compte rendu ou d'incident.

**Terrain & cadre.** Cette section rassemble les informations de localisation et de contexte : une **zone d'intervention** en texte libre, un **lien carte** (vers un plan ou un outil cartographique externe), des coordonnées de **latitude** et **longitude** pour un repère précis, une **référence dossier** pour relier la fiche à un dossier administratif existant, un champ **contraintes** (cadre, sécurité, ou toute limite à respecter), ainsi qu'un **début** et une **fin de fenêtre d'action** — la période exacte pendant laquelle l'action peut légitimement se dérouler. Le pilotage surveille automatiquement la cohérence de cette fenêtre : si la fin est renseignée avant le début, l'incohérence apparaît comme alerte dans le pilotage, à corriger avant publication.

**Personnels affectés.** Une liste de lignes permet d'ajouter chaque membre engagé sur l'action, avec pour chacun un **rôle sur la ligne** (texte libre, par exemple « OPJ », « radio », « chef d'équipe »), et une case **responsable de ligne** pour distinguer le meneur de l'équipe. Si des compétences obligatoires ont été associées à la fiche et qu'une personne affectée n'a pas (ou plus) la qualification correspondante à jour dans son dossier opérateur, une alerte de qualification manquante ou expirée apparaît dans le pilotage, nominativement, pour permettre une correction avant le déroulement réel de l'action.

**Moyens.** Une liste similaire permet de recenser le matériel engagé : un **type**, un **libellé**, une **référence**, et un **état** parmi Disponible, Engagé ou Indisponible. C'est un inventaire simple, propre à la fiche, qui aide à visualiser d'un coup d'œil ce qui est mobilisé pour l'action en cours — un véhicule, une radio, un lot de matériel médical.

**Consignes structurées.** Plutôt qu'un unique bloc de texte, les consignes peuvent être découpées en plusieurs entrées typées : **Consigne**, **Information**, **Restriction** ou **Brief**, chacune pouvant être **épinglée** pour rester mise en avant sur la fiche. Cela permet par exemple de séparer clairement une restriction de sécurité d'une simple information de contexte.

Exemple complet : la mission « Sécurisation de la zone nord — exercice de printemps » désigne un chef d'équipe, un adjoint et un remplaçant avec activation automatique cochée (au cas où le chef serait retenu ailleurs), précise la zone d'intervention et la fenêtre d'action de 21h à 23h, liste trois personnels affectés dont un radio, recense deux moyens (un véhicule et un lot radio, tous deux à l'état « Engagé »), et porte deux consignes : une **Restriction** épinglée (« Zone interdite au public pendant l'exercice ») et un **Brief** non épinglé résumant le scénario.

### Le cycle de vie d'une fiche

Une fiche du tableau opérationnel traverse en général cinq grandes phases, de sa création jusqu'à sa clôture. Comprendre cet enchaînement permet d'anticiper ce qu'il reste à faire à chaque étape et d'éviter les fiches publiées trop tôt ou oubliées en brouillon.

1. **Création.** Une fiche naît toujours en brouillon, invisible sur le mur des membres. Elle peut être créée de plusieurs façons : depuis le pilotage via **Nouvelle entrée** (en choisissant directement un type), via **Création rapide**, à partir d'un **modèle** existant, ou automatiquement depuis un événement, une formation ou une mission de coopération grâce au bouton **Publier au mur opérationnel** présent sur ces écrans. Quelle que soit l'origine, la fiche reste à ce stade une ébauche que seul le pilotage peut voir, sous l'onglet **Brouillons**, dont le nombre est affiché en permanence sur un bouton compteur.

2. **Préparation.** C'est l'étape la plus longue et la plus importante : on renseigne un intitulé clair (idéal : verbe + objet + échéance, par exemple « Tenir permanence radio — vendredi 20 h »), on choisit une rubrique de classement et des mots-clés pour faciliter les recherches futures, on fixe les dates de validité, on définit la priorité (Faible, Normale, Élevée ou Critique), on précise qui doit voir la fiche (toute la communauté, une unité précise, certains emplois, ou soi-même en brouillon personnel) et son niveau de sensibilité. Selon le type de fiche, on complète aussi les blocs décrits plus haut : responsabilités, terrain et cadre, personnels affectés, moyens, consignes structurées, et les points de contrôle à valider avant la fin de l'action. Chaque enregistrement se fait avec le bouton **Enregistrer**, sans que la fiche ne quitte son statut de brouillon.

3. **Publication.** Une fois la fiche prête, elle doit être explicitement mise à la disposition des membres. Trois actions sont possibles sur la carte ou la fiche : **Approuver** valide le contenu et le met en ligne sur le mur ; **Mettre en ligne** publie directement une fiche (depuis un brouillon ou après une approbation) ; **Refuser** annule la mise en ligne, la fiche restant tracée dans l'historique du pilotage mais n'apparaissant jamais sur le mur des membres. Tant qu'aucune de ces actions n'a été effectuée, la fiche demeure invisible pour les membres, même si elle est parfaitement complète et prête depuis plusieurs jours.

4. **Suivi.** Une fiche publiée continue de vivre : son statut opérationnel évolue au fil de l'exécution réelle — **Planifié**, **En cours**, **Suspendu**, **Terminé** ou **Annulé** — et, pour les missions les plus structurées, une phase peut être affichée (Phase 1, 2 ou 3) pour suivre un déroulement en plusieurs temps. C'est également durant cette étape que les points de contrôle sont cochés au fur et à mesure de leur réalisation, et que des mises à jour opérationnelles (FRAGO) peuvent être générées si la situation évolue en cours de route.

5. **Clôture.** Le bouton **Clôturer** fait passer la fiche au statut **Terminé**. Une règle de sécurité s'applique systématiquement : si un ou plusieurs points de contrôle obligatoires n'ont pas encore été validés, la clôture est refusée et un message l'indique clairement. Il faut alors revenir sur la fiche, valider les points manquants, puis relancer la clôture. Une fois clôturée ou devenue obsolète, la fiche peut être retirée du mur (voir la section *Retrait du mur* implicite dans les bonnes pratiques) tout en restant consultable dans l'historique du pilotage.

Au-delà de ces cinq phases, deux actions de réutilisation sont disponibles à tout moment sur une fiche existante : **Copier en brouillon**, qui clone intégralement son contenu pour préparer un scénario récurrent sans repartir de zéro, et **Enregistrer comme modèle**, qui capture sa structure (hors dates et rattachement à un événement) pour la réutiliser plus tard via les modèles. À l'inverse, une fiche devenue inutile (erreur de saisie, doublon, action annulée avant même sa publication) peut simplement être **Retirée du mur**, sur confirmation, avec un motif optionnel conservé dans le journal ; elle disparaît alors du portail tout en restant consultable en pilotage via le filtre **Retirées du mur**, et il devient possible de recréer une nouvelle fiche liée à la même source si besoin.

### La mise à jour opérationnelle (FRAGO)

Sur une fiche déjà publiée ou en cours d'exécution, le bouton **Mise à jour opérationnelle** crée une nouvelle fiche dérivée de la fiche d'origine, en reprenant sa terminologie militaire du monde réel : un FRAGO (« fragmentary order ») est un ordre complémentaire qui vient amender ou préciser un ordre initial sans le réécrire entièrement.

Concrètement, la nouvelle fiche reprend le titre de l'original en le préfixant de `[FRAGO]`, recopie le contenu, la chaîne de commandement, le terrain, les personnels affectés, les moyens et les points de contrôle de la fiche parente, mais remet tous les points de contrôle à l'état « à faire ». Chaque mise à jour est numérotée dans l'ordre (première version, deuxième version, etc.), ce qui permet de retracer l'historique des amendements successifs d'une même action. Cela permet de matérialiser un changement de plan — par exemple un créneau horaire décalé, une zone d'opération modifiée en cours de mission, ou un effectif renforcé — sans perdre la trace de la fiche d'origine ni de son historique.

Exemple : la mission « Sécurisation de la zone nord » publiée le matin doit être amendée après un renseignement de dernière minute annonçant l'arrivée d'un groupe adverse par l'est. L'état-major génère une mise à jour opérationnelle « [FRAGO] Sécurisation de la zone nord », ajuste la zone d'intervention et les effectifs affectés, et republie la fiche dérivée sans toucher à la fiche initiale, qui reste consultable comme trace de la décision d'origine.

Utilisez cette fonctionnalité pour tout amendement significatif plutôt que de modifier discrètement une fiche déjà en cours : elle garde une mémoire claire de « qui a décidé quoi, à partir de quelle version, et à quel moment ».

### Les points de contrôle

Les points de contrôle forment une liste de vérifications associée à une fiche, affichée sous la forme **Points de contrôle : X / Y** (nombre de points faits sur nombre de points obligatoires). Ils servent à s'assurer que les prérequis indispensables à une mission, une permanence ou une manifestation sont bien réunis avant de considérer l'action comme terminée : matériel vérifié, effectif au complet, briefing de sécurité effectué, autorisation obtenue, compte rendu rédigé.

Sur la fiche, chaque point peut être marqué **Valider** ou remis à **Annuler** individuellement, sans limite d'aller-retour. La règle métier qui protège la rigueur du suivi est stricte : il est impossible de clôturer une fiche tant qu'un point de contrôle obligatoire n'a pas été validé. Si la clôture échoue, la première chose à vérifier est donc la liste des points de contrôle encore ouverts sur la fiche concernée.

Exemple : la mission « Exercice de nuit — zone d'entraînement » impose deux points de contrôle obligatoires, « Briefing sécurité effectué » et « Effectif complet au point de rassemblement ». Le chef de mission ne pourra clôturer la fiche qu'après avoir coché ces deux cases, garantissant que le suivi administratif de l'exercice a été rigoureusement respecté et pas seulement déclaré verbalement en fin de soirée.

### Rubriques et modèles

**Les rubriques** sont des catégories de classement libres, définies par la communauté elle-même (par exemple « Opérations judiciaires », « Instruction », « Sécurité publique »), chacune associée à une couleur qui aide à repérer visuellement les fiches sur le mur. On les crée depuis le pilotage ou directement depuis le formulaire d'une fiche, via **Ajouter une rubrique de classement** (un nom et une couleur suffisent). Une fois créées, elles sont proposées dans une liste déroulante appelée **Catégorie** sur chaque fiche, ce qui permet ensuite de filtrer et de colorer le mur selon ces rubriques.

**Les modèles** évitent de ressaisir une structure de fiche récurrente. La section **Modèles et création guidée** du pilotage propose trois usages :

1. Choisir un modèle existant dans la liste puis cliquer sur **Générer une fiche brouillon** : une nouvelle fiche est créée avec le contenu du modèle, prête à être complétée avec des dates et un rattachement précis.
2. Créer un **Nouveau modèle (squelette vide)** en renseignant un nom, une famille de modèle, un type de fiche, un intitulé par défaut et une consigne type.
3. Depuis une fiche déjà existante, utiliser **Enregistrer comme modèle** pour transformer son contenu en modèle réutilisable — les dates et le rattachement à un événement, une mission ou une formation ne sont volontairement pas repris, puisqu'ils sont propres à chaque situation.

Les modèles sont classés par famille, pour aider à les retrouver rapidement selon le type d'usage récurrent d'une communauté milsim :

| Famille | Usage typique |
|---|---|
| **Permanence judiciaire** | Astreintes et veilles à caractère judiciaire ou d'astreinte réglementée |
| **Mission judiciaire** | Opérations structurées avec un objet judiciaire ou procédural |
| **Instruction ou formation** | Séances pédagogiques, stages, remises à niveau |
| **Dispositif sécurité** | Manifestations, présences publiques, dispositifs statiques |
| **Exercice** | Scénarios d'entraînement et exercices tactiques |
| **Sur mesure** | Tout ce qui ne rentre pas dans les familles précédentes |

Si aucun modèle n'a encore été créé par la communauté, trois exemples sont automatiquement proposés pour démarrer : une **permanence de formation** (accueil et veille pédagogique pendant une session), une **mission type — ordre général** avec structure d'ordre complète (contexte, objectifs, effectifs, moyens, fenêtre d'action), et un **flash information (court)**, dont le contenu invite à préciser la période de validité une fois utilisé. Ils servent de point de départ et peuvent être adaptés ou remplacés librement au fil du temps.

### Historique et traçabilité

Chaque action effectuée sur une fiche est enregistrée dans son historique, consultable depuis le pilotage : création, modification, validation ou refus, changement de statut opérationnel, retrait du mur, affectation de personnel, application d'un modèle. Cet historique répond à une préoccupation courante en milieu milsim comme dans une organisation réelle : pouvoir reconstituer, après coup, qui a décidé quoi et à quel moment, notamment lors d'un retour d'expérience après un exercice ou un incident.

Deux mécanismes renforcent cette traçabilité :

- Un **commentaire de validation** peut être ajouté lors d'une approbation ou d'un refus, pour expliquer la décision (par exemple : « Refusé — effectif insuffisant confirmé, à reprogrammer »).
- Chaque **mise à jour opérationnelle** (FRAGO) porte un numéro de version qui s'incrémente à chaque nouvel amendement d'une même fiche d'origine, ce qui permet de reconstituer, dans l'ordre, l'enchaînement complet des décisions prises sur une action donnée.

Ce même historique alimente le **journal récent** et le flux d'**activité récente** décrits plus loin dans la section *Filtres et modes d'affichage*.

### La publication liée

Certaines fiches ne sont pas créées depuis le pilotage lui-même, mais depuis un autre écran du portail : la page des événements, celle des formations, ou celle des missions de coopération inter-unités. Lorsque le droit de modification du mur est accordé, un bouton **Publier au mur opérationnel** (parfois affiché **Publier au mur**) apparaît sur ces écrans.

| Source | Ce qui est repris automatiquement | Type de fiche généré |
|---|---|---|
| **Événement d'agenda** | Titre, description, dates de début et de fin de l'événement | Mission (par défaut) |
| **Formation** | Titre et résumé du parcours de formation | Formation |
| **Mission de coopération inter-unités** | Titre et résumé de la mission | Mission |

Le mécanisme est simple : un clic sur le bouton crée automatiquement un brouillon lié à la source d'origine, en reprenant les informations disponibles listées ci-dessus. Il reste ensuite à ouvrir cette fiche, la compléter (public visé, sensibilité, effectifs, moyens...) puis à la publier avec **Approuver** ou **Mettre en ligne**, exactement comme pour une fiche créée manuellement.

Un garde-fou évite les doublons : si une fiche non retirée du mur est déjà rattachée à la même source, le portail redirige automatiquement vers cette fiche existante plutôt que d'en créer une seconde. En revanche, une fois qu'une fiche liée a été retirée du mur, il devient à nouveau possible de créer une nouvelle fiche liée à la même source — utile par exemple si une formation annulée puis reprogrammée doit être republiée depuis zéro.

Exemple : la communauté programme un événement d'agenda « Rassemblement inter-unités — printemps ». Depuis la fiche de l'événement, l'organisateur clique sur **Publier au mur opérationnel** : un brouillon « Rassemblement inter-unités — printemps » apparaît dans le pilotage, avec les dates de l'événement déjà renseignées. Il ne reste qu'à préciser le public visé et à publier.

---

## Utilisation

### Parcours type — état-major (pilotage)

1. Ouvrir le **Tableau opérationnel** depuis le back-office.
2. Vérifier la **posture** affichée et l'ajuster si la situation le justifie.
3. Créer une fiche : librement, à partir d'un modèle, ou depuis un événement / une formation / une mission de coopération.
4. Compléter le contenu : responsabilités, terrain, personnels affectés, moyens, consignes, puis définir le **public visé** et le **niveau de sensibilité**.
5. **Approuver** ou **Mettre en ligne** la fiche pour la rendre visible sur le mur.
6. Suivre le statut opérationnel et la phase en cours ; cocher les points de contrôle au fil de l'exécution.
7. **Clôturer** la fiche une fois l'action terminée, ou la **Retirer du mur** si elle n'a plus lieu d'être diffusée ; générer au besoin une **mise à jour opérationnelle**.

Ce parcours peut se répéter plusieurs fois par semaine dans une communauté active : une permanence hebdomadaire suit un cycle très court (créer, publier, clôturer), tandis qu'une mission d'ampleur peut rester plusieurs jours en phase de suivi avec plusieurs mises à jour opérationnelles successives.

### Scénario complet : du renseignement à la clôture

Pour illustrer l'enchaînement réel des fonctionnalités, suivons une mission complète au sein de la communauté Athena.

Le jeudi, l'état-major apprend qu'un exercice de sécurisation de zone doit être organisé pour le samedi suivant. Plutôt que de partir d'une fiche vierge, il ouvre le pilotage et choisit le modèle **Mission type — ordre général**, ce qui génère immédiatement un brouillon avec la structure attendue : contexte, objectifs, effectifs, moyens et fenêtre d'action déjà esquissés. Le brouillon apparaît dans le compteur **Brouillons** du tableau.

L'officier en charge complète la fiche : intitulé « Sécurisation de la zone nord — exercice de printemps », rubrique « Exercice », mots-clés « nuit, infiltration, sécurisation », priorité **Élevée**, portée de diffusion limitée à l'unité concernée, niveau de sensibilité « Diffusion interne large ». Il désigne un chef, un adjoint et un remplaçant avec activation automatique, précise la zone d'intervention et la fenêtre d'action de 21h à 23h, affecte trois personnels avec leurs rôles respectifs, recense les moyens engagés (un véhicule, un lot radio) et ajoute deux points de contrôle obligatoires : « Briefing sécurité effectué » et « Effectif complet au point de rassemblement ». Il enregistre régulièrement sans que la fiche ne quitte son statut de brouillon.

Le vendredi, la fiche est relue puis **Approuvée** : elle apparaît désormais sur le mur opérationnel, visible par l'unité concernée, avec la mention de statut opérationnel **Planifié**.

Le samedi soir, juste avant le début de l'exercice, l'état-major reçoit un renseignement de dernière minute : un groupe adverse pourrait intervenir par l'est. Plutôt que de modifier discrètement la fiche en cours, il utilise **Mise à jour opérationnelle**, ce qui génère une fiche « [FRAGO] Sécurisation de la zone nord » reprenant tout le contenu, avec les points de contrôle remis à zéro. Il ajuste la zone d'intervention sur cette nouvelle version, publie le FRAGO, et bascule au passage la posture de la communauté sur **Vigilance** pour la durée de l'exercice.

Pendant l'exercice, le chef de mission fait évoluer le statut opérationnel vers **En cours**, valide au fur et à mesure les deux points de contrôle obligatoires. À la fin de l'action, il clique sur **Clôturer** : la clôture est acceptée puisque les deux points de contrôle sont désormais validés. La posture de la communauté repasse en **Normale**, et la fiche d'origine comme sa mise à jour restent consultables dans l'historique du pilotage pour le retour d'expérience.

### Autres cas d'usage courants

Le scénario ci-dessus détaille une mission complète, mais les autres types de fiches suivent le même cycle de vie en plus simple, avec moins de blocs à compléter.

Pour une **permanence** récurrente, comme la veille radio du vendredi soir, la démarche la plus efficace consiste à créer une première fiche complète, puis à utiliser **Copier en brouillon** chaque semaine plutôt que de tout ressaisir : seules les dates et, si nécessaire, les personnels affectés changent d'une semaine à l'autre.

Pour une **information pratique** durable, comme les horaires d'ouverture du local ou le contact du référent logistique, l'essentiel tient dans le titre et la description ; les blocs de commandement, de terrain et de moyens sont rarement utiles et peuvent être laissés vides.

Pour une **manifestation**, comme une journée portes ouvertes, le bloc *Terrain & cadre* prend une importance particulière (zone d'intervention, lien carte, contraintes de sécurité) puisque le public concerné dépasse souvent le cadre habituel de la communauté.

Pour un **flash information**, comme une alerte météo affectant un exercice extérieur, il suffit de renseigner un titre percutant, une période de validité courte et, si besoin, un bloc de consigne épinglé ; la publication doit alors être immédiate pour que le message garde son utilité.

### Ce qu'affiche une carte sur le mur

Pour lire efficacement le mur, il est utile de savoir ce que représente chaque information visible sur une carte de fiche, qu'on soit membre ou membre du pilotage :

- Le **titre** de la fiche, accompagné d'un badge indiquant son type (permanence, mission, flash...).
- La **priorité**, généralement mise en évidence par une couleur ou un repère visuel pour les niveaux Élevée et Critique.
- Les **dates de validité**, qui déterminent si la fiche est affichée dans la période consultée.
- Le **statut opérationnel** en cours (Planifié, En cours, Suspendu, Terminé, Annulé) et, le cas échéant, la **phase** active.
- Le compteur **Points de contrôle : X / Y**, pour évaluer d'un regard si l'action est prête à être clôturée.
- Une **référence dossier**, lorsque la fiche est reliée à un dossier administratif existant.
- Dans le pilotage uniquement : les actions disponibles selon le statut de la fiche (**Copier en brouillon**, **Approuver**, **Refuser**, **Mettre en ligne**, **Mise à jour opérationnelle**, **Clôturer**), absentes sur le mur des membres qui reste strictement un écran de lecture.

### Parcours type — membre (consultation)

1. Ouvrir le **Mur opérationnel** depuis le menu du portail.
2. Lire en priorité la **posture** affichée et les **flashs information**, qui portent l'information la plus urgente.
3. Parcourir les colonnes utiles selon le besoin : permanences du jour, informations pratiques, manifestations, missions et activités.
4. En cas de besoin de précision ou de correction sur une fiche, contacter l'état-major par les canaux habituels de la communauté (ou ouvrir directement le pilotage si l'on y est également habilité).

Un membre ne dispose d'aucune action de création, de validation ou de modification depuis le mur : cet écran est strictement un écran de lecture, ce qui garantit que l'information affichée a toujours été validée par une personne habilitée avant d'être diffusée.

### Filtres et modes d'affichage

Le pilotage propose deux niveaux de filtrage complémentaires, l'un porté par le serveur (filtres de liste), l'autre appliqué instantanément dans le navigateur (filtres rapides).

**Les filtres de liste** déterminent l'ensemble de fiches chargé initialement :

- **Publication** : Publiées (actives), Brouillons, Retirées du mur, ou Toutes — pour basculer rapidement entre le travail en cours et l'historique.
- **Période** : une date de début et une date de fin, pour ne charger que les fiches concernant une fenêtre de temps donnée.
- **Type de fiche**, **statut opérationnel** et **étiquette**, pour cibler directement une catégorie de travail.

**Les filtres rapides** s'appliquent ensuite sur la page déjà chargée, sans recharger l'écran : recherche par texte libre, type, statut opérationnel, priorité, étiquette, et surtout trois **modes d'affichage** :

| Mode | Effet |
|---|---|
| **Vue complète** | Affiche toutes les cartes correspondant aux filtres actifs |
| **Vue synthèse crise** | Masque tout sauf les fiches critiques et celles déjà en cours d'exécution |
| **Vue briefing** | Réduit la présentation des cartes pour permettre une lecture rapide avant un point de situation |

Un compteur (« Affichage : X visible(s) · Y masquée(s) ») indique en permanence combien de fiches sont filtrées par rapport à l'ensemble chargé, et un bouton **Réinitialiser** efface tous les filtres rapides en un clic pour repartir d'une vue neutre.

Le pilotage affiche également un **journal récent** des actions effectuées sur le mur (créations, validations, changements de statut, retraits) et un flux d'**activité récente**, rafraîchi automatiquement, qui remonte les événements les plus récents sans avoir à recharger la page. Deux types d'alertes ponctuelles peuvent y apparaître : une **qualification manquante ou expirée** lorsqu'une personne affectée à une fiche n'a pas (ou plus) la compétence obligatoire requise dans son dossier opérateur, et une **fenêtre d'action incohérente** lorsque l'heure de fin renseignée sur une fiche est antérieure à l'heure de début. Ces deux alertes sont des signaux d'attention, pas des blocages : elles invitent à corriger la fiche concernée, sans empêcher les autres actions.

Exemple : à l'ouverture d'un exercice de grande ampleur, l'état-major bascule la posture sur **Alerte** puis passe en **Vue synthèse crise** pour ne garder à l'écran que les missions critiques en cours, avant de revenir en **Vue complète** une fois l'exercice terminé pour faire le bilan de toutes les fiches de la journée.

### Audiences et niveaux de sensibilité

Chaque fiche définit deux réglages qui déterminent qui, parmi les membres, la verra effectivement sur le mur.

**La portée de diffusion** répond à la question « pour qui cette fiche existe-t-elle ? » :

- **Toute la communauté** : visible par tout membre ayant le droit de consulter le mur.
- **Une unité précise** : visible uniquement par les membres rattachés à cette unité.
- **Certains emplois** : visible uniquement par les membres occupant l'un des emplois métier sélectionnés, quelle que soit leur unité.
- **Brouillon personnel** : visible uniquement par son auteur, tant qu'elle n'est pas rediffusée plus largement.

**Le niveau de sensibilité** répond à la question « à quel point cette information est-elle délicate ? », indépendamment de la portée choisie :

| Niveau | Signification | Qui peut la voir |
|---|---|---|
| **Diffusion interne large** | Information ordinaire, sans restriction particulière | Tout membre concerné par la portée choisie |
| **Encadrement** | Réservée aux personnes exerçant une fonction d'encadrement | Uniquement l'encadrement et les personnes habilitées à gérer le mur |
| **Confidentiel** | Information sensible nécessitant une diffusion restreinte | Uniquement les personnes habilitées à gérer le mur |
| **Très restreint** | Niveau maximal de restriction | Uniquement les personnes habilitées à gérer le mur |

Concrètement, sur le mur des membres, seules les fiches en diffusion interne large restent visibles pour un lecteur ordinaire ; les trois autres niveaux ne s'affichent que pour les personnes disposant du droit de modification du mur (ou d'un rôle d'administration), qui voient ainsi systématiquement l'ensemble des fiches, quel que soit leur niveau de sensibilité ou leur portée de diffusion.

Exemple : une fiche « Point de situation encadrement — retour d'expérience exercice » est publiée avec une portée « toute la communauté » mais un niveau « Encadrement » : elle n'apparaîtra donc que dans le pilotage et pour les personnes habilitées, jamais sur le mur consulté par l'ensemble des membres.

---

## Cas particuliers

### Droits d'accès exacts

L'accès au mur et au tableau opérationnel repose sur deux droits distincts, attribués dans la gestion des rôles de la communauté (back-office · Organisation · Rôles) :

- **« Consulter le tableau opérationnel (portail) »** : donne accès à l'écran de lecture du mur opérationnel. Sans ce droit (ni aucun des suivants), un membre ne peut pas ouvrir le mur.
- **« Créer et modifier les entrées du tableau opérationnel »** : donne accès au pilotage complet — création, préparation, validation, publication, suivi, clôture, gestion des rubriques et des modèles, gestion du lien public, changement de posture.

Une règle importante à connaître : le second droit donne automatiquement accès à tout ce que permet le premier. Une personne habilitée à créer et modifier les entrées peut donc aussi bien ouvrir le pilotage que consulter le mur, sans qu'il soit nécessaire de lui attribuer les deux droits séparément.

Les rôles d'administration générale de l'organisation (administration de l'organisation, accès administrateur, administration système) donnent eux aussi automatiquement accès au pilotage complet, même sans attribution explicite de ces deux droits — c'est un filet de sécurité pour que les administrateurs ne se retrouvent jamais bloqués devant le mur opérationnel de leur propre communauté.

Si un membre voit le mur mais aucun bouton de gestion (pas de **Ouvrir le pilotage**, pas d'actions sur les fiches), c'est le signe normal d'un rôle en lecture seule : il dispose du premier droit sans le second.

### Limites à connaître

- **Rien n'est visible sans publication explicite.** Une fiche parfaitement complète mais jamais approuvée ni mise en ligne restera invisible sur le mur des membres, sans qu'aucune alerte ne le signale à eux — c'est aux personnes habilitées de suivre le compteur de brouillons pour éviter les oublis.
- **La clôture est bloquée par les points de contrôle obligatoires.** Ce n'est pas un incident : c'est une garantie que le suivi administratif minimal a bien été fait avant de considérer une action comme terminée.
- **Le retrait du mur n'efface rien.** Une fiche retirée reste consultable dans l'historique du pilotage via le filtre **Retirées du mur** ; seul son affichage public disparaît.
- **La sensibilité prime sur la portée de diffusion.** Une fiche destinée à toute la communauté mais marquée « Confidentiel » ne sera visible que par les personnes habilitées à gérer le mur, quelle que soit la portée choisie.
- **Le lien public reproduit le mur, pas le pilotage.** Toute personne disposant du lien peut consulter les fiches actives sans identification, mais ne peut jamais agir sur elles ; désactivez ou régénérez ce lien dès qu'il n'est plus nécessaire.
- **Un doublon de publication liée est automatiquement évité.** Tenter de publier une seconde fois un même événement, une même formation ou une même mission de coopération redirige vers la fiche déjà existante plutôt que d'en créer une deuxième — sauf si la première a été retirée du mur.
- **Les alertes de qualification et de fenêtre d'action sont indicatives, pas bloquantes.** Elles signalent un écart à corriger (compétence expirée, dates incohérentes) mais n'empêchent ni la publication ni la clôture d'une fiche : c'est à l'état-major de les traiter avec la rigueur voulue.
- **Les personnels et moyens affectés à une fiche ne réservent rien automatiquement ailleurs dans le portail.** Ce sont des listes informatives propres à la fiche ; elles ne bloquent pas un membre pour une autre action ni ne vérifient une disponibilité d'agenda en dehors du tableau opérationnel.

### Bonnes pratiques

1. Rédiger des titres actionnables : verbe, objet, échéance — par exemple « Tenir permanence radio — vendredi 20 h » plutôt qu'un titre vague.
2. Renseigner systématiquement une rubrique et des mots-clés dès la création, pour que la fiche reste facilement retrouvable plus tard.
3. Mettre à jour le statut opérationnel au fil de l'exécution réelle plutôt qu'en une seule fois à la fin : cela permet aux membres de suivre une mission en cours de manière fiable.
4. Éviter d'ouvrir deux fiches pour une même action : privilégier la duplication ou la mise à jour opérationnelle plutôt qu'une nouvelle création isolée.
5. Valider les points de contrôle au moment où ils sont réellement accomplis, pas rétroactivement au moment de la clôture.
6. Retirer du mur ce qui n'a plus lieu d'être diffusé, tout en laissant l'historique disponible en pilotage pour la mémoire de l'unité.
7. En période de tension : basculer la posture sur **Alerte** ou **Crise**, activer la **vue synthèse crise**, et privilégier des flashs courts plutôt que des fiches longues à lire dans l'urgence.
8. Réserver le niveau **Encadrement** ou plus restrictif aux informations qui doivent réellement rester hors de portée de l'ensemble des membres, pour ne pas saturer inutilement le pilotage de fiches à accès limité.
9. Traiter sans attendre les alertes de qualification manquante ou expirée : mieux vaut réaffecter une personne à jour de sa compétence avant l'action que de le découvrir après.
10. Utiliser la désignation d'un remplaçant avec activation automatique pour les rôles de commandement critiques, afin de fiabiliser le déroulement d'une action même en cas d'imprévu de dernière minute.
11. Ajouter un commentaire de validation lors d'un refus ou d'une approbation sensible : quelques mots suffisent à rendre l'historique compréhensible plusieurs semaines plus tard.
12. Vérifier régulièrement le compteur de brouillons du pilotage pour éviter qu'une fiche préparée ne reste jamais publiée par simple oubli.

### Lexique

| Mot | Sens dans Athena |
|---|---|
| Mur | Vue membres des fiches publiées |
| Tableau / pilotage | Administration complète du mur |
| Brouillon | Fiche en préparation, invisible sur le mur |
| Approuver / Mettre en ligne | Actions qui rendent une fiche visible sur le mur |
| Refuser | Action qui annule la mise en ligne d'une fiche |
| Retirer du mur | Retirer une fiche de la diffusion, en conservant son historique |
| Posture | Niveau de tension global choisi pour la communauté |
| Statut opérationnel | Avancement réel d'une fiche : Planifié, En cours, Suspendu, Terminé, Annulé |
| Mise à jour opérationnelle (FRAGO) | Nouvelle fiche dérivée d'une fiche existante, pour un amendement ou un ordre complémentaire |
| Points de contrôle | Liste de vérifications à valider avant de pouvoir clôturer une fiche |
| Rubrique | Catégorie de classement propre à la communauté |
| Modèle | Squelette de fiche réutilisable |
| Portée de diffusion | Public visé par une fiche : communauté, unité, emplois, ou soi-même |
| Niveau de sensibilité | Degré de restriction d'accès d'une fiche, indépendant de la portée |
| Publication liée | Fiche créée automatiquement depuis un événement, une formation ou une coopération |
| Lien public | Accès en lecture seule au mur, sans identification, activable depuis le pilotage |
| Chef désigné / Adjoint / Remplaçant | Rôles de responsabilité affectés sur une fiche, avec activation automatique possible du remplaçant |
| Personnels affectés | Liste des membres engagés sur une fiche, avec leur rôle et leur statut de responsable de ligne |
| Moyens | Inventaire du matériel engagé sur une fiche, avec son état (Disponible, Engagé, Indisponible) |
| Fenêtre d'action | Période exacte pendant laquelle une action peut se dérouler |
| Qualification requise | Compétence obligatoire vérifiée sur les personnels affectés à une fiche |
| Journal / activité récente | Suivi chronologique des actions effectuées sur le mur, rafraîchi automatiquement |
| Commentaire de validation | Justification facultative saisie lors d'une approbation ou d'un refus |

### Dépannage

**Je ne vois pas le mur opérationnel dans le menu.** Le droit de consultation ne vous a probablement pas été attribué. Demandez à un administrateur de vérifier votre rôle dans la gestion des rôles de la communauté.

**Je vois le mur mais aucun bouton de gestion.** C'est normal si vous ne disposez que du droit de consultation : seules les personnes habilitées à créer et modifier les entrées voient les outils de pilotage.

**Une fiche que je viens de créer n'apparaît pas sur le mur.** Vérifiez qu'elle a bien été approuvée ou mise en ligne : une fiche reste un brouillon invisible jusqu'à cette action explicite. Le compteur **Brouillons** du pilotage permet de repérer rapidement les fiches encore en attente.

**Je n'arrive pas à clôturer une fiche.** Un ou plusieurs points de contrôle obligatoires sont probablement encore ouverts. Ouvrez la fiche, validez les points restants, puis relancez la clôture.

**Je clique sur « Publier au mur opérationnel » depuis un événement et je suis redirigé vers une autre fiche.** Une fiche non retirée est déjà rattachée à cette même source ; le portail vous a renvoyé vers elle pour éviter un doublon. Retirez-la du mur si elle doit être recréée depuis zéro.

**Le lien public ne fonctionne plus.** Il a probablement été régénéré ou désactivé depuis le pilotage ; demandez le nouveau lien à la personne qui gère le mur.

**Un membre voit une fiche qu'il ne devrait pas voir, ou au contraire ne voit pas une fiche qui le concerne.** Vérifiez la portée de diffusion (communauté, unité, emplois) et le niveau de sensibilité de la fiche : ces deux réglages combinés déterminent l'audience réelle, indépendamment de la publication.

**Une alerte de qualification manquante s'affiche sur une fiche.** La personne affectée n'a pas, ou plus, la compétence obligatoire associée à cette fiche dans son dossier opérateur. Corrigez la validité de la compétence ou remplacez la personne affectée avant le déroulement de l'action.

**Je crée un nouveau modèle mais je ne le retrouve pas dans la liste.** Vérifiez qu'il n'a pas été enregistré comme inactif ou sous un nom déjà proche d'un modèle existant ; les modèles inactifs n'apparaissent plus dans la liste de génération de fiches.

**J'essaie d'ajouter une rubrique mais le système refuse.** Le nom choisi est probablement déjà utilisé par une rubrique existante de la communauté ; sélectionnez-la directement dans la liste plutôt que d'en recréer une identique.

**Je ne retrouve plus le détail d'une décision prise sur une fiche il y a plusieurs jours.** Ouvrez le **journal récent** ou l'historique de la fiche concernée : chaque création, modification, validation, changement de statut et retrait y est enregistré, avec le commentaire de validation le cas échéant.

**Le tableau opérationnel affiche un message indiquant qu'il n'est pas encore activé sur le serveur.** Cela signifie qu'une mise à jour technique doit être appliquée par un administrateur avant de pouvoir utiliser cette fonctionnalité. Contactez l'administration système de votre installation.

---

## Voir aussi

- [Back-office et organisation](back-office-organisation.md)
- [Événements, pointage et messages](evenements-pointage-messages.md)
- [Formations](formations.md)
- Guide intégré du portail : rubrique **Mur opérationnel** (accessible depuis `/documentation`)
