# php-functions

PHP自用函数库

| 函数名称  |      函数描述    | 参数 |
|----------|:-------------:|--------:|
| baseUrl |  获得当前页面根URL |$atRoot = FALSE, $atCore = FALSE, $parse = FALSE|
| cutStr|    截取字符串   |$string, $length, $dot = '...'|
| countFileLines| 计算文件行数 |$filepath|
|ciAddslashes|深度转义字符串|$string, $force = 1|
|ciGmdate|格式化时间|$timestamp = "", $format = "Y-n-d H:i", $convert = 1|
|debug|打印变量|$var = null, $type = 2|
|downloadFile|下载文件到前端|$file, $fileName = ""|
|dayArrList|根据时间获得当月所有日期|$month, $format = 'Ymd'|
|executeTime|求程序运行时间，某些可能无法运行|$sec = false|
|fileGetContents|重写file_get_cotents|$url|
|getFileExt|得到文件扩展名|$fileName|
|getOrderId|生成订单号，根据日期|$type = 'CI', $seqId = 0, $lenth = 18|
|genOrderId|根据雪花算法生成订单|$datacenterId = 1|
|getJsonData|组装json数据|$data = [], $tip = 'success', $code = 0|
|getRandPro|得到概率数组各个值的概率|$proArr|
|dfopen|请求一个文件|$url|
|getCharset|字符串a-x0-9|无|
|getRemoteFileSize|远程获取文件大小|$url|
|getCurrentPageUrl|得到当前页面的URL|无|
|getDistance|根据两点经纬度计算距离|$location1, $location2, $unitType = 1, $decimal = 2|
|genRandomStr|生成随机字符串|$tokenLen = 60|
|getTimeRange|计算时间范围|$range = 1|
|getRangeTimestamp|计算时间戳范围|$type|
|getResponseData|组装json数据|$data = [], $tip = 'success', $code = 0|
|hidePhoneNumber|隐藏手机号码中间4位|$cellphone|
|jump|URL重定向|$uri = '', $method = 'auto', $code = NULL|
|jsonEncode|json encode 去掉转移以及unicode|$array = [], $numberCheck = 0|
|intToString|数字转字符串|$num|
|isEmail|检测是否是email地址|$email|
|isUtf8|检测字符串是否为UTF8编码|$string|
|ip|获得IP地址|无|
|isAllowedIp|检查IP是否在白名单之内|$ip, array $whitelist = []|
|microtimeFloat|返回微秒|无|
|rMkdir|循环创建文件目录|$pathname, $mode = 0777|
|monthArrList|得到月份列表|$startTimeStamp, $endTimeStamp|
|multiArraySort|多维数组排序|$multi_array, $sort_key, $sort = SORT_DESC, $sort_key1 = '', $sort1 = SORT_DESC|
|outPutJson|输出JSON|$data = [], $msg = 'success', $code = 0|
|random|随机字符串|$length, $numeric = 0|
|RC4|RC4算法|$string, $operation = 'DECODE', $key = 'ci', $expiry = 0|
|rumcmdnowait|无需等待执行一条Linux命令|$cmd|
|rumcmd|执行一条Linux命令|$cmd|
|randomDecimals|随机一个浮点数|$min, $max, $decimals = 2|
|runSerialize|字符串反序列化|$str, $array = [], $i = 1|
|rarray|序列化数组|$array, $string|
|rserialize|数组序列化|$array = [], $ret = '', $i = 1|
|stringToInt|字符串转数字|$string|
|scUrlEncode|安全的URLENCODE|$url|
|sendLog|写一条日志|$string, $saveDir = 'sendlog', $t = 'day'|
|safeReplace|安全字符串替换|$string|
|urlencode4js|js encodeURIComponent|$string|
|validIp|IP地址校验|$ip|
|writeLog|写日志内容到文件|$fileName, $data|
|writeFile|写文件|$filePath, $data|