<?php

namespace Gini\Controller\API;

class Debade extends \Gini\Controller\API
{
    public function actionGetNotified($data)
    {
        $hash = $_SERVER['HTTP_X_DEBADE_TOKEN'];
        $secret = \Gini\Config::get('app.debade_secret');
        $str = file_get_contents('php://input');

        if ($hash != \Gini\DeBaDe::hash($str, $secret)) {
            return;
        }
        if (isset($data['id'])) {
            switch ($data['id']) {
                case 'order':
                    $data = $data['data'];
                    if (isset($data['voucher'])) {
                        a('order', ['voucher' => $data['voucher']])->sync();
                    }
                    break;
                case 'payment_statement':
                    $data = $data['data'];
                    if (isset($data['voucher'])) {
                        $rpc = \Gini\ORM\Payment\Statement::getRPC();
                        $statement = a('payment/statement', [ 'voucher' => $data['voucher'] ]);
                        $result = $rpc->mall->payment->pullStatement($statement->voucher);
                        if ($statement->status == $result['status']) return;
                        $method = $statement->method;
                        $config = \Gini\Config::get('payment.method');
                        $backend = $config[$method]['backend'];
                        $payment = new $backend();
                        if ($result['status'] == \Gini\ORM\Payment\Statement::STATUS_TRANSFERRED) {
                            $payment->success($statement);
                        }
                        elseif ($result['status'] == \Gini\ORM\Payment\Statement::STATUS_FAILED) {
                            _G('ME', $statement->requester);
                            _G('GROUP', $statement->group);
                            $payment->fail($statement, $result['fail_reason']);
                        }
                    }
                    break;
            }
        }
    }
}
