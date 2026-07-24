# Système de codes d'invitation pour le recrutement

## Vue d'ensemble

Le système de codes d'invitation permet aux administrateurs de faciliter la migration rapide de communautés entières en distribuant des codes qui valident automatiquement les candidatures. C'est idéal pour intégrer rapidement des membres d'une communauté partenaire ou pour des recrutements ciblés.

## Fonctionnalités principales

### Pour les administrateurs

#### Création de codes
- **Génération automatique** : Le système génère un code unique aléatoire sécurisé
- **Code personnalisé** : Possibilité de définir un code mémorable (ex: `MIGRATION2026`)
- **Validation automatique** : Option pour accepter immédiatement les candidatures
- **Limites configurables** : Nombre maximum d'utilisations et date d'expiration
- **Association automatique** : Lier le code à une offre de recrutement spécifique
- **Spécialité par défaut** : Pré-remplir la spécialité des candidats

#### Gestion et suivi
- **Liste complète** : Vue d'ensemble avec filtres (actifs/tous)
- **Statistiques détaillées** : Nombre d'utilisations, dernière utilisation, taux d'utilisation
- **Historique complet** : Liste de toutes les candidatures créées avec le code
- **Modification** : Ajuster les paramètres d'un code existant
- **Désactivation** : Expire immédiatement un code (préserve l'historique)

### Pour les candidats

- **Champ optionnel** : Saisie simple du code dans le formulaire de candidature
- **Validation immédiate** : Acceptation automatique si configurée
- **Messages clairs** : Retours explicites en cas de code invalide

## Guide d'utilisation

### 1. Créer un code d'invitation

1. Connectez-vous en tant qu'administrateur organisation
2. Accédez à **Recrutement** → **Codes d'invitation**
3. Cliquez sur **"Créer un code"**
4. Remplissez le formulaire :
   - **Libellé** : Nom pour identifier le code (ex: "Migration Alpha Squad")
   - **Code personnalisé** (optionnel) : Laissez vide pour génération auto
   - **Validation automatique** : Cochez pour acceptation immédiate
   - **Nombre max d'utilisations** : Laissez vide pour illimité
   - **Date d'expiration** : Optionnel
   - **Lier à une offre** : Pour affectation automatique
   - **Spécialité par défaut** : Pré-remplissage optionnel

### 2. Distribuer le code

Une fois créé, vous pouvez distribuer le code par :
- Message Discord/TeamSpeak
- Email groupé
- Annonce sur votre site/forum
- Message direct aux membres concernés

**Exemple de message** :
```
Bienvenue à tous les membres d'Alpha Squad !

Pour rejoindre notre communauté, utilisez ce code lors de votre candidature :
MIGRATION2026

Ce code vous permettra d'être accepté immédiatement.

Lien du formulaire : https://votre-site.com/enlistment
```

### 3. Suivre l'utilisation

1. Retournez sur **Codes d'invitation**
2. Cliquez sur le code concerné
3. Consultez les statistiques :
   - Nombre d'utilisations
   - Liste des candidatures
   - Statut de chaque candidature

### 4. Gérer un code existant

#### Modifier
- Accédez à la page du code
- Cliquez sur **"Modifier"**
- Ajustez les paramètres (limite, expiration, etc.)

#### Désactiver
- Accédez à la page du code
- Dans la zone danger, cliquez sur **"Désactiver ce code"**
- Le code devient immédiatement invalide
- L'historique est préservé pour audit

## Cas d'usage

### Migration de communauté

**Contexte** : Votre communauté partenaire "Alpha Squad" (50 membres) souhaite migrer vers votre plateforme.

**Solution** :
1. Créez un code `ALPHASQUAD2026`
2. Paramètres :
   - Validation automatique : ✓
   - Max utilisations : 50
   - Expiration : 30 jours
3. Distribuez le code aux 50 membres
4. Suivez la progression en temps réel
5. Une fois tous migrés, le code s'auto-désactive

### Recrutement ciblé

**Contexte** : Vous ouvrez 5 postes de pilotes expérimentés.

**Solution** :
1. Créez une offre "Pilote de chasse"
2. Créez un code `PILOTE2026`
3. Paramètres :
   - Lier à l'offre "Pilote de chasse"
   - Spécialité par défaut : "Aviation"
   - Max utilisations : 5
   - Validation automatique : ✗ (instruction manuelle)
4. Envoyez le code aux 5 candidats pré-qualifiés
5. Leurs candidatures sont automatiquement liées à l'offre

### Événement de recrutement

**Contexte** : Vous organisez un événement de recrutement ouvert pendant 48h.

**Solution** :
1. Créez un code `EVENT48H`
2. Paramètres :
   - Expiration : dans 48h
   - Validation automatique : ✓
   - Max utilisations : illimité
3. Communiquez largement le code
4. Le code expire automatiquement après l'événement

## Interface et navigation

### Accès
- Menu principal : **Recrutement**
- Sous-menu : **Codes d'invitation**
- URL : `/back-office/recruitments/codes-invitation`

### Pages disponibles
- **Liste** : Vue d'ensemble avec filtres
- **Création** : Formulaire de création
- **Détails** : Statistiques et historique d'un code
- **Modification** : Édition des paramètres

### Permissions requises
- Rôle : Administrateur organisation
- Middleware : `OrganizationAdminMiddleware`

## Sécurité et bonnes pratiques

### Sécurité
✅ **Codes uniques par tenant** : Impossible d'utiliser un code d'un autre tenant
✅ **Validation stricte** : Vérifications côté serveur (expiration, quotas)
✅ **Traçabilité complète** : Chaque utilisation est loggée
✅ **CSRF protection** : Tous les formulaires sont protégés
✅ **Isolation tenant** : Pas de fuite de données entre organisations

### Bonnes pratiques

#### Pour la création
- ✅ Utilisez des libellés descriptifs (pour vous retrouver facilement)
- ✅ Définissez des limites raisonnables (évitez illimité si possible)
- ✅ Ajoutez une date d'expiration (sécurité)
- ✅ Utilisez des codes mémorables pour la distribution orale

#### Pour la distribution
- ✅ Distribuez via des canaux sécurisés (Discord privé, email)
- ✅ Communiquez clairement les instructions d'utilisation
- ✅ Précisez si le code accepte automatiquement ou non
- ⚠️ Évitez de publier les codes publiquement si validation auto

#### Pour le suivi
- ✅ Vérifiez régulièrement les statistiques
- ✅ Désactivez les codes obsolètes
- ✅ Conservez les historiques pour audit
- ✅ Contactez les utilisateurs du code si problème détecté

## Intégration technique

### Base de données

#### Tables
```sql
recruitment_invite_codes       -- Stockage des codes
recruitment_invite_code_uses   -- Historique d'utilisation
enlistments.invite_code_id     -- Lien candidature-code
```

#### Relations
- Un code appartient à un tenant
- Un code peut être utilisé plusieurs fois
- Une candidature peut utiliser un seul code
- Les statistiques sont calculées en temps réel

### Timeline et audit

Chaque utilisation de code est enregistrée dans la timeline de la candidature avec :
- Date et heure d'utilisation
- Libellé du code utilisé
- Mention de la validation automatique si applicable
- Métadonnées JSON pour requêtes avancées

### API et hooks

Le système peut être étendu pour :
- Générer des codes en masse (bulk generation)
- Exporter les statistiques (CSV, JSON)
- Intégrer des webhooks de notification
- Créer des rapports personnalisés

## Dépannage

### Problème : Code non reconnu
**Cause** : Code inexistant ou mal saisi
**Solution** : Vérifier l'orthographe (sensible à la casse)

### Problème : Code invalide
**Causes possibles** :
- Code expiré (date dépassée)
- Limite d'utilisations atteinte
- Code désactivé manuellement

**Solution** : Créer un nouveau code ou modifier l'existant

### Problème : Validation automatique ne fonctionne pas
**Cause** : Option non cochée lors de la création
**Solution** : Modifier le code pour activer l'option

### Problème : Statistiques incorrectes
**Cause** : Cache ou problème de synchronisation
**Solution** : 
1. Rafraîchir la page
2. Vérifier la table `recruitment_invite_code_uses`
3. Contacter le support technique si persistant

## Évolutions futures possibles

### Court terme
- 📊 Export CSV des statistiques
- 🔔 Notifications aux admins lors d'utilisation
- 📱 QR codes pour faciliter la saisie mobile

### Moyen terme
- 📦 Génération de codes par lot (bulk)
- 🎯 Codes à usage unique (one-time tokens)
- 📧 Distribution automatique par email

### Long terme
- 🤖 Workflows automatisés (code → affectation → formation)
- 📈 Tableaux de bord analytiques avancés
- 🔗 Intégration API pour systèmes externes

## Support

Pour toute question ou problème :
1. Consultez la documentation technique : `docs/RECRUITMENT_INVITE_CODES_TESTING.md`
2. Vérifiez les logs de la timeline des candidatures
3. Contactez votre administrateur technique

---

**Version** : 1.0  
**Dernière mise à jour** : Juillet 2026  
**Auteur** : Cursor AI Agent
