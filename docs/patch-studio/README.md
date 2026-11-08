# ATHENA Patch Studio

Cette branche apporte un éditeur SVG multicouche intégré à ATHENA. La compilation COMSPEC Link et la recette Arma décrites ci-dessous restent à effectuer ; les essais navigateur et les contrôles de formule Pro+ ont été exécutés. Ce document ne certifie pas une mise en production.

## Installation et déploiement

1. Sauvegarder la base et le stockage. Appliquer l’étape active `php8.4 run-migrations.php --step=patch_studio_workspace`. Elle réutilise le SQL initial pour les six tables et ajoute la classification des setups et les vignettes. La présence du fichier SQL seul ne suffit pas : le lanceur exécute uniquement sa file active. Voir `WORKSPACE.md` pour le diagnostic des 503 et la migration idempotente.
2. Déployer les contrôleurs, services, routes, navigation, vue et assets. PHP 8.4, GD, DOM, mbstring, PDO MySQL et zlib requis. Aucun service Node en production. Les modules du Studio utilisent désormais des fichiers `.js`, comme la page 9-line, pour éviter le MIME `application/octet-stream` des `.mjs` sur certains serveurs. Le point d’entrée et ses deux modules importés partagent une version d’URL pour renouveler le cache.
3. Donner au compte PHP l'accès en écriture à `storage/patch-studio` et `storage/cache`. Le stockage doit rester hors de la racine HTTP. Ne jamais créer d'alias public vers ces dossiers. Limites HTTP/PHP : upload 8 Mio, POST 48 Mio pour les exports PNG 4K en base64 ; memory_limit=512M recommandé pour ces rendus. Les limites applicatives restent actives.
4. Recompiler COMSPEC Link (`mod/UptoDate/COMSPECExtension`, .NET 8), puis reconstruire le PBO natif avec le mécanisme habituel. Ne pas déployer un stub managé comme DLL NativeAOT ; conserver la procédure de preuve de client officiel du dépôt.
5. Recetter en staging. Distribuer les addons de patch et leurs signatures à tous les participants AVANT de démarrer Arma. Les PBO exportés sont non signés : la signature relève du déploiement serveur.
6. Accès : menu « Ma situation → Patch Studio », route `/patch-studio`. Formule effective **Pro+ minimum**, y compris une offre Pro+ valide. Page, API web, ressources privées et catalogue/fichiers ATAK sont contrôlés côté serveur par `PatchStudio\PlanAccess`. Un administrateur d’organisation ne contourne pas ce contrôle. La clé `patch_studio` figure dans le catalogue des formules : absente d’un ancien `features_json`, elle reprend le défaut du catalogue ; aucune nouvelle migration SQL n’est nécessaire. Une désactivation explicite de la fonctionnalité reste respectée.
7. L’interface reprend le thème de `/9-line` : feuille de style partagée, emblème ATHENA, Inter/JetBrains Mono, noir et vert émeraude. Un avertissement MILSIM/préversion/droits/limites Arma reste visible. Pas de seconde connexion.

## Utilisation

Depuis l’accueil, retrouver les créations récentes, utiliser un setup ou choisir un modèle / « Nouveau patch ». Ajouter des formes, textes, images et motifs à gauche. Un motif s'applique à la forme ou au texte sélectionné ; sans sélection, il crée un fond indépendant. « Remplir » applique une image importée dans les caractères ou la forme. Le contour reste séparé. La bibliothèque propose les textures JPEG MultiCam®, Arid, Tropic, Alpine et Black, stockées localement avec source et SHA256. Les motifs vectoriels précédents restent disponibles pour préserver les anciens projets.

Sélectionner/glisser sur le canevas ; poignée inférieure droite pour redimensionner, poignée supérieure pour tourner. Maj conserve le rapport lors du redimensionnement et permet la sélection multiple. Espace + glisser déplace la vue. Molette règle le zoom. Grille et aimantation sont facultatives. Les positions, dimensions, proportions et typographies sont aussi réglables dans les propriétés. Les groupes se modifient via « Éditer les objets » ; « Sortir du groupe » remonte d'un niveau. Les calques se réordonnent par glisser-déposer, se verrouillent et se masquent. Le nom et l'opacité se règlent dans les propriétés.

