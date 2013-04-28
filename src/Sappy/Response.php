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

/**
 * Response class
 *
 * Used for sending standard HTTP responses back to the client
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2013, Andrew Heebner
 * @license     MIT
 * @package     Sappy
 */
class Response
{

    public  $cache          = null;

    private $_message       = '';
    private $_headers       = [];
    private $_httpCode      = 200;
    private $_noBody        = ['head', 'options'];

    private $_validCodes    = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        449 => 'Retry With',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended'
    ];


    /**
     * @param int   $httpCode
     * @param array $message
     */
    public function __construct($httpCode = 200, $message = [])
    {
        $this->write($httpCode, $message);
        $this->cache = new Cache(App::getOption('cache_directory'), App::getOption('cache_max_age'));
    }

    /**
     * Set additional headers for the response
     *
     * @param  array $headers
     * @return object
     */
    public function headers($headers = [])
    {
        $this->_headers = $headers;

        return $this;
    }

    /**
     * Get the added headers for the response
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    public function getMessage()
    {
        return $this->_message;
    }

    /**
     * Write status code and data to a buffer for an HTTP packet
     *
     * @param  integer $httpCode
     * @param  array   $message
     * @return object
     */
    public function write($httpCode, array $message = [])
    {
        if (array_key_exists($httpCode, $this->_validCodes)) {
            $this->_httpCode = $httpCode;
            $this->_message = $message;
        }

        return $this;
    }

    /**
     * Send header and content buffer to client
     *
     * @param  array $addedHeaders
     * @throws HTTPException
     * @return void
     */
    public function send($addedHeaders = [])
    {

        $data = JSON::encode($this->_message);
        $primaryHeaders = [];

        // this is the only place we need this function
        $processHeaders = function($headers = []) {
            if (!empty($headers)) {
                foreach ($headers as $k => $v) {
                    header(sprintf('%s: %s', $k, $v), true);
                }
            }
        };

        http_response_code($this->_httpCode);

        if ($this->_httpCode != 304) {
            $cache = $this->cache->get(App::getRealRequestPath());

            // no cache, or expired... set it.
            if ($cache === false) {
                $this->cache->set(App::getRealRequestPath(), $data);
                $lastMod = time();
                $cacheStatus = 'not-cached';
            } else {
                $data = $cache['content'];
                $lastMod = $cache['filemtime'];
                $cacheStatus = 'cached';
            }

            $primaryHeaders['X-Cache-Status'] = $cacheStatus;
            $primaryHeaders['Last-Modified']  = gmdate(\Sappy\DATE_RFC1123, $lastMod);
        }

        $primaryHeaders['ETag']             = $this->cache->getETag(APP::getRealRequestPath());
        $primaryHeaders['Status']           = sprintf('%d %s', $this->_httpCode, $this->_validCodes[$this->_httpCode]);
        $primaryHeaders['X-Powered-By']     = App::getSignature();

        if (!in_array(strtolower(App::getRequestMethod()), $this->_noBody) || $this->_httpCode != 304) {
            $primaryHeaders['Content-Type']   = JSON::getContentType();
            $primaryHeaders['Content-Length'] = strlen($data);
            $primaryHeaders['Content-MD5'] = base64_encode(md5($data, true));
        }

        $primaryHeaders['Cache-Control'] = 'public, max-age=' . App::getOption('cache_max_age');
        $primaryHeaders['Expires']       = gmdate(\Sappy\DATE_RFC1123, time() + App::getOption('cache_max_age'));
        $primaryHeaders['Pragma']        = 'cache';

        // process and output all of our response headers
        $processHeaders($primaryHeaders);
        $processHeaders($addedHeaders);
        $processHeaders($this->_headers);

        if (!in_array(strtolower(App::getRequestMethod()), $this->_noBody) || $this->_httpCode != 304) {
            if (App::getOption('use_output_compression') && extension_loaded('zlib')) {
                // ob_gzhandler sets the following headers for us:
                //  - Content-Encoding
                //  - Vary
                ob_start('ob_gzhandler');
            }

            echo $data;
        }

        exit;
    }

}

?>