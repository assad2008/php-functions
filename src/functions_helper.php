<?php

require_once __DIR__ . '/OrderSequence.php';

/**
 * @Filename:  functions_helper.php
 * @Author:  assad
 * @Email:  rlk002@gmail.com
 * @Date:  2019-07-18 11:00:45
 * @Synopsis:  函数库
 * @Version:  1.0
 * @Last Modified by:   assad
 * @Last Modified time: 2022-09-23 09:19:18
 */

/**
 * belongsto functions_helper.php
 * 得到当前页面的基础URL
 *
 * @param      boolean  $atRoot  The at root
 * @param      boolean  $atCore  The at core
 * @param      <type>   $parse   The parse
 *
 * @return     string   ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2020-02-11T16:30
 */
function baseUrl($atRoot = false, $atCore = false, $parse = false)
{
    if (isset($_SERVER['HTTP_HOST'])) {
        $http = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $hostname = $_SERVER['HTTP_HOST'];
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/';
        $dir = str_replace(basename($scriptName), '', $scriptName);

        $core = '';
        $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
        $currentDir = __DIR__;
        if ($documentRoot !== '' && $currentDir !== '') {
            $coreList = preg_split('@/@', str_replace($documentRoot, '', $currentDir), -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($coreList)) {
                $core = $coreList[0];
            }
        }
        if ($atCore && $core === '') {
            $atCore = false;
        }

        $tmplt = $atRoot ? ($atCore ? "%s://%s/%s/" : "%s://%s/") : ($atCore ? "%s://%s/%s/" : "%s://%s%s");
        $end = $atRoot ? ($atCore ? $core : $hostname) : ($atCore ? $core : $dir);
        $base_url = sprintf($tmplt, $http, $hostname, $end);
    } else {
        $base_url = 'http://localhost/';
    }

    if ($parse) {
        $base_url = parse_url($base_url);
        if (isset($base_url['path'])) {
            if ($base_url['path'] == '/') {
                $base_url['path'] = '';
            }
        }
    }
    return $base_url;
}

/**
 * 截取字符串函数
 *
 * @param      string   $string  待截字符串
 * @param      integer  $length  长度
 * @param      string   $dot     末尾拼接
 *
 * @return     string   返回已截的字符
 */
function cutStr($string, $length, $dot = '...')
{
    $string = (string) $string;
    $length = (int) $length;
    if ($length <= 0) {
        return '';
    }
    if (strlen($string) <= $length) {
        return $string;
    }

    $entities = [
        '&nbsp;' => ' ', '&amp;' => '&', '&quot;' => '"', '&#039;' => "'",
        '&ldquo;' => '“', '&rdquo;' => '”', '&mdash;' => '—', '&lt;' => '<',
        '&gt;' => '>', '&middot;' => '·', '&hellip;' => '…',
    ];
    $text = strtr($string, $entities);
    $utf8 = preg_match('//u', $text) === 1;
    $budget = max(0, $length - strlen($dot));
    $size = strlen($text);
    $widthUsed = 0;
    $cutAt = 0;
    $offset = 0;
    while ($offset < $size && $widthUsed <= $length) {
        $byte = ord($text[$offset]);
        $width = $byte < 128 ? 1 : 2;
        $bytes = $width;
        if ($utf8 && $byte >= 224) {
            $bytes = $byte < 240 ? 3 : 4;
        }
        if ($offset + $bytes > $size) {
            break;
        }
        $offset += $bytes;
        $widthUsed += $width;
        if ($widthUsed <= $budget) {
            $cutAt = $offset;
        }
    }
    if ($offset === $size && $widthUsed <= $length) {
        return $string;
    }

    $escape = array_flip($entities);
    if ($utf8) {
        unset($escape[' ']);
    }
    return strtr(substr($text, 0, $cutAt), $escape) . $dot;
}

/**
 * 转码
 *
 * @param      string  $string        The string
 * @param      string  $fromEncoding  The from encoding
 * @param      string  $toEncoding    To encoding
 *
 * @return     <type>  ( description_of_the_return_value )
 */
function convertEncoding($string, $fromEncoding, $toEncoding = 'utf-8')
{
    $from = str_ireplace('utf8', 'UTF-8', trim((string) $fromEncoding));
    $to = str_ireplace('utf8', 'UTF-8', trim((string) $toEncoding));
    if (function_exists('mb_convert_encoding')) {
        $to = explode('//', $to, 2)[0];
        if ($from === '' || strcasecmp($from, 'auto') === 0) {
            $from = mb_detect_encoding($string, ['UTF-8', 'GBK', 'BIG5'], true);
        }
        if (!$from || strcasecmp($from, $to) === 0) {
            return $string;
        }
        try {
            $converted = @mb_convert_encoding($string, $to, $from);
        } catch (ValueError $error) {
            return $string;
        }
    } elseif (function_exists('iconv') && $from !== '' && strcasecmp($from, $to) !== 0) {
        $converted = @iconv($from, $to, $string);
    } else {
        return $string;
    }
    return $converted === false ? $string : $converted;
}

/**
 * 计算文件的行数
 *
 * @param      string   $filepath  文件地址
 *
 * @return     integer  Number of file lines.
 *
 * @author     assad
 * @since      2019-06-29T16:12
 */
function countFileLines($filepath)
{
    if (!$filepath || !is_file($filepath) || !is_readable($filepath)) {
        return 0;
    }

    $fp = @fopen($filepath, "r");
    if (!$fp) {
        return 0;
    }

    $line = 0;
    while (fgets($fp) !== false) {
        $line++;
    }
    fclose($fp);
    return $line;
}

/**
 * 重写addslashes
 *
 * @param      string|array
 * @param      integer  $force   The force
 *
 * @return     <type>   ( description_of_the_return_value )
 */
function ciAddslashes($string, $force = 1)
{
    if (is_array($string)) {
        $data = [];
        foreach ($string as $key => $val) {
            $data[addslashes($key)] = ciAddslashes($val, $force);
        }
        return $data;
    }

    return addslashes($string);
}

/**
 * 格式化时间
 *
 * @param      integer|string  $timestamp  时间戳
 * @param      string          $format     格式
 * @param      integer         $convert    The convert
 *
 * @return     array|string    ( description_of_the_return_value )
 */
