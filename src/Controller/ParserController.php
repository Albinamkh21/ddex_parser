<?php
/*
Author
*/

namespace Study\EmailValidator\Controller;
use Study\EmailValidator\Service\DDXParser;
use Study\EmailValidator\View\View;

class ParserController
{

    private DDXParser $parser;
    public function __construct()
    {
        $this->parser = new DDXParser();
    }


    public function parse()
    {
        $offset = $_POST['offset'] ?? 0;
        $len = $_POST['len'] ?? 0;

        $x = new Reson();


        $result = $this->parser->parse($offset, $len);

        View::render('menu', [
            'title' => 'Главная страница',
            'offset' => $result['offset'] ?? 0,
            'len' => $result['len'] ?? 0,
            'url' => $result['url'] ?? '',
            'url_csv' => $result['url_csv'] ?? '',
            'isFinished' => true,
            'errors' => $result['errors'] ?? null,

        ]);

    }
    public function get()
    {
       $cnt =  $this->parser->getFoldersList();
       View::render('menu', [
            'title' => 'Главная страница',
            'cnt' => $cnt,
        ]);

    }

}