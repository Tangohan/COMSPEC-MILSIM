# Matériel SSE — compatibilité multi-mods

Les actions SSE (photo, empreintes, SEEK, collecte…) exigent un **rôle** de matériel, pas uniquement les items `COMSPEC_SSE_*`.

## Réglages CBA

| Réglage | Effet |
|---------|--------|
| Exiger le matériel SSE | Active / désactive la vérification |
| Accepter les items d'autres mods | Autorise les substituts (cTab, ACE, …) s’ils sont chargés |
| Alias matériels additionnels | Classes custom : `camera:MonItem;seek:MaTablette` |

## Rôles et substituts par défaut

| Rôle | Natif | Substituts (si présents) |
|------|-------|--------------------------|
| `camera` | `COMSPEC_SSE_Camera` | `ItemcTabHCam`, `ItemAndroid`, `ItemcTab` |
| `evidence_bag` | `COMSPEC_SSE_EvidenceBag` | `ACE_surgicalKit`, `ACE_personalAidKit`, `ACE_bodyBag` |
| `fingerprint` | Kit empreintes + SEEK II | `ItemAndroid`, `ItemcTab`, `ACE_microDAGR` |
| `dna` | Kit ADN + SEEK II | kits médicaux ACE |
| `seek` | `COMSPEC_SSE_SEEKII` | `ItemAndroid`, `ItemcTab`, `ACE_microDAGR`, `ACE_Cellphone` |
| `gloves` | `COMSPEC_SSE_Gloves` | `ACE_surgicalKit` |
| `radio` | `COMSPEC_SSE_SatPhone` / Phone | TFAR `anprc*`, ACRE `PRC*`, `ACE_Cellphone` |
| `face` | Camera + SEEK | caméra casque / tablettes / **BII-10** |

Les substituts absents du chargement (mod non présent) sont **ignorés automatiquement**.

Passerelle BII Identifi : [BII-BRIDGE.md](BII-BRIDGE.md).

## API

```sqf
[_unit, "camera"] call comspec_sse_fnc_hasEquipment;
[_unit, "COMSPEC_SSE_Camera"] call comspec_sse_fnc_hasEquipment; // équivalent
[_unit, "seek"] call comspec_sse_fnc_resolveEquipment; // classname trouvé
["fingerprint"] call comspec_sse_fnc_getEquipmentAliases;
```

## Items inventaire

Les items SSE héritent de `CBA_MiscItem` (compatible ACE Arsenal / inventaire).
