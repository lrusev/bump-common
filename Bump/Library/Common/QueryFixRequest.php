<?php

namespace Bump\Library\Common;

use Symfony\Component\HttpFoundation\Request;

class QueryFixRequest extends Request
{

    public static function create(
        $uri,
        $method = 'GET',
        $parameters = array(),
        $cookies = array(),
        $files = array(),
        $server = array(),
        $content = null
    ) {
        $server = array_replace(array(
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'HTTP_HOST' => 'localhost',
            'HTTP_USER_AGENT' => 'Symfony/2.X',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
        ), $server);

        $server['PATH_INFO'] = '';
        $server['REQUEST_METHOD'] = strtoupper($method);

        $components = parse_url($uri);
        if (isset($components['host'])) {
            $server['SERVER_NAME'] = $components['host'];
            $server['HTTP_HOST'] = $components['host'];
        }

        if (isset($components['scheme'])) {
            if ('https' === $components['scheme']) {
                $server['HTTPS'] = 'on';
                $server['SERVER_PORT'] = 443;
            } else {
                unset($server['HTTPS']);
                $server['SERVER_PORT'] = 80;
            }
        }

        if (isset($components['port'])) {
            $server['SERVER_PORT'] = $components['port'];
            $server['HTTP_HOST'] = $server['HTTP_HOST'] . ':' . $components['port'];
        }

        if (isset($components['user'])) {
            $server['PHP_AUTH_USER'] = $components['user'];
        }

        if (isset($components['pass'])) {
            $server['PHP_AUTH_PW'] = $components['pass'];
        }

        if (!isset($components['path'])) {
            $components['path'] = '/';
        }

        switch (strtoupper($method)) {
            case 'POST':
            case 'PUT':
            case 'DELETE':
                if (!isset($server['CONTENT_TYPE'])) {
                    $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
                }
            case 'PATCH':
                $request = $parameters;
                $query = array();
                break;
            default:
                $request = array();
                $query = $parameters;
                break;
        }

        //comment to fix uri
        if (isset($components['query'])) {
            parse_str($components['query'], $qs);
            $query = array_replace($qs, $query);
        }

        if (isset($components['query']) && filter_var($components['query'])) {
            $queryString = $components['query'];
        } else {
            $queryString = http_build_query($query, '', '&');
        }

        $server['REQUEST_URI'] = $components['path'] . ('' !== $queryString ? '?' . $queryString : '');
        $server['QUERY_STRING'] = $queryString;

        return new static($query, $request, array(), $cookies, $files, $server, $content);
    }

    /**
     * Override standard getUri method
     * to allow use Zeroem\CurlBundle\HttpKernel\RemoteHttpKernel
     * with get request with url like:
     * http://emopstest.pdc.org/emopsdr/mapservice_proxy/proxy_aer.jsp?http://agstest.pdc.org/daarcgis/rest/services/other/pdc_map_labels/MapServer/export?imageSR=3857&bbox=-171.45751953125,13.310317960358,-138.54248046875,26.417270361347&bboxSR=4326&f=image&format=png32&transparent=true&layers=show:3&size=749,318
     * prevent urlencoding query string
     */
    public function getUri()
    {
        if (($qs = $this->server->get('QUERY_STRING'))) {
            if (!empty($qs)) {
                $qs = '?' . urldecode($qs);
            }
        }

        return $this->getSchemeAndHttpHost() . $this->getBaseUrl() . $this->getPathInfo() . $qs;
    }
}