function ciGmdate($timestamp = "", $format = "Y-n-d H:i", $convert = 1, $timeoffset = null)
{
    $now = time();
    if ($timestamp === '' || $timestamp === null) {
        $timestamp = $now;
    }
    $timeoffset = $timeoffset ?? ($GLOBALS['timeoffset'] ?? 8);
    if (!is_numeric($timestamp) || !is_numeric($timeoffset)) {
        return '';
    }
    $timestamp = (int) $timestamp;
    $offset = (int) round($timeoffset * 3600);
    $local = $timestamp + $offset;
    if (!$convert) {
        return gmdate($format, $local);
    }

    $today = strtotime(gmdate('Y-m-d', $now + $offset) . ' UTC') - $offset;
    $elapsed = $now - $timestamp;
    if ($elapsed >= 0 && $timestamp >= $today) {
        if ($elapsed > 10800) {
            return '今天&nbsp;' . gmdate('H:i', $local);
        }
        if ($elapsed > 3600) {
            return intdiv($elapsed, 3600) . '&nbsp;小时前';
        }
        if ($elapsed > 1800) {
            return '半小时前';
        }
        if ($elapsed > 60) {
            return intdiv($elapsed, 60) . '&nbsp;分前';
        }
        return $elapsed ? $elapsed . '&nbsp;秒前' : '刚才';
    }
    if ($timestamp < $today && $timestamp >= $today - 2 * 86400) {
        $day = $timestamp >= $today - 86400 ? '昨天' : '前天';
        return $day . '&nbsp;' . gmdate('H:i', $local);
    }
    if (gmdate('Y', $local) < gmdate('Y', $now + $offset)) {
        $format = 'Y-n-d H:i';
    }
    return gmdate($format, $local);
}

/**
 * 下载本地文件
 *
 * @param      string  $file      本地文件地址
 * @param      string  $filename  下载的文件名称
 */
function downloadFile($file, $fileName = "")
{
    $downFileName = $fileName ?: basename($file);
    $downFileName = str_replace(["\\", "/", "\r", "\n", "\0", '"'], '', (string) $downFileName);
    if ($downFileName === '') {
        $downFileName = 'download';
    }
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downFileName . '"; filename*=UTF-8\'\'' . rawurlencode($downFileName));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        flush();
        readfile($file);
        exit(0);
    } else {
        exit(0);
    }
}

/**
 * belongsto Helpers.php
 * 得到日列表
 *
 * @param      string  $month   月份 如：2021-04-01
 * @param      string  $format  The format
 *
 * @return     array   ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2021-04-09T16:07
 */
function dayArrList($month, $format = 'Ymd')
{
    if (!preg_match('/^(\d{4})-(\d{1,2})(?:-(\d{1,2}))?$/D', trim((string) $month), $parts)) {
        return [];
    }
    if (!checkdate((int) $parts[2], (int) ($parts[3] ?? 1), (int) $parts[1])) {
        return [];
    }
    $day = new DateTimeImmutable(sprintf('%04d-%02d-01', $parts[1], $parts[2]));
    $count = (int) $day->format('t');
    $days = [];
    for ($i = 0; $i < $count; $i++) {
        $days[] = $day->format($format);
        $day = $day->modify('+1 day');
    }
    return $days;
}

/**
 * 程序执行时间
 *
 * @param      boolean  $sec    false 则返回毫秒，否则返回秒
 *
 * @return     flaot
 */
function executeTime($sec = false)
{
    $startTime = null;
    if (defined('SYS_START_TIME')) {
        $startTime = SYS_START_TIME;
    }
    if (!$startTime) {
        if (class_exists('\Yaf\Registry')) {
            $startTime = \Yaf\Registry::get("starttime") ?: 0;
        }
        if (!$startTime && class_exists('Yaf_Registry')) {
            $startTime = \Yaf_Registry::get("starttime") ?: 0;
        }
        if (!$startTime && class_exists('Zend_Registry')) {
            $startTime = \Zend_Registry::isRegistered('starttime')  ?\Zend_Registry::get('starttime') : 0;
        }
    }
    if (!$startTime) {
        return false;
    }
    if (!is_numeric($startTime)) {
        $parts = is_string($startTime) ? preg_split('/\s+/', trim($startTime)) : [];
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return false;
        }
        $startTime = (float) $parts[0] + (float) $parts[1];
    }
    $elapsed = microtime(true) - (float) $startTime;
    return $sec ? number_format($elapsed, 6, '.', '') : round($elapsed * 1000, 3);
}

/**
 * 获得一个文件的内容
 *
 * @param      string  $url    文件URL
 *
 * @return     string  ( 文件内容)
 */
function fileGetContents($url)
{
    if (!$url) {
        return false;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 3, //设置一个超时时间，单位为秒
        ],
    ]);
    $content = @file_get_contents($url, false, $ctx);
    return $content;
}

/**
 * 获得文件的扩展名
 *
 * @param      string  $fileName  文件名
 *
 * @return     string  文件扩展名
 */
function getFileExt($fileName)
{
    return addslashes(strtolower(substr(strrchr($fileName, '.'), 1, 10)));
}

/**
 * 得到一个订单ID
 *
 * @param      string   $type    类型
 * @param      integer  $seqId   混淆ID
 * @param      integer  $lenth   长度
 *
 * @return     string
 */
function getOrderId($type = 'YEE', $seqId = 0, $lenth = 18)
{
    return \PhpHelpers\OrderSequence::orderNumber($type, $seqId, $lenth);
}

/**
 * belongsto Helpers.php
 * 生成订单号 根据雪花算法
 *
 * @param      int     $datacenterId  数据中心ID
 *
 * @return     string  ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2021-04-16T10:55
 */
function genOrderId($datacenterId = 1, $workerId = null)
{
    $workerId = $workerId ?? (defined('PHPHELPERS_WORKER_ID')  ?PHPHELPERS_WORKER_ID : 0);
    return \PhpHelpers\OrderSequence::snowflake($datacenterId, $workerId);
}

/**
 * 得到JSON数据，AJAX使用
 *
 * @param      array    $data   The data
 * @param      string   $tip    The tip
 * @param      integer  $code   The code
 *
 * @return     array   The json data.
 */
function getJsonData($data = [], $tip = 'success', $code = 0)
{
    $responseData = [];
    $responseData['code'] = $code;
    $responseData['msg'] = $tip;
    $responseData['data'] = $data;
    $executeTime = @executeTime();
    if ($executeTime) {
        $responseData['execute_time'] = (string) $executeTime . ' ms';
    }
    $ret = jsonEncode($responseData);
    return $ret;
}

