# Mettre COMSPEC SSE en ligne (Workshop)

Ce guide s’adresse au **chef de mission** ou à la personne qui publie le pack jeu. Pas besoin d’ouvrir les fichiers d’atelier.

Le pack **COMSPEC SSE** (Sensitive Site Exploitation) se charge **à côté** de CBA et ACE3. Overwatch est **optionnel** : il sert à remonter le renseignement vers Athena.

## Ce que vous publiez

Un dossier **propre**, déjà assemblé :

`mod/publisher/@COMSPEC_SSE`

Il contient uniquement ce que les joueurs doivent télécharger : la fiche du mod, les logos, et les modules compilés. **Pas** les fichiers de travail, **pas** les guides d’atelier.

Si ce dossier n’existe pas encore, la personne qui compile lance d’abord le build puis l’assemblage (voir la note d’atelier à côté des scripts). Ensuite seulement, on ouvre le Publisher.

## Dans Arma 3 Tools (Publisher)

1. Ouvrir Steam → bibliothèque → **Arma 3 Tools**.
2. Lancer **Publisher**.
3. **Nouveau** (première mise en ligne) ou ouvrir l’entrée **COMSPEC SSE** déjà existante.
4. Dossier du mod : pointer **uniquement** vers `publisher/@COMSPEC_SSE` — jamais vers le dossier de travail `@COMSPEC_SSE` (il mélange sources et modules).
5. Titre : **COMSPEC SSE**.
6. Image : le logo déjà dans le dossier, ou une capture 16:9 de l’écran Zeus / terminal.
7. Dépendances Workshop :
   - **CBA_A3** (obligatoire)
   - **ACE3** (obligatoire)
   - **COMSPEC Overwatch** (optionnel — liaison Athena)
8. Coller le texte de fiche depuis `STEAM_DESCRIPTION.md` (fichier d’atelier, à coller, pas à joindre au pack).
9. **Publier**. À la première mise en ligne, Steam attribue un identifiant : le Publisher écrit alors `meta.cpp` dans le dossier. Conservez ce fichier pour les mises à jour suivantes.

## Après publication

- Dans le lanceur Arma 3 : activer **CBA**, **ACE3**, puis **COMSPEC SSE** (Overwatch ensuite si vous l’utilisez).
- Relancer **Arma complètement** (pas seulement la mission) après un abonnement ou une mise à jour.
- Les joueurs s’abonnent sur le Workshop ; le launcher télécharge le pack.

## Ce qu’il ne faut pas faire

- Envoyer le dossier de développement (fichiers de scripts à nu à côté des modules).
- Joindre les guides internes (`docs/`) au téléchargement Steam.
- Recréer une nouvelle fiche Workshop à chaque version : on **met à jour** la même entrée.

## Test rapide en session

1. Mission avec CBA + ACE3 + COMSPEC SSE.
2. Sur un personnage : menu ACE → actions SSE (inspecter, photographier, terminal).
3. Zeus : catégorie **COMSPEC — SSE** (génération, site, contrôle).
