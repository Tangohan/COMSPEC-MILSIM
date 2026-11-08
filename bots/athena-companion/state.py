import sqlite3

class State:
    def __init__(self, path):
        self.db = sqlite3.connect(path)
        self.db.execute('CREATE TABLE IF NOT EXISTS deliveries (event_id INTEGER PRIMARY KEY, guild_id TEXT, channel_id TEXT, message_id TEXT, digest TEXT)')
        self.db.commit()

    def get(self, event):
        row = self.db.execute('SELECT guild_id, channel_id, message_id, digest FROM deliveries WHERE event_id=?', (event,)).fetchone()
        return dict(zip(('guild_id','channel_id','message_id','digest'), row)) if row else None

    def event_for_message(self, message):
        row = self.db.execute('SELECT event_id FROM deliveries WHERE message_id=?', (str(message),)).fetchone()
        return row[0] if row else None

    def save(self, event, guild, channel, message, digest):
        self.db.execute('INSERT OR REPLACE INTO deliveries VALUES (?,?,?,?,?)', (event, str(guild), str(channel), str(message), digest))
        self.db.commit()