/**
 * 得到数组概率精度
 *
 * @param      array  $proArr  [10,20]
 *
 * @return     string  The random.
 *
 * @author     assad
 * @since      2019-06-29T16:09
 */
function getRandPro($proArr)
{
    if (!is_array($proArr)) {
        return '';
    }
    $total = 0;
    foreach ($proArr as $key => $weight) {
        if (is_string($weight) && preg_match('/^[0-9]+$/D', $weight)) {
            $weight = ltrim($weight, '0');
            $weight = $weight === '' ? 0 : filter_var($weight, FILTER_VALIDATE_INT);
        }
        if (!is_int($weight) || $weight < 0 || $weight > PHP_INT_MAX - $total) {
            return '';
        }
        $proArr[$key] = $weight;
        $total += $weight;
    }
    if ($total > 0) {
        $draw = mt_rand(1, $total);
        foreach ($proArr as $key => $weight) {
            $draw -= $weight;
            if ($draw <= 0) {
                return $key;
            }
        }
    }
    return '';
}

/**
 * belongsto Helpers.php
 * 重写fopen
 *
 * @param      string   $url       The url
 * @param      integer  $limit     读取字节数
 * @param      string   $post      是否为post
 * @param      string   $cookie    cookie参数
 * @param      string   $ip        IP
 * @param      integer  $timeout   超时时间
 * @param      integer  $block     阻塞模式0非阻塞 1阻塞
 *
 * @return     string   ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2019-07-02T17:58
 */
function dfopen($url, $limit = 0, $post = '', $cookie = '', $ip = '', $timeout = 3, $block = 0)
{
    if (!is_string($url) || preg_match('/[\x00-\x20\x7f]/', $url)) {
        return '';
    }
    $parts = parse_url($url);
    $scheme = strtolower($parts['scheme'] ?? '');
    if (empty($parts['host']) || !in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    $limit = (int) $limit;
    $timeout = (float) $timeout;
    if ($limit < 0 || !is_finite($timeout) || $timeout <= 0 || ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP))) {
        return '';
    }
    $tls = $scheme === 'https';
    $host = $parts['host'];
    $port = $parts['port'] ?? ($tls ? 443 : 80);
    $address = $ip === '' ? $host : (strpos($ip, ':') === false ? $ip : '[' . $ip . ']');
    $target = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    $userAgent = str_replace(["\r", "\n"], '', $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0');
    $cookie = str_replace(["\r", "\n"], '', $cookie);
    $authority = isset($parts['port']) ? $host . ':' . $port : $host;
    $isPost = $post !== '' && $post !== null && $post !== false;
    $request = ($isPost ? 'POST ' : 'GET ') . $target . " HTTP/1.0\r\n"
        . "Host: $authority\r\nAccept: */*\r\nAccept-Language: zh-cn\r\n"
        . "User-Agent: $userAgent\r\nCookie: $cookie\r\nConnection: close\r\n";
    if ($isPost) {
        $request .= "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($post) . "\r\n";
    }
    $request .= "\r\n" . ($isPost ? $post : '');

    $deadline = microtime(true) + $timeout;
    $context = stream_context_create(['ssl' => ['peer_name' => trim($host, '[]'), 'verify_peer' => true, 'verify_peer_name' => true]]);
    $socket = @stream_socket_client(($tls ? 'tls://' : 'tcp://') . $address . ':' . $port,
        $errno, $message, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return '';
    }

    $body = '';
    try {
        for ($sent = 0; $sent < strlen($request); $sent += $written) {
            $left = $deadline - microtime(true);
            if ($left <= 0) {
                return '';
            }
            stream_set_timeout($socket, (int) $left, (int) (($left - (int) $left) * 1000000));
            $written = @fwrite($socket, substr($request, $sent));
            if (!$written) {
                return '';
            }
        }
        stream_set_blocking($socket, (bool) $block);
        $headers = '';
        while (!feof($socket) && ($left = $deadline - microtime(true)) > 0) {
            $seconds = (int) $left;
            $micros = (int) (($left - $seconds) * 1000000);
            if ($block) {
                stream_set_timeout($socket, $seconds, $micros);
            }
            $data = @fread($socket, $headers === null && $limit ? min(8192, $limit - strlen($body)) : 8192);
            if ($data === false || $data === '') {
                if ($block || feof($socket)) {
                    break;
                }
                $read = [$socket];
                $write = $except = null;
                if (!@stream_select($read, $write, $except, $seconds, $micros)) {
                    break;
                }
                continue;
            }
            if ($headers !== null) {
                $headers .= $data;
                if (!preg_match('/\r?\n\r?\n/', $headers, $separator, PREG_OFFSET_CAPTURE)) {
                    if (strlen($headers) > 65536) {
                        return '';
                    }
                    continue;
                }
                $data = substr($headers, $separator[0][1] + strlen($separator[0][0]));
                $headers = null;
            }
            $body .= $data;
            if ($limit && strlen($body) >= $limit) {
                return substr($body, 0, $limit);
            }
        }
    } finally {
        fclose($socket);
    }
    return $body;
}

/**
 * 输出JSON
 *
 * @param      array   $data   The data
 * @param      bool    $exit   是否退出
 */
function echoJson($data, $exit = true)
{
    $jsonData = jsonEncode($data);
    header("Content-type: application/json; charset=utf-8");
    echo $jsonData;
    if ($exit) {
        exit(0);
    }
}

/**
 * belongsto functions_helper.php
 * 得到字符串集合
 *
 * @return     string  The charset.
 *
 * @author     assad
 * @since      2019-11-11T17:16
 */
function getCharset()
{
    return '0123456789abcdefghijklmnopqrstuvwxyz-';
}

/**
 * 获取webRoot。
 *
 * @param      bool    $full   The full
 *
 * @return     <type>  The web root.
 */
function getWebRoot($full = false)
{
    $path = $_SERVER['SCRIPT_NAME'];

    if (PHP_SAPI == 'cli') {
        if (isset($_SERVER['argv'][1])) {
            $url = parse_url($_SERVER['argv'][1]);
            $path = empty($url['path']) ? '/' : rtrim($url['path'], '/');
        }
        $path = empty($path) ? '/' : preg_replace('/\/www$/', '/www/', $path);
    }

    if ($full) {
        $http = (isset($_SERVER['HTTPS']) and strtolower($_SERVER['HTTPS']) != 'off') ? 'https://' : 'http://';
        return $http . $_SERVER['HTTP_HOST'] . substr($path, 0, (strrpos($path, '/') + 1));
    }

    $path = substr($path, 0, (strrpos($path, '/') + 1));
    $path = str_replace('\\', '/', $path);
    return $path;
}

