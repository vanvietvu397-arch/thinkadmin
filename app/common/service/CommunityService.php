<?php

declare(strict_types=1);

namespace app\common\service;

use app\admin\controller\DeviceManager;
use Psr\Log\LoggerInterface;
use think\facade\Log;

/**
 * 社区信息服务类
 */
class CommunityService
{
    protected LoggerInterface $logger;
    protected ?DeviceManager $deviceManager;

    public function __construct(LoggerInterface $logger, ?DeviceManager $deviceManager = null)
    {
        $this->logger = $logger;
        $this->deviceManager = $deviceManager;
    }

    /**
     * 查询社区信息
     */
    public function queryCommunityInfo(array $params = []): array
    {
        Log::info('queryCommunityInfo:', $params);
        $this->logger->info('queryCommunityInfo:', $params);
        $sessionId = $params['sessionId'] ?? 'unknown';
        
        // 获取设备信息
        $deviceInfo = null;
        if ($this->deviceManager) {
            $deviceInfo = $this->deviceManager->getDeviceBySession($sessionId);
        }

        $deviceName = $deviceInfo['name'] ?? '未知设备';
        $deviceId = $deviceInfo['id'] ?? 'unknown';

        $this->logger->info("查询社区信息", [
            'deviceId' => $deviceId,
            'deviceName' => $deviceName,
            'sessionId' => $sessionId
        ]);

        // 模拟社区数据
        $communityData = [
            'totalResidents' => 1250,
            'totalHouseholds' => 420,
            'communityName' => '宏山社区',
            'area' => '2.5平方公里',
            'establishedYear' => 2010,
            'facilities' => [
                '儿童活动空间',
                '老年活动中心',
                '社区图书馆',
                '健身广场',
                '社区医院'
            ],
            'recentActivities' => [
                '社区文化节',
                '儿童绘画比赛',
                '老年人健康讲座',
                '垃圾分类宣传活动'
            ]
        ];

        return [
            'success' => true,
            'data' => $communityData,
            'deviceInfo' => [
                'deviceId' => $deviceId,
                'deviceName' => $deviceName,
                'queryTime' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * 查询设备信息
     */
    public function queryDeviceInfo(array $params = []): array
    {
        Log::info('queryDeviceInfo:', $params);
        $this->logger->info('queryDeviceInfo:', $params);
        $sessionId = $params['sessionId'] ?? 'unknown';
        
        $deviceInfo = null;
        if ($this->deviceManager) {
            $deviceInfo = $this->deviceManager->getDeviceBySession($sessionId);
        }

        if (!$deviceInfo) {
            return [
                'success' => false,
                'error' => '设备信息未找到',
                'sessionId' => $sessionId
            ];
        }

        return [
            'success' => true,
            'data' => $deviceInfo
        ];
    }

    /**
     * 查询所有设备状态
     */
    public function queryAllDevices(array $params = []): array
    {
        Log::info('queryAllDevices:', $params);
        $this->logger->info('queryAllDevices:', $params);
        if (!$this->deviceManager) {
            return [
                'success' => false,
                'error' => '设备管理器未初始化'
            ];
        }

        $devices = $this->deviceManager->getAllDevices();
        
        return [
            'success' => true,
            'data' => [
                'totalDevices' => count($devices),
                'devices' => $devices
            ]
        ];
    }
}
