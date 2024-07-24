<?php
header("Content-type:application/json");
include 'Db.php';

$conn = new mysqli($Db_Config['dbhost'], $Db_Config['dbuser'], $Db_Config['dbpass'], $Db_Config['dbname']);

$order_price = $_GET['order_price']; // 修改为 order_price
$order_amount = $_GET['order_amount'];
$order_type = $_GET['order_type'];
$order_title = $_GET['order_title'];
$order_id = $_GET['order_id']; // 接收A站点传递过来的订单号

if(!$order_price) {
    $result = array('code' => -3, 'msg' => '订单金额为空');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if(!$order_type) {
    $result = array('code' => -4, 'msg' => '支付渠道为空');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if(!$order_title) {
    $result = array('code' => -5, 'msg' => '订单标题为空');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$order_amount_array = array($order_price);
for ($i = 0; $i < 19; $i++) {
    $order_price += 0.01;
    $order_amount_array[] = $order_price;
}

$order_amount_allow = array();
$order_amount_forbidden = array();

foreach ($order_amount_array as $order_amount) {
    $sql = "SELECT * FROM wxpay_zsm_orders WHERE order_amount = '$order_amount' AND order_status = '1' AND order_time >= UNIX_TIMESTAMP(NOW() - INTERVAL 2 MINUTE)";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $order_amount_forbidden[] = $order_amount;
    } else {
        $order_amount_allow[] = $order_amount;
    }
}

if(count($order_amount_allow) == 0) {
    $result = array('code' => -1, 'msg' => '太多人了，稍后再试试吧...');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$order_num = $order_id; // 使用A站点传递过来的订单号
$order_time = time();
$order_amount = $order_amount_allow[0];
$order_extra = '';

$sql = "INSERT INTO wxpay_zsm_orders (order_num, order_title, order_time, order_amount, order_price, order_type, order_extra) 
VALUES ('$order_num', '$order_title', '$order_time', '$order_amount', '$order_price', '$order_type', '$order_extra')";

if ($conn->query($sql) === TRUE) {
    $result = array(
        'code' => 0,
        'msg' => '创建订单成功',
        'order_num' => $order_num,
        'order_amount' => number_format($order_amount, 2),
        'order_title' => $order_title,
        'order_time' => date('Y-m-d H:i:s', $order_time),
        'order_qrcode' => 'img/qrcode.png',
        'order_type' => $order_type
    );
} else {
    $result = array('code' => -2, 'msg' => '创建订单失败', 'error' => $sql . $conn->error);
}

$conn->close();
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>
