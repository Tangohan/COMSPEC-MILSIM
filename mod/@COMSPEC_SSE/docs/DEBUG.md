# Debug

## Logs

```sqf
comspec_sse_debug = true; // ou via CBA Settings
```

Messages : `[COMSPEC SSE][INFO] ...` dans RPT + systemChat.

## Zeus Debug Inspector

Module **SSE Debug Inspector** :

- Affiche UID / profil / état au-dessus de l'entité pointée
- Option liens 3D (`drawLine3D`)

```sqf
missionNamespace setVariable ["comspec_sse_debugInspector", false]; // stop
```

## Tests

```sqf
[] execVM "<chemin>\mod\@COMSPEC_SSE\tools\test_scenarios.sqf";
```

Scénarios couverts : identité, téléphone, liens, site, HVT, transmission, offline, seeds, état partagé, regen Zeus.
