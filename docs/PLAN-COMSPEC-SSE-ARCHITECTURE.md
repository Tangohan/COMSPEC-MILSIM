# Plan — Architecture COMSPEC SSE (identity activities)

Reprise de la proposition d'architecture en trois temps : collecte terrain, enregistrement
et corrélation Athena, exploitation par la cellule renseignement. Ce document arbitre entre
ce qui existe déjà, ce qui doit être repris, et ce qui reste à construire.

**État au 30/07/2026.** Version mod : 1.4.12. Branche : `claude/sse-medical-ace-integration-cbf1yt`.

---

## 1. Ce que la proposition change vraiment

Le modèle en trois temps est juste et il n'est pas celui qui a été codé jusqu'ici. La
différence de fond n'est pas l'UI : c'est **le dossier comme unité de travail**.

Aujourd'hui la fiche personne est l'objet central, et le dossier (`sse_cases`) n'est qu'une
étiquette de classement saisie après coup. Dans le modèle proposé, l'équipe **ouvre un
dossier en arrivant sur objectif**, et tout ce qui suit — sujets, matériel, photos — s'y
rattache automatiquement. Le contexte (quoi, où, quand, par qui, dans quel dossier) est
préservé sans que le joueur le ressaisisse.

C'est un renversement, pas un ajout. Il conditionne le reste.

Deuxième apport majeur : **la préparation Eden avec génération déterministe**. Sans elle,
un chef de mission qui veut quinze civils exploitables doit écrire quinze identités à la
main, et personne ne le fera. C'est le point qui décide si le module est joué ou non.

---

## 2. Découpage : mod séparé ou PBO dans le pack

Le mod séparé `@COMSPEC_SSE` avec neuf addons est la préférence exprimée, redite depuis.
**C'est donc la voie retenue si elle est confirmée** ; l'objection ci-dessous est
consignée une fois, elle n'a pas à être rejouée.

Objection, en une ligne : `@COMSPEC_SSE` dépendrait de `COMSPEC Overwatch` pour la couche
réseau, l'identité opérateur et le terminal — il ne serait jamais chargeable seul — et la
DLL vit dans `@COMSPECOverwatch`. Le bénéfice recherché (séparation, retirabilité) est
obtenu par un PBO, sans second item Workshop ni second cycle de version.

| Argument | Constat |
|---|---|
| Dépendance | `@COMSPEC_SSE` dépendrait de `COMSPEC Overwatch` pour la couche réseau, l'identité opérateur et le terminal. Il ne serait jamais utilisable seul. |
| DLL | `COMSPECExtension_x64.dll` est livrée dans `@COMSPECOverwatch`. Un second dossier `@` ne l'emporte pas, et il ne faut surtout pas une seconde DLL. |
| Coût réel | Second item Workshop, second cycle de version, second changelog, second point de désynchronisation — pour du code qui vit dans le même pack. |
| Ce qu'on cherche vraiment | La séparation **logique** et la possibilité de retirer la couche. Un PBO donne exactement cela : le retirer du dossier `addons` suffit. |

**Sur le nombre d'addons, en revanche, je maintiens : neuf est trop.** Un PBO n'est pas un
dossier — chacun coûte une entrée de build, un `CfgPatches`, une chaîne de dépendances et
un risque d'ordre de chargement. `sse_main`, `sse_seek`, `sse_identity`, `sse_camera` et
`sse_network` correspondent à du code déjà écrit et fonctionnel dans `connect` : les en
extraire serait une migration à risque sans gain fonctionnel.

Quatre suffisent, alignés sur les trois temps — que ce soit dans `@COMSPECOverwatch` ou
dans un `@COMSPEC_SSE` dépendant :

```text
@COMSPECOverwatch/addons/
├── connect.pbo          existant — liaison, terminal SEEK, identité (déjà livré)
├── sse_ace.pbo          existant — interactions ACE sur autrui (déjà livré)
├── sse_site.pbo         nouveau  — dossier, site, matériel, evidence, photographie
└── sse_zeus.pbo         nouveau  — modules Zeus + attributs Eden + génération
```

