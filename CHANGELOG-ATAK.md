# CHANGELOG - Features ATAK

Toutes les modifications notables des features ATAK sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Journal développeur (style Bohemia) : [SPOTREP #00001](docs/dev/SPOTREP-00001.md) · [TECHREP #00001](docs/dev/TECHREP-00001.md).

---

## [1.5.48] / Overwatch 1.4.95 / liaison 1.17.9 — 2026-08-30

### Pack Overwatch 1.4.95 — guidage GPS + zones ATAK

Itinéraire posé au poste : points numérotés et trait visibles en jeu. Zones tactiques (poser, danger, ralliement) synchronisées ; alerte à l’entrée d’une zone dangereuse. Relancer Arma complètement.

---

## [1.5.34] / Overwatch 1.4.67 / extension 2.0.14 — 2026-08-24

### Corrigé — météo en erreur rouge au spawn

Le bandeau météo n’est plus envoyé pendant le handshake. Un timeout ou un refus temporaire ne s’affiche plus comme une panne. Dès que le poste a bien reçu, le terminal s’arrête de renvoyer le même ciel.

---

## [1.5.33] / Overwatch 1.4.66 / extension 2.0.13 — 2026-08-24

### Corrigé — rafale 0 / -1 / 503 au spawn

Si Athena ne répond pas tout de suite (timeout, saturation), le terminal ne réessaie plus en boucle les caméras, la météo, les photos et la position. Il attend quelques secondes, puis reprend. Une photo refusée pour saturation part dès que le poste respire, sans marteler le même cliché. Le journal ne traite plus ça comme une panne rouge.

---

## [1.5.32] / Overwatch 1.4.65 — 2026-08-24

### Corrigé — IA alliée sur l’ATAK

Le suivi posé depuis Zeus reste après la fermeture du curateur. On peut le retirer (menu « Retirer l’IA de l’ATAK » ou case décochée), sans étendre tout le groupe.

---

## [1.5.31] / Overwatch 1.4.64 / Athena ATAK 1.0.46 — 2026-08-24

### Amélioré — sons ATAK (web et jeu)

Nouveau pack d’alertes, identique sur la carte web et en mission : bip radio court, carillon de réception d’ordre, confirmation d’acceptation, transmission de renseignement, signal médical Motorola (trois fois), démarrage.

---

## [1.5.30] / Overwatch 1.4.63 / extension 2.0.12 — 2026-08-24

Vague 2026.08c — voir SPOTREP #00001 pour le détail opérateur.

- Poste de situation (dossiers SSE + localisation téléphone)
- Parc de terminaux (retrait appareils / sessions web)
- Relecture : joueurs, IA alliées, téléphones, GPS
- Relief autour de l’équipe (`getTerrainHeightASL`), plus de spam 401
- Overlays liaison, Zeus SSE/ATAK/Overwatch, proximité téléphone

---

## [1.4.50] / extension 2.0.10 — 2026-08-23

### Corrigé — lancement et signalement

Fenêtre Windows unique au menu principal (conditions d’utilisation + disclaimer bêta). Plus de parade de dialogues en mission. Le signalement in-game s’ouvre depuis Échap → gestion du mod et part bien vers l’équipe.

---

## [1.4.49] / Athena ATAK 1.0.42 — 2026-08-23

### Corrigé — gel à la photo ATAK

Les clichés ATAK (JPEG BCE / IceMan) ne déclenchent plus un second `screenshot` PNG synchrone sur le thread jeu. Aligné sur le flux SOAR Discord : un JPEG, envoi en file. Un PNG de repli n’est pris que si le fichier JPEG est vraiment introuvable, hors de la frame du clic.

---

## [1.4.19] - 2026-08-18

### Ajouté — Fiches de renseignement simplifiées

Entre « rien à signaler » et « j'ouvre un dossier d'intérêt », il manquait la marche la plus basse : noter tout de suite ce qu'on vient de voir, sans formulaire à remplir et sans rien conclure. Une plaque relevée, une attitude inhabituelle, un axe soudain désert, une conversation avec un habitant — autant d'éléments qui se perdaient parce que les consigner coûtait plus cher que de les oublier.

- **Menu dédié RENS dans le tiroir ATAK.** Le rédacteur s'ouvre en plein cadre sur **toute la surface de l'ATAK**, pas dans un panneau de téléphone : on choisit ce menu pour écrire, pas pour lire. Également accessible par l'icône « Fiche RENS » de l'écran d'accueil ATAK et par l'action ACE « Rédiger une fiche de renseignement… ».
- **Rédacteur identique dans le portail** (`Pilotage → Fiches de renseignement`) : même disposition, mêmes libellés, mêmes limites. Un opérateur qui passe du jeu au portail ne réapprend rien.
- **Cinq informations, pas une de plus** : un texte libre de 1000 caractères avec compteur vivant, une date, un lieu, un à quatre thèmes, et jusqu'à quatre pièces jointes. Le reste — classement, qualification, rapprochements — se fait ensuite au bureau.
- **Cinq types de fiche** (mission, observation, contact, ambiance, technique) et **douze thèmes** colorés par gravité, qui orientent la fiche vers le bon analyste.
- **Pièces jointes depuis le terrain** : capture de la scène, photographie déjà prise dans la bibliothèque ATAK, ou relevé de position et d'instant. Depuis le portail : photo, capture, PDF ou texte.
- **Suivi côté bureau** : prise en compte, exploitation ou classement sans suite, avec une phrase de justification, et rattachement facultatif à un dossier validé. Le rattachement fait apparaître la fiche dans le dossier sans rien conclure sur les personnes citées.
- Chaque fiche alimente le **journal des transmissions terrain** et le journal d'activité, comme les fiches personnes et les sites.

### Choix de conception

- **Le brouillon survit à la fermeture du rédacteur.** Une fiche est presque toujours interrompue — contact, déplacement, ordre radio. Perdre la saisie à chaque fermeture aurait appris aux opérateurs à ne plus rien rédiger, ce qui est exactement l'inverse du but.
- **Les captures d'écran sont prises après la fermeture du rédacteur.** Prises pendant, elles n'auraient montré que l'interface. Le rédacteur se referme, la scène redevient visible, la capture part.
- **Une clé d'idempotence par fiche.** Une double validation, ou une retransmission après coupure de liaison, retombe sur la même fiche. Sans elle, le bureau recevrait deux fois le même constat — précisément ce que l'automatisme de détection de doublons signale ensuite comme suspect.
- **Liaison coupée : la fiche est conservée, pas perdue**, et le message le dit ainsi. L'annoncer comme un échec pousserait à ressaisir, et deux fiches du même constat arriveraient au rétablissement.
- **Le référentiel des libellés est dupliqué côté jeu**, pour rester lisible sans liaison. L'ordre des thèmes est contractuel — chaque bascule du rédacteur est câblée sur un rang, pas sur un code — et un test compare les deux référentiels pour empêcher la dérive.
- **Une fiche n'identifie personne et ne vaut pas preuve.** Le rappel figure dans le rédacteur, dans la file et sur la fiche : c'est un constat daté et situé, rien de plus.
- **Les pastilles de thème sont de vrais contrôles**, pas du texte structuré : Arma ne sait pas peindre un fond derrière un fragment de texte, et des étiquettes sans couleur auraient perdu la lecture d'un coup d'œil qui fait tout l'intérêt du bandeau.

---

## [1.4.14] - 2026-07-30

### Ajouté — Corrélation d'exploitation

- **Graphe de corrélation du dossier** (`Dossiers → Voir les corrélations`) : personnes, sites, pièces et saisies, classés par nombre de liens. Trois provenances distinguées visuellement et jamais confondues — **déduit** (recalculé depuis les saisies), **automatisme** (posé par une règle), **analyste** (hypothèse assumée).
- Les liens déduits ne sont pas stockés : corriger une fiche corrige le graphe. Un lien stocké se périmerait dès qu'une saisie est rectifiée, et personne ne penserait à aller le corriger.
- **Pose de relation à la main** entre deux personnes du dossier, avec nature du lien, niveau de fiabilité et justification. Retirable.
- Table `sse_relations`, clé unique par arête : réenregistrer la même relation met à jour la fiabilité et la note plutôt que de dupliquer.

### Ajouté — Automatismes SSE

Un automatisme propose, il ne décide pas. Aucune règle ne clôt un site, ne fusionne des fiches ni ne déclare une identité — une règle qui se trompe en silence coûte plus cher que dix rappels à faire à la main.

- **Classement automatique** — une fiche transmise sans code dossier rejoint le dossier ouvert **s'il n'y en a qu'un**. Avec plusieurs dossiers ouverts, la règle s'abstient : une fiche non classée se voit, une fiche mal classée passe inaperçue jusqu'au débriefing.
- **Doublon probable** — deux fiches portant le même relevé biométrique sont signalées et reliées par « même individu que ». Aucune fusion : elle détruirait la fiche la moins complète, qui est parfois celle qui porte l'observation utile.
- **Correspondance forte** — au-delà de 85 % de similarité avec une liste de surveillance, le dossier passe en exploitation et reçoit une note. Le libellé rappelle qu'un score n'est pas une identification.
- **Co-présence** — les fiches du même dossier saisies à moins de 45 minutes d'écart sont reliées, en « non vérifié » : c'est une proximité d'horodatage, pas un lien constaté. Plafonné à 5 liens par fiche.
- **Site prêt pour clôture** — checklist complète signalée au poste de commandement, sans clôturer.
- **Saisie sensible** — armement, munitions, supports numériques et documents remontent immédiatement, sans attendre la clôture du site.
- Chaque règle laisse une trace en clair dans le journal d'activité : on peut répondre à « pourquoi cette fiche est-elle dans ce dossier ? » sans lire le code. Les réponses d'API portent un champ `automation` déjà rédigé en français.

### Ajouté — Déclassification et caviardage

- **Version expurgée du dossier** (`Dossiers → Version expurgée`) : on choisit le niveau de diffusion visé, tout ce qui est au-dessus part au noir automatiquement. Cinq catégories caviardables — Identité, Lieu, Biométrie, Source, Horodatage — chacune avec son niveau minimal de lecture en clair.
- **La source est la catégorie la plus protégée** : on peut souvent dire *ce qui* a été trouvé sans dire *qui* l'a trouvé, l'inverse est rarement vrai.
- **Caviardage manuel** : noircir une zone précise sur une fiche précise, quel que soit le niveau, avec motif obligatoire — c'est lui qu'on relira pour décider de le lever. Levable à tout moment.
- La page ouvre par défaut sur le niveau le plus large, donc le plus caviardé : ouvrir l'écran ne doit jamais exposer plus que ce qu'on a demandé à voir. Un tableau annonce ce qui restera en clair **avant** de produire le document.
- **Le texte caviardé n'est jamais envoyé au navigateur.** La substitution est faite côté serveur. Un trait noir posé en habillage CSS laisserait le texte dans la page — copier-coller, code source, lecteur d'écran, cache — ce qui reviendrait à ne rien caviarder.
- La longueur des barres est quantifiée par pas de 4 et plafonnée : une barre exactement proportionnelle révélerait la longueur du nom, ce qui suffit souvent à identifier quelqu'un sur un dossier à trois personnes.
- Le caviardage est branché sur la source unique des deux comptes rendus : une catégorie ne peut pas être noircie dans le flash et lisible dans le compte rendu initial.
- La date de recueil reste, l'heure part : savoir « le 14 » n'a pas la même valeur que savoir « le 14 à 03h12 ».

### Ajouté — Habilitation de lecture SSE

- **Plafond d'habilitation par session** : le niveau de diffusion demandé sur l'écran de déclassification est rabattu sur ce que la session a le droit de lire. Le paramètre de l'adresse exprime une demande, il n'accorde rien — le forcer à la main sert la version autorisée et inscrit la tentative au journal.
- Trois permissions explicites (`atak.sse.clearance.encadrement`, `.confidentiel`, `.tres_restreint`), et à défaut un **report des rôles déjà en place** : administration → très restreint, gestion des dossiers → confidentiel, accès portail → encadrement, rien → interne. Sans ce report, la mise à jour mettrait tout le monde au plancher tant qu'un administrateur n'a pas assigné les nouvelles permissions.
- **Habilitation portée par un code d'accès invité**, choisie à l'émission. Par défaut Diffusion interne : un invité ne voit ni identité, ni lieu, ni source. On n'accorde jamais par défaut de valeur, seulement par défaut de refus.
- L'écran affiche l'habilitation **et d'où elle vient**. Une habilitation qu'on ne peut pas expliquer se conteste mal.
- Les niveaux au-dessus du plafond apparaissent verrouillés au lieu de disparaître : un refus doit se voir refusé, pas manquer.
- Un dossier tenu au-dessus de l'habilitation du lecteur est signalé. La consultation reste possible — verrouiller d'un coup des dossiers ouverts hier est un arbitrage d'exploitation, pas une décision technique.

### Corrigé

- **L'écran de déclassification ne vérifiait pas qui demandait quoi.** Le niveau était lu tel quel dans l'adresse : n'importe qui pouvant ouvrir un dossier, invité compris, obtenait la version intégrale en changeant un paramètre. Produire un document expurgé et restreindre qui peut le lire sont deux choses différentes ; la première seule ne protégeait rien.

### Ajouté — Diffusion dirigée des rapports tactiques (phase A)

- Le moteur de règles de diffusion **existait sans aucun appelant** : `atak_report_routing_rules`, `atak_report_routing_history` et `AtakReportRoutingRepository` étaient en place, avec conditions, destinataires, escalade et accusé de réception, mais rien ne les invoquait. Le chantier avait été commencé puis laissé avant branchement.
- Les règles s'appliquent désormais à la soumission d'un rapport tactique. Les destinataires apparaissent dans la réponse (`routed_to`) et sur la consultation du rapport (`routing`).
- **Pas d'interrupteur, volontairement** : sans règle enregistrée, le moteur ne route vers personne et n'écrit rien. Une table de règles vide *est* l'état désactivé, et ajouter un réglage donnerait deux endroits à vérifier quand un rapport n'arrive pas.
- Un échec de routage n'échoue jamais la soumission : le rapport est enregistré d'abord, la diffusion tentée ensuite et tracée si elle échoue. Perdre un compte rendu de contact parce qu'une règle est mal formée serait un échange calamiteux.
- **La cible a changé par rapport à la demande initiale.** Le branchement devait porter sur `atak_intel` ; cette table n'a ni `report_type`, ni `priority`, ni `tenant_id`, et la clé étrangère de l'historique de routage pointe sur `atak_tactical_reports`. Router `atak_intel` demanderait d'altérer une clé étrangère en base vivante et d'ajouter un cloisonnement à une table qui n'en a pas — ce n'est pas un branchement mais une phase de schéma, renvoyée à la suite du plan.

### Ajouté — Écran des règles de diffusion

- `/admin/atak-diffusion-rapports` : création, activation et suppression des règles. Sans lui, le moteur branché en phase A tournait à vide, faute de règle à appliquer.
- Conditions exprimées en clair — types de rapport, priorités, mots-clés — plutôt qu'en JSON brut. Aucune case cochée signifie « tous », ce qui est écrit sous chaque groupe.
- **Une règle sans destinataire est refusée** : elle donnerait l'illusion d'une diffusion en place tout en n'en produisant aucune.
- L'écran annonce lui-même qu'une liste vide est l'état normal après installation, pas une panne.
- **Les notifications ne sont pas émises, et l'écran le dit.** La diffusion enregistre qui doit lire et l'affiche sur la fiche du rapport ; l'envoi en jeu, par courriel ou vers Discord n'est pas branché. Mieux vaut le lire à l'écran que le découvrir en opération.

### Ajouté — Émission réelle des notifications de diffusion

- Une diffusion produit désormais une **notification effective**, écrite dans `atak_realtime_notifications` avec ses destinataires, sa position sur la carte et son urgence.
- **Une seule notification par rapport**, portant tous les destinataires — et non une par destinataire. Trois lignes identiques dans le bandeau pour un même compte rendu font passer l'alerte pour du bruit, et c'est le bruit qu'on finit par ignorer.
- L'urgence reprend celle du rapport : un contact `FLASH` s'affiche en critique, une routine en bas de pile. Un compte rendu immédiat n'a pas à ressembler à une routine.
- Expiration à deux heures : une alerte qui reste affichée une semaine devient un décor.
- **Nouvelle route de relève `GET /api/atak/notifications`.** `AtakNotificationRepository` avait `create()`, `listActive()` et `pollSince()` mais **aucune route ne l'exposait** : les notifications écrites n'étaient lisibles par personne. Sans cette relève, émettre revenait à écrire dans un tiroir fermé.
- Une notification qui échoue n'annule pas la diffusion — elle est tracée avec la mention explicite « destinataires à prévenir de vive voix », pour que le silence ne passe pas inaperçu.

### Corrigé — L'historique de diffusion affirmait des notifications jamais envoyées

- `createRoutingEntry()` inscrivait `notification_sent = 1` et le canal « in-game » alors qu'aucun envoi n'avait lieu. Le drapeau est désormais posé **après** émission réelle, par `markNotified()`, et décrit donc ce qui s'est passé.

### Corrigé — Angle mort du contrôle d'intégrité

- Le contrôle ne vérifiait que le premier argument de `view()`. Or le back-office passe la vraie vue par `'content' => …`, la mise en page seule étant en premier argument : **une vue de back-office absente échappait entièrement au contrôle**, alors que c'est précisément le cas qui produit une page blanche en HTTP 200. La couverture passe de 68 à 365 vues.

### Corrigé — Deux lectures inter-communautés sur les rapports tactiques

- `GET /api/atak/reports/{id}` chargeait le rapport **sans filtrer sur la communauté** : un identifiant deviné suffisait à lire le rapport d'une autre communauté.
- `POST /api/atak/reports/{id}/acknowledge` de même — et acquitter est un acte, pas une lecture.
- Les deux sont désormais cloisonnés. `atak_report_routing_history` ne portant pas de `tenant_id`, la lecture de l'historique de diffusion s'appuie sur le cloisonnement du rapport, ce qui est maintenant vrai et documenté à l'endroit qui en dépend.
- Même classe de défaut relevée sur `AtakPoiRepository` et `AtakMedevacRepository`, hors périmètre de cette phase et signalée dans le plan.

### Corrigé — Cinq appels réseau étaient rejetés en silence

`CfgRemoteExec >> Functions` est en `mode = 1`, c'est-à-dire liste blanche stricte : une fonction absente de la liste voit ses appels distants **rejetés sans message**. Cinq fonctions y manquaient alors qu'elles sont bien appelées via `remoteExec`.

- **`receiveOrder`** — les ordres émis n'arrivaient pas à leur destinataire en multijoueur.
- **`createRoleplayZoneFromZeus`** et **`createRoleplayZone`** — les zones posées depuis Zeus n'étaient jamais créées côté serveur.
- **`restoreAtakSession`** et **`clearDisconnectedAtakState`** — la reprise de session après plantage ne se faisait pas.

Chaque entrée porte un `allowedTargets` correspondant à la cible réelle de l'appel, au plus juste plutôt qu'au plus permissif.

- **Le moteur roleplay n'était pas activé sur les autres machines**, ce qui faisait paraître les zones inertes. L'activation passait par `remoteExecCall ["call", 0, true]`, or `Commands` est aussi en liste blanche et `call` n'y figure pas — l'appel était rejeté. L'y ajouter aurait ouvert l'exécution de code arbitraire à distance à n'importe quel client ; l'activation passe désormais par des variables publiques, ce qui couvre en plus les joueurs qui rejoignent en cours de partie — le drapeau JIP de l'ancien appel ne le pouvait pas, `jip = 0` étant posé dans la configuration.
- Seuls les deux réglages désactivés par défaut sont forcés. « Effets visuels de dégradation » est déjà actif par défaut : le forcer n'aurait touché que les joueurs l'ayant volontairement coupé.

### Corrigé — Administration : l'audit système était inaccessible

- **`SystemAuditController` ne se chargeait pas** : une méthode `rollback()` y était déclarée deux fois — un accesseur privé vers le service de reprise, et l'action de route publique. PHP échoue à la compilation de la classe, ce qui rendait inaccessibles les quatre routes `/admin/audit` (consultation, détail, reprise, alerte). `php -l` ne le détecte pas : ce n'est pas une erreur d'analyse syntaxique, ce qui explique que le défaut soit passé inaperçu.
- L'accesseur est renommé `rollbackService()`.

### Corrigé — Ce qui était saisi hors liaison était perdu

- Le mod simulait la coupure réseau mais **perdait les données saisies pendant**. Une fiche SSE renseignée dans une cave sans couverture partait dans le vide ; l'opérateur ne l'apprenait qu'au débriefing. La file d'attente existante ne couvrait que les marqueurs de carte.
- **Tampon hors ligne** pour tout ce qu'un humain rédige : fiche SSE et relevés, point d'intérêt, MEDEVAC, QRF. Rejoués dans l'ordre au rétablissement de la liaison.
- **Les positions ne sont jamais rejouées**, délibérément : restituer une position vieille de dix minutes montrerait l'élément là où il n'est plus. Une position périmée trompe le poste de commandement au lieu de l'informer.
- Le tampon vit dans le profil du joueur et survit à un plantage ou une reconnexion — la coupure qui fait perdre des données est rarement propre. Les entrées sont horodatées sur l'horloge murale et non sur le temps de mission : ce dernier repart à zéro à chaque partie, si bien qu'une entrée aurait été relue avec un âge négatif à la session suivante, donc jamais périmée — une demande MEDEVAC de samedi se serait rejouée le week-end suivant.
- Temporisation croissante entre les tentatives : une liaison qui vient de revenir est souvent instable, et marteler l'extension produit une salve d'échecs qui ressemble à une panne.
- **Péremption assumée et annoncée** : au-delà de 30 minutes ou 5 échecs, l'entrée est écartée et l'opérateur en est informé. Un compte rendu de contact arrivant trois quarts d'heure après les faits se lirait comme une information fraîche. L'abandon n'est jamais silencieux — sinon l'opérateur croit avoir rendu compte.
- La saisie hors liaison affiche « fiche conservée, ne la ressaisissez pas » plutôt qu'une erreur : une ressaisie produirait deux fiches du même sujet, que l'automatisme A2 signalerait ensuite comme doublon.
- Compteur `N EN ATTENTE` dans la barre d'état du terminal SEEK : un tampon invisible ne vaut guère mieux qu'une perte.

### Corrigé — Le compte rendu et le PDF contournaient la déclassification

- **`/compte-rendu` servait le dossier intégral sans aucun contrôle d'habilitation.** Il suffisait de ne pas passer par l'écran de déclassification pour obtenir le même contenu en clair : l'écran expurgé ne protégeait donc rien. Le compte rendu est désormais servi au plafond du lecteur, avec un bandeau quand il est partiel.
- **L'export PDF produisait lui aussi le dossier intégral.** C'était le pire des trois : un PDF circule seul une fois transmis, un caviardage manquant ne se rattrape plus. L'export est rabattu, et le document porte un bandeau rouge indiquant son niveau de production et les catégories noircies — sans quoi une version expurgée est indiscernable d'une version complète une fois imprimée.
- La mention « Ce document est intégral » du compte rendu était devenue fausse ; elle est remplacée.

### Ajouté — Caviardage des écrans de travail

- Second interrupteur, distinct du verrou : registre des personnes, fiche dossier et corrélations rabattus sur l'habilitation du lecteur.
- **Désarmé par défaut**, pour une raison différente du verrou : ces écrans sont ce que la cellule regarde toute la séance, et l'identité étant classée « Confidentiel », les armer retire les noms à un simple membre. À armer une fois les habilitations réparties, ou après ajustement de la doctrine des catégories.
- Les caviardages manuels d'un dossier s'appliquent aussi sur ces écrans : une zone noircie à la main doit l'être partout, sinon le caviardage ne veut rien dire.
- Restent intégraux dans les deux régimes : registre des sites, fiche site, écran de croisement.

### Ajouté — Verrou d'ouverture par classification

- La classification d'un dossier peut désormais **fermer** le dossier, et plus seulement le signaler : fiche, personnes rattachées, notes, preuves, corrélations, compte rendu et export deviennent inaccessibles à qui n'a pas l'habilitation.
- **Désarmé par défaut.** La classification n'a jamais filtré depuis la création du portail : les valeurs déjà posées ont été choisies sans conséquence, et les armer d'office les transformerait rétroactivement en décisions d'exclusion que personne n'a prises.
- **Écran de revue avant d'armer** : le registre porte une colonne « Qui pourra encore l'ouvrir » sur chaque dossier, la répartition par classification, et le nombre de dossiers que le verrou fermerait au lecteur courant. Le portail ne mesure l'effet que pour la session en cours — il ne parle pas à la place des habilitations des autres.
- Armement réservé aux détenteurs du droit d'octroi : le verrou ferme des dossiers à d'autres, il ne doit pas être desserrable par celui qu'il gêne. Armement et désarmement partent au journal.
- Si la table de réglages est injoignable, le verrou est considéré désarmé. Un portail qui verrouille tout parce qu'une table manque est plus dangereux qu'un verrou temporairement inactif : on découvre le second, on subit le premier en pleine opération.
- Nouvelle table `sse_portal_settings` — le portail n'avait aucun stockage serveur pour un réglage, le thème passant par un cookie, ce qui ne convient pas à un verrou.

### Ajouté — Configuration mission maker et Zeus

- **Attributs Eden sur l'unité**, catégorie « COMSPEC — Exploitation SSE » : ce que la base doit répondre (génération automatique, inconnu, signalé, recherché), état civil, nationalité déclarée, langue, référence de dossier antérieur, indice de confiance imposé, graine. Poser un module par PNJ était intenable sur trente civils.
- **Trois modules Eden / Zeus**, catégorie « COMSPEC SSE » : *Dossier SSE actif*, *Profil d'identité SSE*, *Doter en terminal SEEK*. Variantes Zeus Enhanced avec boîtes de dialogue, ignorées automatiquement quand les modules de configuration sont déjà visibles — même garde anti-doublon que les zones roleplay.
- Les champs laissés vides ne sont pas écrits : un profil partiel complète la génération déterministe au lieu de l'écraser. On peut ne forcer que l'alias d'un sujet.
- Le module « Doter en terminal SEEK » ne dote que les joueurs : un terminal récupérable sur un cadavre ennemi n'est pas l'effet recherché.
- Fixer la graine rend un sujet identique d'une session à l'autre — pour un scénario rejoué ou une séance de formation.

### Documentation

- Nouveau [guide SSE chef de mission / Zeus](mod/UptoDate/docs/guide-sse-chef-mission.md) : préparation Eden, pilotage Zeus, tableau des six automatismes avec ce que chaque règle ne fait pas, lecture du graphe, trame de séance, limites assumées.
- Contrat d'API complété (table `sse_relations`, champ `automation`, variables d'unité réglables par le chef de mission).

