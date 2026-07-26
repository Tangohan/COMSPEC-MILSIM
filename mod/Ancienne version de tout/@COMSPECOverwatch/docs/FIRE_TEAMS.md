# Équipes de feu — branchement jeu (léger)

## Plateforme

- API : `GET /api/atak/fire-teams?kind=ephemeral&mapId=&tenant_id=`
- Auth : `X-COMSPEC-KEY` (ou session web admin)
- UI : `/back-office/atak/fire-teams`

## Extension C# (`GetFireTeams`)

Args : `[tenantId, mapId]` (tous deux optionnels / chaînes vides).

Réponse `OK|` puis lignes tabulées :

- Équipe : `id\tlabel\tcolor\tmapId\tkind\tmemberCount`
- Membre : `M\tteamId\tcallsign\trole\tdisplayName`

## SQF

```sqf
private _teams = [] call comspec_overwatch_connect_fnc_getFireTeams;
// missionNamespace "COMSPEC_FireTeams"
```

## Limites actuelles

- Lecture seule côté jeu (pas de création / dissolution in-game).
- Filtre par défaut : équipes **éphémères** de mission (pas les permanentes ORBAT).
- Rebuild de l’extension native requis pour exposer `GetFireTeams` en production.
