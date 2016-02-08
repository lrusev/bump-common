<?php
namespace Bump\Library\Common\Curl;

use Zeroem\CurlBundle\Curl\Request as Base;

class Request extends Base
{
    protected $id;
    protected $url;
    protected $method = 'GET';
    protected $params = array();
    protected $headers = array();

    static private $_methodOptionMap = array(
        "GET"=>CURLOPT_HTTPGET,
        "POST"=>CURLOPT_POST,
        "HEAD"=>CURLOPT_NOBODY,
        "PUT"=>CURLOPT_PUT
    );

    public function __construct($url, $method="GET", array $params=array())
    {
        $method = strtoupper($method);
        $this->method = $method;


        if (!empty($params) && in_array($method, array("GET", "PUT", "DELETE"))) {
            $url .= '?' . http_build_query($params);
        }

        $this->setUrl($url)
             ->setParams($params);

        parent::__construct($url);

        $this->setMethod($method);
        if (!empty($params) && in_array($method, array("POST", "PATCH"))) {
            $this->setOption(CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        }

        $this->setOption(CURLOPT_RETURNTRANSFER, true);
    }

    public function setId($id)
    {
        $this->id =$id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setHeader($key, $value)
    {
        $this->headers[$key] = $key . ': ' . $value;
        $this->setOption(CURLOPT_HTTPHEADER, array_values($this->headers));

        return $this;
    }

    protected function setUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw \InvalidArgumentException("Invalid URL: {$url}");
        }

        $this->url = $url;
        if (empty($this->id)) {
            $this->id = $url;
        }

        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getMethod()
    {
        return $this->method;
    }

    protected function setParams(array $params)
    {
        $this->params = $params;

        return $this;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function __toString() {
        return md5($this->url . $this->method . serialize($this->params));
    }

    /**
     * Convenience method for setting the appropriate cURL options based on the desired
     * HTTP request method
     *
     * @param resource $handle the curl handle
     * @param Request $request the Request object we're populating
     */
    public function setMethod($method) {
        if (isset(static::$_methodOptionMap[$method])) {
            return $this->setOption(static::$_methodOptionMap[$method],true);
        } else {
            return $this->setOption(CURLOPT_CUSTOMREQUEST,$method);
        }
    }
}