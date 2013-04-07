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

use Sappy\Type\JSON;

abstract class Request
{

    protected $_validNamespaces     = [];
    protected $_requestPath         = null;
    protected $_requestHeaders      = [];
    protected $_transport           = null;
    protected $_data                = null;

    public function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function getHTTPVersion()
    {
        @list(,$version) = explode('/', $_SERVER['SERVER_PROTOCOL'], 2);
        return $version;
    }

    public function getHeaders()
    {
        return $this->_requestHeaders;
    }

    public function getHeader($header)
    {
        return isset($this->_requestHeaders[$header]) ? $this->_requestHeaders[$header] : null;
    }

    public function getRequestPath()
    {
        if (!empty($this->_validNamespaces)) {
            $_parts = explode('/', $this->_requestPath);
            if (in_array($_parts[0], $this->_validNamespaces)) {
                array_shift($_parts);
                return join('/', $_parts);
            }
        }

        return $this->_requestPath;
    }

    public function getNamespace()
    {
        if (!empty($this->_validNamespaces)) {
            $_parts = explode('/', $this->_requestPath);
            if (in_array($_parts[0], $this->_validNamespaces)) {
                return $_parts[0];
            }
        }

        return null;
    }

    public function setContent($data)
    {
        $this->_data = $data;
    }

    //
    // TODO: See getTransport() comments.  This needs to be changed as well
    //
    public function getContent($decodeAsArray)
    {
        return $this->_transport->decode($this->_data, $decodeAsArray);
    }

    //
    // TODO: Use the 'Accept' and 'Content-Type' headers to determine our actual transport
    //
    public function getTransport()
    {
        return new JSON();
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
                    $ret = ['type' => $type, 'user' => $user, 'password' => $password];
                    break;

                //
                // TODO: See if this works properly... this just forwards the auth token forward
                //
                case 'Oauth':
                    $ret['token'] = $data;
                    $ret['type']  = $type;
                    break;
            }
        } else {
            return false;
        }

        return (object)$ret;
    }

    public function normalizePath($path)
    {
        $path = trim($path, '/');
        return empty($path) ? '/' : $path;
    }

}

?>