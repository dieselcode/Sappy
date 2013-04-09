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

include 'vendor/autoload.php';

//
// For content negotiation to work, the user *MUST* send a valid 'Accept' header
//  with a preferable mime type, i.e.:  Accept: application/json
//
// Will result in a 400 Bad Request error being sent back to the user if nothing
//  suitable could be found.
//

$api = new \Sappy\App(
    ['v1', 'v2'],           // allowed namespaces
    ['application/json']    // allowed mime types (content negotiation)
);

//
// Auth events need only return true or false
//
//  __auth__ is a builtin event name (cannot be overridden)
//
// capture auth event (this is called when a method requires auth)
//  auth events must return true or false
//
$api->on('__auth__', function($auth, $request) use ($api) {
    if (is_object($auth)) {
        if ($auth->type == 'Basic') {
            if ($auth->user == 'Andrew' && $auth->password == 'foo') {
                return true;
            }
        }
    }

    return false;
});


/**
 * For debugging... viewing headers
 *
 * available on any defined namespace
 */
$api->route('/headers', function() use ($api) {

    $headers = [
        'X-RateLimit-Limit' => 5000,
        'X-RateLimit-Remaining' => 4999
    ];

    $api->get(function($request, $response, $params) use ($api, $headers) {
        $response->write(200, $request->getHeaders());  // send the request headers back
        $response->headers($headers);

        return $response;
    });

    $api->head(function($request, $response, $params) use ($headers) {
        $response->write(200);   // this is a head request, send no content
        $response->headers($headers);

        return $response;
    });

});


// run the API model
$api->run();

?>