# Accueil, setups, membres et initialisation du stockage

## Correction des erreurs 503

Le SQL initial `migrations/2026_11_06_patch_studio.sql` n’était pas inscrit dans `database/steps/registry.php`. Le lanceur actuel ne balaie plus les fichiers SQL historiques : `run-migrations.php` ne pouvait donc pas initialiser les six tables du Studio. Une installation sans ces tables échoue sur les listes de projets et de ressources, puis sur l’enregistrement précédant l’export. Ce cas est reproduit dans les tests. La production n’a pas été interrogée directement avec des identifiants administrateur ; une autre panne est identifiée par sa référence de journal.

Après fusion et déploiement, appliquer **cette étape obligatoire** depuis le répertoire de l’application :

```sh
php8.4 run-migrations.php --step=patch_studio_workspace
```

L’étape active `database/steps/3950_patch_studio_workspace.php` réutilise le SQL existant, crée les tables absentes et ajoute les colonnes `kind`, `preview_hash`, `preview_revision` et un index sur l’accueil. Elle est idempotente : une relance conserve projets, versions, ressources et publications, y compris sur une installation partielle. Elle n’exécute pas l’ensemble des anciennes migrations. Aucune création/altération de table lors d’un GET normal. La commande apparaît aussi dans la console de migrations habituelle. Les besoins PHP/GD et de stockage privé restent ceux du Studio ; pour la 4K, voir `FABRICATION.md`.

Une erreur de table/colonne Studio absente renvoie désormais HTTP 503 avec `code=patch_studio_migration_required`, `migration=patch_studio_workspace` et une référence d’incident. Les autres pannes restent `patch_studio_unavailable`. Les messages SQL et chemins internes restent dans le journal serveur, sous `Patch Studio [référence]`, et ne sont pas retournés au navigateur. L’accueil affiche l’erreur de bibliothèque avec une possibilité de réessayer ; l’export affiche l’erreur dans sa fenêtre et réactive son bouton.

Le service `AtakOperatorPersonnelService` était appelé par `Variables` sans déclaration dans `Container`. Son constructeur et ses dépendances sont maintenant enregistrés dans le conteneur existant. Le test de construction réelle couvre ce raccordement.

## Accueil des créations

`/patch-studio` ouvre un espace de travail avec modèles éditables, dernières créations, recherche et collections : mes créations, mes setups, organisation, partages et archives. Une création récente ouvre le projet existant ; « Reprendre l’édition » conserve le document en mémoire et ses modifications. Le bouton de gestion ouvre les paramètres du projet. Les droits et la formule Pro+ sont toujours vérifiés par les endpoints existants.

Les vignettes sont générées après sauvegarde, ou à l’ouverture d’un ancien projet modifiable qui n’en possède pas, depuis un instantané du document, avec les substitutions enregistrées, sans incorporer automatiquement les données RH de prévisualisation. Elles sont stockées dans le même magasin privé de blobs. Leur génération peut échouer sans invalider la sauvegarde du projet. Une vignette ne crée pas de nouvelle révision et ne modifie pas la date de dernière édition. La génération d’image utilise le même compositeur et les mêmes ressources que l’éditeur.

Nouvelles routes authentifiées :

| Méthode | Route | Contrat |
|---|---|---|
| GET | `/api/patch-studio/projects/{id}/preview` | PNG après contrôle des droits du projet et de la formule |
| POST | `/api/patch-studio/projects/{id}/preview` | `{revision,png}` ; éditeur autorisé, CSRF, révision actuelle, PNG ≤192 Kio, côtés 32–320 px, réencodage GD |

Les réponses restent privées. Le chemin du blob est calculé côté serveur par SHA256. Aucun fichier privé n’est publié sous `public/`. Les anciennes vignettes ne sont pas supprimées à l’aveugle : le nettoyage physique demeure une opération de maintenance, comme les autres blobs conservés par le Studio.

## Setups enregistrables

Un setup est un projet existant de `kind=setup`, avec ses calques, typographies, matières, textures, bordures, dimensions, variantes et substitutions. Pas de second système de stockage. Il bénéficie des mêmes révisions, partages, archives, ressources et permissions. Le type normal reste `kind=patch` par défaut ; le type est validé lors de la création. Le document graphique JSON v1 n’est pas modifié.

