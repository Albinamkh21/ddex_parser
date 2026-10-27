<?php

namespace Study\EmailValidator\Controller;

use Study\EmailValidator\View\View;

class MenuController
{
    public function index()
    {
        View::render('menu', [
            'title' => 'Главная страница',
        ]);
    }
}