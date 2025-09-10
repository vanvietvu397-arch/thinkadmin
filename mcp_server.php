<?php

/*
    |--------------------------------------------------------------------------
    | 增强版多设备MCP WSS服务器
    |--------------------------------------------------------------------------
    |
    | 支持自动重连、心跳保活、连接监控
    | 优化SSL/TLS配置，提高连接稳定性
    |
*/

declare(strict_types=1);

chdir(__DIR__);
require_once __DIR__ . '/vendor/autoload.php';

// 设置必要的环境变量和常量，避免ThinkAdmin扩展中的错误
//if (!defined('ROOT_PATH')) {
//    define('ROOT_PATH', __DIR__ . '/');
//}
//if (!defined('APP_PATH')) {
//    define('APP_PATH', __DIR__ . '/app/');
//}
//if (!defined('RUNTIME_PATH')) {
//    define('RUNTIME_PATH', __DIR__ . '/runtime/');
//}

use think\App;
use think\admin\Library;
use PhpMcp\Schema\ServerCapabilities;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use app\common\service\McpWssTransportService;
use app\common\service\DeviceManagerService;
use app\common\model\DeviceModel;

// 创建应用实例，传入根目录路径
$app = new App(__DIR__);

// 在初始化之前设置Library的静态App实例，避免syspath()函数报错
Library::$sapp = $app;

// 现在初始化应用
$app->initialize();

class StderrLogger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        fwrite(STDERR, sprintf("[%s][%s] %s %s\n", 
            date('Y-m-d H:i:s'), 
            strtoupper($level), 
            $message, 
            empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE)
        ));
    }
}

