<?php

namespace Study\EmailValidator\Controller;
use Study\EmailValidator\Service\DDXParser;
use Study\EmailValidator\Service\Logger;
use Study\EmailValidator\View\View;

class ParserController
{
    public const BATCH_SIZE = 1000;
    private $parser;

    public function __construct()
    {
        $this->parser = new DDXParser();
    }

    public function parse()
    {
        $offset = $_POST['offset'] ?? 0;
        $len = $_POST['len'] ?? 0;

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
        $cnt = $this->parser->getFoldersList();
        View::render('menu', [
            'title' => 'Главная страница',
            'cnt' => $cnt,
        ]);
    }

    public function ajaxInit()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $cnt = $this->parser->getFoldersList();
            echo json_encode(['status' => 'ok', 'total' => $cnt]);
        } catch (\Throwable $e) {
            Logger::logException($e, 'ajaxInit');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function ajaxStep()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $offset = (int) ($_POST['offset'] ?? $_GET['offset'] ?? 0);
            $len = (int) ($_POST['len'] ?? $_GET['len'] ?? self::BATCH_SIZE);

            $result = $this->parser->parse($offset, $len);

            echo json_encode([
                'status' => 'ok',
                'next_offset' => $offset + $len,
                'url' => $result['url'] ?? '',
                'url_csv' => $result['url_csv'] ?? '',
                'errors' => $result['errors'] ?? null,
                'excel_files' => $result['excel_files'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Logger::logException($e, 'ajaxStep');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function ajaxFilter()
    {
        try {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');

            $parser = new DDXParser();
            $result = $parser->ajaxFilter();

            echo json_encode($result);
            exit;

        } catch (\Throwable $e) {
            Logger::logException($e, 'ajaxFilter');

            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'PHP Error: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }

    public function ajaxFilterStep()
    {
        try {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');

            $offset = (int) ($_POST['offset'] ?? $_GET['offset'] ?? 0);
            $len = (int) ($_POST['len'] ?? $_GET['len'] ?? self::BATCH_SIZE);

            $result = $this->parser->ajaxFilterStep($offset, $len);

            echo json_encode($result);
            exit;

        } catch (\Throwable $e) {
            Logger::logException($e, 'ajaxFilterStep');

            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'PHP Error: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }

    public function ajaxFilterFinalize()
    {
        try {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');

            $result = $this->parser->ajaxFilterFinalize();

            echo json_encode($result);
            exit;

        } catch (\Throwable $e) {
            Logger::logException($e, 'ajaxFilterFinalize');

            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'PHP Error: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }
}