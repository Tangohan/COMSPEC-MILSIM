import json
import os
import tempfile
import time
import unittest
from types import SimpleNamespace
from unittest.mock import AsyncMock, Mock, patch

import bureau
from config import Config
from i18n import TEXT, tr


class FormatTests(unittest.TestCase):
    def test_effectifs_shows_counts_and_n_a_when_the_site_cannot_count(self):
        result = bureau.format_effectifs({'active': 42, 'pending': 3, 'absent': None, 'applications': 5, 'integrations': 0, 'link': 'https://x.test/a'}, 'fr')
        values = dict(result['fields'])
        self.assertEqual(values['Membres actifs'], '42')
        self.assertEqual(values['En absence aujourd’hui'], 'n/d')
        self.assertEqual(result['url'], 'https://x.test/a')

    def test_member_card_never_carries_more_than_the_fields_the_site_sends(self):
        result = bureau.format_membre({'members': [{'name': 'Ghost', 'grade': 'Sergent', 'since': '2025-03-04', 'absent': True, 'email': 'no@x.test', 'real_name': 'Secret'}]}, 'en')
        text = json.dumps(result)
        self.assertIn('Ghost', text)
        self.assertIn('on leave', text)
        self.assertNotIn('no@x.test', text)
        self.assertNotIn('Secret', text)

    def test_applications_use_link_buttons_only_and_say_decisions_happen_on_the_site(self):
        items = [{'id': i, 'callsign': 'C%d' % i, 'status': 'submitted', 'date': '2026-11-01', 'link': 'https://x.test/r/%d' % i} for i in range(1, 9)]
        result = bureau.format_candidatures({'items': items, 'total': 8}, 'fr')
        self.assertEqual(len(result['buttons']), 5)
        self.assertTrue(all(url.startswith('https://') for _, url in result['buttons']))
        self.assertIn('uniquement sur le site', result['description'])

    def test_empty_states_and_unknown_error_code_stay_readable(self):
        self.assertIn('Aucune candidature', bureau.format_candidatures({'items': []}, 'fr')['description'])
        self.assertIn('No upcoming event', bureau.format_evenements({'items': []}, 'en')['description'])
        self.assertIn('indisponible', bureau.error_text('whatever', 'fr'))
        self.assertIn('https://x.test/l', bureau.error_text('link_required', 'en', 'https://x.test/l'))

    def test_every_error_code_the_site_returns_has_a_message_in_both_languages(self):
        for code in ('not_linked', 'link_required', 'not_member', 'forbidden', 'rate_limited'):
            for locale in ('fr', 'en'):
                self.assertNotIn('indisponible', bureau.error_text(code, locale))
                self.assertNotIn('unavailable', bureau.error_text(code, locale))

    def test_reminders_render_for_every_kind_and_slot(self):
        kinds = [
            ('applications_new', {'id': 4, 'callsign': 'Rook', 'link': 'https://x.test'}),
            ('integration_validate', {'count': 2, 'link': 'https://x.test'}),
            ('retro30', {'count': 1}), ('qualification_staff', {'count': 3}),
            ('qualification_due', {'name': 'JTAC', 'expires_at': '2026-12-01'}),
            ('campaign', {'slot': 'j14', 'title': 'Campagne 2026', 'due_on': '2026-12-01'}),
            ('campaign', {'slot': 'j3', 'title': 'Campagne 2026', 'due_on': '2026-12-01'}),
            ('campaign', {'slot': 'j0', 'title': 'Campagne 2026', 'due_on': '2026-12-01'}),
            ('test', {}),
            ('remontee', {'event': 'urgent', 'ref': 'FR-2026-000041', 'type': 'FRO', 'urgency': 'high', 'emitter': 'VIPER', 'link': 'https://x.test'}),
            ('remontee', {'event': 'late', 'ref': 'CR-12', 'type': 'SPOTREP', 'urgency': 'critical'}),
        ]
        for kind, data in kinds:
            for locale in ('fr', 'en'):
                title, body, _ = bureau.format_reminder({'kind': kind, 'data': data}, locale)
                self.assertTrue(title and body)
                self.assertNotIn(':count', body)
                self.assertNotIn(':title', body)
        self.assertIsNone(bureau.format_reminder({'kind': 'unknown', 'data': {}}, 'fr'))

    def test_report_reminder_carries_no_text_and_mentions_only_a_numeric_role(self):
        item = {'kind': 'remontee', 'data': {'event': 'new', 'ref': 'FR-1', 'type': 'FRT', 'urgency': 'low', 'body': 'SECRET', 'role': '123456789012345678'}}
        title, body, _ = bureau.format_reminder(item, 'fr')
        self.assertEqual(title, 'Nouvelle remontée')
        self.assertNotIn('SECRET', body)
        self.assertNotIn(':urgency', body)
        self.assertEqual(bureau.reminder_mention(item), '123456789012345678')
        self.assertEqual(bureau.reminder_mention({'kind': 'remontee', 'data': {'role': '@everyone'}}), '')
        self.assertEqual(bureau.reminder_mention({'kind': 'applications_new', 'data': {'role': '123456789012345678'}}), '')

    def test_translations_stay_complete_and_substitution_leaves_other_text_alone(self):
        self.assertEqual(TEXT['fr'].keys(), TEXT['en'].keys())
        self.assertEqual(tr('welcome', 'en', name='Ann', link='L'), 'Welcome Ann! To get your tools, link your Athena account: L')
        self.assertEqual(tr('invalid', 'fr'), TEXT['fr']['invalid'])


