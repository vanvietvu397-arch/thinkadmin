<?php

namespace app\command;

use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\worker\command\GatewayWorker;
use Workerman\Worker;

/**
 * GatewayWorker win环境下的启动
 *
 * Class GatewayWorkerForWin
 * @package app\command
 */
class GatewayWorkerForWin extends GatewayWorker
{
    public function configure()
    {
        $this->setName('worker:gateway_win')
            ->addArgument('service', Argument::OPTIONAL, 'workerman service: gateway|register|business_worker', null)
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, 'the host of workerman server.', null)
            ->addOption('port', 'p', Option::VALUE_OPTIONAL, 'the port of workerman server.', null)
            ->setDescription('GatewayWorker Server for ThinkPHP runs on Windows system');
    }

    /**
     * linux直接使用
     * php think worker:gateway
     * 由于windows下不支持下无法使用status、stop、reload、restart等命令。
     * 所以去掉status、stop、reload、restart、守护进程等命令。
     * 文档说明: https://www.workerman.net/doc/workerman/must-read.html
     * windows系统下workerman单个进程仅支持200+个连接。
     * windows系统下无法使用count参数设置多进程。
     * windows系统下无法使用status、stop、reload、restart等命令。
     * windows系统下无法守护进程，cmd窗口关掉后服务即停止。
     * windows系统下无法在一个文件中初始化多个监听。
     * linux系统无上面的限制，建议正式环境用linux系统，开发环境可以选择用windows系统。
     *
     * 命令使用:
     * php think worker:gateway_win register
     * php think worker:gateway_win business_worker
     * php think worker:gateway_win gateway
     *
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output)
    {
        $service = $input->getArgument('service');

        // 修复：使用Config::get获取完整配置
        $option = Config::get('gateway_worker');

        if ($input->hasOption('host')) {
            $host = $input->getOption('host');
        } else {
            $host = !empty($option['host']) ? $option['host'] : '0.0.0.0';
        }

        if ($input->hasOption('port')) {
            $port = $input->getOption('port');
        } else {
            $port = !empty($option['port']) ? $option['port'] : '2348';
        }

        $registerAddress = !empty($option['registerAddress']) ? $option['registerAddress'] : '127.0.0.1:1236';

        switch ($service) {
            case 'register':
                $this->register($registerAddress);
                break;
            case 'business_worker':
                $this->businessWorker($registerAddress, isset($option['businessWorker']) ? $option['businessWorker'] : []);
                break;
            case 'gateway':
                $this->gateway($registerAddress, $host, $port, $option);
                break;
            default:
                $output->writeln("<error>Invalid argument action:{$service}, Expected gateway|register|business_worker.</error>");
                exit(1);
                break;
        }

        Worker::runAll();
    }
}
