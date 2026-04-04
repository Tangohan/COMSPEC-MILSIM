# COMSPEC ATAK – Backend (déprécié / archivé)

**Depuis la migration Full PHP**, le C2 ATAK est assuré par l’API REST PHP (voir `app/Controllers/Api/AtakApiController.php`). Ce serveur Node.js n’est plus nécessaire en production.

Ancien rôle : API REST, WebSocket (Socket.io), SQLite, signaling WebRTC.

## Démarrage

```bash
cd server
npm install
npm start
```

Le serveur écoute sur `http://localhost:3001` par défaut. La page `ressources/html/atak.html` doit être ouverte depuis un serveur HTTP (ou le même origin) et pointe vers ce port pour l’API et le WebSocket.

## Endpoints

- `GET/POST /api/markers` – Marqueurs carte
- `GET/POST /api/units` – Unités / groupes (panneau droit)
- `GET/POST /api/chat` – Messages tchat
- `GET/POST /api/pings` – Pings
- `GET/POST /api/nine-line` – 9-Line CAS JTAC
- `GET /api/cams` – Liste flux cams
- `GET/POST /api/intel/photos` – Photos CTAB (upload multipart)

WebSocket (Socket.io) : connexion puis `Hello({ tacMapID: 1 })` pour recevoir l’état initial et les mises à jour temps réel (marqueurs, calques, tchat, pings, 9-Line, unités).
