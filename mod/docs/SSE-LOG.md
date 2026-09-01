# Journal technique SSE

Tampon circulaire en mission (`comspec_sse_logBuffer`, 120 entrées).

| Niveau | RPT | Chat (si debug CBA) | Tampon |
|--------|-----|---------------------|--------|
| ERROR / WARN | toujours | oui si debug | toujours |
| INFO / DEBUG | si debug | si debug | toujours |

## Consultation

- ACE Self → **COMSPEC SSE** → **Journal technique (erreurs)**
- Console : `[] call comspec_sse_fnc_showLog` / `[] call comspec_sse_fnc_getLog`
- RPT Arma : lignes `[COMSPEC SSE][ERROR|WARN]`

Les erreurs de génération (`_data` invalide, `setPair`) y apparaissent même sans debug CBA.
