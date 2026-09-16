# Téléphone ATAK — surcharge mémoire à l’envoi d’une photographie

## Contexte

Revue mémoire / crash (Athena 1.0.124 et suivantes, liaison 2.0.41).
Une capture volumineuse (environ 70 Mo) pouvait provoquer un pic de
plusieurs centaines de Mo dans le processus Arma au moment de l’envoi.

## Symptôme

Après une photographie haute définition depuis le téléphone, le jeu
peut se figuer, afficher une mémoire insuffisante, ou se fermer.
Ce n’est pas une fuite permanente : le pic arrive pendant l’envoi.

## Cause

La capture était déjà chargée en mémoire, puis le corps de l’envoi
était recopié dans un tampon extensible, puis recopié une troisième
fois avant le départ. Le contrat d’envoi vers le poste exige une
taille connue : l’ancienne copie en mémoire servait à ça.

## Correctif

Liaison 2.0.42 :

- Le corps d’envoi est écrit dans un fichier temporaire, puis lu en
  flux, avec la taille indiquée. Le fichier est effacé à la fermeture.
- La photographie n’est plus recopiée en entier dans la mémoire gérée
  pour construire l’envoi : lecture directe du fichier une fois
  l’écriture terminée.
- Le poste reçoit le même format qu’avant.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj`

## Vérification

1. Quitter Arma complètement, recharger le pack (journal : Extension 2.0.42).
2. Envoyer une photographie 1080p puis une photographie 4K.
3. Le jeu reste ouvert. Après deux minutes, la mémoire se stabilise.
4. En cas de fermeture : conserver le journal Arma, le minidump et l’heure.

## Statut

Corrigé en source (à valider in-game après relance Arma, DLL reconstruite).
