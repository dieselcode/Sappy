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
     * App constructor
     *
     * @param array $namespaces
     * @param bool  $requireAuth
     */
    public function __construct(array $namespaces = [], $requireAuth = false)
    {
        $this->_validNamespaces = $namespaces;
        $this->_requireAuth = $requireAuth;
    }

    /**
     * Create a new routing pattern
     *
     * @param           $route
     * @param  callable $callback
     * @param  array    $validNamespaces
     * @return void
     */
    public function route($route, callable $callback, $validNamespaces = [])
    {
        $_route = new Route($route, $callback, $validNamespaces);
        $this->_currRoute = $_route;
        $this->_routes[$_route->getHash()] = $_route;

        // activate the callback
        $callback();
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
     * @throws \Exception
     */
    private function _launch()
    {
        if (!empty($this->_routes)) {
            $request  = new Request($this->_validNamespaces);

            //
            // Check for HTTP authorization
            //
            if ($this->_requireAuth !== false) {
                $authData = $request->getAuthData();

                if ($authData === false) {
                    $this->setAuthorized(false);
                    throw new \Exception('Authorization did not succeed (1)', 401);
                }

                if ($this->_auth instanceof \Closure) {
                    $authCallback = $this->_auth;
                    $ret = $authCallback($request, $authData);

                    if (!$ret) {
                        $this->setAuthorized(false);
                        throw new \Exception('Authorization did not succeed (2)', 401);
                    }
                }
            }

            // get a matching route
            $route = $this->getValidRoute($request);

            if ($route instanceof Route) {

                if (!$route->isValidNamespace($request)) {
                    throw new \Exception('Invalid namespace for requested path', 403);
                }

                $callback = $route->getMethodCallback(strtolower($request->getMethod()));
                $params   = $route->getParams($request);

                // see if our method callback requires authorization
                if ($callback['requireAuth']) {
                    $authData = $request->getAuthData();

                    if ($authData === false) {
                        $this->setAuthorized(false);
                        throw new \Exception('Authorization did not succeed (3)', 401);
                    }

                    if ($this->_auth instanceof \Closure) {
                        $authCallback = $this->_auth;
                        $ret = $authCallback($request, $authData);

                        // if authorization succeeds, process our method callback
                        if ($ret) {
                            $this->runMethodCallback($callback['callback'], $request, $params);
                        } else {
                            $this->setAuthorized(false);
                            throw new \Exception('Authorization did not succeed (4)', 401);
                        }
                    }
                } else {
                    $this->runMethodCallback($callback['callback'], $request, $params);
                }
            } else {
                throw new \Exception('Requested route not found', 404);
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
            $callback($e);
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
     * @param  callable $callback
     * @param  Request  $request
     * @param  object   $params
     * @return void
     * @throws \Exception
     */
    protected function runMethodCallback(callable $callback, Request $request, $params)
    {
        if ($callback instanceof \Closure) {
            $response = $callback($request, new Response(), $params);

            if ($response instanceof Response) {
                $response->send();
            } else {
                throw new \Exception('Requested method could complete as requested', 500);
            }
        } else {
            throw new \Exception('Requested HTTP method not allowed for this route', 405);
        }
    }

}

?>