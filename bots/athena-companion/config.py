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
        return cls(token, key, site, os.environ.get('ATHENA_COMPANION_STATE', 'companion.sqlite3'))
