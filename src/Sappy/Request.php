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


class Request extends App
{

    protected $_path        = '';
    protected $_vars        = [];
    protected $_namespaces  = [];

    public function __construct(array $namespaces = [])
    {
        $path = trim($_SERVER['REQUEST_URI'], '/');
        $this->_path = empty($path) ? '/' : $path;

        $this->_vars = $_SERVER;
        $this->_namespaces = $namespaces;
    }

    public function __call($method, $args)
    {
        switch ($method) {
            case 'getMethod':
                return $this->_vars['REQUEST_METHOD'];
                break;

            case 'getPath':
                if (!empty($this->_namespaces)) {
                    $_parts = explode('/', $this->_path);
                    if (in_array($_parts[0], $this->_namespaces)) {
                        array_shift($_parts);
                        return join('/', $_parts);
                    }
                } else {
                    return $this->_path;
                }
                break;

            case 'getNamespace':
                if (!empty($this->_namespaces)) {
                    $_parts = explode('/', $this->_path);
                    if (in_array($_parts[0], $this->_namespaces)) {
                        return $_parts[0];
                    }

                    return null;
                }
                break;
        }

        return true;
    }

}

?>