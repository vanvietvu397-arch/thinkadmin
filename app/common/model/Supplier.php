<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 供应商模型
 * Class Supplier
 * @package app\common\model
 */
class Supplier extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_supplier';
    
    // 指定主键
    protected $pk = 'shop_supplier_id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 日志类型
    protected $oplogType = '供应商管理';
    
    // 日志名称
    protected $oplogName = '供应商';
    
    // 字段类型转换
    protected $type = [
        'shop_supplier_id' => 'integer',
        'logo_id' => 'integer',
        'business_id' => 'integer',
        'open_service' => 'integer',
        'total_money' => 'decimal',
        'money' => 'decimal',
        'freeze_money' => 'decimal',
        'cash_money' => 'decimal',
        'deposit_money' => 'decimal',
        'is_delete' => 'integer',
        'user_id' => 'integer',
        'category_id' => 'integer',
        'score' => 'string',
        'express_score' => 'decimal',
        'server_score' => 'decimal',
        'describe_score' => 'decimal',
        'is_full' => 'integer',
        'fav_count' => 'integer',
        'status' => 'integer',
        'store_type' => 'integer',
        'total_gift' => 'integer',
        'gift_money' => 'integer',
        'product_sales' => 'integer',
        'is_recycle' => 'integer',
        'commission_rate' => 'decimal',
        'app_id' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 常量定义
    const STATUS_NORMAL = 0;        // 正常
    const STATUS_REFUNDING = 10;    // 退押金中
    const STATUS_NO_DEPOSIT = 20;   // 未交保证金
    
    const STORE_TYPE_NORMAL = 10;   // 普通店铺
    const STORE_TYPE_SELF = 20;     // 自营店铺
    
    /**
     * 关联应用
     */
    public function app()
    {
        return $this->belongsTo(App::class, 'app_id', 'app_id');
    }
    
    /**
     * 关联供应商用户
     */
    public function supplierUsers()
    {
        return $this->hasMany(SupplierUser::class, 'shop_supplier_id', 'shop_supplier_id');
    }
    
    /**
     * 关联设备
     */
    public function devices()
    {
        return $this->hasMany(Devices::class, 'shop_supplier_id', 'shop_supplier_id');
    }
    
    /**
     * 获取店铺状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [
            self::STATUS_NORMAL => '正常',
            self::STATUS_REFUNDING => '退押金中',
            self::STATUS_NO_DEPOSIT => '未交保证金',
        ];
        return $statusMap[$data['status']] ?? '未知';
    }
    
    /**
     * 获取店铺类型文本
     */
    public function getStoreTypeTextAttr($value, $data)
    {
        $typeMap = [
            self::STORE_TYPE_NORMAL => '普通店铺',
            self::STORE_TYPE_SELF => '自营店铺',
        ];
        return $typeMap[$data['store_type']] ?? '未知';
    }
    
    /**
     * 获取资料完整性文本
     */
    public function getIsFullTextAttr($value, $data)
    {
        return $data['is_full'] ? '齐全' : '不齐全';
    }
    
    /**
     * 获取在线客服状态文本
     */
    public function getOpenServiceTextAttr($value, $data)
    {
        return $data['open_service'] ? '开启' : '关闭';
    }
    
    /**
     * 获取删除状态文本
     */
    public function getIsDeleteTextAttr($value, $data)
    {
        return $data['is_delete'] ? '已删除' : '正常';
    }
    
    /**
     * 获取供应商列表
     */
    public static function getSupplierList($appId = null)
    {
        $query = self::where('is_delete', 0);
        if ($appId) {
            $query->where('app_id', $appId);
        }
        return $query->with(['app'])->select();
    }
    
    /**
     * 获取启用的供应商列表
     */
    public static function getEnabledList($appId = null)
    {
        $query = self::where('is_delete', 0)
                    ->where('status', self::STATUS_NORMAL);
        if ($appId) {
            $query->where('app_id', $appId);
        }
        return $query->field('shop_supplier_id,name')->select();
    }
    
    /**
     * 获取供应商选项
     */
    public static function getOptions($appId = null)
    {
        $list = self::getEnabledList($appId);
        $options = [];
        foreach ($list as $item) {
            $options[] = [
                'value' => $item['shop_supplier_id'],
                'title' => $item['name'],
            ];
        }
        return $options;
    }
    
    /**
     * 根据名称查找供应商
     */
    public static function findByName($name, $appId = null)
    {
        $query = self::where('name', $name)->where('is_delete', 0);
        if ($appId) {
            $query->where('app_id', $appId);
        }
        return $query->find();
    }
    
    /**
     * 更新供应商状态
     */
    public function updateStatus($status)
    {
        return $this->save(['status' => $status]);
    }
    
    /**
     * 更新资金信息
     */
    public function updateMoney($data)
    {
        $allowedFields = ['total_money', 'money', 'freeze_money', 'cash_money', 'deposit_money'];
        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        return $this->save($updateData);
    }
}
