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

class App
{

    protected $_validNamespaces  = [];
    protected $_auth             = null;
    protected $_routes           = [];
    protected $_currRoute        = null;
    protected $_currMethod       = null;
    private   $_methods          = ['get', 'head', 'post', 'patch', 'put', 'delete'];
    protected $_authorized       = true;
    protected $_requireAuth      = false;


    public function __construct(array $namespaces = [], $requireAuth = false)
    {
        $this->_validNamespaces = $namespaces;
        $this->_requireAuth = $requireAuth;
    }

    public function route($route, callable $callback, $validNamespaces = [])
    {
        $_route = new Route($route, $callback, $validNamespaces);
        $this->_currRoute = $_route;
        $this->_routes[$_route->getHash()] = $_route;

        // activate the callback
        $callback();
    }

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

    public function auth(callable $callback)
    {
        $this->_auth = $callback;
    }

    public function setAuthorized($authorized)
    {
        $this->_authorized = !!$authorized;
    }

    public function isAuthorized()
    {
        return $this->_authorized;
    }

    /**
     * Run the framework and execute all callbacks as needed
     */
    public function launch()
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

            foreach ($this->_routes as $route) {

                if ($route->isValidPath($route->getPath(), $request->getPath())) {

                    if (!$route->isValidNamespace($request)) {
                        throw new \Exception('Invalid namespace for requested path', 403);
                        break;
                    }

                    $callback = $route->getMethodCallback(strtolower($request->getMethod()));
                    $params   = $route->getParams($request);

                    // see if our method callback requires authorization
                    if ($callback['requireAuth']) {
                        $authData = $request->getAuthData();

                        if ($authData === false) {
                            $this->setAuthorized(false);
                            throw new \Exception('Authorization did not succeed (3)', 401);
                            break;
                        }

                        if ($this->_auth instanceof \Closure) {
                            $authCallback = $this->_auth;
                            $ret = $authCallback($request, $authData);

                            // if authorization succeeds, process our method callback
                            if ($ret) {
                                $this->runMethodCallback($callback['callback'], $request, $params);
                                break;
                            } else {
                                $this->setAuthorized(false);
                                throw new \Exception('Authorization did not succeed (4)', 401);
                                break;
                            }
                        }
                    } else {
                        $this->runMethodCallback($callback['callback'], $request, $params);
                        break;
                    }
                } else {
                    continue;
                }

            }

        }
    }

    public function run(callable $callback)
    {
        try {
            $this->launch();
        } catch (\Exception $e) {
            $callback($e);
        }
    }

    protected function getCurrentRoute()
    {
        return $this->_routes[$this->_currRoute->getHash()];
    }

    protected function runMethodCallback(callable $callback, Request $request, $params)
    {
        if ($callback instanceof \Closure) {
            $response = $callback($request, new Response(), $params);
            $response->send();
        } else {
            throw new \Exception('Requested HTTP method not allowed for this route', 405);
        }
    }

}

?>