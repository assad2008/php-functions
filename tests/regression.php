<?php

error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) {
    if (error_reporting() & $level) {
        throw new ErrorException($message, 0, $level, $file, $line);
    }
    return false;
});

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname(__DIR__) . '/src/functions_helper.php';
date_default_timezone_set('Asia/Shanghai');

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function waitForFile($file)
{
    $deadline = microtime(true) + 10;
    do {
        clearstatcache(true, $file);
        if (is_file($file) && filesize($file) > 0) {
            return trim(file_get_contents($file));
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('等待子进程超时 ' . basename($file));
}

function startWorker($task, $dir, $id)
{
    $command = [PHP_BINARY, __FILE__, $task, $dir, (string) $id];
    if (PHP_VERSION_ID < 70400) {
        $command = implode(' ', array_map('escapeshellarg', $command));
    }
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['file', $dir . '/out-' . $id, 'w'],
        2 => ['file', $dir . '/err-' . $id, 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    check(is_resource($process), '启动子进程失败');
    fclose($pipes[0]);
    return $process;
}

if (isset($argv[1]) && $argv[1] !== '--offline') {
    $dir = $argv[2];
    define('PHPHELPERS_ID_DIR', $dir);
    if ($argv[1] === 'ids') {
        for ($i = 0; $i < 100; $i++) {
            echo getOrderId(), ' ', genOrderId(), "\n";
            check(writeFile($dir . '/append', $argv[3] . '-' . $i . "\n", 'ab', 1, 0), '追加写失败');
        }
    } elseif ($argv[1] === 'background') {
        $id = $argv[3];
        waitForFile($dir . '/release-' . $id);
        fwrite(STDOUT, "stdout\n");
        fwrite(STDERR, "stderr\n");
        file_put_contents($dir . '/done-' . $id, 'done');
    } elseif ($argv[1] === 'overwrite') {
        file_put_contents($dir . '/writer-ready', '1');
        check(writeFile($dir . '/overwrite', 'new', 'wb+', 1, 0), '覆盖写失败');
    } elseif ($argv[1] === 'http') {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        check($server !== false, $error);
        file_put_contents($dir . '/listen', stream_socket_get_name($server, false));
        for ($i = 0; $i < 4; $i++) {
            $client = stream_socket_accept($server, 8);
            check($client !== false, '未收到 HTTP 请求');
            $line = trim(fgets($client));
            $headers = [];
            while (($header = fgets($client)) !== false && trim($header) !== '') {
                list($name, $value) = explode(':', $header, 2);
                $headers[strtolower($name)] = trim($value);
            }
            $length = (int) ($headers['content-length'] ?? 0);
            $payload = $length ? stream_get_contents($client, $length) : '';
            if (strpos($line, '/slow') !== false) {
                usleep(2000000);
            } else {
                $body = json_encode([$line, $headers['host'], $headers['cookie'], $payload]);
                fwrite($client, "HTTP/1.0 200 OK\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
            }
            fclose($client);
        }
        fclose($server);
    } elseif ($argv[1] === 'clock') {
        define('SYS_START_TIME', '0.00000000 ' . (time() - 1234));
        check(executeTime() >= 1234000 && executeTime() < 1236000, 'microtime 字符串计时');
    }
    exit(0);
}

$dir = sys_get_temp_dir() . '/phphelpers-test-' . uniqid('', true);
check(mkdir($dir, 0700), '创建测试目录失败');
define('PHPHELPERS_ID_DIR', $dir);
$network = !in_array('--offline', $argv, true);
$children = [];
try {
    foreach (['runcmd' => 0, 'rumcmd' => 7] as $run => $expectedCode) {
        $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('echo 42; exit(' . $expectedCode . ');');
        ob_start();
        $result = $run($command, $exitCode);
        $output = ob_get_clean();
        check($result === null && $output === '42' && $exitCode === $expectedCode, $run . ' 输出和退出码');
    }
    $originalDir = getcwd();
    chdir($dir);
    try {
        foreach (['runcmdnowait', 'rumcmdnowait'] as $run) {
            $command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, __FILE__, 'background', '.', $run]));
            if ($run === 'rumcmdnowait') {
                $command .= ' > "background output.log" 2>&1';
            }
            check($run($command) === null, $run . ' 返回值');
            // 子进程必须等本次调用返回后才能完成，避免依赖固定的耗时阈值。
            file_put_contents('release-' . $run, 'go');
            check(waitForFile('done-' . $run) === 'done', $run . ' 后台执行');
        }
        check(file('background output.log', FILE_IGNORE_NEW_LINES) === ['stdout', 'stderr'], '后台命令保留显式输出重定向');
    } finally {
        chdir($originalDir);
    }

    check(ciGmdate(0, 'H:i', 0, 0) === '00:00', 'UTC 与时间戳 0');
    check(in_array(ciGmdate(), ['刚才', '1&nbsp;秒前'], true), '默认时间');
    check(RC4('d351XMeuBe4eVfrJXulgyULpoKD9YggBw9H4+QeQRUZM6ld0RCq2Z1oU28E', 'DECODE', 'test-key') === 'legacy-order-42', '旧密文');
    check(RC4('') === '' && RC4('invalid') === '', '无效密文');
    check(RC4(RC4('中文', 'ENCODE', 'key'), 'DECODE', 'key') === '中文', '密文往返');
    check(RC4(RC4('expired', 'ENCODE', 'key', -1), 'DECODE', 'key') === '', '已过期密文');
    check(randomDecimals(0.29, 0.29) === 0.29 && randomDecimals(-0.29, -0.29) === -0.29, '小数整数化');
    check(randomDecimals('0.281', '0.291') === 0.29, '区间内可用的小数');
    check(getRandPro([0, 0]) === '' && getRandPro([1, -1]) === '' && getRandPro([0, '02', 0]) === 1, '权重');
    check(cutStr('&amp;ABCDEFG', 6, '') === '&amp;ABCDE', 'HTML 实体');
    check(cutStr('中文ABC', 4, '') === '中文', '中文截断');
    check(cutStr('中文', 4) === '中文' && cutStr('abc', 0) === '', '中文恰好放下或无空间');
    if (function_exists('mb_convert_encoding') || function_exists('iconv')) {
        check(convertEncoding(hex2bin('c0eef7ce'), 'GBK') === hex2bin('e69d8ee9ad91'), '显式 GBK 编码');
    }

    $feb = dayArrList('2024-02');
    check(count($feb) === 29 && end($feb) === '20240229' && $feb === dayArrList('2024-02-18'), '闰月');
    check(dayArrList('2025-02-29') === [] && dayArrList('bad') === [], '无效月份');
    $sunday = strtotime('2024-03-31 16:20');
    check(getRangeTimestamp(3, $sunday) === [strtotime('2024-03-25'), $sunday], '周日的本周');
    check(getRangeTimestamp(4, $sunday) === [strtotime('2024-03-18'), strtotime('2024-03-25') - 1], '周日的上周');
    check(getRangeTimestamp(6, $sunday) === [strtotime('2024-02-01'), strtotime('2024-03-01') - 1], '月末的上月');
    check(getRangeTimestamp(99) === [null, null], '未知时间范围');
    date_default_timezone_set('America/New_York');
    check(count(array_unique(dayArrList('2024-11'))) === 30, '夏令时切换月份');
    date_default_timezone_set('Asia/Shanghai');

    $_SERVER['HTTP_HOST'] = 'example.test';
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
    check(strpos(baseUrl(), 'http://example.test') === 0, '基础 URL');
    check(!validIp('bad') && !validIp('255.255.255.255') && !validIp('10.0.0.1') && validIp('8.8.8.8'), 'IPv4 校验');
    $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8, 10.0.0.1';
    check(ip() === '10.0.0.2' && ip(['10.0.0.1', '10.0.0.2']) === '8.8.8.8', '可信代理链');

    define('SYS_START_TIME', microtime(true) - 1234);
    check(executeTime() >= 1233999 && preg_match('/^1234\.\d{6}$/', executeTime(true)), '浮点时间计时');
    $children['clock'] = startWorker('clock', $dir, 'clock');
    if ($network) {
        $children['http'] = startWorker('http', $dir, 'http');
        $listen = waitForFile($dir . '/listen');
        $port = parse_url('http://' . $listen, PHP_URL_PORT);
        $host = 'helper.test:' . $port;
        $url = 'http://' . $host;
        $response = dfopen($url . '?a=1', 0, '', 'session=test', '127.0.0.1', 2, 0);
        check(json_decode($response, true) === ['GET /?a=1 HTTP/1.0', $host, 'session=test', ''], '非阻塞 HTTP 与指定 IP');
        $response = dfopen($url, 0, '0', '', '127.0.0.1', 2, 1);
        check(json_decode($response, true) === ['POST / HTTP/1.0', $host, '', '0'], '阻塞 HTTP 与 POST');
        check(strlen(dfopen($url, 3, '', '', '127.0.0.1', 2)) === 3, 'HTTP 返回字节上限');
        $start = microtime(true);
        check(dfopen($url . '/slow', 0, '', '', '127.0.0.1', 0.15) === '' && microtime(true) - $start < 1.5, 'HTTP 超时');
    }
    check(dfopen('relative/path') === '', '缺少主机');

    $file = $dir . '/overwrite';
    $guard = fopen($file, 'w+b');
    try {
        check(flock($guard, LOCK_EX) && fwrite($guard, 'original') === 8 && fflush($guard), '持有原文件锁');
        $children['writer'] = startWorker('overwrite', $dir, 'writer');
        waitForFile($dir . '/writer-ready');
        usleep(150000);
        rewind($guard);
        check(stream_get_contents($guard) === 'original', '锁定期间原文件被截断');
    } finally {
        fclose($guard);
    }

    foreach ([1, 2, 3, 4] as $id) {
        $children[$id] = startWorker('ids', $dir, $id);
    }
    foreach (array_keys($children) as $id) {
        $status = proc_close($children[$id]);
        unset($children[$id]);
        check($status === 0, '子进程 ' . $id . ' ' . file_get_contents($dir . '/err-' . $id));
    }
    check(file_get_contents($file) === 'new', '锁释放后覆盖写入');
    $orders = $snowflakes = [];
    foreach ([1, 2, 3, 4] as $id) {
        foreach (file($dir . '/out-' . $id, FILE_IGNORE_NEW_LINES) as $line) {
            list($number, $snowflake) = explode(' ', $line);
            check(preg_match('/^YEE\d{18}$/D', $number) === 1, '订单号格式');
            $orders[$number] = $snowflakes[$snowflake] = true;
        }
    }
    check(count($orders) === 400 && count($snowflakes) === 400, '跨进程订单号重复');
    check(count(array_unique(file($dir . '/append'))) === 400, '并发追加');
    check(((((int) genOrderId(3, 9)) >> 12) & 1023) === ((3 << 5) | 9), '雪花节点位');
    check(getOrderId('X', 0, 2) === 'X00' && getOrderId('X', 0, 2) === 'X01', '短编号');
    for ($i = 0; $i < 10; $i++) {
        check(getOrderId('S', 0, 1) === 'S' . $i, '一位编号');
    }
    try {
        getOrderId('S', 0, 1);
        throw new RuntimeException('短编号用完后重复发号');
    } catch (OverflowException $expected) {
        // 该前缀和长度的号码已全部发出。
    }
    echo $network ? 'HTTP tested. ' : 'HTTP loopback skipped. ';
    echo 'OK PHP ', PHP_VERSION, ' (4 workers, 400 unique IDs per generator)', PHP_EOL;
} finally {
    foreach ($children as $child) {
        proc_terminate($child);
        proc_close($child);
    }
    foreach (new DirectoryIterator($dir) as $entry) {
        if ($entry->isFile()) {
            unlink($entry->getPathname());
        }
    }
    rmdir($dir);
}
