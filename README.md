# php-functions

适用于 PHP 7.4 和 PHP 8.x 的公共函数库。提供 57 个全局函数，涵盖日期统计、中文字符串处理、JSON 响应、订单号、HTTP 请求和文件日志。Composer 包名为 `assad2008/phphelpers`。

## 安装与快速使用

在业务项目中通过 Composer 安装。

```bash
composer require assad2008/phphelpers
```

Composer 会自动加载全局函数。如果框架已经加载 `vendor/autoload.php`，可以直接调用。下面的示例取指定月份的所有日期，并组装接口响应。

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$days = dayArrList('2024-02', 'Y-m-d');
echo getJsonData(['days' => count($days), 'last_day' => end($days)]);
// 未设置请求起始时间时输出 {"code":0,"msg":"success","data":{"days":29,"last_day":"2024-02-29"}}
```

**手动引入**时，复制整个 [src](src/) 目录，保留 `functions_helper.php` 和 `OrderSequence.php` 的同级关系，然后在应用入口执行 `require_once __DIR__ . '/src/functions_helper.php';`。订单号实现随库提供，无需单独安装 Snowflake 包。

## 升级旧项目

57 个函数都保留了原名，已有参数的名称、位置和默认值均可沿用。下列三个函数增加了可选参数。

| 旧调用 | 可选的新写法 | 适用场景 |
| --- | --- | --- |
| `genOrderId(1)` | `genOrderId(1, 2)` | 为数据中心 1 指定工作节点 2。 |
| `getRangeTimestamp(6)` | `getRangeTimestamp(6, $timestamp)` | 以指定时间为基准计算上月范围，适合历史统计。 |
| `ip()` | `ip(['10.0.0.10'])` | 请求来自该代理时解析客户端地址。 |

原先依赖转发头的项目，升级时要配置可信代理。使用 1～12 位短订单号的项目，也要核对业务是否依赖原来的日期前缀。PHP 8 使用命名参数调用 `getOrderId()` 时，长度参数仍拼写为 `$lenth`。

## 常用示例

### 统计上一个自然月

`getRangeTimestamp()` 返回两个 Unix 时间戳，包含起止两端。以 2024 年 3 月 31 日为基准，上月范围会覆盖闰年的 2 月 29 日。

```php
date_default_timezone_set('Asia/Shanghai');

$reference = strtotime('2024-03-31 12:00:00');
[$start, $end] = getRangeTimestamp(6, $reference);

echo date('Y-m-d H:i:s', $start); // 2024-02-01 00:00:00
echo date('Y-m-d H:i:s', $end);   // 2024-02-29 23:59:59
```

### 生成订单号

| 函数 | 返回格式 | 多进程和多机部署 |
| --- | --- | --- |
| `getOrderId('ORD', 0, 18)` | `ORD` 加 18 位数字，前 12 位按本地时间 `ymdHis` 编排。 | 同一前缀和长度共用序列。跨主机发号时需共享支持文件锁和原子重命名的存储。 |
| `genOrderId(1, 2)` | 十进制雪花 ID 字符串，需要 64 位 PHP。 | 各主机独立保存状态时，分配不同的「数据中心、工作节点」组合，两个编号均为 0～31。 |

两种函数默认将状态和锁文件存放在 `sys_get_temp_dir() . '/phphelpers-ids'`。生产环境可在应用入口指定持久目录，让参与发号的 PHP 进程都能读写。请保留目录内的序列文件，清理文件会丢失已分配的进度。

```php
define('PHPHELPERS_ID_DIR', '/var/lib/myapp/order-sequences');
define('PHPHELPERS_WORKER_ID', 2);

