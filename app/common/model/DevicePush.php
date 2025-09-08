<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 设备推送模型
 * Class DevicePush
 * @package app\common\model
 */
class DevicePush extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_device_push';
    
    // 指定主键
    protected $pk = 'id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'shop_supplier_id' => 'integer',
        'push_status' => 'integer',
        'push_type' => 'integer',
        'is_delete' => 'integer',
        'push_start_time' => 'integer',
        'push_end_time' => 'integer',
        'app_id' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 推送类型常量
    const PUSH_TYPE_DEVICE = 1; // 推送设备
    const PUSH_TYPE_GROUP = 2;  // 推送分组
    
    /**
     * 获取推送类型文本
     */
    public function getPushTypeTextAttr($value, $data)
    {
        $typeMap = [
            self::PUSH_TYPE_DEVICE => '推送设备',
            self::PUSH_TYPE_GROUP => '推送分组',
        ];
        return $typeMap[$data['push_type']] ?? '未知';
    }
    
    /**
     * 获取推送状态文本
     */
    public function getPushStatusTextAttr($value, $data)
    {
        return $data['push_status'] ? '启用' : '禁用';
    }
    
    /**
     * 获取推送设备ID数组
     */
    public function getPushDeviceArrayAttr($value, $data)
    {
        if (empty($data['push_device'])) {
            return [];
        }
        
        $devices = json_decode($data['push_device'], true);
        return is_array($devices) ? $devices : [];
    }
    
    /**
     * 设置推送设备ID数组
     */
    public function setPushDeviceArrayAttr($value)
    {
        $this->data['push_device'] = json_encode($value);
    }
    
    /**
     * 获取推送状态（是否在推送时间内）
     */
    public function getIsActiveAttr($value, $data)
    {
        $now = time();
        return $data['push_status'] == 1 
            && $data['push_start_time'] <= $now 
            && $data['push_end_time'] >= $now;
    }
    
    /**
     * 获取启用的推送列表
     */
    public static function getActiveList($shopSupplierId = null)
    {
        $query = self::where('push_status', 1)
                    ->where('is_delete', 0)
                    ->where('push_start_time', '<=', time())
                    ->where('push_end_time', '>=', time());
        
        if ($shopSupplierId) {
            $query->where('shop_supplier_id', $shopSupplierId);
        }
        
        return $query->order('create_time', 'desc')->select();
    }
    
    /**
     * 获取推送列表
     */
    public static function getList($shopSupplierId = null, $status = null)
    {
        $query = self::where('is_delete', 0);
        
        if ($shopSupplierId) {
            $query->where('shop_supplier_id', $shopSupplierId);
        }
        
        if ($status !== null) {
            $query->where('push_status', $status);
        }
        
        return $query->order('create_time', 'desc')->select();
    }
    
    /**
     * 创建推送任务
     */
    public static function createPush($data)
    {
        // 处理推送设备数据
        if (isset($data['push_device']) && is_array($data['push_device'])) {
            $data['push_device'] = json_encode($data['push_device']);
        }
        
        return self::create($data);
    }
    
    /**
     * 更新推送任务
     */
    public function updatePush($data)
    {
        // 处理推送设备数据
        if (isset($data['push_device']) && is_array($data['push_device'])) {
            $data['push_device'] = json_encode($data['push_device']);
        }
        
        return $this->save($data);
    }
    
    /**
     * 获取推送统计信息
     */
    public static function getStats($shopSupplierId = null)
    {
        $query = self::where('is_delete', 0);
        
        if ($shopSupplierId) {
            $query->where('shop_supplier_id', $shopSupplierId);
        }
        
        $total = $query->count();
        $active = $query->where('push_status', 1)->count();
        $expired = $query->where('push_end_time', '<', time())->count();
        
        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'inactive' => $total - $active,
        ];
    }
}
