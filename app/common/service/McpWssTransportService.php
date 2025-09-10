<?php

declare(strict_types=1);

namespace app\common\service;

use Evenement\EventEmitterTrait;
use PhpMcp\Server\Contracts\LoggerAwareInterface;
use PhpMcp\Server\Contracts\LoopAwareInterface;
use PhpMcp\Server\Contracts\ServerTransportInterface;
use PhpMcp\Server\Exception\TransportException;
use PhpMcp\Schema\JsonRpc\Message;
use PhpMcp\Schema\JsonRpc\Error;
use PhpMcp\Schema\JsonRpc\Parser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface;
use Throwable;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;
use function React\Promise\reject;

/**
 * WSS传输层服务实现，支持WebSocket Secure协议连接到小智AI的MCP接入点
 */
class McpWssTransportService implements ServerTransportInterface, LoggerAwareInterface, LoopAwareInterface
{
    use EventEmitterTrait;

    protected LoggerInterface $logger;
    protected LoopInterface $loop;
    protected ?WebSocket $connection = null;
    protected bool $listening = false;
    protected bool $closing = false;
    protected string $wssUrl;
    protected ?ThroughStream $messageStream = null;
    protected ?DeviceManagerService $deviceManager = null;
    protected string $deviceId;
    protected string $deviceName;
    protected ?\React\EventLoop\TimerInterface $heartbeatTimer = null;
    protected ?\React\EventLoop\TimerInterface $reconnectTimer = null;
    protected int $reconnectAttempts = 0;
    protected int $maxReconnectAttempts = 5;
    protected int $reconnectInterval = 30; // 秒
    protected int $heartbeatInterval = 60; // 秒
    protected int $lastMessageTime = 0;
    protected bool $isReconnecting = false;

