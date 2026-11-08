# Carte : moins de traits entre alliés ; SMS sans redessin complet

## Contexte

Retours du propriétaire sur la carte, et conversion des pages en mises à jour sur place.

## Symptôme

Calque « Traits entre alliés » : un trait par paire à portée, illisible. SMS reçu ou accusé : la page Messages entière était redessinée.

## Cause

`fn_meshDraw` tirait tous les liens du maillage. `fn_p2pReceive` et `fn_p2pAck` appelaient `fn_pageRender` pour un seul message.

## Correctif

`fn_meshDraw` (repris de `e39120bc9`) : le meilleur arbre dans chaque groupe, puis entre groupes ; chacun est relié au moins une fois. Traits internes seulement pour mon groupe, ou en zoom rapproché. `fn_p2pReceive`, `fn_p2pAck` : `fn_pageChat [true]`, déjà existant, redessine le seul fil ouvert. La page Messages n'est pas écrite avec `fn_formRender` : `fn_formSet` ne s'applique pas à ses bulles. La liste des conversations reste redessinée en entier à un SMS reçu.

## Fichiers touchés

`fn_meshDraw`, `fn_p2pReceive`, `fn_p2pAck`

## Vérification

`hemtt check`. En jeu : liste des tests de la PR #1395.

## Statut

corrigé à vérifier en jeu
