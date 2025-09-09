<?php
/**
 * run with command 
 * php start.php start
 */

ini_set('display_errors', 'on');
use Workerman\Worker;

if(strpos(strtolower(PHP_OS), 'win') === 0)
{
    exit("start.php not support windows, please use start_for_win.bat\n");
}

// 检查扩展
if(!extension_loaded('pcntl'))
{
    exit("Please install pcntl extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
}

if(!extension_loaded('posix'))
{
    exit("Please install posix extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
}

// 标记是全局启动
define('GLOBAL_START', 1);

require_once __DIR__ . '/vendor/autoload.php';

// 加载所有workerman目录下的启动文件
require_once __DIR__ . '/workerman/start_register.php';
require_once __DIR__ . '/workerman/start_gateway.php';
require_once __DIR__ . '/workerman/start_businessworker.php';
require_once __DIR__ . '/workerman/start_web.php';
// 运行所有服务
Worker::runAll();
