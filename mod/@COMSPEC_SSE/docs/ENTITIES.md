# Exploitation multi-entités (V0.5)

Types reconnus automatiquement via `comspec_sse_fnc_resolveEntityType` :

| Type | Détection | Contenu généré |
|------|-----------|----------------|
| PERSON | `CAManBase` | identité, bio, digital, docs |
| VEHICLE | Land/Air/Ship | plaque, VIN, soute, docs |
| RADIO | classname radio/TFAR/ACRE/satphone | fréquences, réseau, trafic |
| WEAPON | WeaponHolder / armes | série, marquages, lien cache |
| BUILDING | House/Building (si searchable) | traces pièces, docs, parfois téléphone |
| CONTAINER | caisses / ReammoBox | docs + traces |
| PHONE / COMPUTER / DOCUMENT / MEDIA | classname / items SSE | comme V0.2+ |

## ACE

- Personnes : Inspecter, Photographier, Fouiller, Documents, Marquer (+ bio / digital)
- Véhicules / objets / armes / caisses : Examiner, Fouiller, Collecter, Documents, Exploiter radio
- Self : Journal + **Équiper le kit SSE**

## Mission makers

```sqf
// Forcer un type
_obj setVariable ["comspec_sse_forcedType", "RADIO", true];
[_obj, "RADIO"] call comspec_sse_fnc_makeSearchable;

// Bâtiment (opt-in)
_house setVariable ["comspec_sse_searchable", true, true];
[_house, "BUILDING"] call comspec_sse_fnc_makeSearchable;
```
