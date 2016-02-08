<?php

namespace Bump\Library\Common;

use Doctrine\Common\Inflector\Inflector;


class Utils
{

    public static function randomString($length = 16, $mode = 1, $chars = true)
    {
        $string = '';
        $possible = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($chars) {
            $possible .= strtolower($possible);
        }

        switch ($mode) {
            case 3:
                $possible .= '`~!@#$%^&*()_-+=|}]{[":;<,>.?/';
            case 2:
                $possible .= '0123456789';
                break;
        }
        for ($i = 1; $i < $length; $i++) {
            $char = substr($possible, mt_rand(0, strlen($possible) - 1), 1);
            $string .= $char;
        }

        return $string;
    }

    public static function convertToXML(array $data, $root = 'result', $keyFilterCallback = null)
    {
        $xml = new SimpleXMLElementExtended('<' . $root . '/>');
        self::arrayToXml($data, $xml, null, $keyFilterCallback);
        return $xml->asXML();
    }

    public static function arrayToXml(array $data, \SimpleXMLElement $xml, $parentKey = null, $keyFilterCallback = null)
    {
        foreach ($data as $k => $v) {
            if (!is_numeric($k) && is_callable($keyFilterCallback)) {
                $k = call_user_func($keyFilterCallback, $k);
            }

            if (is_array($v)) {
                if (!is_numeric($k)) {
                    self::arrayToXml($v, $xml->addChild($k), $k, $keyFilterCallback);
                } else {
                    self::arrayToXml($v, $xml->addChild(Inflector::singularize($parentKey)), null, $keyFilterCallback);
                }
            } else {
                if (!is_numeric($k)) {
                    $xml->addChildWithCDATA($k, $v);
                } else {
                    if (!is_null($parentKey)) {
                        $xml->addChildWithCDATA(Inflector::singularize($parentKey), $v);
                    } else {
                        throw new \Exception("Array To xml forma error: invalid element name {$k}");
                    }
                }
            }
        }

        return $xml;
    }

    public static function parseJSON($data, $options = true, $throw = false)
    {
        $decoded = json_decode($data, $options);

        if (($jsonLastErr = json_last_error()) != JSON_ERROR_NONE) {
            switch ($jsonLastErr) {
                case JSON_ERROR_DEPTH:
                    if ($throw) {
                        throw new \Exception('Decoding failed: Maximum stack depth exceeded');
                    } else {
                        return false;
                    }
                case JSON_ERROR_CTRL_CHAR:
                    if ($throw) {
                        throw new \Exception('Decoding failed: Unexpected control character found');
                    } else {
                        return false;
                    }
                case JSON_ERROR_SYNTAX:
                    if ($throw) {
                        throw new \Exception('Decoding failed: Syntax error');
                    } else {
                        return false;
                    }
                default:
                    if ($throw) {
                        throw new \Exception('Decoding failed: Syntax error');
                    } else {
                        return false;
                    }
            }
        }

        return $decoded;
    }

    public static function isAssoc($array)
    {
        return is_array($array) && array_keys($array) !== range(0, count($array) - 1);
    }

    public static function exposeParameters(
        \Symfony\Component\DependencyInjection\ContainerBuilder $container,
        array $config,
        $alias,
        array $indexes = array(),
        $glue = '.'
    ) {
        $exposed = array();
        $glue = trim($glue);

        foreach ($config as $name => $value) {
            $key = $alias . $glue . $name;
            if (!is_array($value)) {
                $container->setParameter($key, $value);
                $exposed[$key] = $value;
                continue;
            } else {
                if (in_array($key, $indexes) || in_array($name, $indexes)) {
                    $container->setParameter($key, $value);
                }
            }

            $exposed = array_merge($exposed, self::exposeParameters($container, $value, $key, $indexes, $glue));
        }

        return $exposed;
    }
}
