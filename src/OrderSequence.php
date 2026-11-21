<?php

namespace PhpHelpers;

use InvalidArgumentException;
use OverflowException;
use RuntimeException;

final class OrderSequence
{
    // 2021-04-16 00:00:00 Asia/Shanghai，沿用原有订单的时间起点。
    private const EPOCH_MS = 1618502400000;

    public static function snowflake($datacenter, $worker)
    {
        foreach ([$datacenter, $worker] as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 31]]) === false) {
                throw new InvalidArgumentException('数据中心和工作节点编号须为 0 至 31 的整数');
            }
        }
        $datacenter = (int) $datacenter;
        $worker = (int) $worker;
        $elapsed = (int) floor(microtime(true) * 1000) - self::EPOCH_MS;
        if (PHP_INT_SIZE < 8 || $elapsed < 0 || $elapsed >= (1 << 41)) {
            throw new RuntimeException('雪花订单号需要 64 位 PHP 和有效的 41 位时间戳');
        }
        list($ms, $sequence) = self::next('snowflake-' . $datacenter . '-' . $worker, $elapsed, 4096, (1 << 41) - 1);
        return (string) (($ms << 22) | ($datacenter << 17) | ($worker << 12) | $sequence);
    }

    public static function orderNumber($prefix, $seqId, $length)
    {
        $length = filter_var($length, FILTER_VALIDATE_INT);
        if ($length === false || $length < 1) {
            throw new InvalidArgumentException('订单号长度须为正整数');
        }
        $key = 'order-' . $length . '-' . hash('sha256', (string) $prefix);
        if ($length <= 12) {
            // 短编号没有空间同时容纳日期和序列。用满后报错，避免回绕重号。
            $lowDigits = min(6, $length);
            $highDigits = $length - $lowDigits;
            list($high, $low) = self::next($key, 0, 10 ** $lowDigits, 10 ** $highDigits - 1);
            $head = $highDigits ? str_pad((string) $high, $highDigits, '0', STR_PAD_LEFT) : '';
            return $prefix . $head . str_pad((string) $low, $lowDigits, '0', STR_PAD_LEFT);
        }

        $digits = min(6, max(1, $length - 13));
        $scale = 10 ** $digits;
        $now = time();
        // 把本地时间映射到连续的秒数，夏令时回拨时也能沿用已分配的序列。
        $localSecond = $now + (int) date('Z', $now);
        list($second, $serial) = self::next($key, $localSecond, $scale, PHP_INT_MAX);
        $date = gmdate('ymdHis', $second);
        $sequence = str_pad((string) $serial, $digits, '0', STR_PAD_LEFT);
        $tailLength = $length - 12 - $digits;
        $tail = $tailLength ? substr(str_pad(ltrim((string) (int) $seqId, '-'), $tailLength, '0', STR_PAD_LEFT), -$tailLength) : '';
        return $prefix . $date . $sequence . $tail;
    }

    private static function next($key, $tick, $capacity, $maxTick)
    {
        $dir = defined('PHPHELPERS_ID_DIR') ? PHPHELPERS_ID_DIR : sys_get_temp_dir() . '/phphelpers-ids';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('无法创建订单号序列目录');
        }
        $path = $dir . '/' . $key;
        // 锁文件在状态文件替换后仍是同一个文件，多进程始终争用同一把锁。
        $lock = @fopen($path . '.lock', 'c+b');
        if (!$lock) {
            throw new RuntimeException('无法打开订单号序列锁');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('无法锁定订单号序列');
            }
            clearstatcache(true, $path);
            $sequence = 0;
            if (file_exists($path)) {
                $stored = file_get_contents($path);
                $parts = explode(' ', (string) $stored);
                $lastTick = filter_var($parts[0], FILTER_VALIDATE_INT);
                $lastSequence = filter_var($parts[1] ?? null, FILTER_VALIDATE_INT);
                if (count($parts) !== 2 || $lastTick === false || $lastTick < 0 || $lastTick > $maxTick
                    || $lastSequence === false || $lastSequence < 0 || $lastSequence >= $capacity) {
                    throw new RuntimeException('订单号序列文件损坏');
                }
                if ($lastTick >= $tick) {
                    $tick = $lastTick;
                    $sequence = $lastSequence + 1;
                    if ($sequence === $capacity) {
                        if ($tick === $maxTick) {
                            throw new OverflowException('订单号序列已用完');
                        }
                        $tick++;
                        $sequence = 0;
                    }
                }
            }
            $value = $tick . ' ' . $sequence;
            $pending = $path . '.new';
            if (file_put_contents($pending, $value) !== strlen($value) || !rename($pending, $path)) {
                throw new RuntimeException('无法保存订单号序列');
            }
            return [$tick, $sequence];
        } finally {
            fclose($lock);
        }
    }
}