    public function __construct(string $wssUrl, ?DeviceManagerService $deviceManager = null, ?string $deviceId = null, ?string $deviceName = null)
    {
        $this->logger = new NullLogger();
        $this->loop = Loop::get();
        $this->wssUrl = $wssUrl;
        $this->deviceManager = $deviceManager;

        // 使用传入的设备信息或从URL中提取
        if ($deviceId && $deviceName) {
            $this->deviceId = $deviceId;
            $this->deviceName = $deviceName;
        } elseif ($this->deviceManager) {
            $this->deviceId = $this->deviceManager->extractDeviceIdFromUrl($wssUrl);
            $this->deviceName = $this->deviceManager->extractDeviceNameFromUrl($wssUrl);
        } else {
            $this->deviceId = 'device_' . substr(md5($wssUrl), 0, 8);
            $this->deviceName = '小智AI设备-' . substr(md5($wssUrl), 0, 8);
        }

        // 注册设备（如果设备管理器存在且设备未注册）
        if ($this->deviceManager && !$this->deviceManager->getDevice($this->deviceId)) {
            $this->deviceManager->registerDevice($this->deviceId, $this->deviceName, $wssUrl);
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * 获取设备ID
     */
    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    /**
     * 获取设备名称
     */
    public function getDeviceName(): string
    {
        return $this->deviceName;
    }

    /**
     * 设置重连配置
     */
    public function setReconnectConfig(int $maxAttempts = 5, int $interval = 30): void
    {
        $this->maxReconnectAttempts = $maxAttempts;
        $this->reconnectInterval = $interval;
    }

    /**
     * 设置心跳间隔
     */
    public function setHeartbeatInterval(int $interval = 60): void
    {
        $this->heartbeatInterval = $interval;
    }

    public function setLoop(LoopInterface $loop): void
    {
        $this->loop = $loop;
    }

    /**
     * 连接到WSS服务器
     */
    public function listen(): void
    {
        if ($this->listening) {
            throw new TransportException('WSS transport is already listening.');
        }
        if ($this->closing) {
            throw new TransportException('Cannot listen, transport is closing/closed.');
        }

        $this->logger->info("Connecting to WSS endpoint: {$this->wssUrl}");

        try {
            // 创建带有优化的SSL配置的连接器
            $connector = new Connector($this->loop, null, [
                'timeout' => 30, // 增加连接超时时间
                'tls' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                    'ciphers' => 'ECDHE+AESGCM:ECDHE+CHACHA20:DHE+AESGCM:DHE+CHACHA20:!aNULL:!MD5:!DSS',
                    'disable_compression' => true,
                    'peer_name' => 'api.xiaozhi.me'
                ],
                'dns' => [
                    'timeout' => 5
                ]
            ]);

            $this->logger->info("Attempting to connect to WSS endpoint...");

            $connector($this->wssUrl)
                ->then(function (WebSocket $conn) {
                    $this->connection = $conn;
                    $this->listening = true;
                    $this->closing = false;

                    $this->logger->info("Connected to WSS endpoint successfully");

                    // 创建消息流
                    $this->messageStream = new ThroughStream();

                    // 生成会话ID，包含设备信息
                    $sessionId = 'wss-session-' . $this->deviceId . '-' . substr(md5($this->wssUrl), 0, 8);

                    // 监听WebSocket消息
                    $conn->on('message', function ($msg) {
                        $this->lastMessageTime = time();
                        $this->handleIncomingMessage($msg->getPayload());
                    });

                    // 监听连接关闭
                    $conn->on('close', function ($code = null, $reason = null) {
                        $this->logger->info("WSS connection closed", ['code' => $code, 'reason' => $reason]);
                        $this->handleConnectionClose();
                    });

                    // 监听连接错误
                    $conn->on('error', function (Throwable $error) {
                        $this->logger->error("WSS connection error", ['error' => $error->getMessage()]);
                        $this->emit('error', [new TransportException("WSS connection error: {$error->getMessage()}", 0, $error)]);
                    });

                    // 记录设备连接
                    if ($this->deviceManager) {
                        $this->deviceManager->deviceConnected($this->deviceId, $sessionId);
                    }

                    // 启动心跳机制
                    $this->startHeartbeat();
                    
                    // 重置重连计数器
                    $this->reconnectAttempts = 0;
                    $this->isReconnecting = false;

                    // 触发连接事件
                    $this->emit('client_connected', [$sessionId]);
                    $this->emit('ready');
                })
                ->catch(function (Throwable $e) {
                    $this->logger->error("Failed to connect to WSS endpoint", ['error' => $e->getMessage()]);

                    // 启动自动重连
                    $this->scheduleReconnect();
                });

        } catch (Throwable $e) {
            $this->logger->error("Error setting up WSS connection", ['exception' => $e]);
            throw new TransportException("Error setting up WSS connection: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 处理接收到的消息
     */
    protected function handleIncomingMessage(string $payload): void
    {
        $this->logger->debug('Received WSS message', ['payload' => $payload]);

        try {
            $message = Parser::parse($payload);
            // 使用设备相关的会话ID
            $sessionId = 'wss-session-' . $this->deviceId . '-' . substr(md5($this->wssUrl), 0, 8);

            // 记录设备活动
            if ($this->deviceManager) {
                $this->deviceManager->logDeviceActivity($sessionId, 'message_received', [
                    'message_type' => $message->method ?? 'response'
                ]);
            }

            $this->emit('message', [$message, $sessionId]);
        } catch (Throwable $e) {
            $this->logger->error('Error parsing WSS message', ['exception' => $e]);
            $error = Error::forParseError('Invalid JSON-RPC message: ' . $e->getMessage());
            $sessionId = 'wss-session-' . $this->deviceId . '-' . substr(md5($this->wssUrl), 0, 8);
            $this->emit('message', [$error, $sessionId]);
        }
    }

    /**
     * 处理连接关闭
     */
    protected function handleConnectionClose(): void
    {
        $sessionId = 'wss-session-' . $this->deviceId . '-' . substr(md5($this->wssUrl), 0, 8);

        // 停止心跳
        $this->stopHeartbeat();

        // 记录设备断开连接
        if ($this->deviceManager) {
            $this->deviceManager->deviceDisconnected($sessionId);
        }

        $this->listening = false;
        $this->connection = null;
        if ($this->messageStream) {
            $this->messageStream->close();
            $this->messageStream = null;
        }
        
        $this->emit('client_disconnected', [$sessionId, 'WSS connection closed']);
        
        // 如果不是主动关闭，则启动重连
        if (!$this->closing) {
            $this->scheduleReconnect();
        }
    }

    /**
     * 发送消息到WSS服务器
     */
    public function sendMessage(Message $message, string $sessionId, array $context = []): PromiseInterface
    {
        if (!$this->connection || !$this->listening) {
            return reject(new TransportException('WSS connection not available'));
        }

        $deferred = new Deferred();

        try {
            $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->logger->debug('Sending WSS message', ['message' => $json]);

            $this->connection->send($json);
            $deferred->resolve(null);
        } catch (Throwable $e) {
            $this->logger->error('Error sending WSS message', ['exception' => $e]);
            $deferred->reject(new TransportException("Error sending WSS message: {$e->getMessage()}", 0, $e));
        }

        return $deferred->promise();
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        if ($this->closing) {
            return;
        }

        $this->closing = true;
        $this->listening = false;
        $this->logger->info('Closing WSS transport...');

        // 停止心跳和重连
        $this->stopHeartbeat();
        $this->cancelReconnect();

        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }

        if ($this->messageStream) {
            $this->messageStream->close();
            $this->messageStream = null;
        }

        $this->emit('close', ['WSS transport closed.']);
        $this->removeAllListeners();
    }

    /**
     * 启动心跳机制
     */
    protected function startHeartbeat(): void
    {
        $this->stopHeartbeat(); // 先停止现有的心跳

        $this->heartbeatTimer = $this->loop->addPeriodicTimer($this->heartbeatInterval, function () {
            $this->sendHeartbeat();
        });

        $this->logger->info("心跳机制已启动", [
            'deviceId' => $this->deviceId,
            'interval' => $this->heartbeatInterval
        ]);
    }

    /**
     * 停止心跳机制
     */
    protected function stopHeartbeat(): void
    {
        if ($this->heartbeatTimer) {
            $this->loop->cancelTimer($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }
    }

    /**
     * 发送心跳消息
     */
    protected function sendHeartbeat(): void
    {
        if (!$this->connection || !$this->listening) {
            return;
        }

        try {
            // 发送ping消息保持连接活跃
            $pingMessage = json_encode(['type' => 'ping', 'timestamp' => time()]);
            $this->connection->send($pingMessage);
            
            $this->logger->debug("发送心跳消息", [
                'deviceId' => $this->deviceId,
                'timestamp' => time()
            ]);
        } catch (Throwable $e) {
            $this->logger->error("发送心跳失败", [
                'deviceId' => $this->deviceId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 安排重连
     */
    protected function scheduleReconnect(): void
    {
        if ($this->isReconnecting || $this->closing) {
            return;
        }

        if ($this->reconnectAttempts >= $this->maxReconnectAttempts) {
            $this->logger->error("达到最大重连次数，停止重连", [
                'deviceId' => $this->deviceId,
                'attempts' => $this->reconnectAttempts
            ]);
            return;
        }

        $this->isReconnecting = true;
        $this->reconnectAttempts++;

        $this->logger->info("安排重连", [
            'deviceId' => $this->deviceId,
            'attempt' => $this->reconnectAttempts,
            'maxAttempts' => $this->maxReconnectAttempts,
            'delay' => $this->reconnectInterval
        ]);

        $this->reconnectTimer = $this->loop->addTimer($this->reconnectInterval, function () {
            $this->attemptReconnect();
        });
    }

    /**
     * 尝试重连
     */
    protected function attemptReconnect(): void
    {
        if ($this->closing) {
            return;
        }

        $this->logger->info("尝试重连", [
            'deviceId' => $this->deviceId,
            'attempt' => $this->reconnectAttempts
        ]);

        try {
            $this->isReconnecting = false;
            $this->listen(); // 重新启动连接
        } catch (Throwable $e) {
            $this->logger->error("重连失败", [
                'deviceId' => $this->deviceId,
                'error' => $e->getMessage()
            ]);
            $this->isReconnecting = false;
            $this->scheduleReconnect(); // 继续尝试重连
        }
    }

    /**
     * 取消重连
     */
    protected function cancelReconnect(): void
    {
        if ($this->reconnectTimer) {
            $this->loop->cancelTimer($this->reconnectTimer);
            $this->reconnectTimer = null;
        }
        $this->isReconnecting = false;
    }
}
