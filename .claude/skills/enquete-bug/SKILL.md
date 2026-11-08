---
name: enquete-bug
description: "Méthode d'enquête sur un bug : reproduire, trouver la cause racine, écrire un test rouge, puis le plus petit correctif."
user-invocable: false
---
1. **Reproduire** : écris le chemin exact qui mène au symptôme. Sans reproduction possible, dis ce qui manque.
2. **Cause racine** : remonte jusqu'à la ligne fautive et cite-la. Sépare ce qui est prouvé de ce qui est supposé.
3. **Test rouge** : écris un test qui échoue pour la bonne raison. En SQF sans test possible, écris les étapes de test en jeu.
4. **Correctif** : change le moins de lignes possible. Pas de refonte ni de nettoyage voisin.
5. **Vérifier** : test ciblé vert, puis cherche le même défaut ailleurs avec Grep.
6. **Note** : ajoute `docs/bugs/AAAA-MM-JJ-slug-court.md` avec Contexte, Symptôme, Cause, Correctif, Fichiers touchés, Vérification et Statut.
7. **PR** : référence l'issue. Mod : statut « corrigé à vérifier en jeu » et étapes à suivre.

Après deux hypothèses réfutées, arrête-toi et rends tes pistes.
Dépôt public : aucune IP, aucun e-mail, aucun identifiant Steam ni secret dans la note, le test ou la PR.
