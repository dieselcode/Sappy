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
// capture auth event (this is called when a method requires auth)
//  auth events must return true or false
//
$api->on('auth', function($auth, $request) use ($api) {
    if (is_object($auth)) {
        if ($auth->type == 'Basic') {
            if ($auth->user == 'Andrew' && $auth->password == 'foo') {
                return true;
            }
        }
    }

    return false;
});

//
// capture exceptions
//  error events must return a Response object, or false
//
$api->on('error', function($exception, $request) use ($api) {
    $response = new \Sappy\Response($request);
    $json_template = ['error' => $exception->getMessage()];
    $response->write($exception->getCode(), $json_template);

    return $response;
});


$api->route('/user/:user', function() use ($api) {
    // $response is just an initialized \Sappy\Response object
    // $request contains all server generated request headers and such
    // $params is an object of parsed params as according to the route ($params->user)

    $api->get(function($request, $response, $params) use ($api) {
        $user = [
            'name'  => $params->user,  // pseudocode, yay!
            'id'    => 1234,
            'email' => 'andrew.heebner@gmail.com'
        ];

        if ($user) {
            $response->write(200, $user);
        } else {
            $response->write(404, ['error' => 'User not found']);
        }

        return $response;
    });

    $api->post(function($request, $response, $params) use ($api) {
        $response->write(200, $request->getContent(true));
        return $response;
    }, true); // needs auth

}, ['v1']);   // only available on the v1 namespace


/**
 * For debugging... viewing headers
 *
 * Only available on v2 namespace, get() requires basic auth,
 * and sends custom headers.  The whole shebang
 */
$api->route('/headers', function() use ($api) {
    $api->get(function($request, $response, $params) {
        $response->write(200, $request->getHeaders());
        return $response;
    }, true); // needs auth

    $api->head(function($request, $response, $params) {
        $response->write(200);
        return $response;
    });

    $api->options(function($request, $response, $params) {
        $response->write(200);
        return $response;
    });

}, ['v2'])->headers([  // these are pseudo-code, but you could implement rate-limiting very easily
    'X-RateLimit-Limit' => 5000,
    'X-RateLimit-Remaining' => 4999
]);


// run the API model
$api->run();

?>