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

// launch Sappy with a default namespace of v1
//   supply multiple values to namespace under different circumstances
$api = new \Sappy\App(['v1', 'v2']);


$api->route('/user/:user', function($request, $response, $params) use ($api) {
    // $response is just an initialized \Sappy\Response object
    // $request contains all server generated request headers and such
    // $params is an array of parsed params as according to the route ($params->user)

    $user = [
        'name'  => $params->user,  // pseudocode, yay!
        'id'    => 1492,
        'email' => 'andrew.heebner@gmail.com'
    ];

    if ($user) {
        $response->write(200, $user);
    } else {
        $response->write(404, ['error' => 'User not found']);
    }

    return $response;

}, ['v2']);   // <-- this specifies to only capture this route callback on the v2 namespace


$api->run();

?>