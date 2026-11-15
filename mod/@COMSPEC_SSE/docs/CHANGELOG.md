# COMSPEC SSE — historique des versions

## 1.16.0 — 15/11/2026 · Fiches et fichiers sans mention « RP » ni « simulation »

**Relancer Arma complètement.** Sources seulement dans cette livraison : les PBO `comspec_sse_ui`, `comspec_sse_identity`, `comspec_sse_main` et les `script_mod.hpp` de tous les addons sont à reconstruire (`build_mod.bat`). Aucune DLL à changer (COMSPEC Link reste 2.0.126).

### Résumé joueur
Type : patch
FR : Les fiches SSE consultées en jeu ne portent plus de mention « RP »
- Le bas des fiches et des dossiers SSE n'affiche plus « Ne constitue pas une preuve judiciaire — usage RP / renseignement uniquement ». Il reste la qualité d'exploitation, sur une seule ligne.
- Le terminal des fichiers s'appelle FICHIERS (plus « FICHIERS · SIMULATION ») et la ligne « Simulation : données fictives… » a disparu sous les réponses.
- Un Zeus peut toujours écrire son propre pied de page dans le module « Présentation des documents SSE » : laissé vide, il n'y a aucune mention.
EN : SSE records read in game no longer carry an "RP" notice
- The bottom of SSE records and files no longer shows "Not judicial evidence — RP / intelligence use only". The exploitation quality stays, on a single line.
- The records terminal is called RECORDS (no longer "RECORDS · SIMULATION") and the line "Simulation: fictitious data…" is gone under the answers.
- A Zeus can still write a footer of their own in the "SSE document presentation" module: left empty, there is no notice.

### Détail technique
- `fn_getDocumentChrome.sqf` : pied de page vide pour le défaut et les cinq modèles livrés (`standard_restreint`, `terrain_tache`, `brouillon_froisse`, `bureau_jauni`, `formel_propre`).
- `fn_fillResultDialog.sqf` : le pied n'est écrit que s'il existe (sinon la ligne de qualité reste seule, sans ligne vide) ; un ancien pied livré encore enregistré dans une mission est ignoré (liste exacte des cinq anciens textes), un pied écrit par le Zeus ou la mission reste affiché.
- `fn_filePage.sqf` : en-tête « FICHIERS » ; la ligne « Simulation : données fictives du registre de la communauté et de la mission. » est retirée du bas d'une réponse.
- `stringtable.xml` (clé `STR_COMSPEC_SSE_CFG_08AA395002`) : aide du pied de page du module, « Vide : aucune mention ».
- Non modifié : les papiers d'identité affichés en jeu. Leur marquage éventuel est peint dans les textures (PAA) des gabarits, qui ne sont pas régénérées ici ; la mention discrète « Document de jeu » de la visionneuse ne concerne que les pays réels stylisés et reste.
- **À tester en jeu** : ouvrir une fiche SSE après une fouille (bas de fiche sur une ligne), poser le module « Présentation des documents SSE » avec et sans pied de page, interroger le terminal des fichiers (en-tête et bas de réponse), téléphone en français puis en anglais.

## 1.14.0 — 15/11/2026 · Motifs et mesures au contrôle, date et autorité des papiers

**Relancer Arma complètement.** Sources seulement dans cette livraison : le PBO `comspec_sse_identity` et les `script_mod.hpp` de tous les addons sont à reconstruire (`build_mod.bat`). Aucune DLL à changer (COMSPEC Link reste 2.0.125 : la DLL relaie le JSON du contrôle tel quel).

