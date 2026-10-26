<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * SEC-001 — anti-régression du cloisonnement d'hôte de la DLL COMSPEC Link.
 *
 * La DLL ajoute les secrets Athena (clé communauté X-COMSPEC-KEY, Authorization: Bearer, X-COMSPEC-SESSION,
 * X-COMSPEC-STEAM, et api_key / session_token dans les corps) à ses requêtes. L'adresse du portail (_baseUrl),
 * les URL de la file d'envoi reprise du disque et les URL d'image viennent de l'extérieur : sans contrôle,
 * un mod/mission hostile, une variable publique d'objet ou un fichier de file modifié détourne ces secrets.
 *
 * Test purement statique (lecture des sources C#, comme AtakLinkSelfTestAssetTest ; la CI ne lance que PHPUnit).
 * Les corps de méthode sont découpés par comptage d'accolades (pas par fenêtre de caractères) : un commentaire
 * ajouté ne le fait ni passer ni échouer à tort. Il échoue si :
 *   - AttachApiKeyHeader n'écarte plus les destinations refusées, ou n'en sort pas par `return;` ;
 *   - une affectation `_baseUrl =` (ou `ref _baseUrl`, ou `out _baseUrl`) contourne TrySetSqfBaseUrl ;
 *   - le prédicat unique IsSecretSafeUri perd le contrôle d'hôte, l'exigence https, le refus des identifiants
 *     d'URL, ou fait confiance à la boucle locale hors réglage de développement ;
 *   - la file d'envoi reprise du disque peut repartir vers un hôte non-Athena avec les secrets du corps ;
 *   - les redirections automatiques reviennent sur un client qui porte les secrets ;
 *   - le code d'erreur untrusted_host n'est plus remonté ni affiché dans le téléphone.
 */
final class AtakLinkTrustedHostAssetTest extends TestCase
{
    private function linkDir(): string
    {
        return dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension';
    }

    private function nativeDir(): string
    {
        return dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension';
    }

    /** Tous les .cs compilés dans les deux DLL (projet Link + sources propres au projet natif). */
    private function dllSourceFiles(): array
    {
        $files = array_merge(
            glob($this->linkDir() . '/*.cs') ?: [],
            glob($this->nativeDir() . '/*.cs') ?: []
        );
        self::assertGreaterThanOrEqual(20, count($files), 'Sources C# de la DLL introuvables');

        return $files;
    }

    private function read(string $file): string
    {
        $src = file_get_contents($file);
        self::assertNotFalse($src, 'Source illisible : ' . $file);

        return (string) $src;
    }

    /**
     * Bloc { ... } qui suit $needle, accolades comptées en ignorant commentaires, chaînes et caractères.
     * Renvoie le bloc, accolades comprises.
     */
    private static function blockAfter(string $src, string $needle): string
    {
        $at = strpos($src, $needle);
        self::assertNotFalse($at, 'Introuvable dans les sources : ' . $needle);
        $i = strpos($src, '{', $at);
        self::assertNotFalse($i, 'Pas de bloc après : ' . $needle);

        $len = strlen($src);
        $depth = 0;
        $start = (int) $i;
        for ($i = $start; $i < $len; $i++) {
            $c = $src[$i];
            $next = $i + 1 < $len ? $src[$i + 1] : '';
            if ($c === '/' && $next === '/') {
                $eol = strpos($src, "\n", $i);
                $i = $eol === false ? $len : (int) $eol;
                continue;
            }
            if ($c === '/' && $next === '*') {
                $end = strpos($src, '*/', $i);
                $i = $end === false ? $len : (int) $end + 1;
                continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;
                $verbatim = $i > 0 && $src[$i - 1] === '@';
                for ($i++; $i < $len; $i++) {
                    if (!$verbatim && $src[$i] === '\\') {
                        $i++;
                        continue;
                    }
                    if ($src[$i] === $quote) {
                        if ($verbatim && ($i + 1 < $len) && $src[$i + 1] === $quote) {
                            $i++;
                            continue;
                        }
                        break;
                    }
                }
                continue;
            }
            if ($c === '{') {
                $depth++;
                continue;
            }
            if ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $start, $i - $start + 1);
                }
            }
        }
        self::fail('Bloc non fermé après : ' . $needle);
    }

    public function testAttachApiKeyHeaderRefusesEveryUnsafeDestination(): void
    {
        $ext = $this->read($this->linkDir() . '/Extension.cs');
        $body = self::blockAfter($ext, 'private static void AttachApiKeyHeader(HttpRequestMessage req)');

        $guardCond = 'if (!IsSecretSafeUri(req.RequestUri))';
        self::assertStringContainsString($guardCond, $body,
            'AttachApiKeyHeader doit écarter toute destination refusée par IsSecretSafeUri');

        // Le filtre doit SORTIR de la méthode : un garde sans return laisserait passer les secrets juste après.
        $guardBlock = self::blockAfter($body, $guardCond);
        self::assertStringContainsString('return;', $guardBlock,
            'Le filtre d\'hôte doit sortir par return; avant tout ajout de secret');

        // ... et précéder le premier secret posé.
        $guardAt = (int) strpos($body, $guardCond);
        foreach (['"Authorization"', '"X-COMSPEC-KEY"', '"X-COMSPEC-SESSION"', '"X-COMSPEC-STEAM"'] as $header) {
            $headerAt = strpos($body, $header);
            self::assertNotFalse($headerAt, 'En-tête attendu dans AttachApiKeyHeader : ' . $header);
            self::assertLessThan($headerAt, $guardAt, 'Le contrôle doit précéder l\'ajout de ' . $header);
        }
    }

    public function testSingleSecretSafeUriPredicateAndNarrowAllowlist(): void
    {
        $ext = $this->read($this->linkDir() . '/Extension.cs');

        self::assertSame(1, substr_count($ext, 'private static bool IsTrustedPortalHost('),
            'Une seule fonction de confiance d\'hôte');
        self::assertSame(2, substr_count($ext, 'private static bool IsSecretSafeUri('),
            'Un seul prédicat IsSecretSafeUri (surcharge Uri + chaîne)');

        // Liste blanche : les deux portails réellement appelés par la DLL, sans joker de sous-domaine.
        $hosts = self::blockAfter($ext, 'private static bool IsTrustedPortalHost(string? host)');
        self::assertStringContainsString('"athena.ttrd.fr"', $hosts);
        self::assertStringContainsString('"athena-comspec.eu"', $hosts);
        self::assertStringNotContainsString('EndsWith(', $hosts,
            'Pas de joker de sous-domaine : un nouvel hôte Athena doit être listé explicitement');
        self::assertStringNotContainsString('StartsWith(', $hosts);
        // Boucle locale : seulement si le réglage de développement l'autorise (jamais en production).
        self::assertStringContainsString('return IsDevLoopbackHost(host);', $hosts);
        self::assertStringContainsString(
            'DevTrustLoopback && PortalHostKey(host) is "localhost" or "127.0.0.1" or "::1"', $ext);
        self::assertStringContainsString('Environment.GetEnvironmentVariable("COMSPEC_DEV_TRUST_LOOPBACK") == "1"', $ext);

        // Destination admise : hôte de confiance, pas d'identifiants d'URL, https (http seulement en boucle locale).
        $safeAt = strpos($ext, 'private static bool IsSecretSafeUri(Uri? u) =>');
        self::assertNotFalse($safeAt, 'IsSecretSafeUri(Uri?) introuvable');
        $safeBody = substr($ext, (int) $safeAt, (int) strpos($ext, ';', (int) $safeAt) - (int) $safeAt);
        self::assertStringContainsString('u.UserInfo.Length == 0', $safeBody,
            'Une URL avec identifiants (https://x@athena.ttrd.fr/) doit être refusée');
        self::assertStringContainsString('IsTrustedPortalHost(u.IdnHost)', $safeBody);
        self::assertStringContainsString('u.Scheme == Uri.UriSchemeHttps', $safeBody);
        self::assertStringContainsString('u.Scheme == Uri.UriSchemeHttp && IsDevLoopbackHost(u.IdnHost)', $safeBody,
            'http en clair admis seulement vers l\'Athena local de développement');
    }

    public function testEveryBaseUrlAssignmentGoesThroughTheCheckpoint(): void
    {
        $ext = $this->read($this->linkDir() . '/Extension.cs');

        self::assertSame(1, substr_count($ext, 'private static bool TrySetSqfBaseUrl('),
            'Un seul point de contrôle TrySetSqfBaseUrl');
        $checkpoint = self::blockAfter($ext,
            'private static bool TrySetSqfBaseUrl(string? candidate, out string normalized)');
        self::assertStringContainsString('if (!IsSecretSafeUri(normalized)) return false;', $checkpoint,
            'Le point de contrôle doit refuser une destination non sûre');
        self::assertStringContainsString('_baseUrl = normalized;', $checkpoint,
            'Le point de contrôle est le seul à poser _baseUrl');

        // Les trois seules formes d'affectation admises, chacune en un seul exemplaire.
        self::assertSame(1, substr_count($ext, '_baseUrl = normalized;'));
        self::assertSame(1, substr_count($ext, 'private static string _baseUrl = "";'));
        self::assertSame(1, substr_count($ext, '_baseUrl = NormalizeBaseUrl(_baseUrl);'),
            'Une seule re-normalisation d\'une valeur déjà contrôlée');

        $allowed = [
            'private static string _baseUrl = "";',    // déclaration
            '_baseUrl = normalized;',                  // dans TrySetSqfBaseUrl (valeur validée juste avant)
            '_baseUrl = NormalizeBaseUrl(_baseUrl);',  // re-normalisation d'une valeur déjà contrôlée
        ];
        foreach ($this->dllSourceFiles() as $file) {
            $src = $this->read($file);
            // Détournements qui n'écrivent pas « _baseUrl = » : Interlocked.Exchange(ref _baseUrl, …), TryX(out _baseUrl).
            self::assertStringNotContainsString('ref _baseUrl', $src,
                'ref _baseUrl contourne le point de contrôle (' . basename($file) . ')');
            self::assertStringNotContainsString('out _baseUrl', $src,
                'out _baseUrl contourne le point de contrôle (' . basename($file) . ')');
            foreach (explode("\n", $src) as $no => $line) {
                if (preg_match('/(?<![\w.])_baseUrl\s*(?:=[^=]|\+=|\?\?=)/', $line) !== 1) {
                    continue;
                }
                $trimmed = trim($line);
                self::assertContains(
                    $trimmed,
                    $allowed,
                    sprintf('Affectation _baseUrl hors point de contrôle (%s:%d) : %s',
                        basename($file), $no + 1, $trimmed)
                );
            }
        }

        // Commandes qui reçoivent une adresse et l'utilisent avant de la mémoriser (Redeem*, bêta, rapport).
        self::assertSame(4, substr_count($ext, 'if (!IsSecretSafeUri(baseUrl)) return "ERR|untrusted_host";'),
            'RedeemGameLink, LinkBySteam, RegisterBeta et ReportDiag contrôlent l\'adresse avant l\'envoi');
    }

    public function testDiskQueueNeverSendsBodySecretsToAnUntrustedHost(): void
    {
        $ext = $this->read($this->linkDir() . '/Extension.cs');

        // Un seul envoi de file (FIFO, marqueurs, position coalescée) : c'est lui qui porte le contrôle.
        self::assertSame(2, substr_count($ext, 'AddQueueSecrets('),
            'AddQueueSecrets : une déclaration et un seul appel (TrySendQueuedPost)');
        $send = self::blockAfter($ext, 'private static bool TrySendQueuedPost((string Url, string Body) item)');
        $guard = 'if (!IsSecretSafeUri(item.Url))';
        self::assertStringContainsString($guard, $send,
            'TrySendQueuedPost doit abandonner une URL de file non sûre (queue.jsonl / position.json modifiés)');
        $guardBlock = self::blockAfter($send, $guard);
        self::assertStringContainsString('JournalTrack(item.Url, item.Body, "failed"', $guardBlock,
            'L\'envoi abandonné doit être journalisé');
        self::assertStringContainsString('return true;', $guardBlock,
            'L\'envoi abandonné ne doit pas être remis en file');
        self::assertLessThan((int) strpos($send, 'AddQueueSecrets('), (int) strpos($send, $guard),
            'Le contrôle doit précéder l\'ajout des secrets au corps');

        // Position coalescée reprise du disque : contrôlée dès le rechargement, et comptée si elle est écartée.
        $load = self::blockAfter($ext, 'private static void LoadQueueFromDisk()');
        self::assertStringContainsString('IsSecretSafeUri(u)', $load,
            'La position reprise de position.json doit être contrôlée au rechargement');
        self::assertStringContainsString('Interlocked.Increment(ref _untrustedQueueDropped)', $load,
            'La position écartée au rechargement doit être comptée avec les autres abandons');

        // Signalement : libellé fixe dans le 2ᵉ champ, que fn_extensionCallback.sqf lit (« terrain » y arrête le
        // relevé, « weather » renvoie la météo, « video-feeds » suspend les flux) ; le chemin passe dans le détail.
        $note = self::blockAfter($ext, 'private static void NoteUntrustedQueueDrop(string url)');
        self::assertStringContainsString('InvokeCallback("PostError", "0|file-hors-athena|0|"', $note,
            'Abandon de file : le 2ᵉ champ du rappel PostError doit rester un libellé fixe');
    }

    public function testSecretBearingClientsDoNotFollowRedirectsAutomatically(): void
    {
        $ext = $this->read($this->linkDir() . '/Extension.cs');
        $photo = $this->read($this->linkDir() . '/Extension_MapPhoto.cs');

        // .NET garde les en-têtes personnalisés (X-COMSPEC-*) lors d'une redirection : suivi automatique coupé
        // par défaut sur les clients qui portent les secrets.
        self::assertStringContainsString(
            'private static HttpClient CreateHttpClient(int timeoutSeconds, bool followRedirects = false)', $ext);
        self::assertStringContainsString('AllowAutoRedirect = followRedirects', $ext);
        self::assertSame(0, substr_count($ext, 'AllowAutoRedirect = true'));

        // Seule exception : les tuiles publiques du fond PHOTO, qui ne portent jamais de secret.
        self::assertStringContainsString('CreateHttpClient(30, followRedirects: true)', $photo);
        self::assertDoesNotMatchRegularExpression('/AttachApiKeyHeader\s*\(/', $photo,
            'Le client des tuiles ne doit jamais attacher de secret');
        $followers = 0;
        foreach ($this->dllSourceFiles() as $file) {
            $followers += preg_match_all('/followRedirects:\s*true/', $this->read($file));
        }
        self::assertSame(1, $followers,
            'Un seul client suit les redirections : celui des tuiles publiques (sans secret)');

        // Images de briefing (URL potentiellement externe) : redirections suivies à la main, secrets refiltrés
        // à chaque saut, pas de rétrogradation https → http.
        self::assertStringContainsString('imgResp = SendImageGetFollowingRedirects(imageUrl, token);', $ext);
        $follow = self::blockAfter($ext,
            'private static HttpResponseMessage SendImageGetFollowingRedirects(string url, CancellationToken token)');
        self::assertStringContainsString('AttachApiKeyHeader(req);', $follow,
            'Chaque saut doit repasser par le filtre d\'hôte');
        self::assertStringContainsString('&& next.Scheme == Uri.UriSchemeHttp)', $follow,
            'Pas de rétrogradation https → http en suivant une redirection');
    }

    public function testUntrustedHostErrorReachesThePhoneWithoutPromisingAFallback(): void
    {
        $ext = $this->read($this->linkDir() . '/Extension.cs');
        self::assertStringContainsString('return "ERR|untrusted_host";', $ext,
            'Le refus d\'une destination non sûre remonte un code d\'erreur explicite');

        // Le SQF appelant principal (Connexion Athena) traduit le code en message affiché DANS le téléphone.
        $connect = $this->read(dirname(__DIR__, 2)
            . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_connect.sqf');
        $case = 'case "untrusted_host": {';
        self::assertStringContainsString($case, $connect,
            'fn_connect.sqf doit afficher un message compréhensible pour untrusted_host');
        $block = substr($connect, (int) strpos($connect, $case), 600);
        // Connect n'a aucun repli : ne pas promettre une adresse « utilisée automatiquement ».
        self::assertStringNotContainsString('automatiquement', $block);

        // Textes affichés traduits (anglais par défaut).
        $en = $this->read(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        self::assertStringContainsString('["Adresse Athena non autorisee", "', $en);
        self::assertStringContainsString('["Portail refuse par la DLL (protection des jetons)', $en);
    }

    public function testCommandsWithoutSecretsAlsoStayOnAthena(): void
    {
        $ext = $this->read($this->linkDir() . '/Extension.cs');
        // LatestVersions, StartupCheck et Server.PublicIp ne portent pas de secret mais prennent une adresse de
        // SQF : sans contrôle, un script les fait sonder le réseau local ou révéler l'adresse du joueur.
        foreach (['LatestVersions(string? portal)', 'StartupCheck(string?[] args)'] as $signature) {
            $body = self::blockAfter($ext, 'private static string ' . $signature);
            self::assertStringContainsString('if (!IsSecretSafeUri(uri)) return "ERR|untrusted_host";', $body,
                $signature . ' doit refuser un hôte non-Athena');
        }
        self::assertStringContainsString('if (!IsSecretSafeUri(ipUri)) return "ERR|untrusted_host";', $ext,
            'Server.PublicIp doit refuser un hôte non-Athena');
    }

    /**
     * Seuls AttachApiKeyHeader et les deux sites de GameAuth (Bearer du compte) posent un en-tête porteur de
     * secret, et ces deux sites refusent d'abord un hôte non sûr. Un en-tête secret ajouté ailleurs, même juste
     * après AttachApiKeyHeader, contournerait le filtre : ce recensement échoue alors. Hors de ces blocs, il
     * refuse aussi les formes qui n'écrivent pas le nom dans l'appel (revue E, itération 3, V3-2) : nom secret
     * écrit ailleurs que dans un Remove (constante, initialiseur), propriété Authorization,
     * AuthenticationHeaderValue, et Add / TryAddWithoutValidation d'en-têtes dont le nom n'est pas une seule
     * chaîne littérale (constante, variable, concaténation).
     */
    public function testSecretHeadersAreOnlySetBehindTheHostFilter(): void
    {
        $extFile = $this->linkDir() . '/Extension.cs';
        $guarded = [[$extFile, self::blockAfter($this->read($extFile), 'private static void AttachApiKeyHeader(HttpRequestMessage req)')]];
        $gameAuthFile = $this->linkDir() . '/GameAuth.cs';
        $gameAuth = $this->read($gameAuthFile);
        $guard = 'if (!IsSecretSafeUri(uri)) return "ERR|untrusted_host";';
        foreach (['private static string GamePostJson(', 'private static string GameGetJson('] as $signature) {
            $block = self::blockAfter($gameAuth, $signature);
            self::assertStringContainsString($guard, $block, $signature . ' doit refuser un hôte non sûr');
            $bearerAt = strpos($block, '"Authorization"');
            self::assertNotFalse($bearerAt, $signature . ' : Bearer du compte attendu');
            self::assertLessThan($bearerAt, (int) strpos($block, $guard),
                $signature . ' : le filtre d\'hôte doit précéder le Bearer');
            $guarded[] = [$gameAuthFile, $block];
        }

        $secretName = '"(?:Authorization|X-ATAK-TOKEN|X-COMSPEC-KEY|X-COMSPEC-SESSION|X-COMSPEC-STEAM)"';
        $rules = [
            'en-tête secret posé' => '/\.(?:TryAddWithoutValidation|Add)\(\s*' . $secretName . '/i',
            'nom d\'en-tête secret écrit hors d\'un Remove' => '/(?<!\.Remove\()' . $secretName . '/i',
            'propriété Authorization' => '/\.Authorization\s*=(?!=)/',
            'AuthenticationHeaderValue' => '/\bAuthenticationHeaderValue\s*\(/',
            'en-tête au nom non littéral' => '/Headers\.(?:TryAddWithoutValidation|Add)\(\s*+(?!"[^"]*"\s*,)/',
        ];
        // Seul site relu au nom non littéral : recopie des en-têtes de CONTENU d'une réponse décompressée.
        $reviewed = ['Extension_SyncStats.cs' => ['fresh.Headers.TryAddWithoutValidation(h.Key, h.Value);']];
        $sites = 0;
        foreach ($this->dllSourceFiles() as $file) {
            $src = $this->read($file);
            foreach ($rules as $rule => $pattern) {
                if (preg_match_all($pattern, $src, $m, PREG_OFFSET_CAPTURE) < 1) {
                    continue;
                }
                foreach ($m[0] as [$text, $offset]) {
                    if ($rule === 'en-tête secret posé') {
                        $sites++;
                    }
                    $inside = false;
                    foreach ($guarded as [$guardedFile, $block]) {
                        $start = $guardedFile === $file ? strpos($src, $block) : false;
                        if ($start !== false && $offset >= $start && $offset < $start + strlen($block)) {
                            $inside = true;
                            break;
                        }
                    }
                    $lineStart = strrpos(substr($src, 0, $offset), "\n");
                    $lineEnd = strpos($src, "\n", $offset);
                    $lineText = trim(substr($src, $lineStart === false ? 0 : $lineStart + 1,
                        ($lineEnd === false ? strlen($src) : $lineEnd) - ($lineStart === false ? 0 : $lineStart + 1)));
                    if (in_array($lineText, $reviewed[basename($file)] ?? [], true)) {
                        continue;
                    }
                    $line = substr_count(substr($src, 0, $offset), "\n") + 1;
                    self::assertTrue($inside, ucfirst($rule) . ' hors du filtre d\'hôte : '
                        . basename($file) . ':' . $line . ' ' . $lineText);
                }
            }
        }
        self::assertGreaterThanOrEqual(5, $sites, 'Recensement des en-têtes secrets vide : motif à revoir');
    }
}
