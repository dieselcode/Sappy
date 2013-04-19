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


    /**
     * Return the current request ID
     *
     * @return null|string
     */
    public function getRequestId()
    {
        return static::$_requestId;
    }

    /**
     * Return the current request method (GET, POST, ...)
     *
     * @return string
     */
    public static function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Check if the request was done securely
     *
     * @return bool
     */
    public static function isSecure()
    {
        return !!(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
    }

    /**
     * Check if the request was made via AJAX
     *
     * @return bool
     */
    public static function isAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return !!(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        }

        return false;
    }

    /**
     * Return the requested HTTP version
     *
     * @return float
     */
    public static function getHTTPVersion()
    {
        return (float)substr($_SERVER['SERVER_PROTOCOL'], -3);
    }

    /**
     * Get the requester's IP address
     *
     * @return null|float
     */
    public static function getRemoteAddr()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
     * Get the requester's IP address (cycle through proxies)
     * @return null|float
     */
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

    public static function getAccept()
    {
        return isset($_SERVER['HTTP_ACCEPT']) ?
            static::_parseHeaderValue($_SERVER['HTTP_ACCEPT']) :
            null;
    }

    public static function getAcceptCharset()
    {
        return isset($_SERVER['HTTP_ACCEPT_CHARSET']) ?
            static::_parseHeaderValue($_SERVER['HTTP_ACCEPT_CHARSET']) :
            null;
    }

    public static function getAcceptLanguage()
    {
        return isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ?
            static::_parseHeaderValue($_SERVER['HTTP_ACCEPT_LANGUAGE']) :
            null;
    }

    public static function getAcceptEncoding()
    {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) ?
            static::_parseHeaderValue($_SERVER['HTTP_ACCEPT_ENCODING']) :
            null;
    }

    /**
     * Get the HTTP cookies for the current request
     *
     * @param  null       $cookieName
     * @return array|null
     */
    public function getCookies($cookieName = null)
    {
        $out = [];
        $cookies = static::getHeader('Cookie');

        if (!is_null($cookies)) {
            foreach (explode('; ', $cookies) as $k => $v) {
                $out[$k] = $v;
            }

            if (!empty($cookieName)) {
                return isset($out[$cookieName]) ? $out[$cookieName] : null;
            } else {
                return $out;
            }
        }

        return null;
    }

    /**
     * Get the requester's user agent
     *
     * @return null|string
     */
    public static function getUserAgent()
    {
        return (self::hasUserAgent()) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    /**
     * Check if the request has a user agent
     *
     * @return bool
     */
    public static function hasUserAgent()
    {
        return !!isset($_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * Return all request headers
     *
     * @return array
     */
    public static function getHeaders()
    {
        return static::$_requestHeaders;
    }

    /**
     * Return a specified request header
     *
     * @param  $header
     * @return null|string
     */
    public static function getHeader($header)
    {
        return isset(static::$_requestHeaders[$header]) ? static::$_requestHeaders[$header] : null;
    }

    /**
     * Return the current *real* request path (sans namespace)
     * @return null|string
     */
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

    /**
     * Get the current requested namespace
     *
     * @return null
     */
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

    /**
     * Set the current request content
     *
     * @param  $data
     * @return void
     */
    public static function setContent($data)
    {
        static::$_data = JSON::decode($data);
    }

    /**
     * Return the current request content
     *
     * @return mixed
     */
    public static function getContent()
    {
        return static::$_data;
    }

    /**
     * Get authorization data via HTTP headers
     *
     * @return bool|object
     */
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
                case 'OAuth':
                    $ret['type']  = $type;
                    $ret['token'] = $data;
                    break;
            }
        } else {
            return false;
        }

        return (object)$ret;
    }

    /**
     * Normalize a route/request path
     *
     * @param  $path
     * @return string
     */
    public static function normalizePath($path)
    {
        $path = trim($path, '/');
        return empty($path) ? '/' : $path;
    }

    /**
     * Set the current request headers
     *
     * @return void
     */
    protected static function _setRequestHeaders()
    {
        $http = [];

        $parseHeader = function ($name) {
            $out   = [];
            $parts = explode('_', $name);
            array_shift($parts);

            foreach ($parts as $value) {
                $out[] = ucfirst(strtolower($value));
            }

            return join('-', $out);
        };

        foreach ($_SERVER as $k => $v) {
            if (substr($k, 0, 5) == 'HTTP_') {
                $http[$parseHeader($k)] = $v;
            } elseif ($k == 'CONTENT_TYPE') {
                $http['Content-Type'] = $v;
            }
        }

        static::$_requestHeaders = $http;
    }

    /**
     * Parse a header line into an array based on preference
     *
     * @param  $value
     * @return array
     */
    private static function _parseHeaderValue($value)
    {
        $out = [];
        $data = explode(',', str_replace(' ', '', $value));

        foreach ($data as $val) {

            $_val = $val;
            $_qval = 1.0;

            if (false !== strstr($val, ';q=')) {
                list($_val, $tmp) = explode(';', $val);
                $q = explode('=', $tmp);
                $_qval = (float)$q[1];
            }

            $out[$_val] = $_qval;
        }

        arsort($out);
        return $out;
    }

}

?>