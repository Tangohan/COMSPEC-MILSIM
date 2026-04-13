# Évolutions possibles — espace compte et sécurité

Document de **priorisation produit** pour compléter le périmètre actuel (hub « Mon compte », préférences, e-mail, images, mot de passe, profils de candidature, lien vers la fiche personnelle). Référence technique des routes : [`ROUTES.md`](ROUTES.md) (section 2.1).

## Priorité 1 — intégrité du compte et confiance

| Sujet | Intérêt | Notes |
|--------|---------|--------|
| **Re-vérification de l’e-mail après changement** | Évite qu’un tiers avec session ouverte impose une nouvelle boîte sans contrôle du titulaire. | Aujourd’hui [`AccountController::mail`](../app/Controllers/Web/AccountController.php) met à jour l’e-mail après mot de passe ; un flux « lien de confirmation vers la nouvelle adresse » renforce la sécurité. |
| **Clarifier l’état « adresse confirmée »** | Cohérence avec les relances et l’accès. | Déjà affiché sur le hub compte ; à aligner avec tout futur flux de changement d’e-mail. |

## Priorité 2 — visibilité et contrôle pour l’utilisateur

| Sujet | Intérêt | Notes |
|--------|---------|--------|
| **Appareils ou sessions récentes** | Permet de réagir en cas de compte utilisé ailleurs. | Mentionné dans la vision [`PLAN_CURSOR_THE-UNIT-COMMANDER.md`](PLAN_CURSOR_THE-UNIT-COMMANDER.md) (événements / sessions). Nécessite modèle de données et UI non technique. |
| **Révocation de session** (déconnexion à distance) | Complement des sessions listées. | À coupler avec une politique de durée de session côté configuration. |

## Priorité 3 — renforcement d’authentification

| Sujet | Intérêt | Notes |
|--------|---------|--------|
| **Second facteur (2FA)** | Réduction du risque en cas de mot de passe compromis. | Évoqué comme évolution possible dans [`PLAN_CURSOR_THE-UNIT-COMMANDER.md`](PLAN_CURSOR_THE-UNIT-COMMANDER.md). Fort impact UX et support. |
| **Passkeys / WebAuthn** | Alternative moderne au mot de passe seul. | À traiter après ou en parallèle d’une réflexion 2FA selon maturité produit. |

## Hors périmètre implicite du hub « Mon compte »

- **Dossier opérationnel (personnage, unité, clearance)** : porté par **`/personnel/me`** et l’édition associée, pas par `/account` (choix volontaire dans l’UI actuelle).
- **SSO / fédération d’identité** : chantier transverse, non spécifique à la page compte.

## Prochaine étape recommandée

Choisir **une** entrée de priorité 1 pour un cycle de développement, puis itérer (re-vérif e-mail avant d’ouvrir les chantiers « sessions » ou 2FA).