class RolePlanTests(unittest.TestCase):
    def test_roles_follow_the_site_level_and_only_touch_the_two_managed_roles(self):
        self.assertEqual(bureau.plan_roles('member', ['1', '9'], '10', '20'), (['10'], []))
        self.assertEqual(bureau.plan_roles('manager', [1], '10', '20'), (['10', '20'], []))
        self.assertEqual(bureau.plan_roles('member', ['10', '20', '99'], '10', '20'), ([], ['20']))
        self.assertEqual(bureau.plan_roles('none', ['10', '20', '99'], '10', '20'), ([], ['10', '20']))
        self.assertEqual(bureau.plan_roles('member', ['99'], '', '20'), ([], []))

    def test_an_unknown_level_grants_nothing(self):
        self.assertEqual(bureau.plan_roles('owner', [], '10', '20'), ([], []))
        self.assertEqual(bureau.plan_roles(None, ['10'], '10', '20'), ([], ['10']))

    def test_roles_that_manage_the_server_are_refused(self):
        self.assertTrue(bureau.dangerous_role(SimpleNamespace(administrator=True)))
        self.assertTrue(bureau.dangerous_role(SimpleNamespace(manage_roles=True)))
        self.assertTrue(bureau.dangerous_role(SimpleNamespace(ban_members=True)))
        self.assertFalse(bureau.dangerous_role(SimpleNamespace(send_messages=True, view_channel=True)))


class ConfigTests(unittest.TestCase):
    def load(self, **extra):
        env = {'DISCORD_TOKEN': 'tok', 'ATHENA_COMPANION_API_KEY': 'k' * 40, 'ATHENA_SITE_URL': 'https://example.test'}
        env.update(extra)
        with patch.dict(os.environ, env, clear=True):
            return Config.load()

    def test_new_options_default_to_off_and_privileged_intent_needs_an_explicit_opt_in(self):
        config = self.load()
        self.assertEqual((config.heartbeat_path, config.health_port, config.members_intent), ('', 0, False))
        config = self.load(ATHENA_COMPANION_HEARTBEAT='/run/x/hb', ATHENA_COMPANION_HEALTH_PORT='8765', ATHENA_COMPANION_MEMBERS_INTENT='1')
        self.assertEqual((config.heartbeat_path, config.health_port, config.members_intent), ('/run/x/hb', 8765, True))

    def test_bad_health_port_is_refused(self):
        for port in ('abc', '70000', '-1'):
            with self.subTest(port=port), self.assertRaises(ValueError):
                self.load(ATHENA_COMPANION_HEALTH_PORT=port)


