---
name: recherche-regression
description: "Trouve le commit qui a introduit un bug avec git bisect et un test ciblé. Utiliser quand « ça marchait avant »."
argument-hint: "[symptôme, dernière version bonne]"
---
Entrée : $ARGUMENTS.

1. **Test ciblé** : écris la commande qui échoue sur le bug et réussit sur la version bonne (`vendor/bin/phpunit --filter ...`, ou un `grep` sur une valeur dans un fichier de mod). Sans test automatisable (SQF en jeu), n'utilise pas bisect : lis `git log -p` sur les fichiers suspects.
2. **Bornes** : mauvais = `HEAD`, bon = tag, commit ou version retrouvée dans `CHANGELOG-ATAK.md` ou par `git log --grep`.
3. **Worktree jetable** : `git worktree add ../wt-bisect <mauvais>`. Ne bisecte jamais dans le checkout principal.
4. **Bisect** : `git bisect start <mauvais> <bon>` puis `git bisect run <commande>`. Pour un commit non testable (build cassé), la commande rend `125`.
5. **Fin** : `git bisect reset`, puis supprime le worktree.
6. **Rapport** (10 lignes) : commit, PR, ligne fautive, preuve. Pas d'e-mail d'auteur. Ensuite `/bug` pour le correctif, ou `/postmortem`.

Ne corrige rien dans cette étape.
