---
name: fin-de-session
description: "Termine une session en écrivant l'état restant dans l'issue ou la PR, puis invite à /clear. Utiliser à une PR livrée, à 15 commits, ou au changement de sujet."
---
1. Lance `git status` et `git log --oneline -15` sur la branche.
2. Écris dans l'issue ou la PR liée, sans secret ni donnée personnelle :
   - ce qui est fait, avec les commits ou la PR ;
   - ce qui reste, dans l'ordre ;
   - les décisions prises et les pistes écartées ;
   - ce qui n'a pas été vérifié, dont les tests en jeu.
3. Sans issue ni PR, propose d'en créer une. Ne crée pas de fichier de notes dans le dépôt.
4. Signale le travail non commité et ne le commite pas sans accord.
5. Termine par une ligne : « Session close. Lance /clear, puis ouvre le prochain sujet dans une nouvelle session. »
