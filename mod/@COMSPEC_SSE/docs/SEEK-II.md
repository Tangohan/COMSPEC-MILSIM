# SEEK II / Biométrie — V0.3

## Terminal SEEK II

Item requis : `COMSPEC_SSE_SEEKII`  
**ou** un substitut compatible (ex. `ItemAndroid`, `ItemcTab`) si l’option CBA *Accepter les items d'autres mods* est active — voir [EQUIPMENT.md](EQUIPMENT.md).

ACE → SSE → Biométrie → **Ouvrir SEEK II**

Interface sobre vert/gris :

- EMPREINTES / IRIS / VISAGE / ADN
- IDENTIFIER (interrogation bases simulée)
- CAPTURE ALL
- TRANSMETTRE

## Captures individuelles

| Action | Matériel |
|--------|----------|
| Empreintes | FingerprintKit ou SEEK II |
| Iris | SEEK II |
| Visage | Camera ou SEEK II |
| ADN | DNKit |
| Capture complète | SEEK II recommandé |

## API

```sqf
[_unit] call comspec_sse_fnc_openSeek;
[_unit, player] call comspec_sse_fnc_captureAll;
[_unit, player] call comspec_sse_fnc_identifySubject;
[_unit] call comspec_sse_fnc_getBiometricSummary;
[_bioA, _bioB] call comspec_sse_fnc_compareBiometrics;
```

Verdicts d'identification simulés (déterministes via seed) :

- INCONNU des bases
- SIGNALÉ — correspondance partielle
- RECHERCHÉ — correspondance confirmée
