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
 * Route class
 *
 * Holds specific information and methods pertaining to routing
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2013, Andrew Heebner
 * @license     MIT
 * @package     Sappy
 */
class Route
{

    protected $_path            = '';
    protected $_hash            = '';
    protected $_methodCallbacks = [];
    protected $_validNamespaces = [];
    protected $_headers         = [];

    /**
     * Route constructor
     *
     * @param array    $route
     * @param callable $callback
     * @param array    $namespaces
     */
    public function __construct($route, callable $callback, array $namespaces = [])
    {
        $this->_path            = $route;
        $this->_hash            = $this->_generateRouteHash($route);
        $this->_validNamespaces = $namespaces;
    }

    /**
     * Retrieve the current route hash
     *
     * @return string
     */
    public function getHash()
    {
        return $this->_hash;
    }

    /**
     * Retrieve the current route path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Retrieve the callback options for requested method
     *
     * @param  string $method
     * @return mixed Return an array if method was found, null if not
     */
    public function getMethodCallback($method)
    {
        return isset($this->_methodCallbacks[$method]) ? $this->_methodCallbacks[$method] : null;
    }

    /**
     * Get available methods for current route
     *
     * @return array
     */
    public function getAvailableMethods()
    {
        $keys = [];

        if (!empty($this->_methodCallbacks)) {
            $keys = array_keys($this->_methodCallbacks);
        }

        // options and head are set internally because they're handled internally
        return array_merge($keys, ['options', 'head']);
    }

    /**
     * Check the current namespace against the allowed namespaces for a route
     *
     * @param  object $request
     * @return bool
     */
    public function isValidNamespace($request)
    {
        if (count($this->_validNamespaces) > 0) {
            return in_array($request->getNamespace(), $this->_validNamespaces) ? true : false;
        } else {
            // no namespaces specified... route will be allowed on all valid namespaces
            return true;
        }
    }

    /**
     * Capture parameters from the request
     *
     * @param  object $request
     * @return object
     */
    public function getParams($request)
    {
        $obj = [];

        $params  = explode('/', $this->_path);
        $request = explode('/', $request->getPath());

        foreach ($params as $k => $v) {
            if (substr($v, 0, 1) == ':') {
                $val = substr($v, 1);
                $obj[$val] = isset($request[$k]) ? $request[$k] : null;
            }
        }

        unset($params, $request);

        return (object)$obj;
    }

    /**
     * Set method callback options
     *
     * @param  string   $method
     * @param  callable $callback
     * @param  bool     $requireAuth
     * @return void
     */
    public function setMethodCallback($method, \Closure $callback, $requireAuth = false)
    {
        $this->_methodCallbacks[$method] = [
            'callback' => $callback,
            'requireAuth' => $requireAuth
        ];
    }

    /**
     * Set additional route headers
     *
     * @param  array $headers
     * @return void
     */
    public function setRouteHeaders(array $headers = [])
    {
        $this->_headers = $headers;
    }

    /**
     * Used in Response, export the route headers
     *
     * @return void
     */
    public function exportRouteHeaders()
    {
        if (!empty($this->_headers)) {
            foreach ($this->_headers as $k => $v) {
                header(sprintf('%s: %s', $k, $v), true);
            }
        }
    }

    /**
     * Check to see if the requested path matches the current path
     *
     * @param  string $routePath
     * @param  string $requestPath
     * @return bool
     */
    public function isValidPath($routePath, $requestPath)
    {
        return !!preg_match('#^'.preg_replace('#(:(\w+))#', '(\w+)', $routePath).'$#', $requestPath);
    }

    /**
     * Generate an md5 hash of a route string
     *
     * @param  string $route
     * @return string
     */
    private function _generateRouteHash($route)
    {
        return md5($route);
    }

}

?>