/**
 * 得到远程文件的大小
 *
 * @param      string  $url    文件URL
 *
 * @return     integer  大小
 *
 * @author     assad
 * @since      2019-06-29T16:08
 */
function getRemoteFileSize($url)
{
    if (empty($url)) {
        return 0;
    }

    $headers = @get_headers($url, true);
    if (empty($headers) || !is_array($headers)) {
        return 0;
    }

    $headers = array_change_key_case($headers, CASE_LOWER);
    if (!isset($headers['content-length'])) {
        return 0;
    }

    $length = $headers['content-length'];
    if (is_array($length)) {
        $length = end($length);
    }

    return (int) $length;
}

/**
 * 得到本次访问的URL
 *
 * @return     string  The url.
 */
function getCurrentPageUrl()
{
    $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
    $php_self = $_SERVER['PHP_SELF'] ? safeReplace($_SERVER['PHP_SELF']) : safeReplace($_SERVER['SCRIPT_NAME']);
    $path_info = isset($_SERVER['PATH_INFO']) ? safeReplace($_SERVER['PATH_INFO']) : '';
    $relate_url = isset($_SERVER['REQUEST_URI']) ? safeReplace($_SERVER['REQUEST_URI']) : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . safeReplace($_SERVER['QUERY_STRING']) : $path_info);
    return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
}

/**
 * belongsto Helpers.php
 * 得到两点的距离
 *
 * @param      string  $location1  The location 1
 * @param      string $location2  The location 2
 * @param      int  $unitType 单位 1米 2千米
 * @param      int  $decimal 保留小数位
 *
 * @author     assad
 * @since      2019-08-05T11:26
 */
function getDistance($location1, $location2, $unitType = 1, $decimal = 2)
{

    if (!$location1 || !$location2) {
        return 0;
    }

    list($longitude1, $latitude1) = explode(",", $location1);
    list($longitude2, $latitude2) = explode(",", $location2);

    $EARTH_RADIUS = 6370.996; // 地球半径系数
    $PI = M_PI;

    $radLat1 = $latitude1 * $PI / 180.0;
    $radLat2 = $latitude2 * $PI / 180.0;

    $radLng1 = $longitude1 * $PI / 180.0;
    $radLng2 = $longitude2 * $PI / 180.0;

    $a = $radLat1 - $radLat2;
    $b = $radLng1 - $radLng2;

    $distance = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));

    $distance = $distance * $EARTH_RADIUS * 1000;

    if ($unitType == 2) {
        $distance = $distance / 1000;
    }

    return round($distance, $decimal);
}

/**
 * 得到随机字符串
 *
 * @param      integer  $tokenLen  长度
 *
 * @return     string
 */
function genRandomStr($tokenLen = 60)
{
    if (file_exists('/dev/urandom')) {
        $randomData = file_get_contents('/dev/urandom', false, null, 0, 100) . uniqid(mt_rand(), true);
    } else {
        $randomData = mt_rand() . mt_rand() . mt_rand() . mt_rand() . microtime(true) . uniqid(mt_rand(), true);
    }
    return substr(hash('sha512', $randomData), 0, $tokenLen);
}

/**
 * belongsto Helpers.php
 * 得到时间范围
 *
 * @param      integer  $range  The range
 *
 * @return     array    The time range.
 *
 * @author     assad
 * @since      2019-09-26T18:00
 */
function getTimeRange($range = 1)
{
    $startTime = 0;
    $endTime = time();
    $year = date('Y');
    $month = date('m');

    switch ($range) {
        case 1: //最近7日
            $startTime = strtotime('- 7 days');
            break;
        case 2: //本月
            $t = date('t');
            $startTime = mktime(0, 0, 0, $month, 1, $year);
            $endTime = mktime(23, 59, 59, $month, $t, $year);
            break;
        case 3: //最近半年
            $startTime = strtotime('- 180 days');
            break;
        default:
            // code...
            break;
    }
    return ['start' => $startTime, 'end' => $endTime];
}

/**
 * belongsto Helpers.php
 * 获得时间戳范围
 *
 * @param      integer  $type   The type
 *
 * @author     assad
 * @since      2019-09-19T10:34
 */
function getRangeTimestamp($type, $timestamp = null)
{
    $now = $timestamp ?? time();
    $today = strtotime('today', $now);
    switch ($type) {
        case 1: // 今日
            return [$today, strtotime('+1 day', $today) - 1];
        case 2: // 昨日
            return [strtotime('-1 day', $today), $today - 1];
        case 3: // 本周至今
            return [strtotime('monday this week', $today), $now];
        case 4: // 上周
            $monday = strtotime('monday this week', $today);
            return [strtotime('-1 week', $monday), $monday - 1];
        case 5: // 本月
            return [strtotime('first day of this month', $today), strtotime('first day of next month', $today) - 1];
        case 6: // 上月
            $first = strtotime('first day of this month', $today);
            return [strtotime('-1 month', $first), $first - 1];
        default:
            return [null, null];
    }
}

/**
 * 得到JSON数据，AJAX使用
 *
 * @param      array    $data   The data
 * @param      string   $tip    The tip
 * @param      integer  $code   The code
 *
 * @return     array   The json data.
 */
function getResponseData($data = [], $tip = 'success', $code = 0)
{
    $responseData = [];
    $responseData['code'] = $code;
    $responseData['msg'] = $tip;
    $responseData['data'] = $data;
    $executeTime = @executeTime();
    $executeTime && $responseData['executeTime'] = (string) $executeTime . ' ms';
    return $responseData;
}

/**
 * belongsto Helpers.php
 * 隐藏手机号码中间4位
 *
 * @param      string  $cellphone  待脱敏号码
 *
 * @return     string  ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2019-07-29T16:58
 */
function hidePhoneNumber($cellphone)
{
    $cellphone = (string) $cellphone;
    if (strlen($cellphone) < 11) {
        return $cellphone;
    }
    return substr($cellphone, 0, 3) . '****' . substr($cellphone, 7, 4);
}

