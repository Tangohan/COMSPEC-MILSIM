# ATAK caméra — `RscTitles: Member already defined`

## Symptôme

Arma 3 interrompt le chargement de `comspec_atak_core` avec :

```text
File z\comspec_atak\addons\comspec_atak_core\ui\camera.hpp, line 92:
.RscTitles: Member already defined.
```

## Cause

`ui/runtime.hpp`, inclus en premier par `config.cpp`, déclare déjà le bloc
`RscTitles` du PBO. Le nouveau `ui/camera.hpp` tentait de rouvrir cette classe
pour ajouter le viseur. Le parseur de configuration Arma considère la seconde
déclaration dans le même addon comme un membre dupliqué et refuse le PBO.

Le défaut ne vient pas de la connexion : l'indication « mot de passe incorrect »
observée ensuite est une réponse distincte du backend.

## Correctif

- le viseur et le badge JPEG sont maintenant les `controlsBackground` du dialogue
  `COMSPEC_ATAK_CameraHud` ;
- la seconde déclaration `RscTitles` a été supprimée ;
- la prise de vue masque puis réaffiche les cinq contrôles du dialogue afin que
  la capture reste dépourvue d'interface ;
- les appels `cutRsc`/`cutText` devenus inutiles ont été retirés.

## Vérification

- test unitaire de non-régression : `camera.hpp` ne peut plus déclarer
  `RscTitles` et les contrôles du viseur doivent rester dans le dialogue ;
- rebuild du PBO puis confirmation en jeu encore nécessaires sous Windows.