---

## [1.4.13] - 2026-07-30

### Ajouté — Terminal SEEK

- **Terminal en pages, dans l'écran de l'appareil** : accueil à six tuiles (Sujet, Contexte, Biométrie, Constat, Photo, Dossier), navigation par flèches et bouton Home. Les contrôles sont posés dans la zone d'écran réelle de l'illustration, mesurée sur la texture ; les touches A1, A2, QUERY et SIGN sont posées sur le clavier de l'appareil.
- **Dossier SSE actif** : la référence est posée une fois pour l'élément, à l'arrivée sur objectif, puis héritée par toutes les fiches sans ressaisie. Visible dans la barre d'état du terminal, réglable depuis le menu ACE ou la page Dossier. Le champ de la fiche devient un repli manuel.
- **Requête d'identité** : bouton REQUÊTE, interrogation de la base fictive (6 s, barre ACE), verdict `Aucune correspondance` / `Correspondance possible` / `Correspondance confirmée` avec indice de confiance et référence de dossier. Affiché dans le panneau d'analyse et le bandeau LCD, transmis à Athena et repris sur la fiche du portail.
- **Résultat déterministe** : chaque personne reçoit une graine stable, dérivée de son identifiant réseau ou posée par le chef de mission. Deux interrogations du même sujet donnent le même verdict. La qualité des relevés module le résultat — une acquisition pauvre ne permet pas de confirmer.
- Le chef de mission peut imposer le verdict par variables d'objet : `COMSPEC_SSE_MatchResult`, `COMSPEC_SSE_Confidence`, `COMSPEC_SSE_RecordRef`.
- **Dotation du terminal SEEK** depuis Zeus (module Zeus Enhanced et module ACE Zeus), l'objet étant requis pour ouvrir une fiche.

