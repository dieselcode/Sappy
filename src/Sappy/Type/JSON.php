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

namespace Sappy\Type;

use Sappy\Exceptions\HTTPException;
use Sappy\Event;

/**
 * JSON class
 *
 * Used for extending native json handling
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2013, Andrew Heebner
 * @license     MIT
 * @package     Sappy
 */
class JSON
{
    /**
     * @var string
     */
    private $_contentType = 'application/json';


    public function __construct()
    {
    }

    /**
     * Encode array as a JSON representation
     *
     * @param  array $message
     * @return string
     * @throws HTTPException
     */
    public function encode($message)
    {
        $data = json_encode($message);

        if (($error = $this->_handleError()) !== true) {
            Event::emit('error', [new HTTPException($error, 500), $this]);
        }

        return $data;
    }

    /**
     * Decode JSON as object/array
     *
     * @param  string   $message
     * @param  bool     $decodeAsArray
     * @return string
     * @throws HTTPException
     */
    public function decode($message, $decodeAsArray = false)
    {
        $data = json_decode($message, $decodeAsArray);

        if (($error = $this->_handleError()) !== true) {
            Event::emit('error', [new HTTPException($error, 500), $this]);
        }

        return $data;
    }

    /**
     * Handle JSON errors, and pass them on
     *
     * @return bool|string
     */
    private function _handleError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $message = true;
                break;
            case JSON_ERROR_DEPTH:
                $message = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $message = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $message = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $message = 'Unknown error';
                break;
        }

        return ($message !== true) ? 'JSON Error: ' . $message : $message;
    }

    /**
     * Return current content type
     *
     * @return string
     */
    public function getContentType()
    {
        return $this->_contentType;
    }

}