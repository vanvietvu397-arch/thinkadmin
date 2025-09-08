<?php

namespace app\common\model;

use think\admin\Model;

/**
 * 供应商用户模型
 * Class SupplierUser
 * @package app\common\model
 */
class SupplierUser extends Model
{
    // 指定数据库连接
    protected $connection = 'mysql2';
    
    // 指定表名
    protected $table = 'jjjshop_supplier_user';
    
    // 指定主键
    protected $pk = 'supplier_user_id';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 日志类型
    protected $oplogType = '供应商用户管理';
    
    // 日志名称
    protected $oplogName = '供应商用户';
    
    // 字段类型转换
    protected $type = [
        'supplier_user_id' => 'integer',
        'user_id' => 'integer',
        'source' => 'integer',
        'source_id' => 'integer',
        'is_super' => 'integer',
        'shop_supplier_id' => 'integer',
        'is_delete' => 'integer',
        'type' => 'integer',
        'app_id' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];
    
    // 隐藏字段
    protected $hidden = ['password'];
    
    // 常量定义
    const SOURCE_SYSTEM = 0;  // 系统添加
    const SOURCE_COMMUNITY = 1; // 社区添加
    
    const TYPE_GRID_LEADER = 1;  // 网格长
    const TYPE_GRID_MEMBER = 2;  // 网格员
    const TYPE_PART_TIME = 3;    // 兼职
    const TYPE_POLICE = 4;       // 民警
    
    /**
     * 关联应用
     */
    public function app()
    {
        return $this->belongsTo(App::class, 'app_id', 'app_id');
    }
    
    /**
     * 关联供应商
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'shop_supplier_id', 'shop_supplier_id');
    }
    
    /**
     * 获取来源文本
     */
    public function getSourceTextAttr($value, $data)
    {
        $sourceMap = [
            self::SOURCE_SYSTEM => '系统添加',
            self::SOURCE_COMMUNITY => '社区添加',
        ];
        return $sourceMap[$data['source']] ?? '未知';
    }
    
    /**
     * 获取用户类型文本
     */
    public function getTypeTextAttr($value, $data)
    {
        $typeMap = [
            self::TYPE_GRID_LEADER => '网格长',
            self::TYPE_GRID_MEMBER => '网格员',
            self::TYPE_PART_TIME => '兼职',
            self::TYPE_POLICE => '民警',
        ];
        return $typeMap[$data['type']] ?? '未知';
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
    public static function getUserList($supplierId = null, $appId = null)
    {
        $query = self::where('is_delete', 0);
        if ($supplierId) {
            $query->where('shop_supplier_id', $supplierId);
        }
        if ($appId) {
            $query->where('app_id', $appId);
        }
        return $query->with(['app', 'supplier'])->select();
    }
    
    /**
     * 根据用户名查找用户
     */
    public static function findByUsername($username, $supplierId = null, $appId = null)
    {
        $query = self::where('user_name', $username)->where('is_delete', 0);
        if ($supplierId) {
            $query->where('shop_supplier_id', $supplierId);
        }
        if ($appId) {
            $query->where('app_id', $appId);
        }
        return $query->find();
    }
    
    /**
     * 获取供应商下的用户列表
     */
    public static function getSupplierUsers($supplierId)
    {
        return self::where('shop_supplier_id', $supplierId)
                   ->where('is_delete', 0)
                   ->with(['app'])
                   ->select();
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
    
    /**
     * 获取用户类型选项
     */
    public static function getTypeOptions()
    {
        return [
            ['value' => self::TYPE_GRID_LEADER, 'title' => '网格长'],
            ['value' => self::TYPE_GRID_MEMBER, 'title' => '网格员'],
            ['value' => self::TYPE_PART_TIME, 'title' => '兼职'],
            ['value' => self::TYPE_POLICE, 'title' => '民警'],
        ];
    }
    
    /**
     * 获取来源选项
     */
    public static function getSourceOptions()
    {
        return [
            ['value' => self::SOURCE_SYSTEM, 'title' => '系统添加'],
            ['value' => self::SOURCE_COMMUNITY, 'title' => '社区添加'],
        ];
    }
}