/**
 * belongsto Helpers.php
 *  URL重定向
 *
 * @param      string   $uri     需要跳转的URL
 * @param      string   $method  auto,refresh
 * @param      integer  $code    HTTP状态码
 *
 * @author     assad
 * @since      2019-07-09T16:27
 */
function jump($uri = '', $method = 'auto', $code = null)
{
    if (!filter_var($uri, FILTER_VALIDATE_URL)) {
        $url = baseUrl();
        if ($uri != '/') {
            $uri = $url . '/' . $uri;
        }
    }
    // IIS environment likely? Use 'refresh' for better compatibility
    if ($method === 'auto' && isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false) {
        $method = 'refresh';
    } elseif ($method !== 'refresh' && (empty($code) or !is_numeric($code))) {
        if (isset($_SERVER['SERVER_PROTOCOL'], $_SERVER['REQUEST_METHOD']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1') {
            $code = ($_SERVER['REQUEST_METHOD'] !== 'GET')
            ? 303// reference: http://en.wikipedia.org/wiki/Post/Redirect/Get
            : 307;
        } else {
            $code = 302;
        }
    }

    switch ($method) {
        case 'refresh':
            header('Refresh:0;url=' . $uri);
            break;
        default:
            header('Location: ' . $uri, true, $code);
            break;
    }
    exit;
}

/**
 * belongsto Helpers.php
 * json encode 去掉转移以及unicode
 *
 * @param      array   $array  The array
 *
 * @return     <type>  ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2019-09-26T18:03
 */
function jsonEncode($array = [], $numberCheck = 0)
{
    if ($numberCheck) {
        $jsonNumericCheck = JSON_NUMERIC_CHECK;
    } else {
        $jsonNumericCheck = 0;
    }
    return json_encode($array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | $jsonNumericCheck);
}

/**
 * belongsto functions_helper.php
 * 数字转字符串
 *
 * @param      integer  $num    The number
 *
 * @return     string   转换后的字符串
 *
 * @author     assad
 * @since      2019-11-11T17:18
 */
function intToString($num)
{
    $chars = getCharset();
    $string = '';
    $len = strlen($chars);
    $useBcmath = function_exists('bcmod') && function_exists('bcdiv');
    while ($num >= $len) {
        if ($useBcmath) {
            $mod = (int) bcmod($num, $len);
            $num = bcdiv($num, $len);
        } else {
            $mod = $num % $len;
            $num = intval($num / $len);
        }
        $string = $chars[$mod] . $string;
    }
    $string = $chars[(int) $num] . $string;
    return $string;
}

/**
 * 检测是否是email地址
 *
 * @param      string   $email  email地址
 *
 * @return     boolean  True if email, False otherwise.
 */
function isEmail($email)
{
    return strlen($email) > 6 && strlen($email) <= 32 && preg_match("/^([A-Za-z0-9\-_.+]+)@([A-Za-z0-9\-]+[.][A-Za-z0-9\-.]+)$/", $email);
}

/**
 * 检测字符串是否为UTF8编码
 *
 * @param      string   $string  待检字符串
 *
 * @return     boolean  True if utf 8, False otherwise.
 */
function isUtf8($string)
{
    return preg_match('%^(?:
                    [\x09\x0A\x0D\x20-\x7E] # ASCII
                    | [\xC2-\xDF][\x80-\xBF] # non-overlong 2-byte
                    | \xE0[\xA0-\xBF][\x80-\xBF] # excluding overlongs
                    | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
                    | \xED[\x80-\x9F][\x80-\xBF] # excluding surrogates
                    | \xF0[\x90-\xBF][\x80-\xBF]{2} # planes 1-3
                    | [\xF1-\xF3][\x80-\xBF]{3} # planes 4-15
                    | \xF4[\x80-\x8F][\x80-\xBF]{2} # plane 16
                    )*$%xs', $string);
}

/**
 * 获得IP地址
 *
 * @return     string  IP地址
 */
function ip(array $trustedProxies = [])
{
    $peer = $_SERVER['REMOTE_ADDR'] ?? getenv('REMOTE_ADDR');
    if (!filter_var($peer, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return '';
    }
    if (!$trustedProxies && defined('PHPHELPERS_TRUSTED_PROXIES')) {
        $trustedProxies = PHPHELPERS_TRUSTED_PROXIES;
    }
    if (!in_array($peer, $trustedProxies, true)) {
        return $peer;
    }
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? getenv('HTTP_X_FORWARDED_FOR');
    if (!$forwarded) {
        return $peer;
    }
    foreach (array_reverse(explode(',', $forwarded)) as $hop) {
        $hop = trim($hop);
        if (!filter_var($hop, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $peer;
        }
        if (!in_array($hop, $trustedProxies, true)) {
            return $hop;
        }
    }
    return $peer;
}

/**
 * 检查IP是否在白名单之内
 *
 * @param      string  $ip         { parameter_description }
 * @param      array   $whitelist  The whitelist
 *
 * @return     bool    True if allowed ip, False otherwise.
 */
function isAllowedIp($ip, array $whitelist = [])
{
    $ip = (string) $ip;
    if (in_array($ip, $whitelist, true)) {
        return true;
    }
    foreach ($whitelist as $whitelistedIp) {
        $whitelistedIp = (string) $whitelistedIp;
        $wildcardPosition = strpos($whitelistedIp, "*");
        if ($wildcardPosition === false) {
            continue;
        }
        if (substr($ip, 0, $wildcardPosition) . "*" === $whitelistedIp) {
            return true;
        }
    }
    return false;
}

/**
 * 返回微秒
 *
 * @return     flaot
 */
function microtimeFloat()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float) $usec + (float) $sec);
}

/**
 * 创建文件目录
 *
 * @param      string   $pathname  要创建的目录
 * @param      <type>   $mode      权限
 *
 * @return     boolean  ( 是否创建成功 )
 */
function rMkdir($pathname, $mode = 0777)
{
    if (!$pathname) {
        return false;
    }
    if (strpos($pathname, '..') !== false) {
        return false;
    }

    $pathname = str_replace('\\', '/', $pathname);
    $pathname = preg_replace('#/+#', '/', $pathname);
    $pathname = rtrim($pathname, '/');
    if (preg_match('/^[A-Za-z]:$/', $pathname)) {
        $pathname .= '/';
    }
    if (is_dir($pathname)) {
        return true;
    }

    $parent = dirname($pathname);
    if ($parent === $pathname) {
        return false;
    }
    if (!is_dir($parent) && !rMkdir($parent, $mode)) {
        return false;
    }

    return @mkdir($pathname, $mode) || is_dir($pathname);
}

