# COMSPEC SSE — historique des versions

## 1.3.1 — 03/11/2026 · Pays réels stylisés (univers réaliste-simulation)

**Relancer Arma complètement.** Aucun changement du main.pbo de l'ATAK ni de la DLL (le paquet Athena porte les
nouveaux champs, convertis de façon générique).

- **Pays réels stylisés** (`fn_idRealCountries`, généré depuis le site par `tools/gen_id_real_countries.php`) :
  Royaume-Uni (GBR), Grèce (GRC), États-Unis (USA), Allemagne (DEU, bande MRZ « D<< »), Autriche (AUT), Colombie (COL),
  France, Belgique, Espagne, Pologne, Ukraine, Russie, Irak, Syrie, Afghanistan. Réservés à l'univers
  réaliste-simulation : réglage CBA « Univers réaliste-simulation (pays réels stylisés) » (décoché par défaut) ou pays
  importés par la communauté sur Athena (paquet, `real_sim`). Les pays fictifs ALT, TNA, LVN, MLD ne changent pas.
- **Garde-fous** : gabarits **génériques par zone de style** (`tpl_*_eu|am|ot_*`, bordeaux, bleu, vert), sans nom
  de pays peint (posé en jeu), emblème « SIM » inventé, aucune armoirie, emblème officiel, hologramme, motif de
  sécurité, police ou mise en page officielle ; mention « SIMULATION — DOCUMENT DE JEU » dans le bandeau et en pied de
  page ; numéros au format générique du jeu (code du pays + une lettre + six chiffres).
- **MRZ** : code OACI de l'émetteur (`mrzIssuer`, sinon `mrz` du pays) et de la nationalité — Allemagne « D<< » ;
  `tools/check_id_mrz.py` et `tools/test_id_mrz.sqf` vérifient les quinze pays.
