<?php

namespace Bump\Library\Common\Api;

use Bump\Library\Common\CacheAggregator;
use Bump\Library\Common\Curl;
use Bump\Library\Common\Utils;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Inflector\Inflector;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Zeroem\CurlBundle\Curl\CurlEvents;
use Zeroem\CurlBundle\Curl\MultiInfoEvent;
use Zeroem\CurlBundle\Curl\MultiManager;
use Zeroem\CurlBundle\Curl\RequestGenerator;
use Zeroem\CurlBundle\HttpKernel\RemoteHttpKernel;


abstract class Base
{
    protected $remoteKernel;
    protected $timeout = 60;
    protected $baseUrl;
    protected $actionsMap;
    protected $defaultParams;
    protected $defaultContentType = "application/json";
    protected $defaultCurlOptions = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_NOSIGNAL => true,
    );
    protected $cache;

    public function __construct($baseUrl = null, array $actions = array(), array $defaultParams = array())
    {
        if (!is_null($baseUrl)) {
            $this->setBaseUrl($baseUrl);
        }

        $this->setActions($actions);
        $this->setDefaultParams($defaultParams);
        $this->setCache(new CacheAggregator(new ArrayCache()));
    }

    public function setBaseUrl($baseUrl)
    {
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid base URL: {$baseUrl}");
        }

        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function setCache(CacheAggregator $cache)
    {
        $this->cache = $cache;

        return $this;
    }

    public function getCache()
    {
        return $this->cache;
    }

    public function setActions(array $actions)
    {
        if (empty($actions)) {
            return $this;
        }

        $baseUrl = $this->getBaseUrl();

        $baseUrl = rtrim($baseUrl, '/');
        $this->actionsMap = array();

        foreach ($actions as $name => $action) {
            $url = $baseUrl . '/' . ltrim($action, '/');
            $parts = parse_url($url);
            if (is_numeric($name)) {
                $name = basename($parts['path']);
                if (false !== ($pos = strpos($name, '.'))) {
                    $name = substr($name, 0, $pos);
                }
            }

            $this->actionsMap[$name] = $url;
        }

        return $this;
    }

    public function getActions()
    {
        if (empty($this->actionsMap)) {
            throw new \RuntimeException("Actions is not presented.");
        }

        return $this->actionsMap;
    }

    public function getBaseUrl()
    {
        if (empty($this->baseUrl)) {
            throw new \RuntimeException("Base URL is not presented.");
        }

        return $this->baseUrl;
    }

    protected function handleResponse(HttpResponse $response, $contentType = null)
    {
        if (is_null($contentType)) {
            $contentType = $response->headers->get('Content-type');
            if (false !== strpos($contentType, ';')) {
                $parts = explode(';', $contentType);
                $contentType = $parts[0];
            }
        }

        $content = $response->getContent();
        $statusCode = $response->getStatusCode();

        return $this->handleResponseContent($content, $contentType, $statusCode);
    }

    protected function handleResponseContent($content, $contentType, $statusCode = 200)
    {
        $data = null;
        if (!empty($content)) {
            switch ($contentType) {
                case 'application/xml':
                    $data = $this->parseXML($content);
                    break;
                case 'application/json':
                    $data = $this->parseJson($content);
                    break;
                default:
                    $data = $content;
            }
        } else {
            if ($statusCode === 201 || $statusCode === 204) {
                return true;
            }
        }

        return $data;
    }

    protected function parseJSON($content, $detectEncoding = array('utf-8', 'iso-8859-1', 'windows-1251'))
    {
        if (!empty($detectEncoding)) {
            $encoding = mb_detect_encoding($content, $detectEncoding);
            if ($encoding !== 'UTF-8') {
                $content = iconv($encoding, 'UTF-8', $content);
            }
        }

        return Utils::parseJson($content);
    }

    protected function parseXML($content)
    {
        return json_decode(json_encode((array)simplexml_load_string($content)), 1);
    }

    /**
     * Gets the value of remoteKernel.
     *
     * @return mixed
     */
    protected function getRemoteKernel()
    {
        if (empty($this->remoteKernel)) {
            $this->remoteKernel = new RemoteHttpKernel($this->getRequestGenerator());
        }

        return $this->remoteKernel;
    }

    protected function collectCurlOptions()
    {
        $options = $this->defaultCurlOptions + $this->getCurlOptions();
        $options[CURLOPT_TIMEOUT] = $this->getTimeout();

        return $options;
    }

    protected function getRequestGenerator()
    {
        return new RequestGenerator($this->collectCurlOptions());
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = (int)$timeout;

        return $this;
    }

    public function getActionUrl($action, $throwError)
    {
        return $this->isValidAction($action, $throwError) ? $this->actionsMap[$action] : null;
    }

    public function getUrlAction($url)
    {
        return array_search($url, $this->getActions());
    }

    public function isValidAction($action, $throwError = false)
    {
        $actions = $this->getActions();
        if (!isset($actions[$action])) {
            if ($throwError) {
                throw new \InvalidArgumentException("Invalid action: {$action}");
            }

            return false;
        }

        return true;
    }

    protected function getCurlOptions()
    {
        return array();
    }

    public function __call($name, $args)
    {
        if (!empty($this->actionsMap)) {
            $method = strtoupper(substr($name, 0, 3));
            if (in_array($method, array("GET", "POST", "PUT", "HEAD", "DELETE"))) {
                $action = substr($name, 3);
                if (!isset($this->actionsMap[$action])) {
                    $action = Inflector::tableize($action);
                }

                if (isset($this->actionsMap[$action])) {
                    $params = reset($args);
                    if (!is_array($params)) {
                        $params = array();
                    }

                    return $this->doSingleRequest($this->actionsMap[$action], $method, $params);
                }
            }
        }

        throw new \RuntimeException("Try to call undefined method {$name}");
    }

    public function doAction($action, $method = 'GET', array $params = array(), array $headers = array())
    {
        $actions = $this->getActions();
        if (!isset($actions[$action])) {
            throw new \InvalidArgumentException("Invalid action: {$action}");
        }

        if ($method == 'GET' && $this->cache->contains($actions[$action])) {
            return $this->cache->fetch();
        }

        $data = $this->doSingleRequest($this->buildRequest($actions[$action], $method, $params, $headers));
        $this->cache->save($actions[$action], $data);

        return $data;
    }

    public function doBatchAction(array $actions = array(), $method = "GET", array $params = array())
    {
        $map = $this->getActions();
        if (empty($actions)) {
            $actions = array_keys($this->actionsMap);
        }

        $collection = new Curl\Collection;

        foreach ($actions as $key => $val) {
            $a = $val;
            $p = $params;
            if (is_array($val)) {
                $a = $key;
                $p = $val;
            }

            if (!isset($map[$a])) {
                throw new \InvalidArgumentException("Undefined action: {$a}");
            }

            $request = new Curl\Request($map[$a], $method, $this->filterParams($p));
            $request->setOptionArray($this->collectCurlOptions());
            $request->setId($a);
            $collection->add($request);
        }

        return $this->doMultiRequest($collection);
    }

    public function doMultiRequest(Curl\Collection $collection, $contentType = null)
    {
        $self = $this;
        $result = new ResponseCollection();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            CurlEvents::MULTI_INFO,
            function (MultiInfoEvent $e) use ($self, &$result, $contentType) {
                $request = $e->getRequest();
                $response = $e->getManager()->getResponse($request);

                if ($request->getMethod() == 'GET') {
                    $data = $this->makeApiResponse($response, $contentType);
                } else {
                    if ($response->isRedirection()) {
                        $data = $this->isSuccessful($response);
                    } else {
                        $data = $self->handleResponse($response, $self->getDefaultContentType());
                    }
                }
                /*if ($request->getMethod()=='POST') {
                    // var_dump(file_get_contents($response->headers->get('location')));
                    var_dump($response, $request, $data);exit;
                }*/

                $result[$request->getId()] = $data;
                if (!empty($data) && $request->getMethod() == 'GET' && $self->isSuccessful($response)) {
                    $self->getCache()->save(md5($request->getUrl()), $data);
                }
            });

        $mm = new Curl\MultiManager($dispatcher);
        foreach ($collection as $request) {
            if ($request->getMethod() == 'GET' && $this->cache->contains(md5($request->getUrl()))) {
                $result[$request->getId()] = $this->cache->fetch();
                continue;
            } else {
                $result[$request->getId()] = null;
            }

            $mm->addRequest($request);
        }

        if (!$mm->isEmpty()) {
            $mm->execute();
        }

        return $result;
    }

    protected function isSuccessful(HttpResponse $response)
    {
        return $response->isSuccessful();
    }

    public function get($url, $params = null, array $headers = array(), $contentType = null)
    {
        return $this->request($url, "GET", $params, $headers, $contentType);
    }

    public function post($url, $params = null, array $headers = array(), $contentType = null)
    {
        return $this->request($url, "POST", $params, $headers, $contentType);
    }

    public function put($url, $params = null, array $headers = array(), $contentType = null)
    {
        return $this->request($url, "PUT", $params, $headers, $contentType);
    }

    public function delete($url, $params = null, array $headers = array(), $contentType = null)
    {
        return $this->request($url, "DELETE", $params, $headers, $contentType);
    }

    public function patch($url, $params = null, array $headers = array(), $contentType = null)
    {
        return $this->request($url, "PATCH", $params, $headers, $contentType);
    }

    protected function request($url, $method = "GET", $params = null, array $headers = array(), $contentType = null)
    {
        if (func_num_args() === 3) {
            if (!is_array($params)) {
                $contentType = $params;
                $params = array();
            }
        }

        if (is_null($params)) {
            $params = array();
        }

        return $this->doSingleRequest($this->buildRequest($url, $method, $params, $headers, false), $contentType);
    }


    public function doSingleRequest(Request $request, $contentType = null)
    {
        $method = $request->getMethod();
        if ($method == 'GET' && $this->cache->contains(md5(serialize(array(
                $request->getUri(),
                $request->request->all()
            ))))
        ) {
            return $this->cache->fetch();
        }

        $response = $this->getRemoteKernel()->handle($request);

        if ($response->isSuccessful()) {
            if ($method == 'GET') {
                $data = $this->makeApiResponse($response, $contentType);
            } else {
                $data = $this->handleResponse($response, $this->getDefaultContentType());
            }

            if ($method == 'GET') {
                if (!empty($data)) {
                    $this->cache->save($data);
                } else {
                    $this->cache->delete();
                }
            }

            return $data;
        } else {
            // throw new ServiceUnavailableHttpException();
            return $this->makeApiResponse($response);
        }
    }

    protected function makeApiResponse(HttpResponse $response, $contentType = null)
    {
        if (is_null($contentType)) {
            $contentType = $this->getDefaultContentType();
        }

        switch ($contentType) {
            case 'application/json':
                return new JSONResponse($response);
                break;
            case 'application/xml':
                return new XMLResponse($response);
                break;
            default:
                return new RawResponse($response);
        }
    }

    public function buildRequest(
        $url,
        $method = 'GET',
        array $params = array(),
        array $headers = array(),
        $doFilter = true
    ) {
        if ($doFilter) {
            $params = $this->filterParams($params);
        }

        $request = Request::create($url, $method, $params);
        if (!empty($headers)) {
            $request->headers->add($headers);
        }
        return $request;
    }

    protected function filterParams(array $params = array())
    {
        return array_merge($this->getDefaultParams(), $params);
    }

    public function getDefaultParams()
    {
        return $this->defaultParams;
    }

    public function getDefaultContentType()
    {
        return $this->defaultContentType;
    }

    public function setDefaultContentType($contentType)
    {
        $this->defaultContentType = $contentType;

        return $this;
    }

    public function setDefaultParams(array $default = array())
    {
        $this->defaultParams = $default;

        return $this;
    }
}