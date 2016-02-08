<?php
namespace Bump\Library\Common\Api;

use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Bump\Library\Common\SimpleXMLElementExtended;
use Bump\Library\Common\Utils;


class XMLResponse extends Response
{

    protected function morph($content)
    {
        $encoding = mb_detect_encoding($content, array('utf-8', 'iso-8859-1', 'windows-1251'));
        if ($encoding!=='UTF-8') {
            $content = iconv($encoding, 'UTF-8', $content);
        }

        return json_decode(json_encode((array) @simplexml_load_string($content)),1);
    }

    public function getContentType()
    {
        return 'application/xml';
    }

    public function getHash($prefix='')
    {
        return md5($prefix . serialize($this->getData()));
    }

    public function send($return=false)
    {
        $response = new HttpResponse($this->__toString(), 200, array('Content-Type'=>$this->getContentType()));
        return $return?$response:$response->send();
    }

    public function __toString()
    {
        return Utils::convertToXML($this->getData(), 'result', array($this, 'normalizeKey'));
    }
}