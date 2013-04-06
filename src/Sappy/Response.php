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

    /**
     * @var string
     */
    private $_message   = '';
    /**
     * @var int
     */
    private $_httpCode  = 200;
    /**
     * @var null|Type\JSON
     */
    private $_transport = null;

    /**
     * Response constructor
     */
    public function __construct()
    {
        $this->_transport = new JSON();
    }

    /**
     * Write status code and data to a buffer for an HTTP packet
     *
     * @param  integer $httpCode
     * @param  array   $message
     * @return object
     */
    public function write($httpCode, array $message)
    {
        $this->_httpCode = $httpCode;
        $this->_message  = $message;

        return $this;
    }

    /**
     * Send header and content buffer to client
     *
     * @return void
     */
    public function send()
    {
        $data = $this->_transport->encode($this->_message);

        http_response_code($this->_httpCode);
        header('Content-Type: application/json', true);
        header('Content-Length: ' . strlen($data));
        header('X-Powered-By: Sappy/1.0 (http://www.github.com/dieselcode/Sappy)', true);
        echo $data;
        exit;
    }

}

?>