import os
import tempfile
import unittest
from unittest.mock import patch, Mock, AsyncMock
from types import SimpleNamespace
from config import Config
from i18n import TEXT
from state import State

class ConfigTests(unittest.TestCase):
    def test_https_base_path_preserved_and_secrets_hidden(self):
        with patch.dict(os.environ, {'DISCORD_TOKEN':'secret-token', 'ATHENA_COMPANION_API_KEY':'x'*64, 'ATHENA_SITE_URL':'https://example.test/public/'}, clear=True):
            config = Config.load()
        self.assertEqual(config.site_url, 'https://example.test/public')
        self.assertNotIn('secret-token', repr(config))
        self.assertNotIn('x'*64, repr(config))

    def test_insecure_or_credential_bearing_urls_rejected(self):
        for site in ['http://example.test', 'https://user:password@example.test', 'https://example.test/?key=secret']:
            with self.subTest(site=site), patch.dict(os.environ, {'DISCORD_TOKEN':'token', 'ATHENA_COMPANION_API_KEY':'x'*64, 'ATHENA_SITE_URL':site}, clear=True):
                with self.assertRaises(ValueError):
                    Config.load()

    def test_french_and_english_cover_same_messages(self):
        self.assertEqual(TEXT['fr'].keys(), TEXT['en'].keys())
        self.assertTrue(all(TEXT['fr'].values()))
        self.assertTrue(all(TEXT['en'].values()))

class StateTests(unittest.TestCase):
    def test_delivery_survives_restart_and_reaction_message_maps_to_event(self):
        with tempfile.TemporaryDirectory() as directory:
            path = directory + '/state.sqlite'
            state = State(path)
            state.save(12, '123', '456', '789', 'digest')
            state.db.close()
            state = State(path)
            self.assertEqual(state.get(12)['message_id'], '789')
            self.assertEqual(state.event_for_message(789), 12)
            self.assertIsNone(state.event_for_message(100))
            state.db.close()

class BotTests(unittest.IsolatedAsyncioTestCase):
    async def test_publication_ack_is_retried_without_sending_twice(self):
        import discord
        from main import Companion
        with tempfile.TemporaryDirectory() as directory:
            bot = Companion(Config('not-used', 'x'*64, 'https://example.test', directory + '/state.sqlite'))
            bot._connection.user = SimpleNamespace(id=7)
            message = SimpleNamespace(id=789, author=SimpleNamespace(id=7), reactions=[], edit=AsyncMock())
            channel = Mock(spec=discord.TextChannel)
            channel.id = 456
            channel.send = AsyncMock(return_value=message)
            channel.fetch_message = AsyncMock(return_value=message)
            async def history(limit):
                for item in []:
                    yield item
            channel.history = history
            guild = SimpleNamespace(id=123, get_channel=lambda _: channel)
            bot.get_guild = lambda _: guild
            bot.api = SimpleNamespace(call=AsyncMock(return_value={'ok':True}))
            job = {'locale':'en', 'event_id':12, 'title':'Training', 'starts_at':'2026-11-06 21:00:00', 'enabled':1, 'rsvp_enabled':0, 'guild_id':'123', 'default_channel_id':'456'}
            await bot.publish(job)
            await bot.publish(job)
            channel.send.assert_awaited_once()
            message.edit.assert_not_awaited()
            self.assertEqual(bot.api.call.await_count, 2)
            self.assertEqual(bot.state.get(12)['message_id'], '789')
            await bot.close()

    async def test_gateway_snapshot_excludes_bots_and_preserves_all_three_choices(self):
        import discord
        from main import Companion
        with tempfile.TemporaryDirectory() as directory:
            bot = Companion(Config('not-used', 'x'*64, 'https://example.test', directory + '/state.sqlite'))
            bot.state.save(12, '123', '456', '789', 'digest')
            bot.jobs[12] = {'enabled':1, 'rsvp_enabled':1}
            class Reaction:
                def __init__(self, emoji, users):
                    self.emoji, self.people, self.count = emoji, users, len(users)
                async def users(self, limit):
                    for person in self.people:
                        yield person
            message = SimpleNamespace(reactions=[
                Reaction('✅', [SimpleNamespace(id=1, bot=True), SimpleNamespace(id=2, bot=False)]),
                Reaction('❌', [SimpleNamespace(id=3, bot=False)]),
                Reaction('❓', [SimpleNamespace(id=4, bot=False)]),
            ])
            channel = Mock(spec=discord.TextChannel)
            channel.fetch_message = AsyncMock(return_value=message)
            bot.get_guild = lambda _: SimpleNamespace(get_channel=lambda _:channel)
            bot.api = SimpleNamespace(call=AsyncMock(return_value={'ok':True}))
            await bot.snapshot(12)
            self.assertEqual(bot.api.call.call_args.kwargs['reactions'], {'yes':['2'], 'no':['3'], 'maybe':['4']})
            await bot.close()

    async def test_registration_and_embeds_without_connecting_to_discord(self):
        from main import Companion
        with tempfile.TemporaryDirectory() as directory:
            bot = Companion(Config('not-used', 'x'*64, 'https://example.test/public', directory + '/state.sqlite'))
            command = bot.tree.get_command('athena').get_command('connect')
            self.assertTrue(command.guild_only)
            self.assertTrue(command.default_permissions.manage_guild)
            self.assertTrue(command.checks)
            self.assertFalse(bot.intents.message_content)
            self.assertFalse(bot.intents.members)
            job = {'locale':'fr', 'event_id':12, 'title':'Training', 'starts_at':'2026-11-06 21:00:00', 'enabled':1, 'rsvp_enabled':1}
            embed = bot.embed(job)
            self.assertEqual(embed.footer.text, 'ATHENA:12')
            self.assertEqual(embed.url, 'https://example.test/public/evenements#event-12')
            self.assertTrue(any('Présent' in field.name for field in embed.fields))
            job['locale'] = 'en'
            self.assertTrue(any('Attending' in field.name for field in bot.embed(job).fields))
            await bot.close()

if __name__ == '__main__':
    unittest.main()
