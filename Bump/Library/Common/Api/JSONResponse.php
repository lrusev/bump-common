<?php
namespace Bump\Library\Common\Api;

use Bump\Library\Common\Utils;
use Symfony\Component\HttpFoundation\Response as HttpResponse;


class JSONResponse extends Response
{

    protected function morph($content)
    {
        $encoding = mb_detect_encoding($content, array('utf-8', 'iso-8859-1', 'windows-1251'));
        if ($encoding !== 'UTF-8') {
            $content = iconv($encoding, 'UTF-8', $content);
        }

        return Utils::parseJson($content);
    }

    public function getContentType()
    {
        return 'application/json';
    }

    public function send($return = false)
    {
        $response = new HttpResponse($this->__toString(), 200, array('Content-Type' => $this->getContentType()));
        return $return ? $response : $response->send();
    }

    public function getHash($prefix = '')
    {
        return md5($prefix . serialize($this->getData()));
    }

    public function __toString()
    {
        return json_encode($this->getData());
    }
}