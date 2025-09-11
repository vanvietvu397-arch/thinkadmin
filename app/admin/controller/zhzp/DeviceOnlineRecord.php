<?php

// +----------------------------------------------------------------------
// | Admin Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-admin
// | github 代码仓库：https://github.com/zoujingli/think-plugs-admin
// +----------------------------------------------------------------------

namespace app\admin\controller\zhzp;

use think\admin\Controller;
use think\admin\helper\QueryHelper;
use app\common\model\DeviceOnlineRecordModel;
use app\common\model\DeviceModel;

/**
 * 设备在线记录管理
 * @class DeviceOnlineRecord
 * @package app\admin\controller\zhzp
 */
class DeviceOnlineRecord extends Controller
{
    /**
     * 设备在线记录管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        DeviceOnlineRecordModel::mQuery()->layTable(function () {
            $this->title = '设备在线记录';
            $this->devices = DeviceModel::where('is_delete', 0)->column('device_name', 'id');
        }, function (QueryHelper $query) {
            // 加载对应数据列表
            if ($this->type === 'index') {
                $query->where(['is_delete' => 0]);
            } else {
                $query->where(['is_delete' => 1]);
            }
            
            // 搜索条件
            $query->equal('device_id,status');
            $query->like('ip_address');
            $query->dateBetween('start_time,create_time');
            
            // 关联查询
            $query->with(['device']);
     
        });
    }

    /**
     * 删除在线记录
     * @auth true
     * @menu false
     */
    public function remove()
    {
        $ids = $this->request->post('id');
        if (empty($ids)) {
            $this->error('请选择要删除的记录');
        }
        
        // 处理ID数组
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        
        // 执行软删除，将 is_delete 设置为 1
        $result = DeviceOnlineRecordModel::whereIn('id', $ids)->update(['is_delete' => 1]);
        
        if ($result) {
            $this->success('记录删除成功');
        } else {
            $this->error('记录删除失败');
        }
    }

    /**
     * 批量删除在线记录
     * @auth true
     * @menu false
     */
    public function batchRemove()
    {
        $ids = $this->request->post('id');
        if (empty($ids)) {
            $this->error('请选择要删除的记录');
        }
        
        // 处理ID数组
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        
        // 执行批量软删除
        $result = DeviceOnlineRecordModel::batchSoftDelete($ids);
        
        if ($result) {
            $this->success('批量删除成功');
        } else {
            $this->error('批量删除失败');
        }
    }

    /**
     * 清空所有在线记录
     * @auth true
     * @menu false
     */
    public function clearAll()
    {
        if ($this->request->isPost()) {
            // 软删除所有记录
            $result = DeviceOnlineRecordModel::where('is_delete', 0)->update(['is_delete' => 1]);
            
            if ($result) {
                $this->success('清空成功，共删除 ' . $result . ' 条记录');
            } else {
                $this->error('清空失败');
            }
        } else {
            $this->error('请求方式错误');
        }
    }

    /**
     * 获取设备在线统计
     * @auth true
     * @menu false
     */
    public function statistics()
    {
        // 获取统计数据
        $totalRecords = DeviceOnlineRecordModel::where('is_delete', 0)->count();
        $onlineDevices = DeviceOnlineRecordModel::getOnlineDeviceCount();
        $totalDevices = DeviceModel::where('is_delete', 0)->count();
        
        // 获取最近7天的在线记录统计
        $weekStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i}days"));
            $startTime = strtotime($date . ' 00:00:00');
            $endTime = strtotime($date . ' 23:59:59');
            
            $count = DeviceOnlineRecordModel::where('is_delete', 0)
                ->where('start_time', '>=', $startTime)
                ->where('start_time', '<=', $endTime)
                ->group('device_id')
                ->count();
            
            $weekStats[] = [
                'date' => $date,
                'count' => $count
            ];
        }
        $arr = [
            'total_records' => $totalRecords,
            'online_devices' => $onlineDevices,
            'total_devices' => $totalDevices,
            'week_stats' => $weekStats
        ];
        $this->arr = $arr;
        $this->title = '设备在线统计';
        $this->fetch('statistics');
    }   
}
