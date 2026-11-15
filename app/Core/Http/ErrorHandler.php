<?php

namespace Kanboard\Core\Http;

/** Last-resort HTTP boundary. Deliberately independent of autoload, plugins and DB. */
final class ErrorHandler
{
    private static $surface;
    private static $reference;
    private static $bufferLevel;
    private static $handled = false;
    private static $reserve;
    private static $diagnostics = [];

    public static function register($surface)
    {
        self::$surface = $surface;
        self::$reference = bin2hex(random_bytes(12));
        self::$bufferLevel = ob_get_level();
        self::$reserve = str_repeat('x', 65536);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        // Native diagnostic logging can include secrets from error messages.
        ini_set('log_errors', '0');
        set_exception_handler([self::class, 'handle']);
        set_error_handler([self::class, 'warning']);
        register_shutdown_function([self::class, 'shutdown']);
        ob_start();
    }

    public static function warning($severity, $message, $file, $line)
    {
        if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            self::fail('FatalError', $file, $line, 500, ['severity' => $severity]);
            exit;
        }
        $key = $severity.':'.$file.':'.$line;
        if ((error_reporting() & $severity) && count(self::$diagnostics) < 10 && !isset(self::$diagnostics[$key])) {
            self::$diagnostics[$key] = true;
            self::record('PhpDiagnostic', $file, $line, ['severity' => $severity]);
        }
        return true;
    }

    public static function shutdown()
    {
        $last = error_get_last();
        if (!self::$handled && $last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            self::$reserve = null;
            self::fail('FatalError', $last['file'], $last['line'], 500, ['severity' => $last['type']]);
        }
    }

    public static function handle(\Throwable $error)
    {
        $codes = [];
        $status = 500;
        if ($error instanceof \PDOException) {
            $state = (string) ($error->errorInfo[0] ?? $error->getCode());
            $driver = (int) ($error->errorInfo[1] ?? 0);
            $codes = ['sqlstate' => preg_match('/^[A-Z0-9]{5}$/D', $state) ? $state : '', 'driver_code' => $driver];
            if (substr($state, 0, 2) === '08' || in_array($driver, [1040, 1203, 1226, 2002, 2003, 2006, 2013], true)) {
                $status = 503;
            }
        }
        self::fail(get_class($error), $error->getFile(), $error->getLine(), $status, $codes);
    }

    private static function fail($class, $file, $line, $status, array $codes)
    {
        if (self::$handled) { return; }
        self::$handled = true;
        self::$reserve = null;
        self::record($class, $file, $line, $codes + ['status' => $status]);
        while (ob_get_level() > self::$bufferLevel) { ob_end_clean(); }
        // Once streaming has started, do not append another protocol/body.
        if (headers_sent()) { return; }
        header_remove('Location');
        header_remove('Status');
        header_remove('ETag');
        header_remove('Content-Length');
        header_remove('Content-Disposition');
        http_response_code($status);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('X-Request-ID: '.self::$reference);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') { return; }
        self::body($status);
    }

    private static function body($status)
    {
        if (self::$surface === 'web') {
            header('Content-Type: text/html; charset=utf-8');
            $message = $status === 503 ? 'Servicio temporalmente no disponible.' : 'No fue posible completar la solicitud.';
            echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Projecta</title><h1>'.$message.'</h1><p>Intenta nuevamente más tarde.</p><p>Referencia: '.self::$reference.'</p></html>';
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['error' => $status === 503 ? 'service_unavailable' : 'internal_error', 'reference' => self::$reference];
        if (in_array(self::$surface, ['jsonrpc', 'mcp'], true)) {
            $payload = ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32603, 'message' => 'Internal error', 'data' => ['reference' => self::$reference]]];
        }
        echo json_encode($payload);
    }

    private static function record($class, $file, $line, array $codes)
    {
        $root = str_replace('\\', '/', dirname(__DIR__, 3)).'/';
        $file = str_replace('\\', '/', $file);
        $relative = strpos($file, $root) === 0 ? substr($file, strlen($root)) : '[external]';
        $record = ['event' => 'projecta.http_error', 'reference' => self::$reference, 'time' => gmdate('c'),
            'surface' => self::$surface, 'class' => $class, 'file' => $relative, 'line' => (int) $line] + $codes;
        // Never pass exception messages, traces, requests or SQL to the sink.
        @error_log(json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /** Release only our buffer before MCP transport starts streaming. */
    public static function stream()
    {
        while (ob_get_level() > self::$bufferLevel) { ob_end_flush(); }
    }
}
