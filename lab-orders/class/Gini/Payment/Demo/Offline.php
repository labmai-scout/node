<?php

namespace Gini\Payment\Demo;

class Offline implements \Gini\Payment\PaymentHandler {

    function pay($statement, $criteria=[])
    {
    	if (!$statement->id ||
    		!$statement->name() == 'payment/statement' ||
    		!$statement->status == \Gini\ORM\Payment\Statement::STATUS_DRAFT) return false;

    	$statement->department    = $criteria['department'];
    	$statement->department_no = $criteria['department_no'];
    	$statement->project       = $criteria['project'];
    	$statement->project_no    = $criteria['project_no'];
        $statement->method        = $criteria['method'];

        $statement->pdata = [
            'department'=> $statement->department,
            'department_no'=> $statement->department_no,
            'project'=> $statement->project,
            'project_no'=> $statement->project_no
        ];

    	$bool = $this->_changeToStatus($statement, \Gini\ORM\Payment\Statement::STATUS_PENDING_TRANSFER);
        if ($bool) {
            $statement->log('**{user}** 已成功提交支付', [
                'user' => _G('ME')->name
            ]);
        }

        return $bool && $statement->sync();

    }

    function success($statement)
    {
    	if (!$statement->id ||
    		!$statement->name() == 'payment/statement' ||
    		$statement->status == \Gini\ORM\Payment\Statement::STATUS_TRANSFERRED) return false;

        $bool = $this->_changeToStatus($statement, \Gini\ORM\Payment\Statement::STATUS_TRANSFERRED);

        if ($bool) {
            if (_G('ME')->id) {
                $statement->log('**{user}** 成功进行了支付', [
                    'user' => _G('ME')->name
                ]);
            } else {
                $statement->log('支付成功');
            }
        }

        return $bool;
    }

    function fail($statement, $reason='')
    {
    	if (!$statement->id ||
    		!$statement->name() == 'payment/statement' ||
    		!$statement->status == \Gini\ORM\Payment\Statement::STATUS_PENDING_TRANSFER) return false;


        $bool = $this->_changeToStatus($statement, \Gini\ORM\Payment\Statement::STATUS_FAILED);

        if ($bool) {
            $log = '支付失败';
            $statement->log($log, [], $reason);
        }

        return $bool;
    }

    function cancel($statement, $reason = '')
    {

    }

    function _changeToStatus($statement, $status)
    {
        switch ($status) {

        case \Gini\ORM\Payment\Statement::STATUS_TRANSFERRED:
            $order_func = 'successPayment';
            break;
        case \Gini\ORM\Payment\Statement::STATUS_FAILED:
            $order_func = 'failPayment';
            break;
        case \Gini\ORM\Payment\Statement::STATUS_CANCEL:
            $order_func = 'cancelPayment';
            break;
        default:
            $order_func = '';
            break;
        }

        $orders = $statement->getOrders();

        $me = _G('ME');
        $db = $statement->db();
        $db->beginTransaction();

        if ($order_func) {
            foreach ($orders as $order) {
                $ret = $order->{$order_func}($me, $statement);
                if (!$ret) {
                    $db->rollback();
                    return false;
                }
            }
        }

        $statement->status = $status;

        if (!$statement->save()) {
            $db->rollback();
            return false;
        }

        $db->commit();

        $statement->sync();

        return true;
    }
}