/**
 * belongsto Helpers.php
 * 得到月份列表
 *
 * @param      int|string    $start  开始时间戳
 * @param      int|string    $end    结束时间戳
 *
 * @return     array  月份列表
 *
 * @author     assad
 * @since      2021-04-09T16:06
 */
function monthArrList($startTimeStamp, $endTimeStamp)
{
    if (!is_numeric($startTimeStamp) || !is_numeric($endTimeStamp) || ($endTimeStamp <= $startTimeStamp)) {
        return [];
    }
    $start = strtotime(date('Y-m-01', $startTimeStamp));
    $end = strtotime(date('Y-m-01', $endTimeStamp));
    //转为时间戳
    $d = [];
    while ($start <= $end) {
        //这里累加每个月的的总秒数 计算公式：上一月1号的时间戳秒数减去当前月的时间戳秒数
        $d[] = date('Ym', $start);
        $start = strtotime('+1 month', $start);
    }
    return $d;
}

/**
 * belongsto functions_helper.php
 * 多维数组排序
 *
 * @param      array  $multi_array  数组
 * @param      string  $sort_key     排序的KEY
 * @param      string  $sort         排序方式
 * @param      string  $sort_key1    排序KEY2
 * @param      string  $sort1        排序方式
 *
 * @return     array  返回排序好的数组
 *
 * @author     assad
 * @since      2019-06-29T16:10
 */
function multiArraySort($multi_array, $sort_key, $sort = SORT_DESC, $sort_key1 = '', $sort1 = SORT_DESC)
{
    if (!is_array($multi_array)) {
        return false;
    }

    $key_array = [];
    $key_array1 = [];
    foreach ($multi_array as $row_array) {
        if (!is_array($row_array) || !array_key_exists($sort_key, $row_array)) {
            return false;
        }

        $key_array[] = $row_array[$sort_key];
        if ($sort_key1 !== '') {
            if (!array_key_exists($sort_key1, $row_array)) {
                return false;
            }
            $key_array1[] = $row_array[$sort_key1];
        }
    }

    if (empty($key_array)) {
        return $multi_array;
    }

    if (!empty($key_array1)) {
        array_multisort($key_array, $sort, SORT_NUMERIC, $key_array1, $sort1, SORT_NUMERIC, $multi_array);
    } else {
        array_multisort($key_array, $sort, SORT_NUMERIC, $multi_array);
    }
    return $multi_array;
}

/**
 * belongsto Helpers.php
 * 输出JSON
 *
 * @param      array    $data   The data
 * @param      string   $msg    The message
 * @param      integer  $code   The code
 *
 * @author     assad
 * @since      2019-07-27T10:23
 */
function outPutJson($data = [], $msg = 'success', $code = 0)
{
    $data = getResponseData($data, $msg, $code);
    $jsonData = jsonEncode($data);
    header("Content-type: application/json; charset=utf-8");
    echo $jsonData;
    exit(0);
}

/**
 * 随机字符串
 *
 * @param      integer  $length   长度
 * @param      integer  $numeric  是否只返回数字
 *
 * @return     string
 */
function random($length, $numeric = 0)
{
    $length = (int) $length;
    if ($length <= 0) {
        return '';
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
    $seed = base_convert(md5(microtime() . $documentRoot . uniqid('', true)), 16, $numeric ? 10 : 35);
    $seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
    if ($numeric) {
        $hash = '';
    } else {
        $hash = chr(mt_rand(1, 26) + mt_rand(0, 1) * 32 + 64);
        $length--;
    }

    if ($length <= 0) {
        return $hash;
    }

    $max = strlen($seed) - 1;
    for ($i = 0; $i < $length; $i++) {
        $hash .= $seed[mt_rand(0, $max)];
    }
    return $hash;
}

/**
 * Removes an utf 8 bom.
 *
 * @param      string  $string  The string
 *
 * @return     <type>  ( description_of_the_return_value )
 */
function removeUTF8Bom($string)
{
    if (substr($string, 0, 3) == pack('CCC', 239, 187, 191)) {
        return substr($string, 3);
    }
    return $string;
}

/**
 * RC4算法
 *
 * @param      string   $string     要加密的字符串
 * @param      string   $operation  ENCODE加密，DECODE解密
 * @param      string   $key        加密使用的KEY
 * @param      integer  $expiry     过期时间
 *
 * @return     string   返回加密或者解决的字符串，加密结果为base64
 */
function RC4($string, $operation = 'DECODE', $key = 'ci', $expiry = 0)
{
    $ckey_length = 4;
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

    $cryptkey = $keya . md5($keya . $keyc);
    $key_length = strlen($cryptkey);

    if ($operation === 'DECODE') {
        $string = base64_decode(substr($string, $ckey_length), true);
        if ($string === false || strlen($string) < 26) {
            return '';
        }
    } else {
        $string = sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
    }
    $string_length = strlen($string);

    $result = '';
    $box = range(0, 255);

    $rndkey = [];
    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }

    for ($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }

    if ($operation === 'DECODE') {
        $expires = substr($result, 0, 10);
        if (strspn($expires, '0123456789') !== 10 || ($expires !== '0000000000' && (int) $expires <= time())) {
            return '';
        }
        $plain = substr($result, 26);
        $expected = substr(md5($plain . $keyb), 0, 16);
        return hash_equals($expected, substr($result, 10, 16)) ? $plain : '';
    }
    return $keyc . rtrim(base64_encode($result), '=');
}

/**
 * 在后台执行 shell 命令，默认丢弃标准输出和错误输出。
 *
 * @param      string  $cmd    要执行的命令
 * @throws InvalidArgumentException 命令无效
 * @throws RuntimeException 无法启动后台 shell
 */
