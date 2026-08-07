# Modèles SSE utilisateur

Les **modèles** permettent de réutiliser des profils narratifs complets (profil, thème, pools de noms, SMS, documents, biométrie, ordinateur…).

## Sources

| Source | Stockage |
|--------|----------|
| `BUILTIN` | Intégrés au mod (catalogue + ères Irak / Russie) |
| `MISSION` | `comspec_sse_models_mission` (synchronisé) |
| `USER` | `profileNamespace` (persistant local) |
| `WEB` | Atelier portail Athena `/atak/sse/dev` (export SQF / fichier d’échange) |

## Modèles intégrés

### Génériques
- Cellule insurgée — Irak
- Chef HVT
- Réseau courriers
- Financier
- Technicien IED
- Safehouse urbain
- Contrebande frontière
- Civil non pertinent (bruit)
- Cellule drone / ISR
- Propagande / média

### Irak 2010–2020
- `builtin_iq_2010_2020_cache_armes`
- `builtin_iq_2010_2020_ied`
- `builtin_iq_2010_2020_hvt`
- `builtin_iq_2010_2020_courrier`
- `builtin_iq_2010_2020_financier`
- `builtin_iq_2010_2020_safehouse`

### Russie / Est 2020–2024
- `builtin_ru_2020_2024_recon`
- `builtin_ru_2020_2024_logistics`
- `builtin_ru_2020_2024_command`
- `builtin_ru_2020_2024_drone`
- `builtin_ru_2020_2024_ew`
- `builtin_ru_2020_2024_infoops`
- `builtin_ru_2020_2024_courier`
- `builtin_ru_2020_2024_civil`

Région `RUSSIA` disponible dans les pools narratifs (noms, préfixes +7, alias).

## API

```sqf
// Créer
private _model = ["Ma cellule Abu Yassin", createHashMapFromArray [
    ["profile", "INSURGENT"],
    ["complexity", "DETAILED"],
    ["region", "IRAQ"],
    ["theme", "weapons_cache"],
    ["aliasPool", ["ABU YASSIN", "ABU HAMZA"]],
    ["contactPool", ["FARID", "MUSTAFA", "THE DRIVER"]],
    ["smsTemplates", [
        "Livraison demain après la prière.",
        "Le camion passe par le point ALPHA."
    ]],
    ["includeComputer", true],
    ["tags", ["custom", "irak"]]
], name player] call comspec_sse_fnc_createModel;

[_model] call comspec_sse_fnc_saveModel;

// Lister
private _all = [] call comspec_sse_fnc_listModels;
private _userOnly = ["USER"] call comspec_sse_fnc_listModels;

// Appliquer
[_unit, _model get "id"] call comspec_sse_fnc_applyModel;
[_unit, "builtin_chef_hvt"] call comspec_sse_fnc_applyModel;

// Capturer une entité existante
private _captured = [_unit, "Capture Karim"] call comspec_sse_fnc_modelFromEntity;

// Export / import (partage entre missions)
private _export = [_model get "id"] call comspec_sse_fnc_exportModel;
[_export, "Copie importée"] call comspec_sse_fnc_importModel;

// Supprimer (pas les builtins)
[_model get "id"] call comspec_sse_fnc_deleteModel;
```

## Zeus

- **Appliquer modèle SSE** — dialogue de sélection
- **Enregistrer comme modèle SSE** — capture la cible
- **Lister modèles SSE** — inventaire

## Eden

Champ **Modèle SSE** : saisir un ID (`builtin_chef_hvt`, etc.).  
Prioritaire sur Profil si renseigné + Génération AUTO.

## Structure d'un modèle

```
id, name, author, source, version
profile, complexity, region, theme
namePool, aliasPool, contactPool
smsTemplates, documentTemplates, codewords, locations
forcedIdentity, forcedPhone
noiseProbability, falseLeadProbability
includeBiometrics, includePhone, includeDocuments, includeComputer
networkSize, tags, notes
```

## Créer des packs (prompts)

Voir [`docs/sse/prompts-packs-modeles-mission.md`](../../../docs/sse/prompts-packs-modeles-mission.md) :
prompt ChatGPT (contenu JSON + SQF) et prompt Cursor (intégration dépôt).
