# Quick Picture — la photo suivante ne partait pas au poste

**Date :** 2026-09-13  
**Statut :** corrigé

## Contexte

Ouverture du téléphone : une première vue part au poste (acceptable). Ensuite, une photo prise via Quick Picture ne remonte pas.

## Symptôme

Journal :

```
NotifyNewPhoto — COMSPEC_295_….png  (OK, mis en file)
PhotoUpload — duplicate · COMSPEC_235_….png
```

Le poste garde l’ancienne vue. La nouvelle n’arrive pas. Une première capture peut aussi échouer en fichier introuvable (`name_only`).

## Cause

Chaque cliché annonce un fichier précis. Si ce fichier n’est pas encore écrit sur le disque, la recherche prenait **la photo la plus récente déjà présente** — donc le cliché précédent — puis le considérait comme un doublon.

## Correctif

- Un nom de fichier annoncé n’est plus remplacé par une autre photo.
- Attente plus longue du vrai fichier (y compris encore vide le temps qu’il s’écrive).
- Si le fichier n’existe vraiment pas, nouvel essai possible au lieu d’un faux doublon.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

1. Pack Overwatch 1.5.71, liaison 2.0.35. Quitter Arma complètement.
2. Ouvrir le téléphone, prendre une photo Quick Picture : elle arrive au poste.
3. En prendre une deuxième, différente : le poste montre les deux vues, pas un doublon de la première.

## Statut

corrigé — pack **1.5.71** / liaison **2.0.35**