### Résumé joueur
Type : nouveauté
FR : L'avis de recherche est plus complet au contrôle ; la date et l'autorité du papier remontent
- La carte « AVIS DE RECHERCHE » du contrôle d'identité ajoute les motifs et les mesures codifiés de l'avis (code et libellé) et le service demandeur.
- Quand le papier présenté porte une date de délivrance et une autorité émettrice, le contrôle transmis à Athena les envoie : l'écran « Consigner depuis les contrôles » du portail les préremplit.
EN : The wanted notice is fuller at the check; the paper's issue date and authority are sent up
- The "WANTED NOTICE" card of the identity check adds the notice's coded reasons and measures (code and label) and the requesting service.
- When the presented paper carries an issue date and an issuing authority, the check sent to Athena sends them: the portal's "Record from checks" screen pre-fills them.

### Détail technique
- `fn_idWantedCheck.sqf` : la réponse `op: "wanted"` passe à 12 éléments (`[…, mesure, motifs[], mesures[], service, signalement]`) ; les éléments 8 à 11 sont facultatifs (site plus ancien : listes vides). La clé `wanted` de la fiche garde les 5 premiers éléments à leur place et ajoute `[motifs, mesures, service, signalement]`. Aucune extension de la réponse de `SubmitIdCheck`.
- `fn_idPage.sqf` : lignes « Motif », « Mesures à prendre » et « Service » sous la carte de l'avis, quand elles existent.
- `fn_idAthenaSubmit.sqf` et `fn_controlRecord.sqf` : `document.issued` et `document.authority` rejoignent le payload (le relevé reprend aussi ces deux champs de la fiche du document). Le site les range bornés (date AAAA-MM-JJ, autorité 160 caractères) sous `results.paper` du contrôle, sans migration.
- Image du papier : non envoyée. Arma ne sait pas sortir une image (`fn_idPhoto` : le relevé reste textuel) et la DLL découpe le JSON en morceaux de 4 000 caractères : une image n'y passerait pas ; le site peut dessiner le papier à partir de ces champs.
- **À tester en jeu** : contrôler un papier délivré par un pays du paquet (date et autorité imprimées), le transmettre, puis ouvrir « Consigner depuis les contrôles » sur le portail ; contrôler une personne sous avis codifié et lire les lignes de motifs, de mesures et de service.

## 1.13.0 — 15/11/2026 · « Recherchée » au contrôle d'identité

**Relancer Arma complètement.** Sources seulement dans cette livraison : le PBO `comspec_sse_identity` et les `script_mod.hpp` de tous les addons sont à reconstruire (`build_mod.bat`). Aucune DLL à changer (COMSPEC Link reste 2.0.125).

### Résumé joueur
Type : nouveauté
FR : Une personne recherchée est marquée au contrôle d'identité
- Une fois le contrôle transmis à Athena, une personne sous avis de recherche actif est marquée « personne recherchée » (« HVT » pour une cible prioritaire), même si l'avis vient d'être posé sur le portail.
- La carte du contrôle montre la référence de l'avis, la menace, « armé et dangereux » et la mesure à prendre.
- Le détail complet de l'avis se lit dans l'app Fiches du téléphone ATAK (mod COMSPEC ATAK 4.22.0).
EN : A wanted person is marked at the identity check
- Once the check is sent to Athena, a person under an active wanted notice is marked "wanted person" ("HVT" for a priority target), even if the notice has just been placed on the portal.
- The check card shows the notice reference, the threat, "armed and dangerous" and the action to be taken.
- The full notice can be read in the ATAK phone's Records app (COMSPEC ATAK mod 4.22.0).

### Détail technique
- `fn_idWantedCheck.sqf` (nouveau) : après `OK|id|réf` de `SubmitIdCheck`, une seconde commande `{"kind":"sse_fiches","op":"wanted","check":id}` demande au site l'avis actif de l'identité du contrôle (réponse `[version, "wanted", avis, réf, type, menace, armé, mesure]`). COMSPEC Link ne relaie que `OK|id|réf` pour un contrôle : plutôt que changer la DLL (version, attestation, reconstruction), on reprend le canal « kind » déjà utilisé par le catalogue. Jamais pour l'identité d'un membre.
- `fn_idHistory.sqf` : le verdict global tient compte de la clé `wanted` de la fiche (« recherche », « hvt » pour un type HVT) ; `fn_idPage.sqf` : carte « AVIS DE RECHERCHE » sous le verdict ; `fn_idAthenaSubmit.sqf` : l'appel suit la transmission réussie.
- Les contrôles mis en file d'attente hors liaison n'ont pas ce second appel (l'avis du paquet d'identités reste la référence locale).
- **À tester en jeu** : contrôler une personne sous avis posé après le début de la mission ; vérifier la bannière « personne recherchée » et la carte de l'avis.
## 1.12.0 — 15/11/2026 · Le mod lit les nouveaux champs des papiers du site

