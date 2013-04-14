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

// RFC1123 date format
const DATE_RFC1123 = 'D, d M Y H:i:s \G\M\T';

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
    static    $_options          = [];

    protected $_content          = null;

    static    $_projectURL       = 'https://github.com/dieselcode/Sappy';
    static    $_versionLocation  = 'https://raw.github.com/dieselcode/Sappy/master/VERSION';


    /**
     * App constructor
     *
     * @param array $namespaces
     * @param array $options
     */
    public function __construct(array $namespaces = [], array $options = [])
    {
        // parse out the user's options
        $this->_setOptions($options);

        // set the request headers
        $this->_setRequestHeaders();

        static::$_validNamespaces = $namespaces;
        static::$_requestPath     = $this->normalizePath($_SERVER['REQUEST_URI']);
        static::$_requestId       = sha1(uniqid(mt_rand(), true));

        $this->_handleEvents();

        // set the content after everything is all setup
        try {
            $this->setContent(@file_get_contents('php://input'));
        } catch (HTTPException $e) {
            $this->emit('error', [$e, $this]);
        }
    }

    /**
     * Set default options for APP class
     *
     * @param  array $options
     * @return void
     */
    private function _setOptions(array $options = [])
    {
        //
        // Define default class options here
        //
        static::$_options = [
            'use_output_compression' => true,
            'generate_content_md5'   => true,
            'cache_control'          => false,
        ];

        foreach ($options as $option => $value) {
            if (array_key_exists($option, static::$_options)) {
                static::$_options[$option] = $value;
            }
        }
    }

    /**
     * Get a user or default option value
     *
     * @param  string $option
     * @return null
     */
    public static function getOption($option)
    {
        if (array_key_exists($option, static::$_options)) {
            return static::$_options[$option];
        }

        return null;
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
     * Generate a software signature for the X-Powered-By header
     *
     * @return string
     */
    public static function getSignature()
    {
        return sprintf('Sappy/%s (%s)', self::getVersion(), static::$_projectURL);
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
    }

    /**
     * Set a handler for a specified named event
     *
     * @param string   $event
     * @param callable $callback
     */
    public function on($event, callable $callback)
    {
        Event::on($event, $callback);
    }

    /**
     * Emit a named event with specified args
     *
     * @param string $event
     * @param array  $args
     * @return mixed|null
     */
    public function emit($event, array $args = [])
    {
        $response = Event::emit($event, $args);

        if ($response instanceof Response) {
            $headers = ($args[0] instanceof HTTPException) ? $args[0]->getHeaders() : [];

            try {
                $response->send($headers);
            } catch (HTTPException $e) {
                $this->emit('error', [$e, $this]);
            }
        }

        return $response;
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
     */
    private function _launch()
    {
        if (!empty($this->_routes)) {
            // get a matching route
            $route = $this->getValidRoute($this);

            if ($route instanceof Route) {

                if (!$route->isValidNamespace($this)) {
                    $this->emit('error', [
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
                        $this->emit('error', [
                            new HTTPException('Authorization did not succeed', 401),
                            $this
                        ]);
                    }

                    if (Event::hasEvent('__AUTH__')) {
                        // emit the auth event and get the response
                        $ret = $this->emit('__AUTH__', [$authData, $this]);

                        // if authorization succeeds, process our method callback
                        if ($ret === true) {
                            $this->runMethodCallback($route, $callback, $params);
                        } else {
                            $this->emit('error', [
                                new HTTPException('Authorization did not succeed', 401),
                                $this
                            ]);
                        }
                    } else {
                        $this->emit('error', [
                            new HTTPException('Could not authenticate properly; Server problem', 500),
                            $this
                        ]);
                    }
                } else {
                    $this->runMethodCallback($route, $callback, $params);
                }
            } else {
                $this->emit('error', [
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
     * @param Route  $route
     * @param mixed  $callback
     * @param object $params
     * @return void
     */
    protected function runMethodCallback(Route $route, $callback, $params)
    {
        if (!is_null($callback) && is_array($callback)) {
            $closure = $callback['callback'];
            $response = $closure($this, new Response(), $params);

            if ($response instanceof Response) {
                //
                // As per HTTP spec, Options needs a list of methods set to the Allow header
                //
                if ($callback['method'] == 'options') {
                    $headers = array_merge(
                        array('Allow' => strtoupper(join(', ', $route->getAvailableMethods()))),
                        $response->getHeaders()
                    );
                } else {
                    $headers = $response->getHeaders();
                }

                try {
                    $response->send($headers);
                } catch (HTTPException $e) {
                    $this->emit('error', [$e, $this]);
                }
            } else {
                $this->emit('error', [
                    new HTTPException('Missing response data for method', 500),
                    $this
                ]);
            }
        } else {
            // Set the Allow HTTP header to reflect a list of allowed methods for this route
            $headers = ['Allow' => strtoupper(join(', ', $route->getAvailableMethods()))];
            $this->emit('error', [
                new HTTPException('Requested HTTP method not allowed/implemented for this route', 405, $headers),
                $this
            ]);
        }
    }

    /**
     * Setup internal event handlers
     *
     * @return void
     */
    private function _handleEvents()
    {
        //
        // TODO: Add user-supplied logging to this, so they can debug
        //
        $this->on('error', function(HTTPException $exception, Request $request) {
            $response = new Response();
            $response->write($exception->getCode(), ['message' => $exception->getMessage()]);

            return $response;
        });
    }

}

?>