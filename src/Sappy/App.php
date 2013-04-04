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

    protected $_namespaces  = [];
    protected $_routes      = [];
    protected $_currRoute   = null;

    private   $_methods     = ['get', 'post', 'patch', 'put', 'delete'];


    public function __construct(array $namespaces = [])
    {
        $this->_namespaces = $namespaces;
    }

    public function route($route, callable $callback)
    {
        $_route = new Route($route, $callback);

        $this->_routes[$_route->getHash()] = $_route;
        $this->_currRoute = $_route;

        // activate the callback
        $callback();
    }

    public function __call($method, $args)
    {
        if (!empty($this->_currRoute)) {
            if (in_array($method, $this->_methods)) {
                $this->getCurrentRoute()->setMethodCallback($method, $args[0]);
            }
        }
    }

    /**
     * Run the framework and execute all callbacks as needed
     */
    public function run()
    {
        if (!empty($this->_routes)) {
            $request  = new Request($this->_namespaces);
            $response = new Response();

            foreach ($this->_routes as $route) {

                //
                // TODO: Match the requested path with the route path
                //  Must use regexp to gather this info... dunno how yet.
                //
                var_dump($route->isValidNamespace($request));
                var_dump($route->getPath());
                var_dump($request->getPath());

                if ($route->isValidNamespace($request) && $route->isValidPath($route->getPath(), $request->getPath())) {
                    $callback = $route->getMethodCallback(strtolower($request->getMethod()));
                    $params   = $route->getParams($request);

                    // call our callback with the request, a new Response object, and the parsed params
                    //  all callbacks return the Response object
                    $response = $callback($request, $response, $params);
                } else {
                    // write out a 404 error stating that the current namespace is not valid
                    $response->write(404, ['error' => 'Namespace not allowed here']);
                }

                // output the response
                $response->go();
            }

        }
    }

    protected function getCurrentRoute()
    {
        return $this->_routes[$this->_currRoute->getHash()];
    }

}

?>