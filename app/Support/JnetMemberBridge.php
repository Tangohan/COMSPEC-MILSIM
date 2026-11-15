<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;

/**
 * Passerelle des routes /jnet/moi/*. Chaque route y passe : elle appelle la méthode du contrôleur existant (mêmes droits,
 * mêmes données) en choisissant sous quelle adresse le contrôleur voit la requête.
 *
 *  - Contrôleur migré : il déclare « public const JNET_MOI_READY = true » (il rend ses pages par JnetMemberShell::view et
 *    construit ses adresses avec jnet_moi_url()). Il reçoit la vraie requête, sous /jnet/moi/….
 *  - Contrôleur pas encore migré : il lit encore « /back-office/ma-situation/… » pour choisir son habillage ou ses
 *    retours. Il reçoit la même requête présentée sous l'ancienne adresse : la page s'affiche comme avant, à sa nouvelle
 *    adresse, et les liens qu'elle contient (anciens) mènent par 301 à la nouvelle.
 *
 * Ainsi chaque lien /jnet/moi/* répond dès maintenant, et chaque contrôleur bascule seul, en une constante, sans toucher
 * à routes/web.php.
 */
final class JnetMemberBridge
{
    /**
     * @param class-string $class contrôleur de la route
     * @param string $action méthode
     * @param class-string|null $owner contrôleur dont la constante JNET_MOI_READY décide de l'habillage (par défaut $class)
     */
    public static function handler(string $class, string $action, ?string $owner = null): \Closure
    {
        return static function (Request $request, array $params) use ($class, $action, $owner): Response {
            return self::run($request, $params, $class, $action, $owner ?? $class);
        };
    }

    /**
     * @param array<string, string> $params
     */
    public static function run(Request $request, array $params, string $class, string $action, string $owner): Response
    {
        if (!self::ready($owner)) {
            $slug = JnetMemberPaths::slugOf($request->path());
            if ($slug !== null) {
                $request = $request->withPath('/' . JnetMemberPaths::legacyPath($slug));
            }
        }
        try {
            $controller = Container::get($class);
        } catch (\InvalidArgumentException $e) {
            // Contrôleur absent du Container : instanciation directe, comme le routeur. Toute autre erreur remonte.
            if ($e->getMessage() !== 'Unknown service: ' . $class) {
                throw $e;
            }
            $controller = new $class();
        }
        $response = $controller->$action($request, $params);

        return $response instanceof Response ? $response : new Response();
    }

    /** Le contrôleur sert-il lui-même ses pages dans la coque JNET ? */
    public static function ready(string $owner): bool
    {
        return defined($owner . '::JNET_MOI_READY') && constant($owner . '::JNET_MOI_READY') === true;
    }
}
