<?php

define("_VALID_PHP", true);
define("_PIPN", true);

require_once("../../init.php");
require_once("../../lib/class_filter.php");
$r_fields = array(
    'State',
    'RefNum'
);

$plmessage = '';
$failed = false;
foreach ($r_fields as $f)
    if (!isset($_REQUEST[$f]))
        die('مشخصات پرداخت به درستی دریافت نگردید');
if (!isset($_GET['data']))
    die();
$data = base64_decode($_GET['data']);

parse_str($data, $data);

$row = Core::getRowById("gateways", $data['pl_id']);

$merchant_id = $row->extra;

if (!isset($_REQUEST['State']) || !isset($_REQUEST['RefNum'])) {
    $plmessage = 'اطلاعات ارسالی از یک دو سه پی به درستی دریافت نشد';
    $failed = true;
}

$State = $_REQUEST['State'];
$RefNum = $_REQUEST['RefNum'];
if ($State == 'OK') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://123pay.ir/api/v1/verify/payment');
    curl_setopt($ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&RefNum=$RefNum");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response);
    if ($result->status) {
        $plmessage = "پرداخت شما با موفقیت دریافت شد. شناسه تراکنش شما: {$RefNum}";
        $failed = false;
    } else {
        $plmessage = $result->message;
        $failed = true;
    }
} else {
    $plmessage = 'تراکنش ناموفق';
    $failed = true;
}

list($user_id, $sesid) = explode("_", $data['custom']);

$cartrow = $content->getCartContent($sesid);
$totalrow = $content->getCartTotal($sesid);
$gross = $totalrow->total - $totalrow->coupon;

$mb_gross = $data['amount'];
$txn_id = $RefNum;

