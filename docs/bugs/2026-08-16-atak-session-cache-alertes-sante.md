# ATAK — sessions 24 h, cache carte, alertes santé masquables

## Contexte

Portail `/atak` : longues sessions opérationnelles, cache local qui s’accumule, alertes santé qui ne restaient pas masquées.

## Symptômes

1. Session trop courte (téléphone ~2 h, TTL roleplay 10 min, cookie défaut 5 h).
2. Pas de moyen clair de réinitialiser la carte / vider certains caches.
3. Clic « masquer » sur une alerte santé : elle réapparaît (resync tchat/API avec une autre clé).

## Correctifs

### Sessions 24 h
- Cookie / `gc_maxlifetime` : défaut `SESSION_LIFETIME=1440` min.
- Session téléphone après liaison : 1440 min.
- `session_ttl_sec` roleplay / reprise CTD / journal activité : 86400 s.

### Cache carte (Compte → Carte & cache)
- Vue, tracés, alertes santé masquées, photos masquées, couches, tout le cache local (+ reload).

### Alertes santé
- Empreinte indicatif+type (`alerts_fp` / `units_cs`) en plus de la clé exacte.
- Fenêtre de masquage 24 h (plus 30 min).
- Stockage `atak_medical_dismissed_v2_*`.

## Fichiers touchés

- `app/Config/auth.php`, `.env.example`
- `app/Repositories/TacticalPhonePairingRepository.php`
- `app/Repositories/AtakDisconnectRecoveryRepository.php`
- `app/Services/Tactical/AtakActivityLogService.php`
- `app/Controllers/Api/AtakApiController.php`
- `public/assets/js/atak-medical-alerts.js`
- `public/assets/js/atak-cache-reset.js`
- `views/atak.php`
- `public/assets/css/atak.css`

## Vérification

1. Compte → Carte & cache : chaque bouton fait l’action annoncée.
2. Masquer une alerte santé → elle ne revient pas au prochain poll (sauf nouvelle alerte API après le masquage).
3. Prod : `SESSION_LIFETIME=1440` dans `.env` si l’ancien 300 est encore forcé.

## Statut

corrigé
