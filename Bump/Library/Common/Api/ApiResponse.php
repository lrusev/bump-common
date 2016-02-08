<?php

namespace Bump\Library\Common\Api;


interface ApiResponse
{
    public function getData();

    public function normalize();

    public function getOriginalResponse();

    public function isSuccessful();

    public function send();

    public function getContentType();

    public function getHash($prefix = '');
}