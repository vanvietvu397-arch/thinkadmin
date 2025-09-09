<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 商家用户模型
 * Class ShopUserModel
 * @package app\common\model
 */
class ShopUserModel extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_shop_user';
    
    // 指定主键
    protected $pk = 'shop_user_id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 日志类型
    protected $oplogType = '商家用户管理';
    
    // 日志名称
    protected $oplogName = '商家用户';
    
    // 字段类型转换
    protected $type = [
        'shop_user_id' => 'integer',
        'is_super' => 'integer',
        'is_delete' => 'integer',
        'app_id' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 隐藏字段
    protected $hidden = ['password'];
    
    /**
     * 关联应用
     */
    public function app()
    {
        return $this->belongsTo(AppModel::class, 'app_id', 'app_id');
    }
    
    /**
     * 获取超级管理员状态文本
     */
    public function getIsSuperTextAttr($value, $data)
    {
        return $data['is_super'] ? '是' : '否';
    }
    
    /**
     * 获取删除状态文本
     */
    public function getIsDeleteTextAttr($value, $data)
    {
        return $data['is_delete'] ? '已删除' : '正常';
    }
    
    /**
     * 密码加密
     */
    public function setPasswordAttr($value)
    {
        return md5($value);
    }
    
    /**
     * 验证密码
     */
    public function checkPassword($password)
    {
        return $this->password === md5($password);
    }
    
    /**
     * 获取用户列表
     */
    public static function getUserList($appId = null)
    {
        $query = self::where('is_delete', 0);
        if ($appId) {
            $query->where('app_id', $appId);
        }
        return $query->with(['app'])->select();
    }
    
    /**
     * 根据用户名查找用户
     */
    public static function findByUsername($username, $appId = null)
    {
        $query = self::where('user_name', $username)->where('is_delete', 0);
        if ($appId) {
            $query->where('app_id', $appId);
        }
        return $query->find();
    }
    
    /**
     * 创建用户
     */
    public static function createUser($data)
    {
        $data['password'] = md5($data['password']);
        return self::create($data);
    }
    
    /**
     * 更新用户
     */
    public function updateUser($data)
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = md5($data['password']);
        }
        return $this->save($data);
    }
}
