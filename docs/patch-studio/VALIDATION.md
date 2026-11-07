# Validation de cette branche

## Exécuté avec succès

- PHP portable 8.4.23, GD/DOM/mbstring/PDO SQLite/zlib/ZIP : `php tests/patch-studio/backend.php`, **40 contrôles réussis**.
- `node tests/patch-studio/core.test.mjs`, **9 tests réussis** : modèles éditables, typographie/motifs/transformations, historique borné, groupement, duplication, variables/échappement, géométries/bordures, composition nocturne/sélection.
- Les SVG générés pour chaque géométrie et chaque position de bordure sont relus par DOMDocument : XML valide après correction des attributs dupliqués sur les bordures extérieures.
- Tests backend : validation JSON et limites, SVG actif/entités, signatures, permissions tenant/partage/édition, conflits de sauvegarde, historique et rollback transactionnel, accès aux assets, stockage/intégrité, PNG non carré, DXT1/DXT5 alpha et relecture par PaaDecoder, miniature, checksum PBO, contenu ZIP.
- Vérification syntaxique PHP des fichiers modifiés et `node --check` des modules ; `git diff --check`.

Ces tests utilisent le SQL de migration adapté à SQLite dans le harness, sans identifiants ni base de production. Ils ne remplacent pas une application MySQL/MariaDB en staging.

## Essai navigateur exécuté

`tests/patch-studio/router.php` est un routeur de test localhost avec session fixture et SQLite isolée. Ne jamais le déployer.

```
PATCH_STUDIO_TEST=1 PATCH_TEST_ROOT=/tmp/athena-patch-browser php -S 127.0.0.1:8765 -t public tests/patch-studio/router.php
PATCH_TEST_URL=http://127.0.0.1:8765 node tests/patch-studio/browser.cjs
```

Le script utilise Playwright et Chromium (`CHROMIUM_PATH` optionnel). Il couvre les vrais contrôleurs HTTP, le texte/police/espacement/motif/rotation, annuler/rétablir, import sécurisé, sauvegarde/rechargement, CSRF, conflit et les cinq modes d'export. Les essais navigateur ont désormais réussi sous Chromium avec le serveur PHP de test isolé. Ils vérifient les deux réponses de modules `.js` (type MIME JavaScript et version d’URL identique), le parcours d’édition/import/sauvegarde/rechargement, le CSRF, les conflits et les cinq modes d’export. Le refus HTTP 403 des formules Free/Standard/Pro est vérifié côté page et API.

`php tests/patch-studio/access-ui.php` : **58 contrôles réussis**, couvrant le seuil Pro+, les offres actives/expirées, l’essai fondateur Pro, les impayés, les paiements manuels expirés, les anciens plans sans clé de fonctionnalité, sa désactivation explicite, les appels directs aux API et les vues éditeur/refus.

La suite PHPUnit existante reste non exécutée (vendor sans autoloader/exécutables), ainsi que la compilation DLL .NET. Ces validations ne sont pas assimilées aux tests navigateur.

## Obligatoire avant production

- Installer les dépendances habituelles, lancer PHPUnit et les contrats de routage/injection ; compiler COMSPEC Link .NET 8 (puis NativeAOT Windows suivant la procédure du dépôt).
- Compléter la recette visuelle sur les navigateurs et appareils cibles ; le parcours Chromium desktop ci-dessus est passé. Vérifier aussi les dimensions et alpha dans Arma.
- Appliquer la migration sur une copie MySQL/MariaDB ; vérifier multi-tenant et permissions réelles ATHENA avec lecteur, éditeur, propriétaire et admin. Tester les RH masquées/anonymisées et les identités multi-communauté.
- Importer SVG malveillants/volumineux, polices valides/invalides et images défectueuses ; vérifier quotas et refus d'accès sans session. Tester interruption autosave et conflit entre deux onglets.
- Inspecter PAA avec HEMTT/TexView et charger le PBO exporté dans Arma ; vérifier alpha, orientation et formats 256×128, 512×256, 512×512, 1024×512 et 1024×1024.

## Recette Arma (aucun résultat moteur acquis ici)

1. Client seul : menu PATCHES, cinq sources, recherche, session liée, catalogue paginé, vignette PNG, sélection persistante. Déconnecter/reconnecter ATHENA, ressources privées retirées, catalogue vide.
2. PAA téléchargé localement : `getTextureInfo` sur chemin absolu puis relatif, avec et sans filePatching. Relever le RPT, sans assimiler ce résultat à un montage addon.
3. Addon exact chargé : classe CfgAthenaPatches, hash attendu, getTextureInfo, index 1 du téléphone au gilet. Confirmer que les patchs historiques/mods/mission fonctionnent toujours.
4. Serveur dédié + deux clients : addon sur tous ; retirer l'addon d'un client, confirmer le repli local et l'absence de transfert d'image par setObjectTextureGlobal. Tester JIP, reconnexion, mort/réapparition, changement d'équipement et ressource manquante.
5. Publier une révision en session : ancien addon conservé, nouveau hash non appliqué tant que le nouvel addon n'est pas distribué/monté. Vérifier cache corrompu/supprimé, échec réseau, reprise par nouveau téléchargement, perte de liaison ATHENA et restauration du profil.
6. Nuit : comparer compositions standard/nocturne, sans conclure à un matériau IR physique.

## Écarts fonctionnels encore ouverts

- Sélection RH par IDs autorisés ; pas de sélecteur automatique d'unité/groupe ni de régénération serveur lors d'un changement d'indicatif.
- Pas de garantie pixel-identique entre moteurs de navigateur ; pas de rendu serveur autonome du JSON.
- Bordures couleur/styles/couture ; pas de motif image indépendant sur le contour, pas d'opérations booléennes vectorielles union/soustraction.
- Aimantation sur grille et bords/centre du canevas ; pas de guides arbitraires déplaçables, de distribution entre objets tournés ni de sélection rectangulaire.
- Pas de purge physique automatique en ligne des archives/historiques, pas de reprise Range.
- Support 3D seulement celui déjà démontré par les configs ; pas de généralisation à tous les uniformes ou tablettes.

Ces écarts et les recettes non exécutées empêchent d'annoncer la couverture intégrale du cahier des charges ou l'aptitude à la production.


## Textures image MultiCam

- `php tests/patch-studio/textures.php` : **44 contrôles réussis** (cinq JPEG, dimensions/proportions, SHA256, source/droits, validation JSON, références malveillantes refusées et contenu exact des ZIP).
- Le neuvième test du moteur vérifie le remplissage image des caractères, les transformations et la fidélité après sérialisation, sans URL externe dans le rendu SVG. Une texture absente entraîne une erreur explicite.
- `node tests/patch-studio/textures-browser.cjs` : **réussi** sur serveur PHP isolé : les cinq variantes donnent des rendus distincts dans le texte ; fond, réglages de texture, exports PNG/PAA/ZIP/PBO, sauvegarde, réouverture à froid, pixels identiques et absence de requêtes vers le site source. La suite navigateur générale est également passée.
- Aucun nouveau test Arma/DLL ; aucune nouvelle migration SQL. Les exports graphiques sont validés ici, pas l'apparition automatique d'un fichier téléchargé sur un support 3D.
