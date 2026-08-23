# Bug — acquisitions numériques SSE (HTTP 500)

## Contexte
Transmission terrain SEEK → poste Athena, `POST /api/sse/digital-acquisitions` (`SubmitSseDigital`). File hors-ligne DIGITAL qui ne se vide pas (`flushQueue sent=0 remaining=2`).

## Symptôme
Erreur production : `Call to undefined method App\Core\Database::lastInsertId()`.
Message joueur : « Une erreur est survenue. Merci de réessayer plus tard. » (HTTP 500, ex. corrélation `0077e9f25f7de93a`).

## Cause
`SseDigitalLabRepository` fait `execute()` puis `$this->db->lastInsertId()`. `App\Core\Database` exposait `insert()` (qui lit déjà l’id) mais pas `lastInsertId()`.

## Correctif
Ajouter `Database::lastInsertId()` et l’utiliser aussi depuis `insert()`.

## Fichiers touchés
- `app/Core/Database.php`
- `tests/Unit/DatabaseLastInsertIdTest.php`

## Vérification
- PHPUnit : `DatabaseLastInsertIdTest`
- Relancer une acquisition numérique SEEK après déploiement portail : plus de 500, file DIGITAL qui se vide.

## Statut
corrigé (à déployer sur athena.ttrd.fr)
