# Guide de test : Système de codes d'invitation de recrutement

## Configuration initiale

### 1. Exécuter la migration

```bash
php migrate.php
```

Vérifier que les tables suivantes ont été créées :
- `recruitment_invite_codes`
- `recruitment_invite_code_uses`
- Colonne `invite_code_id` ajoutée à `enlistments`

## Tests fonctionnels

### Test 1 : Création d'un code basique

**Objectif** : Vérifier la création d'un code avec génération automatique

**Étapes** :
1. Se connecter en tant qu'administrateur organisation
2. Naviguer vers `/back-office/recruitments/codes-invitation`
3. Cliquer sur "Créer un code"
4. Remplir uniquement le libellé : "Test migration communauté Alpha"
5. Cocher "Validation automatique"
6. Soumettre le formulaire

**Résultat attendu** :
- Code généré automatiquement (12 caractères alphanumériques majuscules)
- Redirection vers la page de détails du code
- Badge "Actif" visible
- Badge "Validation automatique" visible
- Statistiques à 0 utilisations

---

### Test 2 : Création d'un code personnalisé avec paramètres

**Objectif** : Vérifier la création avec tous les paramètres

**Étapes** :
1. Créer un nouveau code avec :
   - Libellé : "Migration limitée"
   - Code personnalisé : "MIGRATION2026"
   - Validation automatique : activée
   - Nombre max d'utilisations : 10
   - Date d'expiration : 7 jours dans le futur
   - Spécialité par défaut : "Infanterie"

**Résultat attendu** :
- Code créé avec le code "MIGRATION2026"
- Tous les paramètres correctement enregistrés
- Visible dans la liste des codes actifs

---

### Test 3 : Utilisation d'un code valide (validation auto)

**Objectif** : Vérifier l'utilisation d'un code avec acceptation automatique

**Étapes** :
1. Créer un code avec validation automatique
2. Déconnexion
3. Aller sur le formulaire de candidature (mode invité)
4. Remplir le formulaire complet
5. Dans le champ "Code d'invitation" (à ajouter au formulaire), saisir le code
6. Soumettre

**Résultat attendu** :
- Candidature créée avec statut `reviewed` (acceptée)
- Colonne `invite_code_id` remplie
- Compteur d'utilisations du code incrémenté
- Entrée dans `recruitment_invite_code_uses`
- Message dans la timeline : "Code d'invitation utilisé (validation automatique)"

---

### Test 4 : Utilisation d'un code invalide

**Objectif** : Vérifier la gestion des codes invalides

**Tests à effectuer** :
a) Code inexistant : "FAKECODEXXX"
b) Code expiré (créer un code avec expiration dans le passé)
c) Code à limite atteinte (créer un code max_uses=1, l'utiliser, puis réessayer)

**Résultat attendu** :
- Message d'erreur approprié pour chaque cas
- Candidature non créée
- Redirection vers la page d'erreur de candidature

---

### Test 5 : Statistiques et historique

**Objectif** : Vérifier l'affichage des statistiques

**Étapes** :
1. Utiliser un code 3 fois (3 candidatures différentes)
2. Aller sur la page de détails du code
3. Vérifier les statistiques

**Résultat attendu** :
- Compteur : "3" ou "3 / X" si limite définie
- Section "Candidatures avec ce code" affiche 3 lignes
- Chaque ligne montre : nom, email, statut, date d'utilisation
- Liens vers les dossiers fonctionnels

---

### Test 6 : Modification d'un code

**Objectif** : Vérifier la modification des paramètres

**Étapes** :
1. Créer un code
2. Cliquer sur "Modifier"
3. Changer :
   - Libellé
   - Augmenter la limite d'utilisations
   - Modifier la date d'expiration
4. Enregistrer

**Résultat attendu** :
- Modifications enregistrées
- Code toujours fonctionnel avec nouveaux paramètres
- Statistiques d'utilisation préservées

---

### Test 7 : Désactivation d'un code

**Objectif** : Vérifier la désactivation d'un code actif

**Étapes** :
1. Créer un code actif
2. L'utiliser une fois
3. Aller sur la page de détails
4. Cliquer sur "Désactiver ce code"
5. Confirmer
6. Tenter d'utiliser le code dans une nouvelle candidature

**Résultat attendu** :
- Code marqué comme expiré immédiatement
- Badge "Inactif" sur la page de détails
- Tentative d'utilisation rejetée avec message d'erreur
- Historique d'utilisation préservé

---

### Test 8 : Association automatique à une offre

**Objectif** : Vérifier le lien automatique avec une offre de recrutement

**Prérequis** : Avoir au moins une offre de recrutement publiée

**Étapes** :
1. Créer un code avec "Lier automatiquement à une offre" sélectionnée
2. Utiliser le code dans une candidature
3. Vérifier le dossier de candidature

**Résultat attendu** :
- Candidature liée automatiquement à l'offre sélectionnée
- Champ `recruitment_opening_id` rempli

---

### Test 9 : Filtres de la liste

**Objectif** : Vérifier les filtres de la liste des codes

**Étapes** :
1. Créer plusieurs codes :
   - 2 codes actifs
   - 1 code expiré
   - 1 code avec limite atteinte
2. Vérifier la liste avec filtre "Codes actifs"
3. Vérifier la liste avec filtre "Tous les codes"

**Résultat attendu** :
- Filtre "Actifs" : affiche uniquement les 2 codes actifs
- Filtre "Tous" : affiche les 4 codes
- Badges de statut corrects pour chaque code

---

### Test 10 : Sécurité et permissions

**Objectif** : Vérifier les contrôles d'accès

**Tests à effectuer** :
a) Accès sans être connecté → redirection vers login
b) Accès en tant que membre simple → erreur de permission
c) Accès en tant qu'admin d'un autre tenant → voir uniquement ses codes
d) Tentative d'utiliser un code d'un autre tenant → rejeté

