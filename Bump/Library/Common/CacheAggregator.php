<?php
namespace Bump\Library\Common;

class CacheAggregator {

    protected $cache;
    protected $cachePrefix;
    protected $enabled = true;
    protected $defaultLifetime = 60;
    protected $lastCacheKey;

    public function __construct(\Doctrine\Common\Cache\Cache $cache, $cachePrefix='', $defaultLifetime=null)
    {
        $this->cache = $cache;
        $this->setCachePrefix($cachePrefix);
        $this->setDefaultLifetime($defaultLifetime);
    }

    public function enable()
    {
        $this->enabled = true;

        return $this;
    }

    public function disable()
    {
        $this->enabled = false;

        return $this;
    }

    public function isDisabled()
    {
        return !$this->enabled;
    }

    public function remember($key, \Closure $callback, $lifetime=null)
    {
        if ($this->isDisabled()) {
            return $callback();
        }

        $key = $this->prefixize($key);
        $lifetime = $this->lifetime($lifetime);

        if ($this->cache->contains($key)) {
            return $this->cache->fetch($key);
        }

        $this->cache->save($key, ($value = $callback()), $lifetime);
        return $value;
    }

    public function contains($key)
    {
        if ($this->isDisabled()) {
            return false;
        }
        return $this->cache->contains($this->prefixize($key));
    }

    public function fetch($key=null)
    {
        if ($this->isDisabled()) {
            return null;
        }

        if (is_null($key)) {
            if (is_null($this->lastCacheKey)) {
                throw new \InvalidArgumentException("Expected at least key, and data arguments");
            }

            $key = $this->lastCacheKey;
        } else {
            $key = $this->prefixize($key);
        }

        return $this->cache->fetch($key);
    }

    public function delete($key=null)
    {
        if (is_null($key)) {
            if (is_null($this->lastCacheKey)) {
                throw new \InvalidArgumentException("Expected at least key, and data arguments");
            }

            $key = $this->lastCacheKey;
        } else {
            $key = $this->prefixize($key);
        }

        return $this->cache->delete($this->prefixize($key));
    }

    public function save($key, $data=null, $lifetime=null)
    {
        if ($this->isDisabled()) {
            return false;
        }


        if (func_num_args()==1) {
            if (is_null($this->lastCacheKey)) {
                throw new \InvalidArgumentException("Expected at least key, and data arguments");
            }

            $data = $key;
            $key = $this->lastCacheKey;
        } else {
            $key = $this->prefixize($key);
        }

        $lifetime = $this->lifetime($lifetime);
        $this->cache->save($key, $data, $lifetime);
        $this->lastCacheKey = null;

        return $this;
    }

    protected function lifetime($lifetime=null)
    {
        if (is_null($lifetime)) {
            $lifetime = $this->getDefaultLifetime();
        }

        return $lifetime;
    }

    protected function prefixize($key=null)
    {
        if (is_null($key)) {
            if (is_null($this->lastCacheKey)) {
                throw new \InvalidArgumentException("Cache key should be specified.");
            }

            return $this->lastCacheKey;
        }

        if (is_array($key)) {
            $key = md5(serialize($key));
        } else if (is_object($key)) {
            if (method_exists($key, '__toString')) {
                $key = (string)$key;
            } else if (method_exists($key, 'toArray')) {
                $key = serialize($key->toArray());
            } else {
                $key = spl_object_hash($key);
            }
        }

        if (!empty($this->cachePrefix)) {
            $key = $this->cachePrefix . '.' . $key;
        }

        $this->lastCacheKey = $key;
        return $key;
    }


    public function setCachePrefix($prefix)
    {
        $this->cachePrefix = $prefix;
        return $this;
    }

    public function getCachePrefix()
    {
        return $this->cachePrefix;
    }

    public function __call($name, $args)
    {
        if (method_exists($this->cache, $name)) {
            return call_user_func_array(array($this->cache, $name), $args);
        }

        throw new \BadMethodCallException("Call to undefined method {$name}");
    }

    /**
     * Gets the value of defaultLifetime.
     *
     * @return mixed
     */
    public function getDefaultLifetime()
    {
        return $this->defaultLifetime;
    }

    /**
     * Sets the value of defaultLifetime.
     *
     * @param mixed $defaultLifetime the default lifetime
     *
     * @return self
     */
    public function setDefaultLifetime($defaultLifetime)
    {
        $this->defaultLifetime = (int)$defaultLifetime;

        return $this;
    }
}