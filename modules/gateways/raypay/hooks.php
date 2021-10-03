<?php
use WHMCS\Database\Capsule;

add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {
    $transaction = Capsule::table('tblaccounts')->where('invoiceid', $vars['invoiceid'])->first();

    if($vars['paymentmethod'] == 'raypay' && $vars['ispaid'] == true && isset($transaction->transid)){
        $gatewayParams = getGatewayVariables('raypay');

        $output = '<div class="col-sm-8 col-sm-offset-2 alert alert-success order-confirmation raypay">';
        $output .= str_replace(["{invoice_id}"], [ $transaction->transid], $gatewayParams['success_massage']);
        $output .='</div>';
        $output .='<style>.order-confirmation:not(.raypay) {display: none;}</style>';
        return $output;
    }
});
