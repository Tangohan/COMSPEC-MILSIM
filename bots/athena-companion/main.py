"""ATHENA Companion — official multi-server bot. No privileged Discord intents."""
import asyncio
import hashlib
import json
import logging
import time
import aiohttp
import discord
from discord import app_commands
from discord.ext import tasks
from api import AthenaAPI, ApiError
from config import Config
from i18n import tr
from state import State

EMOJIS = {'yes': '✅', 'no': '❌', 'maybe': '❓'}
log = logging.getLogger('athena-companion')

class Companion(discord.Client):
    def __init__(self, config):
        intents = discord.Intents.none()
        intents.guilds = True
        intents.guild_reactions = True
        super().__init__(intents=intents, allowed_mentions=discord.AllowedMentions.none())
        self.config = config
        self.tree = app_commands.CommandTree(self)
        self.state = State(config.state_path)
        self.jobs = {}
        self.snapshot_at = {}
        self.pending = {}
        self.guild_sync_at = 0
        self.group = app_commands.Group(name='athena', description='ATHENA Companion · événements / events')
        self.group.add_command(app_commands.Command(name='connect', description='Relier Athena avec un code temporaire / Connect with a temporary code', callback=self.connect))
        self.group.add_command(app_commands.Command(name='help', description='Aide ATHENA Companion / ATHENA Companion help', callback=self.help_command))
        self.tree.add_command(self.group)
        self.tree.on_error = self.command_error

    async def setup_hook(self):
        self.http_session = aiohttp.ClientSession()
        self.api = AthenaAPI(self.config, self.http_session)
        await self.tree.sync()
        self.poll.start()

    async def close(self):
        self.poll.cancel()
        for task in self.pending.values():
            task.cancel()
        if hasattr(self, 'http_session'):
            await self.http_session.close()
        self.state.db.close()
        await super().close()

    def channels(self, guild):
        return {str(c.id): c.name for c in guild.text_channels if c.permissions_for(guild.me).view_channel}

    @app_commands.guild_only()
    @app_commands.default_permissions(manage_guild=True)
    @app_commands.checks.has_permissions(manage_guild=True)
    async def connect(self, interaction: discord.Interaction, code: str, channel: discord.TextChannel):
        await interaction.response.defer(ephemeral=True)
        if interaction.guild is None or channel.guild.id != interaction.guild.id:
            return await interaction.followup.send(tr('guild_only', interaction.locale), ephemeral=True)
        permissions = channel.permissions_for(interaction.guild.me)
        if not all((permissions.view_channel, permissions.send_messages, permissions.embed_links, permissions.read_message_history, permissions.add_reactions)):
            return await interaction.followup.send(tr('permissions', interaction.locale), ephemeral=True)
        try:
            await self.api.call('pair', code=code, guild_id=str(interaction.guild.id), guild_name=interaction.guild.name,
                                channel_id=str(channel.id), channels=self.channels(interaction.guild))
            await interaction.followup.send(tr('linked', interaction.locale), ephemeral=True)
        except ApiError as exc:
            key = {'companion.invalid_code':'invalid','companion.already_bound':'bound'}.get(str(exc),'failed')
            await interaction.followup.send(tr(key, interaction.locale), ephemeral=True)

    async def help_command(self, interaction: discord.Interaction):
        await interaction.response.send_message(tr('help', interaction.locale) + '\n' + self.config.site_url + '/discord/roles-lies', ephemeral=True)

    async def command_error(self, interaction, error):
        key = 'admin_only' if isinstance(error, app_commands.MissingPermissions) else 'failed'
        send = interaction.followup.send if interaction.response.is_done() else interaction.response.send_message
        await send(tr(key, interaction.locale), ephemeral=True)
        log.warning('Command failed: %s', type(error).__name__)

    def embed(self, job):
        locale = job['locale']
        cancelled = bool(job.get('cancelled_at'))
        disabled = not int(job['enabled'])
        title = tr('cancelled', locale) if cancelled else tr('disabled', locale) if disabled else job['title']
        embed = discord.Embed(title=str(title)[:256], description=str(job.get('description') or '')[:1800],
                              color=0xB42318 if cancelled else 0x0B8A5C,
                              url=self.config.site_url + '/evenements#event-' + str(job['event_id']))
        embed.add_field(name=tr('start', locale), value=str(job['starts_at']), inline=True)
        if job.get('location'):
            embed.add_field(name=tr('location', locale), value=str(job['location'])[:1024], inline=True)
        if int(job['rsvp_enabled']) and not cancelled and not disabled:
            embed.add_field(name=tr('vote', locale).split('\n')[0], value=tr('vote', locale).split('\n',1)[1], inline=False)
            embed.add_field(name=tr('link', locale), value=self.config.site_url + '/discord/roles-lies', inline=False)
        embed.set_footer(text='ATHENA:' + str(job['event_id']))
        return embed

    async def publish(self, job):
        event = int(job['event_id'])
        guild = self.get_guild(int(job['guild_id']))
        if guild is None:
            return
        saved = self.state.get(event)
        if saved and saved['guild_id'] != str(job['guild_id']):
            saved = None
        message_id = job.get('message_id') or (saved or {}).get('message_id')
        channel_id = job.get('channel_id') or (saved or {}).get('channel_id') or job['default_channel_id']
        channel = guild.get_channel(int(channel_id))
        if not isinstance(channel, discord.TextChannel):
            return
        if not message_id and (job.get('cancelled_at') or not int(job['enabled'])):
            return
        embed = self.embed(job)
        digest = hashlib.sha256(json.dumps(embed.to_dict(), sort_keys=True).encode()).hexdigest()
        if message_id:
            message = await channel.fetch_message(int(message_id))
            if message.author.id != self.user.id:
                raise ValueError('Message does not belong to Companion')
            if not saved or saved['digest'] != digest:
                await message.edit(embed=embed)
        else:
            # Recover an acknowledgement lost after sending, without reposting the announcement.
            message = None
            async for candidate in channel.history(limit=100):
                if candidate.author.id == self.user.id and any(e.footer.text == 'ATHENA:' + str(event) for e in candidate.embeds):
                    message = candidate
                    break
            if message is None:
                message = await channel.send(embed=embed)
        self.state.save(event, guild.id, channel.id, message.id, digest)
        await self.api.call('published', event_id=event, guild_id=str(guild.id), channel_id=str(channel.id), message_id=str(message.id))
        if int(job['rsvp_enabled']) and int(job['enabled']) and not job.get('cancelled_at'):
            for emoji in EMOJIS.values():
                if not any(str(r.emoji) == emoji and r.me for r in message.reactions):
                    await message.add_reaction(emoji)
            if time.monotonic() - self.snapshot_at.get(event, 0) > 300:
                await self.snapshot(event, message)

    async def snapshot(self, event, message=None):
        saved = self.state.get(event)
        job = self.jobs.get(event)
        if not saved or not job or not int(job['enabled']) or not int(job['rsvp_enabled']) or job.get('cancelled_at'):
            return
        guild = self.get_guild(int(saved['guild_id']))
        if guild is None:
            return
        channel = guild.get_channel(int(saved['channel_id']))
        if channel is None:
            return
        message = await channel.fetch_message(int(saved['message_id']))
        reactions = {status: [] for status in EMOJIS}
        for reaction in message.reactions:
            status = next((s for s,e in EMOJIS.items() if str(reaction.emoji) == e), None)
            if status is None:
                continue
            if reaction.count > 10000:
                raise ValueError('Reaction snapshot exceeds limit')
            seen = 0
            async for user in reaction.users(limit=10001):
                seen += 1
                if seen > 10000:
                    raise ValueError('Reaction snapshot exceeds limit')
                if not user.bot:
                    reactions[status].append(str(user.id))
                if len(reactions[status]) > 10000:
                    raise ValueError('Reaction snapshot exceeds limit')
        await self.api.call('reactions', event_id=event, guild_id=saved['guild_id'], channel_id=saved['channel_id'], message_id=saved['message_id'], reactions=reactions)
        self.snapshot_at[event] = time.monotonic()

    async def reaction_changed(self, payload):
        if str(payload.emoji) not in EMOJIS.values() or payload.user_id == self.user.id:
            return
        event = self.state.event_for_message(payload.message_id)
        if event is None:
            return
        task = self.pending.get(event)
        if task and not task.done():
            task.cancel()
        async def sync():
            try:
                await asyncio.sleep(1)
                await self.snapshot(event)
            except asyncio.CancelledError:
                pass
            except Exception as exc:
                log.warning('Reaction sync failed for event %s: %s', event, type(exc).__name__)
            finally:
                if self.pending.get(event) is asyncio.current_task():
                    self.pending.pop(event, None)
        self.pending[event] = asyncio.create_task(sync())

    async def on_guild_channel_update(self, before, after):
        if before.name != after.name:
            try:
                await self.api.call('channels', guild_id=str(after.guild.id), guild_name=after.guild.name, channels=self.channels(after.guild))
            except ApiError:
                log.warning('Channel name sync failed')

    async def on_raw_reaction_add(self, payload):
        await self.reaction_changed(payload)

    async def on_raw_reaction_remove(self, payload):
        await self.reaction_changed(payload)

    @tasks.loop(seconds=60)
    async def poll(self):
        try:
            data = await self.api.call('jobs')
            self.jobs = {int(j['event_id']):j for j in data['jobs']}
            for job in self.jobs.values():
                try:
                    await self.publish(job)
                except Exception as exc:
                    log.warning('Event %s failed: %s', job['event_id'], type(exc).__name__)
                    await self.api.call('failed', event_id=int(job['event_id']), guild_id=str(job['guild_id']))
            if time.monotonic() - self.guild_sync_at > 600:
                for guild in self.guilds:
                    await self.api.call('channels', guild_id=str(guild.id), guild_name=guild.name, channels=self.channels(guild))
                self.guild_sync_at = time.monotonic()
        except Exception as exc:
            log.warning('Companion poll failed: %s', type(exc).__name__)

    @poll.before_loop
    async def before_poll(self):
        await self.wait_until_ready()

if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO)
    configuration = Config.load()
    Companion(configuration).run(configuration.token, log_level=logging.WARNING)
