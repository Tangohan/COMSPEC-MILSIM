<?php
/**
 * Signature système standardisée pour tous les emails
 */
function getEmailSignature(): string {
    return '
        <p style="margin: 20px 0 0 0; font-size: 14px; color: #222222;">
            Cordialement,<br>
            <strong>Votre brigade connectée. - SI-Brigade</strong>
        </p>
    ';
}

