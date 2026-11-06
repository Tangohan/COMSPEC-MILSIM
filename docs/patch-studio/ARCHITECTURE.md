# ATHENA Patch Studio — audit et architecture

## Cartographie vérifiée avant développement

- PHP 8.4, routeur maison `routes/web.php`, injection `App\Core\Container`, PDO MySQL/MariaDB. Pas de framework graphique dans package.json (Tailwind seulement).
- Session web : `AuthMiddleware`, `TenantContext`, `Gate`, `Csrf`. Session jeu : `ComspecApiKeyAuth`, `GameAuth.cs`, en-têtes existants de `AttachApiKeyHeader`. Aucun second login.
- RH : `UserRepository::listEffectifsRosterByIds`, `MemberNameService::identitiesFor`, `AtakOperatorPersonnelService::forUsers`, `OrgVisibilityCapabilities`. La visibilité doit être appliquée AVANT de retourner des variables. Les champs non résolus restent des substitutions explicites.
- `app/Support/PaaEncoder.php` : DXT1 opaque, DXT5 alpha, mipmaps, TAGG/SFFO, côtés puissances de 2 de 4 à 4096. Réutilisé sans modifier son comportement.
- `mod/COMSPEC_ATAK_Native/tools/paa_dxt.py` existe, Pillow/numpy, DXT5 (LZO optionnel). Pas de `pbo_patch.py` dans ce dossier : chemin réel `tools/pbo_patch.py`.
- `app/Support/AtakTranslationModBuilder.php` possède déjà les générateurs PHP ZIP et PBO (préfixe + SHA1). Réutilisés : pas d'appel shell avec chemins utilisateurs.
- SQF réel : `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/shell/fn_{patchCatalog,patchTexture,pagePatches,vestMount}.sqf`. Catalogue intégré + CfgUnitInsignia mods/mission ; choix dans `profileNamespace COMSPEC_ATAK_Patch`. Conservation de ces sources et clés.
- COMSPEC Link réel : `mod/UptoDate/COMSPECExtension/Extension.cs` (HTTP/auth/cache), `Extension_ItemIcons.cs` (lecture PBO), `Extension_WardrobeCache.cs`. L'extension native séparée gère notamment musique/BT.
- Images existantes : `AtakGameImage`, `DownloadBriefingSlideImage`, `ImageDataChunk`, `fn_texUsable.sqf`. Le dépôt rapporte déjà des refus moteur hors PBO ; getTextureInfo vérifie la lisibilité. Un chemin disque n'est pas une ressource montée.
- Support vérifié : `main/config.cpp`, classe téléphone au gilet `hiddenSelections[]={"camo","patch"}` ; `fn_vestMount` applique l'index 1. Les tablettes JPC ont `camo,screen`, pas un patch. Aucun emplacement inventé sur leurs modèles.
- `setObjectTextureGlobal` synchronise une référence, pas les octets. Le déploiement addon/mission sur tous les clients et le serveur est le repli normal. Aucun IR physique démontré ; variante nocturne = simulation visuelle.

## Décisions

Éditeur natif SVG, objets indépendants et schéma v1 strict, transformations, groupes, masque de groupe, motifs procéduraux originaux et ressources incorporées pour le rendu. Même fonction de composition pour le canevas et l'export. Aucun CDN requis. Le navigateur rasterise le SVG en PNG après chargement des polices ; PHP valide et réencode ce PNG, puis PaaEncoder produit le PAA. Il n'y a pas de moteur de rendu serveur concurrent : publier exige une rasterisation du navigateur, liée à la révision enregistrée. Le serveur n'atteste pas que le PNG soumis par un éditeur autorisé correspond aux objets JSON.

Projets et versions en SQL ; ressources et rendus en stockage privé sous `storage/patch-studio`. Les rendus publiés sont immuables et adressés par hash. ACL propriétaire, partage explicite lecteur/éditeur dans le tenant, lecture organisation, administrateur `admin.organization`. Aucun accès inter-tenant même pour une clé de communauté jeu. Contrôle optimiste de révision pour éviter les écrasements autosave.

Les patchs distants enrichissent le catalogue existant. Leur application 3D exige une classe de version exacte chargée par addon/mission. Les téléchargements servent à l'aperçu et au cache d'intégrité ; ils ne sont jamais appliqués globalement comme chemins disque. Les exports addon déclarent `CfgAthenaPatches` avec identité/version, plus `CfgUnitInsignia` ; cette config fournit la résolution sûre.
