<?php

/**
 * RayPay payment gateway
 *
 * @developer hanieh_ramzanpour
 * @publisher RayPay
 * @copyright (C) 2021 RayPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * https://raypay.ir
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

if (!defined("WHMCS")) die();

$gatewayParams = getGatewayVariables('raypay');

if (!$gatewayParams['type']) die('Module Not Activated');

/**
 * @param $failed_massage
 * @param $invoice_id
 * @return mixed
 */
function raypay_get_filled_message($massage, $invoice_id)
{
    return str_replace(["{invoice_id}"], [$invoice_id], $massage);
}

/**
 *  End RayPay process
 */
function raypay_end()
{
    global $orderid, $CONFIG, $paymentSuccess, $track_id;
    if (isset($orderid)) {
        if($paymentSuccess){
            callback3DSecureRedirect($orderid, $paymentSuccess);
        }
        else {
            header('Location: ' . $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $orderid . '&paymentfailed=true&track_id=' . $track_id);
            exit();
        }
    } else {
       header('Location: ' . $CONFIG['SystemURL'] . '/clientarea.php?action=invoices');
       exit();
    }
}

$paymentSuccess = false;
$orderid = 0;

if(!empty($_GET['invoice_id'])){
    $porder_id = $_GET['order_id'];
    $invoice_id = $_GET['invoice_id'];
    $track_id = $invoice_id;

    $orderid = checkCbInvoiceID($porder_id, $gatewayParams['name']);

    if (!empty($invoice_id) && !empty($porder_id) && $porder_id == $orderid)
    {
        $url = 'https://api.raypay.ir/raypay/api/v1/payment/verify';
        $ch = curl_init($url);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $_POST ) );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $result_string      = curl_exec( $ch );
        $result      = json_decode( $result_string );
        $http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

            if ( $http_status != 200 )
            {
                logTransaction( $gatewayParams['name'],
                    [
                        "GET"    => $_GET,
                        "POST"   => $_POST,
                        "result" => sprintf( 'خطا هنگام بررسی وضعیت تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->StatusCode, $result->Message )
                    ], 'Failure' );
                raypay_end();
            }

            $verify_status   = empty( $result->Data->Status ) ? NULL : $result->Data->Status;
            $verify_order_id = empty(  $result->Data->FactorNumber) ? NULL :  $result->Data->FactorNumber;
            $verify_amount = empty(  $result->Data->Amount) ? NULL :  $result->Data->Amount;

            checkCbTransID( $invoice_id );

            if ( empty( $verify_status ) || empty( $verify_order_id ) || $verify_status !== 1 )
            {
                logTransaction( $gatewayParams['name'],
                    [
                        "GET"    => $_GET,
                        "POST"   => $_POST,
                        "result" => raypay_get_filled_message( $gatewayParams['failed_massage'], $invoice_id)
                    ], 'Failure' );
            }
            else
            {
                $paymentSuccess = TRUE;
                if ( ! empty( $gatewayParams['Currencies'] ) && $gatewayParams['Currencies'] == 'Toman' )
                {
                    $amount = $verify_amount / 10;
                }
                addInvoicePayment( $orderid, $invoice_id, $amount, 0, $gatewayParams['paymentmethod'] );
                logTransaction( $gatewayParams['name'],
                    [
                        "GET"    => $_GET,
                        "POST"   => $_POST,
                        "result" => raypay_get_filled_message( $gatewayParams['success_massage'], $invoice_id ),
                        "verify_result" => print_r($result, true),
                    ], 'Success' );
            }
    }
    else
    {
        logTransaction($gatewayParams['name'],
            [
                "GET" => $_GET,
                "POST" => $_POST,
                "result" => 'کاربر از انجام تراکنش منصرف شده است'
            ], 'Failure');
    }
}
raypay_end();