<?php

require_once('api/Simpla.php');
require_once('TinkoffMerchantAPI.php');

class Tinkoff extends Simpla
{
    public function checkout_form($order_id, $button_text = null)
    {
        if (empty($button_text)) {
            $button_text = 'Перейти к оплате &#8594;';
        }

        $button = '';
        $order = $this->orders->get_order((int)$order_id);

        $payment_method = $this->payment->get_payment_method($order->payment_method_id);
        $payment_currency = $this->money->get_currency(intval($payment_method->currency_id));
        $settings = $this->payment->get_payment_settings($payment_method->id);

        $price = round($this->money->convert($order->total_price, $payment_method->currency_id, false), 2);

        // описание заказа
        $desc = 'Оплата заказа №'.$order->id;
        $config = new Config();
        $arrFields = array(
            'OrderId'  => $order->id,
            'Amount'   => $price * 100,
            'DATA'	   => 'Email='.$order->email.'|connection_type=simpla'.$config->version
        );

        $Tinkoff = new TinkoffMerchantAPI( $settings['tinkoff_terminal'], $settings['tinkoff_secret'], $settings['tinkoff_gateway'] );
        $request = $Tinkoff->buildQuery('Init', $arrFields);
        $request = json_decode($request);

        // Пороверим, не закончился ли поток данных от Тинькофф по причине того, что оплата уже прошла.
        // Если оплата уже прошла, вернётся код ошибки "8"
        if (isset($request->Success) && isset($request->ErrorCode) && $request->ErrorCode == 8) {
            // Отправляем пользователя на страницу заказа
            $orderUrl = $this->config->root_url . '/order/' . $order->url;
            $button = '<p>Платёж принят. <a href="' . $orderUrl . '" title="Если не сработало автоматическое перенаправление, то так можно перейти к заказу вручную">Перейти к просмотру заказа</a></p>';
            $button .= '<script>
                if (window.location.href != ' . $orderUrl . ') {
                    window.location.href = "' . $orderUrl . '";
                }
                </script>';
        } else if (isset($request->PaymentURL)) {
            $button = '<a class="checkout_button btn btn-success btn-lg" href="'.$request->PaymentURL.'">'.$button_text.'</a><br>';
        } else {
            $button = 'Запрос к сервису ТКС провален<br>';
        }

        return $button;
    }
}
