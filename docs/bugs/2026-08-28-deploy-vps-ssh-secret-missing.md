# Deploy VPS : secret SSH absent, production non mise à jour

## Contexte

La page stockage (`/public/admin/system/storage`) est sur GitHub `main` (PR #236, commits `476703b3` et `431ba2da`) mais la production athena.ttrd.fr répond 404. Le déploiement nominal est l'Action **Deploy VPS**.

## Symptôme

L'Action échoue en ~4–8 s à l'étape « Pull on VPS », sans jamais exécuter `git pull` sur le VPS.

Run : https://github.com/Tangohan/COMSPEC-MILSIM/actions/runs/33208506010

Message exact :

```text
Error: can't connect without a private SSH key or password
```

Les cinq runs depuis l'introduction du workflow (PR #234, 27 août 2026) échouent de la même façon.

## Cause

Le secret GitHub `VPS_SSH_KEY` n'existe pas. Le dépôt n'a que les anciens secrets FTP (`FTP_PASSWORD`, `FTP_SERVER`, `FTP_USERNAME`), inutilisés par ce workflow. `appleboy/ssh-action` reçoit une clé vide. Ce n'est pas une divergence git sur le VPS, ni un sparse-checkout, ni un problème SSH réseau.

## Correctif

- Dans le dépôt : pré-contrôle qui échoue avec un message explicite si `VPS_SSH_KEY` est vide ; en cas d'échec de fast-forward, journaliser l'état git sans `reset --hard`.
- Côté GitHub (humain, obligatoire pour débloquer la prod) : créer le secret `VPS_SSH_KEY` (clé privée dont la publique est déjà dans `/root/.ssh/authorized_keys`). Ne pas régénérer les clés. Puis relancer l'Action.

Contournement VPS (sans force) si l'opérateur a déjà un SSH manuel :

```bash
cd /var/www/athena.ttrd.fr
git fetch origin
git checkout main
git pull --ff-only origin main
```

Ne jamais pousser depuis le VPS.

## Fichiers touchés

- `.github/workflows/deploy-vps.yml`

## Vérification

`gh secret list` ne doit plus omettre `VPS_SSH_KEY`. Un run Deploy VPS doit passer l'étape SSH. La page `/public/admin/system/storage` doit répondre 200 une fois le pull effectué.

## Statut

identifié — diagnostic workflow posé ; secret GitHub encore à créer
