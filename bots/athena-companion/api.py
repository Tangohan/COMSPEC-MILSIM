import json
import aiohttp

class ApiError(Exception):
    pass

class AthenaAPI:
    def __init__(self, config, session):
        self.url = config.site_url + '/integrations/companion/v1'
        self.key = config.api_key
        self.session = session

    async def call(self, op, **payload):
        try:
            async with self.session.post(self.url, headers={'Authorization': 'Bearer ' + self.key},
                data={'op': op, 'payload': json.dumps(payload, ensure_ascii=False)},
                timeout=aiohttp.ClientTimeout(total=20), allow_redirects=False) as response:
                data = await response.json(content_type=None)
                if response.status != 200:
                    error = data.get('error', '') if isinstance(data, dict) else ''
                    raise ApiError(error if error in {'companion.invalid_code', 'companion.already_bound', 'unauthorized', 'not_found'} else 'request_failed')
                if not isinstance(data, dict):
                    raise ApiError('invalid_response')
                return data
        except (aiohttp.ClientError, TimeoutError, ValueError) as exc:
            raise ApiError('site_unavailable') from exc
