<?php
namespace Study\EmailValidator\Service;

class Logger
{
    private static $logFile = null;
    private static $timers = array();

    private static function logDir()
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
    }

    private static function logFile()
    {
        if (self::$logFile === null) {
            self::$logFile = self::logDir() . DIRECTORY_SEPARATOR . 'app.log';
        }
        return self::$logFile;
    }

    private static function ensureLogDirExists()
    {
        $dir = self::logDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return is_dir($dir) && is_writable($dir);
    }

    private static function write($level, $message)
    {
        $line = sprintf('[%s] [%s] %s%s', date('Y-m-d H:i:s'), $level, $message, PHP_EOL);

        if (self::ensureLogDirExists()) {
            $written = @file_put_contents(self::logFile(), $line, FILE_APPEND | LOCK_EX);
            if ($written !== false) {
                return;
            }
        }

        error_log(trim($line));
    }

    public static function info($message)
    {
        self::write('INFO', $message);
    }

    public static function warning($message)
    {
        self::write('WARNING', $message);
    }

    public static function error($message)
    {
        self::write('ERROR', $message);
    }

    public static function logException(\Throwable $e, $context = '')
    {
        $prefix = $context !== '' ? "[{$context}] " : '';
        self::error(sprintf(
            "%sException: %s%s   in %s:%d%s   Stack trace:%s%s",
            $prefix,
            $e->getMessage(),
            PHP_EOL,
            $e->getFile(),
            $e->getLine(),
            PHP_EOL,
            PHP_EOL,
            $e->getTraceAsString()
        ));
    }

    public static function stepStart($step)
    {
        self::$timers[$step] = microtime(true);
        self::info("-> Start: {$step}");
    }

    public static function stepEnd($step, $extra = '')
    {
        $start = isset(self::$timers[$step]) ? self::$timers[$step] : null;
        $suffix = $extra !== '' ? " ({$extra})" : '';

        if ($start !== null) {
            $elapsed = round(microtime(true) - $start, 3);
            unset(self::$timers[$step]);
            self::info("<- Finished: {$step}{$suffix}, время : {$elapsed} сек.");
        } else {
            self::info("<- Finished: {$step}{$suffix}");
        }
    }

    public static function stepFail($step, $message = '')
    {
        $start = isset(self::$timers[$step]) ? self::$timers[$step] : null;
        $suffix = $message !== '' ? ": {$message}" : '';

        if ($start !== null) {
            $elapsed = round(microtime(true) - $start, 3);
            unset(self::$timers[$step]);
            self::error("x Шаг прерван с ошибкой: {$step}{$suffix}, время до ошибки: {$elapsed} сек.");
        } else {
            self::error("x Шаг прерван с ошибкой: {$step}{$suffix}");
        }
    }

    public static function registerGlobalHandlers()
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            self::error(sprintf('PHP (%s): %s in %s:%d', self::severityName($severity), $message, $file, $line));
            return false;
        });

        set_exception_handler(function (\Throwable $e) {
            self::logException($e, 'Необработанное исключение');
            self::respondWithJsonErrorIfApplicable($e->getMessage());
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error === null) {
                return;
            }

            $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
            if (!in_array($error['type'], $fatalTypes, true)) {
                return;
            }

            self::error(sprintf(
                'Fatal error (%s): %s in %s:%d',
                self::severityName($error['type']),
                $error['message'],
                $error['file'],
                $error['line']
            ));

            self::respondWithJsonErrorIfApplicable($error['message']);
        });
    }

    private static function respondWithJsonErrorIfApplicable($message)
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $isAjaxRoute = stripos($uri, '/parser/ajax') !== false || stripos($uri, '/parser/parse') !== false;

        if (!$isAjaxRoute || headers_sent()) {
            return;
        }

        if (ob_get_level()) {
            ob_clean();
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'status' => 'error',
            'message' => 'Внутренняя ошибка сервера: ' . $message,
        ));
    }

    private static function severityName($severity)
    {
        $map = array(
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        );
        return isset($map[$severity]) ? $map[$severity] : (string)$severity;
    }
}
