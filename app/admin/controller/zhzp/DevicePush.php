<?php

// +----------------------------------------------------------------------
// | Admin Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------

namespace app\admin\controller\zhzp;

use think\admin\Controller;
use think\admin\helper\QueryHelper;
use app\common\model\DevicePushModel;
use app\common\model\AppModel;
use app\common\model\SupplierModel;
use app\common\model\DeviceModel;
use app\common\model\DeviceGroupModel;

/**
 * 设备推送管理
 * @class DevicePush
 * @package app\admin\controller\zhzp
 */
class DevicePush extends Controller
{
    /**
     * 设备推送管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        DevicePushModel::mQuery()->layTable(function () {
            $this->title = '设备推送管理';
            $this->apps = AppModel::getEnabledList();
            $this->suppliers = SupplierModel::getEnabledList();
        }, function (QueryHelper $query) {
            // 加载对应数据列表
            if ($this->type === 'index') {
                $query->where(['is_delete' => 0]);
            } else {
                $query->where(['is_delete' => 1]);
            }
            
            // 搜索条件
            $query->like('push_title,push_content#push_title');
            $query->equal('app_id,shop_supplier_id,push_status,push_type');
            $query->dateBetween('create_time');
            
            // 关联查询
            $query->with(['app', 'supplier']);
        });
    }

    /**
     * 添加设备推送
     * @auth true
     * @menu false
     */
    public function add()
    {
        DevicePushModel::mForm('form');
    }

    /**
     * 编辑设备推送
     * @auth true
     * @menu false
     */
    public function edit()
    {
        DevicePushModel::mForm('form');
    }

    /**
     * 表单数据处理
     * @param array $data
     * @throws \think\db\exception\DbException
     */
    protected function _form_filter(&$data)
    {
        if ($this->request->isGet()) {
            // 获取应用和供应商数据
            $this->apps = AppModel::getEnabledList();
            $this->suppliers = SupplierModel::getEnabledList();
            
            // 获取设备列表
            $this->devices = DeviceModel::where('is_delete', 0)->where('status', 1)->field('id,device_name,device_number')->select();
            
            // 获取设备分组列表
            $this->groups = DeviceGroupModel::where('is_delete', 0)->where('status', 1)->field('id,group_name')->select();
        }
        
        if ($this->request->isPost()) {
            // 验证必填字段
            if (empty($data['push_title'])) {
                $this->error('推送标题不能为空');
            }
            if (empty($data['push_content'])) {
                $this->error('推送内容不能为空');
            }
            if (empty($data['push_start_time'])) {
                $this->error('推送开始时间不能为空');
            }
            if (empty($data['push_end_time'])) {
                $this->error('推送结束时间不能为空');
            }
            
            // 验证时间
            if (strtotime($data['push_start_time']) >= strtotime($data['push_end_time'])) {
                $this->error('推送开始时间必须小于结束时间');
            }
            
            // 处理推送设备数据
            if (isset($data['push_device']) && is_array($data['push_device'])) {
                $data['push_device'] = json_encode($data['push_device']);
            }
            
            // 设置默认值
            $data['push_status'] = $data['push_status'] ?? 1;
            $data['push_type'] = $data['push_type'] ?? 1;
            $data['app_id'] = $data['app_id'] ?? 10001;
            $data['shop_supplier_id'] = $data['shop_supplier_id'] ?? 1;
            
            // 转换时间戳
            $data['push_start_time'] = strtotime($data['push_start_time']);
            $data['push_end_time'] = strtotime($data['push_end_time']);
        }
    }

    /**
     * 删除设备推送
     * @auth true
     * @menu false
     */
    public function remove()
    {
        $ids = $this->request->post('id');
        if (empty($ids)) {
            $this->error('请选择要删除的推送');
        }
        
        // 处理ID数组
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        
        // 执行软删除，将 is_delete 设置为 1
        $result = DevicePushModel::whereIn('id', $ids)->update(['is_delete' => 1]);
        
        if ($result) {
            $this->success('推送删除成功');
        } else {
            $this->error('推送删除失败');
        }
    }

    /**
     * 禁用设备推送
     * @auth true
     * @menu false
     */
    public function forbid()
    {
        DevicePushModel::mSave();
    }

    /**
     * 启用设备推送
     * @auth true
     * @menu false
     */
    public function resume()
    {
        DevicePushModel::mSave();
    }

    /**
     * 推送状态切换
     * @auth true
     * @menu false
     */
    public function state()
    {
        DevicePushModel::mSave();
    }

    /**
     * 推送详情
     * @auth true
     * @menu false
     */
    public function detail()
    {
        $id = $this->request->param('id');
        if (empty($id)) {
            $this->error('推送ID不能为空');
        }

        $push = DevicePushModel::with(['app', 'supplier'])
                              ->where('id', $id)
                              ->where('is_delete', 0)
                              ->find();

        if (!$push) {
            $this->error('推送不存在');
        }

        // 解析推送设备数据
        $pushDevices = [];
        if (!empty($push->push_device)) {
            $deviceIds = json_decode($push->push_device, true);
            if (is_array($deviceIds) && !empty($deviceIds)) {
                $pushDevices = DeviceModel::whereIn('id', $deviceIds)
                                     ->where('is_delete', 0)
                                     ->with(['app', 'supplier'])
                                     ->select();
            }
        }

        $this->push = $push;
        $this->pushDevices = $pushDevices;
        $this->title = '推送详情 - ' . $push->push_title;
        $this->fetch('detail');
    }

    /**
     * 获取推送列表（AJAX）
     * @auth true
     * @menu false
     */
    public function getPushList()
    {
        $appId = $this->request->param('app_id');
        $supplierId = $this->request->param('shop_supplier_id');
        
        $query = DevicePushModel::where('is_delete', 0)->where('push_status', 1);
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        if ($supplierId) {
            $query->where('shop_supplier_id', $supplierId);
        }
        
        $list = $query->field('id,push_title,push_type')->select();
        
        $this->success('获取成功', $list);
    }

    /**
     * 获取设备列表（AJAX）
     * @auth true
     * @menu false
     */
    public function getDeviceList()
    {
        $appId = $this->request->param('app_id');
        $supplierId = $this->request->param('shop_supplier_id');
        
        $query = DeviceModel::where('is_delete', 0)->where('status', 1);
        
        if ($appId) {
            $query->where('app_id', $appId);
        }
        
        if ($supplierId) {
            $query->where('shop_supplier_id', $supplierId);
        }
        
        $list = $query->field('id,device_name,device_number')->select();
        
        $this->success('获取成功', $list);
    }
}
