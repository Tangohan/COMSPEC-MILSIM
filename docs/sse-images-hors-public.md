# Images SSE hors du dossier public

Suite de la confidentialité des images de membres (PR #1709). Les portraits d'identité, signatures et fonds de papiers SSE
étaient rangés sous `public/uploads/…` et servis directement par le serveur web : quiconque connaissait l'adresse exacte d'un
fichier le téléchargeait sans passer par les contrôles d'accès du site. Ils sont désormais rangés HORS du dossier public et
ne sortent que par les routes contrôlées du portail SSE.

## 1. Inventaire des dossiers d'images SSE

| Dossier (sous `public/uploads/`) | Écrit par | Chemin gardé en base | Lu par | Après ce correctif |
|---|---|---|---|---|
| `sse-id-photos` | `SseIdImageService` (atelier de papiers, fiche SSE, dossier membre admin, espace opérateur), `PhotoSseTransfer` (photothèque vers SSE) | `sse_identities.photo_path`, `sse_file_photos.path` (copies et envois de membres) | routes protégées `identites/{id}/photo` et `photos/{id}/image`; PDF (avis de recherche, fiche, dossier); espace du titulaire (donnée intégrée) | **privé** (`storage/private/sse-images/sse-id-photos`) |
| `sse-id-signatures` | `SseIdImageService` (fichier et pad de signature `SignatureImageService`), `SseSignatureStrokes` (SEEK II) | `sse_identities.signature_path`, `sse_seek_records.signature_image`, `sse_persons.signature_image` | route `identites/{id}/signature`; pages de fiche SEEK II (donnée intégrée); PDF | **privé** (`…/sse-id-signatures`) |
| `sse-id-backgrounds` | `SseIdImageService` (gabarit personnalisé) | `sse_id_documents.background_path` | route `documents/{id}/fond` | **privé** (`…/sse-id-backgrounds`) |
| `sse-flags` | `SseIdImageService` (drapeau de pays fictif) | `sse_id_countries.flag_path` | adresse publique | public (drapeau fictif, non sensible) |
| `sse` (racine) | `SseApiController` (photo d'une fiche personne envoyée par le jeu) | `sse_person_photos.image_path` | adresse publique | **inchangé** (voir « Reste à faire ») |
| `sse/evidence` | `SsePortalController`, `AtakApiController` (pièces versées à un dossier) | colonne `image_path` des pièces | adresse publique | **inchangé** |
| `sse/objects` | `SsePortalController` (objets) | `meta.image_path` | adresse publique | **inchangé** |
| `sse/fiches` | `SseFieldNoteService` (pièces jointes de fiches de renseignement) | `file_path` | adresse publique | **inchangé** |
| `recon`, `intel` | photos terrain | `recon_images` | déjà à l'abri pour les clichés confidentiels (`storage/private/recon`, migration existante) | inchangé |

Déjà privé avant ce correctif : `storage/private/sse-seek` (clichés du terminal SEEK II, route `photos/{id}/image`),
`storage/private/recon`, `storage/private/photo-originals`.

**Serveur web.** Sur le VPS (nginx, `docs/nginx.example.conf`) la racine est `public/` et les fichiers d'`/uploads/` sont servis
en statique avant PHP. Sur l'hébergement Apache (`public/.htaccess`), `uploads/` est exclu de la réécriture et sert donc aussi les
fichiers directement. Dans les deux cas, un fichier sous `public/uploads/sse-id-*` est téléchargeable par son adresse.

## 2. Ce qui change dans le code

* Nouveau stockage privé : `storage/private/sse-images/<dossier>/<communauté>_<aléa>.<ext>`, chemin gardé `sse-private/<dossier>/<fichier>`.
  Tout nouvel envoi (`SseIdImageService`, `PhotoSseTransfer`) y est écrit ; plus rien de sensible n'est écrit sous `public/`.
* `App\Support\SseImageStorage` : seul point de résolution des chemins. Il accepte les DEUX emplacements (privé, et public hérité
  `uploads/…`) le temps de la migration, refuse `..`, les octets nuls, tout dossier ou nom hors forme stricte, et vérifie le vrai type
  de l'image (premiers octets, jamais l'extension).
* Les images sensibles (privées ET héritées) ne sont plus jamais affichées par une adresse publique : les écrans pointent vers les
  routes contrôlées, qui appliquent `SseMemberImageGate` (titulaire, hiérarchie, habilitation, dérogation), l'habilitation « Confidentiel »
  pour les couvertures et fausses identités, et journalisent les consultations confidentielles :
  * `GET /atak/sse/fichiers/identites/{id}/photo|signature` ;
  * `GET /atak/sse/fichiers/photos/{id}/image` ;
  * `GET /atak/sse/fichiers/documents/{id}/fond` (nouvelle route, fond de gabarit).
  En-têtes : `Content-Type` vérifié sur le contenu, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store` (confidentiel,
  identités, fonds), `Referrer-Policy: no-referrer`, pas de liste de dossier ; refus ou fichier absent : 404 identique, aucun octet.
* Hors portail (fiche du titulaire, administration des membres, signatures affichées dans une page SEEK II déjà protégée) : donnée intégrée
  (`data:`) plutôt qu'une adresse de fichier.
* Le pack de papiers pour le jeu n'expose plus le chemin d'une image sensible (valeur `null`) ; le mod ne lit pas ces images.
* Aucune migration de schéma : seules des valeurs de colonnes existantes changent (`uploads/sse-id-…` devient `sse-private/sse-id-…`).

## 3. Bascule pas à pas (production)

Rien n'est déplacé tant que ces étapes ne sont pas lancées à la main sur le serveur.

1. **Déployer le code** de ce correctif. Aucun fichier ne bouge : les chemins hérités restent lisibles, et les écrans passent déjà par les
   routes contrôlées. Les nouveaux envois vont dans `storage/private/sse-images`.
2. **Sauvegarde** : copie complète de `public/uploads/sse-id-photos`, `sse-id-signatures`, `sse-id-backgrounds` (par exemple
   `tar czf sse-images-AAAAMMJJ.tgz public/uploads/sse-id-*`) et dump de la base (tables `sse_identities`, `sse_id_documents`,
   `sse_file_photos`, `sse_seek_records`, `sse_persons`). Vérifier que `storage/private/` est inscriptible par l'utilisateur de PHP et
   hors de la racine web.
3. **Simulation** (ne modifie rien) :
   `php tools/sse-move-images-private.php`
   Elle liste chaque fichier, sa taille, les lignes qui le référencent, le total, les collisions (une copie privée différente existe déjà)
   et les fichiers introuvables. Corriger ou noter chaque collision et chaque fichier introuvable avant de continuer.
4. **Essai prudent** : `php tools/sse-move-images-private.php --apply --limit=20`, puis ouvrir une fiche, un papier et un fond dans le site.
5. **Déplacement** : `php tools/sse-move-images-private.php --apply` (ou `--tenant=ID` communauté par communauté). Pour chaque fichier :
   copie, contrôle taille + SHA-256 + type d'image, mise à jour du chemin en base dans une transaction, PUIS suppression de la copie publique.
   Une étape qui échoue ne supprime rien. Le script est reprenable : le relancer ne refait que ce qui reste. Journal :
   `storage/logs/sse-move-images-AAAAMMJJ-HHMMSS.jsonl` (à conserver).
6. **Vérification** : relancer la simulation, elle doit afficher `0 fichier(s)`. `find public/uploads -path '*sse-id-*' -type f` ne doit plus rien
   lister. Ouvrir un portrait, une signature, un papier à fond personnalisé, un avis de recherche PDF.
7. **Fermer l'ancien chemin sur le serveur web** :
   * nginx : la règle `location ~* ^/uploads/sse-id-(photos|signatures|backgrounds)/ { return 404; }` de `docs/nginx.example.conf`
     (avant la location des fichiers statiques), puis `nginx -t && systemctl reload nginx`.
   * Apache : retirer le `#` de la règle `RewriteRule ^uploads/sse-id-(photos|signatures|backgrounds)/ - [R=404,L]` dans `public/.htaccess`.
   Test : `curl -I https://<site>/uploads/sse-id-photos/<ancien nom>` doit répondre 404.

## 4. Retour arrière

* Avant l'étape 5 : rien à défaire.
* Après l'étape 5 : `php tools/sse-move-images-private.php --rollback=storage/logs/sse-move-images-AAAAMMJJ-HHMMSS.jsonl` recopie chaque
  fichier du journal vers `public/uploads/…` (si absent, avec contrôle d'empreinte) et remet l'ancien chemin en base. L'espace privé n'est pas
  vidé. Retirer ensuite la règle nginx / Apache de l'étape 7 si les adresses publiques doivent servir de nouveau.
* En dernier recours : restaurer l'archive et le dump de l'étape 2.
* Le code reste compatible avec les deux emplacements : retirer la règle web suffit à rendre les anciens fichiers lisibles par les routes.

## 5. Risques et reste à faire

* Les nouveaux envois vont dans le dossier privé dès le déploiement ; une sauvegarde de serveur qui ne couvrirait pas `storage/private/` les
  perdrait : vérifier qu'elle l'inclut.
* Non couverts ici (inventaire ci-dessus) : photos des fiches personne (`public/uploads/sse`), pièces de dossier (`sse/evidence`), objets
  (`sse/objects`), pièces jointes de fiches (`sse/fiches`). Ils portent encore leurs adresses publiques ; à traiter dans une suite avec le même
  `SseImageStorage` et le même script (ajouter les colonnes à `SseImageMigration::TARGETS`).
* Un lien direct vers `/uploads/sse-id-…` enregistré par un tiers cessera de fonctionner (c'est le but).
