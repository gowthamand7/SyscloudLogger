<?php

namespace SyscloudLogger\SCLogger;

class sysCloudCache
{
    public static $prefix   = false;
    private static $adapter = false;

    private static function getAdapter()
    {
        if (self::$adapter == false) {
            //try to utilize the installed version of caching
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                self::$adapter = new winCacheSysCloudCacheAdapter();
            } else {
                self::$adapter = new ApcuSysCloudCacheAdapter();
            }

            //if none of the cache is enabled try to use file caching
            if (self::$adapter->isEnabled() == false) {
                self::$adapter = new fileSysCloudCacheAdapter();
                if (self::$adapter->isEnabled() == false) {
                    throw new Exception('Caching is not supported');
                }
            }
        }
        return self::$adapter;
    }

    public static function setPrefix($prefix)
    {
        self::$prefix = $prefix;
    }

    public static function getPrefix()
    {
        return self::$prefix;
    }

    public static function get($key)
    {
        return self::getAdapter()->get(self::$prefix . $key);
    }

    public static function set($key, $value, $ttl = 0)
    {
        return self::getAdapter()->set(self::$prefix . $key, $value, $ttl);
    }

    public static function hasKey($key)
    {
        return self::getAdapter()->hasKey(self::$prefix . $key);
    }

    public static function delete($key)
    {
        return self::getAdapter()->delete(self::$prefix . $key);
    }
}

interface cacheAdapter
{
    public function isEnabled();

    public function get($key);

    public function set($key, $value, $ttl);

    public function hasKey($key);

    public function delete($key);
}

class ApcuSysCloudCacheAdapter implements cacheAdapter
{
    public function isEnabled()
    {
        return false;
    }

    public function get($key)
    {
        return apcu_fetch($key);
    }

    public function set($key, $value, $ttl)
    {
        return apcu_add($key, $value, $ttl);
    }

    public function hasKey($key)
    {
        return apcu_exists($key);
    }

    public function delete($key)
    {
        return apcu_delete($key);
    }
}

class winCacheSysCloudCacheAdapter implements cacheAdapter
{
    public function isEnabled()
    {
        return false;
//        return function_exists('wincache_ucache_info') && !strcmp(
//            ini_get('wincache.ucenabled'),
//            "1"
//        );
    }

    public function get($key)
    {
        return wincache_ucache_get($key);
    }

    public function set($key, $value, $ttl)
    {
        return wincache_ucache_set($key, $value, $ttl);
    }

    public function hasKey($key)
    {
        return wincache_ucache_exists($key);
    }

    public function delete($key)
    {
        return wincache_ucache_delete($key);
    }
}

class fileSysCloudCacheAdapter implements cacheAdapter
{
    public function isEnabled()
    {
        if (is_dir(__DIR__ . '/tmp/') == false) {
            return mkdir(__DIR__ . '/tmp/', 0777, true);
        }
        return true;
    }

    public function get($key)
    {
        $data = $this->safeRead($key);

        if ($this->isExpired($data) == true) {
            return false;
        } else {
            return $data['value'];
        }
    }

    private function isExpired($data)
    {
        if (is_array($data) && count($data) > 0) {
            if ($data['expiresIn'] < time()) {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    private function safeRead($key)
    {
        $content = false;
        if (file_exists(__DIR__ . '/tmp/' . $key) == false) {
            return false;
        }
        $fp      = fopen(__DIR__ . '/tmp/' . $key, 'r+');
        if (flock($fp, LOCK_EX)) {
            $content = fread($fp, filesize(__DIR__ . '/tmp/' . $key));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return json_decode($content, true);
    }

    private function safeWrite($key, $value, $ttl)
    {
        $data = [
            'value' => $value,
            'expiresIn' => time() + $ttl
        ];
        $fp   = fopen(__DIR__ . '/tmp/' . $key, 'w+');
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, json_encode($data));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public function set($key, $value, $ttl)
    {
        $this->safeWrite($key, $value, $ttl);
    }

    public function hasKey($key)
    {
        $data = $this->safeRead($key);

        if ($this->isExpired($data) == true) {
            return false;
        } else {
            return true;
        }
    }

    public function delete($key)
    {
        $this->safeWrite($key, 0, -1);
    }
}
