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

if (!defined("WHMCS")) die("This file cannot be accessed directly");

/**
 * @return array
 */
function raypay_config()
{
    return [
        "FriendlyName" => [
            "Type" => "System",
            "Value" => "RayPay"
        ],
        "Currencies" => [
            "FriendlyName" => "واحد پولی",
            "Type" => "dropdown",
            "Options" => "Rial,Toman"
        ],
        "user_id" => [
            "FriendlyName" => "شناسه كاربري",
            "Type" => "text"
        ],
        "marketing_id" => [
            "FriendlyName" => "شناسه كسب و كار",
            "Type" => "text"
        ],
        "sandbox" => [
            "FriendlyName" => "فعالسازي SandBox",
            "Type" => "yesno"
        ],
        "success_massage" => [
            "FriendlyName" => "پیام پرداخت موفق",
            "Type" => "textarea",
            "Value" => "پرداخت شما با موفقیت انجام شد. شناسه پرداخت: {invoice_id}",
            "Description" => "متن پیامی که می خواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کد {invoice_id} برای نمایش شناسه پرداخت رای پی استفاده نمایید."
        ],
        "failed_massage" => [
            "FriendlyName" => "پیام پرداخت ناموفق",
            "Type" => "textarea",
            "Value" => "پرداخت شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.",
            "Description" => "متن پیامی که می خواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کد {invoice_id} برای نمایش شناسه پرداخت رای پی استفاده نمایید."
        ]
    ];
}

/**
 * @param $params
 * @return string
 */
function raypay_link($params)
{
    $systemurl = $params['systemurl'];
    $user_id = $params['user_id'];
    $marketing_id = $params['marketing_id'];
    $sandbox = $params['sandbox'] == 'on' ? true : false;
    $amount = strval(round($params['amount'], 0));
    $moduleName = $params['paymentmethod'];
    if (!empty($params['Currencies']) && $params['Currencies'] == "Toman") {
        $amount *= 10;
    }

    // Customer information
    $client = $params['clientdetails'];
    $name = $client['firstname'] . ' ' .  $client['lastname'];
    $mail = $client['email'];
    $phone = $client['phonenumber'];
    $invoice_id             = round(microtime(true) * 1000);

    $desc = 'پرداخت whmcs با شماره سفارش  ' . $params['invoiceid'];

    // Remove any trailing slashes and then add a new one.
    // WHMCS version 7 contains a trailing slash but version 6
    // does not contain any one. We remove and then add a new trailing slash for
    // the compatibility of the two versions.
    $systemurl = rtrim($systemurl, '/') . '/';

    $callback = $systemurl . 'modules/gateways/callback/' . $moduleName . '.php?order_id=' .$params['invoiceid'] . '&invoice_id=' . $invoice_id ;
    $url = 'https://api.raypay.ir/raypay/api/v1/payment/pay';

    if (empty($amount)) {
        return 'واحد پول انتخاب شده پشتیبانی نمی شود.';
    }

    $data = array(
        'factorNumber' => strval($params['invoiceid']),
        'userID' => $user_id,
        'marketingID' => $marketing_id,
        'invoiceID'    => strval($invoice_id),
        'amount' => $amount,
        'fullName' => $name,
        'mobile' => $phone,
        'email' => $mail,
        'desc' => $desc,
        'redirectUrl' => $callback,
        'enableSandBox' => $sandbox
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json',));

    $result = curl_exec($ch);
    $result = json_decode($result);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_status != 200) {
        $output = sprintf('<p>خطا هنگام ایجاد تراکنش. وضعیت خطا: %s</p>', $http_status);
        $output .= sprintf('<p style="unicode-bidi: plaintext;">پیام خطا: %s</p>', $result->Message);
        $output .= sprintf('<p>کد خطا: %s </p>', $result->StatusCode);
        return $output;
    } else {
        $logo_link = $systemurl . 'modules/gateways/raypay/logo.svg';
        $token = $result->Data;
        $link='https://my.raypay.ir/ipg?token=' . $token;
        $output = '<a  href="' . $link . '">
            <button type="submit" name="pay" value="پرداخت" style="direction: rtl;"><img src="' . $logo_link . '" width="70px">پرداخت امن با رای پی</button></a>
            <p style="margin-top: 10px;">پرداخت امن به وسیله کلیه کارتهای عضو شتاب با درگاه پرداخت آیدی پی</p>';

        if($_GET['paymentfailed']){
            $output .=
                '<div class="alert alert-danger raypay-message">'. str_replace(["{invoice_id}"], [$invoice_id], $params['failed_massage']) .'</div>
                <style>
                .raypay-message {
                    width: calc(100vw - 187px);
                    max-width: 710px;
                    margin: 15px auto 0;
                }
                @media (max-width: 767px) {
                    .raypay-message{
                        width: calc(100vw - 137px);
                    }
                }
                .panel.panel-danger {
                    display: none;
                }
                </style>';

            $output = '<div style="direction: rtl;">'. $output .'</div>';
        }
        return $output;
    }
}