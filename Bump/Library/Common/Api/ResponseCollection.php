<?php

namespace Bump\Library\Common\Api;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Bump\Library\Common\Utils;

class ResponseCollection extends ArrayCollection implements ApiResponse
{

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

    public function __get($key)
    {
        return $this->get($key);
    }

    public function normalize()
    {
        foreach($this as $response) {
            $response->normalize();
        }

        return $this;
    }

    public function getData()
    {
        $data = array();

        foreach($this as $key=>$response) {
            $data[$key] = $response->getData();
        }

        return $data;
    }

    public function getHash($prefix='')
    {
        return md5($prefix . serialize($this->getData()));
    }

    public function getContentType()
    {
        return get_class($this);
    }

    public function send($return=false)
    {
        $contentType = $this->first()->getContentType();
        $response = new HttpResponse($this->__toString(), 200);

        switch($contentType) {
            case 'application/json':
                $response->headers->set('Content-type', 'application/json');
            break;
            case 'application/xml':
                $response->headers->set('Content-type', 'application/xml');
            break;
            default:
                throw new \RuntimeException("Unsupported multi-response content type: {$contentType}");
        }

        return $return?$response:$response->send();
    }

    public function __toString()
    {
        $contentType = $this->first()->getContentType();
        $data = $this->getData();
        switch($contentType) {
            case 'application/json':
                $content = json_encode($data);
            break;
            case 'application/xml':
                $content = Utils::convertToXML($this->getData(), 'result', array($this, 'normalizeKey'));
            break;
            default:
                throw new \RuntimeException("Unsupported multi-response content type: {$contentType}");
        }

        return $content;
    }

    public function getOriginalResponse()
    {
        throw new \LogicException(__METHOD__ .  " shouldn't be called from collection.");
    }

    public function isSuccessful()
    {
        return $this->forAll(function($key, $item) {
            return $item->isSuccessful();
        });
    }

    public function getSuccessful()
    {
        $successful = $this->filter(function($item) {
            return $item->isSuccessful();
        });

        return $successful;
    }

    public function isAnySuccessful()
    {
        $successful = $this->getSuccessful();

        return $successful->count() > 0;
    }
}