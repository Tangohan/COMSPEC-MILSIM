# SSE — Documentation

Documentation fonctionnelle et technique du module SSE, incluant les **dossiers d’intérêt** (`ATH-SSE-DI`) et le **laboratoire numérique** (`ATH-SSE-LABNUM`).

## Cycles de référence

- **Dossiers d’intérêt :** acquisition → dossier d’intérêt → collecte → propositions de rapprochement → validation humaine → consolidation ou clôture → réévaluation.
- **Exploitation numérique :** découverte du support → saisie → enregistrement → acquisition → intégrité → extraction → exploitation web → rapprochements SSE → validation → compte rendu.

## Règle fondamentale

Une proposition produite par le système constitue une aide à l’analyse. Elle ne vaut ni identification, ni validation, ni preuve. Aucun traitement planifié ne consolide ou ne fusionne une identité.

## Documents — Dossiers d’intérêt

- [Produit et périmètre](01-produit/presentation.md)
- [Terminologie](01-produit/terminologie.md)
- [Manuel opérateur](02-operateur/manuel-operateur.md)
- [Doctrine de qualification](03-doctrine/qualification.md)
- [Moteur de rapprochement](04-technique/rapprochements.md)
- [Architecture et données](04-technique/architecture.md)
- [Tâches planifiées](04-technique/cron.md)
- [Sécurité multi-tenant](04-technique/securite.md)
- [Référence API](04-technique/api.md)
- [Intégration Arma 3 / SEEK II](05-arma/seek-ii.md)
- [Administration et habilitations](06-administration/habilitations.md)
- [Plan de recette](07-tests/plan-recette.md)

## Documents — Exploitation numérique (`ATH-SSE-LABNUM`)

- [Présentation](08-exploitation-numerique/presentation.md)
- [Doctrine d’acquisition](08-exploitation-numerique/doctrine-acquisition.md)
- [Supports](08-exploitation-numerique/supports.md)
- [Acquisitions](08-exploitation-numerique/acquisitions.md)
- [Artefacts](08-exploitation-numerique/artefacts.md)
- [Téléphones](08-exploitation-numerique/telephones.md)
- [Ordinateurs](08-exploitation-numerique/ordinateurs.md)
- [Chronologie](08-exploitation-numerique/chronologie.md)
- [Moteur d’analyse](08-exploitation-numerique/moteur-analyse.md)
- [Intégration SSE](08-exploitation-numerique/integration-sse.md)
- [Intégration Arma](08-exploitation-numerique/integration-arma.md)
- [Sécurité](08-exploitation-numerique/securite.md)
- [Rapports](08-exploitation-numerique/rapports.md)
- [Dictionnaire de données](08-exploitation-numerique/dictionnaire-donnees.md)
- [Plan de recette](08-exploitation-numerique/plan-recette.md)
- [Mise en place technique](08-exploitation-numerique/mise-en-place.md)

## Interface portail

- Charte **Bureau SSE** : [`docs/frontend/dc/SSE-CONFIDENTIEL-CHARTE.md`](../frontend/dc/SSE-CONFIDENTIEL-CHARTE.md)
- Apparence unique : **Bureau SSE** (inspirée LMS Effectifs / tableau de bord)
- **Toiles de données** (data mesh) : `/atak/sse/toiles` — graphes d’enquête créables, importables depuis un dossier
- **Manuel opérateur HTML** (intégré au site) : `/atak/sse/guide` — accessible après habilitation SSE (navigation *Aide → Documentation*)
