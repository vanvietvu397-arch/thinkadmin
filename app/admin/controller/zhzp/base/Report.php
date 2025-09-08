<?php

// +----------------------------------------------------------------------
// | WeMall Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2025 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// | 会员免费 ( https://thinkadmin.top/vip-introduce )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-wemall
// | github 代码仓库：https://github.com/zoujingli/think-plugs-wemall
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace app\admin\controller\zhzp\base;


use think\admin\Controller;
use think\Model;

/**
 * 商城数据统计
 * @class Report
 * @package plugin\wemall\controller\base
 */
class Report extends Controller
{
    /**
     * 显示数据统计
     * @auth true
     * @menu true
     * @throws \think\db\exception\DbException
     */
    public function index()
    {
        $this->usersTotal = 0;
        $this->goodsTotal = 0;
        $this->orderTotal = 0;
        $this->amountTotal = 0;


            // 组装15天的统计数据
            for ($i = 15; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i}days"));
                $this->days[] = [
                    '当天日期' => date('m-d', strtotime("-{$i}days")),
                    '增加用户' => 0,
                    '订单数量' => 0,
                    '订单金额' => 0,
                    '返佣金额' => 0,
                    '剩余余额' => 0,
                    '充值余额' => 0,
                    '消费余额' => 0,
                ];
            }

        $this->levels = array_values([12,3,4,5,6,7]);
        $this->fetch();
    }
}