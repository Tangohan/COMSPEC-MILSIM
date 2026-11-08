---
name: hotfix-prod
description: "Correctif S1 en production : branche depuis main, test rouge, PR minimale, retour arrière prêt. Déclenché par un humain uniquement."
disable-model-invocation: true
argument-hint: "[issue ou description du S1]"
---
Entrée : $ARGUMENTS. S1 : portail hors service, perte de données, ou connexion du mod impossible pour tous. Une fusion sur main déploie en production : ne fusionne jamais, TTRD fusionne.

1. **Confirme le S1** : symptôme, produit, depuis quand, version. Sans ces éléments, demande-les.
2. **Branche** : `hotfix/<slug-court>` depuis `origin/main`, dans un worktree. Ne touche pas au checkout principal.
3. **Cause** : méthode `enquete-bug`. Si la cause est un commit récent, `/recherche-regression`.
4. **Test rouge**, puis **plus petit correctif** : pas de refonte, pas de nettoyage voisin, pas de migration SQL sauf si elle est la cause.
5. **Vérifier** : phpunit sur le test ciblé, puis `vendor/bin/phpstan analyse --memory-limit=1G`. Le job CI `php` peut déjà être rouge sur main : signale seulement les nouveaux échecs.
6. **Retour arrière** : écris dans la PR la commande exacte (`git revert <commit>`) et dis si une migration ou un main.pbo est à défaire. Hotfix de mod : numéro de version selon `/livrer-version`.
7. **Note** : `docs/bugs/AAAA-MM-JJ-slug.md` (format dans `docs/bugs/README.md`).
8. **PR** : base `main`, titre « hotfix : ... », corps court avec cause, correctif, risque, retour arrière. Alerte TTRD et arrête-toi.

Dépôt public : aucun secret, IP, e-mail ni identifiant Steam dans la PR.