`sse_main`, `sse_seek`, `sse_identity`, `sse_camera`, `sse_network` de la proposition
correspondent à du code déjà présent dans `connect` : les y laisser évite un éclatement
sans bénéfice. `sse_interactions` est `sse_ace`.

---

## 3. Ce qui est déjà livré

À ne pas reconstruire.

| Élément proposé | État |
|---|---|
| Objet SEEK II en inventaire | **Livré** — `COMSPEC_Item_SeekTerminal`, requis pour ouvrir une fiche, réglage CBA `comspec_sse_require_item` |
| UI Arma dédiée (`RscDisplay`) | **Livré** — dialog 9991, châssis, barre d'état, platine, touches physiques A1/A2/−/+/::/SIGN |
| Interaction ACE sur un personnage | **Livré** — nœud « Renseignement SSE » sur `CAManBase` |
| Acquisitions avec durée ACE | **Livré** — empreintes 8 s, iris 10 s, ADN 20 s, barre ACE, interruption si la cible s'éloigne |
| Qualité d'acquisition variable | **Livré** — indice par modalité, jamais 100 %, jauge et référence de laboratoire |
| Données post-mortem | **Livré** — le terminal préremplit une personne décédée, le constat porte « Décédée » |
| Préremplissage automatique | **Livré partiellement** — joueur, position, grille, heure, inventaire cible, statut ACE restrain, constat ACE Medical |
| Couche réseau partagée | **Livré** — `SubmitSsePerson` passe le JSON tel quel ; pas de seconde DLL |
| Chaîne de possession | **Livré** — `sse_custody_events` alimentée et affichée en frise sur la fiche |
| Watchlist / croisement | **Livré** — croisement à l'enregistrement, résultat au journal du poste de commandement |
| Dossier site, pièces, saisies | **Livré côté plateforme** — dépôt, API, portail. **Aucune commande d'extension** : le terrain ne peut pas encore ouvrir un site |
| Mode hors-ligne / file locale | **Non livré** |
| Eden / génération déterministe | **Non livré** |
| Réseau de relations | **Non livré** |

---

## 4. Lots à construire

### L4 — Le dossier comme contexte actif (fondation)

Sans ce lot, tout le reste reste une collection de fiches isolées.

- `COMSPEC_SSE_ActiveCase` en variable de mission : dossier courant de l'élément.
- Ouverture depuis le terminal : « Ouvrir un dossier SSE » → référence, objectif, unité,
  équipe, DTG, grille — tout prérempli sauf le nom de l'objectif.
- Tout enregistrement ultérieur (sujet, saisie, photo) hérite du dossier actif sans saisie.
- Le champ « code dossier » actuel devient un repli manuel, plus la voie normale.
- Extension : `SubmitSseCase`, `SubmitSseSite`, `SubmitSseSeizure` — **recompilation C#**.

### L5 — Matériel et evidence (`sse_site.pbo`)

- Interactions ACE sur objets, par famille de classes plutôt que par classname.
- Registre `CfgCOMSPEC_SSE` : `classname → profil` (`PHONE`, `COMPUTER`, `DOCUMENT`,
  `STORAGE`, `WEAPON`, `RADIO`, `IDENTITY_DOCUMENT`), avec détection par héritage de config
  en repli, et surcharge Eden possible. Pas de dépendance dure aux mods tiers.
- États : `DISCOVERED` → `COLLECTED` → `TRANSFERRED` → `EXPLOITED`.
- Provenance conservée : `RECOVERED FROM SUBJECT P03` ou `FOUND AT ROOM 02`.

### L6 — Préparation Eden et génération déterministe (`sse_zeus.pbo`)

Le lot qui rend le module jouable.

- Attributs Eden sur unités et objets : activation, type SSE, mode manuel ou généré,
  profil de jeu de données, valeur de renseignement, association de dossier.
- **Seed stable par entité** : `SSE SEED // 482731`. Toutes les valeurs dérivées — identité,
  numéro biométrique, contenu de téléphone, documents, relations — sont reconstruites à
  l'identique à chaque requête et après sauvegarde. **Jamais de `random` à la requête.**
