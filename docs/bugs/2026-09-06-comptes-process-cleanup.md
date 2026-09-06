# Process comptes allégé

## Contexte

Le hub compte et les flux RH mélangeaient identité portail, « personnage », multi-communautés, clearance profil et revues d’accréditation / secret défense. Ces couches alourdissaient le process sans valeur produit claire.

## Correctif

- Plus de clearance profil dans les flux compte / RH / élévation / recrutement / onboarding.
- Accès documents : plafond par **rôle** uniquement (taxonomie de classification conservée).
- Suppression du produit d’accréditation (page, API panel 9101, nav, CTA documents).
- Plus de revue périodique d’habilitation (ClearanceReviewPolicy, badge effectifs, digest RH).
- Hub compte recentré : une identité, rôle & élévations — sans pédagogie multi-personnage / multi-tenant.
- Colonnes SQL `clearance_*` laissées nullable (plus écrites) ; SSE clearance inchangé.

## Vérification

- `tests/Unit/AccountProcessCleanupAssetTest.php`
- Tests asset clearance / fiche / operational status mis à jour
