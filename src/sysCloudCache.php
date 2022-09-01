<?php

namespace SyscloudLogger\SCLogger;

class sysCloudCache
{
    public static $prefix   = false;
    private static $adapter = false;
    private static function getAdapter()
    {
//        if (!self::$adapter) {
//            self::$adapter = new RedisSysCloudCacheAdapter('usersync.eqsbnb.clustercfg.use1.cache.amazonaws.com:6379');
//            if (!self::$adapter->isEnabled()) {
//                throw new Exception('Caching is not supported');
//            }
//        }
        if (!self::$adapter) {
            //try to utilize the installed version of caching
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                self::$adapter = new winCacheSysCloudCacheAdapter();
            } else {
                self::$adapter = new ApcuSysCloudCacheAdapter();
            }
            //if none of the cache is enabled try to use file caching
            if (!self::$adapter->isEnabled()) {
                self::$adapter = new fileSysCloudCacheAdapter();
                if (!self::$adapter->isEnabled()) {
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
class RedisSysCloudCacheAdapter implements cacheAdapter
{
    private $redis;
    public function __construct($redis_host) {
        $this->redis = new \RedisCluster(null, explode(",", $redis_host));
        $this->redis->setOption(\Redis::OPT_PREFIX, 'RedisSysCloudCache:');
    }
    public function isEnabled() {
        return (bool) $this->redis;
    }
    public function get($key)
    {
        return $this->redis->get($key);
    }
    public function set($key, $value, $ttl) {
        if ($ttl == 0) {
            return $this->redis->set($key, $value);
        } else {
            return $this->redis->set($key, $value, array('ex' => $ttl));
        }
    }
    public function hasKey($key)
    {
       return $this->redis->exists($key);
    }
    public function delete($key)
    {
        return $this->redis->del($key);
    }
}
class winCacheSysCloudCacheAdapter implements cacheAdapter
{
    public function isEnabled()
    {
        return false;
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
    private static $store = [];
    public function isEnabled()
    {
        if (!is_dir(__DIR__ . '/tmp/')) {
            return mkdir(__DIR__ . '/tmp/', 0777, true);
        }
        return true;
    }
    public function get($key)
    {
        if(isset(self::$store[$key]))
        {
            $data = self::$store[$key];
        }else {
        $data = $this->safeRead($key);
        }
        if ($this->isExpired($data)) {
            return false;
        } else {
            return $data['value'];
        }
    }
    private function isExpired($data)
    {
        if (is_array($data) && !empty(count($data))) {
            if ($data['expiresIn'] < time()) {
                return true;
            }
                return false;
            }
        return true;
    }
    private function safeRead($key)
    {
        $content = false;
        $path = __DIR__ . '/tmp/' . $key;
        if (!file_exists($path)) {
            return false;
        }
        $fp      = fopen($path, 'r+');
        if($fp == false)
        {
            $error = \error_get_last();
            throw new \Exception("Unable to read the local cache file {$path} ". \json_encode($error));
        }
        $size = filesize($path);
        if($size === 0)
        {
            fclose($fp);
            return [
                 'value' => '',
                'expiresIn' => 0
            ];
        }
        if (flock($fp, LOCK_EX)) {
            $content = fread($fp, $size);
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
        self::$store[$key] = $data;
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
        if(isset(self::$store[$key]))
        {
            $data = self::$store[$key];
        }else {
        $data = $this->safeRead($key);
        }
        if ($this->isExpired($data)) {
            return false;
        }
            return true;
        }
    public function delete($key)
    {
        $this->safeWrite($key, 0, -1);
    }
}
