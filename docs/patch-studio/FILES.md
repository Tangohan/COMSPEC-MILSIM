# Fichiers de la livraison

- `.htaccess`
- `app/Controllers/Api/AtakPatchesApiController.php`
- `app/Controllers/Api/PatchStudioApiController.php`
- `app/Controllers/Web/PatchStudioController.php`
- `app/Services/PatchStudio/Access.php`
- `app/Services/PatchStudio/Assets.php`
- `app/Services/PatchStudio/Document.php`
- `app/Services/PatchStudio/Export.php`
- `app/Services/PatchStudio/Projects.php`
- `app/Services/PatchStudio/Variables.php`
- `config/navigation.php`
- `docs/patch-studio/ARCHITECTURE.md`
- `docs/patch-studio/README.md`
- `docs/patch-studio/VALIDATION.md`
- `migrations/2026_11_06_patch_studio.sql`
- `migrations/rollback/2026_11_06_patch_studio.sql`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/config.cpp`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/shell/fn_pagePatches.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/shell/fn_patchAthena.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/shell/fn_patchCatalog.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/shell/fn_patchTexture.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/shell/fn_vestMount.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/COMSPECExtension/Extension_Patches.cs`
- `public/assets/css/patch-studio.css`
- `public/assets/fonts/patch-studio/BarlowCondensed-Bold.ttf`
- `public/assets/fonts/patch-studio/BarlowCondensed-Regular.ttf`
- `public/assets/fonts/patch-studio/Oswald.ttf`
- `public/assets/fonts/patch-studio/RobotoCondensed.ttf`
- `public/assets/fonts/patch-studio/SairaStencilOne-Regular.ttf`
- `public/assets/fonts/patch-studio/barlowcondensed-OFL.txt`
- `public/assets/fonts/patch-studio/oswald-OFL.txt`
- `public/assets/fonts/patch-studio/robotocondensed-OFL.txt`
- `public/assets/fonts/patch-studio/sairastencilone-OFL.txt`
- `public/assets/js/patch-studio-core.js`
- `public/assets/js/patch-studio.js`
- `routes/web.php`
- `tests/patch-studio/backend.php`
- `tests/patch-studio/bootstrap.php`
- `tests/patch-studio/browser.cjs`
- `tests/patch-studio/core.test.mjs`
- `tests/patch-studio/project.json`
- `tests/patch-studio/router.php`
- `views/patch_studio/index.php`

Ajouts de la correction MIME/UI/Pro+ :

- `app/Services/PatchStudio/PlanAccess.php`
- `app/Services/Billing/SubscriptionPlanFeaturesCatalog.php`
- `tests/patch-studio/access-ui.php`
- `tests/patch-studio/plan-fixtures.php`
- `views/patch_studio/_head.php`
- `views/patch_studio/_header.php`
- `views/patch_studio/_disclaimer.php`
- `views/patch_studio/upgrade.php`

Ajouts des textures image :

- `app/Services/PatchStudio/TextureCatalog.php`
- `public/assets/textures/patch-studio/catalog.json`
- `public/assets/textures/patch-studio/SOURCES.md`
- Les cinq JPEG immuables référencés dans ce catalogue.
- `tests/patch-studio/textures.php`
- `tests/patch-studio/textures-browser.cjs`

Aucun fichier de configuration secret ni artefact du stockage privé n’est inclus.

Évolution fabrication textile :

- `public/assets/js/patch-studio-surface.js` : matières, contours, fils, ombres et tranches laser.
- `public/assets/js/patch-studio-core.js`, `patch-studio.js` et CSS : composition, références de supports, cinq sections, saisie immédiate et export haute résolution.
- `app/Services/PatchStudio/{Document,Assets,Export}.php`, contrôleur API : schéma, ressources imbriquées, limites PNG et garde mémoire.
- `tests/patch-studio/{fabrication.test.mjs,fabrication.php,fabrication-browser.cjs,load-core.mjs}` et adaptation des suites existantes.
- `docs/patch-studio/FABRICATION.md` et mise à jour des documents existants.
- `.github/workflows/patch-studio.yml` : validation PHP/Node/Chromium isolée.

Aucune migration supplémentaire et aucun changement DLL/SQF pour cette évolution.

Accueil et correction du stockage :

- `app/Services/PatchStudio/Schema.php`, `database/steps/3950_patch_studio_workspace.php` et inscription dans `database/steps/registry.php`.
- `app/Core/Container.php` : déclaration du service RH existant.
- `app/Services/PatchStudio/{Projects,Variables}.php`, contrôleur API et `routes/web.php` : setups, vignettes, catalogue RH filtré et diagnostic de schéma.
- `public/assets/js/patch-studio-workspace.js`, module principal, CSS et `views/patch_studio/index.php` : accueil et dialogues raccordés.
- `public/assets/js/patch-studio-night.js` : calculs de vision nocturne (réglages exportés et aperçu), sans DOM, testés par `tests/patch-studio/night.test.mjs`.
- `tests/patch-studio/{workspace.php,workspace-browser.cjs}` et adaptation des suites au nouvel accueil.
- `docs/patch-studio/WORKSPACE.md`, README et validation ; workflow Studio complété.
