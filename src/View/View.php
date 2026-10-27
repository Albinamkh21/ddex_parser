<?php

namespace Study\EmailValidator\View;

class View
{
    public static function render(string $view, array $data = [])
    {
        extract($data, EXTR_OVERWRITE);

        require_once(__DIR__."/../views/{$view}.php");


        exit();
    }

    public static function renderStr(string $view, array $data = []): string
    {
        extract($data, EXTR_OVERWRITE);

        ob_start();
        require_once(__DIR__."/../views/{$view}.php");
        $str = ob_get_contents();
        ob_end_clean();

        return $str;
    }
}