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

/**
 * Request class
 *
 * Used for parsing incoming HTTP requests
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2013, Andrew Heebner
 * @license     MIT
 * @package     Sappy
 */
class Request extends App
{

    /**
     * @const AUTH_BASIC Define basic authorization
     */
    const AUTH_BASIC        = 'Basic';

    /**
     * @const AUTH_OAUTH Define Oauth authorization
     */
    const AUTH_OAUTH        = 'Oauth';

    /**
     * @var string
     */
    protected $_path        = '';
    /**
     * @var array
     */
    protected $_vars        = [];
    /**
     * @var array
     */
    protected $_validNamespaces  = [];
    /**
     * @var null|string
     */
    protected $_data        = null;
    /**
     * @var null|Type\JSON
     */
    protected $_transport   = null;

    /**
     * Request constructor
     *
     * @param array $namespaces
     */
    public function __construct(array $namespaces = [])
    {
        $path        = trim($_SERVER['REQUEST_URI'], '/');
        $this->_path = empty($path) ? '/' : $path;

        $this->_vars            = array_merge($_SERVER, getallheaders());
        $this->_validNamespaces = $namespaces;
        $this->_transport       = $this->getTransport();
        $this->_data            = @file_get_contents('php://input');
    }

    /**
     * Returns transport object to be used in Response
     *
     * @return object
     */
    public function getTransport()
    {
        //
        // TODO: Implement more transports
        //
        switch ($this->_vars['Content-Type']) {
            default:
            case 'application/json':
                return new JSON();
                break;
        }
    }

    /**
     * Return HTTP request method
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->_vars['REQUEST_METHOD'];
    }

    /**
     * Return currently requested path
     *
     * @return string
     */
    public function getPath()
    {
        if (!empty($this->_validNamespaces)) {
            $_parts = explode('/', $this->_path);
            if (in_array($_parts[0], $this->_validNamespaces)) {
                array_shift($_parts);
                return join('/', $_parts);
            }
        }

        return $this->_path;
    }

    /**
     * Return the incoming HTTP version
     *
     * @return float
     */
    public function getHTTPVersion()
    {
        @list(,$version) = explode('/', $this->_vars['SERVER_PROTOCOL'], 2);
        return $version;
    }

    /**
     * Return currently used namespace
     *
     * @return string|null
     */
    public function getNamespace()
    {
        if (!empty($this->_validNamespaces)) {
            $_parts = explode('/', $this->_path);
            if (in_array($_parts[0], $this->_validNamespaces)) {
                return $_parts[0];
            }
        }

        return null;
    }

    /**
     * Return all PHP server variables and HTTP request variables
     * @return array
     */
    public function getHeaders()
    {
        return $this->_vars;
    }

    /**
     * Get a specific PHP server value or HTTP header value
     *
     * @param  string $header HTTP Header key to retrieve
     * @return string|null
     */
    public function getHeader($header)
    {
        return isset($this->_vars[$header]) ? $this->_vars[$header] : null;
    }

    /**
     * Get the incoming HTTP content
     *
     * @param  bool $decodeAsArray Decode the json data as an array, rather than object
     * @return object|array
     */
    public function getContent($decodeAsArray = false)
    {
        return $this->_transport->decode($this->_data, $decodeAsArray);
    }

    /**
     * Get current authorization header data
     *
     * @return bool|object
     */
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

                //
                // TODO: See if this works properly... this just forwards the auth token forward
                //
                case 'Oauth':
                    $ret['token'] = $data;
                    break;
            }
        } else {
            return false;
        }

        return (object)$ret;
    }

}

?>