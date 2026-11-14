# Module officiel — seul client accepté

La clé d’accès d’une communauté ne distingue pas le pack officiel d’une copie. Le poste exige en plus une preuve sur les requêtes du jeu.

## Secrets

Deux lignes dans le `.env`, 64 caractères hexadécimaux chacune :

- `COMSPEC_CLIENT_ATTEST_MASTER` : maître. Il ne quitte jamais le serveur ni la machine de build. Chaque version de COMSPEC Link (2.0.125 et plus) porte un secret calculé depuis lui : `HMAC-SHA256(maître, "COMSPEC-CLIENT-KEY-V2\n" + version)`. Le module annonce sa version (`v2:2.0.125`) et le serveur recalcule le secret.
- `COMSPEC_CLIENT_ATTEST_SECRET` : ancien secret unique, celui de COMSPEC Link 2.0.124 et avant.

Aucune des deux : aucun contrôle. Une valeur invalide : refus de toutes les requêtes du jeu (mieux qu’une porte ouverte par une faute de frappe). La carte et le bureau, ouverts dans le navigateur avec une session, ne sont pas concernés.

Pages qui restent ouvertes sans preuve : version publiée, ping, tuiles, liste et envoi des fonds photo, image de téléphone.

## Produire le pack

```text
php tools/embed-client-attest.php [--env=CHEMIN_DU_.env]
```

Le script lit la version de COMSPEC Link dans `Extension.cs`, crée le maître dans le `.env` s’il n’existe pas, et écrit `mod/UptoDate/COMSPECExtension/ClientAttestMaterial.Local.cs` (ignoré par Git) : le secret de cette version, plus l’ancien secret s’il est encore dans le `.env`. Depuis un worktree, `--env` désigne le `.env` du dépôt principal.

À relancer à chaque changement de version de la DLL. Un matériel d’une autre version fait échouer la compilation (erreur dans `OfficialClientProof.cs`) : une DLL que le serveur refuserait ne peut pas être publiée par oubli.

## Ordre de mise en place

L’ordre n’a pas d’importance, personne n’est coupé :

- Le pack envoie les deux preuves. Un serveur sans maître ignore la nouvelle et accepte l’ancienne.
- Un serveur avec maître accepte la nouvelle preuve, et encore l’ancienne des packs 2.0.124 et avant.

## Retirer une version

Relever la « version minimale des DLL du jeu » dans l’administration du site (ou `tools/game-client-gate.php`). Ce plancher s’applique à la version prouvée par la preuve, pas seulement à celle que le module annonce :

- plancher à 2.0.125 ou plus : l’ancien secret unique n’est plus accepté ;
- plancher à N : les secrets de toutes les versions avant N ne servent plus.

Rien à changer dans le `.env`, rien à republier. À faire quand les joueurs ont eu le temps de se mettre à jour : ceux qui ne le sont pas reçoivent le message « mettez à jour depuis le Workshop ».

Si le maître fuit (serveur ou machine de build compromis) : le remplacer dans les deux `.env`, relancer le script, republier le pack.

## Limite

Le secret d’une version est dans le binaire publié, masqué, pas en clair. Quelqu’un qui le ressort du binaire peut signer à nouveau, jusqu’à ce que le plancher dépasse cette version, et doit recommencer à chaque version. Ce dispositif arrête la copie du code source et tout autre programme. Il ralentit une extraction du binaire officiel, il ne l’empêche pas : aucun secret livré dans un programme ne résiste à quelqu’un qui le décompile. Ce qui reste vrai dans ce cas : chaque requête porte le jeton du compte du joueur, donc un abus se rattache à un compte et se coupe par ce compte.

Un script modifié qui appelle encore le binaire officiel est accepté : c’est le binaire qui signe, pas les scripts.
