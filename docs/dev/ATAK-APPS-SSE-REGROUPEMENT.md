# ATAK : une seule application SSE

Demande : « on a 30 mille apps SSE dans le téléphone, tu peux pas faire une application complète qui regroupe la totalité ? ».
Avant : une tuile-dossier « SSE » (ATAK 4.28/4.29) qui listait les apps ; chaque app s'ouvrait en plein écran, sans retour d'une fonction à l'autre.
Maintenant : **une seule application SSE** (classe `SseHub`, page `SSEHUB`) avec un accueil à sections, une recherche, des compteurs, et une
**coque** qui entoure chaque fonction d'une barre de navigation interne. Le code des écrans d'origine n'a pas été touché.

## 1. Inventaire

Registre : `COMSPEC_ATAK_Apps` dans `addons/main/config.cpp` (ATAK) et dans `mod/@COMSPEC_SSE/addons/*/config.cpp` (pack SSE : `compat_atak`,
`identity`). Dix apps du téléphone portent le SSE ; neuf sont regroupées (l'app SSE elle-même est la dixième).

| Classe | Page | Écran (fonction) | Déclarée par | Nom dans l'app | Condition d'affichage et droits | Dépendance serveur |
|---|---|---|---|---|---|---|
| `SseField` | `SSEFIELD` | `comspec_sse_fnc_fieldPage` | pack SSE `compat_atak` | Sur le terrain | installation (App Store), interdiction de l'encadrement, mode Essentiel | serveur SSE : dossier, indices, scellés, journal (`fn_fieldAction`) |
| `IdCheck` | `IDCHECK` | `comspec_sse_fnc_idPage` | pack SSE `identity` | Contrôle ID | idem | serveur SSE : fichiers et contrôles (`fn_idAction`, `fn_controlRecord`), Athena en option (`fn_idAthenaSubmit`) |
| `Fichiers` | `FICHIERS` | `comspec_sse_fnc_filePage` | pack SSE `identity` | Fichiers | idem | serveur SSE (`fn_fileClient`, `fn_fileAction`) |
| `IdCover` | `IDCOVER` | `comspec_sse_fnc_idCoverPage` | pack SSE `identity` | Ma couverture | idem | serveur SSE (`fn_idClient`), Athena en option (`fn_idCoverAthena`) |
| `Wanted` | `WANTED` | `pageWanted` (routé par `fn_pageRender`) | ATAK `main` | Recherchés | idem, **et masquée si le service Athena est refusé** (`fn_appVisible`) | portail Athena (web) |
| `Fiches` | `FICHES` | `comspec_atak_native_fnc_pageFiches` | ATAK `main` | Fiches | idem, **et Athena non refusé** | portail Athena (web) |
| `SseAlert` | `SSEALERT` | `comspec_atak_native_fnc_pageAlertes` | ATAK `main` | Avis et vigilance | idem, **et Athena non refusé** | portail Athena (web) : alertes, émission, signalement, file « Mes envois » |
| `DataLink` | `DATALINK` | `comspec_atak_native_fnc_pageDataLink` | ATAK `main` | Extraction | installation, interdiction, mode Essentiel | serveur SSE (`fn_dl7Downloaded`, câble DL-7) |
| `Sse` | `SSE` | `pageSse` (routé par `fn_pageRender`) | ATAK `main` | Console d'analyse (ancien nom « SSE ») | idem | serveur SSE (dossier en cours, concordances) |
| `SseHub` | `SSEHUB` | `comspec_atak_native_fnc_pageSseHub` | ATAK `main` | SSE (l'application) | visible dès qu'**une** fonction est visible ; filtre du mode Essentiel d'abord | aucune (lit les compteurs déjà en mémoire) |

Apps du téléphone **qui ne sont pas du SSE** et ne sont donc pas regroupées : FRS / FRM (`Intel`), Reco, SALUTE, Photo, Galerie, Vidéo live,
Marquer tracking, Comptes rendus, CR fin d'op, Anti-drone. Les photos de preuve et les
détections du travail SSE se font dans « Sur le terrain » (`fn_evidencePhoto`), pas dans l'app Photo. OSINT reste cachée (`hidden=1`).

Autres points d'entrée qui ouvraient une app SSE (tous inchangés, ils ouvrent la même page, désormais dans la coque) :
touche SEEK II (`fn_openSeekKeybind` : `SSE` ou `SSEFIELD`), actions ACE du pack (`comspec_sse_fnc_openApp` : `FICHIERS`, `WANTED`,
`IDCHECK`), notifications du téléphone (`fn_notify` avec la page `SSEFIELD`), câble DL-7 (`DATALINK`), liens entre apps (`FICHES`,
`WANTED`, `SSE` depuis Avis et vigilance, Recherchés, Tinder), fenêtres BII.

## 2. Plan de regroupement (réalisé)

* **Une tuile.** `SseHub` est la seule app SSE à la racine des sections, aux épingles, dans la vue regroupée par rôle (`fn_appSpaces`) et dans le
  lanceur. Les huit autres classes ne sont plus répétées à la racine (`fn_sseHub "rootHidden"`), mais restent des apps déclarées : `fn_navigate`
  accepte toujours leurs pages, la recherche du lanceur les trouve, un groupe du joueur peut encore les contenir.
* **Accueil de l'app** (`fn_pageSseHub`) : champ « Chercher une fonction » (nom, phrase, section), compteurs « Avis en vigueur » et « Envois en
  échec » (lus dans `COMSPEC_ATAK_Alertes` et `COMSPEC_ATAK_AlertesOut`, rien de plus), puis trois sections repliables.
  * Contrôle et identités : Contrôle ID, Fichiers, Ma couverture.
  * Terrain et analyse : Sur le terrain, Console d'analyse, Extraction.
  * Avis et recherches : Avis et vigilance, Recherchés, Fiches.
  Chaque ligne a l'icône, le nom, une phrase et la pastille de l'app d'origine ; une section sans fonction visible n'est pas dessinée.
* **Coque autour de chaque fonction.** Dès qu'une page SSE est la page active (par l'accueil, une notification, une touche, un lien profond), la
  zone de contenu est réduite d'une barre (`fn_layoutGet` : clé `sseStrip`, `body` raccourci) et `fn_pageRender` dessine, par `fn_formRender` « tabs »,
  la barre **SOMMAIRE + fonctions de la même section** avec pastilles. Le titre devient « SSE · fonction ». La page reste la page active
  (`activePage` inchangé), donc les rafraîchissements des sous-écrans (`activePage == "SSEALERT"`, `pageRender` différés, `backSub`) fonctionnent
  sans modification.
* **Navigation.** Changer de fonction remplace l'entrée d'historique (pas d'empilement) ; SOMMAIRE retire de l'historique ce qui suit l'accueil de
  l'app ; la touche retour d'une fonction ramène à l'accueil de l'app (ou à l'écran d'origine si on y est entré par un raccourci).
* **Migration des profils.** Une fois par partie (`fn_sseHub "migrate"`, appelée par le lanceur), les épingles (`COMSPEC_ATAK_PinnedApps`) et les
  apps récentes (`COMSPEC_ATAK_AppRecent`) qui pointaient une ancienne tuile SSE sont remplacées par `SSEHUB` (sans doublon, ordre gardé). Une
  ouverture future d'une fonction compte pour l'app dans les récentes (`fn_navigate`). Profil vide, absent ou abîmé : aucun effet, aucune erreur.
* **Droits.** L'app ne contient que les fonctions dont `fn_appVisible` est vrai (même fonction que pour la tuile d'origine : installation,
  interdiction par l'encadrement, Athena refusé, mode Essentiel). Ouvrir une page reste filtré par `fn_navigate` (interdit : refus ; désinstallée :
  fiche de l'App Store).
* **Textes.** Français dans le code, anglais dans `i18n/en.txt` (entrées ajoutées pour les sections, la barre, la recherche, les compteurs).

## 3. Risques et points à valider en jeu

| Risque | Pourquoi | Parade / à vérifier |
|---|---|---|
| Barre mal dimensionnée (petit écran, portrait MINI, texte agrandi) | hauteur de la barre = 1,05 ligne de formulaire + 3,6 marges, non vue à l'écran | vérifier en MINI portrait, FULL paysage, texte x1,3 ; ajuster `_sseH` dans `fn_layoutGet` |
| Une page SSE qui ne mesure pas sa zone avec `body` | elle déborderait sous la barre | toutes celles lues passent par `layoutGet "body"` ; contrôler les neuf écrans |
| Défilement : le groupe de la barre est un second formulaire | son nom de défilement compte dans `COMSPEC_ATAK_ScrollGroups` | vérifier que le défilement d'une fonction est gardé au redessin |
| Quatre onglets sur écran étroit | `tabs` réduit la police puis passe à la ligne | vérifier « SOMMAIRE / TERRAIN / ANALYSE / EXTRACTION » en portrait |
| Groupes de joueur (`fn_appFolders`) contenant une ancienne classe SSE | non migrés : l'app d'origine y reste ouverte dans la coque | accepté, hors demande ; à migrer si les joueurs s'en plaignent |
| Compteur « Avis en vigueur » = alertes déjà en mémoire | pas de requête à l'ouverture, 0 tant que l'app Avis et vigilance n'a pas chargé | ouvrir Avis et vigilance une fois ; ajouter un chargement à la demande si besoin |
| Premier chargement des icônes de l'app | inchangé (icônes déjà utilisées) | — |
| Aucune mesure de rendu en jeu | pas de partie lancée pendant ce travail | à passer par le coordinateur (onglet PERFS du Debug) |

Perf : aucune boucle par image ni scrutateur ajouté ; la mémoire de mise en page garde un seul bit de plus dans sa clé ; l'accueil relit neuf apps
(visibilité, pastille) à chaque dessin, comme l'ancien dossier ; la barre relit les mêmes données à chaque dessin d'une fonction.

## 4. Contraintes respectées

Aucun affichage à l'écran hors téléphone (ni toast, ni hint, ni systemChat), pas de petit libellé au-dessus d'un titre, aucune mention de fiction, pas de
changement de version ni de binaire, COMSPEC Link non touché.

## 5. Tests

`tests/Unit/AtakSseAppAssetTest.php` : une seule tuile ; sections mappées sur les pages d'origine ; même liste de pages dans `fn_sseHub`,
`fn_layoutGet` et `fn_pageRender` ; alias et migration des épingles ; conditions de droits ; textes anglais ; sources silencieuses et équilibrées.
`tests/Unit/AtakSseHubLauncherAssetTest.php` mis à jour (une fonction suffit à afficher l'app, accueil à sections).
