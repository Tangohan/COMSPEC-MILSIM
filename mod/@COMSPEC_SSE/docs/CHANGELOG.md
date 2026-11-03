# COMSPEC SSE — historique des versions

## 1.2.0 — 03/11/2026 · Contrôle d'identité : photo, comptes rendus, IA réalistes, checkpoints IA

**Relancer Arma complètement.** Fonctionne avec le pack ATAK 3.0.1 ; certaines fonctions demandent l'**ATAK 3.0.2 ou
plus** (testé à l'exécution par `fn_idAtakAtLeast`, rien ne change avec un ATAK plus ancien) :
- boutons **Partager** (Bluetooth, `["shareIdCheck", _rec] call comspec_atak_native_fnc_btAction`), **NFC**
  (`shareIdCheckNfc`, 2 m sans appairage) et **Sur mon téléphone** (`["open", "controle"] call
  comspec_atak_native_fnc_mobileQr`, page `/m/controle`) dans l'app Contrôle ID ;
- alerte discrète en **vibreur seul** (6ᵉ paramètre `"vibrate"` de `comspec_atak_native_fnc_notify`) au lieu de
  vibreur + notification ;
- SPOTREP par l'entrée publique `uiNamespace COMSPEC_ATAK_SpotrepDraft` (3.0.1 : brouillon `COMSPEC_ATAK_RepDrafts`) ;
- rattachement de la FRM et de ses photos au contrôle sur Athena : clé `idCheck` du brouillon FRS (référence CTRL-…
  sinon clé d'idempotence), envoyée en `id_check_ref` par l'ATAK 3.0.2 (ignorée avant).

- **Photo du document** : bouton « Photographier » dans la visionneuse et dans l'app Contrôle ID ; cliché joint aux
  pièces de la fiche FRM (`COMSPEC_ATAK_FrsPieces`, envoyées après la fiche comme une capture de l'app FRS : StageCapture
  puis `UploadSseNoteAttachment`, `/api/sse/notes/{id}/pieces`) ; photo de preuve SSE sur la personne contrôlée si elle a
  un dossier (`fn_evidencePhoto`). `fn_idPhoto`.
- **FRM pré-remplie** (`fn_idFrm`) : contrôleur, date, heure, carroyage et coordonnées, lecture MRZ et puce NFC (« non
  effectuée » sinon), photo ; en-tête de la fiche (carroyage, date, lieu) rempli.
- **SALUTE et SPOTREP pré-remplis** depuis l'app Contrôle ID (`fn_idReport`) : brouillons des apps de l'ATAK
  (`COMSPEC_ATAK_SaluteDraft`, `COMSPEC_ATAK_RepDrafts`), main.pbo non modifié.
- **Compte rendu AAR** (`fn_afterActionReport`) : une ligne par contrôle notable (détection, HVT, saisie, interpellation
  au menottage ACE d'une personne contrôlée, fuite, menace, reddition, renforts, issues des checkpoints) — `fn_idAar`.
- **IA réalistes** : machine à états CBA (`fn_idAiFsm`, `fn_idAiBrain`, `fn_idAiEnter`), une IA contrôlée traitée par
  image, contexte recalculé une fois par seconde ; états calme, interpellé, coopère, nerveux, ment, refuse, fuit,
  agressif, se rend, appelle des renforts ; profil (attribut Eden « Profil au contrôle »), état, contexte (soldats,
  armes pointées, checkpoint, couvre-feu) et tension ; ordres réels là où l'IA est locale (`comspec_sse_idAiDo`) ;
  reddition ACE ; phrases courtes aux joueurs proches (`fn_idAiSay`) ; hésitation avant la réponse ; renforts
  (`fn_idAiReinforce`, joinSilent vers un camp hostile pour un insurgé caché parmi les civils).
- **Checkpoints IA** (`fn_idCheckpoint`) : module Eden « Checkpoint IA » (`modules_identity.hpp`,
  `fn_idCheckpointModule`) et module Zeus ; une boucle serveur d'une seconde ; halte et demande de papiers, niveaux N0 à
  N3 selon l'équipement du poste (`fn_idEvaluate` avec l'IA), liste de recherchés, suspicion (papiers, équipement
  visible, comportement), issues laisser passer, fouiller, retenir, alerter, ouvrir le feu ; contrôle abstrait d'ambiance
  des IA qui passent.
- **Zeus** : Checkpoint IA, Retirer un checkpoint IA, Déclencher un contrôle, Forcer un état IA, Couvre-feu
  (`fn_idServer` cpCreate, cpRemove, cpTrigger, fsmForce, curfew, réservés au Zeus).
- **Réglages CBA** (Identité) : IA réalistes au contrôle, Agressivité des IA, Distance des checkpoints, Délai pour
  présenter ses papiers, Les checkpoints contrôlent aussi les IA, Couvre-feu.
- Serveur : l'anti-flood des demandes ne s'applique plus qu'aux postes distants (les checkpoints peuvent contrôler
  plusieurs joueurs à la fois). Réponses des IA traduites (téléphone en anglais).
- Traductions EN : `tools/i18n_identity_en.tsv` (+145 lignes), stringtable (module et attribut Eden).

## 1.1.2 — 03/11/2026 · Mesure des papiers dans l'onglet PERFS de l'ATAK

- `fn_idDocsOf` (conditions des menus ACE « Mes papiers », fouilles) mesuré dans l'onglet Debug › PERFS de l'ATAK
  natif 3.0.1 (« SSE papiers (idDocsOf) »), seulement quand l'ATAK natif est chargé. Aucun changement visible.

## 1.1.1 — 03/11/2026 · Papiers des joueurs depuis Athena

**Relancer Arma complètement.** Kit et pays de la communauté : liaison Athena (COMSPEC Link 2.0.118).

- **Kit de mission** (bloc `kits` du paquet d'identité) : le serveur compare le Steam ID du joueur (`getPlayerUID`)
  à celui du compte Athena et lui remet les papiers listés qu'il ne porte pas déjà, à la connexion, au JIP, au
  chargement du paquet et après chaque respawn. Sans kit : comportement inchangé (attribut Eden, module Zeus).
- **Respawn** : les papiers du kit ne sont plus perdus ; l'exemplaire resté sur l'ancien corps passe sur le nouveau
  (sinon nouvel exemplaire). Réglage CBA « Rendre les papiers du kit au respawn » (activé par défaut).
- **Pays émetteurs** enrichis : nom anglais (téléphone en anglais), autorité par défaut, langues, papiers délivrés,
  couleurs ; pays archivé : plus de papiers générés, ceux déjà émis restent lisibles.
- **Contrôles** : le Steam ID du joueur contrôlé part avec le contrôle (`subject.steam_id`), pour l'historique
  « Contrôles subis » de sa fiche Athena.

## 1.1.0 — 03/11/2026 · Contrôle d'identité

**Relancer Arma complètement.** Niveaux en ligne (documents Athena, base Athena, transmission) : COMSPEC Link 2.0.118
ou plus (pack ATAK 3.0.0). Avant : tout fonctionne hors ligne (base locale, file d'attente).

- **Papiers d'identité fictifs** : carte d'identité, passeport, permis de conduire de quatre pays imaginaires (Altis,
  Tanoa, Livonie, Malden), marqués SIMULATION. Bande MRZ ICAO et puce NFC simulées.
- **Contrôle par ACE** : demander les papiers, examiner un document (visionneuse recto / verso, zoom), fouiller les
  papiers d'une personne morte, menottée, rendue ou inconsciente, saisir et rendre, contrôler les occupants d'un
  véhicule, faire descendre.
- **Joueur à joueur** : demande sur l'ATAK, réponse par le menu ACE personnel « Mes papiers » (présenter, remettre,
  refuser).
- **IA** : les civils présentent leurs papiers, les insurgés de faux papiers, une HVT sa couverture, un recherché armé
  refuse ou s'enfuit.
- **App « Contrôle ID » de l'ATAK** : contrôle par niveaux (visuel, MRZ, puce, base), verdict, alerte discrète,
  historique, transmission à Athena, fiche FRM pré-remplie.
- **Eden** : catégorie « COMSPEC SSE — Papiers » (papiers portés, pays, nature, qualité des faux).
- **Zeus** : donner des papiers, signaler volé / révoquer, voir les vrais papiers.
- **Biométrie** : la vraie identité et les faux papiers réglés dans Eden ou par Zeus ne sont plus visibles des joueurs
  (gardés sur le serveur).

## 1.0.0 — 20/10/2026

Version majeure : tout passe par le téléphone ATAK natif (voir STEAM_CHANGELOG.txt).
