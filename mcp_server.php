<?php

/*
    |--------------------------------------------------------------------------
    | 无心跳版多设备MCP WSS服务器
    |--------------------------------------------------------------------------
    |
    | 完全依赖小智AI的心跳机制，不主动发送心跳
    | 专注于保持连接稳定，减少干扰
    |
*/

declare(strict_types=1);

chdir(__DIR__);
require_once __DIR__ . '/vendor/autoload.php';

use PhpMcp\Schema\ServerCapabilities;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use app\admin\controller\McpWssTransport;
use app\admin\controller\DeviceManager;

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
    $logger->info("🚀 启动无心跳版多设备MCP服务器 (依赖小智AI心跳机制)");

    // 加载设备配置
    $deviceConfig = require 'config/devices.php';
    $devices = $deviceConfig['devices'] ?? [];
    $enabledDevices = array_filter($devices, fn($device) => $device['enabled']);

    $logger->info("📱 发现启用设备: " . count($enabledDevices) . " 个");

    // 创建设备管理器
    $deviceManager = new DeviceManager($logger);

    // 为每个设备创建独立的MCP服务器实例
    $serverInstances = [];
    $loop = \React\EventLoop\Loop::get();

    foreach ($enabledDevices as $deviceId => $device) {
        $logger->info("🔧 配置设备: {$device['name']} ({$deviceId})");

        // 为每个设备创建独立的DI容器
        $deviceContainer = new BasicContainer();
        $deviceContainer->set(LoggerInterface::class, $logger);
        $deviceContainer->set(DeviceManager::class, $deviceManager);

        // 为每个设备创建唯一的服务器信息
        $serverName = "MCP Server - {$device['name']}";
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
                [\app\common\service\TemplateService::class, 'getTemplateCategories'],
                'get_template_categories',
                '获取模板分类统计信息'
            )
            ->withTool(
                [\app\common\service\TemplateService::class, 'getTemplateDetail'],
                'get_template_detail',
                '根据模板ID获取模板详细信息'
            )
            ->withLogger($logger)
            ->withContainer($deviceContainer)
            ->build();

        $logger->info("✅ 工具注册完成: {$device['name']} (3个工具)");

        // 使用定时器延迟启动每个设备，避免同时连接
        $delay = array_search($deviceId, array_keys($enabledDevices)) * 10; // 每个设备延迟10秒启动
        
        $loop->addTimer($delay, function() use ($serverInstance, $logger, $deviceId, $device, $deviceManager, $delay) {
            try {
                $logger->info("🔌 启动设备连接: {$device['name']} (延迟{$delay}秒)");
                
                // 创建WSS传输层
                $transport = new McpWssTransport(
                    $device['wss_url'], 
                    $deviceManager, 
                    $deviceId, 
                    $device['name']
                );
                $transport->setLogger($logger);

                // 添加事件监听
                $transport->on('ready', function () use ($logger, $device) {
                    $logger->info("🟢 设备就绪: {$device['name']} (依赖小智AI心跳)");
                });

                $transport->on('client_connected', function ($sessionId) use ($logger, $device) {
                    $logger->info("🔗 设备已连接: {$device['name']}");
                });

                $transport->on('client_disconnected', function ($sessionId, $reason) use ($logger, $device) {
                    $logger->info("🔴 设备断开: {$device['name']} - {$reason}");
                });

                $transport->on('error', function ($error) use ($logger, $device) {
                    $logger->error("❌ 设备错误: {$device['name']} - {$error->getMessage()}");
                });
                
                // 启动服务器监听
                $serverInstance->listen($transport);
                
                $logger->info("🎯 设备监听启动: {$device['name']}");
            } catch (\Throwable $e) {
                $logger->error("💥 启动失败: {$device['name']} - {$e->getMessage()}");
            }
        });

        $serverInstances[] = $serverInstance;
    }

    $logger->info("⏳ 等待设备启动... (每个设备延迟10秒，无主动心跳)");

    // 添加全局状态监控（每10分钟检查一次）
    $loop->addPeriodicTimer(600, function() use ($logger, $deviceManager) {
        $devices = $deviceManager->getAllDevices();
        $activeDevices = 0;
        
        foreach ($devices as $device) {
            if (isset($device['status']) && $device['status'] === 'connected') {
                $activeDevices++;
            }
        }
        
        $logger->info("💓 状态监控: {$activeDevices} 个设备在线", [
            'totalDevices' => count($devices),
            'activeDevices' => $activeDevices
        ]);
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
