"""Runtime configuration; credentials are never logged or stored in Discord messages."""
import os
from dataclasses import dataclass
from urllib.parse import urlsplit

@dataclass(frozen=True, repr=False)
class Config:
    token: str
    api_key: str
    site_url: str
    state_path: str
    heartbeat_path: str = ''
    health_port: int = 0
    members_intent: bool = False

    @classmethod
    def load(cls):
        token = os.environ.get('DISCORD_TOKEN', '').strip()
        key = os.environ.get('ATHENA_COMPANION_API_KEY', '').strip()
        site = os.environ.get('ATHENA_SITE_URL', 'https://athena-comspec.eu').strip().rstrip('/')
        parsed = urlsplit(site)
        if not token or len(key) < 32:
            raise ValueError('DISCORD_TOKEN and an ATHENA_COMPANION_API_KEY of at least 32 characters are required')
        if parsed.scheme != 'https' or not parsed.hostname or parsed.username or parsed.password or parsed.query or parsed.fragment:
            raise ValueError('ATHENA_SITE_URL must be an HTTPS base URL, including /public when applicable')
        port = os.environ.get('ATHENA_COMPANION_HEALTH_PORT', '0').strip() or '0'
        if not port.isdigit() or int(port) > 65535:
            raise ValueError('ATHENA_COMPANION_HEALTH_PORT must be a port number (0 disables the local health endpoint)')
        return cls(token, key, site, os.environ.get('ATHENA_COMPANION_STATE', 'companion.sqlite3'),
                   os.environ.get('ATHENA_COMPANION_HEARTBEAT', '').strip(), int(port),
                   os.environ.get('ATHENA_COMPANION_MEMBERS_INTENT', '0').strip() == '1')
