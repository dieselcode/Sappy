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


class Route extends App
{

    protected $_path            = '';
    protected $_hash            = '';
    protected $_methodCallbacks = [];
    protected $_validNamespaces = [];

    public function __construct($route, callable $callback, array $namespaces = [])
    {
        $this->_path = trim($route, '/');  // remove leading/trailing slashes
        $this->_hash = $this->_generateRouteHash($route);

        // do some error-checking here and make sure they're not putting in bad namespaces.
        //  Namespaces here must match what they initialized 'App' with.
        $this->_validNamespaces = $namespaces;
    }

    public function __call($method, $args)
    {
        switch ($method) {
            case 'getHash':
                return $this->_hash;
                break;

            case 'getPath':
                return $this->_path;
                break;

            case 'getMethodCallback':
                return isset($this->_methodCallbacks[$args[0]]) ? $this->_methodCallbacks[$args[0]] : null;
                break;

            case 'isValidNamespace':
                if ($args[0] instanceof Request) {
                    if (!empty($this->_validNamespaces)) {
                        var_dump($args[0]->getNamespace());
                        var_dump($this->_validNamespaces);
                        return in_array($args[0]->getNamespace(), $this->_validNamespaces) ? true : false;
                    }

                    return false;
                }
                break;

            case 'hasParams':
                return !!preg_match('#(:(\w+))#', $this->_path);
                break;

        }

        return true;
    }

    public function setMethodCallback($method, \Closure $callback, $requireAuth = false)
    {
        $this->_methodCallbacks[$method] = [
            'callback' => $callback,
            'requireAuth' => $requireAuth
        ];
    }

    public function getParams(Request $request)
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

    public function isValidPath($routePath, $requestPath)
    {
        return !!preg_match('#'.preg_replace('#(:(\w+))#', '(.*)', $routePath).'?#', $requestPath);
    }

    private function _generateRouteHash($route)
    {
        return md5($route);
    }

}

?>