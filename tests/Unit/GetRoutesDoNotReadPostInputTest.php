<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Request::input() / inputString() ne lisent QUE le corps POST : dans une méthode servie en GET, un
 * paramètre d’adresse (?vue=…, ?q=…, ?filtre=…) lu ainsi est toujours vide et la page ignore l’onglet,
 * la recherche ou le filtre demandé. Ces méthodes doivent lire query() / queryString().
 *
 * Contrôle statique : pour chaque route GET dont la méthode n’est routée sur aucun autre verbe, le corps
 * de la méthode (lu par réflexion) ne doit pas appeler ->input( ni ->inputString(. Les méthodes routées
 * en GET et POST (formulaires qui se renvoient à eux-mêmes) lisent le corps dans leur branche POST et
 * ne sont pas contrôlées ici.
 */
final class GetRoutesDoNotReadPostInputTest extends TestCase
{
    /**
     * Exceptions justifiées : 'Classe::méthode' => raison.
     *
     * @var array<string, string>
     */
    private const ALLOWED = [];

    /** @return list<array<string, mixed>> */
    private static function routes(): array
    {
        $server = $_SERVER;
        $hadGet = isset($_GET);
        $hadPost = isset($_POST);
        $_GET ??= [];
        $_POST ??= [];
        try {
            $router = new Router(new Request());
            $register = require dirname(__DIR__, 2) . '/routes/web.php';
            $register($router);
        } finally {
            $_SERVER = $server;
            if (!$hadGet) {
                unset($_GET);
            }
            if (!$hadPost) {
                unset($_POST);
            }
        }

        return (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
    }

    public function testGetOnlyHandlersReadAddressParametersFromQueryString(): void
    {
        /** @var array<string, array<string, string>> $verbs Classe::méthode => verbe => chemin */
        $verbs = [];
        foreach (self::routes() as $route) {
            $handler = $route['handler'];
            if (!is_array($handler) || !is_string($handler[0] ?? null) || !is_string($handler[1] ?? null)) {
                continue;
            }
            $verbs[$handler[0] . '::' . $handler[1]][(string) $route['method']] ??= (string) $route['path'];
        }

        $checked = 0;
        $problems = [];
        foreach ($verbs as $key => $byVerb) {
            if (array_keys($byVerb) !== ['GET'] || isset(self::ALLOWED[$key])) {
                continue;
            }
            [$class, $method] = explode('::', $key, 2);
            if (!method_exists($class, $method)) {
                continue; // RoutedControllerMethodsExistTest signale déjà ce cas.
            }
            $ref = new \ReflectionMethod($class, $method);
            $file = (string) $ref->getFileName();
            $lines = array_slice((array) file($file), $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1);
            ++$checked;
            foreach ($lines as $offset => $line) {
                if (preg_match('/->input(String)?\(/', (string) $line) === 1) {
                    $problems[] = 'GET ' . $byVerb['GET'] . ' → ' . $key . '() ligne ' . ($ref->getStartLine() + $offset)
                        . ' : ' . trim((string) $line) . ' (utiliser query() / queryString())';
                }
            }
        }

        self::assertGreaterThan(100, $checked);
        self::assertSame([], $problems);
    }

    public function testInputIgnoresTheQueryString(): void
    {
        $get = $_GET ?? null;
        $post = $_POST ?? null;
        $_GET = ['vue' => 'flotte'];
        $_POST = [];
        try {
            $request = new Request();
            self::assertSame('flotte', $request->queryString('vue', 'ligne'));
            self::assertSame('ligne', (string) $request->input('vue', 'ligne'));
        } finally {
            $_GET = $get ?? [];
            $_POST = $post ?? [];
        }
    }
}
