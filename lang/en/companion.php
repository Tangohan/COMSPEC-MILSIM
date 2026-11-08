<?php

declare(strict_types=1);

return [
    'title' => 'ATHENA Companion',
    'intro' => 'The official bot publishes events and syncs Discord RSVPs. Invite it, then connect your server with a temporary code.',
    'invite' => 'Invite ATHENA Companion',
    'generate' => 'Generate a pairing code (10 minutes)',
    'pair_code' => 'Code valid for 10 minutes: :code. On your Discord server, use /athena connect with this code and the publication channel.',
    'unlink' => 'Unlink server',
    'unlinked' => 'Server unlinked. The bot no longer receives your events.',
    'connected' => 'Connected server: :guild · :channel',
    'not_configured' => 'ATHENA Companion still needs to be configured by the platform administrator.',
    'api_key' => 'Companion service connection key',
    'api_help' => 'Use the same random key (at least 32 characters) in the bot service. The Discord token stays exclusively in that service.',
    'key_keep' => 'Leave blank to keep the key',
    'saved' => 'Companion settings saved.',
    'invalid_key' => 'The key must contain 32 to 128 letters, digits, hyphens or underscores.',
    'invalid_code' => 'The pairing code is invalid or expired.',
    'already_bound' => 'The server or community is already connected. Unlink it before changing servers.',
    'channel_choice' => 'ATHENA Companion — :channel',
];