Ctrl+S, Ctrl+Z, Ctrl+Y/Ctrl+Maj+Z, Ctrl+C/V/D, Suppr, Échap et flèches sont raccordés. Dans un champ de texte, seuls Ctrl+S sont interceptés ; les opérations d'édition natives restent disponibles. L'historique en mémoire est borné à 80 états. Sauvegarde après 3,5 s d'inactivité sur un projet existant. Premier enregistrement manuel. Brouillon de récupération local isolé par tenant et compte, révisions SQL conservées. Un conflit 409 suspend l'autosave : recharger ou « Enregistrer sous ».

Bibliothèque : projets privés, organisation, partages, archives/supprimés. Gérer le projet ouvert permet de changer l'état, de restaurer une archive et d'accorder les rôles lecteur/éditeur à un ID de membre actif du même tenant. L'historique restaure une version sans effacer les suivantes. « Supprimé » est une suppression logique, restaurable.

Les variables sont `{callsign}`, `{unit}`, `{group}`, `{rank}`, `{firstname}`, `{lastname}`, `{country}`, `{organization}`. Les données réelles sont chargées à la demande et ne sont pas stockées dans le modèle. Les services RH existants filtrent les identités et affectations ; sans droit de pilotage des effectifs, seul le compte courant est résolu. `group` et `country` ne sont pas exposés par le contrat RH retenu : utiliser les substitutions. La fenêtre Membres ATHENA charge la liste autorisée depuis l’API, avec recherche et filtre d’unité visible ; une série prend cent membres maximum, revérifie leurs droits et produit un PNG par membre. Les téléchargements multiples peuvent nécessiter une autorisation du navigateur. Il n’y a pas de sélection automatique de groupe ni de tâche de régénération en arrière-plan après changement d'indicatif : actualiser les membres, générer et publier une nouvelle révision.

Les polices libres sont embarquées avec leurs licences OFL. DIN n'est pas distribué sans licence ; son import TTF/OTF est possible avec attestation de droits d'incorporation. Une police importée reçoit un nom interne stable, ne modifie pas les polices système et n'est servie qu'aux comptes autorisés. Les polices à glyphes SVG actifs sont refusées.

Les réglages sont organisés en Support, Texture, Bordure, Texte et Rendu. Le modèle **Laser Cut · MultiCam** utilise deux textiles indépendants, des découpes masquées, une couture suivant le contour et un camouflage continu entre fond et bordure. Voir [FABRICATION.md](FABRICATION.md) pour les matières, la construction, les limites précises du rendu et le PNG haute résolution.

L’accueil, les setups enregistrables, la sélection dynamique des membres et la nouvelle fenêtre d’export sont décrits dans [WORKSPACE.md](WORKSPACE.md).

## Modèle et persistance

`ARCHITECTURE.md` contient l'audit des composants réutilisés. Le contrat exécutable est `app/Services/PatchStudio/Document.php`. `tests/patch-studio/project.json` est un projet v1 complet. Le document comprend `version`, `canvas`, `variants.standard`, `variants.night` facultative, `fallbacks` et les réglages facultatifs `render`. Les objets sont `shape`, `text`, `image`, `group` ; fond, texture, symbole et masque se composent avec ces primitives. Un groupe possède des enfants et un masque géométrique facultatif. Pas de markup arbitraire ni d'URL dans le JSON. Un remplissage `texture` référence un `textureId` immuable du catalogue commun ; il ne crée pas de ressource privée en base.

Chaque objet porte identité, nom, transformation, dimensions, opacité, visibilité et verrouillage. Texte : contenu, famille/ressource de police, taille, graisse, italique, casse, espacements, interligne, alignements et courbure. Remplissage : `solid`, `gradient`, `pattern`, `texture`, `asset`, `none`, avec transformation et réglages colorimétriques. Contour : couleur, épaisseur, opacité, style et position ; position intérieure/extérieure réservée aux formes. Image : asset stable et recadrage en fractions. Limites : 160 objets variantes comprises, profondeur 5, canevas 32–2048 px, 1 Mio JSON. Valeurs numériques finies et bornées, propriétés inconnues refusées.

Tables :

