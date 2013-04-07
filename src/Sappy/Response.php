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

    private $_message   = '';
    private $_httpCode  = 200;
    private $_noBody    = ['head', 'options'];
    private $_app       = null;
    private $_transport = null;


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
        $this->_httpCode = $httpCode;
        $this->_message  = $message;

        return $this;
    }

    /**
     * Send header and content buffer to client
     *
     * @param  null|object   $route
     * @param  array         $addedHeaders
     * @return void
     */
    public function send($route = null, array $addedHeaders = [])
    {
        $data = $this->_transport->encode($this->_message);

        //
        // TODO: Implement cache control
        //

        http_response_code($this->_httpCode);
        header('Connection: close', true);
        header('X-Powered-By: ' . $this->_app->getSignature(), true);
        header('Vary: Accept, Authorization, Cookie', true);

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

        if ($route instanceof Route) {
            $route->exportRouteHeaders();
        }

        // HEAD and OPTIONS requests don't get a content body, just the headers
        if (!in_array(strtolower($this->_app->getRequestMethod()), $this->_noBody)) {
            echo $data;
        }

        exit;
    }

}

?>