### Ajouté — Exploitation de site

- Dossiers de site avec référence lisible, **checklist de fouille prégarnie selon le type** (habitation, dépôt, poste ennemi, cache, véhicule), saisies catégorisées rattachables à une pièce et à une personne, compte rendu de clôture.
- Endpoints `/api/sse/sites` (ouverture, consultation, pièces, saisies, clôture) et écrans portail « Sites exploités ».

### Ajouté — Portail SSE

- **Comptes rendus d'exploitation** : flash et compte rendu initial structuré (Situation, Exploitation du site, Personnel, Matériel, Faits marquants, Appréciation, Suites à donner), générés à la lecture depuis les éléments déjà versés au dossier. Remplace l'inventaire par un produit de renseignement : chaque personne y est reprise avec ses relevés et son verdict d'identité, chaque site avec les pièces non traitées.
- **Sites rattachés au dossier** : un site ouvert depuis le terrain avec la référence active rejoint le dossier, qui agrège désormais personnes, sites et saisies.

- **Charte « SSE Case File »** : palette de station de travail (vert `#12d18e`, trois couleurs sémantiques), Archivo condensé et JetBrains Mono, vignette à balayage sur les portraits, hachures pour les portraits absents.
- Portrait d'enrôlement et **chaîne de possession** sur la fiche personne — les événements étaient enregistrés depuis la 1.4.0 sans être affichés nulle part.
- Volumétrie des dossiers (personnes, notes, pièces) et jauge de similarité sur les croisements.

### Corrigé

