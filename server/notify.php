<?php
header("Content-type:application/json");
include 'Db.php';

$orderMsg = $_GET['orderMsg'];
$sign = $_GET['sign'];
$timestamp = $_GET['time'];

if(!$orderMsg || !$sign || !$timestamp) {
    echo '参数不完整！';
    notifyLog('参数不完整！', ' - ');
    exit;
}

$SecretKey = 'QWA123123QWA565678';

if(generateSignature($timestamp, $SecretKey) == $sign) {
    $conn = new mysqli($Db_Config['dbhost'], $Db_Config['dbuser'], $Db_Config['dbpass'], $Db_Config['dbname']);
    if(strpos($orderMsg,'AlipayGphone') !== false){
        $money_1 = substr($orderMsg, strripos($orderMsg, "收款") + 6);
        $money_2 = substr($money_1, 0, strrpos($money_1, "元"));
        $pay_type = 'alipay';
    } else {
        $money_1 = substr($orderMsg, strripos($orderMsg, "到账") + 6);
        $money_2 = substr($money_1, 0, strrpos($money_1, "元"));
        $pay_type = 'wxpay';
    }
    $order_paytime = time();
    $notify = "UPDATE wxpay_zsm_orders SET order_content='$orderMsg',order_paytime='$order_paytime',order_status='2' WHERE order_status='1' AND order_amount='$money_2' AND order_time >= UNIX_TIMESTAMP(NOW() - INTERVAL 2 MINUTE)";
    if ($conn->query($notify) === TRUE) {
        echo '回调通知成功';
        notifyLog('回调通知成功，金额：' . $money_2, $pay_type);
    } else {
        echo '通知失败' . $conn->error;
        notifyLog('通知失败' . $conn->error, $pay_type);
    }
    $conn->close();
} else {
    echo '签名错误！';
    notifyLog('签名错误', ' - ');
}

function generateSignature($timestamp, $secretKey) {
    $signatureString = $timestamp . "\n" . $secretKey;
    $hash = hash_hmac('sha256', $signatureString, $secretKey, true);
    $base64EncodedHash = base64_encode($hash);
    $urlEncodedSignature = urlencode($base64EncodedHash);
    return $urlEncodedSignature;
}

function notifyLog($message, $pay_type) {
    $logFile = '../log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message $pay_type" . PHP_EOL;
    $fileHandle = fopen($logFile, 'a');
    if ($fileHandle) {
        fwrite($fileHandle, $logMessage);
        fclose($fileHandle);
    }
}
?>
