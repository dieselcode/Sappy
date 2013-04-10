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
use Sappy\Transport\JSON;

abstract class Request
{

    protected static $_validNamespaces     = [];
    protected static $_allowedTypes        = ['application/json'];
    protected static $_requestPath         = null;
    protected static $_requestHeaders      = [];
    protected static $_data                = null;
    protected static $_requestId           = null;


    public function getRequestId()
    {
        return static::$_requestId;
    }

    public static function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public static function isSecure()
    {
        return !!(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
    }

    public static function isAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return !!(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        }

        return false;
    }

    public static function getHTTPVersion()
    {
        @list(,$version) = explode('/', $_SERVER['SERVER_PROTOCOL'], 2);
        return $version;
    }

    public static function getRemoteAddr()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    public static function getRealRemoteAddr()
    {
        $remoteAddr = self::getRemoteAddr();

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $remoteAddr = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $remoteAddr = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $remoteAddr;
    }

    //
    // TODO: Change this to just parse the Accept header (and convert to static)
    //
    public function getAccept()
    {
        $acceptTypes  = [];
        $allowedTypes = static::$_allowedTypes;

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

    //
    // TODO: Parse the options out, based on preference
    //
    public static function getCharset()
    {
        return $_SERVER['HTTP_ACCEPT_CHARSET'];
    }

    public static function getUserAgent()
    {
        return (self::hasUserAgent()) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    public static function hasUserAgent()
    {
        return !!isset($_SERVER['HTTP_USER_AGENT']);
    }

    public function getHeaders()
    {
        return static::$_requestHeaders;
    }

    public function getHeader($header)
    {
        return isset(static::$_requestHeaders[$header]) ? static::$_requestHeaders[$header] : null;
    }

    public function getRequestPath()
    {
        if (!empty(static::$_validNamespaces)) {
            $_parts = explode('/', static::$_requestPath);
            if (in_array($_parts[0], static::$_validNamespaces)) {
                array_shift($_parts);
                return join('/', $_parts);
            }
        }

        return static::$_requestPath;
    }

    public function getNamespace()
    {
        if (!empty(static::$_validNamespaces)) {
            $_parts = explode('/', static::$_requestPath);
            if (in_array($_parts[0], static::$_validNamespaces)) {
                return $_parts[0];
            }
        }

        return null;
    }

    public function setContent($data)
    {
        try {
            static::$_data = JSON::decode($data);
        } catch (HTTPException $e) {
            Event::emit('error', [$e, $this]);
        }
    }

    public function getContent()
    {
        return static::$_data;
    }

    public function getAuthData()
    {
        $auth = static::getHeader('Authorization');
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

    public static function normalizePath($path)
    {
        $path = trim($path, '/');
        return empty($path) ? '/' : $path;
    }

    protected static function _setRequestHeaders()
    {
        static::$_requestHeaders = getallheaders();
    }

}

?>