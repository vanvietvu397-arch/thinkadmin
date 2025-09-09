<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 应用模型
 * Class AppModel
 * @package app\common\model
 */
class AppModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_app';
    
    // 指定主键
    protected $pk = 'app_id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 日志类型
    protected $oplogType = '应用管理';
    
    // 日志名称
    protected $oplogName = '应用';
    
    // 字段类型转换
    protected $type = [
        'app_id' => 'integer',
        'logo' => 'integer',
        'passport_open' => 'integer',
        'passport_type' => 'integer',
        'is_recycle' => 'integer',
        'expire_time' => 'integer',
        'weixin_service' => 'integer',
        'wx_cash_type' => 'integer',
        'is_delete' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 常量定义
    const PASSPORT_TYPE_WECHAT = 10; // 微信开放平台
    const PASSPORT_TYPE_PHONE = 20;  // 手机号
    const PASSPORT_TYPE_ACCOUNT = 30; // 账号密码
    
    const WX_CASH_TYPE_TRANSFER = 1; // 商家转账到零钱
    const WX_CASH_TYPE_BUSINESS = 2; // 商家发起转账
    
    /**
     * 关联商家用户
     */
    public function shopUsers()
    {
        return $this->hasMany(ShopUserModel::class, 'app_id', 'app_id');
    }
    
    /**
     * 关联供应商
     */
    public function suppliers()
    {
        return $this->hasMany(SupplierModel::class, 'app_id', 'app_id');
    }
    
    /**
     * 获取启用状态文本
     */
    public function getPassportOpenTextAttr($value, $data)
    {
        return $data['passport_open'] ? '开放' : '不开放';
    }
    
    /**
     * 获取通行证类型文本
     */
    public function getPassportTypeTextAttr($value, $data)
    {
        $typeMap = [
            self::PASSPORT_TYPE_WECHAT => '微信开放平台',
            self::PASSPORT_TYPE_PHONE => '手机号',
            self::PASSPORT_TYPE_ACCOUNT => '账号密码',
        ];
        return $typeMap[$data['passport_type']] ?? '未知';
    }
    
    /**
     * 获取微信提现方式文本
     */
    public function getWxCashTypeTextAttr($value, $data)
    {
        $typeMap = [
            self::WX_CASH_TYPE_TRANSFER => '商家转账到零钱',
            self::WX_CASH_TYPE_BUSINESS => '商家发起转账',
        ];
        return $typeMap[$data['wx_cash_type']] ?? '未知';
    }
    
    /**
     * 获取应用列表
     */
    public static function getEnabledList()
    {
        return self::where('is_delete', 0)
                   ->where('is_recycle', 0)
                   ->field('app_id,app_name')
                   ->select();
    }
    
    /**
     * 获取应用选项
     */
    public static function getOptions()
    {
        $list = self::getEnabledList();
        $options = [];
        foreach ($list as $item) {
            $options[] = [
                'value' => $item['app_id'],
                'title' => $item['app_name'],
            ];
        }
        return $options;
    }
}
