<?php

namespace app\common\model;

use think\model\Pivot;

/**
 * 设备指令中间表模型
 * Class DeviceInstructMiddle
 * @package app\common\model
 */
class DeviceInstructMiddle extends Pivot
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_device_instruct_middle';
    
    // 指定主键
    protected $pk = 'middle_id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 字段类型转换
    protected $type = [
        'middle_id' => 'integer',
        'shop_supplier_id' => 'integer',
        'instruct_id' => 'integer',
        'device_id' => 'integer',
        'is_delete' => 'integer',
        'app_id' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(DeviceModel::class, 'device_id', 'id');
    }
    
    /**
     * 关联指令
     */
    public function instruct()
    {
        return $this->belongsTo(DeviceInstructModel::class, 'instruct_id', 'id');
    }
    
    /**
     * 为设备添加指令
     */
    public static function addDeviceInstruct($deviceId, $instructId, $shopSupplierId, $appId)
    {
        // 检查是否已存在
        $exists = self::where('device_id', $deviceId)
                     ->where('instruct_id', $instructId)
                     ->where('is_delete', 0)
                     ->find();
        
        if ($exists) {
            return false; // 已存在
        }
        
        return self::create([
            'device_id' => $deviceId,
            'instruct_id' => $instructId,
            'shop_supplier_id' => $shopSupplierId,
            'app_id' => $appId,
        ]);
    }
    
    /**
     * 移除设备的指令
     */
    public static function removeDeviceInstruct($deviceId, $instructId)
    {
        return self::where('device_id', $deviceId)
                   ->where('instruct_id', $instructId)
                   ->where('is_delete', 0)
                   ->update(['is_delete' => 1]);
    }
    
    /**
     * 获取设备的所有指令
     */
    public static function getDeviceInstructs($deviceId)
    {
        return self::with(['instruct'])
                   ->where('device_id', $deviceId)
                   ->where('is_delete', 0)
                   ->select();
    }
    
    /**
     * 获取指令关联的所有设备
     */
    public static function getInstructDevices($instructId)
    {
        return self::with(['device'])
                   ->where('instruct_id', $instructId)
                   ->where('is_delete', 0)
                   ->select();
    }
    
    /**
     * 批量设置设备的指令
     */
    public static function setDeviceInstructs($deviceId, $instructIds, $shopSupplierId, $appId)
    {
        // 先删除现有的关联
        self::where('device_id', $deviceId)->update(['is_delete' => 1]);
        
        // 添加新的关联
        $data = [];
        foreach ($instructIds as $instructId) {
            $data[] = [
                'device_id' => $deviceId,
                'instruct_id' => $instructId,
                'shop_supplier_id' => $shopSupplierId,
                'app_id' => $appId,
                'create_time' => time(),
                'update_time' => time(),
            ];
        }
        
        if (!empty($data)) {
            return self::insertAll($data);
        }
        
        return true;
    }
}
