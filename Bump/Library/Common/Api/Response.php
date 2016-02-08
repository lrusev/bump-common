<?php

namespace Bump\Library\Common\Api;

use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Doctrine\Common\Collections\ArrayCollection;

abstract class Response extends ArrayCollection implements \Serializable, ApiResponse
{
    protected $originalResponse;
    protected $successful;
    protected $normalized = false;
    protected $debug = false;

    public function __construct(HttpResponse $response)
    {
        $this->originalResponse = $response;
        $this->originalResponse->setContent(trim($this->originalResponse->getContent()));

        try {
            $data = $this->morph($response->getContent());
            if (!is_array($data)) {
                $this->successful = false;
                parent::__construct();
            } else {
                parent::__construct($data);
            }

        } catch (\Excetpion $e) {
            $this->successful = false;
            parent::__construct();
        }
    }

    public function isSuccessful()
    {
        return is_null($this->successful)?$this->originalResponse->isSuccessful():$this->successful;
    }

    public function getData()
    {
        return $this->toArray();
    }

    public function getOriginalResponse()
    {
        return $this->originalResponse;
    }

    public function setDebug($flag=true)
    {
        $this->debug = (bool)$debug;

        return $this;
    }

    public function getDebug()
    {
        return $this->debug;
    }

    public function __call($name, $args)
    {
        if (method_exists($this->originalResponse, $name)) {
            return call_user_func_array(array($this->originalResponse, $args));
        }

        if ($this->debug) {
            throw new \BadMethodCallException("Try to call undefined method: {$name}");
        }

        return null;
    }

    public function has($key, $separator=null)
    {
        if (!is_null($separator)) {
            $keys = explode($separator, $key);
            $ref = $this;
            $has = false;
            foreach($keys as $k) {
                if (is_array($ref)) {
                    if (!isset($ref[$k])) {
                        $ref = null;
                        break;
                    }
                } else if (is_null(($ref = $ref->get($k)))) {
                    break;
                }
            }

            return !is_null($ref);
        }

        return null!==$this->get($key);
    }

    public function get($key, $separator=null)
    {
        if (!is_null($separator)) {
            $keys = explode($separator, $key);
            $key = $keys[count($keys)-1];
            $keys = array_slice($keys, 0, count($keys)-1);
            $ref = $this;
            $has = false;
            foreach($keys as $k) {
                if (is_array($ref)) {
                    if (!isset($ref[$k])) {
                        $ref=null;
                        break;
                    }
                } else if (is_null(($ref = $ref->get($k)))) {
                    break;
                }
            }

            return ($ref && isset($ref[$key]))?$ref[$key]:null;
        }

        return parent::get($key);
    }

    public function isNormalized()
    {
        return $this->normalized;
    }

    public function normalize()
    {
        if (!$this->isSuccessful() || $this->normalized) {
            return $this;
        }

        $data = $this->getData();
        $this->clear();
        $normalized = array();
        foreach($data as $key=>$value) {
            $nk = $this->normalizeKey($key);
            if (isset($normalized[$key])) {
                throw new \RuntimeException("Normalization conflict: {$key}->{$nk} already exists");
            }

            $normalized[$nk] = $value;
            $this->set($nk, $value);
        }

        $this->normalized = true;

        return $this;
    }

    public function normalizeKey($key)
    {
        $key = strtolower($key);
        $key = preg_replace(array("/[^0-9A-z]/i", "/_{2,}/"), array("_", "_"), $key);

        return $key;
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function serialize() {
        $data = array(
            'data'=>$this->getData(),
            'content'=>$this->originalResponse->getContent(),
            'headers'=>$this->originalResponse->headers->all(),
            'status'=>$this->originalResponse->getStatusCode(),
            'normalized'=>$this->isNormalized()
        );

        return serialize($data);
    }

    public function unserialize($data) {
        $data = unserialize($data);
        $this->clear();
        $this->originalResponse = new HttpResponse($data['content'], $data['status'], $data['headers']);
        $this->normalized = $data['normalized'];

        foreach($data['data'] as $key=>$val) {
            $this->set($key, $val);
        }
    }

    abstract protected function morph($content);
}