**Relancer Arma complètement.** Sources seulement dans cette livraison : le PBO `comspec_sse_identity` et les `script_mod.hpp` des 23 addons sont à reconstruire (`build_mod.bat`). Aucune DLL à changer. Les deux libellés anglais sont dans le dictionnaire de l'ATAK natif (`main/i18n/en.txt`) : anglais à partir du prochain `main.pbo`, français d'ici là.

### Résumé joueur
FR : Les papiers du site sont lus plus complètement
- Au contrôle, la lecture de la base (N3) rappelle la référence du papier, la même que sur Athena.
- Un papier du site dont la date d'émission est postérieure à la date de jeu est jugé faux.
- Les images du site (photo, signature, fond) ne peuvent pas être affichées par Arma : le mod garde le gabarit et la silhouette.
EN : Website papers are read more completely
- At the check, the database lookup (N3) reminds you of the paper reference, the same as on Athena.
- A website paper whose issue date is later than the in-game date is judged forged.
- Arma cannot display the website images (photo, signature, background): the mod keeps its template and silhouette.

### Détail technique
- `fn_idAthenaPack.sqf` : les papiers du pack lisent `ref`, `issued_at`, `layout`, `print` et `images` (`_docFields` partagé par `apply` et `applySelf`). `_normDoc` les normalise : texte de 200 caractères au plus, `print` en HashMap de 16 champs au plus (vides retirés), `images` en HashMap `photo` / `signature` / `background` (chemin relatif sans `..`, `:` ni antislash, sinon vide). Champs absents ou `null` : chaînes et tables vides, aucune erreur. Lecture du pack bornée à 400 morceaux (le DLL en fait de 6000 octets, sous la limite de retour d'extension).
- `fn_idEvaluate.sqf` : constat `paper_ref` (référence du papier, sans effet sur le verdict) au N3 ; constat `not_issued` (verdict faux) pour un papier Athena dont la date d'émission imprimée est postérieure à la date de jeu, aux niveaux N0, N1 et N3. Papier né en jeu ou date illisible : inchangé.
- `fn_idPaperOf.sqf` : nouvelles clés `paperRef`, `issuedAt`, `layout`, `print`, `images`.
- Impossible dans Arma : charger une image par HTTP (`ctrlSetText` et les textures d'objet ne lisent que des fichiers du jeu). Repli : conserver le chemin et garder le gabarit du mod.

## 1.11.0 — 15/11/2026 · Kits de contenu par pays

**Relancer Arma complètement.** Sources seulement dans cette livraison : les PBO `comspec_sse_generator`, `comspec_sse_identity`, `comspec_sse_eden`, `comspec_sse_zeus` et `comspec_sse_main` sont à reconstruire (`build_mod.bat`). Aucune DLL à changer. Les libellés anglais des nouvelles régions et des nouveaux modèles sont dans le dictionnaire de l'ATAK natif (`main/i18n/en.txt`) : ils s'affichent en anglais à partir du prochain `main.pbo` de l'ATAK, en français d'ici là.

- **Kits par pays** : dix-sept pays des papiers reçoivent de grands viviers de prénoms et de noms (90 à 200 prénoms par sexe, 90 à 180 noms), repris à l'identique par le site (`config/sse_kits/kits.json`). Irak, Syrie, Yémen, Libye, Afghanistan, Mali, Niger, Tchad, Somalie, Russie, Ukraine, Serbie et Colombie sont élargis ; Liban, Albanie, Mexique et Philippines sont nouveaux (`tools/countries/countries.json`, `CfgComspecCountries.hpp`).
- **Papiers** : ces dix-sept pays délivrent aussi le titre de séjour, le certificat d'immatriculation et la carte militaire (gabarits neutres, noms des papiers dans la langue du pays).
- **Pays réels stylisés** : mêmes viviers dans `fn_idRealCountries.sqf`, et onze pays de plus proposés à l'import par le site (une graine donne la même identité en jeu et sur Athena).
- **Générateur** : huit régions de plus (`AFGHANISTAN`, `UKRAINE`, `BALKANS`, `LATAM`, `SE_ASIA`, `HORN_AFRICA`, `LIBYA`, `YEMEN`) dans l'attribut Eden « Région / théâtre ». `fn_kitPools.sqf` (généré par `tools/gen_kit_pools.php`) porte les viviers de chaque région ; `fn_getNarrativePools` les ajoute aux listes d'origine, garde le résultat en mémoire et en rend une copie.
- **Modèles intégrés** : 37 modèles de plus (61 en tout), rangés par kit : Sahel, Syrie, Levant, Afghanistan, Ukraine, Balkans, Amérique latine, Asie du Sud-Est, Corne de l'Afrique, Yémen, Libye, et trois organisations sans pays (société militaire privée, administration corrompue, ONG de façade).
- **Véhicules** : les thèmes citent les véhicules courants de la région après les types génériques.
- **Compatibilité** : la région « Aléatoire » tire toujours parmi les six régions d'origine. Les noms tirés pour une même graine changent (listes plus longues). Les noms de famille russes au féminin ne sortent plus dans le vivier masculin de la région Russie.
- **Garde-fous** : tout est fictif ; les noms de famille trop liés à une personnalité ou à une dynastie réelles sont retirés des viviers, et le site écarte une liste d'homonymes.

**À tester en jeu** : un civil de chaque nouveau pays (Liban, Albanie, Mexique, Philippines) présente une carte lisible ; « Donner un document » propose le titre de séjour, la carte grise et la carte militaire pour l'Irak et le Mali ; Eden › Région / théâtre liste les huit nouvelles régions et « Générer un profil » en Amérique latine donne des noms hispaniques et un indicatif +57 ou +52 ; Zeus › Appliquer un modèle liste les 61 modèles et « Sahel — Convoi de carburant » s'applique ; une mission existante en région Irak se charge sans erreur dans le RPT.

## 1.10.0 — 15/11/2026 · Les listes des modules viennent du site de la communauté

- **Référence de dossier :** le module Zeus propose les dossiers ouverts sur le site de la communauté (liste avec filtre). La saisie à la main reste possible pour un dossier pas encore créé. En Eden, l'attribut « Dossier du site » l'emporte sur la référence saisie.
- **Nouveau module « Identité du site (Athena) » :** pose sur un PNJ une personne du lore de la session, un avis de recherche actif, un contexte d'acquisition et un dossier, choisis dans les listes du site. En Eden, synchronisez le module aux personnes ; l'attribut « Personne du lore (liste du site) » existe aussi sur chaque unité.
- **Éditeur Eden :** les listes sont lues sur le site seulement si la connexion Athena du menu COMSPEC SSE est ouverte sur le poste. Sinon la valeur enregistrée est gardée (marquée « hors liste ») et appliquée par le serveur au lancement de la mission.
- **Origine des listes :** chaque formulaire l'écrit en pied (site et heure de la dernière synchro, listes gardées sur le poste, ou listes du mod) ; bouton ACTUALISER. Aucune alerte.
- **Transmission :** la fiche personne envoie le contexte d'acquisition, le dossier et l'avis de recherche choisis pour le sujet. La file hors ligne et ses clés d'idempotence sont inchangées : un envoi rejoué ne crée pas de doublon.
- Demande COMSPEC ATAK 4.21.0 (c'est lui qui lit le site). Sans lui, les modules gardent leurs listes locales.
- Contrat : `docs/technical/ICD-SSE-CATALOGUE.md`.

## À venir · Profils d'IA, éditeur SSE en Zeus, papiers et contrôle (version attribuée à la publication)

- **SEEK II : la photo du visage remonte enfin sur Athena.** « SIGNER ET ENVOYER » n'envoyait que la fiche SEEK ; la photo n'était jointe qu'à la transmission d'une fiche personne (bouton TRANSMETTRE), donc elle ne partait jamais. Elle part maintenant avec la référence REC de la fiche signée (Overwatch connect : `fn_seekSend`, `fn_sseCaptureFacePhoto`) et le site la range sur la fiche SEEK II et sur l'identité rattachée (Bureau SSE › Identités et papiers, Fichiers › Fiches SEEK II et Photothèque). À reconstruire et publier avec le prochain paquet ATAK natif.
- **Exploitation numérique : le contenu relevé arrive au laboratoire.** L'envoi d'un support ne portait que des totaux (« 12 contacts, 40 messages ») : les écrans téléphone, ordinateur, communications et chronologie du Bureau SSE restaient vides. Après une exploitation complète (ou le mode ouvert : contacts, messages, appels, photos, positions, navigateur, courriels, identifiants, fichiers), le mod envoie aussi le contenu (nouvelle fonction `buildAthenaDigitalDetail`, envoi `digital_detail` par la file du SSE, découpé et rejouable hors ligne). Un simple « identifier » n'envoie rien de plus. Aucune DLL à changer.

- **« Suivez-moi » :** une IA qui accepte vous suit vraiment, à deux ou trois mètres, au pas quand vous marchez, en courant quand vous courez, et s'arrête quand vous vous arrêtez. Elle vous suit jusqu'à ce que vous lui disiez « Reste ici » ou « Circulez ». Une IA calme accepte bien plus souvent ; une IA terrorisée peut refuser ; menottée, elle demande à être escortée. L'en-tête de « Parler à » indique « Suit le joueur ».
- **IA hostiles (coupé par défaut) :** une IA peut désormais s'énerver contre vous. Insultez-la, menacez-la, fouillez-la, contrôlez ses papiers une fois de trop, bousculez-la ou braquez-la : elle s'agace, vous insulte et vous menace (« Dégage. », « Je vais te casser ta gueule ! »), refuse vos ordres, puis, si la mission l'autorise, vous bouscule et vous frappe. Chaque personne a son caractère (irritable, ivre, fanatique, rancunier, fier, lâche qui bluffe) et ses mots. Des excuses ou « Vous pouvez partir » la calment. Les coups sont plafonnés et jamais mortels, sauf si le maître du jeu le décide. Réglage « Agressivité des IA » (jamais, verbale, physique) dans les options du mod, par IA dans Eden, et par IA ou par zone pour le Zeus (menu SSE · Roleplay de l'IA › Agressivité…), avec un « calme général » et des zones sans agressivité.
- **IA hostiles et mods TSP :** si TSP Melee est installé, les coups des IA passent par lui (mêmes animations, mêmes sons, même parade qu'entre joueurs) ; avec TSP Animate, elles ont aussi ses gestes grossiers. Sans ces mods, le SSE se débrouille seul. Réglages « Combat corps à corps » et « Animations TSP ».
- **Papiers, s'il vous plaît :** la réponse de l'IA s'affiche au-dessus de sa tête (bulle de l'ATAK), ou en toast hors de vue : plus rien dans le centre de notifications. L'IA s'arrête dès le début de la demande. Une IA sans papiers reçoit une identité générée (réglage « IA sans papiers », 0 % par défaut).
- **Photo d'identité :** portrait de face dans une scène dédiée, éclairée, sur fond clair, de jour comme de nuit ; portrait de repli sans PiP.
- **Papiers :** plus de mention SIMULATION sur les pays du jeu, textes sans halo, colonnes lisibles, sceau discret. Mention discrète gardée pour les pays réels stylisés.
- **App Contrôle ID :** verdict et état des niveaux d'abord, une seule action principale, le reste replié (Contrôle, Signaler, Partager).
- **Profils IA :** Bureau SSE › Profils IA. Un profil se relie à une IA en Zeus (éditeur SSE › IA de la zone) ou en Eden (attribut, aussi par groupe) ; le lien est gardé par le serveur.
- **Le profil et les papiers, une seule source :** changer le profil d'identité SSE met à jour les papiers ; un faux ou une couverture reste faux et détectable.
- **Éditeur SSE :** les cinq types marchent (clics perdus à cause d'une virgule dans une macro), mise en page refaite, état clair (brouillon, enregistré, publié), « Garder pour les prochaines parties ». Module Zeus aussi sans ZEN.
- **Preuves :** un support numérique s'ouvre sur un écran numérique, plus sur une feuille ; textes humains à la place des codes.

## 1.4.0 — 08/11/2026 · Identité : photo en direct, carte militaire, preuves, éditeur SSE

**Relancer Arma complètement.** Nouveau PBO `comspec_sse_editor`. Aucun changement du main.pbo de l'ATAK ni de la DLL
(paquet Athena : champ `settings` ajouté, ignoré par un ancien mod).

- **Éditeur SSE, suite** : bibliothèque de la communauté sur Athena (compte relié et habilitation Confidentiel), celle du poste
  en repli et en cache ; ouverture depuis le menu COMSPEC SSE d'Eden ; ligne « Trouvé par un joueur » dans l'historique ; le texte
  d'une identité ou d'un kit devient la légende du papier (`fn_idLegend`). Site : étape de migration 4070 à passer.

- **Transmission automatique à Athena activée par défaut** (`comspec_sse_id_autoTransmit`) ; le réglage de la communauté
  (Bureau SSE, Contrôles) reçu dans le paquet l'emporte. `person_id` renseigné quand une fiche correspond (unité, ou papier
  présenté par son vrai titulaire).
- **Carte militaire** (`military_id`) : émission, 256 exemplaires, gabarits `tpl_military_*`, MRZ « A< », Eden, Zeus. Rôle :
  en civil elle reste à vérifier (N0), le fichier donne force et grade, une force ennemie est signalée (N3). Militaires
  réguliers IA : carte militaire le plus souvent.
- **N3** : un avis sans prénom ou un alias égal au seul nom ne sonne plus l'alerte chez tout porteur de ce nom (« homonyme
  possible », à recouper). Un alias n'est plus recopié dans le champ nom.
- **Photo du document en direct** (PiP, `fn_idFace`) : caméra sur le visage du vrai titulaire, joueur ou IA, seulement
  pendant l'ouverture ; silhouette si le PiP est désactivé. Faux papier : un autre visage du jeu, stable pour cette identité.
  Photo substituée : autre visage, photo de travers. La photo du site ne s'affiche pas en jeu (Arma ne lit pas d'image
  téléchargée sans `-filePatching`).
- **Papier saisi** : pièce du module evidence, avec chaîne de possession (`fn_custody`), rattachée au dossier en cours ;
  restitution inscrite au journal (`fn_idEvidence`).
- **Éditeur SSE** (module Zeus « COMSPEC SSE · Éditeur », addon `editor`) : choix du type en cartes, modèles, aperçu en direct,
  mode de remise, bibliothèque (recherche, duplication, historique), placement dans le monde, guidage au premier lancement.
  Bibliothèque enregistrée sur le poste, pas encore sur Athena. Icônes et logo : `tools/gen_editor_assets.py`.
- Traductions EN : `tools/i18n_identity_en.tsv` (+34 lignes), à reporter dans le dictionnaire de l'ATAK.

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
