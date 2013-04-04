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

function prepareRequest($url, array $params = null)
{
    preg_match_all('/(:(\w+))/', $url, $matches);

    if (!empty($params)) {
        ksort($params);
        $paramKeys = array_keys($params);
        $paramValues = array_values($params);

        $urlKeys = array_keys(array_flip($matches[2]));
        sort($urlKeys);

        $urlParams = array_keys(array_flip($matches[1]));
        sort($urlParams);

        if ($paramKeys === $urlKeys) {
            return str_replace($urlParams, $paramValues, $url);
        }
    }

    return false;
}

function parseParams($url, $supplied = '')
{
    $obj = [];

    $params  = explode('/', $supplied);
    $request = explode('/', $url);

    foreach ($params as $k => $v) {
        if (substr($v, 0, 1) == ':') {
            $val = substr($v, 1);
            $obj[$val] = isset($request[$k]) ? $request[$k] : null;
        }
    }

    return (object)$obj;
}

var_dump( parseParams('/user/andrew/bar', '/user/:user/:foo') );