function runCmdNoWait($cmd)
{
    if (!is_string($cmd) || trim($cmd) === '' || strpos($cmd, "\0") !== false) {
        throw new InvalidArgumentException('命令必须是非空且不含 NUL 字节的字符串');
    }

    if (PHP_OS_FAMILY === 'Windows') {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('后台执行命令需要启用 proc_open()');
        }
        $shell = getenv('SystemRoot') . '\\System32\\cmd.exe';
        $command = '"' . $shell . '" /d /s /c "' . $cmd . '"';
        $streams = [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']];
        $process = proc_open($command, $streams, $pipes, null, null, ['bypass_shell' => true]);
        if ($process === false) {
            throw new RuntimeException('无法启动后台 shell');
        }
        // 让 PHP 在返回时释放 Windows 进程句柄。显式 proc_close() 会等待任务结束。
        return;
    }

    if (!function_exists('popen') || !function_exists('pclose')) {
        throw new RuntimeException('后台执行命令需要启用 popen() 和 pclose()');
    }
    // 整组命令在后台运行；pclose() 只等待提交任务的 shell。
    $launcher = popen("(\n" . $cmd . "\n) </dev/null >/dev/null 2>&1 &", 'r');
    if ($launcher === false || pclose($launcher) !== 0) {
        throw new RuntimeException('无法启动后台 shell');
    }
}

/**
 * 同步执行 shell 命令并直接输出其标准输出。
 *
 * @param      string  $cmd    要执行的命令
 * @param      int|null  $exitCode    接收命令的退出码
 */
function runCmd($cmd, &$exitCode = null)
{
    $exitCode = null;
    passthru($cmd, $exitCode);
}

/** @deprecated 请使用 runCmdNoWait()。 */
function rumcmdnowait($cmd)
{
    runCmdNoWait($cmd);
}

/** @deprecated 请使用 runcmd()。 */
function rumcmd($cmd, &$exitCode = null)
{
    runCmd($cmd, $exitCode);
}

/**
 * belongsto Helpers.php
 * 随机一个浮点数
 *
 * @param      integer  $min    The minimum
 * @param      integer  $max    The maximum
 * @param      integer  $decimals  小数位
 *
 * @return     <type>   ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2019-07-27T10:24
 */
function randomDecimals($min, $max, $decimals = 2)
{
    $precision = filter_var($decimals, FILTER_VALIDATE_INT);
    if ($precision === false || $precision < 0 || $precision >= strlen((string) PHP_INT_MAX)
        || !is_numeric($min) || !is_numeric($max) || $min > $max) {
        throw new InvalidArgumentException('无效的小数范围或精度');
    }
    $scale = 10 ** $precision;
    $bounds = [];
    foreach ([$min, $max] as $index => $value) {
        $scaled = $value * $scale;
        if (is_int($scaled)) {
            $bounds[] = $scaled;
            continue;
        }
        if (!is_finite($scaled) || $scaled < PHP_INT_MIN || $scaled >= (float) PHP_INT_MAX) {
            throw new InvalidArgumentException('小数范围超过整数精度');
        }
        $nearest = round($scaled);
        if ($nearest / $scale == $value) {
            $bounds[] = (int) $nearest;
        } else {
            $bounds[] = (int) ($index === 0 ? ceil($scaled) : floor($scaled));
        }
    }
    if ($bounds[0] > $bounds[1]) {
        throw new InvalidArgumentException('区间内没有符合精度的数值');
    }
    return mt_rand($bounds[0], $bounds[1]) / $scale;
}

/**
 * belongsto functions_helper.php
 * 字符串反序列化
 *
 * @param      string   $str    The string
 * @param      array    $array  The array
 * @param      integer  $i      { parameter_description }
 *
 * @return     array    ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2019-11-12T23:36
 */
function runSerialize($str, $array = [], $i = 1)
{
    $str = explode("\n$i\n", $str);
    foreach ($str as $key => $value) {
        $k = substr($value, 0, strpos($value, "\t"));
        $v = substr($value, strpos($value, "\t") + 1);
        if (strpos($v, "\n") !== false) {
            $next = $i + 1;
            $array[$k] = runSerialize($v, isset($array[$k]) && is_array($array[$k]) ? $array[$k] : [], $next);
        } elseif (strpos($v, "\t") !== false) {
            $array[$k] = rarray(isset($array[$k]) && is_array($array[$k]) ? $array[$k] : [], $v);
        } else {
            $array[$k] = $v;
        }
    }
    return $array;
}

/**
 * belongsto functions_helper.php
 * 序列化数组
 *
 * @param      array   $array   The array
 * @param      string  $string  The string
 *
 * @return     array   ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2019-11-12T23:37
 */
function rarray($array, $string)
{
    if (!is_array($array)) {
        $array = [];
    }
    $k = substr($string, 0, strpos($string, "\t"));
    $v = substr($string, strpos($string, "\t") + 1);
    if (strpos($v, "\t") !== false) {
        $array[$k] = rarray(isset($array[$k]) ? $array[$k] : [], $v);
    } else {
        $array[$k] = $v;
    }
    return $array;
}

/**
 * belongsto functions_helper.php
 * 数组序列化
 *
 * @param      array    $array  The array
 * @param      string   $ret    The ret
 * @param      integer  $i      { parameter_description }
 *
 * @return     string   ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2019-11-12T23:38
 */
function rserialize($array = [], $ret = '', $i = 1)
{
    if (!is_array($array)) {
        return null;
    }
    foreach ($array as $k => $v) {
        if (is_array($v)) {
            $next = $i + 1;
            $ret .= "$k\t";
            $ret = rserialize($v, $ret, $next);
            $ret .= "\n$i\n";
        } else {
            $ret .= "$k\t$v\n$i\n";
        }
    }
    if (substr($ret, -3) == "\n$i\n") {
        $ret = substr($ret, 0, -3);
    }
    return $ret;
}

/**
 * 增强substr方法
 *
 * @param      string  $string  The string
 * @param      int     $length  The length
 * @param      string  $append  The append
 *
 * @return     bool    ( description_of_the_return_value )
 */
function xsubstr($string, $length, $append = '')
{
    $length = (int) $length;
    if ($length <= 0) {
        return '';
    }

    $rawString = (string) $string;
    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        $string = mb_substr($rawString, 0, $length, 'utf-8');
        return mb_strlen($rawString, 'utf-8') > $length ? $string . $append : $string;
    }

    preg_match_all("/./su", $rawString, $data);
    $string = join("", array_slice($data[0], 0, $length));

    return count($data[0]) > $length ? $string . $append : $string;
}

/**
 * belongsto functions_helper.php
 * 字符串转数字
 *
 * @param      string   $string  待转字符串
 *
 * @return     integer  转换后的数字
 *
 * @author     assad
 * @since      2019-11-11T17:17
 */
