# ACE Interaction

## Sur personne (`CAManBase`)

```
SSE
├── Inspecter
├── Photographier
├── Fouiller
├── Biométrie
│   ├── Ouvrir SEEK II
│   ├── Empreintes
│   ├── Iris
│   ├── Photo faciale
│   ├── ADN
│   ├── Capture complète
│   └── Identifier
├── Exploitation numérique (…)
└── Marquer comme exploité
```

## Sur objet / véhicule

```
SSE
├── Examiner
├── Collecter
└── Exploitation numérique
    ├── Identifier / Contacts / Messages / Appels / Photos / Coordonnées
    ├── Extraction complète
    ├── Infos système / Utilisateurs / Fichiers / Navigateur / Mail
    ├── Supports connectés / Identifiants
    └── Collecter support (USB/SD)
```

## Self

```
COMSPEC SSE
├── Journal SSE
├── Ouvrir terminal SSE
└── Équiper le kit SSE
```

Sur cible SSE : **Ouvrir terminal SSE** (lie le record puis ouvre le hub).

## Réglages CBA

- Exiger le matériel SSE
- Durées inspect / fouille / photo / empreintes / téléphone / ordinateur
- Probabilités bruit / fausse piste
- Mission ID / dossier SSE / mapId
- Logs debug

Les actions utilisent `ace_common_fnc_progressBar`.
