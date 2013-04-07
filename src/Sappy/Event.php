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
 * Event class
 *
 * Holds all events to be called/generated within Sappy
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2013, Andrew Heebner
 * @license     MIT
 * @package     Sappy
 */
class Event
{

    static $_eventHandlers = [];

    /**
     * Set an event handler
     *
     * @param string   $event
     * @param callable $callback
     */
    public static function on($event, callable $callback)
    {
        static::$_eventHandlers[$event] = $callback;
    }

    /**
     * Emit an event with data, and return the response
     *
     * @param  string     $event
     * @param  array      $args
     * @return mixed|null
     */
    public static function emit($event, array $args = [])
    {
        if (static::hasEvent($event)) {
            $callback = static::$_eventHandlers[$event];
            $response = call_user_func_array($callback, $args);

            // normally used for error events
            if ($response instanceof Response) {
                $headers = ($args[0] instanceof HTTPException) ? $args[0]->getHeaders() : [];
                $response->send(null, $headers);
            } else {
                return $response;
            }
        }

        return null;
    }

    /**
     * Check if an event handler exists
     *
     * @param  string $event
     * @return bool
     */
    public static function hasEvent($event)
    {
        return !!isset(static::$_eventHandlers[$event]);
    }

}

?>