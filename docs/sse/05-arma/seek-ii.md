# SSE — Guide d’intégration Arma 3 et SEEK II

SEEK II proposera visage, iris, empreinte, document, photographie de personne/objet/véhicule et identité déclarée. L’absence de correspondance mène à « enregistrer sans décision » ou créer un dossier d’intérêt.

Événements : `SSE.CONTROL.STARTED`, `SSE.IDENTITY.CAPTURED`, `SSE.FACE.CAPTURED`, `SSE.IRIS.CAPTURED`, `SSE.FINGERPRINT.CAPTURED`, `SSE.DOCUMENT.SCANNED`, `SSE.VEHICLE.CAPTURED`, `SSE.OBJECT.CAPTURED`, `SSE.ACQUISITION.SUBMITTED`, `SSE.MATCHES.RECEIVED`, `SSE.CONTROL.COMPLETED`.

Les envois hors ligne utilisent une clé d’idempotence persistante, une file locale chiffrée et un acquittement serveur. Une réponse ne présente que des correspondances possibles et ne consolide jamais automatiquement.

Pour la saisie et l’acquisition de supports numériques (téléphones, PC, USB, etc.) depuis le terrain, voir [Intégration Arma — Exploitation numérique](../08-exploitation-numerique/integration-arma.md).
