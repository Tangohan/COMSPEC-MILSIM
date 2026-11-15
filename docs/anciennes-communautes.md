# Très anciennes communautés : ne rien perdre, tout signaler

Les grosses vagues de changements poussées en production (nom affiché, JNET, bilan 30 jours, tâches d'entretien,
migrations, rôles, formules) ont été conçues sur des données récentes. Une communauté créée avant les migrations de base
d'octobre 2026 a des données plus pauvres : colonnes vides, anciens rôles, pas de prénom ni de nom, dossiers acceptés
il y a des années. Ce document dit ce qui est protégé, ce qui est signalé aux responsables, et comment contrôler.

Règle : aucune donnée ancienne n'est supprimée, écrasée ni cachée sans avertissement.

## Ce qui est protégé

- **Bilans de recrutement (clôture automatique « néant »)** : la tâche horaire ne clôt que les dossiers dont le bilan est
  attendu depuis 45 à 180 jours. Au-delà (`EnlistmentRetroAutoClose::AUTO_CLOSE_MAX_LATE_DAYS`), le dossier n'est ni clos ni
  notifié : il est seulement compté dans l'avis du tableau de bord. Un instructeur reçoit une seule information par
  passage (50 au plus par exécution). Chaque communauté peut désactiver la clôture (Qualité des données, section
  « Données anciennes à vérifier »). Simulation : `php recruitment-retro-auto-close.php --dry-run [--tenant=ID]`.
- **Annuaire JNET** : lu page par page (jusqu'à 3 000 membres) ; il était plafonné aux 120 premiers membres actifs, les
  suivants disparaissaient de /jnet/personnel et de leur fiche.
- **Matricules** : l'entretien automatique n'attribue un numéro qu'aux membres sans aucun matricule et saute un numéro déjà
  porté par un autre membre de la communauté (anciens matricules saisis à la main).
- **Anciens noms de personnage** : jamais copiés automatiquement. Le responsable lance la reprise depuis Qualité des
  données, après une simulation ; seuls les membres sans prénom ni nom sont repris, rien n'est remplacé, chaque reprise est
  journalisée (table `legacy_identity_import_log`) et annulable.

## L'avis aux responsables

Dans « Tâches en attente » du tableau de bord (droits `admin.organization` ou `admin.access`), la tâche « Données anciennes
à vérifier » dit, avec les nombres : membres sans prénom ni nom (leur nom s'affiche sous l'indicatif), dossiers de
recrutement anciens sans bilan, membres sans unité, grade ou portrait (à partir de 3). Le bouton mène à Qualité des données.
Calcul léger, mis en cache 10 minutes par communauté, jamais bloquant ; textes en français et en anglais.

## Contrôler avant et après une mise en production

Rapport en **lecture seule** (aucune option d'écriture, session MySQL en lecture seule, uniquement des `SELECT`) :

```
php tools/audit-report-anciennes-communautes.php                 toutes les communautés
php tools/audit-report-anciennes-communautes.php --tenant=ID     une communauté
php tools/audit-report-anciennes-communautes.php --format=csv    ou json ; --limit=N lignes par section
php tools/audit-report-anciennes-communautes.php --selftest      vérifie le garde-fou, sans base
```

Il utilise la connexion de l'application (le serveur sur lequel il tourne). Sections :

1. **Étapes de migration non passées** ou dont le dernier passage a échoué (une étape manquante limite l'écran qui en dépend).
2. **Tables et colonnes absentes** que les écrans récents attendent.
3. **Membres, par communauté** : date de création, membres actifs, part sans prénom ni nom (et combien sont reprenables
   depuis le nom de personnage), sans profil de personnel, sans unité, sans grade, sans portrait, niveaux d'accès encore
   nommés « Opérateur ».
4. **Candidatures acceptées sans bilan 30 jours** : total, combien seront closes (dans la fenêtre), combien dépassent la
   fenêtre de sécurité (jamais touchées), ancienneté de la plus vieille.

Code de sortie : 0 rien à signaler, 3 au moins un point à vérifier, 1 ou 2 erreur. Un « point à vérifier » n'est pas une
panne : les données sont intactes ; c'est ce que les responsables doivent compléter.

## Retour arrière

- Journal des reprises : `migrations/rollback/legacy_identity_import_log_down.sql` (efface seulement le journal). Pour retirer
  les noms repris, utiliser d'abord « Annuler cette reprise » (ne retire que ce que le lot a copié et que le membre n'a pas modifié).
- Clôture automatique : réglage par communauté, ou désactivation de la tâche `recruitment_retro_auto_close` dans les tâches planifiées.

## Jeu d'essai

`tests/Support/LegacyTenantFixture.php` : une base en mémoire « très vieille communauté » (membre sans profil, sans unité,
sans grade, sans prénom ni nom, rôle « Opérateur », dossier accepté il y a deux ans sans bilan, ancien matricule). Les
tests `tests/Unit/LegacyCommunityDataTest.php` y font passer les services récents : aucune exception, aucune suppression,
aucun écrasement, chaque membre reste listé, l'avis apparaît.
