# Identités unifiées (Outils opérateur)

Décision : il n'y a plus de « couverture » à part sur le site. Un membre a des **identités**, et rien d'autre : celle de son personnage et ses identités d'emprunt, chacune avec ses papiers et, s'il le veut, une histoire. Une seule est « Active en jeu ».

## Audit : ce qui existait

Trois objets parallèles sur `/operateur/identite` :

| Objet | Tables | Services | Écran |
|---|---|---|---|
| Identités (principale + d'emprunt) | `sse_identities` (état civil, photo, `kind`), `sse_file_profiles` (signalement), `operator_identity_states` (`item_kind = identity` : archivée) | `OperatorIdentityService`, `MemberIdentityPapersService`, `SseIdentityRepository` | onglet Identités |
| Papiers | `sse_id_documents`, `sse_id_kits` (kit envoyé en jeu) | `MemberIdentityPapersService`, `MemberIdentitySyncService` | onglet Papiers |
| Couvertures | `sse_cover_exports` (fichier « comspec-cover » v1 signé, dans `payload_json`), `sse_cover_export_links` (identité + papiers, #1551), `operator_identity_states` (`item_kind = cover` : active, révoquée, modifiée) | `SseCoverExportService`, `SseCoverGeneratorService`, `SseCoverSyncService` | onglet Couvertures |

L'« identité réelle » du membre (`user_profiles`, `user_legal_identities`) n'est pas un objet de jeu : c'est son état civil réel, réservé aux responsables du dossier personnel. Elle ne sert ici qu'à préremplir le personnage (`MemberIdentityPapersService::primaryProposal()`), et n'est jamais affichée comme une identité.

### Vrais doublons

Une couverture recopiait dans son fichier ce que l'identité portait déjà : nom, prénom, sexe, date et lieu de naissance, pays, métier, et la liste des papiers (type, pays, numéro, dates). Le membre choisissait deux fois ce qui est « actif » (identité envoyée en jeu, couverture active) et gérait les papiers à deux endroits (onglet Papiers, papiers « emportés » par la couverture).

### Ce qui était réellement distinct

Le texte de la légende (histoire, employeur, ville, adresse, téléphone, motif du déplacement, famille, contacts), le marqueur « couverture active », et le fichier signé.

### Ce que le jeu reçoit

- **Paquet d'identité** du serveur de jeu (`SseIdentityPackService`, `GET /api/sse/identity/pack`) et **kit** (`sse_id_kits`) : identités et papiers remis en mission. Inchangés.
- **Fichier de couverture** : le téléphone (app « Ma couverture › Synchroniser avec Athena », SSE 1.9.1) lit `GET /api/atak/covers`, `GET /api/atak/covers/files?refs=` ou le relais `op=~covers` / `op=~cover:…` du paquet. Contrat « comspec-cover » v1, lecture stricte (aucune clé inconnue).

## Nouveau modèle

- Type vu du membre : `personnage` (l'identité principale) ou `emprunt`. Jamais la nature exacte (`alias`, `cover`, `forged`, `generated`) : une identité montée par un responsable se lit « D'emprunt ».
- Champs communs : état civil, photo, signalement, nationalité, papiers.
- Histoire : texte libre facultatif (`sse_identity_stories.story`, 2000 caractères). Ses 8 premières lignes deviennent `cover.history` pour le téléphone.
- « Active en jeu » : un seul marqueur, celui de l'identité dont les papiers partent en mission (kit par défaut, sinon le personnage).
- Papiers : ils appartiennent à l'identité. La carte de l'identité les montre, l'onglet Papiers les détaille.

## Interface

- Onglets : `Identités`, `Papiers`. L'onglet `Couvertures` est supprimé.
- Liste de cartes avec pastille de type, « Active en jeu », filtres (toutes, active, personnage, d'emprunt, archivées) ; sections Identité · Papiers · Histoire · Jeu.
- Une seule création guidée « Nouvelle identité » : type → état civil → signalement → papiers à demander → histoire → récapitulatif.
- Anciennes adresses : `GET|POST /operateur/identite/couverture`, `POST /operateur/identite/couverture/{id}`, `GET /operateur/identite/couverture/{id}/telecharger` renvoient à `/operateur/identite` (`OperatorHubController::legacyCovers`), sans rien écrire.
- Accueil : tuile « Mes couvertures » retirée. Une épingle enregistrée sur la clé `couverture` est ignorée (`OperatorHubCatalog::layout`).

## Étape 4450 (`database/steps/4450_sse_identities_unified.php`)

`bootstrap/sse_identities_unified_migration.php`, idempotente, MySQL / MariaDB / sqlite, rejouable :

1. crée `sse_identity_stories` (histoire, date du dernier envoi au téléphone) et `sse_cover_export_merges` (trace de la fusion) ;
2. fond chaque couverture exportée pas encore traitée dans une identité du membre : lien de #1551 (`linked`), sinon même nom et même date de naissance (`matched`), sinon identité d'emprunt créée (`created`) ; révoquée (`revoked`) ou illisible (`invalid`) : rien à fondre ;
3. ajoute le texte de la légende à l'histoire de l'identité (une seule fois) ; garde `was_active`.

Mode à blanc : `SSE_IDENTITES_A_BLANC=1`. Rien n'est créé ni écrit ; l'étape liste ce qui serait fondu et les doublons (deux couvertures de la même personne, deux identités du même membre au même nom et à la même date de naissance).

L'identité envoyée en jeu n'est pas changée par l'étape : si la couverture active d'un membre n'était pas celle de son identité active, il choisit « Utiliser en jeu » sur l'identité voulue.

Tant que l'étape n'est pas passée : pas de champ histoire, le téléphone est servi comme avant. Aucune erreur.

### Tables dépréciées, à supprimer dans une étape ultérieure

Aucune n'est modifiée ni supprimée par l'étape 4450.

- `sse_cover_export_links` : plus aucun code ne la lit, hors l'étape 4450.
- `operator_identity_states`, lignes `item_kind = 'cover'` : lues seulement par l'ancien chemin (avant l'étape) et par l'étape.
- `sse_cover_exports` : encore écrite par les écrans des responsables (voir plus bas) ; à supprimer quand ces écrans le seront, avec `sse_cover_export_merges`.

## Points d'accès hérités du téléphone

Gardés, en lecture seule, même contrat (`SseCoverSyncService`, `AtakCoverSyncApiController`, relais `~covers`). Après l'étape 4450, la réponse vient des identités (`SseIdentityCoverFeedService`) :

- une entrée par identité non archivée du membre, avec les mêmes clés qu'avant ;
- `ref` : `CVR-XXXXXXXX` stable par identité (`SseCoverExportService::identityRef`) ;
- `cover` : état civil et papiers émis et valables de l'identité (`eligibleDocs`), histoire du membre, le reste tiré de l'identité comme le faisait `generateFromIdentity` (`coverFromIdentity`) ; pas de photo ;
- fichier daté du premier jour du mois (UTC) : contenu et signature stables dans le mois, jamais servi expiré ;
- `active` : l'identité « Active en jeu » ; `by_manager` : toujours faux ; `downloaded` : l'identité a déjà été envoyée au téléphone.

S'y ajoutent les fichiers générés depuis par un responsable et pas encore fondus (absents de `sse_cover_export_merges`), servis comme avant ; rejouer l'étape les fond.

Le mod publié (SSE 1.9.1) n'a pas à changer. Pour SSE 1.10.0, à faire côté mod, cosmétique : renommer l'app « Ma couverture » en « Mes identités » dans `stringtable.xml`.

## Ce qui est gardé côté responsables

- Génération d'un fichier pour un membre (`MemberCoverExportController`, `views/partials/sse_cover_export.php` : « Ma situation › Mes papiers », fiche membre des effectifs, fiche membre du Bureau SSE) : gardée telle quelle. C'est le seul producteur du fichier que le mod importe hors ligne.
- Nature « couverture » d'une identité (`sse_identities.kind = cover`), couvertures nées en jeu (`SseIdGameCoverService`), briefs de couverture des opérations : vérité de jeu des responsables, non touchée. Le membre voit ces identités comme « D'emprunt ».
