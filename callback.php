<?php
chdir ('../../');
require_once('api/Simpla.php');
$simpla = new Simpla();

set_error_handler('exceptions_error_handler', E_ALL);
function exceptions_error_handler($severity) {
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        die('NOTOK');
    }
}

try {
    $order = $simpla->orders->get_order(intval($_POST['OrderId']));
    $method = $simpla->payment->get_payment_method(intval($order->payment_method_id));
    $settings = unserialize($method->settings);

    $_POST['Password'] = $settings['tinkoff_secret'];
    ksort($_POST);
    $sorted = $_POST;
    $original_token = $sorted['Token'];
    unset($sorted['Token']);
    $values = implode('', array_values($sorted));
    $token = hash('sha256', $values);

    if ($token == $original_token) {

        if ($_POST['Status'] == 'AUTHORIZED' && $order->paid) {
            die('OK');
        }

        switch ($_POST['Status']) {
            case 'AUTHORIZED': $order_status = '1'; break; /*Деньги на карте захолдированы. Корзина очищается.*/
            case 'CONFIRMED': $order_status = '2'; break; /*Платеж подтвержден.*/
            case 'CANCELED': $order_status = '3'; break; /*Платеж отменен*/
            case 'REJECTED': $order_status = '3'; break; /*Платеж отклонен.*/
            case 'REVERSED': $order_status = '3'; break; /*Платеж отменен*/
            case 'REFUNDED': $order_status = '3'; break; /*Произведен возврат денег клиенту*/
        }

        if ($_POST['Status'] == 'CONFIRMED') {
            $update_array = array('paid' => 1);
        }

        // Установим статус оплачен
        $simpla->orders->update_order(intval($order->id), $update_array);

        // Спишем товары
        $simpla->orders->close(intval($order->id));

        // Отправим уведомление на email
        $simpla->notify->email_order_user(intval($order->id));
        $simpla->notify->email_order_admin(intval($order->id));

        die('OK');
    } else {
        die('NOTOK');
    }
} catch(Exception $e) {
    die('NOTOK');
}