- Profils : `RANDOM CIVILIAN`, `LOCAL CIVILIAN`, `INSURGENT`, `HVT`, `MILITARY`,
  `TECHNICIAN`, `COURIER`, `FINANCIER`, `UNKNOWN`, `CUSTOM`.
- Modules Zeus : créer un site, forcer un résultat d'identité, poser une entrée de
  watchlist, réinitialiser un site.

Note technique : les valeurs d'attributs Eden ne sont pas lisibles au runtime. Leur
`expression` doit recopier la configuration vers des variables d'objet exploitables.

### L7 — Identité à plusieurs niveaux de fiabilité

- `SOURCE`, `RELIABILITY`, `CONFIDENCE` sur chaque donnée.
- Identité déclarée, identité documentaire et identité biométrique **coexistent** au lieu
  de s'écraser. `UNVERIFIED` / `CORROBORATED` / `CONFIRMED` / `CONFLICTING`.
- C'est ce qui rend l'exploitation intéressante : un sujet qui ment produit une
  contradiction visible, pas une simple correction.

### L8 — Portrait biométrique

Trois niveaux possibles ; le niveau 1 est un piège.

| Niveau | Moyen | Verdict |
|---|---|---|
| 1 | Texture `.paa` du visage via `face _unit` → `CfgFaces` | Donne l'UV dépliée, pas un portrait. Utile seulement comme **identifiant stable** |
| 2 | Capture d'écran cadrée sur la tête | Réaliste, réutilise le pipeline photo existant. **Retenu** |
| 3 | Caméra dédiée + `renderTarget`, cadrage tête/épaules | Meilleur rendu, coût supérieur. À viser ensuite |

`face _unit` reste précieux : il fonctionne avec les visages moddés sans liste blanche, et
sert de clé biométrique fictive stable.

### L9 — Mode hors-ligne et comptes rendus

- File locale d'acquisitions, `ATHENA LINK // LOST`, puis `7 RECORDS PENDING → SYNC`.
- Comptes rendus par échelon : `SSE FLASH`, `INITIAL SSE REPORT`, `IDENTITY REPORT`,
  `MATERIAL EXPLOITATION REPORT`, `FINAL SSE REPORT`.

Le compte rendu ne doit pas ressembler à un inventaire. Le générateur cinq lignes actuel
(`SseSiteRepository::buildFiveLineReport`) est un PV de saisie : il faut le remplacer par
le format SITUATION / SITE EXPLOITATION / PERSONNEL / MATERIAL / KEY FINDINGS / ASSESSMENT
/ FOLLOW-ON, alimenté par les événements déjà enregistrés.

---

## 5. Ordre recommandé

1. **L4** — le dossier actif. Fondation, débloque le reste.
2. **L6** — Eden et génération. Sans données préparées, rien à exploiter.
3. **L5** — matériel et evidence.
4. **L7** — fiabilité et contradictions.
5. **L8** — portrait niveau 2.
6. **L9** — hors-ligne et comptes rendus.

L4 et L5 imposent une recompilation de `COMSPECExtension` : les regrouper en une seule
passe C#, avec `LookupSsePersonByUnit` qui attend déjà.

---

## 6. Ce qui reste hors périmètre

Inchangé, et à défendre : reconnaissance faciale réelle, lecture d'iris réelle, OCR de
pièce d'identité, fusion avec le dossier RH des membres. Tout est simulation de scénario.

La règle roleplay 1.4.8 tient également : aucune donnée KAT sur le terminal SSE.

---

## 7. Catalogue d'actions terrain (spécification du 30/07)

Ajouté après coup. Rien n'est codé ici — c'est la liste de référence pour L4 à L9.

### SEEK II — acquisition

Capture visage, iris gauche et droit **séparés**, empreintes, identité déclarée, photo
secondaire, notes opérateur, `SUBJECT ID` généré, qualité par capture, reprise d'une
acquisition, consultation des derniers sujets du terminal.

*Écart avec l'existant* : les modalités sont aujourd'hui globales (une entrée « iris »),
pas latéralisées, et il n'y a ni `SUBJECT ID` ni historique local.

