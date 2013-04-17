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

class TestApi extends Sappy\App
{

    private $options = array(
        'use_output_compression' => true,
        'generate_content_md5'   => true,
        'cache_control'          => false,
        'require_user_agent'     => true,
        'http_send_keepalive'    => false,
        'allow_app_extending'    => true,
    );


    public function __construct($namespaces = [])
    {
        parent::__construct($namespaces, $this->options);

        $this->setExtendables();
        $this->setCallbacks();
        $this->setRoutes();
    }

    public function setExtendables()
    {
        // becomes available as $this->foo()
        $this->extend('foo', function() {
            return [$this->getUserAgent(), $this->getRemoteAddr()];
        });
    }

    public function setCallbacks()
    {
        // internal callback, used for all auth requests
        $this->on('__AUTH__', function($auth, $request) {
            if (is_object($auth)) {
                if ($auth->type == 'Basic') {
                    if ($auth->user == 'Andrew' && $auth->password == 'foo') {
                        return true;
                    }
                }
            }

            return false;
        });

        // dummy callback, just shows how to add return headers to be used
        $this->on('rate.limit.headers', function($remoteAddr) {
            // .. do something with the remoteAddr
            return ['X-RateLimit-Limit' => 5000, 'X-RateLimit-Remaining' => 4999];
        });
    }

    public function setRoutes()
    {
        // test the extendables... $this->foo()
        $this->route('/test_extend', function() {
            $this->get(function($request, $response, $params) {
                $array = array_merge($this->foo(), $request->getHeaders());
                $response->write(200, $array);

                return $response;
            }, true);   // require auth to use this method
        });

        // send the request headers back to the user
        $this->route('/headers', function() {
            $this->get(function($request, $response, $params) {
                $rateHeaders = $this->emit('rate.limit.headers', [$request->getRealRemoteAddr()]);

                $response->write(200, $request->getHeaders());  // send the request headers back
                $response->headers($rateHeaders);

                return $response;
            });
        });
    }
}

$api = new TestApi(['v1','v2']);
$api->run();

?>