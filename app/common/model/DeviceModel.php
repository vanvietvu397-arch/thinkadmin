<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 设备模型
 * Class Device
 * @package app\common\model
 */
class DeviceModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_device';
    
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
        'group_id' => 'integer',
        'classify_id' => 'integer',
        'status' => 'integer',
        'app_id' => 'integer',
        'spec' => 'integer',
        'enable_xiaozhi' => 'integer',
        'is_delete' => 'integer',
        'xiaozhi_last_connect_time' => 'integer',
        'xiaozhi_last_disconnect_time' => 'integer',
        'xiaozhi_last_error_time' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 小智AI连接状态枚举
    const XIAOZHI_STATUS_CONNECTED = 'connected';
    const XIAOZHI_STATUS_DISCONNECTED = 'disconnected';
    const XIAOZHI_STATUS_ERROR = 'error';
    
    // 设备规格枚举
    const SPEC_HORIZONTAL = 1; // 横屏
    const SPEC_VERTICAL = 2;   // 竖屏
    
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
     * 关联设备分类
     */
    public function classify()
    {
        return $this->belongsTo(DeviceClassifyModel::class, 'classify_id', 'id');
    }
    
    /**
     * 关联设备分组
     */
    public function group()
    {
        return $this->belongsTo(DeviceGroupModel::class, 'group_id', 'id');
    }
    
    /**
     * 关联设备指令（多对多）
     */
    public function instructs()
    {
        return $this->belongsToMany(DeviceInstructModel::class, DeviceInstructMiddle::class, 'instruct_id', 'device_id');
    }
    
    /**
     * 关联设备日志
     */
    public function logs()
    {
        return $this->hasMany(DeviceLogModel::class, 'device_id', 'id');
    }
    
    /**
     * 获取小智AI连接状态文本
     */
    public function getXiaozhiStatusTextAttr($value, $data)
    {
        $statusMap = [
            self::XIAOZHI_STATUS_CONNECTED => '已连接',
            self::XIAOZHI_STATUS_DISCONNECTED => '未连接',
            self::XIAOZHI_STATUS_ERROR => '连接错误',
        ];
        return $statusMap[$data['xiaozhi_status']] ?? '未知';
    }
    
    /**
     * 获取设备规格文本
     */
    public function getSpecTextAttr($value, $data)
    {
        $specMap = [
            self::SPEC_HORIZONTAL => '横屏',
            self::SPEC_VERTICAL => '竖屏',
        ];
        return $specMap[$data['spec']] ?? '未知';
    }
    
    /**
     * 获取在线设备数量
     */
    public static function getOnlineCount($shopSupplierId = null)
    {
        $query = self::where('xiaozhi_status', self::XIAOZHI_STATUS_CONNECTED)
                    ->where('is_delete', 0);
        
        if ($shopSupplierId) {
            $query->where('shop_supplier_id', $shopSupplierId);
        }
        
        return $query->count();
    }
    
    /**
     * 获取设备总数
     */
    public static function getTotalCount($shopSupplierId = null)
    {
        $query = self::where('is_delete', 0);
        
        if ($shopSupplierId) {
            $query->where('shop_supplier_id', $shopSupplierId);
        }
        
        return $query->count();
    }
}
