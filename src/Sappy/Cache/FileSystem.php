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

namespace Sappy\Cache;

class FileSystem
{

    protected $_cachePath = '';
    private   $_cacheExt  = '.json';


    public function __construct($cachePath = '/')
    {
        $this->setCachePath($cachePath);
    }

    public function setCachePath($cachePath = '/')
    {
        if (substr($cachePath, -1) !== '/') $cachePath .= '/';
        $this->_cachePath = $cachePath;
    }

    public function setCache($name, $contents)
    {
        if ($this->exists($name)) {
            if (file_put_contents($this->_getFullCachePath($name), serialize($contents))) {
                return true;
            }
        }

        return false;
    }

    public function getAge($name)
    {
        return filemtime($this->_getFullCachePath($name));
    }

    public function getCache($name)
    {
        if ($this->exists($name)) {
            return unserialize(file_get_contents($this->_getFullCachePath($name)));
        }

        return null;
    }

    public function exists($name)
    {
        return !!file_exists($this->_getFullCachePath($name));
    }

    private function _getFullCachePath($name)
    {
        return $this->_cachePath . $name . $this->_cacheExt;
    }

}

?>