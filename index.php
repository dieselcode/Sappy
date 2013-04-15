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

require_once 'vendor/autoload.php';

$options = [
    'use_output_compression' => true,      // enable support for output compression (gzip, deflate, etc.)
    'generate_content_md5'   => true,      // generate Content-MD5 header
    'cache_control'          => false,     // set to an integer to enable (int is max age in seconds);
                                           //  false to turn off (no-cache)
    'require_user_agent'     => true,      // require the request to have a valid user agent string
];

$api = new \Sappy\App(
    ['v1', 'v2'],           // allowed namespaces
    $options
);

// testing extending capabilities
$api->extend('foo', function() {
    return [$this->getUserAgent(), $this->getRemoteAddr()];
});

//
// Auth events need only return true or false
//
//  __auth__ is a builtin event name (cannot be overridden)
//
// capture auth event (this is called when a method requires auth)
//  auth events must return true or false
//
$api->on('__AUTH__', function($auth, $request) use ($api) {
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
// Fake rate limit event callback
//
$api->on('rate.limit.headers', function($remoteAddr) use ($api) {
    // .. do something with the remoteAddr
    return ['X-RateLimit-Limit' => 5000, 'X-RateLimit-Remaining' => 4999];
});


/**
 * Testing extend above
 */
$api->route('/test_extend', function() use ($api) {
    $api->get(function($request, $response, $params) use ($api) {
        $response->write(200, $api->foo());

        return $response;
    });
});


/**
 * For debugging... viewing headers
 *
 * available on any defined namespace
 */
$api->route('/headers', function() use ($api) {

    $api->get(function($request, $response, $params) use ($api) {
        $rateHeaders = $api->emit('rate.limit.headers', [$request->getRealRemoteAddr()]);

        $response->write(200, $request->getHeaders());  // send the request headers back
        $response->headers($rateHeaders);

        return $response;
    });

    $api->head(function($request, $response, $params) use ($api) {
        $rateHeaders = $api->emit('rate.limit.headers', [$request->getRealRemoteAddr()]);

        $response->write(200);   // this is a head request, send no content; just a status
        $response->headers($rateHeaders);

        return $response;
    });

    $api->post(function($request, $response, $params) use ($api) {
        $json = $request->getContent();
        $response->write(200, $json);

        return $response;
    });

});

// run the API model
$api->run();

?>