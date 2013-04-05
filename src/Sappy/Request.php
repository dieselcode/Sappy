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

    const AUTH_BASIC        = 'Basic';
    const AUTH_OAUTH        = 'Oauth';

    protected $_path        = '';
    protected $_vars        = [];
    protected $_namespaces  = [];
    protected $_data        = null;

    public function __construct(array $namespaces = [])
    {
        $path = trim($_SERVER['REQUEST_URI'], '/');
        $this->_path = empty($path) ? '/' : $path;

        $this->_vars        = array_merge($_SERVER, getallheaders());
        $this->_namespaces  = $namespaces;
        $this->_data        = @file_get_contents('php://input');
    }

    public function getMethod()
    {
        return $this->_vars['REQUEST_METHOD'];
    }

    public function getPath()
    {
        if (!empty($this->_namespaces)) {
            $_parts = explode('/', $this->_path);
            if (in_array($_parts[0], $this->_namespaces)) {
                array_shift($_parts);
                return join('/', $_parts);
            }
        }

        return $this->_path;
    }

    public function getNamespace()
    {
        if (!empty($this->_namespaces)) {
            $_parts = explode('/', $this->_path);
            if (in_array($_parts[0], $this->_namespaces)) {
                return $_parts[0];
            }
        }

        return null;
    }

    public function getHeaders()
    {
        return $this->_vars;
    }

    public function getHeader($header)
    {
        return isset($this->_vars[$header]) ? $this->_vars[$header] : null;
    }

    public function getContent($decodeAsArray = false)
    {
        $data = json_decode($this->_data, $decodeAsArray);
        if (json_last_error() == JSON_ERROR_SYNTAX) {
            throw new \Exception('Client data was malformed', 400);
        }

        return $data;
    }

    public function getAuthData()
    {
        $auth = $this->getHeader('Authorization');
        $ret  = [];

        if (!empty($auth)) {
            list($type, $data) = explode(' ', $auth);

            switch($type) {
                case 'Basic':
                    list($user, $password) = explode(':', base64_decode($data));
                    $ret = ['user' => $user, 'password' => $password];
                    break;

                case 'Oauth':
                    //
                    // TODO: Add Oauth helper stuff
                    //
                    break;
            }
        } else {
            return false;
        }

        return (object)$ret;
    }

}

?>