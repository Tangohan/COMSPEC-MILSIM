# Guide SSE — chef de mission, Zeus, automatismes

Comment préparer, piloter et exploiter une séquence **SSE** (exploitation de site sensible)
avec COMSPEC Overwatch.

Ce guide couvre trois choses :

1. ce que le chef de mission règle **avant** la partie (Eden) ;
2. ce que Zeus peut **imposer en cours** de partie ;
3. ce que le serveur fait **tout seul** — les automatismes — et pourquoi il ne fait pas plus.

Pour la manipulation du terminal côté joueur, voir
[terminal-sse-renseignement.md](terminal-sse-renseignement.md).
Pour l'architecture d'ensemble, voir
[../../docs/PLAN-COMSPEC-SSE-ARCHITECTURE.md](../../docs/PLAN-COMSPEC-SSE-ARCHITECTURE.md).

---

## 1. Le minimum pour que ça tourne

Trois conditions, dans cet ordre :

| Condition | Sans elle |
|---|---|
| Un **dossier ouvert** côté portail (`Athena → SSE → Dossiers`) | Les fiches partent non classées |
| Au moins un joueur portant le **terminal biométrique SEEK** | Aucune fiche n'est ouvrable |
| Des sujets à contrôler (PNJ civils, blessés, détenus) | Rien à exploiter |

Le dossier est ouvert par le poste de commandement, pas depuis le jeu : c'est lui qui
donne la référence (`SSE-2026-0007`) que le terrain va utiliser.

---

## 2. Préparation en Eden

### 2.1 Doter les joueurs en terminal

Objet à ajouter à l'équipement (sac, gilet ou uniforme) :

```
COMSPEC_Item_SeekTerminal
```

Depuis l'arsenal Eden : catégorie **Objets divers**, « Terminal biométrique SEEK ».
En script d'initialisation d'unité :

```sqf
this addItemToBackpack "COMSPEC_Item_SeekTerminal";
```

Un terminal par binôme d'exploitation suffit. En doter toute la section produit
l'effet inverse de celui recherché : tout le monde saisit, personne ne recoupe.

### 2.2 Régler un sujet — attributs d'unité

Sélectionnez le PNJ dans Eden, ouvrez ses attributs, catégorie
**COMSPEC — Exploitation SSE**. La catégorie n'apparaît que sur les unités.

| Attribut | Effet | Laisser vide / 0 / -1 |
|---|---|---|
| **Ce que la base doit répondre** | Impose le verdict de la requête d'identité | Génération automatique |
| **Nom**, **Prénom**, **Alias connu** | État civil proposé par le terminal | Génération automatique |
| **Nationalité déclarée** | Ce que le sujet déclare — pas ce qui est établi | Génération automatique |
| **Langue parlée** | Détermine si un interprète est nécessaire | Génération automatique |
| **Référence de dossier antérieur** | Affichée en cas de correspondance | Génération automatique |
| **Indice de confiance imposé** | Pourcentage affiché après requête | `-1` → calculé sur la qualité réelle des relevés |
| **Graine** | Fige le sujet d'une session à l'autre | `0` → dérivée de l'identifiant réseau |

Les quatre valeurs de **« Ce que la base doit répondre »** :

| Valeur | Ce que le terminal affiche | Quand l'utiliser |
|---|---|---|
| Génération automatique | Verdict stable dérivé de la graine du sujet | Cas normal, y compris pour la foule |
| Inconnu des bases | Aucune correspondance | Pour que « connu » veuille dire quelque chose |
| Signalé | Correspondance partielle, confiance ≈ 58 % | Le sujet mérite un entretien, pas une interpellation |
| Recherché | Correspondance confirmée, confiance ≈ 93 % | Le sujet que le scénario veut faire trouver |

> **Ne réglez pas tous vos PNJ.** La génération automatique produit déjà des sujets
> différents et stables. Réglez les deux ou trois qui portent le scénario, laissez le
> reste tel quel — c'est le bruit qui donne sa valeur au signal.

### 2.3 Graine : à quoi ça sert vraiment

La graine décide de tout ce qui est fictif sur un sujet : verdict, indice de confiance,
références de relevés. Elle est dérivée de l'identifiant réseau de l'unité, donc stable
pendant la session mais **différente à la session suivante**.

