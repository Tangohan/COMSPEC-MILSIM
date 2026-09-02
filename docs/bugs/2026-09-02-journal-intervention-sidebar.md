# Journal d’intervention — page cassée (barre latérale)

## Contexte

Pendant une intervention, le bandeau ambre propose Journal / Changements. Ces pages utilisent l’habillage d’administration du site, qui charge la barre latérale plateforme.

## Symptôme

Ouverture de `/admin/system/tenants/{id}/intervention/journal` : erreur « syntax error, unexpected end of file, expecting elseif or else or endif » (production, fichier `platform_admin_sidebar.php`).

## Cause

La barre latérale mélangeait une condition `if (...):` / `endif` avec une fonction qui fermait le PHP pour écrire du HTML. Un fichier incomplet ou ce mélange laissait une condition ouverte jusqu’à la fin du fichier. PHP s’arrêtait alors au chargement de n’importe quelle page d’administration du site, y compris le journal d’intervention.

## Correctif

La barre latérale n’utilise plus cette syntaxe : les conditions sont entre accolades, les liens sont écrits en PHP sans sortie HTML au milieu d’une fonction. Contrôle automatique de syntaxe ajouté.

## Fichiers touchés

- `views/partials/platform_admin_sidebar.php`
- `app/Controllers/Admin/System/SystemTenantInterventionController.php`
- `tests/Unit/PlatformSiteAdminAssetTest.php`

## Vérification

`php -l` sur la barre latérale. Depuis une intervention, ouvrir Journal : la page s’affiche, la barre latérale reste à droite.

## Statut

Corrigé.
