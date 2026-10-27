<?php

namespace Study\EmailValidator;

use Study\EmailValidator\View\View;

class RoutManager
{
    const ALLOWED_ACTIONS = ['parse', 'get', 'index', 'ajaxinit', 'ajaxstep', 'ajaxfilter', 'ajaxfilterstep', 'ajaxfilterfinalize'];
    const ALLOWED_ROUTES = ['parser', 'menu'];
    const NAMESPACE = "Study\\EmailValidator\\Controller\\";

    public function __construct()
    {
        $url = strtok($_SERVER["REQUEST_URI"], '?');
        $routes = explode('/', $url);

        if ($url == '/') {
            $action_name = 'index';
            $controller_name = self::NAMESPACE . "MenuController";
            $controller = new $controller_name();
            $controller->$action_name();
            return;
        }

        if (!isset($routes[1]) || !in_array(strtolower($routes[1]), self::ALLOWED_ROUTES)) {
            $this->render404();
            return;
        }

        $route = ucfirst(strtolower($routes[1]));
        $controller_name = self::NAMESPACE . "{$route}Controller";

        $action_name = isset($routes[2]) ? strtolower($routes[2]) : 'index';
        if (!in_array($action_name, self::ALLOWED_ACTIONS)) {
            $this->render404();
            return;
        }

        if (!class_exists($controller_name, true)) {
            $this->render404();
            return;
        }

        if (!method_exists($controller_name, $action_name)) {
            $this->render404();
            return;
        }

        $controller = new $controller_name();
        $controller->$action_name();
    }

    private function render404()
    {
        View::render('error/404', [
            'title' => 'Ошибка 404',
            'error_code' => '404 - Not Found',
            'result' => 'Нет такой страницы'
        ]);
    }
}