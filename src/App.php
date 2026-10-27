<?php

declare(strict_types=1);

namespace Study\EmailValidator;

use Study\EmailValidator\Response\Response;
use Study\EmailValidator\Service\DDXParser;

class App
{
    private $parser;
    private $response;

    public function __construct()
    {
        $this->parser = new DDXParser();
        $this->response = new Response();
    }

    public function run()
    {
        $router = new RoutManager();
    }
}