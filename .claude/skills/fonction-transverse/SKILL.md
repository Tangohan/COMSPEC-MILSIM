---
name: fonction-transverse
description: "Organise une fonction qui touche à la fois le site et un mod : le contrat d'abord, puis une session par côté."
argument-hint: "[fonction à livrer]"
---
Fonction : $ARGUMENTS

1. **Contrat** : décris les routes, champs, types et erreurs échangés dans `docs/technical/ICD-ATHENA-ATAK.md`. Ajoute ou adapte les tests de `tests/Contract/`.
2. **Compatibilité** : le site part en production à la fusion, le mod seulement à sa publication. Le site doit donc accepter l'ancienne version du mod.
3. **Site** : une session ou un agent, une PR. Le site passe d'abord, sauf si le mod reste compatible sans lui.
4. **Mod** : une autre session ou `dev-atak-native`, une PR. Liste ce qu'il faut tester en jeu.
5. **Fin** : /fin-de-session à chaque côté. L'issue garde l'état des deux PR.

Ne mélange jamais le site et le mod dans la même session. Si le contrat doit changer en cours de route, reviens à l'étape 1 avant de continuer.
