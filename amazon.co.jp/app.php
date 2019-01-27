<?php

function app_dir($dir)
{
    $dir = __DIR__ . '/cache/' . $dir;
    is_dir($dir) || mkdir($dir, '0755', true);
    return realpath($dir) . '/';
}

function app_log($file, $log)
{
    $date = date('Y-m-d H:i:s');
    $file = __DIR__ . '/cache/' . basename($file);
    file_put_contents($file, "[$date] {$log}\n", FILE_APPEND);
}

function app_utf8($data)
{
    if (is_array($data)) {
        $new = array();
        foreach ($data as $k => $v) {
            $new[app_utf8($k)] = app_utf8($v);
        }
        return $new;
    }
    return mb_convert_encoding($data, 'UTF-8');
}

function app_exit($data)
{
    $data = app_utf8($data);
    header('Content-Type: application/json; charset=utf-8');
    $data = is_array($data) ? json_encode($data) : $data;
    json_last_error() && $data = json_encode(array(
        'error' => json_last_error_msg()
    ));
    exit($data);
}

include(__DIR__ . '/bridge.php');
include(__DIR__ . '/search.php');
include(__DIR__ . '/product.php');
