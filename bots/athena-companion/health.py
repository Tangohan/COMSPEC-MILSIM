"""Point de santé local (127.0.0.1 seulement) : le superviseur ou `status.sh` y lit l'état du bot. Désactivé si le port vaut 0."""
import time

from aiohttp import web

MAX_POLL_AGE = 180  # secondes : la file des événements est interrogée toutes les 60 s


class Health:
    def __init__(self, client, port):
        self.client = client
        self.port = port
        self.runner = None

    def snapshot(self, now=None):
        now = time.time() if now is None else now
        ready = self.client.is_ready()
        age = None if not self.client.poll_ok_at else int(now - self.client.poll_ok_at)
        healthy = ready and age is not None and age <= MAX_POLL_AGE
        return {'status': 'ok' if healthy else 'degraded', 'ready': ready, 'guilds': len(self.client.guilds), 'poll_age_s': age}

    async def handle(self, request):
        data = self.snapshot()
        return web.json_response(data, status=200 if data['status'] == 'ok' else 503)

    async def start(self):
        app = web.Application()
        app.router.add_get('/health', self.handle)
        self.runner = web.AppRunner(app)
        await self.runner.setup()
        await web.TCPSite(self.runner, '127.0.0.1', self.port).start()

    async def stop(self):
        if self.runner is not None:
            await self.runner.cleanup()
