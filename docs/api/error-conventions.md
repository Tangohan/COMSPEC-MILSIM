# Conventions JSON d'erreur (API)

## Enveloppe standard

```json
{
  "success": false,
  "error": {
    "code": "validation_failed",
    "message": "Validation échouée.",
    "details": {
      "fields": {
        "timezone": ["Valeur invalide"]
      }
    }
  }
}
```

## Règles

- `success` est toujours présent (`false` en erreur).
- `error.code` est stable et exploitable côté front/intégration (pas localisé).
- `error.message` est lisible humainement (peut être localisé).
- `error.details` est optionnel et réservé aux structures (erreurs champs, diagnostics).
- Les codes HTTP restent sémantiques (`400`, `401`, `403`, `404`, `422`, `500`, etc.).

## Codes initiaux (zones critiques)

- `invalid_context`
- `unauthorized`
- `tenant_missing`
- `csrf_invalid`
- `validation_failed`
- `notifications_channel_invalid`
- `method_not_allowed`
- `db_unreachable`
