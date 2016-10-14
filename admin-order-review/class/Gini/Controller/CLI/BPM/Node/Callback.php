<?php

namespace Gini\Controller\CLI\BPM\Node;

class Callback extends \Gini\Controller\CLI
{
    public static function autoSelectSchool($task)
    {
        $instance = $task->instance;
        $data = (array)$instance->getVariable('data');
        $order = a('order');
        $order->setData($data);

        $needApprove = false;
        // 自购订单必须要审核
        if ($order->customized) {
            // TODO 是否需要设置自购订单是否需要进入审核流程
            $needApprove = true;
        } else {
            // 如果关联的商品不需要审核，直接通过
            $items = (array)$order->items;
            foreach ($items as $item) {
                $casNO = $item['cas_no'];
                if (!$casNO) {
                    $criteria = ['id'=> $item['id']];
                    if ($item['version']) {
                        $criteria['version'] = $item['version'];
                    }
                    $product = a('product', $criteria);
                    if (!$product->id) break;
                    $casNO = $product->cas_no;
                }
                if ($casNO && self::_isHazPro($casNO)) {
                    $needApprove = true;
                    break;
                }
            }
        }

        if (!$needApprove) {
            $task->autoApprove(T('该订单中没有管制商品, 系统自动审核通过'));
            $instance->next();
            return;
        }

        $node = $order->node;
        $groupID = $order->group->id;
        $key = "labmai-{$node}/{$groupID}";
        $info = (array)\Gini\TagDB\Client::of('rpc')->get($key);
        $organization = $info['organization'];
        $ocode = $organization['school_code'];
        $oname = $organization['school_name'];
        if (!$ocode || !$oname) {
            $task->autoReject(T('订单的院系信息缺失, 自动拒绝'));
            $instance->next();
            return;
        }
        // 确认组订单所属的组已经在管理界面被生成了
        $groupName = "school-{$ocode}";
        $group = a('sjtu/bpm/process/group', ['name'=>$groupName]);
        if (!$group->id) {
            $task->autoReject(T('尚未为您的院系指定订单审核人员, 自动拒绝'));
            $instance->next();
            return;
        }
        $bool = $task->autoApprove(T('订单交由院系管理员审核'), $groupName);
        $instance->next();
        return $bool;
    }

    public static function reject($task)
    {
        $instance = $task->instance;
        $data = (array)$instance->getVariable('data');
        $voucher = $data['voucher'];
        if (!$voucher) return;
        $rpc = self::_getRPC('order');
        if (!$rpc) return;
        $now = date('Y-m-d H:i:s');
        try {
            $bool = $rpc->mall->order->updateOrder($voucher, [
                'status' => \Gini\ORM\Order::STATUS_CANCELED,
                'description'=> [
                    'a' => T('**系统** 自动 **取消** 了该订单'),
                    't' => $now,
                ]
            ]);
        } catch (\Exception $e) {
            return;
        }
        if (!$bool) return;
        $task->update([
            'status'=> \Gini\Process\ITask::STATUS_UNAPPROVED
        ]);
        $instance->next();
        return $bool;
    }

    public static function pass($task)
    {
        $instance = $task->instance;
        $data = (array)$instance->getVariable('data');
        $order = a('order');
        $order->setData($data);

        $voucher = $data['voucher'];
        if (!$voucher) return;
        $rpc = self::_getRPC('order');
        if (!$rpc) return;
        $now = date('Y-m-d H:i:s');
        try {
            $bool = $rpc->mall->order->updateOrder($voucher, [
                'status' => $order->customized ? \Gini\ORM\Order::STATUS_APPROVED : \Gini\ORM\Order::STATUS_NEED_VENDOR_APPROVE,
                'payment_status'=> \Gini\ORM\Order::PAYMENT_STATUS_PENDING,
                'description'=> [
                    'a' => $order->customized ? T('订单被审批通过, 可以进行付款') : T('订单交给供应商确认'),
                    't' => $now,
                ]
            ]);
        } catch (\Exception $e) {
            return;
        }
        if (!$bool) return;
        $task->update([
            'status'=> \Gini\Process\ITask::STATUS_APPROVED
        ]);
        $instance->next();
        return $bool;
    }

    private static function _isHazPro($casNO)
    {
        if (!$casNO) return false;
        $types = (array)\Gini\ChemDB\Client::getTypes($casNO);
        $types = (array)@$types[$casNO];
        if (empty($types)) return false;

        // TODO 这个管控商品的分类是否需要是可定制？如何定制?
        $hazTypes = [
            'drug_precursor', // 易制毒
            'explosive', // 易制爆
            'highly_toxic', // 剧毒品
            'psychotropic', // 精神药品
            'narcotic', // 麻醉品
        ];
        return !empty(array_intersect($hazTypes, $types));
    }

    // 订单的更新直接向lab-orders进行提交, 因为hub-orders没有自购订单的信息
    private static $_RPCs = [];
    private static function _getRPC($type)
    {
        $confs = \Gini\Config::get('app.rpc');
        if (!isset($confs[$type])) {
            return;
        }
        $conf = $confs[$type] ?: [];
        if (!self::$_RPCs[$type]) {
            $rpc = \Gini\IoC::construct('\Gini\RPC', $conf['url']);
            self::$_RPCs[$type] = $rpc;
            $clientID = $conf['client_id'];
            $clientSecret = $conf['client_secret'];
            $token = $rpc->mall->authorize($clientID, $clientSecret);
            if (!$token) {
                \Gini\Logger::of(APP_ID)
                    ->error('Mall\\RObject getRPC: authorization failed with {client_id}/{client_secret} !',
                        ['client_id' => $clientID, 'client_secret' => $clientSecret]);
            }
        }

        return self::$_RPCs[$type];
    }

}


