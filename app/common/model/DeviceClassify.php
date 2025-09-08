<?php

namespace app\common\model;

use think\admin\Model;
/**
 * 设备分类模型
 * Class DeviceClassify
 * @package app\common\model
 */
class DeviceClassify extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_device_classify';
    
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
     * 关联设备（一对多）
     */
    public function devices()
    {
        return $this->hasMany(Devices::class, 'classify_id', 'id');
    }
    
    /**
     * 获取分类下的设备数量
     */
    public function getDeviceCountAttr($value, $data)
    {
        return $this->devices()->where('is_delete', 0)->count();
    }
    
    /**
     * 获取启用的分类列表
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
     * 获取分类选项（用于下拉选择）
     */
    public static function getOptions($shopSupplierId = null)
    {
        $list = self::getEnabledList($shopSupplierId);
        $options = [];
        
        foreach ($list as $item) {
            $options[] = [
                'value' => $item['id'],
                'label' => $item['classify_name']
            ];
        }
        
        return $options;
    }
}
