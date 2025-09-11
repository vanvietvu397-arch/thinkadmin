<?php

// +----------------------------------------------------------------------
// | WeMall Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// | 会员免费 ( https://thinkadmin.top/vip-introduce )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-wemall
// | github 代码仓库：https://github.com/zoujingli/think-plugs-wemall
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace app\admin\controller\zhzp\base;


use think\admin\Controller;
use think\Model;
use app\common\model\DeviceModel;
use app\common\model\DeviceOnlineRecordModel;
use app\common\model\VoiceTemplateModel;
use app\common\model\VoiceKeywordModel;

/**
 * 商城数据统计
 * @class Report
 * @package plugin\wemall\controller\base
 */
class Report extends Controller
{
    /**
     * 显示数据统计
     * @auth true
     * @menu true
     * @throws \think\db\exception\DbException
     */
    public function index()
    {
        // 获取统计数据
        $this->getStatisticsData();
        
        // 获取15天设备在线数据
        $this->getDeviceOnlineData();

        $this->fetch();
    }

    /**
     * 获取统计数据
     */
    private function getStatisticsData()
    {
        // 设备总量
        $this->deviceTotal = DeviceModel::where('is_delete', 0)->count();
        
        // 当前在线设备数量
        $this->onlineTotal = DeviceOnlineRecordModel::getOnlineDeviceCount();
        
        // 模板总量
        $this->templateTotal = VoiceTemplateModel::count();
        
        // 语音匹配总量
        $this->keywordTotal = VoiceKeywordModel::count();
    }

    /**
     * 获取设备在线数据
     */
    private function getDeviceOnlineData()
    {
        $this->days = [];
        
        // 组装15天的统计数据
        for ($i = 15; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i}days"));
            $startTime = strtotime($date . ' 00:00:00');
            $endTime = strtotime($date . ' 23:59:59');
            
            // 查询当天在线设备数量
            $onlineCount = $this->getDailyOnlineDeviceCount($startTime, $endTime);
            
            $this->days[] = [
                '当天日期' => date('m-d', strtotime("-{$i}days")),
                '在线设备数' => $onlineCount,
            ];
        }
    }

    /**
     * 获取指定日期范围内的在线设备数量
     * @param int $startTime 开始时间戳
     * @param int $endTime 结束时间戳
     * @return int
     */
    private function getDailyOnlineDeviceCount($startTime, $endTime)
    {
        try {
            // 查询在指定日期范围内有在线记录的设备数量
            $onlineDevices = DeviceOnlineRecordModel::where('is_delete', 0)
                ->where(function($query) use ($startTime, $endTime) {
                    $query->where(function($q) use ($startTime, $endTime) {
                        // 在线记录的开始时间在指定日期范围内
                        $q->where('start_time', '>=', $startTime)
                          ->where('start_time', '<=', $endTime);
                    })->whereOr(function($q) use ($startTime, $endTime) {
                        // 或者在线记录跨越指定日期范围
                        $q->where('start_time', '<', $startTime)
                          ->where(function($subQ) use ($startTime, $endTime) {
                              $subQ->whereNull('end_time')
                                   ->whereOr('end_time', '>', $startTime);
                          });
                    });
                })
                ->group('device_id')
                ->count();
            
            return $onlineDevices;
            
        } catch (\Exception $e) {
            // 如果查询失败，返回0
            return 0;
        }
    }
}