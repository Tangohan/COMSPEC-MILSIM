# BO ATAK — entrée « Réseau de relais » absente du menu

**Statut :** corrigé (sources)

## Contexte

Back-office, menu ATAK. La page de supervision des mâts Relais existait déjà (`/back-office/atak/relays-network`) mais n’apparaissait nulle part dans la navigation.

## Symptôme

Aucun libellé « Relais » / « Réseau de relais » dans la barre latérale ATAK. Seule une section partielle restait dans Contrôle de mission.

## Cause

La page et le contrôleur avaient été livrés sans entrée dans `ath_sidebar_nav.php`, `config/navigation.php` ni `config/back_office_pages.php`. Sur certaines branches locales, la route manquait aussi.

## Correctif

- Route `GET /back-office/atak/relays-network` (+ détail / suppression admin)
- Entrée **Réseau de relais** dans le menu ATAK (niveau principal + sous Terminaux)
- Fiche BO + lien depuis Contrôle de mission
- Page alignée sur le back-office clair (plus de thème sombre « terminal »)
- Tutoriel intégré en 6 étapes (plus de lien vers un fichier)

## Fichiers touchés

- `views/partials/ath_sidebar_nav.php`
- `config/navigation.php`
- `config/back_office_pages.php`
- `routes/web.php`
- `app/Controllers/Admin/AdminAtakRelaysController.php`
- `views/admin/atak/relays_network.php`
- `views/admin/atak/server_control.php`

## Vérification

1. Recharger le BO.
2. Menu ATAK : « Réseau de relais » visible.
3. Ouverture de `/back-office/atak/relays-network` : page claire, stats, tutoriel en étapes.
4. Bouton « Tutoriel Relais » : navigation entre les 6 étapes, sans ouvrir de fichier externe.

## Statut

corrigé (sources) — déploiement portail requis