try {
    $logger = new StderrLogger();
    $logger->info("🚀 启动增强版多设备MCP服务器 (支持自动重连和心跳保活)");

    // 从数据库查询启用的设备配置
    $deviceModel = new DeviceModel();
    $enabledDevices = $deviceModel->where('enable_xiaozhi', 1)
                                 ->where('is_delete', 0)
                                 ->where('status', 1)
                                 ->select()
                                 ->toArray();

    $logger->info("📱 发现启用设备: " . count($enabledDevices) . " 个");

    // 创建设备管理器
    $deviceManager = new DeviceManagerService($logger);

    // 为每个设备创建独立的MCP服务器实例
    $serverInstances = [];
    $loop = \React\EventLoop\Loop::get();

    foreach ($enabledDevices as $index => $device) {
        $deviceId = $device['id'];
        $deviceName = $device['device_name'] ?? "设备-{$deviceId}";
        $logger->info("🔧 配置设备: {$deviceName} (ID: {$deviceId})");

        // 为每个设备创建独立的DI容器
        $deviceContainer = new BasicContainer();
        $deviceContainer->set(LoggerInterface::class, $logger);
        $deviceContainer->set(DeviceManagerService::class, $deviceManager);

        // 为每个设备创建唯一的服务器信息
        $serverName = "MCP Server - {$deviceName}";
        $serverVersion = "2.0.0-{$deviceId}";

        // 创建MCP服务器实例
        $serverInstance = Server::make()
            ->withServerInfo($serverName, $serverVersion)
            ->withCapabilities(ServerCapabilities::make(
                tools: true,
                logging: true
            ))
            ->withInstructions('这是一个模板信息查询服务器，专门为小智AI语音助手设计。当用户询问"关于模板的信息"时，可以调用相关工具查询模板数据。')
             ->withTool(
                 [\app\common\service\TemplateService::class, 'queryTemplateInfo'],
                 'query_template_info',
                 '查询模板相关信息，支持关键词搜索'
             )
            ->withTool(
                [\app\common\service\TemplateService::class, 'getTemplateDetail'],
                'get_template_detail',
                '根据模板ID获取模板详细信息'
            )
            ->withLogger($logger)
            ->withContainer($deviceContainer)
            ->build();

        $logger->info("✅ 工具注册完成: {$deviceName} (2个工具)");

        // 使用定时器延迟启动每个设备，避免同时连接
        $delay = $index * 2; // 每个设备延迟2秒启动
        
         $loop->addTimer($delay, function() use ($serverInstance, $logger, $deviceId, $device, $deviceManager, $delay) {
             try {
                 $deviceName = $device['device_name'] ?? "设备-{$deviceId}";
                 $logger->info("🔌 启动设备连接: {$deviceName} (延迟{$delay}秒)");
                 
                 // 构建WSS URL - 根据设备配置或使用默认格式
                 $wssUrl = $device['xiaozhi_mcp_url'] ?? "";
                 
                 // 创建WSS传输层
                 $transport = new McpWssTransportService(
                     $wssUrl, 
                     $deviceManager, 
                     (string)$deviceId, 
                     $deviceName
                 );
                 $transport->setLogger($logger);
                 
                 // 配置重连和心跳参数
                 $transport->setReconnectConfig(10, 15); // 最多重连10次，间隔15秒
                 $transport->setHeartbeatInterval(45); // 心跳间隔45秒

                 // 存储transport实例到全局变量，供TemplateService使用
                 $GLOBALS['mcp_transport_' . $deviceId] = $transport;
                 
                 // 存储设备配置信息，但不设置当前调用设备（避免多设备冲突）
                 $GLOBALS['mcp_device_config_' . $deviceId] = $device;

                 // 添加事件监听
                 $transport->on('ready', function () use ($logger, $deviceName) {
                     $logger->info("🟢 设备就绪: {$deviceName} (支持自动重连和心跳保活)");
                 });

                 $transport->on('client_connected', function ($sessionId) use ($logger, $deviceName) {
                     $logger->info("🔗 设备已连接: {$deviceName}");
                 });

                 $transport->on('client_disconnected', function ($sessionId, $reason) use ($logger, $deviceName, $deviceId) {
                     $logger->info("🔴 设备断开: {$deviceName} - {$reason}", [
                         'deviceId' => $deviceId,
                         'sessionId' => $sessionId,
                         'reason' => $reason,
                         'timestamp' => date('Y-m-d H:i:s')
                     ]);
                 });

                 $transport->on('error', function ($error) use ($logger, $deviceName) {
                     $logger->error("❌ 设备错误: {$deviceName} - {$error->getMessage()}");
                 });
                 
                 // 启动服务器监听
                 $serverInstance->listen($transport);
                 
                 $logger->info("🎯 设备监听启动: {$deviceName}");
             } catch (\Throwable $e) {
                 $logger->error("💥 启动失败: {$deviceName} - {$e->getMessage()}");
             }
         });

        $serverInstances[] = $serverInstance;
    }

    $logger->info("⏳ 等待设备启动... (每个设备延迟2秒，支持自动重连)");

    // 添加全局状态监控（每2分钟检查一次）
    $loop->addPeriodicTimer(120, function() use ($logger, $deviceManager) {
        $devices = $deviceManager->getAllDevices();
        $activeDevices = 0;
        $deviceStatus = [];
        
        foreach ($devices as $deviceId => $device) {
            $isConnected = isset($device['connected']) && $device['connected'];
            if ($isConnected) {
                $activeDevices++;
            }
            $deviceStatus[] = [
                'deviceId' => $deviceId,
                'name' => $device['name'] ?? 'Unknown',
                'connected' => $isConnected,
                'lastSeen' => $device['last_seen'] ?? 'Never'
            ];
        }
        
        $logger->info("💓 状态监控: {$activeDevices} 个设备在线", [
            'totalDevices' => count($devices),
            'activeDevices' => $activeDevices,
            'deviceStatus' => $deviceStatus
        ]);
    });

    // 添加连接健康检查（每30秒检查一次）
    $loop->addPeriodicTimer(30, function() use ($logger, $deviceManager) {
        $devices = $deviceManager->getAllDevices();
        
        foreach ($devices as $deviceId => $device) {
            if (isset($device['connected']) && $device['connected']) {
                // 检查最后活动时间
                $lastSeen = $device['last_seen'] ?? null;
                if ($lastSeen) {
                    $lastSeenTime = strtotime($lastSeen);
                    $timeSinceLastSeen = time() - $lastSeenTime;
                    
                    // 如果超过5分钟没有活动，记录警告
                    if ($timeSinceLastSeen > 300) {
                        $logger->warning("设备长时间无活动", [
                            'deviceId' => $deviceId,
                            'deviceName' => $device['name'] ?? 'Unknown',
                            'lastSeen' => $lastSeen,
                            'minutesSinceLastSeen' => round($timeSinceLastSeen / 60, 1)
                        ]);
                    }
                }
            }
        }
    });

    // 保持服务器运行
    $loop->run();

} catch (\Throwable $e) {
    fwrite(STDERR, "[MCP NO HEARTBEAT MULTI DEVICE SERVER CRITICAL ERROR]\n");
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    fwrite(STDERR, 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
