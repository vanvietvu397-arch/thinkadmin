<?php

declare(strict_types=1);

namespace app\common\service;

use Psr\Log\LoggerInterface;

/**
 * 设备管理器服务 - 管理多个小智AI设备连接
 */
class DeviceManagerService
{
    private LoggerInterface $logger;
    private array $devices = [];
    private array $deviceSessions = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * 注册设备
     *
     * @param string $deviceId 设备ID
     * @param string $deviceName 设备名称
     * @param string $wssUrl WSS连接地址
     * @param array $metadata 设备元数据
     */
    public function registerDevice(string $deviceId, string $deviceName, string $wssUrl, array $metadata = []): void
    {
        $this->devices[$deviceId] = [
            'id' => $deviceId,
            'name' => $deviceName,
            'wss_url' => $wssUrl,
            'metadata' => $metadata,
            'connected' => false,
            'last_seen' => null,
            'session_id' => null
        ];

        $this->logger->info("设备已注册", [
            'deviceId' => $deviceId,
            'deviceName' => $deviceName,
            'wssUrl' => $wssUrl
        ]);
    }

    /**
     * 设备连接
     *
     * @param string $deviceId 设备ID
     * @param string $sessionId 会话ID
     */
    public function deviceConnected(string $deviceId, string $sessionId): void
    {
        if (isset($this->devices[$deviceId])) {
            $this->devices[$deviceId]['connected'] = true;
            $this->devices[$deviceId]['last_seen'] = date('Y-m-d H:i:s');
            $this->devices[$deviceId]['session_id'] = $sessionId;

            $this->deviceSessions[$sessionId] = $deviceId;

            $this->logger->info("设备已连接", [
                'deviceId' => $deviceId,
                'deviceName' => $this->devices[$deviceId]['name'],
                'sessionId' => $sessionId
            ]);
        }
    }

    /**
     * 设备断开连接
     *
     * @param string $sessionId 会话ID
     */
    public function deviceDisconnected(string $sessionId): void
    {
        if (isset($this->deviceSessions[$sessionId])) {
            $deviceId = $this->deviceSessions[$sessionId];

            if (isset($this->devices[$deviceId])) {
                $this->devices[$deviceId]['connected'] = false;
                $this->devices[$deviceId]['session_id'] = null;

                $this->logger->info("设备已断开连接", [
                    'deviceId' => $deviceId,
                    'deviceName' => $this->devices[$deviceId]['name'],
                    'sessionId' => $sessionId
                ]);
            }

            unset($this->deviceSessions[$sessionId]);
        }
    }

    /**
     * 根据会话ID获取设备信息
     *
     * @param string $sessionId 会话ID
     * @return array|null 设备信息
     */
    public function getDeviceBySession(string $sessionId): ?array
    {
        if (isset($this->deviceSessions[$sessionId])) {
            $deviceId = $this->deviceSessions[$sessionId];
            return $this->devices[$deviceId] ?? null;
        }
        return null;
    }

    /**
     * 根据设备ID获取设备信息
     *
     * @param string $deviceId 设备ID
     * @return array|null 设备信息
     */
    public function getDevice(string $deviceId): ?array
    {
        return $this->devices[$deviceId] ?? null;
    }

    /**
     * 获取所有设备列表
     *
     * @return array 设备列表
     */
    public function getAllDevices(): array
    {
        return $this->devices;
    }

    /**
     * 获取已连接的设备列表
     *
     * @return array 已连接的设备列表
     */
    public function getConnectedDevices(): array
    {
        return array_filter($this->devices, function ($device) {
            return $device['connected'];
        });
    }

    /**
     * 从WSS URL中提取设备标识
     *
     * @param string $wssUrl WSS连接地址
     * @return string 设备标识
     */
    public function extractDeviceIdFromUrl(string $wssUrl): string
    {
        // 从URL中解析token，然后从token中提取设备信息
        $parsedUrl = parse_url($wssUrl);
        $query = $parsedUrl['query'] ?? '';
        parse_str($query, $params);

        $token = $params['token'] ?? '';

        if ($token) {
            // 解析JWT token获取设备信息
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode($parts[1]), true);
                if ($payload && isset($payload['agentId'])) {
                    return 'device_' . $payload['agentId'];
                }
            }
        }

        // 如果无法从token解析，使用URL的hash作为设备ID
        return 'device_' . substr(md5($wssUrl), 0, 8);
    }

    /**
     * 根据WSS URL查找对应的配置设备ID
     *
     * @param string $wssUrl WSS连接地址
     * @param array $devices 设备配置数组
     * @return string|null 配置中的设备ID
     */
    public function findDeviceIdByUrl(string $wssUrl, array $devices): ?string
    {
        foreach ($devices as $deviceId => $deviceConfig) {
            if ($deviceConfig['wss_url'] === $wssUrl) {
                return $deviceId;
            }
        }
        return null;
    }

    /**
     * 从WSS URL中提取设备名称
     *
     * @param string $wssUrl WSS连接地址
     * @return string 设备名称
     */
    public function extractDeviceNameFromUrl(string $wssUrl): string
    {
        $parsedUrl = parse_url($wssUrl);
        $query = $parsedUrl['query'] ?? '';
        parse_str($query, $params);

        $token = $params['token'] ?? '';

        if ($token) {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode($parts[1]), true);
                if ($payload && isset($payload['agentId'])) {
                    return '小智AI设备-' . $payload['agentId'];
                }
            }
        }

        return '小智AI设备-' . substr(md5($wssUrl), 0, 8);
    }

    /**
     * 记录设备活动
     *
     * @param string $sessionId 会话ID
     * @param string $action 活动类型
     * @param array $data 活动数据
     */
    public function logDeviceActivity(string $sessionId, string $action, array $data = []): void
    {
        $device = $this->getDeviceBySession($sessionId);
        if ($device) {
            $this->devices[$device['id']]['last_seen'] = date('Y-m-d H:i:s');

            $this->logger->info("设备活动", [
                'deviceId' => $device['id'],
                'deviceName' => $device['name'],
                'action' => $action,
                'data' => $data
            ]);
        }
    }
}
