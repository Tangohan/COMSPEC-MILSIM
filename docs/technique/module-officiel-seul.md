# Module officiel — seul client accepté

La clé d’accès d’une communauté ne distingue pas le pack officiel d’une copie. Le poste exige en plus une preuve sur les requêtes du jeu.

## Secret

`COMSPEC_CLIENT_ATTEST_SECRET` : 64 caractères hexadécimaux (32 octets).

- Vide : aucun changement. Les joueurs déjà en ligne continuent.
- Renseigné : toute requête du jeu sans preuve valable reçoit un refus. La carte et le bureau, ouverts dans le navigateur avec une session, ne sont pas concernés.
- Valeur invalide : refus de toutes les requêtes du jeu (mieux qu’une porte ouverte par une faute de frappe).

Pages qui restent ouvertes sans preuve : version publiée, ping, tuiles, liste et envoi des fonds photo, image de téléphone.

## Produire le pack

```text
php tools/embed-client-attest.php
```

Le script crée le secret dans `.env` s’il n’existe pas, et écrit `mod/UptoDate/COMSPECExtension/ClientAttestMaterial.Local.cs`. Ce fichier est ignoré par Git. Une compilation Release sans lui affiche un avertissement : le binaire ne pourra pas se faire reconnaître.

Publier ce binaire. Copier la même ligne `COMSPEC_CLIENT_ATTEST_SECRET` sur le serveur **en même temps** que le pack. Si le serveur l’a avant le pack, les joueurs sont refusés jusqu’à la mise à jour.

## Rotation

1. Générer un nouveau secret (retirer la ligne du `.env` local, relancer le script).
2. Recompiler et publier le pack.
3. Remplacer la valeur sur le serveur au moment de la publication.

L’ancien pack cesse d’être accepté dès que le serveur change.

## Limite

Le secret est dans le binaire publié, masqué, pas en clair. Quelqu’un qui le ressort du binaire peut signer à nouveau. Ce dispositif arrête la copie du code source et tout autre programme. Il n’arrête pas une extraction du binaire officiel. Dans ce cas, on tourne le secret.

Un script modifié qui appelle encore le binaire officiel est accepté : c’est le binaire qui signe, pas les scripts.