- **Noms** : réservoirs par langue (anglais, grec, allemand, espagnol de Colombie et d'Espagne, français, néerlandais,
  polonais, ukrainien, russe — nom au féminin Ivanov → Ivanova —, arabe d'Irak et de Syrie, dari / pachto), mêmes
  tirages que le site.
- **Durées de validité par type** (carte, passeport, permis) lues dans le paquet Athena (`validity`) et le jeu stylisé.
- **Paquet Athena** (rétrocompatible) : `countries[]` + `real_sim`, `zone`, `name_native`, `validity`.
- Réglage « Pays des papiers » : pays réels proposés (« … (simulation) »), pris seulement si l'univers
  réaliste-simulation est coché.
- Traductions EN : `tools/i18n_identity_en.tsv` (+18 lignes).

## 1.3.0 — 03/11/2026 · Couvertures et infiltrés (Lot 4), HVT et BOLO (Lot 5)

**Relancer Arma complètement.** Pack ATAK 3.0.2 conseillé (vibreur seul `"vibrate"` testé à l'exécution par
`fn_idAtakAtLeast`, repli vibreur + notification avec le 3.0.1). Aucun changement du main.pbo de l'ATAK ni de la DLL.

- **Légende** (`fn_idLegend`, serveur) : adresse, ville, employeur, profession, téléphone simulé
  (`comspec_sse_fnc_generatePhone` : numéro à indicatif fictif, modèle, IMEI ; messages non repris), contacts,
  documents complémentaires, historique, biographie, famille, langues, phrases types, sujets sensibles, secrets, motif
  du déplacement ; cohérente avec la pièce principale (nom, naissance, adresse, profession imprimés) ; brief RP du paquet
  (`briefs[]`, Steam ID) prioritaire ; mise en cache par clé biométrique, recalculée si les papiers changent.
- **Infiltré** (`fn_idCover`) : `setCaptive` là où l'unité est locale (`comspec_sse_idLocalCaptive`, réglage) tant que
  la couverture tient ; soupçons : arme à la main (brûlée), arme longue dans le dos, tenue militaire, gilet, casque,
  JVN vus par un ennemi (ligne de vue, distance des témoins), zone interdite (Zeus ou marqueurs `comspec_id_interdit…`),
  tir (`FiredMan` posé sur le poste de l'infiltré seulement, relayé `coverFired`), contrôle raté, mauvaises réponses,
  retenue à un checkpoint ; menotté, alerte ou arme trouvée à la fouille = brûlée. Brûlée : groupes ennemis à 400 m
  en combat (`comspec_sse_idAiDo` « alert »), alerte discrète au joueur, Zeus, AAR, évènement
  `comspec_sse_idCoverBurnt`. **Boucle serveur de 2 s posée seulement tant qu'il y a des infiltrés, retirée ensuite.**
  Score de cohérence (papiers 35, légende 25, équipement 25, comportement 15) : examen N0 d'un joueur (ligne « ne
  collent pas aux papiers »), suspicion des checkpoints.
- **Questions de légende** (`fn_idQuestion`) : checkpoints IA (étape « quiz » de `fn_idCheckpoint`, 2 ou 3 questions,
  réglage et délai), réponses au choix (vraie légende + 2 leurres) dans l'app « Ma couverture » ou le menu ACE
  personnel › Mes papiers › Répondre ; fausse = suspicion, silence = évasif ; évènement serveur
  `comspec_sse_idAnswered`. Joueur → PNJ : menu ACE › Contrôle d'identité › Poser une question (1 s) ; réponse selon
  la qualité de sa couverture (civil 98 %, couverture selon sa qualité, faux papiers mal appris), tension de l'IA
  augmentée par une réponse qui ne colle pas ; réponses gardées sur la fiche du contrôle et dans la FRM.
- **App ATAK « Ma couverture »** (`fn_idCoverPage`, page IDCOVER, `COMSPEC_ATAK_Apps` du SSE) : fiche RP du seul joueur
  concerné, cohérence et motifs, question en attente ; onglet Zeus (curateur) : infiltrés, cohérence, fiche avec vrai
  nom. Serveur : `coverSheet`, `coverList`.
- **N4 · visage** (`fn_idBio`) : cliché (distance, lumière, visage couvert, compétence, difficulté), recherche
  `comspec_sse_fnc_bioSearch` modalité visage (ABIS avec la liaison, sinon base locale), taux de confiance, dossier,
  tier ; nom des papiers différent du dossier = fausse identité ; visage différent de la photo du document. Durée et
  activation par réglage, matériel `face` (`comspec_sse_fnc_hasEquipment` : téléphone ATAK ou SEEK).
- **HVT** (`fn_idHvt`) : Zeus « Désigner HVT » (watchlist 1 ou 2, alias, vrai nom serveur seulement, papiers connus,
  couverture à un autre nom, `COMSPEC_ATAK_TinderKind` = HVT pour la chasse ATAK : FIND) ; identification N3 / N4 →
  bouton « Confirmer le PID » → tuyaux confirmés (`fn_hvtIntel` « tip » + « review », sources PID, CTRL-ID, BIO-N4)
  pour le groupe du contrôleur (FIX) ; menottes ACE ou mort → JACKPOT (données SSE générées, chaîne de possession
  ouverte, AAR, Zeus, Athena). Le JACKPOT de l'app Tinder reste détecté par l'ATAK lui-même.
- **BOLO** : Zeus « BOLO » (nom, prénom, alias, niveau, papiers connus, signalement ; posé sur une personne : visage
  joint), « Lever un BOLO » ; avis local (N3, liste papier des checkpoints), numéro de document (N3 et checkpoints),
  visage (checkpoints) ; liste diffusée `comspec_sse_idBolos`, onglet BOLO de Contrôle ID, notification (réglage), app
  Recherchés : injection dans `uiNamespace COMSPEC_ATAK_Wanted` quand la liste est chargée et copie dans
  `missionNamespace COMSPEC_ATAK_WantedLocal` (même format). **Remontée Athena** : `SubmitIdCheck` avec
  `kind = bolo | hvt` et `watch{game_ref, last_name, first_name, alias, level, notes, bio_key, known_docs[], status}`
  (DLL du serveur, sinon d'un Zeus, sinon file hors ligne) → liste de surveillance de la communauté, renvoyée dans
  `watch[]` des paquets suivants (dédoublonnée ; visage et papiers connus redeviennent un BOLO).
- **Paquet Athena, Lot 6** (rétrocompatible) : `briefs[]` (légende, infiltration ouverte si identité de couverture),
  `population[]` (identités préparées remises aux IA à leur premier contrôle : camp, catégorie civil / militaire /
  suspect / HVT, zone = marqueur ; une entrée par IA), `wanted[]` (avis locaux limités aux camps `sides`, papiers
  connus en BOLO).
- **Zeus** : Couverture (infiltrer / brûler / lever, captif, carte de couverture), Zone interdite (couvertures),
  Désigner HVT, BOLO, Lever un BOLO ; « Voir les vrais papiers » affiche l'infiltration (cohérence, soupçons) et la HVT.
- **Eden** : attribut « Infiltré (couverture) » (`COMSPEC_ID_Undercover`, serveur seulement).
- **Réglages CBA** (Identité) : Infiltré captif tant que sa couverture tient · Soupçons avant que la couverture saute ·
  Distance des témoins · Questions de légende · Délai pour répondre · Comparaison du visage (N4) · Durée de la
  comparaison du visage · Notifier les BOLO.
- Traductions EN : `tools/i18n_identity_en.tsv` (+227 lignes, à fusionner dans `i18n/en.txt` de l'ATAK à sa prochaine
  version), stringtable (attribut Eden).
- **Pour une prochaine version de l'ATAK (main.pbo)** : `fn_wantedAction` « load » devrait fusionner
  `missionNamespace COMSPEC_ATAK_WantedLocal` à la liste d'Athena (aujourd'hui un « Actualiser » retire les BOLO locaux
  jusqu'au prochain rendu de Contrôle ID ; ils reviennent par Athena une fois remontés) ; fusion du TSV dans `en.txt`.

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
