<?php

namespace Bump\Library\Common\Api;

use Bump\Library\Common\QueryFixRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;


abstract class PDCApiBase extends Base
{
    protected function fetchAuthParams($url, HttpResponse $response, $username, $password)
    {
        if ($response->isSuccessful()) {
            if (false !== strpos($response->headers->get('Content-Type'), 'text/html')) {
                if (preg_match('/form\s+name="login_form"\s+action="([^"]+)"/mi', $response->getContent(), $match)) {
                    $authUrl = $this->resolveUri($url, $match[1]);
                    return array(
                        'url' => $authUrl,
                        'method' => 'POST',
                        'params' => array('j_username' => $username, 'j_password' => $password)
                    );
                }
            }
        }

        return false;
    }

    protected function createAuthRequest(Request $request, HttpResponse $response, $username, $password)
    {
        if (($params = $this->fetchAuthParams($request->getUri(), $response, $username, $password))) {
            return QueryFixRequest::create($params['url'], $params['method'], array(), array(), array(), array(),
                http_build_query($params['params'], '', '&'));
        }

        return false;
    }

    protected function auth(Request $request, HttpResponse $response, $username, $password)
    {
        if (($authRequest = $this->createAuthRequest($request, $response, $username, $password))) {
            $authResponse = $this->getRemoteKernel()->handle($authRequest);
            if ($authResponse->isRedirection()) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    protected function isSuccessful(HttpResponse $response)
    {
        if ($response->isSuccessful()) {
            if (false !== strpos($response->headers->get('Content-Type'), 'text/html')) {
                if (preg_match('/form\s+name="login_form"\s+action="([^"]+)"/mi', $response->getContent(), $match)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    protected function resolveUri($url, $uri)
    {
        $parts = parse_url($url);

        $u = '';

        if (!empty($parts['scheme'])) {
            $u .= $parts['scheme'];
            if (!empty($parts['host'])) {
                $u .= '://';
                if (!empty($parts['user'])) {
                    $u .= $parts['user'];

                    if (!empty($parts['pass'])) {
                        $u .= ':' . $parts['pass'];
                    }

                    $u .= '@';
                }
                $u .= $parts['host'];
            }
        }

        if (!empty($parts['port'])) {
            $u .= ':' . $parts['port'];
        }

        if (!empty($parts['path'])) {
            $u .= substr($parts['path'], 0, strrpos($parts['path'], '/') + 1) . $uri;
        }

        return $u;
    }

}