- **Le terminal ne pouvait plus enregistrer de fiche** (HTTP 422 « identité requise » alors que l'alias était saisi) : l'échappement JSON tronquait les chaînes accentuées — « Décédée » apparaît systématiquement pour un sujet décédé — et n'échappait pas les caractères de contrôle. Le motif de refus du serveur est désormais affiché au lieu d'un générique « vérifiez la liaison ».
- **Panneau biométrique tronqué** : trois modalités sur deux lignes débordaient, l'ADN était coupé à l'écran.
- Diagnostic de résolution photo : les dossiers réellement balayés sont listés.

---

## [1.4.12] - 2026-07-29

### Ajouté — Terminal biométrique SEEK (renseignement SSE)

- **Nouvelle interface du terminal** : châssis durci, colonne identité, colonne relevé (platine de lecture, analyse, bandeau LCD) et pied de page procédure. Remplace le formulaire à une colonne.
- **Objet transportable** « Terminal biométrique SEEK » (sac, gilet, uniforme) : sans lui, plus de fiche. Réglage CBA « Terminal SEEK requis » pour les communautés qui préfèrent l’accès sans objet.
- **Fiche SSE depuis le menu ACE** sur une autre personne (blessé, inconscient, détenu, corps) : nouveau PBO optionnel `sse_ace`, nœud « Renseignement SSE ». Aucun écran ACE / KAT n’est modifié ; sans ACE chargé, la couche se retire en silence.
- **Constat de terrain ACE Medical** repris automatiquement sur la fiche : état, pouls, volémie, douleur, localisation des lésions. Les localisations alimentent « signes distinctifs ». Conforme à la règle 1.4.8 — ni SpO2, ni voies aériennes, ni donnée KAT.
- **Relevés biométriques simulés** : empreintes, iris et ADN, avec barre de progression, indice de qualité, nombre de points caractéristiques, algorithme et référence de laboratoire fictive. Analyse locale explicitement simulée — le rapprochement reste à la main du poste de commandement.
- **Code dossier SSE** saisi sur le terrain : la fiche est classée directement dans le dossier correspondant du portail. Code inconnu = fiche enregistrée mais non classée.
- **Signature par l’ATAK** : indicatif, identifiant de terminal et horodatage scellés dans la fiche, en guise de procès-verbal.
- **Exploitation d’un corps** : le terminal préremplit désormais identité, armement et équipement sur une personne décédée (le formulaire restait vide).

### Ajouté — Portail SSE

- **Comptes rendus d'exploitation** : flash et compte rendu initial structuré (Situation, Exploitation du site, Personnel, Matériel, Faits marquants, Appréciation, Suites à donner), générés à la lecture depuis les éléments déjà versés au dossier. Remplace l'inventaire par un produit de renseignement : chaque personne y est reprise avec ses relevés et son verdict d'identité, chaque site avec les pièces non traitées.
- **Sites rattachés au dossier** : un site ouvert depuis le terrain avec la référence active rejoint le dossier, qui agrège désormais personnes, sites et saisies.

- **Registre des personnes** en fiches plutôt qu’en tableau : constat de terrain, relevés biométriques avec jauge de qualité, état de signature et classement.
- `GET /api/sse/persons/by-unit` — fiche déjà ouverte pour une unité Arma donnée.
- Table `sse_biometric_samples`, colonnes de constat et de signature, index de recherche par unité.

### Corrigé — Erreurs de script

- `fn_canTransmit` : condition d’écran endommagé mal parenthésée — `exitWith` recevait un bloc de code (`Error exitwith: Type code, if attendu`), l’état « position seule » n’était jamais évalué.
- `fn_updatePosition` : deux conditions d’anomalie de suivi écrites `if {…} && {…}` (Code au lieu de Booléen) — immobilité et déplacement incohérent ne se déclenchaient pas.

### Corrigé — Zeus

- **Effets ATAK sans effet en solo** : le relais Zeus rejetait toute cible dont `owner` vaut 0, ce qui est le cas de toutes les unités hors multijoueur. Casse d’écran, gel, brouillage et extinction fonctionnent à nouveau ; le routage se fait sur la localité.
- **« ID ATAK » vide** dans le panneau « ATAK — Éditer joueur » : les identifiants étaient lus juste après un appel asynchrone, donc avant la synchronisation. Cible locale = lecture directe ; cible distante = une seule nouvelle tentative après l’aller-retour réseau.
- **Modules roleplay en double** dans l’arbre Zeus : les quatre zones étaient déclarées à la fois en config et comme modules Zeus Enhanced. La variante ZEN n’est plus enregistrée quand les modules config sont visibles.

### Corrigé — Photos

- **Captures ATAK introuvables** : les dossiers `Screenshot` des mods Workshop (`…\Arma 3\!Workshop\@<mod>\`) n’étaient pas balayés, alors que BCE y écrit ses clichés — et le chemin qu’il annonce peut pointer vers une installation qui n’existe plus sur le poste.
- **Dossier de captures COMSPEC** : toute capture résolue est recopiée dans `Documents\Arma 3 - COMSPEC\Captures` (200 fichiers conservés), emplacement stable indépendant de l’endroit où Arma ou BCE écrivent.
- `file_not_found` indique désormais si le dossier d’origine existait et combien de dossiers ont été balayés.
- Séparateur de chemin corrigé dans la collecte des photos locales (SQF n’échappe pas les antislashs : `"\\"` en produisait deux).

---

## [1.4.11] - 2026-07-29

### Corrigé

- Gel violent à la prise de photo ATAK : résolution fichier image allégée dans `COMSPECExtension` (fin du polling 7 s + scans récursifs disque à chaque cliché) ; délais pré-upload SQF réduits.

### Ajouté — Carte web

- Style **Point discret** pour les effectifs (même repère violet que les clichés terrain), choix à l’étape profil de session et dans Compte → Affichage.
- Option pour **masquer les points des photos** sur la carte (panneau Cams inchangé).
- Fin du doublon **Photo tablette + Aperçu casque** à chaque cliché ATAK (aperçu auto ne recycle plus la capture BCE).
- **Demande caméra casque** depuis le menu contextuel d’un opérateur en liaison : photo, photo HD, ou flux d’aperçus rapides (~5 s / 3 min).
- **Écran endommagé** : l’opérateur reste visible sur le web (position seule, liaison dégradée) au lieu de disparaître ; libellé terminal corrigé (plus de « éteint · écran endommagé » cumulé).
- **Fin de brouillage** : la liaison ne reste plus bloquée sur « Hors liaison » (recalcul automatique de l’état Athena).
- **Signalement in-game** : le formulaire ACE peut joindre le **journal de session** Overwatch (fichier + tampon mémoire) pour faciliter le diagnostic côté admin.
- **Journaux Overwatch** : un **nouveau fichier par lancement Arma** (`%LOCALAPPDATA%\\Arma 3\\COMSPEC\\logs\\COMSPEC_*.log`), purge auto des plus anciens (12 conservés).
- **parseSimpleArray** : plus de crash Arma sur les réponses extension mal formées ou chemins Windows.
- **Ordres C2** : « En cours » uniquement après **Confirmé** ; refus possible dès la réception (Reçu/Émis).
- **Points de mission** : clic droit carte → ordre de déplacement avec grille, itinéraire, ETA ; transmission ATAK ; confirmé/refusé in-game ; marqueur + trait après acceptation.
- **Marker Dropper** : journal web affiche le libellé (« helico », etc.) au lieu de `_USER_DEFINED #…` ; moins de doublons au resync.

---

## [1.4.8] - 2026-07-29

### Ajouté — Médical ACE (roleplay)

- Détection auto : inconscient, arrêt cardiaque (ACE Medical uniquement — états transmissibles via l’ATAK)

### Retiré — Détections KAT non roleplay

- Plus d’alertes auto voies obstruées, pneumothorax ou hypoxie SpO2 (données internes KAT non visibles sur un terminal tactique)

### Ajouté — Portail ATAK (carte & photos)

- Barre d’outils carte : traits, zones, périmètres, mesure, personnalisation
- Formulaire zones : icône au centre en liste déroulante
- Photos recon : flou, commentaire, masquage local, transfert SSE, effets roleplay visuels
- Détections « Au sol / suivi » retirées à la déconnexion opérateur

### Corrigé

- Flou photo recon en aperçu agrandi (lightbox)
- Upload photos sans gel ; marqueurs web JSON ; scroll page statut ATAK

---

## [1.4.1] - 2026-07-28

### Ajouté — Portail SSE classifié (`/atak/sse`)

- Sas d’accès double entrée : membre habilité + code, ou invité code seul
- Dossiers d’affaire (classification, notes, preuves, rattachement fiches personnes)
- Codes temporaires délivrés par le commandement (`atak.sse.grant`)
- Croisements listes de surveillance + export PDF classifié
- Permissions `atak.sse.*`, update config tenants `SSE_PORTAL_V1`
- Guide / formation module 7 + lien depuis l’onglet Personnes du Tacmap

---

## [1.4.0] - 2026-07-28

### Ajouté — Renseignement interpersonnel (SSE)

#### Mod
- Terminal « Renseignement interpersonnel » (idd 9991) : identité, statut, circonstances, déclarations
- Photo du visage (`UploadSsePhoto`) + simulation empreintes (`SubmitSseBiometricsSim`)
- Préremplissage inventaire / ACE restrain ; menu ACE « Enregistrer une personne »
- Extension : `SubmitSsePerson`, `UploadSsePhoto`, `SubmitSseBiometricsSim`
- Versions addons `main` / `connect` / `mavik_compat` → **1.4.0**

#### Portail
- API `/api/sse/persons` (+ photos, biométrie sim)
- Tables `sse_persons`, `sse_person_photos` (+ sites / watchlist / custody préparées)
- Onglet TOC **Personnes** (`atak-sse-persons.js`)
- Module pont `sse_person` + update config tenants `SSE_PERSONS_V1`
- Guide Overwatch + formation module 7 mis à jour ; changelog site `/nouveautes`

Voir aussi `mod/UptoDate/STEAM_CHANGELOG.txt` et `mod/UptoDate/docs/contrat-api-sse.md`.

---

## [1.3.1] - 2026-07-28

### Corrigé — Messagerie Groups / radio jeu ↔ web

#### Cause
- Le pont `Iceman_ATAK_GroupMessage` n’envoyait plus vers Athena (journal local seul)
- Aucun poll chat pour faire remonter les messages TOC → inbox Athena en jeu

#### Mod
- Restauration envoi `GROUPE|…` via `SendChat` (`fn_athena_bridgeIcemanGroup`)
- `GetChatMessages` (extension) + `fn_pollChatMessages` → messages web / HQ dans l’inbox Athena
- Rebuild : `connect.pbo`, `atak_athena.pbo`, `COMSPECExtension_x64.dll`

#### Portail
- Enrichissement `GroupMessageParser` sur `POST /api/chat`

### Ajouté — Signalements tactiques lisibles (FRAGO / Reports)

#### Mod
- Alertes tactiques : corps métier seul (plus de duplication type / indicatif / grille)
- FRAGO : SMEAC structuré + `ORDER_ID` pour ouvrir l’ordre lié
- Inbox Athena : détail FRAGO par rubriques

#### Portail
- `TacticalAlertParser` : `cleanSummary`, `parseFragoSections`, `activityLabel`, `order_id` / `frago`
- `tacmap-tactical-alerts.js` : cartes + modal **Ouvrir le FRAGO** / **Ouvrir** / carte / ordre
- `atak-activity.js` / `atak-chat.js` / `atak-orders.js` : ouverture fiche + `ATAKOpenOrder`
- Styles modal / boutons (`tacmap.css`, `atak.css`)

### Ajouté — Photos CTAB automatiques vers ATAK web

#### Mod (`atak_athena`)
- Upload auto à la capture (EH BCE / Iceman + poll Photo Library)
- Retry si fichier pas encore écrit ou liaison absente ; flush à la reconnexion
- UI : « Renvoyer la photo » en secours uniquement

### Corrigé — Marqueurs Marker Widget / Dropper (BCE) vers ATAK web

#### Cause
- Widget = BCE Compat cTab (`_USER_DEFINED` / `_IcTab_DEFINED #…` + `setMarker*Local`), pas Iceman
- Filtre / timing COMSPEC rataient ces marqueurs ; « Forcer une resynchronisation » ne poussait que la position

#### Mod (`connect` / `atak_athena`)
- `fn_isSyncableMapMarker` / `fn_forceSyncMapMarkers`
- Acceptation noms BCE ; re-sync différé ; hooks PlaceMarker / onMapDoubleClick
- EH marqueurs dès PostInit ; diagnostic **Renvoyer les marqueurs carte**
- Force sync hub / pause manager inclut les marqueurs

### Versions
- `connect` **1.3.1** · `atak_athena` **1.0.11**
- Rebuild : `connect.pbo`, `atak_athena.pbo`, `COMSPECExtension_x64.dll` + déploiement PHP/JS/CSS Athena

---

## [1.3.0] - 2026-07-28

### Ajouté — Réalisme liaison ATAK (roleplay réseau & appareil)

#### Mod (`connect` 1.3.0)
- **`fn_canTransmit`** : gate central avant envois extension (position, polls, marqueurs) — modes `full` / `position_only` / `none`
- **Hub overlays** IDC 9200–9204 : déconnexion, zone, pertes, écran cassé, glitch (`display_hub.hpp`)
- **Dommages enrichis** : chocs Hit/Explosion, bras blessé, lien KAM (pneumothorax, SpO2 → capteur HR roleplay)
- **`fn_getMedicalState`** : champs KAM optionnels (SpO2, voies aériennes, pneumothorax) propagés vers Athena
- **État crash ATAK** : gel terminal distinct offline réseau (`fn_triggerAtakCrash`, ppEffects)
- **Modules Zeus/Eden** réactivés : `COMSPEC_Module_NoCoverage`, `Interference`, `Degraded`, `Jammer`
- **Sync zones portail** : `fn_pollRoleplayConfig` / `fn_syncRoleplayZonesFromPortal` (poll 90 s)
- **Reprise JIP** : `fn_initCrashRecovery`, `fn_restoreAtakSession`, `fn_clearDisconnectedAtakState`
- **Callbacks extension** : `NetworkDisconnected` / `NetworkReconnected`
- **Assets** : overlays roleplay + logo web (brouillons IA) — doc `docs/design/atak-assets-roleplay.md`

#### Extension `COMSPECExtension`
- `GetRoleplayConfig` → `GET /api/atak/roleplay-stats` (format tabulaire SQF)
- `GetSessionRestore` → `GET /api/atak/session-restore`

#### Portail Athena
- `roleplayStats` : `zones_enabled`, `zones_json`, `session_ttl_sec`
- `GET /api/atak/session-restore?steam_uid=…` — snapshot TTL 10 min (`AtakDisconnectRecoveryRepository`)
- Snapshot auto à chaque `POST /api/atak/position` (indicatif, liaison, position)
- Assets web : `atak-eagle-logo.png`, `atak-link-lost-icon.png` (alerte roleplay JS)

### Modifié
- Versions addons `main` / `connect` / `mavik_compat` → **1.3.0**
- `atak-roleplay-effects.js` : icône liaison perdue au lieu de l’emoji seul

### Rebuild pack
- **Obligatoire** : `connect.pbo`, `COMSPECExtension_x64.dll`
- Recommandé : `main.pbo`, `mavik_compat.pbo` (version affichée hub)

---

## [1.2.2] - 2026-07-27

### Ajouté — Waypoints partagés & itinéraires de patrouille (portail)

#### API & schéma
- Tables `atak_waypoint_routes` / `atak_waypoints` (`migrations/2026_07_27_001_atak_waypoints.sql`) ; filet lazy `AtakWaypointsSchema` si la base était déjà installée
- Routes : `/api/atak/waypoint-routes` (CRUD), `/api/atak/waypoints` (CRUD), `POST /api/atak/waypoints/{id}/reached`
- Création d’itinéraire avec points en un seul appel ; `GET …/waypoint-routes/{id}` expose `next_waypoint` pour le guidage client
- Progression automatique de l’itinéraire : Planifié → Actif (1er point atteint) → Terminé

#### Pack mod
- Changelog Workshop / dépôt pack alignés sur **1.2.2** ; versions addons `main` / `connect` / `mavik_compat` → `1.2.2`, `atak_athena` → `1.0.7`
- Guidage SQF (marqueurs numérotés + sondage `reached`) prévu dans une prochaine livraison pack — API déjà consommable

### Ajouté — Réalisme ATAK (terminal & certificat)

#### API mod ↔ registre communauté
- `GET/POST /api/atak/terminals` et `POST /api/atak/certificates` — protégés par clé d’accès communauté (pas d’exemption dans `config/tactical_api.php`)
- Le mod (extension `RegisterTerminal`, `RegisterCertificate`, `GetTerminalRealism`) s’authentifie via la clé ATAK ; résolution `user_id` par `steam_uid` à l’enregistrement
- `GET /api/atak/terminals?terminal_uid=…` : état terminal + dernier certificat + réglages `atak_defaults` (dont `automatic_pairing`) ; le client jeu ne peut pas lister tous les terminaux
- `POST` certificat refusé si `automatic_pairing` désactivé pour la communauté (`403 automatic_pairing_disabled`)
- Back-office **Certificats et terminaux** (`/back-office/atak/realisme`) ; tables `atak_terminals` / `atak_certificates` (migration lazy)

### Ajouté — Réglages d’exécution portail (runtime admin)

#### Persistance & API
- `TenantAdminSettingsRepository` — réglages `admin_runtime` par communauté (fusion dans `tenant_settings`)
- `GET/POST /api/back-office/runtime-settings` — lecture / enregistrement pour les admins organisation
- Quatre blocs métier : **Portail**, **Notifications**, **Sécurité**, **Défauts ATAK** (sanitisation serveur, valeurs bornées)

#### Portail
- Inscriptions publiques, validation manuelle des comptes, mur public, fuseau horaire

#### Notifications
- Rappels RSVP automatiques, notifications Discord, SMS d’urgence, récapitulatif hebdomadaire

#### Sécurité
- Authentification à deux facteurs, expiration de session, verrouillage après échecs, journal d’audit étendu

#### Défauts ATAK (consommés par le réalisme terminal)
- Appairage automatique des certificats, version client minimale, durée de validité des certificats, partage de position hors opération

### Ajouté — Comptes rendus post-op structurés (AAR)

- Back-office **Comptes rendus post-op** (`/back-office/atak/comptes-rendus`) : liste, fiche, édition, dépôt
- Lien optionnel avec un **cycle de mission** ; statuts **En attente** / **Validé**
- Champs structurés : synthèse, points forts et faibles, actions ouvertes / clôturées, scores et métriques opérationnelles
- Filtres rapides (en attente, validés, actions ouvertes) ; KPIs en tête de liste
- Table `aar_reports` (migration lazy `aar_reports_migration`)

### Ajouté — Matrice rôles & permissions

- Page **Rôles & permissions** (`/back-office/roles-permissions`) : vue matricielle par rôle et par module
- Modules : Membres, Opérations, ATAK, Finances, Systèmes — niveaux d’accès en libellés métier (Complet, Sa section, Lecture, etc.)
- Filtres (recherche, périmètre, niveau, actif), édition inline, export CSV, marquage **revue d’accès** trimestrielle
- APIs `/api/admin/roles-permissions` (liste, export, sauvegarde par rôle)
- Migration `role_permission_matrix_migration`

### Ajouté — Réponses nominatives (événements)

- Vue **Réponses nominatives** (`/back-office/events/{id}/reponses-nominatives`) depuis la fiche créneau
- Tableau par membre : réponse RSVP (Confirmé, Peut-être, Sans réponse, Décliné), section, état terminal / certificat ATAK
- Filtres par réponse, section et état ATAK ; export CSV ; édition des métadonnées orga par ligne
- APIs `/api/events/{id}/reponses-nominatives` (liste, export, mise à jour)
- Migration `community_event_rsvp_nominative_migration`

### Ajouté — Contrôle d’accès modules (`ModuleFeatureAccess`)

- Garde unifiée `guardAtak` / `guardOperations` / `guardSystems` branchée sur la matrice RBAC
- Appliquée au réalisme ATAK, aux comptes rendus post-op, aux réponses nominatives et à la matrice permissions
- Redirection back-office avec message métier si droits insuffisants (pas de vocabulaire technique côté utilisateur)

### Ajouté — Préférences carte ATAK (panneau compte)

- **Recentrage personnel** : recadrer la carte sur sa position dès qu’elle remonte en début de session
- **Contacts en retard** : afficher ou masquer les effectifs dont la position arrive avec délai
- **Alertes sonores par catégorie** : liaison, ordres / urgences, médical — cases à cocher + persistance locale (`atak_alert_categories`)
- Volume et style des sons d’alerte regroupés dans le même panneau (complète la barre latérale)

### Ajouté — Quick wins Athena / Tacmap

#### Overwatch — libellés français
- Panneaux **Relecture mission**, **État logistique**, **Calculateur d’appui-feu**, **Zones de danger**, **Identification ami / ennemi**
- Onglet et santé « Relecture » ; bilan après-action en libellés métier (instantanés, signalements, anomalies)

#### Barre d’outils — profils TOC / Chef d’équipe / Médecin
- Presets dans **Personnaliser** : TOC (tout), Chef d’équipe, Médecin (outils adaptés)
- Préférence enregistrée en localStorage (`atak_map_tools_visible_v1` + `atak_map_tools_preset_v1`)

#### Badge opérateur téléphone + temps restant
- Sur Tacmap en session téléphone : badge **Opérateur téléphone** + compte à rebours jusqu’à expiration

#### Cams — aperçus assumés + demande de vue
- Rappel UI : aperçus photo uniquement (pas de vidéo en direct)
- Bouton **Demander une nouvelle vue** → journal TOC + message radio (confirmation)

#### Effectifs BFT — Vibrer
- Action **Vibrer** sur la liste / tableau des effectifs (API existante), avec confirmation

#### Cycle de mission (briefing → exécution → après-action)
- Hub **Cycle de mission** (`/back-office/atak/cycle-mission`) : créer, ouvrir, clôturer
- Statuts métier : Préparation · En cours · Clôturée (`theatre_mission_cycles`)
- Badge mission sur Tacmap / ATAK ; à la clôture, relecture + bilan bornés (`from` / `to`)
- API `/api/mission-cycle/*` ; migration idempotente + ensure lazy

#### Équipes de feu sur la carte (prio. moyenne)
- Filtre BFT par équipe de feu (liste Effectifs + marqueurs carte)
- Panneau **Composition des équipes de feu** pendant l’opération (couleur, liaison)
- Couleurs d’équipe déjà sur marqueurs / puces conservées

#### Identification IFF de conduite (prio. moyenne)
- Alertes TOC + Overwatch pour contact / véhicule **inconnu**, **suspect**, défi **expiré**
- Compte à rebours d’expiration du défi ; délai de grâce (5 min) → « Contact inconnu »
- Panneau Identification TOC opérationnel (plus seulement Overwatch)

#### Logistique mission (prio. moyenne)
- Seuils stock bas / critique (≤ 35 % / ≤ 15 %) + pastilles d’alerte
- Bouton **Ravitailler** → demande dans le journal d’activité TOC
- Lien vers les évacuations sanitaires en cours depuis le suivi logistique

#### Briefing diapos (prio. moyenne)
- Actions **Monter / Descendre** pour l’ordre ; publication rapide Visible en jeu / brouillon
- Présence briefing enrichie (compteurs téléphone / tableau en jeu)

#### Rapports bugs Overwatch (prio. moyenne)
- Suivi métier **Nouveau → En cours → Corrigé** (`new` / `in_progress` / `fixed`)
- Affichage **version du pack** (+ extension) ; filtre par statut

### Ajouté — Pack priorité haute (TOC / terrain)

#### Parité téléphone ATAK
- Session `/connect` : entrée directe carte (plus de hub invité) + caps BFT / médical / journal radio
- Badge **Opérateur téléphone** + TTL (déjà en place) ; auteur radio = libellé téléphone
- Journal d’activité à l’ouverture carte téléphone

#### Fidélité des repères
- Normalisation API enrichie (couleur Arma → hex, type, forme, dir, alpha)
- Libellés FR ACE (POI, MEDEVAC, renfort, service) ; couleurs hex sans `#`

#### Photos terrain
- Messages d’échec métier (manquant / trop lourd / liaison dégradée)
- Galerie TOC horodatée Zulu + message si image indisponible
- Notifs mod selon détail d’échec d’envoi

#### SITREP ops-ready
- Overwatch : plus de cadrage « test » — signalement poste de commandement
- ACE SITREP / CONTACT / SPOTREP → tableau de situation fusionné

#### Replay AAR exploitable
- Timeline contacts / MEDEVAC / ordres / repères (`/api/replay/events`)
- Bilan + PDF export en français avec compteurs opérationnels

### Déploiement
- Cache-bust Tacmap `?v=202607270730` (session-profile, chat, sitrep, cams, replay, arma-map-markers, atak-sounds, atak-map, atak.css)
- FTP Hostinger → `athena.ttrd.fr` (`public_html` + dual `assets/`) ; `.env` non modifié
- Rebuild mod OK (`build_mod.bat`) — réalisme terminal / certificat + sync liaison
- Lancer `run-migrations` (ou UI migrations) : `atak_realism_registry`, `aar_reports`, `role_permission_matrix`, `community_event_rsvp_nominative` ; `theatre_mission_cycles` et `workflow_status` rapports mod si absent
---

## [1.2.1] - 2026-07-26

### Ajouté — Athena WEB / TOC ATAK (branchement Overwatch)

#### Carte — marqueurs Marker Dropper & cTab
- Les repères posés en jeu (Marker Dropper, marqueurs carte Arma, marqueurs utilisateur cTab / ATAK Enhanced) remontent sur la carte Athena du poste de commandement
- Pont cTab : écoute immédiate des mises à jour + file d’attente si la liaison Athena n’est pas encore prête
- Marqueurs `_USER_DEFINED` (Dropper / carte Arma) inclus dans le miroir web
- Sync immédiate vers le miroir web dès qu’un repère est posé ou mis à jour (sans attendre le prochain cycle long)
- Les points d’intérêt ACE (LZ d’évacuation, renfort, service véhicule, POI) s’affichent aussi sur la carte web
- **Diamants hostiles** : alerte, destruction, objectif ou points rouges simples en diamant (lisibles d’un coup d’œil, sans confusion avec un effectif ami)
- **Badges de préfixe** : libellés courts type « T », « T1 », « A-3 » en pastille à côté du symbole
- Libellés français sur la carte et dans l’historique (« Alerte », « Objectif », « Point d’intérêt », « Repère · … »)
- Déduplication : un point anonyme ou au même indicatif qu’un contact déjà en liaison ne double plus le symbole OTAN de l’effectif
- Pop-up carte : précision « ce point n’est pas un effectif en liaison — c’est un repère posé sur la carte »

#### En-tête carte tactique (Tacmap)
- Barre d’en-tête redesignée : actions regroupées en **clusters** (contexte mission, liaison, état système)
- Bouton **Lier le jeu** (code d’appariement Arma ↔ compte) intégré au cluster liaison
- Badge **BÊTA** à côté de la marque (accès anticipé) ; tagline d’état recentrée sur **Liaison** (plus de libellé « théâtre » en en-tête)
- Plus de bouton fluo / accent agressif : actions primaires sobres, cohérentes avec le reste du TOC

#### Connexion téléphone — page `/atak/connect`
- Page web téléphone : saisie du **code affiché sur le PC / tablette** pour ouvrir la carte ATAK mobile sans compte
- Flux : code → token de session → vue connectée (expiration gérée avec message clair)
- Complète l’écran de liaison en jeu (adresse mobile + code d’appariement)

#### Rapports d’erreurs mod → Athena
- Remontée des diagnostics / bugs Overwatch vers le portail (`POST /api/atak/mod-report`)
- Journal admin **Rapports erreurs** (liste, filtre, retrait)
- Exemption de clé d’accès pour ce chemin (signalement possible avant ou sans liaison complète) + rate-limit
- Correctif `Database::getPdo` : accès PDO stable pour le dépôt des rapports (évite échec au boot / lazy-init)

#### Menu contact — Faire vibrer le terminal
- Action **Faire vibrer le terminal** dans le menu contextuel d’un contact
- Disponible uniquement si le contact est **en liaison** (sinon message clair : hors liaison)
- Confirmation opérateur : « Le terminal de [indicatif] vibre en jeu »
- Journal d’activité TOC : « Terminal — vibration — [indicatif] » (signal haptique, pas un ordre de manœuvre)
- Action voisine : **Envoyer une notification…** (bandeau cliquable sur le terminal du joueur)

#### Blue Force / indicatif / identifiant de suivi
- Chaque contact en liaison reçoit un **identifiant de suivi** stable lié à son indicatif (réutilisé d’une session à l’autre)
- Affichage liste BFT : ligne **« Suivi … »** sous l’indicatif
- Fiche pop-up unité : ligne **« Identifiant de suivi »**
- Même identité partagée entre carte, tablette et TOC

#### Messagerie HQ (poste de commandement)
- Contact permanent **HQ** dans la messagerie ATAK / cTab en jeu : messages destinés au PC → journal radio / messagerie Athena
- Journal d’activité TOC : **« Message HQ — [auteur] : … »**

#### Sons & alertes TOC
- Préférences sonores TOC avec libellés métier (silencieux avec/sans vibration, ambiance tension, signal médical)
- Assistances médicales : bandeaux et toasts restent visibles même si le son est coupé
- Escalade médicale : les alertes moins graves du même indicatif sont clôturées pour éviter le doublon

### Modifié

#### Journal radio — moins de bruit technique
- Messages de **réglages d’affichage** (camps adversaire / indépendants / civils) appliqués en silence côté carte et **hors journal radio** du TOC
- Variantes anciennes (auteur « REGLAGES » + corps « AFFICHAGE|… ») également filtrées
- Sync Blue Force / positions : hors journal d’activité par défaut

#### Messages de groupe vs canal HQ
- Les **messages de groupe** restent en jeu et **ne spamment plus** le journal radio web du TOC
- Canal officiel jeu → TOC : destinataire **HQ**

#### Ordres / signaux terminal
- Types « Faire vibrer » et « Notifier » traités comme **signaux terminal**, distincts des ordres de manœuvre

### Corrigé

- Confusion repère / effectif : libellé court type indicatif préfixé **« Repère · … »** ; ne remplace plus le symbole Blue Force
- Messages techniques d’affichage camps filtrés côté serveur et interface
- Contacts hors liaison : vibration / notification refusées avec message opérateur compréhensible
- Demande de renfort (QRF) sans position de contact : réponse **400** avec message métier (« Indiquez la position du contact pour demander le renfort ») au lieu d’un échec opaque

### Stabilité & ACE (volet jeu Overwatch)
- REAPP / respawn durci ; menu ACE ATAK rebranché ; NDA qui ne revient plus à chaque lancement
- Terminal ATAK requis par défaut ; features prioritairement dans ATAK Enhanced / cTab
- Adresse mobile + code d’appariement sur l’écran de liaison téléphone
- Briefing / diaporama, demande d’appui aérien (CAS) et manifeste de vol branchés côté mod (formulaires dédiés + viewer 9-lignes) — détail Steam / changelog Overwatch

### Déploiement prod (Athena WEB — 26/07/2026)

- FTP Hostinger → `athena.ttrd.fr` (`/domains/athena.ttrd.fr/public_html/`)
- Assets synchronisés en dual : `assets/` **et** `public/assets/` (JS + CSS)
- Cache-bust TOC : `views/atak.php` — `?v=202607261735` sur scripts / styles critiques
- Contrôle post-upload : ping HTTPS **200** ; JS vérifiés (vibrer, diamants, suivi, filtre réglages)
- Périmètre web : carte / marqueurs, unités BFT, menu vibrer, tchat filtré, contrôleur & dépôts ATAK, routes
- `.env` non modifié ; pas de rebuild PBO dans ce déploiement web (packs déjà rebuild côté mod)

Voir aussi `mod/UptoDate/STEAM_CHANGELOG.txt` (texte Steam) et `mod/UptoDate/@COMSPECOverwatch/CHANGELOG.md`.

---

## [1.2.0] - 2026-07-24

### Ajouté - Phase 2.5 : Intelligence & Automatisation

[Contenu complet Phase 2.5 déjà inséré ci-dessus]

### Ajouté - Phase MOD : Intégration Arma 3

[Contenu complet Phase MOD déjà inséré ci-dessus]

---

## [1.0.0] - 2026-07-24

### Ajouté - Phase 1 : Fondations coordination

#### Système de rapports tactiques structurés
- **Tables** : `atak_tactical_reports`, `atak_report_attachments`, `atak_report_templates`
- **Vue** : `v_atak_tactical_reports`
- **Repository** : `AtakTacticalReportRepository` avec 9 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atak/reports` : Liste rapports avec filtres (type, priorité, statut, émetteur, dates)
  - `POST /api/atak/reports` : Créer rapport (SPOTREP, SITREP, SALUTE, CONTACT)
  - `GET /api/atak/reports/{id}` : Détail rapport avec attachements
  - `POST /api/atak/reports/{id}/acknowledge` : Acquitter rapport
- **Features** :
  - Support 4 types : SPOTREP, SITREP, SALUTE, CONTACT
  - Génération automatique numéro rapport (`SPOTREP-20260724-001`)
  - Données structurées JSON (SALUTE : Size, Activity, Location, Unit, Time, Equipment)
  - Classification : UNCLASSIFIED, RESTRICTED, CONFIDENTIAL, SECRET
  - Priorités : ROUTINE, PRIORITY, IMMEDIATE, FLASH
  - Statuts : DRAFT, SUBMITTED, ACKNOWLEDGED, ACTIONED, ARCHIVED
  - Système visibilité : ALL, COMMAND, RESTRICTED, PRIVATE
  - Géolocalisation (pos_x, pos_y, grid_reference)
  - Multi-tenant et context-aware

#### Système POI (Points d'Intérêt) tactiques
- **Tables** : `atak_poi`, `atak_poi_observations`, `atak_poi_photos`
- **Vue** : `v_atak_poi` avec compteurs observations/photos
- **Repository** : `AtakPoiRepository` avec 10 méthodes publiques
- **API** : 3 endpoints REST
  - `GET /api/atak/poi` : Liste POI avec filtres (catégorie, affiliation, statut, menace)
  - `POST /api/atak/poi` : Créer POI
  - `PUT /api/atak/poi/{id}` : Mettre à jour POI
- **Features** :
  - 13 catégories : OBJECTIVE, BUILDING, CACHE, ENEMY_POSITION, HVT, PATROL_BASE, CHECKPOINT, STRUCTURE, INFRASTRUCTURE, ROUTE, TERRAIN, HAZARD, OTHER
  - Affiliation : FRIENDLY, ENEMY, NEUTRAL, UNKNOWN
  - Certitude : CONFIRMED, PROBABLE, POSSIBLE, DOUBTFUL
  - Niveau menace : NONE, LOW, MEDIUM, HIGH, CRITICAL
  - Statuts : ACTIVE, NEUTRALIZED, DESTROYED, ABANDONED, OCCUPIED, UNDER_SURVEILLANCE
  - Recherche proximité géographique (`findNearPosition`)
  - Historique observations multiples
  - Photos géolocalisées
  - Source fiabilité (échelle A-F NATO)
  - Génération automatique code POI

#### Zones tactiques enrichies
- **Tables** : `atak_tactical_zones`, `atak_zone_alerts`
- **Vue** : `v_atak_active_zones` avec calcul `is_currently_active`
- **Repository** : `AtakTacticalZoneRepository` avec 14 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atak/zones` : Liste zones avec filtres
  - `POST /api/atak/zones` : Créer zone
  - `POST /api/atak/zones/check-position` : Vérifier position dans zones
  - `GET /api/atak/zones/alerts` : Liste alertes non acquittées
- **Features** :
  - 9 types zones : LZ, DZ, OBJECTIVE, DANGER_ZONE, NO_GO_AREA, PATROL_AREA, SECTOR, BOUNDARY, OTHER
  - 3 géométries : CIRCLE, RECTANGLE, POLYGON
  - Algorithmes géométriques :
    - `isInCircle()` : Calcul distance euclidienne
    - `isInRectangle()` : Test rotation + bounds
    - `isInPolygon()` : Ray casting algorithm
  - Système alertes entrée/sortie configurable
  - Sons alertes personnalisables
  - Temporalité (`active_from`, `active_until`)
  - Priorités et niveaux menace
  - Style visuel (couleurs, opacité, contours)
  - Log détaillé alertes avec position exacte

### Ajouté - Phase 2 : Capacités spécialisées

#### Extension système MEDEVAC 9-Line avec triage TCCC
- **Tables** : `atak_medevac_requests`, `atak_medevac_patients`, `atak_medevac_status_updates`
- **Vue** : `v_atak_active_medevac` avec golden hour et patients
- **Triggers** :
  - `trg_medevac_golden_hour` : Calcul automatique golden hour pour patients T1
  - `trg_medevac_status_log` : Logging changements statut
- **Repository** : `AtakMedevacRepository` avec 12 méthodes publiques
- **API** : 6 endpoints REST
  - `GET /api/atak/medevac` : Liste MEDEVAC
  - `POST /api/atak/medevac` : Créer demande MEDEVAC 9-Line
  - `GET /api/atak/medevac/{id}` : Détail avec patients
  - `PATCH /api/atak/medevac/{id}/status` : Mettre à jour statut
  - `POST /api/atak/medevac/{id}/assign` : Assigner asset
  - `POST /api/atak/medevac/{id}/patients` : Ajouter patient
- **Features** :
  - Format 9-Line NATO complet
  - Triage TCCC : T1 (urgent), T2 (urgent surgical), T3 (delayed), T4 (expectant)
  - Golden hour tracking automatique (T1)
    - Calcul expiration : request_time + 60min
    - Statut : `OK` (> 30min), `WARNING` (15-30min), `CRITICAL` (< 15min), `EXPIRED` (> 60min)
    - Minutes restantes calculées
  - Catégories patients : Litter vs Ambulatory
  - Équipement spécialisé : hoist, ventilator, blood, etc.
  - Statut sécurité LZ : NO_ENEMY, POSSIBLE_ENEMY, ENEMY_IN_AREA, ENEMY_SUPPRESSED
  - Marquage LZ : NONE, SMOKE, PANEL, STROBE, FLARE, VS17, MIRROR
  - Contamination NBC tracking
  - Workflow complet : REQUESTED → ACKNOWLEDGED → ASSIGNED → INBOUND → ON_SITE → EVACUATING → COMPLETED
  - Historique changements statut avec timestamps
  - Données médicales détaillées par patient :
    - Conscience : ALERT, VERBAL, PAIN, UNRESPONSIVE
    - Respiration : NORMAL, ABNORMAL, ABSENT
    - Circulation : NORMAL, COMPROMISED, ABSENT
    - Blessures structurées (location, type, severity)
    - Traitements appliqués
    - Médicaments administrés (nom, dose, heure)

#### Système QRF (Quick Reaction Force)
- **Tables** : `atak_qrf_requests`, `atak_qrf_sitrep_updates`, `atak_qrf_waypoints`
- **Vue** : `v_atak_active_qrf` avec distance et urgence
- **Trigger** : `trg_qrf_urgency_deadline` : Calcul deadline urgence
- **Repository** : `AtakQrfRepository` avec 13 méthodes publiques
- **API** : 5 endpoints REST
  - `GET /api/atak/qrf` : Liste QRF
  - `POST /api/atak/qrf` : Créer demande QRF
  - `POST /api/atak/qrf/{id}/assign` : Assigner QRF
  - `POST /api/atak/qrf/{id}/position` : Mettre à jour position QRF
  - `POST /api/atak/qrf/{id}/sitrep` : Ajouter SITREP
- **Features** :
  - Types menace : AMBUSH, ATTACK, TROOPS_IN_CONTACT, CASEVAC_URGENT, IED_STRIKE, OTHER
  - Taille ennemi : FIRE_TEAM, SQUAD, PLATOON, COMPANY, UNKNOWN
  - Statut unité amie : SECURE, ENGAGED, PINNED, OVERRUN, RETREATING
  - Workflow : REQUESTED → ACKNOWLEDGED → QRF_ASSIGNED → QRF_ENROUTE → QRF_ENGAGED → SITUATION_STABILIZED → COMPLETED
  - Tracking position QRF temps réel
  - Calcul distance vers zone contact (formule euclidienne)
  - ETA dynamique
  - SITREP multi-source (demandeur + QRF)
  - Types SITREP : STATUS_CHANGE, POSITION_UPDATE, SITUATION_UPDATE, CONTACT_REPORT
  - Waypoints route QRF
  - Deadline urgence (FLASH : 5min, IMMEDIATE : 15min, PRIORITY : 30min)
  - Support demandé multiples : infantry, armor, aviation, cas, medevac, eod, engineers

#### Suivi véhicules et assets lourds enrichi
- **Tables** : `atak_vehicle_tracking`, `atak_vehicle_position_history`, `atak_vehicle_events`, `atak_vehicle_service_requests`
- **Vue** : `v_atak_active_vehicles` avec statut fuel/ammo
- **Triggers** :
  - `trg_vehicle_deployed` : Logging déploiement
  - `trg_vehicle_destroyed` : Logging destruction
- **Repository** : `AtakVehicleTrackingRepository` avec 16 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atek/vehicles` : Liste véhicules
  - `POST /api/atak/vehicles` : Upsert véhicule (create or update intelligent)
  - `POST /api/atak/vehicles/{id}/service` : Demander service
  - `GET /api/atak/vehicles/service-requests` : Liste demandes service
- **Features** :
  - 10 classes véhicules : LIGHT_VEHICLE, TRUCK, APC, IFV, TANK, ARTILLERY, HELICOPTER, FIXED_WING, UAV, BOAT
  - Côté : BLUFOR, OPFOR, INDEPENDENT, CIVILIAN
  - Statuts : OPERATIONAL, DAMAGED, IMMOBILIZED, DESTROYED, ABANDONED
  - Types mission : TRANSPORT, COMBAT, RECON, SUPPLY, MEDEVAC, CAS, CAP, PATROL, LOGISTICS
  - Tracking complet :
    - Position GPS (pos_x, pos_y)
    - Cap et vitesse
    - Fuel % (alerte < 20%)
    - Munitions % (alerte < 30%)
    - Santé composants : moteur, coque, chenilles/roues, tourelle
  - Upsert intelligent par callsign :
    - Si véhicule existe : mise à jour sélective (seulement champs fournis)
    - Si nouveau : création complète
    - Update automatique `last_seen_at`
  - Historique positions (table séparée pour replay)
  - Événements automatiques :
    - DEPLOYED, DESTROYED, DAMAGED, REPAIRED, ABANDONED, RECOVERED, REFUELED, REARMED
  - Demandes service :
    - Types : REFUEL, REARM, REPAIR, MAINTENANCE, RECOVERY
    - Priorités : LOW, MEDIUM, HIGH, CRITICAL
    - Statuts : REQUESTED, ACKNOWLEDGED, IN_PROGRESS, COMPLETED, CANCELLED
  - Calcul distance vers destination
  - Statut "véhicule actif" (vu < 30min)
  - Labels fuel/ammo : CRITICAL, LOW, MEDIUM, OK, FULL
  - Équipage et passagers tracking

### Documentation

#### Guides utilisateur et intégration
- **`docs/GUIDE-INTEGRATION-API-ATAK.md`** : Guide complet 31 endpoints
  - Exemples SQF pour mod Arma
  - Exemples JavaScript pour interface web
  - Formats requêtes/réponses détaillés
  - Codes erreurs
  - Notes performance et sécurité

#### Documentation technique
- **`docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md`** : Synthèse technique complète
  - Architecture système
  - Détails base de données (15 tables, 5 vues, 4 triggers)
  - Pattern repositories
  - Sécurité et performance
  - Tests recommandés
  - Roadmap Phase 3-5

#### Proposition features
- **`docs/NOUVELLES-FEATURES-ATAK-MOD.md`** : Proposition 15 features sur 5 phases
  - Features détaillées avec cas d'usage
  - Notes implémentation
  - Priorités P0/P1/P2

#### Documentation produit
- **`docs/COMPARAISON-PRODUIT-COMSPEC-CTAB-SIT.md`** : Comparaison produits
- **`docs/ATAK-WEB-DOCUMENTATION-PRODUIT.md`** : Doc ATAK Web
- **`docs/ATHENA-MYTHOLOGIE.md`** : Lien mythologique
- **Variantes forum** : `*-VERSION-FORUM.md` (sans URLs/tableaux)

### Migration

#### Scripts SQL
- **`migrations/2026_07_24_001_atak_tactical_reports.sql`** : Phase 1.1
- **`migrations/2026_07_24_002_atak_poi_intelligence.sql`** : Phase 1.2
- **`migrations/2026_07_24_003_atak_tactical_zones.sql`** : Phase 1.3
- **`migrations/2026_07_24_004_atak_medevac_extended.sql`** : Phase 2.1
- **`migrations/2026_07_24_005_atak_qrf_system.sql`** : Phase 2.2
- **`migrations/2026_07_24_006_atak_vehicle_tracking.sql`** : Phase 2.3

Toutes les migrations :
- Sont idempotentes (`IF NOT EXISTS`)
- Commentées en détail
- Incluent contraintes FK
- Définissent index stratégiques
- Multi-tenant natives

### Sécurité

#### Multi-tenant
- Isolation complète par `tenant_id` + `context_id`
- Contraintes FK vers `tenants` et `contextes`
- Filtrage systématique dans repositories

#### Soft delete
- Implémenté sur : reports, POI, zones
- Permet restauration et audit
- Vues filtrent automatiquement

#### Protection SQL
- Requêtes préparées PDO
- Pas de concaténation SQL
- Paramètres bindés

### Performance

#### Optimisations base de données
- Index composites sur (tenant_id, context_id)
- Index géographiques sur (pos_x, pos_y)
- Colonnes calculées STORED
- Vues enrichies pour éviter N+1 queries

#### Optimisations API
- Pagination par défaut (limit: 100-200)
- Filtres côté SQL
- Sélection colonnes via vues

### Ajouté - Phase 2.5 : Intelligence & Automatisation

#### Auto-routage intelligent des rapports
- **Tables** : `atak_report_routing_rules`, `atak_report_routing_history`
- **Repository** : `AtakReportRoutingRepository` avec 7 méthodes publiques
- **Features** :
  - Règles routage configurables par tenant
  - Critères : type rapport, priorité, mots-clés, position géographique
  - Distribution automatique aux destinataires pertinents (rôles, utilisateurs)
  - Escalade temporelle (ROUTINE : +24h, PRIORITY : +4h, IMMEDIATE : +1h)
  - Historique complet distributions
  - État lecture/accusé réception par destinataire
  - Méthode `applyRoutingRules()` : matching intelligent
  - Méthode `routeReport()` : distribution multi-canal

#### Calcul dynamique menace zones
- **Tables** : `atak_zone_events`
- **Repository** : `AtakZoneThreatRepository` avec 9 méthodes publiques
- **Trigger** : `trg_zone_threat_recalc` : Recalcul automatique après événement
- **Vue enrichie** : `v_atak_zone_threat` avec `current_threat_level`
- **Features** :
  - Événements trackés : CONTACT, EXPLOSION, GUNFIRE, IED, CASUALTY, OBSERVATION
  - Impact événement sur score menace (CONTACT: +30, EXPLOSION: +40, IED: +50)
  - Expiration temporelle (2h par défaut, décroissance progressive)
  - Prise en compte POI hostiles dans rayon 500m
  - Seuils adaptatifs : LOW (<30), MEDIUM (30-60), HIGH (60-80), CRITICAL (>80)
  - Recalcul automatique toutes zones affectées
  - Méthode `calculateThreatImpact()` : formule complexe
  - Méthode `countNearbyThreats()` : agrégation géospatiale

#### Notifications temps réel enrichies
- **Table** : `atak_realtime_notifications`
- **Repository** : `AtakNotificationRepository` avec 8 méthodes publiques
- **Features** :
  - Types : REPORT, MEDEVAC, QRF, VEHICLE, ZONE, GENERAL
  - Priorités : INFO, WARNING, URGENT, CRITICAL
  - Destinataires : ALL, COMMAND, SPECIFIC_ROLE, SPECIFIC_USER
  - Expiration automatique (TTL configurable)
  - État lu/non-lu par utilisateur
  - Polling endpoint `/api/atak/notifications/poll?since={timestamp}`
  - Notifications spécialisées :
    - Golden hour warnings (< 15min)
    - Alertes véhicule critique (fuel <5%, dégâts >70%)
    - Zone menace CRITICAL dépassée
    - Nouveaux rapports IMMEDIATE/FLASH
  - Cleanup automatique notifications expirées

#### Scoring urgence MEDEVAC & Asset optimal
- **Table** : `atak_medical_assets`
- **Repository** : `AtakMedevacIntelligenceRepository` avec 8 méthodes publiques
- **Features** :
  - **Scoring urgence patients** :
    - Patients T1 (urgent) : +50 points
    - Golden hour < 30min : +30 points
    - Zone pickup menace HIGH/CRITICAL : +20 points
    - Conditions météo défavorables : -10 points
    - Formule finale : `urgency_score = base_score + modifiers`
  - **Sélection asset optimal** :
    - Calcul ETA précis (distance euclidienne + vitesse asset)
    - Capacité patients (litter vs ambulatory)
    - Disponibilité temps réel
    - Risque trajet (zones menace traversées)
    - Méthode `findOptimalAsset()` : algorithme complet
    - Score asset : `(100 - distance_score) + capacity_score - risk_score`
  - **Évaluation menace LZ** :
    - Comptage POI hostiles <500m
    - Événements récents zone
    - Niveau menace zone tactique
    - Retour : NONE, LOW, MEDIUM, HIGH, CRITICAL
  - **Vue enrichie** : `v_atak_medevac_urgency` avec score et asset recommandé

#### Route QRF optimale
- **Table** : `atak_qrf_coordination`
- **Repository** : `AtakAdvancedIntelligenceRepository` (méthodes QRF)
- **Features** :
  - **Calcul route optimale** :
    - Algorithme A* avec pénalités zones menace
    - Évitement NO-GO zones (pénalité infinie)
    - Contournement zones HIGH threat (+40% distance)
    - Préférence zones LOW threat (-10% distance)
    - Méthode `calculateOptimalQrfRoute()` : pathfinding complet
  - **Génération waypoints** :
    - Waypoints intermédiaires espacés 500m
    - Ordre séquence automatique
    - Calcul distance totale route
    - ETA basé vitesse unité
    - Méthode `generateWaypoints()` : interpolation intelligente
  - **Hazards route** :
    - Détection obstacles/menaces sur trajet
    - Liste zones menace traversées
    - Suggestions contournement
    - Méthode `findHazardsAlongRoute()` : analyse géospatiale
  - **Coordination multi-QRF** :
    - Évite duplication efforts
    - Suggestions split objectifs
    - Calcul distance inter-QRF
    - Table `atak_qrf_coordination` pour sync

#### Maintenance prédictive véhicules
- **Table** : `atak_vehicle_maintenance_log`
- **Repository** : `AtakAdvancedIntelligenceRepository` (méthodes véhicules)
- **Vue enrichie** : `v_atak_vehicle_health` avec `maintenance_score`
- **Features** :
  - **Calcul score maintenance** :
    - Distance parcourue depuis dernière maintenance (40% poids)
    - Historique pannes/dommages (30% poids)
    - Santé composants actuels (30% poids)
    - Formule : `score = distance_factor * 0.4 + history_factor * 0.3 + health_factor * 0.3`
    - Échelle 0-100 (100 = maintenance urgente)
  - **Prédiction panne** :
    - Temps estimé avant panne critique
    - Basé tendance dégradation composants
    - Machine learning simple (régression linéaire)
    - Méthode `predictFailureTime()` : extrapolation
  - **Recommandations automatiques** :
    - "Maintenance préventive sous 48h" (score 60-80)
    - "Inspection moteur urgente" (engine_health < 50%)
    - "Remplacement chenilles recommandé" (track_health < 40%)
    - "Véhicule à immobiliser" (score > 90)
    - Méthode `generateMaintenanceRecommendations()` : règles métier
  - **Log maintenance détaillé** :
    - Type : ROUTINE, PREVENTIVE, CORRECTIVE, EMERGENCY, INSPECTION
    - Composants concernés
    - Pièces remplacées
    - Coût et durée
    - Technicien et lieu

#### Corrélation POI intelligence
- **Table** : `atak_poi_correlations`, `atak_intelligence_analysis`
- **Repository** : `AtakAdvancedIntelligenceRepository` (méthodes POI)
- **Vue enrichie** : `v_atak_poi_enriched` avec `confidence_score`
- **Features** :
  - **Détection corrélations** :
    - Proximité géographique (<500m)
    - Affiliation identique (ENEMY)
    - Compatibilité type (CACHE ↔ ENEMY_POSITION)
    - Temporalité (observations <24h)
    - Méthode `detectPoiCorrelations()` : matching multi-critères
  - **Scoring confiance POI** :
    - Nombre observations (20% poids)
    - Source fiabilité (30% poids)
    - Fraîcheur temporelle (25% poids)
    - Corrélations avec autres POI (25% poids)
    - Formule : `confidence = obs_score + source_score + fresh_score + corr_score`
    - Échelle 0-100 (100 = haute confiance)
    - Méthode `calculatePoiConfidence()` : algorithme complet
  - **Analyse intelligence** :
    - Patterns détectés (clusters hostiles)
    - Suggestions tactiques ("Surveillance zone recommandée")
    - Niveau confiance analyse
    - Méthode `analyzePoiPair()` : corrélation détaillée
  - **Types corrélation** :
    - PROXIMITY : Proximité géographique simple
    - PATTERN : Pattern comportemental
    - ACTIVITY : Activité liée
    - TEMPORAL : Séquence temporelle
    - NETWORK : Réseau hostile

### Migration Phase 2.5

#### Script SQL
- **`migrations/2026_07_24_007_atak_intelligence_enhancements.sql`** : Phase 2.5
  - 9 nouvelles tables
  - 5 vues enrichies
  - 1 trigger calcul menace
  - ~400 lignes SQL commentées
  - Alter tables existantes (ajout colonnes calculées)

### Documentation Phase 2.5

#### Guide technique enrichissements
- **`docs/PHASE-2.5-INTELLIGENCE-ENRICHMENTS.md`** : Documentation complète
  - Détails 6 capacités intelligence
  - Algorithmes expliqués (pseudocode)
  - Cas d'usage opérationnels
  - Diagrammes workflow
  - ~1000 lignes

#### Mise à jour guides existants
- **`docs/GUIDE-INTEGRATION-API-ATAK.md`** : Ajout endpoints Phase 2.5
- **`docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md`** : Mise à jour stats (15 tables → 24 tables)
- **`CHANGELOG-ATAK.md`** : Mise à jour version 1.1.0

### Ajouté - Phase MOD : Intégration Arma 3

#### Fonctions SQF tactiques (11 fichiers, ~800 lignes)
- **Localisation** : `mod/@COMSPECOverwatch/addons/connect/functions/`
- **Fonctions principales** :
  - `fn_submitTacticalReport.sqf` : Soumettre rapport (SPOTREP, CONTACT, SITREP, SALUTE)
    - Validation type rapport
    - Sérialisation données structurées
    - Appel extension HTTP
    - Feedback visuel (hint + son confirmation)
    - Logging RPT détaillé
  - `fn_createPOI.sqf` : Créer POI
    - Position automatique (player ou cursorTarget)
    - Marker local temporaire (5min)
    - Catégories prédéfinies
    - Transmission immédiate backend
  - `fn_requestMEDEVAC.sqf` : Demander MEDEVAC 9-Line
    - Format standard NATO
    - Calcul automatique patients litter/ambulatory
    - Intégration ACRE/TFAR (fréquence radio)
    - Hints critiques visuels
    - Son radio transmission
  - `fn_requestQRF.sqf` : Demander QRF
    - Types menace standardisés
    - Estimation force amie automatique (count units group)
    - Suggestions support selon situation
    - Marker local contact (cercle rouge)
    - Son alerte urgence
  - `fn_updateVehicleTracking.sqf` : Update véhicule
    - Données complètes (callsign, classe, side, crew)
    - Position, heading, speed
    - Fuel %, ammo %, health composants
    - Détection automatique état critique
    - Trigger service requests si nécessaire
  - `fn_requestVehicleService.sqf` : Service véhicule
    - Types : REFUEL, REARM, REPAIR, MAINTENANCE, RECOVERY
    - Feedback priorité (fumée verte si CRITICAL)
    - Marker temporaire service
    - Son alerte si urgent
  - `fn_initVehicleTracking.sqf` : Init tracking auto
    - Event handlers : GetInMan, GetOutMan, Killed
    - CBA PerFrameHandler (update 10s)
    - Start/stop automatique
    - Report destruction immédiat
- **Fonctions helpers** :
  - `fn_hashMapToJson.sqf` : Sérialisation HashMap → JSON
    - Support types : STRING, SCALAR, BOOL, ARRAY, HASHMAP
    - Récursif pour structures imbriquées
    - Échappement strings correct
    - ~100 lignes
  - `fn_formatTimestamp.sqf` : Format timestamp SQL
    - Input : systemTime array Arma
    - Output : `YYYY-MM-DD HH:MM:SS`
    - Padding zéros

#### Système menus ACE Interact (~250 lignes)
- **Fichiers** :
  - `fn_initATAKMenu.sqf` : Création menus ACE
  - `fn_initATAK.sqf` : Initialisation principale
  - `XEH_postInitClient.sqf` : Hook CBA Extended Event Handlers
- **Structure menus** :
  ```
  ACE Self-Interact → 📡 ATAK Tactique
    ├─ 📝 Rapports Tactiques
    │   ├─ SPOTREP (Observation)
    │   ├─ CONTACT (Ennemi) [priorité IMMEDIATE auto]
    │   └─ SITREP (Situation)
    ├─ 📍 Marquer POI
    │   ├─ Cache d'armes (ENEMY, PROBABLE)
    │   ├─ Position Ennemie (CONFIRMED)
    │   └─ Objectif (NEUTRAL)
    ├─ 🚁 Demander Appui
    │   ├─ MEDEVAC (9-Line, transmission radio auto)
    │   └─ QRF (marker contact + alerte)
    └─ 🔧 Service Véhicule [si dans véhicule]
        ├─ ⛽ Ravitaillement [si fuel <30%]
        ├─ 🔫 Réarmement
        └─ 🔨 Réparation [si damage >20%]
  ```
- **Conditions dynamiques** :
  - Sous-menu véhicule uniquement si `vehicle player != player`
  - Actions service selon état véhicule (fuel, damage)
- **Feedback visuel** :
  - Hints notifications structurées
  - Markers locaux temporaires (POI, contact, service)
  - Fumée signalisation (verte pour demandes critiques)
  - Sons : radio, alerte, confirmation
- **Raccourcis clavier CBA** :
  - **Shift+R** : Rapport contact rapide (`comspec_atak_quick_report`)
  - **Shift+P** : POI rapide position actuelle (`comspec_atak_quick_poi`)
- **Initialisation automatique** :
  - Vérification extension au boot (`GetVersion`)
  - Init tracking véhicules
  - Création menus ACE (si addon détecté)
  - Event handlers respawn (réinit système)
  - Boucle maintenance 60s (polling futures)

#### Extension C# v2.0 (~350 lignes)
- **Localisation** : `mod/@COMSPECOverwatch/extension-source-example/`
- **Fichiers** :
  - `ExtensionMain.cs` : Code principal
  - `COMSPECExtension.csproj` : Projet .NET 6
  - `build.sh` / `build.bat` : Scripts compilation
  - `README.md` : Documentation complète
- **Architecture** :
  ```
  Arma 3 (SQF)
    ↓ callExtension "COMSPECExtension"
  RVExtensionArgs (C# DLL)
    ↓ ProcessCommand(command, jsonData)
  SendHttpRequest(method, endpoint, json)
    ↓ HttpClient.PostAsync()
  Backend API PHP
  ```
- **Commandes implémentées** :
  - `GetVersion` → Retourne "2.0"
  - `Connect` → Init API (URL + Token)
  - `SubmitTacticalReport` → POST `/api/atak/reports`
  - `CreatePOI` → POST `/api/atak/poi`
  - `RequestMEDEVAC` → POST `/api/atak/medevac`
  - `RequestQRF` → POST `/api/atak/qrf`
  - `UpdateVehicleTracking` → POST `/api/atak/vehicles`
  - `RequestVehicleService` → POST `/api/atak/vehicles/service`
- **Optimisations** :
  - HttpClient singleton (connection pooling)
  - Retry policy 3x avec backoff exponentiel (5xx seulement)
  - Timeout 10s configurable
  - Cache vehicle_id local (évite lookups répétés)
  - Logs détaillés : `%LOCALAPPDATA%\Arma 3\COMSPECExtension.log`
- **Gestion erreurs** :
  - Codes retour : `OK`, `ERROR`, `NETWORK_ERROR`, `TIMEOUT`
  - Messages structurés JSON : `["status", "message"]`
  - Logging toutes erreurs avec timestamp
- **Sécurité** :
  - Tokens passés dynamiquement (config CBA)
  - Headers `X-ATAK-Token`, `User-Agent`
  - HTTPS obligatoire production
  - Validation inputs côté SQF avant envoi
- **Technologies** :
  - .NET 6.0
  - Newtonsoft.Json (sérialisation)
  - UnmanagedExports (export DLL pour Arma)

#### Configuration et intégration
- **`mod/@COMSPECOverwatch/addons/connect/config.cpp`** (modifié) :
  - Déclarations 11 nouvelles fonctions
  - Hook CBA Extended Event Handlers :
    ```cpp
    class Extended_PostInit_EventHandlers {
        class comspec_overwatch_connect {
            clientInit = "call compile preprocessFileLineNumbers '...XEH_postInitClient.sqf'";
        };
    };
    ```
  - Version bumped : 1.1.3 → 1.2.0
- **Prérequis** :
  - Arma 3
  - CBA A3 (obligatoire)
  - ACE3 (recommandé pour menus)
  - Extension `COMSPECExtension_x64.dll` v2.0
- **Configuration CBA** :
  - **Option A : Liaison rapide** (recommandée)
    1. Athena → ATAK → "Générer code liaison"
    2. En jeu : K → "Connecter compte" → coller code
  - **Option B : Manuelle**
    - URL : `https://athena.ttrd.fr/public`
    - Token ATAK : Généré sur interface web
    - ID Communauté : Fourni par admin
- **BattlEye** :
  - Whitelist DLL dans `battleye/beserver_x64.cfg` :
    ```cpp
    allowedLoadFileExtensions[] = {"dll"};
    allowedPreloadFileExtensions[] = {"dll"};
    ```

### Documentation MOD

#### Guides utilisateur (4 documents, ~2000 lignes)
- **`mod/@COMSPECOverwatch/README.md`** : Documentation principale enrichie
  - Section complète features ATAK détaillées
  - Raccourcis clavier (ajout Shift+R, Shift+P)
  - Menus ACE avec hiérarchie complète
  - Tracking véhicules automatique
  - Prérequis et installation
- **`mod/@COMSPECOverwatch/GUIDE-INSTALLATION-TEST.md`** : Guide complet
  - Installation étape par étape (mod, config CBA, extension)
  - 7 tests manuels détaillés (rapport, POI, MEDEVAC, QRF, tracking, service)
  - Script test automatique SQF
  - Troubleshooting complet (extension not found, 401, menus absents, tracking inactif)
  - Checklist déploiement production
- **`mod/@COMSPECOverwatch/EXTENSION_C#_SPECIFICATION.md`** : Spec technique extension
  - Détails 8 commandes avec formats JSON
  - Exemples C# implémentation
  - Configuration HTTP client (headers, timeout, retry)
  - Gestion erreurs et logging
  - Optimisations recommandées (cache, batching, async)
  - Tests recommandés (unitaires + intégration)
  - Compilation et déploiement
- **`mod/@COMSPECOverwatch/extension-source-example/README.md`** : Doc extension
  - Prérequis développement (Visual Studio, .NET 6)
  - Commandes disponibles avec exemples
  - Architecture code (diagramme)
  - Performance et optimisations
  - Contribution et ajout nouvelles commandes
  - Sécurité (tokens, HTTPS)
  - Troubleshooting compilation

### Documentation projet

#### Synthèses et récapitulatifs
- **`docs/RECAPITULATIF-INTEGRATION-MOD-ATAK.md`** : Synthèse MOD
  - Composants livrés détaillés
  - Structure menus ACE complète
  - Statistiques code (~2900 lignes MOD)
  - Intégration backend (31 endpoints)
  - Tests et validation
  - Déploiement et configuration
- **`docs/SYNTHESE-FINALE-INTEGRATION-ATAK.md`** : Document maître (~850 lignes)
  - Vue d'ensemble Backend + MOD
  - Architecture complète (BDD, repos, API, SQF, extension)
  - Algorithmes intelligence Phase 2.5 détaillés
  - Statistiques globales (17100 lignes produites)
  - Fonctionnalités opérationnelles (joueurs + commandement)
  - Tests & validation
  - Guide déploiement complet
  - Roadmap futures phases

### Dépendances

#### Backend
- PHP >= 8.0
- MySQL >= 8.0
- Extension PDO MySQL
- Authentification COMSPEC existante

#### Frontend (en attente Phase JS)
- Leaflet.js
- JavaScript ES6+

#### Mod Arma 3
- ✅ CBA A3 (obligatoire)
- ✅ ACE3 (recommandé)
- ✅ Extension C# .NET 6
- ✅ SQF functions (11 fichiers)
- ✅ Arma 3 >= 2.0

---

## [À venir] - Phase 3 : Coordination avancée

### Planifié

#### Waypoints et routes partagées
- Table `atak_shared_waypoints`
- Synchronisation bidirectionnelle web ↔ jeu
- Calcul distance et temps estimé
- Routes partagées entre unités
- Visualisation temps réel sur carte

#### Timeline mission interactive
- Table `atak_mission_timeline`
- Agrégation tous événements (rapports, contacts, MEDEVAC, QRF, etc.)
- Filtres par type, unité, criticité
- Navigation temporelle
- Export PDF/Excel pour AAR

#### Contrôle artillerie et mortiers
- Table `atak_fire_missions`
- Calcul balistique (élévation, azimut, charge)
- Visualisation zone impact
- Workflow mission feu NATO
- Corrections tir (shot, splash, impact)

---

## [À venir] - Phase 4 : Capacités avancées

### Planifié

#### Système UAV et reconnaissance
- Table `atak_uav_tracking`
- Flux vidéo (captures périodiques)
- Détection automatique contacts
- Zones surveillance
- Handoff entre opérateurs

#### IFF avancé
- Extension système IFF existant
- Interrogation active
- Code du jour dynamique
- Alertes véhicule inconnu
- Intégration avec véhicules

#### Intégration météo opérationnelle
- Table `atak_weather_log`
- Impact visibilité/portée
- Alertes conditions critiques
- Prévisions mission
- Historique pour AAR

---

## [À venir] - Phase 5 : Immersion totale

### Planifié

#### Mode replay complet
- Reconstruction mission 3D
- Contrôles vidéo (play, pause, vitesse)
- Changement point de vue
- Export MP4
- Analyse post-mission

#### Système certifications LMS
- Intégration avec LMS existant
- Déblocage capacités selon certification
- Badges visibles in-game
- Progression utilisateur
- Rapports performance

#### Contrôle caméra et observation
- Stream images caméras terrain
- Demande vues spécifiques
- Contrôle PTZ (pan, tilt, zoom)
- Archive pour AAR
- Multi-flux simultanés

---

## Versionning

**Format** : [MAJOR.MINOR.PATCH]

- **MAJOR** : Changements incompatibles API
- **MINOR** : Ajout features rétrocompatibles
- **PATCH** : Corrections bugs rétrocompatibles

**Version actuelle** : 1.2.0 (Phases 1, 2, 2.5 + MOD Arma 3 complètes)

---

*Document maintenu par l'équipe développement COMSPEC*
