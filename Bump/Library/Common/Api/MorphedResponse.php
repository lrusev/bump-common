<?php
namespace Bump\Library\Common\Api;

use Bump\Library\Common\Utils;
use Symfony\Component\HttpFoundation\Response as HttpResponse;


class MorphedResponse extends Response
{
    protected $morphedData;

    public function __construct(HttpResponse $response, $morphedData)
    {
        $this->morphedData = $morphedData;
        parent::__construct($response);
    }

    protected function morph($content)
    {
        return $this->morphedData;
    }

    public function getContentType()
    {
        return $this->getOriginalResponse()->headers->get('Content-type');
    }

    public function send($return=false)
    {
        $response = new HttpResponse($this->__toString(), 200, array('Content-Type'=>$this->getContentType()));
        return $return?$response:$response->send();
    }

    public function getHash($prefix='')
    {
        return md5($prefix . serialize($this->getData()));
    }

    public function __toString()
    {
        return json_encode($this->getData());
    }
}