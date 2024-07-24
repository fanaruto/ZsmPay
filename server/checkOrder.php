<?php
header("Content-type:application/json");
include 'Db.php';

$conn = new mysqli($Db_Config['dbhost'], $Db_Config['dbuser'], $Db_Config['dbpass'], $Db_Config['dbname']);

$order_num = $_GET['order_num'];
$order_amount = $_GET['order_amount'];

if(!$order_amount) {
    $result = array('code' => -1, 'msg' => '订单金额为空');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if(!$order_num) {
    $result = array('code' => -2, 'msg' => '订单号为空');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$checkOrder = "SELECT * FROM wxpay_zsm_orders WHERE order_num='$order_num' AND order_amount='$order_amount'";
$checkOrderSQL = $conn->query($checkOrder);

if ($checkOrderSQL->num_rows > 0) {
    $checkOrderRows = $checkOrderSQL->fetch_assoc();
    if($checkOrderRows['order_status'] == 2) {
        $result = array('code' => 0, 'msg' => '已完成支付', 'order_type' => $checkOrderRows['order_type']);
    } else {
        $result = array('code' => -2, 'msg' => '未完成支付');
    }
} else {
    $result = array('code' => -1, 'msg' => '无法查询到该订单的状态');
}

$conn->close();
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>
