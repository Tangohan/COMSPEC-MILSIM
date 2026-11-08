# Erreur 500 sur /admin/users

- **Contexte** : annuaire des comptes de l'administration du site, filtre « Sans connexion depuis » ajouté le 01/11.
- **Symptôme** : la page répond 500.
- **Cause** : les clés `'30'`, `'90'`, `'180'` du tableau des options deviennent des entiers en PHP ; `$h(string $v)` les refuse avec `strict_types=1` (TypeError).
- **Correctif** : conversion en chaîne dans les deux boucles d'options. Le filtre sélectionné s'affiche de nouveau, la comparaison stricte étant faite sur des chaînes.
- **Fichiers touchés** : `views/admin/system/users.php`, `tests/Contract/PlatformUsersViewTest.php`.
- **Vérification** : rendu de la vue avant et après correctif hors PHPUnit (non installé sur ce poste). Le test ajouté reste à passer en CI.
- **Statut** : corrigé, à vérifier en production.