Fixez-la (n'importe quel entier > 0) dans deux cas :

- **scénario rejoué** — les mêmes sujets doivent donner les mêmes résultats d'une
  session à l'autre ;
- **séance de formation** — l'instructeur connaît d'avance ce que les stagiaires
  vont trouver.

Deux unités ne doivent pas partager la même graine : elles produiraient des relevés
identiques, et l'automatisme A2 les signalerait comme un doublon.

### 2.4 Modules Eden

Catégorie **COMSPEC SSE**. Les trois modules existent aussi côté Zeus (§ 3).

| Module | Ce qu'il fait |
|---|---|
| **Dossier SSE actif** | Impose la référence de dossier pour tout l'élément |
| **Profil d'identité SSE** | Applique un profil aux sujets attachés ou synchronisés |
| **Doter en terminal SEEK** | Place le terminal chez les joueurs désignés |

Le module « Profil » fait doublon avec les attributs d'unité du § 2.2 : les attributs
sont plus pratiques en préparation (un clic par sujet), le module est plus pratique
pour appliquer le **même** profil à un groupe entier d'un coup — synchronisez-le avec
autant de sujets que voulu.

---

## 3. En cours de partie — Zeus

Catégorie **COMSPEC SSE** dans l'arbre Zeus. Avec Zeus Enhanced, les mêmes trois
modules sont disponibles avec des boîtes de dialogue au lieu des attributs.

### 3.1 Dossier SSE actif

Ouvre une boîte demandant la référence. Elle est diffusée à **tous** les joueurs :
un opérateur qui rejoint la partie après la pose la reçoit sans manipulation.

Laisser le champ vide efface le dossier actif — les fiches suivantes repartent
non classées (et l'automatisme A1 les rattrapera s'il n'y a qu'un dossier ouvert).

### 3.2 Profil d'identité SSE

**Posez le module sur la personne**, pas sur le sol : le module refuse de s'appliquer
si vous ne désignez pas une unité, plutôt que de régler silencieusement le mauvais sujet.

La boîte reprend les mêmes champs qu'en Eden. Les champs laissés vides ne sont pas
écrits : vous pouvez ne forcer que l'alias d'un sujet sans réinventer sa nationalité.

**Usage typique** : un joueur est en train de contrôler quelqu'un, la scène prend une
tournure intéressante, vous décidez en direct que ce sujet est le contact recherché.
Posez le module, choisissez « Recherché », validez. La requête suivante remontera
la correspondance.

### 3.3 Doter en terminal SEEK

- Posé **sur un joueur** : ce joueur reçoit le terminal immédiatement.
- Posé **sur le sol** : boîte demandant un rayon, tous les joueurs dedans sont dotés.

Le terminal va dans le sac, sinon le gilet, sinon l'uniforme. Si tout est plein,
le joueur reçoit un message le lui disant — rien n'est jeté au sol.

Les PNJ ne sont jamais dotés : un terminal récupérable sur un cadavre ennemi n'est
pas l'effet recherché.

### 3.4 Modules en double dans l'arbre Zeus ?

Les modules sont déclarés une fois en configuration (`scopeCurator = 2`) et une fois
comme modules ZEN. L'enregistrement ZEN est **automatiquement ignoré** quand les
modules de configuration sont visibles, pour éviter le doublon.

Pour forcer malgré tout les variantes ZEN (boîtes de dialogue) :

```sqf
missionNamespace setVariable ["COMSPEC_ZenSseModulesForce", true];
```

---

## 4. Automatismes — ce que le serveur fait tout seul

Principe directeur : **un automatisme propose, il ne décide pas.** Il classe, il
signale, il rapproche. Il ne clôt jamais un site, ne déclare jamais une identité,
ne fusionne ni ne supprime aucune fiche.

La raison est simple : une règle qui se trompe en silence coûte plus cher que dix
rappels à faire à la main. Un rattachement erroné qui a l'air correct ne sera jamais
relu.

Chaque règle laisse une trace dans le **journal d'activité** du portail. On doit
pouvoir répondre à « pourquoi cette fiche est-elle dans ce dossier ? » sans lire le code.

| Règle | Déclencheur | Ce qu'elle fait | Ce qu'elle ne fait pas |
|---|---|---|---|
| **A1 — Classement automatique** | Fiche transmise sans code dossier | Rattache au dossier ouvert **s'il n'y en a qu'un** | Ne choisit jamais entre plusieurs dossiers |
| **A2 — Doublon probable** | Relevé biométrique déjà versé sous la même référence | Signale, pose une relation « même individu », note le dossier | Ne fusionne pas les fiches |
| **A3 — Correspondance forte** | Croisement liste de surveillance ≥ 85 % | Passe le dossier en exploitation, y dépose une note | N'affirme pas une identification |
| **A4 — Co-présence** | Fiches du même dossier saisies à moins de 45 min | Pose une relation « contrôlé en même temps que », en *non vérifié* | Ne relie pas au-delà de 5 fiches |
| **A5 — Site prêt pour clôture** | Toutes les pièces de la checklist fouillées | Signale que le compte rendu peut être rédigé | Ne clôt pas le site |
| **A6 — Saisie sensible** | Saisie d'armement, munitions, support numérique ou documents | Remonte immédiatement, sans attendre la clôture | Ne requalifie pas la saisie |

### 4.1 Pourquoi A1 ne classe qu'avec un seul dossier ouvert

Avec deux dossiers ouverts, la règle n'a aucun moyen de savoir lequel. Se tromper
de dossier est pire que ne rien classer : une fiche non classée se voit, une fiche
mal classée passe inaperçue jusqu'au débriefing.

**Conséquence pratique** : si vous ouvrez plusieurs dossiers en parallèle, posez le
module « Dossier SSE actif » — sinon vos fiches arriveront non classées.

### 4.2 Pourquoi A2 ne fusionne pas

La fusion détruirait la fiche la moins complète, sans que personne l'ait décidé. Or
c'est parfois la moins complète qui porte l'observation utile. Les deux fiches restent,
reliées par « même individu que ».

### 4.3 Pourquoi A4 pose du « non vérifié »

A4 constate une proximité d'horodatage, pas un lien. Deux personnes contrôlées à
trois minutes d'écart sont probablement du même contrôle — probablement, pas
certainement. La relation est posée pour que l'analyste la voie, marquée pour qu'il
ne la prenne pas pour un fait.

### 4.4 Régler les seuils

Ils sont dans `app/Services/Sse/SseAutomationService.php`, en tête de classe :

| Constante | Défaut | Effet |
|---|---|---|
| `HARD_MATCH_SCORE` | 85 | Seuil d'escalade A3 |
| `CO_PRESENCE_MINUTES` | 45 | Fenêtre de co-présence A4 |
| `CO_PRESENCE_MAX_LINKS` | 5 | Plafond de relations posées par fiche |

Baisser `CO_PRESENCE_MAX_LINKS` sur un scénario à forte densité de population :
un contrôle de vingt personnes ne produit pas 190 arêtes utiles, il produit du bruit.

---

## 5. Corrélation — lire le graphe

`Athena → SSE → Dossiers → [dossier] → Voir les corrélations`

La page montre trois natures de liens, **jamais confondues** :

| Nature | Origine | Fiabilité |
|---|---|---|
| **Déduit** | Recalculé à chaque ouverture depuis les saisies enregistrées | Confirmé — c'est une donnée, pas une hypothèse |
| **Automatisme** | Posé par une règle (A2, A4) | Corroboré ou non vérifié |
| **Analyste** | Posé à la main sur cette page | Ce que vous déclarez |

Les liens déduits ne sont pas stockés : corriger une fiche corrige le graphe.
C'est délibéré — un lien stocké se périme dès qu'une saisie est rectifiée, et
personne ne pense à aller le corriger.

Les entités sont classées par **nombre de liens**. C'est la seule hiérarchie que la
page impose : le sujet le plus relié mérite d'être regardé en premier.

> Un lien décrit une proximité constatée. Il ne vaut ni appartenance, ni implication,
> ni identification. La page le rappelle, et le compte rendu généré aussi.

---

## 6. Comptes rendus

`Athena → SSE → Dossiers → [dossier] → Ouvrir le compte rendu`

Deux documents, générés **à la lecture** depuis l'état réel du dossier :

- **Flash** — ce qui justifie d'interrompre le poste de commandement ;
- **Compte rendu initial** — Situation · Site · Personnel · Matériel · Faits marquants.

Ils ne sont pas figés à la rédaction : rouvrir la page après une correction donne
un document à jour. Le bouton « Copier » les met dans le presse-papier pour un
collage en TS, Discord ou courrier.

Le compte rendu de **clôture de site** (cinq lignes), lui, est figé à la clôture —
c'est un acte, pas une vue.

---

## 7. Déclassification et caviardage

`Athena → SSE → Dossiers → [dossier] → Version expurgée`

Le compte rendu de la section précédente est **intégral**. Pour le diffuser au-delà
du cercle qui détient le dossier — un allié, un échelon supérieur, un débriefing
ouvert — il faut une version expurgée.

### 7.1 Déclassification par niveau

Choisissez le niveau de diffusion visé. Tout ce qui est **au-dessus** de ce niveau
part au noir automatiquement.

| Catégorie | Lisible à partir de | Ce qu'elle couvre |
|---|---|---|
| **Identité** | Confidentiel | Nom, prénom, alias, naissance, nationalité, pièce d'identité |
| **Lieu** | Encadrement | Grilles, désignation des sites, pièces où les objets ont été trouvés |
| **Biométrie** | Confidentiel | Références de relevés, référence de dossier antérieur |
| **Source** | Diffusion très restreinte | Indicatif de l'opérateur, équipe, identifiant de terminal, signature |
| **Horodatage** | Encadrement | Heures précises — un enchaînement d'horaires reconstitue un itinéraire |

**La source est la catégorie la plus protégée**, et c'est délibéré : on peut souvent
se permettre de dire *ce qui* a été trouvé sans dire *qui* l'a trouvé, alors que
l'inverse est rarement vrai.

La page ouvre par défaut sur **Diffusion interne**, le niveau le plus large — donc
celui qui caviarde le plus. Ouvrir l'écran ne doit jamais exposer plus que ce qu'on
a demandé à voir.

Le tableau en tête de page dit, **avant** de produire le document, ce qui restera
en clair et ce qui partira au noir. On ne découvre pas ce qu'on a diffusé après
l'avoir diffusé.

### 7.2 Qui est habilité à lire quoi

Le niveau demandé dans l'écran est **rabattu sur l'habilitation de la session**. Le
paramètre de l'adresse exprime une demande, il n'accorde rien : quelqu'un qui le force
à la main obtient la version à laquelle il a droit, et la tentative part au journal.

Le plafond vient de deux sources, dans cet ordre.

**1. Habilitation explicite** — permissions à assigner aux rôles :

| Permission | Plafond accordé |
|---|---|
| `atak.sse.clearance.encadrement` | Encadrement |
| `atak.sse.clearance.confidentiel` | Confidentiel |
| `atak.sse.clearance.tres_restreint` | Diffusion très restreinte |

C'est la voie propre dès que vous voulez gérer vos habilitations à la main.

**2. Report des rôles existants** — si aucune habilitation explicite n'est accordée :

| Vous avez déjà | Plafond déduit |
|---|---|
| Administration du portail, ou droit d'octroi | Diffusion très restreinte |
| Gestion des dossiers | Confidentiel |
| Accès au portail SSE | Encadrement |
| Rien de tout ça | Diffusion interne |

Ce report est délibéré : sans lui, la mise à jour mettrait tout le monde au plancher
tant qu'un administrateur n'a pas assigné les nouvelles permissions, et personne ne
comprendrait pourquoi les dossiers sont devenus illisibles du jour au lendemain. Une
règle de sécurité qu'on désactive en urgence parce qu'elle a tout cassé ne protège
plus rien.

**Invités (code d'accès temporaire)** : le plafond est celui que **le code porte**.
Il se choisit à l'émission du code, écran « Accès et habilitations ». Par défaut,
Diffusion interne — un invité ne voit ni identité, ni lieu, ni source. On n'accorde
jamais par défaut de valeur, seulement par défaut de refus.

Le plafond est un plafond : il ne se lève pas en cours de session.

L'écran affiche votre habilitation et **d'où elle vient**. Une habilitation qu'on ne
peut pas expliquer se conteste mal : l'opérateur doit pouvoir dire à son encadrement
pourquoi il ne voit pas quelque chose.

### 7.3 Où sont décidées les affectations de catégories

Le tableau du § 7.1 — quelle catégorie exige quel niveau — est défini dans
`app/Services/Sse/SseRedactionService.php`, constante `CATEGORIES`. Il n'est **pas**
réglable par communauté aujourd'hui : c'est un choix de doctrine inscrit dans le code.

Si votre doctrine diffère (par exemple : le lieu au même rang que l'identité), c'est
là qu'il faut le changer, en un seul endroit.

### 7.4 Verrou d'ouverture par classification

La classification du dossier peut désormais **fermer** le dossier, pas seulement le
signaler. Ce verrou est **désarmé par défaut** et s'arme depuis le registre des
dossiers (`Athena → SSE → Dossiers`, premier panneau).

**Désarmé** (état livré) — la classification s'affiche en badge et noircit les
catégories concernées sur les versions expurgées, mais n'empêche aucune ouverture.

**Armé** — un dossier dont la classification dépasse l'habilitation du lecteur ne
s'ouvre plus pour lui : ni la fiche, ni les personnes rattachées, ni les notes, ni
les preuves, ni les corrélations, ni le compte rendu, ni l'export.

| Classification du dossier | Qui pourra encore l'ouvrir, une fois armé |
|---|---|
| Diffusion interne | Tout le monde, invités compris |
| Encadrement | Tout membre ayant accès au portail SSE |
| Confidentiel | Administration, droit d'octroi, gestion des dossiers |
| Diffusion très restreinte | Administration du portail et droit d'octroi |

#### Relisez avant d'armer

Le registre porte une colonne **« Qui pourra encore l'ouvrir »** sur chaque dossier,
et le panneau du haut donne la répartition par classification.

C'est à relire sérieusement. La classification n'a **jamais** filtré depuis la
création du portail : les valeurs déjà posées sur vos dossiers ont été choisies sans
conséquence — quelqu'un a pu cocher « Confidentiel » par prudence, ou « Diffusion
très restreinte » parce que ça sonnait bien. Armer le verrou transforme
rétroactivement ces choix en décisions d'exclusion que personne n'a prises.

Le panneau indique aussi combien de dossiers **vous** seriez fermés. Il ne peut pas
mesurer l'effet sur les autres : le portail ne parle pas à la place des habilitations
de chacun. Passez en revue avec la personne qui tient les rôles.

#### Qui peut armer

Les détenteurs du droit d'octroi (`atak.sse.grant`) ou l'administration. Armer ce
verrou ferme des dossiers à d'autres — ce n'est pas un réglage d'affichage, et il ne
doit pas être desserrable par celui qu'il gêne.

L'armement et le désarmement partent au journal d'activité.

#### Repli

Si la table de réglages est absente ou injoignable, le verrou est considéré
**désarmé**. C'est un choix assumé : un portail qui verrouille tout parce qu'une
table manque est plus dangereux qu'un verrou temporairement inactif — on découvre le
second, on subit le premier en pleine opération.

### 7.5 Caviardage manuel

Noircir une zone précise sur une fiche précise, **quel que soit le niveau** — y
compris le plus restreint. Un motif est obligatoire : c'est lui qu'on relira pour
décider de lever le caviardage.

Cas typiques : protection de source, mineur, tiers manifestement non impliqué,
élément dont la véracité est contestée.

Le caviardage est levable (bouton « Lever »). La zone redevient alors lisible aux
niveaux qui l'autorisent — pas à tous.

### 7.6 Ce que « trait noir » veut dire ici

Le texte caviardé **n'est jamais envoyé au navigateur**. La substitution est faite
côté serveur, la chaîne d'origine ne quitte pas le dossier.

C'est le point sur lequel la plupart des implémentations se trompent : un trait noir
obtenu en habillage (`color: black; background: black`) laisse le texte dans la page.
Il ressort au copier-coller, dans le code source, dans un lecteur d'écran et dans le
cache du navigateur. Autant ne rien caviarder.

La longueur de la barre est en outre **quantifiée** par pas de 4, plafonnée à 24.
Une barre exactement proportionnelle révélerait la longueur du nom : sur un dossier
à trois personnes, cela suffit souvent à savoir laquelle est laquelle.

---

## 8. Trame de séance

1. **Avant** — le PC ouvre un dossier sur le portail, note la référence.
2. **Eden** — deux ou trois PNJ réglés, le reste en génération automatique ;
   un terminal par binôme d'exploitation.
3. **Sur objectif** — le chef d'élément pose le dossier actif
   (module Zeus, ou page 6 du terminal).
4. **Fouille** — création du site, checklist des pièces, saisies versées au fur
   et à mesure. A6 remonte les natures sensibles sans attendre.
5. **Contrôle des personnes** — une fiche par sujet : relevés, photo, requête
   d'identité, signature ATAK. A1 à A4 travaillent en fond.
6. **Clôture du site** — quand A5 signale la checklist complète, rédaction du
   compte rendu cinq lignes.
7. **Exploitation** — le PC lit les corrélations, pose ses hypothèses, sort le
   compte rendu.
8. **Débriefing** — le journal d'activité dit qui a saisi quoi, quand, et ce que
   les automatismes ont fait.

---

## 9. Limites assumées

Ce qui **n'est pas** simulé, et ne le sera pas :

- reconnaissance faciale réelle ;
- lecture d'iris réelle ;
- OCR de documents d'identité ;
- rapprochement avec les fiches RH des membres de la communauté.

Le terminal compare des identifiants de scénario. Tout ce qu'il affiche est fictif
et dérivé d'une graine.

Rappel de la règle 1.4.8 : **aucune donnée médicale ACE/KAT** (saturation, voies
aériennes, état des membres) n'apparaît sur le terminal SSE. Le contexte médical
transmis se limite à ce qu'un opérateur non soignant peut constater.