$getxn_id = $core->verifyTxnId($txn_id);
if (!$failed) {
    if ($mb_gross == $gross && $getxn_id == true) {
        if ($cartrow) {
            foreach ($cartrow as $crow) {
                $data = array(
                    'txn_id' => sanitize($txn_id),
                    'pid' => $crow->pid,
                    'uid' => intval($user_id),
                    'downloads' => 0,
                    'file_date' => time(),
                    'ip' => sanitize($_SERVER['REMOTE_ADDR']),
                    'created' => "NOW()",
                    'payer_email' => sanitize($payer_email),
                    'payer_status' => 'verified',
                    'item_qty' => $crow->total,
                    'price' => $crow->total * $crow->price,
                    'currency' => 'IRR',
                    'pp' => "_123pay",
                    'status' => 1,
                    'active' => 1
                );
                $db->insert("transactions", $data);
            }

            unset($crow);
        }
        require_once("../../lib/class_mailer.php");
        $row2 = Core::getRowById(Content::eTable, 5);
        $usr = Core::getRowById(Users::uTable, $user->uid);

        $body = str_replace(array(
            '[USERNAME]',
            '[STATUS]',
            '[TOTAL]',
            '[PP]',
            '[IP]'), array(
            $usr->username,
            "Completed",
            $core->formatMoney($gross),
            "123PAY",
            $_SERVER['REMOTE_ADDR']), $row2->body);

        $newbody = cleanOut($body);

        $mailer = Mailer::sendMail();
        $message = Swift_Message::newInstance()
            ->setSubject($row2->subject)
            ->setTo(array($core->site_email => $core->site_name))
            ->setFrom(array($core->site_email => $core->site_name))
            ->setBody($newbody, 'text/html');

        $mailer->send($message);

        $row3 = Core::getRowById(Content::eTable, 8);
        $val = '
		  <table border="0" cellpadding="4" cellspacing="2">';
        $val .= '
			<thead>
			  <tr>
				<td width="20"><strong>#</strong></td>
				<td class="header">' . Lang::$word->PRD_NAME . '</td>
				<td class="header">' . Lang::$word->PRD_PRICE . '</td>
				<td class="header">' . Lang::$word->TXN_QTY . '</td>
				<td class="header">' . Lang::$word->CKO_TPRICE . '</td>
			  </tr>
			</thead>
			<tbody>';
        $i = 0;
        foreach ($cartrow as $ccrow) {
            $i++;
            $val .= '
				<tr>
				  <td style="border-bottom-width:1px; border-bottom-color:#bbb; border-bottom-style:dashed">' . $i . '.</td>
				  <td style="border-bottom-width:1px; border-bottom-color:#bbb; border-bottom-style:dashed">' . sanitize($ccrow->title, 30, false) . '</td>
				  <td style="border-bottom-width:1px; border-bottom-color:#bbb; border-bottom-style:dashed">' . $core->formatMoney($ccrow->price) . '</td>
				  <td align="center" style="border-bottom-width:1px; border-bottom-color:#bbb; border-bottom-style:dashed">' . $ccrow->total . '</td>
				  <td align="right" style="border-bottom-width:1px; border-bottom-color:#bbb; border-bottom-style:dashed">' . $core->formatMoney($ccrow->total * $ccrow->price) . '</td>
				</tr>';
        }
        unset($ccrow);
        $val .= '
			<tr>
			  <td colspan="4" align="right" valign="top" style="border-bottom-width:1px; border-bottom-color:#bbb; border-bottom-style:dashed"><strong>';
        if ($totalrow->coupon != 0) {
            $val .= 'Discount:<br />';
        }
        $val .= 'Sub Total:<br />
				</strong></td>
			  <td align="right" valign="top" style="border-bottom-width:1px; border-bottom-color:#bbb; border-bottom-style:dashed"><strong>';
        if ($totalrow->coupon != 0) {
            $val .= '- ' . $core->formatMoney($totalrow->coupon) . '<br />';
        }
        $val .= $core->formatMoney($gross) . '<br />
				</strong>';
        $val .= ' </td>
			</tr>
			<tr>
			  <td colspan="4" align="right" valign="top"><strong style="color:#F00">Grand Total:</strong></td>
			  <td align="right" valign="top"><strong style="color:#F00">' . $core->formatMoney($gross) . '</strong></td>
			</tr>
			  </tbody>
		  </table>';

        $body3 = str_replace(array('[USERNAME]', '[ITEMS]', '[SITENAME]', '[URL]'),
            array($usr->username, $val, $core->site_url, $core->site_name), $row3->body);

        $newbody2 = cleanOut($body3);

        $mailer2 = Mailer::sendMail();
        $message2 = Swift_Message::newInstance()
            ->setSubject($row3->subject)
            ->setTo(array($usr->email => $usr->username))
            ->setFrom(array($core->site_email => $core->site_name))
            ->setBody($newbody2, 'text/html');

        $mailer2->send($message2);

        $db->delete("cart", "user_id='" . $sesid . "'");
        $db->delete("extras", "user_id='" . $sesid . "'");
        $db->delete("recent", "user_id='" . $sesid . "'");

    }
    header("Location: " . SITEURL . "/account.php");
} else {
    require_once("../../lib/class_mailer.php");
    $row = $core->getRowById("email_templates", 6);
    $usr = $core->getRowById("users", $user_id);


    $body = str_replace(array('[USERNAME]', '[STATUS]', '[TOTAL]', '[PP]', '[IP]'),
        array($usr->username, "Failed", $core->formatMoney($gross), "_123pay", $_SERVER['REMOTE_ADDR']), $row->body);

    $newbody = cleanOut($body);

    $mailer = Mailer::sendMail();
    $message = Swift_Message::newInstance()
        ->setSubject($row->subject)
        ->setTo(array($core->site_email => $core->site_name))
        ->setFrom(array($core->site_email => $core->site_name))
        ->setBody($newbody, 'text/html');

    $mailer->send($message);

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf8"/>
        <title>خطا در درگاه یک دو سه پی</title>
    </head>
    <body>
    <?php echo $plmessage; ?>
    </body>
    </html>
    <?php
}