function stringToInt($string)
{
    $chars = getCharset();
    $string = strrev($string);
    $baselen = strlen($chars);
    $inputlen = strlen($string);
    $useBcmath = function_exists('bcadd') && function_exists('bcmul') && function_exists('bcpow');

    if ($useBcmath) {
        $integer = '0';
        for ($i = 0; $i < $inputlen; $i++) {
            $index = strpos($chars, $string[$i]);
            if ($index === false) {
                continue;
            }
            $integer = bcadd($integer, bcmul($index, bcpow($baselen, $i)));
        }
        return explode('.', $integer)[0];
    }

    $integer = 0;
    for ($i = 0; $i < $inputlen; $i++) {
        $index = strpos($chars, $string[$i]);
        if ($index === false) {
            continue;
        }
        $integer += $index * pow($baselen, $i);
    }
    return (string) $integer;
}

/**
 * 安全的URLENCODE
 *
 * @param      string  $url    The url
 *
 * @return     string  得到安全的URL
 */
function scUrlEncode($url)
{
    static $fix = ['%21', '%2A', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D'];
    static $replacements = ['!', '*', ';', ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]"];
    return str_replace($fix, $replacements, urlencode($url));
}

/**
 * 写一条日志
 *
 * @param      string   $string  待写内容
 * @param      string   $t       切割类型day按天
 *
 * @return     boolean  ( description_of_the_return_value )
 */
function sendLog($string, $saveDir = 'sendlog', $t = 'day')
{
    $saveBaseDir = '';
    if (defined('DATA_PATH')) {
        $saveBaseDir = DATA_PATH . '/logs/';
    } elseif (defined('LOG_PATH')) {
        $saveBaseDir = LOG_PATH;
    } elseif (defined('FCPATH')) {
        $saveBaseDir = FCPATH . 'data/logs/';
    } else {
        return false;
    }
    if (!$string) {
        return false;
    }
    if (is_array($string)) {
        $string = jsonEncode($string);
    }
    $timestamp = time();
    $fileName = $saveBaseDir . $saveDir . '/log.log';
    if ($t == 'day') {
        $f = date('Ymd', $timestamp);
        $fileName = $saveBaseDir . $saveDir . '/' . $f . '.log';
    }
    $logTime = date('Y/m/d H:i:s', $timestamp);
    $record = $logTime . ' - ' . $string . "\n";
    return writeLog($fileName, $record, 'ab');
}

/**
 * 安全字符串替换
 *
 * @param      string   $string  待转换字符串
 *
 * @return     string  返回安全的字符串
 */
function safeReplace($string)
{
    $search = ['%20', '%27', '%2527', '*', '"', "'", '"', ';', '<', '>', "{", '}', '\\'];
    $replace = ['', '', '', '', '&quot;', '', '', '', '&lt;', '&gt;', '', '', ''];
    $string = str_replace($search, $replace, $string);
    return $string;
}

/**
 * belongsto Helpers.php
 * js encodeURIComponent
 *
 * @param      string  $string  The string
 *
 * @return     string  ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2019-08-17T16:30
 */
function urlencode4js($string)
{
    $fix = ['%27', '%21', '%2A', '%28', '%29'];
    $replacements = ["'", '!', '*', '(', ')'];
    return str_replace($fix, $replacements, rawurlencode($string));
}

/**
 * belongsto functions_helper.php
 * IP地址校验
 *
 * @param      string  $ip     IP地址
 *
 * @return     boolean          ( description_of_the_return_value )
 *
 * @author     assad
 * @since      2019-11-12T23:38
 */
function validIp($ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    $ip = ip2long($ip);
    if ($ip !== false) {
        $ip = (float) sprintf('%u', $ip);
        if ($ip >= 0 && $ip <= 50331647) {
            return false;
        }
        if ($ip >= 167772160 && $ip <= 184549375) {
            return false;
        }
        if ($ip >= 2130706432 && $ip <= 2147483647) {
            return false;
        }
        if ($ip >= 2851995648 && $ip <= 2852061183) {
            return false;
        }
        if ($ip >= 2886729728 && $ip <= 2887778303) {
            return false;
        }
        if ($ip >= 3221225984 && $ip <= 3221226239) {
            return false;
        }
        if ($ip >= 3232235520 && $ip <= 3232301055) {
            return false;
        }
        if ($ip >= 4294967040) {
            return false;
        }
    }
    return true;
}

/**
 * 写日志内容到文件
 *
 * @param      integer   $fileName  写入文件路径
 * @param      integer   $data       内容
 * @param      string   $method     打开文件方法
 * @param      integer  $ifLock     是否加锁
 * @param      integer  $check      The check
 * @param      integer  $chmod      The chmod
 *
 * @return     boolean
 */
function writeLog($fileName, $data, $method = 'wb+', $ifLock = 1, $check = 1, $chmod = 1)
{
    if (!$fileName) {
        return false;
    }
    if ($check && strpos($fileName, '..') !== false) {
        return false;
    }
    if (!is_dir(dirname($fileName)) && !rMkdir(dirname($fileName), 0777)) {
        return false;
    }
    return writeFile($fileName, $data, $method, $ifLock, $chmod);
}

/**
 * 写文件
 *
 * @param      string   $filePath  文件路径
 * @param      string   $data       要写入的内容
 * @param      string   $method     打开文件方法
 * @param      integer  $ifLock     是否加锁
 * @param      integer  $chmod      权限
 *
 * @return     boolean
 */
function writeFile($filePath, $data, $method = 'wb+', $ifLock = 1, $chmod = 1)
{
    $data = is_array($data) || is_object($data) ? print_r($data, true) : (string) $data;
    $truncate = $ifLock && isset($method[0]) && $method[0] === 'w';
    $mode = $truncate ? 'c' . substr($method, 1) : $method;
    $handle = @fopen($filePath, $mode);
    if (!$handle) {
        return false;
    }
    try {
        if ($ifLock && !flock($handle, LOCK_EX)) {
            return false;
        }
        if ($truncate && !ftruncate($handle, 0)) {
            return false;
        }
        $length = strlen($data);
        for ($offset = 0; $offset < $length; $offset += $written) {
            $written = fwrite($handle, substr($data, $offset, 65536));
            if (!$written) {
                return false;
            }
        }
        if (!fflush($handle)) {
            return false;
        }
    } finally {
        fclose($handle);
    }
    if ($chmod) {
        @chmod($filePath, 0777);
    }
    return true;
}
