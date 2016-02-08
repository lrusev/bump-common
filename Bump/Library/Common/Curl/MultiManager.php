<?php
namespace Bump\Library\Common\Curl;

use Symfony\Component\HttpFoundation\Response;
use Zeroem\CurlBundle\Curl\MultiManager as Base;
use Zeroem\CurlBundle\Curl\Collector\HeaderCollector;
use Bump\Library\Common\Curl\Request as CurlRequest;
use Zeroem\CurlBundle\Curl\Request as BaseRequest;

class MultiManager extends Base
{
    protected static $errors = array(
        CURLM_BAD_HANDLE      => 'Bad Handle',
        CURLM_BAD_EASY_HANDLE => 'Bad Easy Handle',
        CURLM_OUT_OF_MEMORY   => 'Out of Memory',
        CURLM_INTERNAL_ERROR  => 'Internal Error'
    );

    protected $headers = array();

    public function getResponse(CurlRequest $request)
    {
        $content = $this->getContent($request);
        if (($headers = $this->findHeaders($request))) {
            $headers = $headers->retrieve();
        } else {
            $headers = array();
        }

        $statusCode = $request->getInfo(CURLINFO_HTTP_CODE);

        $response = new Response(
            $content,
            $statusCode,
            $headers
        );

        return $response;
    }

    public function addRequest(BaseRequest $request) {
        parent::addRequest($request);
        $oid = spl_object_hash($request);

        if (!isset($this->headers[$oid])) {
            $this->headers[$oid] = new HeaderCollector();
            $request->setOption(CURLOPT_HEADERFUNCTION, array($this->headers[$oid], "collect"));
        }

        return $this;
    }

    public function isEmpty()
    {
        return count($this->headers)===0;
    }

    public function findHeaders(BaseRequest $request)
    {
        $oid = spl_object_hash($request);
        if (isset($this->headers[$oid])) {
            return $this->headers[$oid];
        }

        return false;
    }
}