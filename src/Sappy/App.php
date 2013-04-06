<?php
/**
 * Copyright (c)2013 Andrew Heebner
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Sappy;

use Sappy\Exceptions\HTTPException;

/**
 * App class
 *
 * Main Sappy\App class.  Used for creating and running of API frameworks.
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2013, Andrew Heebner
 * @license     MIT
 * @package     Sappy
 */
class App
{

    /**
     * @var array
     */
    protected $_validNamespaces  = [];
    /**
     * @var null
     */
    protected $_auth             = null;
    /**
     * @var array
     */
    protected $_routes           = [];
    /**
     * @var null
     */
    protected $_currRoute        = null;
    /**
     * @var null
     */
    protected $_currMethod       = null;
    /**
     * @var array
     */
    private   $_methods          = ['get', 'head', 'post', 'patch', 'put', 'delete'];
    /**
     * @var bool
     */
    protected $_authorized       = true;
    /**
     * @var bool
     */
    protected $_requireAuth      = false;
    /**
     * @var string
     */
    private   $_projectURL       = 'http://www.github.com/dieselcode/Sappy';
    /**
     * @var string
     */
    private   $_versionLocation  = 'https://raw.github.com/dieselcode/Sappy/master/VERSION';
    /**
     * @var object
     */
    public    $request           = null;


    /**
     * App constructor
     *
     * @param array $namespaces
     * @param bool  $requireAuth
     */
    public function __construct(array $namespaces = [], $requireAuth = false)
    {
        $this->_validNamespaces = $namespaces;
        $this->_requireAuth     = $requireAuth;
        $this->request          = new Request($this->_validNamespaces);

        $this->_createDummyRoutes();
    }

    /**
     * Gets current version of Sappy
     *
     * @return null|string
     */
    public static function getVersion()
    {
        $vFile = dirname(dirname(dirname(__FILE__))) . '/VERSION';

        if (file_exists($vFile)) {
            return file_get_contents($vFile);
        }

        return null;
    }

    /**
     * Gets current available version from remote server
     *
     * @return null|string
     */
    public function getCurrentVersion()
    {
        $latest  = @file_get_contents($this->_versionLocation);

        if (!empty($latest)) {
            return $latest;
        }

        return null;
    }

    protected function getSignature()
    {
        return sprintf('Sappy/%s (%s)', $this->getVersion(), $this->_projectURL);
    }

    /**
     * Create a new routing pattern
     *
     * @param           $route
     * @param  callable $callback
     * @param  array    $validNamespaces
     * @return object
     */
    public function route($route, callable $callback, array $validNamespaces = [])
    {
        $_route = new Route($route, $callback, $validNamespaces);
        $this->_currRoute = $_route;
        $this->_routes[$_route->getHash()] = $_route;

        // activate the callback
        $callback();

        // return ourselves for chaining
        return $this;
    }

    /**
     * Associative list of headers to send with the route
     *
     * @param  array $headers
     * @return void
     */
    public function headers(array $headers = [])
    {
        $this->getCurrentRoute()->setRouteHeaders($headers);
    }

    /**
     * Magic method.  Allows for creating method callbacks on individual routes
     *
     * @param  string $method
     * @param  array  $args
     * @return void
     */
    public function __call($method, $args)
    {
        if (!empty($this->_currRoute)) {
            if (in_array($method, $this->_methods)) {
                $this->_currMethod = $method;
                $requireAuth = isset($args[1]) ? $args[1] : false;
                $this->getCurrentRoute()->setMethodCallback($method, $args[0], $requireAuth);
            }
        }
    }

    /**
     * Define an authorization callback
     *
     * @param  callable $callback
     * @return void
     */
    public function auth(callable $callback)
    {
        $this->_auth = $callback;
    }

    /**
     * Set the authorization status
     *
     * @param  bool $authorized
     * @return void
     */
    public function setAuthorized($authorized)
    {
        $this->_authorized = !!$authorized;
    }

    /**
     * Check authorization status
     *
     * @return bool
     */
    public function isAuthorized()
    {
        return $this->_authorized;
    }

