<?php
defined("_VALID_PHP") or die('Direct access to this location is not allowed.');

$message = '';
$cartrow = $content->getCartContent();
$totalrow = $content->getCartTotal();
$discount = $totalrow->total - $totalrow->coupon;
$session_id = md5(time());

$merchant_id = $row->extra;
$amount = ($row->extra2 == 'IRT') ? intval($discount * 10) : intval($discount);

$coded_data = "pl_id={$row->id}&amount={$discount}&custom=" . $user->uid . '_' . $user->sesid;
$coded_data = base64_encode($coded_data);

$callback_url = urlencode( SITEURL . "/gateways/" . $row->dir . "/ipn.php?data=$coded_data" );

$ch = curl_init();
curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/create/payment' );
curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&amount=$amount&callback_url=$callback_url" );
curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
$response = curl_exec( $ch );
curl_close( $ch );
$result = json_decode( $response );

$failed = false;
if (!$result->status) {
    $message = $result->message;
    $failed = true;
}
?>
<div class="wojo basic button">
    <form action="<?php echo $result->payment_url; ?>" method="get" id="pl_form" name="pl_form">
        <?php if (!$failed) { ?>
            <input type="submit" style="vertical-align: middle; border: 0; width: 160px; margin-right: 10px"
                   name="submit" title="پرداخت توسط یک دو سه پی" value=" پرداخت آنلاین "
                   onclick="document.mb_form.submit();"/>
        <?php } ?>
        <?php
        if ($failed)
            echo "<strong>خطا</strong> " . $message . "<br />";
        ?>
    </form>
</div>