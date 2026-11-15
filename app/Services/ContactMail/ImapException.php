<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/** Échec de dialogue IMAP. Le message ne contient jamais d'identifiant, de mot de passe ni de contenu de message. */
final class ImapException extends \RuntimeException {}