Depuis l’éditeur : **Enregistrer un setup**, nom et visibilité privée/organisation. Le patch ouvert reste le patch courant ; enregistrer un setup ne le remplace pas. Depuis **Mes setups**, « Utiliser le setup » crée un nouveau document non enregistré, sans modifier l’original. « Gérer » permet d’ouvrir/modifier l’original ou ses paramètres, dans les limites de ses droits. Les variables restent dynamiques. Un setup d’organisation ne rend pas ses ressources publiques.

Les sauvegardes manuelles annulent le timer autosave devenu inutile ; Ctrl+S sur un document inchangé ne crée pas une révision artificielle.

## Liste dynamique des membres

`GET /api/patch-studio/variables` retourne un catalogue borné de membres actifs de la communauté ; `?q=nom-ou-indicatif` effectue une recherche via `UserRepository::listPersonnelDirectoryRich`. Chaque résultat passe ensuite par le même filtre RH/ORBAT que les variables existantes. Sans droit de pilotage RH (`EffectifsLmsAccess`), seul le compte courant est résolu. Les personnes masquées/anonymisées ne sont pas retournées. Aucun email ou nouveau champ RH n’est ajouté.

La fenêtre « Membres ATHENA » charge la liste depuis l’API, permet la recherche, le filtre par unité visible, la sélection multiple et la prévisualisation. Cent résultats maximum par recherche et cent membres maximum par série. Les noms/unités affichés proviennent uniquement du résultat autorisé. La génération en série et l’application de la sélection interrogent de nouveau `?users=1,2` pour revérifier les droits ; aucun tableau RH n’est enregistré dans le projet ou le setup.

Depuis l’export, revenir du choix des membres conserve format, résolution et fond choisis. Les champs non disponibles dans le contrat RH utilisent toujours les substitutions explicites.

## Export

La fenêtre d’export présente un aperçu, le nom du projet, le format, la résolution, le fond, le périmètre de sélection et les variables ATHENA. Les descriptions de PNG/PAA/ZIP/PBO/publication et leurs contraintes sont contextuelles. L’aperçu reflète le membre choisi et le fond. Le bouton affiche l’avancement, une erreur apparaît dans la fenêtre et une réussite télécharge le fichier réel. Les encodeurs, contrôles de révision et limites existants sont réutilisés.

## Validation et retour arrière

- `php tests/patch-studio/workspace.php` : **18 contrôles** : absence de schéma, installation/réinstallation/installation partielle, tables/colonnes, conservation des données/historiques, stockage et droits des setups, présence de l’étape active, construction du service RH depuis le conteneur réel.
- `node tests/patch-studio/workspace-browser.cjs` : accueil, vignette privée réelle, sauvegarde/réutilisation de setup, recherche et sélection dynamique, maintien des options d’export, téléchargement PNG, erreurs de migration, formule Pro+ et CSRF.
- Les suites navigateur éditeur/textures et les contrôles PHP existants sont exécutés avec la nouvelle navigation ; la suite fabrication vérifie encore le Laser Cut et les PNG 4K. Captures inspectées pour l’accueil et la fenêtre d’export.

La migration a été exécutée sur SQLite isolée dans les tests, pas sur la base du site. La recette de déploiement comprend l’étape active sur MySQL/MariaDB puis les GET projets/assets et un export avec une session Pro+ réelle. La CI globale avait déjà onze échecs PHPUnit sur la base de la PR précédente, dont le service RH non déclaré ; la CI Studio est indépendante. Ne pas confondre un résultat Studio vert avec une suite globale entièrement verte ou une validation Arma.

Retour arrière : rétablir le code précédent en conservant les six tables, leurs données et ces colonnes additives. Les setups restent conservés comme projets ; l’ancien code sans cette classification peut les afficher comme projets normaux. Ne pas exécuter le rollback destructif initial pour revenir uniquement sur l’accueil. Conserver les blobs privés et les sauvegardes.
