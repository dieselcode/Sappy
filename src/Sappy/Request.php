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

use Sappy\Transport\JSON;

abstract class Request
{

    protected static $_validNamespaces     = [];
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

    /**
     * TODO: For the following four methods...
     *
     *  Break down the return output to a list of options that the client sends.
     *  Order the list by priority and preference, base on what the breakdown returns.
     */
    public static function getAccept()
    {
        return isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : null;
    }

    public static function getAcceptCharset()
    {
        return isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : null;
    }

    public static function getAcceptLanguage()
    {
        return isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null;
    }

    public static function getAcceptEncoding()
    {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : null;
    }

    //
    // -------------------------------------------------------------
    //

    public static function getUserAgent()
    {
        return (self::hasUserAgent()) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    public static function hasUserAgent()
    {
        return !!isset($_SERVER['HTTP_USER_AGENT']);
    }

    public static function getHeaders()
    {
        return static::$_requestHeaders;
    }

    public static function getHeader($header)
    {
        return isset(static::$_requestHeaders[$header]) ? static::$_requestHeaders[$header] : null;
    }

    public static function getRequestPath()
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

    public static function getNamespace()
    {
        if (!empty(static::$_validNamespaces)) {
            $_parts = explode('/', static::$_requestPath);
            if (in_array($_parts[0], static::$_validNamespaces)) {
                return $_parts[0];
            }
        }

        return null;
    }

    public static function setContent($data)
    {
        static::$_data = JSON::decode($data);
    }

    public static function getContent()
    {
        return static::$_data;
    }

    public static function getAuthData()
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
                // TODO: See if this works properly... this just forwards the auth token
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