### Requête distante

`QUERY ATHENA` et `QUERY LOCAL WATCHLIST` distincts ; `NO MATCH`, `POSSIBLE MATCH`,
`CONFIRMED MATCH`, `WATCHLIST HIT` ; alias et données synthétiques affichés.

*Écart* : livré en 1.4.13 sous forme d'une requête unique locale. La séparation
Athena / watchlist locale et le `WATCHLIST HIT` restent à faire.

### Mode hors-ligne

Acquisitions stockées, nombre de dossiers en attente, synchronisation manuelle ou
automatique au retour du réseau.

### Fouille ACE sur une personne

`Inspect Identity`, `Photograph Subject`, `Open SEEK II`, `Search Clothing`,
`Search Equipment`, `Collect Personal Effects`, `Document Subject`,
`Associate With SSE Case`. Distinguer inventaire visible, objets révélés par la fouille,
et objets cachés configurés dans Eden.

### Photographie opérationnelle

`OVERVIEW` / `EVIDENCE` / `SUBJECT` / `DOCUMENT` / `DEVICE`, chaque cliché portant
automatiquement dossier, opérateur, DTG, coordonnées, cible et référence SSE.

### Marquage de zones

`SITE` / `BUILDING` / `ROOM` / `CACHE` / `COLLECTION POINT`. Tout élément récupéré dans la
pièce **hérite de la localisation** — c'est ce qui évite la ressaisie.

### Evidence

Référence `SSE-26-0042-E014`, états `OBSERVED` → `DOCUMENTED` → `COLLECTED` → `BAGGED` →
`TRANSFERRED` → `EXPLOITED`, objets d'emballage (sachet, étiquette, pochette document,
sachet numérique), qualité de preuve dégradée si l'objet a été manipulé avant collecte.

### Numérique, documents, interprète

`Quick Exploitation` ne rend qu'une synthèse (`CONTACTS FOUND // 17`) ; l'exploitation
complète est faite dans Athena. Documents à trois niveaux : contenu visible, traduit,
analyste. Un rôle ou un objet interprète débloque `TRANSLATE`, `INTERVIEW`,
`VERIFY DECLARED IDENTITY`.

### Interview tactique

`IDENTITY` / `PURPOSE` / `LOCATION` / `ASSOCIATES` / `ORGANIZATION` / `EQUIPMENT`. Le PNJ
peut dire vrai, mentir, se taire ou répondre partiellement. Athena conserve
`SOURCE // SUBJECT STATEMENT`, `RELIABILITY // UNVERIFIED` — cela rejoint le lot L7.

### Fouille rapide ou approfondie

`QUICK SEARCH` contre `THOROUGH SEARCH` : la seconde est plus longue et seule elle révèle
les éléments cachés. C'est une vraie décision temps contre renseignement.

### Véhicules, caches, radios

Exploitation dédiée avec plaque fictive, occupants, inventaire ; caches avec leur propre
identifiant de site ; radios avec fréquence relevée, rapprochée ensuite côté Athena.

### Tablette SSE de terrain

Écran plus large que le SEEK : dossier actif, sujets, evidence, photos, localisations,
envois en attente, tâches. **Le SEEK reste centré identité et biométrie ; la tablette gère
le dossier.** C'est une séparation utile — elle évite de charger le terminal.

### Tâches et comptes rendus

Tâches envoyées par le TOC (`OBTAIN BIOMETRICS // SUBJECT P04`), `SEND SSE FLASH` généré
automatiquement, et écran de clôture listant ce qui reste non résolu (`ROOM 02 // NOT
CLEARED`, `SUBJECT P04 // BIOMETRICS INCOMPLETE`).

### Progression d'exploitation

`SSE COMPLETION // 62%` par pièce ou par site, calculé sur les éléments configurés par le
chef de mission. **Option désactivable** — sinon le SSE devient une chasse au pourcentage.

### Boucle cible

```text
DISCOVER → SEARCH → DOCUMENT → IDENTIFY → COLLECT → CORRELATE → TRANSMIT → REPORT
```

