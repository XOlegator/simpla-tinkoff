<?php

require_once('api/Simpla.php');
require_once('TinkoffMerchantAPI.php');

class Tinkoff extends Simpla
{
    public function checkout_form($order_id, $button_text = null)
    {
        if(empty($button_text))
            $button_text = 'Перейти к оплате';

        $button = '';
        $order = $this->orders->get_order((int)$order_id);

        if ($order->status == 0) {
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

            if (isset($request->PaymentURL)) {
                $button = '<a class="checkout_button" style="display: inline-block" href="'.$request->PaymentURL.'">'.$button_text.'</a>';
            } else {
                $button = 'Запрос к сервису ТКС провален';
            }
        }

        return $button;
    }
}