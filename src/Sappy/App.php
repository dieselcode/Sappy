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
class App extends Request
{

    protected $_routes           = [];
    protected $_currRoute        = null;
    protected $_currMethod       = null;
    private   $_methods          = ['get', 'head', 'options', 'post', 'patch', 'put', 'delete'];
    protected $_authorized       = true;

    protected $_content          = null;

    private   $_projectURL       = 'https://github.com/dieselcode/Sappy';
    private   $_versionLocation  = 'https://raw.github.com/dieselcode/Sappy/master/VERSION';


    /**
     * App constructor
     *
     * @param array $namespaces
     * @param array $allowedTypes
     */
    public function __construct(array $namespaces = [], array $allowedTypes = [])
    {
        $this->_setRequestHeaders();

        $this->_validNamespaces = $namespaces;
        $this->_requestPath     = $this->normalizePath($_SERVER['REQUEST_URI']);
        $this->_allowedTypes    = $allowedTypes;
        $this->_requestId       = sha1(uniqid(mt_rand(), true));

        $this->_dummyRoutes();
        $this->_handleEvents();

        // set the content after everything is all setup
        $this->setContent(@file_get_contents('php://input'));
    }

    /**
     * Gets current version of Sappy
     *
     * @return null|string
     */
    public static function getVersion()
    {
        clearstatcache();
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
        clearstatcache();
        $latest  = @file_get_contents($this->_versionLocation);

        if (!empty($latest)) {
            return $latest;
        }

        return null;
    }

    public function getSignature()
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
        $_route = new Route($this->normalizePath($route), $callback, $validNamespaces);
        $this->_currRoute = $_route;
        $this->_routes[$_route->getHash()] = $_route;

        // activate the callback
        $callback();

        // return ourselves for chaining
        return $this;
    }

    public function on($event, callable $callback)
    {
        Event::on($event, $callback);
    }

    public function emit($event, $args)
    {
        return Event::emit($event, $args);
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
     * Get callback for current route, and handle accordingly
     *
     * @return void
     * @throws HTTPException
     */
    private function _launch()
    {
        if (!empty($this->_routes)) {
            // get a matching route
            $route = $this->getValidRoute($this);

            if ($route instanceof Route) {

                if (!$route->isValidNamespace($this)) {
                    Event::emit('error', [
                        new HTTPException('Invalid namespace for requested path', 403),
                        $this
                    ]);
                }

                $method     = strtolower($this->getRequestMethod());
                $callback   = $route->getMethodCallback($method);
                $params     = $route->getParams($this);

                // see if our method callback requires authorization
                if (!is_null($callback) && $callback['requireAuth'] !== false) {
                    $authData = $this->getAuthData();

                    if ($authData === false) {
                        Event::emit('error', [
                            new HTTPException('Authorization did not succeed', 401),
                            $this
                        ]);
                    }

                    if (Event::hasEvent('__AUTH__')) {
                        // emit the auth event and get the response
                        $ret = Event::emit('__AUTH__', [$authData, $this]);

                        // if authorization succeeds, process our method callback
                        if ($ret === true) {
                            $this->runMethodCallback($route, $callback, $params);
                        } else {
                            Event::emit('error', [
                                new HTTPException('Authorization did not succeed', 401),
                                $this
                            ]);
                        }
                    } else {
                        Event::emit('error', [
                            new HTTPException('Could not authenticate properly; Server problem', 500),
                            $this
                        ]);
                    }
                } else {
                    $this->runMethodCallback($route, $callback, $params);
                }
            } else {
                Event::emit('error', [
                    new HTTPException('Requested route not found', 404),
                    $this
                ]);
            }

        }
    }

    /**
     * Run the current App model
     *
     * @return void
     */
    public function run()
    {
        $this->_launch();
    }

    /**
     * Get a valid route matching the incoming Request route
     *
     * @return Route|bool Returns a Route object on success, false on error
     */
    protected function getValidRoute()
    {
        foreach ($this->_routes as $route) {
            if ($route->isValidPath($route->getPath(), $this->getRequestPath())) {
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
     * @param  array    $callback
     * @param  object   $params
     * @return void
     * @throws HTTPException
     */
    protected function runMethodCallback(Route $route, $callback, $params)
    {
        if (!is_null($callback) && is_array($callback)) {
            $closure = $callback['callback'];
            $response = $closure($this, new Response($this), $params);

            if ($response instanceof Response) {
                if ($callback['method'] == 'options') {
                    $headers = array_merge(
                        array('Allow' => strtoupper(join(', ', $route->getAvailableMethods()))),
                        $response->getHeaders()
                    );
                } else {
                    $headers = $response->getHeaders();
                }

                $response->send($headers);
            } else {
                Event::emit('error', [
                    new HTTPException('Requested method could not complete as requested', 500),
                    $this
                ]);
            }
        } else {
            // Set the Allow HTTP header to reflect a list of allowed methods for this route
            $headers = ['Allow' => strtoupper(join(', ', $route->getAvailableMethods()))];
            Event::emit('error', [
                new HTTPException('Requested HTTP method not allowed/implemented for this route', 405, $headers),
                $this
            ]);
        }
    }

    private function _handleEvents()
    {
        //
        // all errors are handled internally
        //
        $this->on('error', function(HTTPException $exception, Request $request) {
            $response = new Response($request);
            $response->write($exception->getCode(), ['message' => $exception->getMessage()]);

            return $response;
        });
    }

    //
    // __version route is always accessible
    //
    private function _dummyRoutes()
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