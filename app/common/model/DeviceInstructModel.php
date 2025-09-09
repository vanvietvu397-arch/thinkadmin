<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 设备指令模型
 * Class DeviceInstructModel
 * @package app\common\model
 */
class DeviceInstructModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_device_instruct';
    
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
        'status' => 'integer',
        'app_id' => 'integer',
        'is_delete' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    /**
     * 关联应用
     */
    public function app()
    {
        return $this->belongsTo(AppModel::class, 'app_id', 'app_id');
    }
    
    /**
     * 关联供应商
     */
    public function supplier()
    {
        return $this->belongsTo(SupplierModel::class, 'shop_supplier_id', 'shop_supplier_id');
    }
    
    /**
     * 关联设备（多对多）
     */
    public function devices()
    {
        return $this->belongsToMany(DeviceModel::class, DeviceInstructMiddle::class, 'device_id', 'instruct_id');
    }
    
    /**
     * 关联设备指令中间表
     */
    public function middle()
    {
        return $this->hasMany(DeviceInstructMiddle::class, 'instruct_id', 'id');
    }
    
    /**
     * 关联设备日志
     */
    public function logs()
    {
        return $this->hasMany(DeviceLogModel::class, 'instruct_id', 'id');
    }
    
    /**
     * 获取指令关联的设备数量
     */
    public function getDeviceCountAttr($value, $data)
    {
        return $this->devices()->where('is_delete', 0)->count();
    }
    
    /**
     * 获取启用的指令列表
     */
    public static function getEnabledList($shopSupplierId = null)
    {
        $query = self::where('status', 1)->where('is_delete', 0);
        
        if ($shopSupplierId) {
            $query->where('shop_supplier_id', $shopSupplierId);
        }
        
        return $query->order('id', 'desc')->select();
    }
    
    /**
     * 获取指令选项（用于下拉选择）
     */
    public static function getOptions($shopSupplierId = null)
    {
        $list = self::getEnabledList($shopSupplierId);
        $options = [];
        
        foreach ($list as $item) {
            $options[] = [
                'value' => $item['id'],
                'label' => $item['instruct_name'] . ' (' . $item['instruct_code'] . ')'
            ];
        }
        
        return $options;
    }
    
    /**
     * 根据指令编码查找指令
     */
    public static function findByCode($code, $shopSupplierId = null)
    {
        $query = self::where('instruct_code', $code)
                    ->where('status', 1)
                    ->where('is_delete', 0);
        
        if ($shopSupplierId) {
            $query->where('shop_supplier_id', $shopSupplierId);
        }
        
        return $query->find();
    }
}
