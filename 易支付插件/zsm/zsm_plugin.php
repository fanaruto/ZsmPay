<?php

    /**
     * 2024-07
     * 作者：Captainhttps://github.com/Captain-wang-s
     * 作者博客：
     
     */ 

class zsm_plugin
{
    static public $info = [
        'name'        => 'zsm', //支付插件英文名称，需和目录名称一致，不能有重复
        'showname'    => 'z免签', //支付插件显示名称
        'author'      => 'z免签', //支付插件作者
        'link'        => 'https://github.com/Captain-wang-s/liKeYun_ZsmPay', //支付插件作者链接
        'types'       => ['alipay','qqpay','wxpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
        'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
            'appurl' => [
                'name' => '接口地址',
                'type' => 'input',
                'note' => '必须以http://或https://开头，以/结尾',
            ],
            'appid' => [
                'name' => '商户ID',
                'type' => 'input',
                'note' => '如果不需要商户ID，随便填写即可',
            ],
            'appkey' => [
                'name' => '通讯密钥',
                'type' => 'input',
                'note' => '',
            ],
        ],
        'select' => null,
        'note' => '', //支付密钥填写说明
        'bindwxmp' => false, //是否支持绑定微信公众号
        'bindwxa' => false, //是否支持绑定微信小程序
    ];

    static public function submit(){
        global $siteurl, $channel, $order, $ordername, $sitename, $conf;

        if($order['typename']=='alipay'){
            $paytype='2';
        }elseif($order['typename']=='qqpay'){
            $paytype='4';
        }elseif($order['typename']=='wxpay'){
            $paytype='1';
        }elseif($order['typename']=='bank'){
            $paytype='3';
        }

        // 生成订单参数
        $order_title = $ordername; // 订单标题
        $order_amount = $order['realmoney']; // 订单金额
        $order_type = $paytype; // 支付类型

        // 调用 createOrder.php 创建订单
        $createOrderUrl = $channel['appurl'] . 'server/createOrder.php';
        $params = [
            'order_price' => $order_amount,
            'order_amount' => $order_amount,
            'order_type' => $order_type,
            'order_title' => $order_title,
            'order_id' => $order['trade_no'] // 传递A站点的订单号
        ];
        $query = http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $createOrderUrl . '?' . $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($result['code'] === 0) {
            $order_num = $result['order_num'];
            $order_amount = $result['order_amount'];
            $order_title = $result['order_title'];
            $order_time = $result['order_time'];
            $order_qrcode = $result['order_qrcode'];
            $order_type = $result['order_type'];

            // 生成跳转URL
            $jump_url = $channel['appurl'].'index.html?order_num='.$order_num.'&order_amount='.$order_amount.'&order_title='.$order_title.'&order_time='.$order_time.'&order_type='.$order_type;

            // 返回跳转信息
            return ['type'=>'jump','url'=>$jump_url];
        } else {
            return ['type'=>'error','msg'=>'创建订单失败: ' . $result['msg']];
        }
    }

    //异步回调
    static public function notify(){
        global $channel;

        $order_num = $_GET['order_num'];//商户订单号
        $order_amount = $_GET['order_amount'];//订单金额
        $sign = $_GET['sign'];//校验签名，计算方式 = md5(order_num + order_amount + 通讯密钥)

        if(!$order_num || !$sign)return ['type'=>'html','data'=>'error_param'];

        $_sign =  md5($order_num . $order_amount . $channel['appkey']);
        if ($_sign !== $sign)return ['type'=>'html','data'=>'error_sign'];

        // 查找对应的订单
        $order = findOrderByTradeNo($order_num);
        if(!$order) return ['type'=>'html','data'=>'order_not_found'];

        if($order['status'] == 'unpaid' && round($order_amount,2)==round($order['realmoney'],2)){
            processNotify($order, $order_num);
        }
        return ['type'=>'html','data'=>'success'];
    }

    //同步回调
    static public function return(){
        global $channel;

        $order_num = $_GET['order_num'];//商户订单号
        $order_amount = $_GET['order_amount'];//订单金额
        $sign = $_GET['sign'];//校验签名，计算方式 = md5(order_num + order_amount + 通讯密钥)

        if(!$order_num || !$sign)return ['type'=>'error','data'=>'参数不完整'];

        $_sign =  md5($order_num . $order_amount . $channel['appkey']);
        if ($_sign !== $sign)return ['type'=>'error','data'=>'签名校验失败'];

        // 查找对应的订单
        $order = findOrderByTradeNo($order_num);
        if(!$order) return ['type'=>'html','data'=>'order_not_found'];

        if($order['status'] == 'unpaid' && round($order_amount,2)==round($order['realmoney'],2)){
            processReturn($order, $order_num);
        }else{
            return ['type'=>'error','msg'=>'订单信息校验失败'];
        }
    }
}

// // 根据订单号查找订单的逻辑
// function findOrderByTradeNo($tradeNo) {
//     global $db;

//     // 查询订单信息
//     $sql = "SELECT * FROM orders WHERE trade_no = ?";
//     $stmt = $db->prepare($sql);
//     $stmt->execute([$tradeNo]);
//     $order = $stmt->fetch(PDO::FETCH_ASSOC);

//     return $order;
// }

// // 处理回调通知的逻辑
// function processNotify($order, $tradeNo) {
//     global $db;

//     // 更新订单状态
//     $sql = "UPDATE orders SET status = 'paid' WHERE trade_no = ?";
//     $stmt = $db->prepare($sql);
//     $stmt->execute([$tradeNo]);

//     // 记录支付时间
//     $sql = "UPDATE orders SET paytime = NOW() WHERE trade_no = ?";
//     $stmt = $db->prepare($sql);
//     $stmt->execute([$tradeNo]);

//     // 其他业务逻辑处理
// }

// // 处理同步回调的逻辑
// function processReturn($order, $tradeNo) {
//     global $db;

//     // 更新订单状态
//     $sql = "UPDATE orders SET status = 'paid' WHERE trade_no = ?";
//     $stmt = $db->prepare($sql);
//     $stmt->execute([$tradeNo]);

//     // 记录支付时间
//     $sql = "UPDATE orders SET paytime = NOW() WHERE trade_no = ?";
//     $stmt = $db->prepare($sql);
//     $stmt->execute([$tradeNo]);

//     // 其他业务逻辑处理
// }
?>

