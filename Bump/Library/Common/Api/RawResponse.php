<?php
namespace Bump\Library\Common\Api;

use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RawResponse implements ApiResponse, \Serializable
{
    public function __construct(HttpResponse $response)
    {
        $this->originalResponse = $response;
    }

    public function send($return = false)
    {
        $response = new HttpResponse($this->getData(), 200, array('Content-Type' => $this->getContentType()));
        return $return ? $response : $response->send();
    }

    public function getData()
    {
        return $this->originalResponse->getContent();
    }

    public function getContentType()
    {
        return $this->getOriginalResponse()->headers->get('Content-Type', 'text/html');
    }

    public function getOriginalResponse()
    {
        return $this->originalResponse;
    }

    public function __toString()
    {
        return $this->getData();
    }

    public function isSuccessful()
    {
        return $this->originalResponse->isSuccessful();
    }

    public function normalize()
    {
        return $this;
    }

    public function getHash($prefix = '')
    {
        return md5($prefix . $this->getOriginalResponse()->getContent());
    }

    public function serialize()
    {
        $data = array(
            'content' => $this->originalResponse->getContent(),
            'headers' => $this->originalResponse->headers->all(),
            'status' => $this->originalResponse->getStatusCode()
        );

        return serialize($data);
    }

    public function unserialize($data)
    {
        $data = unserialize($data);
        $this->originalResponse = new HttpResponse($data['content'], $data['status'], $data['headers']);
    }

}