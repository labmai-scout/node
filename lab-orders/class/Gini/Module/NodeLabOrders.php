<?php

namespace Gini\Module;

class NodeLabOrders
{
    public static function setup()
    {
        // DO SOME INITIAL STUFF
    }

    public static function diagnose()
    {
        // 检查pdf字体是否生成
        if (!file_exists(APP_PATH.'/'.DATA_DIR.'/fonts/simfang.ttf')) {
            return ['请下载仿宋字体 "curl -sLo data/fonts/simfang.ttf http://d.genee.cn/fonts/simfang.ttf"'];
        }
        if (!file_exists(APP_PATH.'/'.DATA_DIR.'/fonts/simfang.php')) {
            return ['请处理仿宋字体 "php vendor/tecnickcom/tcpdf/tools/tcpdf_addfont.php -i data/fonts/simfang.ttf -o data/fonts"'];
        }

        try {
            $conf = \Gini\Config::get('app.rpc')['gateway'];
            $rpc = \Gini\IoC::construct('\Gini\RPC', $conf['url']);
            if (!$rpc->Gateway->authorize($conf['client_id'], $conf['client_secret'])) {
                return ['Please check your Gateway RPC config in app.yml!'];
            }
        } catch (\Exception $e) {
            return ['app.rpc.gateway: '.$e->getMessage()];
        }
    }

    /**
     * @brief 订单付款按钮的开关 trigger触发
     * @return [type] [description]
     */
    private static function _getProductType($types)
    {
    // 优先级  易制毒 > 易制爆 > 剧毒品 > 危化品 explosive
        if (empty($types)) return FALSE;

        $sorts = ['drug_precursor','explosive','highly_toxic','psychotropic','narcotic','hazardous'];

        foreach ($sorts as $t) {
            if (in_array($t, $types)) {
                return $t;
            }
        }

        return false;
    }
    public static function productInSameGroup($e, $product, $target)
    {
        $is_customized = (int)$product['customized'];
        // 自购供应商的商品，由其他逻辑处理，我只关注远程商品
        if ($is_customized) return;
        // 如果两个商品都是危化品, 危化品是一类的放在一起, 不然分拆
        $target_product = $target['product'];
        $pid = (int)$product['id'];
        $product = a('product', $pid);

        if ($product->cas_no || $target_product->cas_no) {

            $pTypes = [];
            if ($product->cas_no) {
                $pTypes = (array)\Gini\ChemDB\Client::getTypes($product->cas_no);
                $pTypes = $pTypes[$product->cas_no];
            }

            $tTypes = [];
            if ($target_product->cas_no) {
                $tTypes = (array)\Gini\ChemDB\Client::getTypes($target_product->cas_no);
                $tTypes = $tTypes[$target_product->cas_no];
            }

            // 不能同组的条件是 两个有一个是 危化品 并且两个类别不一样就不可以同组
            if (self::_getProductType($pTypes)!==self::_getProductType($tTypes)) {
                return false;
            }
        }
        return TRUE;
    }
}