$orderNumber = getOrderId('ORD'); // ORD 加 18 位数字
$snowflakeId = genOrderId(1);     // 使用配置的工作节点 2
```

- **短订单号**　`getOrderId('A', 0, 2)` 在全新序列上依次生成 `A00` 至 `A99`。号码用尽后抛出 `OverflowException`。长度大于 12 时才使用日期前缀，时间回拨或当前时间段的序列用满时，按逻辑时间向前发号。
- **历史雪花 ID**　当前时间起点固定为北京时间 2021-04-16 00:00:00。旧版按服务器默认时区解释该日期，解析历史 ID 时应使用生成当时的时间起点。

### 反向代理下获取客户端 IP

`ip()` 默认返回 `REMOTE_ADDR` 中的 IPv4 地址。若所有请求都经过已知的 Nginx 代理，可以在入口配置一次，原有业务代码继续调用 `ip()`。

```php
define('PHPHELPERS_TRUSTED_PROXIES', ['10.0.0.10', '10.0.0.11']);

$clientIp = ip();
```

请将示例地址替换为实际代理的 IPv4 地址。只有直连对端在清单中时，函数才从右向左检查 `X-Forwarded-For`，返回遇到的第一个非可信代理地址。入口代理应覆盖外部传入的转发头，或正确追加实际对端地址。

## 函数索引

### 日期与耗时

| 函数签名 | 用法与返回值 |
| --- | --- |
| `dayArrList($month, $format = 'Ymd')` | 接收 `Y-m` 或 `Y-m-d`，返回当月每天的日期字符串。无效日期返回 `[]`。 |
| `monthArrList($startTimeStamp, $endTimeStamp)` | 返回起止时间戳覆盖的月份，格式为 `Ym`，包含首尾月份。结束时间须晚于开始时间。 |
| `getRangeTimestamp($type, $timestamp = null)` | 返回 `[开始时间戳, 结束时间戳]`。类型 1～6 依次为今日、昨日、本周至今、上周、本月、上月。周一为一周的起点。 |
| `getTimeRange($range = 1)` | 返回含 `start` 和 `end` 的关联数组。类型 1 为最近 7 天，2 为本月，3 为最近 180 天。 |
| `ciGmdate($timestamp = "", $format = "Y-n-d H:i", $convert = 1, $timeoffset = null)` | 格式化时间。`$convert = 1` 时可显示“刚才”“昨天”等相对时间，`$convert = 0` 时按指定格式输出。`$timeoffset` 以小时计。 |
| `microtimeFloat()` | 返回带小数秒的 Unix 时间戳。 |
| `executeTime($sec = false)` | 根据 `SYS_START_TIME` 或 Yaf/Zend Registry 的 `starttime` 计算耗时。默认返回毫秒，传 `true` 返回保留六位小数的秒数字符串，缺少起始时间时返回 `false`。 |

### 订单号、随机数与数值转换

| 函数签名 | 用法与返回值 |
| --- | --- |
| `getOrderId($type = 'YEE', $seqId = 0, $lenth = 18)` | 生成带业务前缀的订单号，`$lenth` 仅计算数字部分。 |
| `genOrderId($datacenterId = 1, $workerId = null)` | 生成十进制雪花 ID 字符串。数据中心和工作节点均取 0～31。 |
| `getRandPro($proArr)` | 按非负整数权重抽取一个数组键。权重无效或总和为零时返回空字符串。 |
| `randomDecimals($min, $max, $decimals = 2)` | 在闭区间内按指定小数位数取随机值。区间无可选值或精度无效时抛出 `InvalidArgumentException`。 |
| `random($length, $numeric = 0)` | 生成指定长度的随机字符串，`$numeric = 1` 时仅含数字。 |
| `genRandomStr($tokenLen = 60)` | 返回小写十六进制随机字符串，默认 60 位，最长 128 位。 |
| `getCharset()` | 返回进制转换使用的 37 个字符，即 `0–9`、`a–z` 和 `-`。 |
| `intToString($num)` | 把非负整数编码为上述字符集的字符串。大整数转换可使用 BCMath 扩展。 |
| `stringToInt($string)` | 把上述字符集的编码转换为十进制数字字符串。 |

### 字符串、编码与校验

| 函数签名 | 用法与返回值 |
| --- | --- |
| `xsubstr($string, $length, $append = '')` | 按 UTF-8 字符数截取，仅在发生截断时追加 `$append`。 |
| `cutStr($string, $length, $dot = '...')` | 按显示宽度截取并处理常见 HTML 实体。ASCII 计 1，中文计 2，截断时预留后缀长度。 |
| `convertEncoding($string, $fromEncoding, $toEncoding = 'utf-8')` | 按指定源编码转码，优先使用 mbstring，其次使用 iconv。 |
| `isUtf8($string)` | 用库内正则检查 UTF-8 文本，匹配返回 `1`，不匹配返回 `0`。 |
| `removeUTF8Bom($string)` | 移除字符串开头的 UTF-8 BOM。 |
| `hidePhoneNumber($cellphone)` | 隐藏 11 位手机号中间四位，短于 11 位时原样返回。 |
| `isEmail($email)` | 按库内格式校验邮箱，接受 7～32 字节的地址。 |
| `ciAddslashes($string, $force = 1)` | 递归转义数组的键和值，对标量调用 `addslashes()`。`$force` 为历史保留参数。 |
| `safeReplace($string)` | 按固定字符表替换引号、尖括号等字符。 |
| `RC4($string, $operation = 'DECODE', $key = 'ci', $expiry = 0)` | 读写旧版密文。编码用 `ENCODE`，有效期以秒计。解码失败或密文过期返回空字符串。 |

### 数组、序列化与 JSON

| 函数签名 | 用法与返回值 |
| --- | --- |
| `multiArraySort($multi_array, $sort_key, $sort = SORT_DESC, $sort_key1 = '', $sort1 = SORT_DESC)` | 对二维数组按一列或两列数值排序，返回排序后的数组。缺少排序键时返回 `false`。 |
| `rserialize($array = [], $ret = '', $i = 1)` | 把数组写成库自定义的制表符和换行分隔格式。 |
| `runSerialize($str, $array = [], $i = 1)` | 解析 `rserialize()` 使用的文本格式，返回数组。 |
| `rarray($array, $string)` | 把制表符分隔的键值片段还原到数组中，供 `runSerialize()` 处理嵌套数据。 |
| `getResponseData($data = [], $tip = 'success', $code = 0)` | 返回含 `code`、`msg`、`data` 的数组。有有效耗时值时附加 `executeTime`。 |
| `getJsonData($data = [], $tip = 'success', $code = 0)` | 返回含 `code`、`msg`、`data` 的 JSON 字符串。有有效耗时值时附加 `execute_time`。 |
| `jsonEncode($array = [], $numberCheck = 0)` | 编码为 JSON，保留中文和斜杠。`$numberCheck = 1` 时将数字字符串转为数值。 |
| `echoJson($data, $exit = true)` | 将传入的数据直接编码并输出 JSON。默认结束脚本，传 `false` 可继续执行。 |
| `outPutJson($data = [], $msg = 'success', $code = 0)` | 将数据包装为标准响应，输出 JSON 并结束脚本。 |

### URL、HTTP 与 IP

| 函数签名 | 用法与返回值 |
| --- | --- |
| `baseUrl($atRoot = false, $atCore = false, $parse = false)` | 获取当前脚本的基础 URL，可选择站点根目录或核心目录。`$parse = true` 时返回解析结果。 |
| `getWebRoot($full = false)` | 返回脚本所在的 Web 目录。`$full = true` 时附带协议和主机名。 |
| `getCurrentPageUrl()` | 根据服务器变量拼接当前页面的完整 URL。 |
| `jump($uri = '', $method = 'auto', $code = null)` | 发送重定向响应并结束脚本。 |
| `scUrlEncode($url)` | 编码 URL，同时保留 `/`、`?`、`&` 等分隔符。 |
| `urlencode4js($string)` | 对单个 URI 组件编码，采用与 JavaScript `encodeURIComponent()` 对应的字符规则。 |
| `fileGetContents($url)` | 读取文件或 URL，HTTP 超时为 3 秒，读取失败返回 `false`。 |
| `dfopen($url, $limit = 0, $post = '', $cookie = '', $ip = '', $timeout = 3, $block = 0)` | 通过 HTTP/HTTPS 获取响应正文。可设置 POST 数据、Cookie、连接 IP、超时和读取字节数。`$limit = 0` 表示不限长度。 |
| `getRemoteFileSize($url)` | 读取响应头 `Content-Length`。取不到长度时返回 `0`。 |
| `ip(array $trustedProxies = [])` | 返回客户端 IPv4。通过代理部署时按可信代理清单解析转发链。 |
| `validIp($ip)` | 校验 IPv4 格式，并排除库内列出的私有、回环等网段。 |
| `isAllowedIp($ip, array $whitelist = [])` | 按白名单匹配完整地址或尾部通配符，如 `192.168.1.*`。 |
| `getDistance($location1, $location2, $unitType = 1, $decimal = 2)` | 计算两点的球面距离。坐标按 `经度,纬度` 传入，默认单位为米，`$unitType = 2` 时为公里。 |

### 文件、日志与命令

| 函数签名 | 用法与返回值 |
| --- | --- |
| `getFileExt($fileName)` | 返回小写文件扩展名，最多取 10 个字符。 |
| `countFileLines($filepath)` | 统计文本行数。文件不存在或不可读时返回 `0`。 |
| `rMkdir($pathname, $mode = 0777)` | 递归创建目录，返回是否成功。 |
| `writeFile($filePath, $data, $method = 'wb+', $ifLock = 1, $chmod = 1)` | 写入已有目录中的文件，默认加锁并覆盖。`$method = 'ab'` 时追加，`$chmod = 1` 时写完尝试设置权限为 `0777`。 |
| `writeLog($fileName, $data, $method = 'wb+', $ifLock = 1, $check = 1, $chmod = 1)` | 按需创建父目录并调用 `writeFile()`。默认覆盖，持续写日志时使用 `'ab'`。 |
| `sendLog($string, $saveDir = 'sendlog', $t = 'day')` | 追加带时间的日志，默认按天分文件。日志根目录取自 `DATA_PATH`、`LOG_PATH` 或 `FCPATH`，均未定义时返回 `false`。 |
| `downloadFile($file, $fileName = "")` | 发送下载响应并结束脚本。可指定浏览器显示的文件名。 |
| `runcmd($cmd, &$exitCode = null)` | 同步执行并直接输出标准输出，返回 `null`。按需传入第二个变量接收退出码。 |
| `runcmdnowait($cmd)` | 在 Linux、macOS 和 Windows 上提交后台命令后返回 `null`。默认丢弃标准输出和错误输出，可在命令中重定向到日志。 |

旧名称 `rumcmd()` 和 `rumcmdnowait()` 仍可调用，参数与对应的新名称相同。

```php
runcmd('php --version', $exitCode);
runcmdnowait('php worker.php >> worker.log 2>&1');
```

后台函数正常返回表示 shell 已接受任务。任务最终是否成功，应查看任务状态或日志。Windows 需要启用 `proc_open()`，Linux 和 macOS 需要启用 `popen()`、`pclose()`。命令为空或含 NUL 字节时抛出 `InvalidArgumentException`，无法启动后台 shell 时抛出 `RuntimeException`。

## 运行测试

在本仓库根目录执行。完整回归会启动本地 HTTP 服务及多个 PHP 子进程，检查日期边界、编码、旧密文、文件锁和并发发号。

```bash
php tests/regression.php
```

环境限制本机 TCP 连接时使用 `php tests/regression.php --offline`。该模式跳过本地 HTTP 连接测试，仍执行多进程发号和文件读写测试。[CI 工作流](.github/workflows/php.yml) 配置了 PHP 7.4、8.0、8.3、8.4、8.5，并单独测试 HTTPS 主机名校验。
