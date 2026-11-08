# BFT : menu ORBAT, traits de groupe, photos multiples sur Discord

- **Contexte** : retours du propriétaire sur l'app BFT et la carte.
- **Menu ORBAT** : la vue arbre existait déjà, cachée derrière un petit bouton « VUE : LISTE » à bascule. Remplacé par un menu à deux boutons, LISTE et ORBAT, la vue choisie en couleur.
- **Traits de groupe, non fait** : les seuls traits entre joueurs trouvés sur la carte sont ceux de `fn_meshDraw` (bascule « Traits entre alliés », un trait par paire de téléphones à portée). Ce fichier est le calque Wave Relay, périmètre de l'agent réseau : correctif retiré de la branche. Il reste disponible dans le commit `e39120bc9` : arbre couvrant par groupe puis entre groupes, ossature doublée, traits internes pour mon groupe ou en zoom rapproché. **Supposé** : ce sont bien les traits décrits par le propriétaire.
- **Photos multiples, non fait, demande le site** : le mod envoie chaque cliché séparément (`fn_dronePhoto` « send », une requête par photo) et `DiscordEventRelayService` publie un message par requête. Besoin côté site : regrouper les clichés d'un même joueur et d'une même mission sur une courte fenêtre (20 à 30 s, ou par `batch_id` envoyé par le mod), puis publier un seul message. Discord accepte 10 fichiers et 10 embeds par message : au-delà, une suite de messages. À traiter avec une file et une tâche planifiée : pas un petit changement.
- **Pointeur de carte** : déjà livré (`docs/bugs/2026-11-08-atak-carte-pointage-appui-long.md`), à vérifier en jeu.
- **Fichiers touchés** : `functions/pages/fn_pageBft.sqf`.
- **Vérification** : en jeu : BFT, boutons LISTE et ORBAT, choix gardé à la réouverture .
- **Statut** : corrigé à vérifier en jeu (menu ORBAT) ; identifié, non fait (traits de groupe : périmètre réseau ; photos multiples : site).
