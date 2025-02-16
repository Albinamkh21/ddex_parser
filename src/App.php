<?php

declare(strict_types=1);



namespace Study\EmailValidator;

use Study\EmailValidator\Response\Response;
use Study\EmailValidator\Service\DDXParser;


class App
{
    private DDXParser $parser;
    private Response $response;

    public function __construct()
    {
        $this->parser = new DDXParser();
        $this->response = new Response();

    }

    public function run()
    {

        $router = new RoutManager();

        /*
       try {

         // $this->parser->validate();
         //  $this->parser->parse();
           //$this->parser->getFoldersList();
       }
       catch (\Exception $e) {
           print $e->getMessage();
       }

*/

    }
}