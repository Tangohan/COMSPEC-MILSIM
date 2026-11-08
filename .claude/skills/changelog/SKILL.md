---
name: changelog
description: "Écrit l'entrée de CHANGELOG-ATAK.md d'une version avec le Résumé joueur FR et EN lu par l'annonce Discord. Utiliser avec /livrer-version."
argument-hint: "[version, ex. ATAK natif 2.0.2]"
---
La règle fait foi dans l'en-tête de `CHANGELOG-ATAK.md` : relis-le. Un dossier `changelog.d/` n'existe pas encore : s'il est créé, suis son README. Sinon, l'entrée s'écrit dans `CHANGELOG-ATAK.md`.

1. **Titre** : `(ATAK natif X.Y.Z)`, `(ATAK natif X.Y.Z, COMSPEC Link 2.0.N)` ou `site`. La version est celle de `/livrer-version` et du titre de la PR.
2. **Juste sous le titre**, dans cet ordre :
   ```
   ### Résumé joueur
   Type : nouveauté | correctif | performance | patch
   FR : titre court en clair
   - 2 à 4 puces
   EN : same title in English
   - same points
   ### Détail technique
   - ...
   ```
3. **Écrire pour le joueur** : phrases complètes, noms d'apps et de menus tels qu'il les voit. Jamais de nom de fonction, de variable, de fichier, de clé d'API ni de secret ; pas de numéro de PR, pas de lien. Mêmes informations en FR et en EN.
4. **Aperçu** : `python3 .github/scripts/discord_changelog.py --preview X.Y.Z` (corrige les termes techniques signalés).
5. **Test** : `vendor/bin/phpunit --filter AtakReleaseInfoTest`.
6. **Pas d'annonce** : label `discord:skip` ou `[skip discord]` dans le titre de la PR.

Les liens Steam et SPOTREP/TECHREP de l'en-tête sont à part : ne les modifie pas sans demande.