- `patch_studio_projects` : tenant, propriétaire, visibilité, état, révision, document courant, `kind` (patch/setup), hash et révision de vignette privée.
- `patch_studio_versions` : document immuable par numéro de révision et auteur.
- `patch_studio_shares` : lecteur/éditeur par projet et membre.
- `patch_studio_assets` : identité stable, propriétaire, type validé, SHA256, licence et taille ; dédoublonnage par propriétaire.
- `patch_studio_asset_refs` : références conservatrices couvrant toutes les versions.
- `patch_studio_releases` : PNG original, miniature et PAA immuables, révision et variante.

Les blobs sont adressés par SHA256, écrits atomiquement avec noms serveur, intégrité revérifiée à la lecture. Aucun chemin utilisateur n'est exécuté. Les imports raster sont décodés puis réencodés, sans métadonnées actives. SVG : allowlist d'éléments et attributs, scripts/événements/styles/images embarquées/liens externes supprimés ; DOCTYPE/ENTITY refusés. Pas d'import d'archive à décompresser. Font SFNT : répertoire de tables borné et validation supplémentaire OTS du navigateur. Quotas : 300 assets, 256 Mio par propriétaire ; 250 projets. Les données supprimées et anciennes versions retiennent volontairement leurs ressources. Pas de purge physique automatique en ligne : la purge nécessite une fenêtre de maintenance et une sauvegarde ; une suppression anticipée casserait l'historique.

## API web

Toutes les routes sont sous `/api/patch-studio`, protégées par `AuthMiddleware`. Écritures : en-tête `X-CSRF-Token`, JSON sauf upload multipart. Réponses privées `no-store`.

| Méthode | Route | Données / résultat |
|---|---|---|
| GET / POST | `/projects` | Liste autorisée / `{name,document}` crée une révision 1 |
| GET / PUT | `/projects/{id}` | Projet / `{revision,name,document,visibility,status}` ; 409 si révision concurrente |
| GET | `/projects/{id}/history` | 100 dernières versions ; `?revision=N` lit le document d'une version |
| POST | `/projects/{id}/share` | `{userId,role: reader|editor|remove}` ; propriétaire/admin seulement |
| GET / POST | `/assets` | Assets du propriétaire / multipart `file`, `fontLicense=yes`, `license` |
| GET | `/assets/{id}` | Octets après vérification d'accès tenant/projet |
| GET | `/variables?users=1,2` | Membres autorisés et valeurs RH filtrées |
| POST | `/projects/{id}/export` | `{revision,variant,format,png,letterbox,selection}` |

`png` est la chaîne base64 sans préfixe data:, `format` vaut png/paa/zip/pbo/publish. Enregistrer avant publication/export. La duplication est une création à partir du document ouvert, l'archivage/suppression/restauration un PUT d'état ; aucune route concurrente de persistance. 180 requêtes/minute/utilisateur et 12 rendus/minute ; un verrou non bloquant limite les rendus simultanés à un par compte. Erreurs 401/403/404/409/422/429/503, pas de trace serveur exposée.

## Rendu et export

`compose()` produit le SVG pour le canevas ET le rendu ; les motifs sont des remplissages SVG découpés exactement par les glyphes, indépendants du contour. `raster()` incorpore images et polices chargées, attend leur disponibilité puis rasterise dans Canvas. Les textures réellement utilisées sont chargées à la demande, leur SHA256 est vérifié puis leurs octets sont incorporés au SVG avant rasterisation. Le serveur valide et réencode le PNG, réemploie `PaaEncoder` et les méthodes `buildZip/buildPbo` d'`AtakTranslationModBuilder`. Aucun processus shell de conversion, aucun fichier temporaire de rendu. Les écritures de blobs temporaires sont nettoyées dans `finally`.

Le PNG envoyé par un éditeur autorisé est une entrée de rendu : le serveur ne recalcule pas les objets et ne prouve pas leur équivalence avec ce PNG. La fidélité est celle du navigateur de création ; des différences de rasterisation entre versions de navigateur/OS restent possibles. Ne pas présenter cette version comme un moteur de rendu serveur déterministe entre plateformes.