class BotTests(unittest.IsolatedAsyncioTestCase):
    def make(self, directory, **config):
        from main import Companion
        return Companion(Config('not-used', 'x' * 64, 'https://example.test', directory + '/state.sqlite', **config))

    async def test_commands_are_registered_with_the_minimum_intents(self):
        with tempfile.TemporaryDirectory() as directory:
            bot = self.make(directory)
            for name in ('effectifs', 'membre', 'candidatures', 'evenements', 'integration'):
                self.assertIsNotNone(bot.tree.get_command(name), name)
                self.assertTrue(bot.tree.get_command(name).guild_only, name)
            self.assertFalse(bot.intents.message_content)
            self.assertFalse(bot.intents.members)
            self.assertFalse(bot.intents.presences)
            await bot.close()
            with_members = self.make(directory, members_intent=True)
            self.assertTrue(with_members.intents.members)
            await with_members.close()

    async def test_command_answers_privately_and_shows_the_site_refusal_without_details(self):
        with tempfile.TemporaryDirectory() as directory:
            bot = self.make(directory)
            bot.api = SimpleNamespace(call=AsyncMock(return_value={'ok': False, 'error': 'forbidden'}))
            interaction = SimpleNamespace(
                locale='fr', user=SimpleNamespace(id=5), guild=SimpleNamespace(id=7),
                response=SimpleNamespace(defer=AsyncMock(), send_message=AsyncMock()), followup=SimpleNamespace(send=AsyncMock()))
            await bot.bureau(interaction, 'effectifs')
            interaction.response.defer.assert_awaited_once_with(ephemeral=True)
            sent = interaction.followup.send.await_args
            self.assertTrue(sent.kwargs['ephemeral'])
            self.assertIn('droit', sent.args[0])
            call = bot.api.call.await_args
            self.assertEqual((call.args[0], call.kwargs['guild_id'], call.kwargs['user_id'], call.kwargs['command']), ('cmd', '7', '5', 'effectifs'))
            await bot.close()

    async def test_second_command_within_three_seconds_is_refused_locally(self):
        with tempfile.TemporaryDirectory() as directory:
            bot = self.make(directory)
            bot.api = SimpleNamespace(call=AsyncMock(return_value={'ok': True, 'data': {'active': 1}}))
            def interaction():
                return SimpleNamespace(locale='en', user=SimpleNamespace(id=5), guild=SimpleNamespace(id=7),
                                       response=SimpleNamespace(defer=AsyncMock(), send_message=AsyncMock()), followup=SimpleNamespace(send=AsyncMock()))
            first, second = interaction(), interaction()
            await bot.bureau(first, 'effectifs')
            await bot.bureau(second, 'effectifs')
            self.assertEqual(bot.api.call.await_count, 1)
            second.response.send_message.assert_awaited_once()
            self.assertTrue(first.followup.send.await_args.kwargs['ephemeral'])
            await bot.close()

    async def test_applications_reply_carries_link_buttons_not_actions(self):
        import discord
        with tempfile.TemporaryDirectory() as directory:
            bot = self.make(directory)
            data = {'ok': True, 'data': {'items': [{'id': 3, 'callsign': 'Rook', 'status': 'submitted', 'date': '2026-11-02', 'link': 'https://example.test/back-office/recruitments/3'}], 'total': 1}}
            bot.api = SimpleNamespace(call=AsyncMock(return_value=data))
            interaction = SimpleNamespace(locale='en', user=SimpleNamespace(id=6), guild=SimpleNamespace(id=7),
                                          response=SimpleNamespace(defer=AsyncMock()), followup=SimpleNamespace(send=AsyncMock()))
            await bot.bureau(interaction, 'candidatures')
            view = interaction.followup.send.await_args.kwargs['view']
            buttons = list(view.children)
            self.assertEqual(len(buttons), 1)
            self.assertEqual(buttons[0].style, discord.ButtonStyle.link)
            self.assertEqual(buttons[0].url, 'https://example.test/back-office/recruitments/3')
            await bot.close()

    async def test_reminder_is_acknowledged_after_delivery_and_dm_refusal_is_final(self):
        import discord
        with tempfile.TemporaryDirectory() as directory:
            bot = self.make(directory)
            channel = Mock(spec=discord.TextChannel)
            channel.permissions_for = lambda _: SimpleNamespace(send_messages=True)
            channel.send = AsyncMock()
            guild = SimpleNamespace(id=7, me=object(), preferred_locale='fr', get_channel=lambda _: channel)
            reminders = {'reminders': [
                {'kind': 'applications_new', 'ref': '4', 'target': 'channel', 'to': '55', 'data': {'id': 4, 'callsign': 'Rook', 'link': 'https://example.test'}},
                {'kind': 'qualification_due', 'ref': '9:2026-12-01', 'target': 'dm', 'to': '66', 'data': {'name': 'JTAC', 'expires_at': '2026-12-01'}},
            ]}
            bot.api = SimpleNamespace(call=AsyncMock(side_effect=lambda op, **kw: reminders if op == 'reminders' else {'ok': True}))
            closed = AsyncMock(side_effect=discord.Forbidden(SimpleNamespace(status=403, reason='x'), 'closed'))
            bot.fetch_user = AsyncMock(return_value=SimpleNamespace(send=closed))
            with patch('main.asyncio.sleep', new=AsyncMock()):
                await bot.remind_guild(guild)
            channel.send.assert_awaited_once()
            acks = [c for c in bot.api.call.await_args_list if c.args[0] == 'reminder_ack']
            self.assertEqual([(c.kwargs['kind'], c.kwargs['ref']) for c in acks], [('applications_new', '4'), ('qualification_due', '9:2026-12-01')])
            await bot.close()

    async def test_reminder_to_a_missing_channel_is_not_acknowledged(self):
        with tempfile.TemporaryDirectory() as directory:
            bot = self.make(directory)
            guild = SimpleNamespace(id=7, me=object(), preferred_locale='en', get_channel=lambda _: None)
            bot.api = SimpleNamespace(call=AsyncMock(side_effect=lambda op, **kw: {'reminders': [{'kind': 'retro30', 'ref': '2026-W46', 'target': 'channel', 'to': '55', 'data': {'count': 2}}]} if op == 'reminders' else {'ok': True}))
            with patch('main.asyncio.sleep', new=AsyncMock()):
                await bot.remind_guild(guild)
            self.assertFalse([c for c in bot.api.call.await_args_list if c.args[0] == 'reminder_ack'])
            await bot.close()

    async def test_role_sync_changes_only_managed_roles_and_never_without_the_permission(self):
        with tempfile.TemporaryDirectory() as directory:
            bot = self.make(directory)
            bot.api = SimpleNamespace(call=AsyncMock(return_value={'enabled': True, 'member_role_id': '10', 'manager_role_id': '', 'users': [{'discord_user_id': '1', 'level': 'member'}]}))
            member = SimpleNamespace(roles=[SimpleNamespace(id=99)], add_roles=AsyncMock(), remove_roles=AsyncMock())
            role = SimpleNamespace(id=10, managed=False, permissions=SimpleNamespace(send_messages=True), is_default=lambda: False)
            role.__ge__ = lambda self, other: False
            top = object()
            guild = SimpleNamespace(id=7, me=SimpleNamespace(guild_permissions=SimpleNamespace(manage_roles=False), top_role=top),
                                    get_role=lambda i: role, get_member=lambda i: member, fetch_member=AsyncMock(return_value=member))
            await bot.sync_roles_guild(guild)
            bot.api.call.assert_not_awaited()
            member.add_roles.assert_not_awaited()
            await bot.close()

    async def test_heartbeat_file_is_written_atomically_and_holds_no_secret(self):
        with tempfile.TemporaryDirectory() as directory:
            path = directory + '/heartbeat'
            bot = self.make(directory, heartbeat_path=path)
            bot.poll_ok_at = time.time()
            await bot.beat()
            data = json.loads(open(path, encoding='utf-8').read())
            self.assertEqual(set(data), {'at', 'guilds', 'poll_ok_at'})
            self.assertFalse(os.path.exists(path + '.tmp'))
            await bot.close()


class HealthTests(unittest.TestCase):
    def test_snapshot_is_healthy_only_when_connected_and_polling_recently(self):
        from health import Health
        client = SimpleNamespace(is_ready=lambda: True, guilds=[1, 2], poll_ok_at=1000.0)
        health = Health(client, 8765)
        self.assertEqual(health.snapshot(now=1100.0)['status'], 'ok')
        self.assertEqual(health.snapshot(now=1500.0)['status'], 'degraded')
        client.poll_ok_at = 0.0
        self.assertEqual(health.snapshot(now=1100.0)['status'], 'degraded')
        client.is_ready = lambda: False
        client.poll_ok_at = 1000.0
        self.assertEqual(health.snapshot(now=1010.0)['status'], 'degraded')


if __name__ == '__main__':
    unittest.main()
