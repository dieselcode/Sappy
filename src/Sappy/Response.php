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

    private $_message       = '';
    private $_httpCode      = 200;
    private $_noBody        = ['head', 'options'];
    private $_app           = null;
    private $_transport     = null;

    private $_validCodes    = array(
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
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
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
    );


    public function __construct($app)
    {
        if ($app instanceof App) {
            $this->_app       = $app;
            $this->_transport = $this->_app->transport();
        } else {
            Event::emit('error', [
                new HTTPException('Invalid application passed to response object', 500),
                $this
            ]);
        }
    }

    /**
     * Write status code and data to a buffer for an HTTP packet
     *
     * @param  integer $httpCode
     * @param  mixed   $message
     * @return object
     */
    public function write($httpCode, $message)
    {
        if (array_key_exists($httpCode, $this->_validCodes)) {
            $this->_httpCode = $httpCode;
            $this->_message  = $message;
        } else {
            Event::emit('error', [
                new HTTPException('Invalid HTTP response code was supplied', 500),
                $this
            ]);
        }

        return $this;
    }

    /**
     * Send header and content buffer to client
     *
     * @param  array $addedHeaders
     * @return void
     */
    public function send($addedHeaders = [])
    {
        $data = $this->_transport->encode($this->_message);

        //
        // TODO: Implement cache control
        //

        http_response_code($this->_httpCode);
        header(sprintf('Status: %d %s', $this->_httpCode, $this->_validCodes[$this->_httpCode]));

        header('Connection: close', true);
        header('X-Powered-By: ' . $this->_app->getSignature(), true);

        if (!in_array(strtolower($this->_app->getRequestMethod()), $this->_noBody)) {
            header('Content-Type: ' . $this->_transport->getContentType(), true);
            header('Content-Length: ' . strlen($data), true);
            header('Content-MD5: ' . base64_encode(md5($data, true)), true);
        }

        if (!empty($addedHeaders)) {
            foreach ($addedHeaders as $k => $v) {
                header(sprintf('%s: %s', $k, $v), true);
            }
        }

        // HEAD and OPTIONS requests don't get a content body, just the headers
        if (!in_array(strtolower($this->_app->getRequestMethod()), $this->_noBody)) {
            echo $data;
        }

        exit;
    }

}

?>