PNG : natif ou préréglage jusqu’à 4096 × 4096, alpha, choix du fond, canevas complet ou sélection. La rasterisation a lieu à la résolution finale ; PAA/ZIP/PBO/publication restent limités à 2048 px par côté. Les proportions sont préservées par centrage transparent. PAA : côtés puissances de deux, DXT1 opaque/DXT5 alpha, mipmaps et orientation de l'encodeur existant. ZIP : PNG/PAA, JSON éditable, manifeste d'assets/licences, textures JPEG nécessaires aux deux compositions avec leur catalogue/source/SHA256, sources config/préfixe, addon PBO et instructions. PBO : CfgPatches, CfgUnitInsignia et CfgAthenaPatches avec ID/variante/hash ; checksum SHA1. Un rendu publié ne peut pas être remplacé silencieusement : nouvelle révision obligatoire.

## ATAK, DLL, 3D et multijoueur

`GET /api/atak/patches?offset=N` : dix publications autorisées par page, variante/révision/hashes/classes exactes ; `nextOffset`. `GET /api/atak/patches/{id}/{revision}/{variant}/{png|paa}` : miniature PNG ou texture PAA. Session de jeu liée obligatoire via le mécanisme existant ; une clé de communauté seule n'autorise rien. ETag et SHA256 fournis.

COMSPEC Link : commande `AthenaPatches`, modes `list` et `download`. HTTP/auth/proof existants, args bornés, URLs serveur fixes, un téléchargement à la fois, taille 8 Mio maximum, vérification SHA256/signature, écriture atomique, cache de 128 Mio borné. Une erreur ne produit pas de fichier final. Les fichiers inchangés ne sont pas réécrits ; le serveur reste interrogé pour contrôler l'accès. Pas de reprise HTTP Range ni de transfert différentiel par blocs : un téléchargement interrompu se relance complètement, seuls les fichiers dont le hash change doivent être renouvelés.

SQF : source ATHENA ajoutée à `patchCatalog`, recherche/page/sélection/profil existants conservés. Les miniatures PNG passent par `slideWebData/slideWebShow` et CT_WEBBROWSER existants. Le PAA téléchargé sert de cache ; seule une texture déclarée dans `CfgAthenaPatches` monté et dont le hash attendu correspond peut être appliquée en 3D. Support vérifié : `COMSPEC_ATAK_VestPhone`, hiddenSelection `patch`, index 1. Les autres modèles ne sont pas supposés compatibles. Les tablettes JPC n'exposent pas de patch dédié dans les configs inspectées.

`fn_vestMount` publie la référence montée ; chaque client vérifie sa disponibilité et applique localement le patch COMSPEC de secours s'il manque. Le choix est conservé dans le profil, y compris l'identité publiée pour le repli hors connexion. Le catalogue est rechargé au plus une fois par minute. Une mise à jour nécessite une nouvelle publication et la distribution de l'addon correspondant ; le téléchargement en session ne monte pas de PBO.

Arma ne reçoit jamais des octets via `setObjectTextureGlobal`. Les tests client seul, dédié, deux clients et JIP sont indispensables. Les rapports antérieurs du dépôt signalent le refus hors PBO ; aucune expérience Arma nouvelle n'a été possible ici. Ni le navigateur intégré ni le repli 3D multijoueur ajouté ne sont certifiés par exécution moteur dans cette branche. La variante nocturne est une composition graphique indépendante : aucun RVMAT ni comportement IR physique ajouté.

## Validation et retour arrière

Voir `VALIDATION.md` pour les résultats et la recette. Pour revenir en arrière : retirer les routes/navigation Studio et les builds SQF/DLL de cette branche, redéployer les versions précédentes. Conserver les tables et blobs permet une réinstallation sans perte. Le SQL destructif de rollback est dans `migrations/rollback/2026_11_06_patch_studio.sql`, à utiliser seulement après sauvegarde/export. Aucun lancement automatique de rollback ni modification de la production n'a été effectué.

Les sources et conditions des textures tierces sont décrites dans `public/assets/textures/patch-studio/SOURCES.md`. Ces images proviennent du site officiel MultiCam et correspondent aux variantes demandées ; les filigranes sont conservés. Aucune licence libre ni performance IR/NIR n’est revendiquée. Aucun accès au site source n’est nécessaire en utilisation.
