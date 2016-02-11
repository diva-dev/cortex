<?php
/*
 * This file is part of the Cortex package.
 *
 * (c) Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Brain;

use Brain\Cortex\Factory\Factory;
use Brain\Cortex\Group\GroupCollection;
use Brain\Cortex\Group\GroupCollectionInterface;
use Brain\Cortex\Route\PriorityRouteCollection;
use Brain\Cortex\Route\RouteCollectionInterface;
use Brain\Cortex\Router\ResultHandler;
use Brain\Cortex\Router\ResultHandlerInterface;
use Brain\Cortex\Router\Router;
use Brain\Cortex\Router\RouterInterface;
use Brain\Cortex\Uri\PsrUri;
use Brain\Cortex\Uri\UriInterface;
use Brain\Cortex\Uri\WordPressUri;
use Psr\Http\Message\UriInterface as PsrUriInterface;

/**
 * @author  Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @package Cortex
 */
class Cortex
{
    /**
     * @var bool
     */
    private static $booted = false;

    /**
     * @var bool
     */
    private static $late = false;

    /**
     * @param  \Psr\Http\Message\UriInterface|null $psrUri
     * @return bool
     */
    public static function boot(PsrUriInterface $psrUri = null)
    {
        if (self::$booted) {
            return false;
        }

        if (did_action('parse_request')) {
            $exception = new \BadMethodCallException(
                sprintf('%s must be called before "do_parse_request".', __METHOD__)
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw $exception;
            }

            do_action('cortex.fail', $exception);
        }

        add_filter('do_parse_request', function ($do, \WP $wp) use ($psrUri) {

            self::$late = true;

            try {
                $instance = new static();
                $routes = $instance->factoryRoutes();
                $groups = $instance->factoryGroups();
                $router = $instance->factoryRouter($routes, $groups);
                $handler = $instance->factoryHandler();
                $uri = $instance->factoryUri($psrUri);
                $do = $handler->handle($router->match($uri), $wp, $do);
                unset($uri, $handler, $router, $groups, $routes, $instance);
                remove_all_filters('cortex.routes');
                remove_all_filters('cortex.groups');

                return $do;
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    throw $e;
                }

                do_action('cortex.fail', $e);

                return $do;
            }

        }, 100, 2);

        self::$booted = true;

        return true;
    }

    /**
     * @return bool
     */
    public static function late()
    {
        return self::$late;
    }

    /**
     * @return \Brain\Cortex\Group\GroupCollectionInterface
     */
    private function factoryGroups()
    {
        /** @var \Brain\Cortex\Group\GroupCollectionInterface $groups */
        $groups = Factory::factoryByHook(
            'group-collection',
            GroupCollectionInterface::class,
            function () {
                return new GroupCollection();
            }
        );

        do_action('cortex.groups', $groups);

        return $groups;
    }

    /**
     * @return \Brain\Cortex\Route\RouteCollectionInterface
     */
    private function factoryRoutes()
    {
        /** @var \Brain\Cortex\Route\RouteCollectionInterface $routes */
        $routes = Factory::factoryByHook(
            'group-collection',
            RouteCollectionInterface::class,
            function () {
                return new PriorityRouteCollection();
            }
        );

        do_action('cortex.routes', $routes);

        return $routes;
    }

    /**
     * @param  \Brain\Cortex\Route\RouteCollectionInterface $routes
     * @param  \Brain\Cortex\Group\GroupCollectionInterface $groups
     * @return \Brain\Cortex\Router\RouterInterface
     */
    private function factoryRouter(
        RouteCollectionInterface $routes,
        GroupCollectionInterface $groups
    ) {
        /** @var \Brain\Cortex\Router\RouterInterface $router */
        $router = Factory::factoryByHook(
            'router',
            RouterInterface::class,
            function () use ($routes, $groups) {
                return new Router($routes, $groups);
            }
        );

        return $router;
    }

    /**
     * @return \Brain\Cortex\Router\ResultHandlerInterface
     */
    private function factoryHandler()
    {
        /** @var ResultHandlerInterface $handler */
        $handler = Factory::factoryByHook(
            'result-handler',
            ResultHandlerInterface::class,
            function () {
                return new ResultHandler();
            }
        );

        return $handler;
    }

    /**
     * @param  \Psr\Http\Message\UriInterface|null $psrUri
     * @return \Brain\Cortex\Uri\UriInterface
     */
    private function factoryUri(PsrUriInterface $psrUri = null)
    {
        /** @var UriInterface $uri */
        $uri = Factory::factoryByHook(
            'result-handler',
            UriInterface::class,
            function () use ($psrUri) {
                $psrUri instanceof PsrUriInterface or $psrUri = new PsrUri();

                return new WordPressUri($psrUri);
            }
        );

        return $uri;
    }
}