**Résultat attendu** :
- Tous les contrôles de sécurité fonctionnels
- Isolation correcte entre tenants

---

## Tests de performance

### Test 11 : Génération de codes uniques

**Objectif** : Vérifier la génération de codes sans collision

**Étapes** :
1. Créer 100 codes sans spécifier de code personnalisé
2. Vérifier l'unicité dans la base de données

**Résultat attendu** :
- Tous les codes générés sont uniques
- Pas de collision ni d'erreur

---

## Tests d'intégration

### Test 12 : Timeline et traçabilité

**Objectif** : Vérifier l'enregistrement dans la timeline

**Étapes** :
1. Utiliser un code avec validation auto
2. Consulter la timeline du dossier de candidature

**Résultat attendu** :
- Entrée "Code d'invitation utilisé" présente
- Détails du code visibles
- Mention "(validation automatique)" si applicable
- Métadonnées JSON correctes

---

## Résolution de problèmes

### Problème : Code non reconnu malgré création réussie
**Solution** : Vérifier que la table existe et que l'isolation par tenant fonctionne

### Problème : Validation automatique ne fonctionne pas
**Solution** : Vérifier la colonne `auto_accept` et la logique dans `EnlistmentController`

### Problème : Statistiques incorrectes
**Solution** : Vérifier la table `recruitment_invite_code_uses` et le compteur `uses_count`

---

## Checklist de validation complète

- [ ] Migration exécutée sans erreur
- [ ] Création de code avec génération automatique
- [ ] Création de code personnalisé
- [ ] Utilisation de code valide avec validation auto
- [ ] Rejet de code invalide/expiré/limité
- [ ] Statistiques et historique corrects
- [ ] Modification de code
- [ ] Désactivation de code
- [ ] Association à une offre
- [ ] Filtres de liste fonctionnels
- [ ] Permissions et sécurité
- [ ] Timeline et traçabilité
- [ ] Isolation entre tenants
- [ ] Interface responsive et accessible

---

## Notes pour les développeurs

### Points d'attention
- Le code doit être unique par tenant (contrainte `unique_tenant_code`)
- La validation du code se fait dans `EnlistmentController::store()`
- Les statistiques utilisent des jointures, vérifier les performances avec de gros volumes
- La désactivation est une expiration immédiate, pas une suppression

### Améliorations futures possibles
- Export CSV de l'historique d'utilisation
- Notifications aux admins lors de l'utilisation
- Génération de codes par lot
- Codes à usage unique (one-time)
- QR codes pour faciliter la saisie
