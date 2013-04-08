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
use Sappy\Type\JSON;

abstract class Request
{

    protected $_validNamespaces     = [];
    protected $_allowedTypes        = [];
    protected $_requestPath         = null;
    protected $_requestHeaders      = [];
    protected $_transport           = null;
    protected $_data                = null;
    protected $_requestId           = null;
    protected $_useUTF8             = false;


    public function getRequestId()
    {
        return $this->_requestId;
    }

    public function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function getHTTPVersion()
    {
        @list(,$version) = explode('/', $_SERVER['SERVER_PROTOCOL'], 2);
        return $version;
    }

    public function getRemoteAddr()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    public function getRealRemoteAddr()
    {
        $remoteAddr = $this->getRemoteAddr();

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $remoteAddr = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $remoteAddr = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $remoteAddr;
    }

    public function getAllowedTypes()
    {
        return $this->_allowedTypes;
    }

    public function getAccept()
    {
        $acceptTypes  = [];
        $allowedTypes = $this->getAllowedTypes();

        if (isset($_SERVER['HTTP_ACCEPT'])) {
            $accept = strtolower(str_replace(' ', '', $_SERVER['HTTP_ACCEPT']));
            $accept = explode(',', $accept);

            foreach ($accept as $a) {
                $q = 1;

                if (strpos($a, ';q=')) {
                    list($a, $q) = explode(';q=', $a);
                }

                $acceptTypes[$a] = $q;
            }

            arsort($acceptTypes);

            // if we didn't define any allowed mimes, then just return
            if (empty($allowedTypes)) {
                return $acceptTypes;
            }

            $allowedTypes = array_map('strtolower', $allowedTypes);

            // let’s check our supported types:
            foreach ($acceptTypes as $mime => $q) {
                if ($q && in_array($mime, $allowedTypes)) {
                    return $mime;
                }
            }
        }

        // no mime-type found
        return null;
    }

    public function getCharset()
    {
        return $_SERVER['HTTP_ACCEPT_CHARSET'];
    }

    public function getUserAgent()
    {
        return ($this->hasUserAgent()) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    public function hasUserAgent()
    {
        return !!isset($_SERVER['HTTP_USER_AGENT']);
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
        // TODO: See below, make sure we're decoding the data as it comes in
        //  via the acceptable charset
        $this->_data = $data;
    }

    //
    // TODO: Refactor transports to use an interface
    //   Make sure we're properly using the enabled charsets if they were activated
    //
    public function getContent($decodeAsArray)
    {
        return $this->_transport->decode($this->_data, $decodeAsArray);
    }

    //
    // TODO: ensure we're getting the proper shit back
    //
    public function getTransport()
    {
        $accept = $this->getAccept();

        // user didn't specify, try and use our default handler
        if (is_null($accept)) {
            if (!empty($this->_allowedTypes)) {
                // grab the first off the list
                $accept = $this->_allowedTypes[0];
            } else {  // we have no defaults, throw a bad request error
                Event::emit('error', [
                    new HTTPException('Content negotiation failed.  No suitable transport found', 400),
                    $this
                ]);
            }
        }

        // all better now... deploy the proper transport layer
        switch ($accept) {
            //
            // TODO: Add more transports
            //
            default:
            case 'application/json':
                return new JSON();
                break;
        }
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