    /**
     * Get callback for current route, and handle accordingly
     *
     * @return void
     * @throws HTTPException
     */
    private function _launch()
    {
        if (!empty($this->_routes)) {
            //
            // Check for HTTP authorization
            //
            if ($this->_requireAuth !== false) {
                $authData = $this->request->getAuthData();

                if ($authData === false) {
                    $this->setAuthorized(false);
                    throw new HTTPException('Authorization did not succeed (1)', 401);
                }

                if ($this->_auth instanceof \Closure) {
                    $authCallback = $this->_auth;
                    $ret = $authCallback($this->request, $authData);

                    if ($ret !== true) {
                        $this->setAuthorized(false);
                        throw new HTTPException('Authorization did not succeed (2)', 401);
                    }
                }
            }

            // get a matching route
            $route = $this->getValidRoute($this->request);

            if ($route instanceof Route) {

                if (!$route->isValidNamespace($this->request)) {
                    throw new HTTPException('Invalid namespace for requested path', 403);
                }

                $callback = $route->getMethodCallback(strtolower($this->request->getMethod()));
                $params   = $route->getParams($this->request);

                // see if our method callback requires authorization
                if ($callback['requireAuth'] !== false) {
                    $authData = $this->request->getAuthData();

                    if ($authData === false) {
                        $this->setAuthorized(false);
                        throw new HTTPException('Authorization did not succeed (3)', 401);
                    }

                    if ($this->_auth instanceof \Closure) {
                        $authCallback = $this->_auth;
                        $ret = $authCallback($this->request, $authData);

                        // if authorization succeeds, process our method callback
                        if ($ret === true) {
                            $this->runMethodCallback($route, $callback['callback'], $this->request, $params);
                        } else {
                            $this->setAuthorized(false);
                            throw new HTTPException('Authorization did not succeed (4)', 401);
                        }
                    }
                } else {
                    $this->runMethodCallback($route, $callback['callback'], $this->request, $params);
                }
            } else {
                throw new HTTPException('Requested route not found', 404);
            }

        }
    }

    /**
     * Run the current App model
     *
     * @param  callable $callback
     * @return void
     */
    public function run(callable $callback)
    {
        try {
            $this->_launch();
        } catch (\Exception $e) {
            $response = $callback($e, $this->request);

            if ($response instanceof Response) {
                $response->send(null, $e->getHeaders());
            } else {
                // send a 500 status code since we didn't return anything
                $response->write(500, 'An internal error occurred.  No further information provided')->send();
            }
        }
    }

    /**
     * Get a valid route matching the incoming Request route
     *
     * @param  Request $request
     * @return mixed Returns a Route object on success, false on error
     */
    protected function getValidRoute(Request $request)
    {
        /** @type Route $route */
        foreach ($this->_routes as $route) {
            if ($route->isValidPath($route->getPath(), $request->getPath())) {
                return $route;
            }
        }

        return false;
    }

    /**
     * Returns Route object that is currently in use
     *
     * @return object
     */
    protected function getCurrentRoute()
    {
        return $this->_routes[$this->_currRoute->getHash()];
    }

    /**
     * Runs a callback for a specified HTTP method
     *
     * @param  Route    $route
     * @param  callable $callback
     * @param  Request  $request
     * @param  object   $params
     * @return void
     * @throws HTTPException
     */
    protected function runMethodCallback(Route $route, $callback, Request $request, $params)
    {
        if (!is_null($callback) && $callback instanceof \Closure) {
            $response = $callback($request, new Response($request), $params);

            if ($response instanceof Response) {
                $response->send($route);
            } else {
                throw new HTTPException('Requested method could complete as requested', 500);
            }
        } else {
            // Set the Allow HTTP header to reflect a list of allowed methods for this route
            $headers = ['Allow' => strtoupper(join(', ', $route->getAvailableMethods()))];
            throw new HTTPException('Requested HTTP method not allowed/implemented for this route', 405, $headers);
        }
    }

    private function _createDummyRoutes()
    {
        //
        // Get version of Sappy (and check against remote version)
        //
        $this->route('/__version', function() {
            $this->get(function($request, $response, $params) {
                $remoteVer  = $this->getCurrentVersion();
                $localVer   = $this->getVersion();
                $message    = '';

                switch (version_compare($localVer, $remoteVer)) {
                    case -1:
                        $message = 'outdated; update available (' . $remoteVer . ')';
                        break;
                    case 0:
                        $message = 'up-to-date';
                        break;
                    case 1:
                        $message = 'experimental code; revert to ' . $remoteVer . ' for stability';
                        break;
                }

                $response->write(200, [
                    'Sappy' => [
                        'version' => [
                            'local'   => $localVer,
                            'current' => $remoteVer,
                            'status'  => $message
                        ]
                    ]
                ]);

                return $response;
            });
        });

    }

}

?>