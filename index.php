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

$api = new \Sappy\App(['v1', 'v2']);

//
// This Closure is called when we encounter an Authorization header (only if we enable authorization)
//   The below method handles BASIC auth
//
$api->auth(function($request, $auth) use ($api) {
    if (!empty($auth)) {
        if ($auth->user == 'andrew' && $auth->password == 'foo') {
            return true;
        }
    }

    return false;
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
    }, true); // needs auth

    $api->post(function($request, $response, $params) use ($api) {
        $response->write(200, $request->getContent(true));
        return $response;
    }, true); // needs auth

}, ['v1']);   // only available on the v1 namespace


/**
 * For debugging... viewing headers
 */
$api->route('/headers', function() use ($api) {
    $api->get(function($request, $response, $params) {
        $response->write(200, $request->getHeaders());
        return $response;
    });
}, ['v2']);  // only available on the v2 namespace


//
// Try and run the application; Catch all errors and exceptions and send them to the client
//   FYI: Thsi is very generic... you'd want better logging than this
//
$api->run(function($exception) use ($api) {
    $response = new \Sappy\Response();
    $json_template = ['error' => $exception->getMessage()];
    $response->write($exception->getCode(), $json_template)->send();
});

?>