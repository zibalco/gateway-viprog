<?php
$pluginData['zibal']['type'] 						= 'payment';
$pluginData['zibal']['name'] 						= 'پرداخت آنلاین با <a href="http://zibal.ir" target="_blank">زیبال</a>';
$pluginData['zibal']['uniq'] 						= 'zibal';
$pluginData['zibal']['note'] 						= 'zibal';
$pluginData['zibal']['description'] 				= '';
$pluginData['zibal']['author']['name'] 				= 'تیم فنی زیبال';
$pluginData['zibal']['author']['url'] 				= 'http://zibal.ir';
$pluginData['zibal']['author']['email'] 			= 'info@zibal.ir';
$pluginData['zibal']['field']['config'][1]['title'] 	= 'لطفا مرچنت کد خود را در فیلد زیر وارد نمایید ';
$pluginData['zibal']['field']['config'][1]['name'] 	= 'merchant';

function gateway__zibal($data)
{
	global $db;
	
	$MerchantID 			= $data['merchant'];
	$Price 					= $data['amount'];
	$orderId 			= $data['invoice_id'];
	$InvoiceNumber 			= $data['invoice_id'];
	$CallbackURL 			= $data['callback'];

	$parameters = array(
   		 "merchant"=> $MerchantID,//required
    		"callbackUrl"=> $CallbackURL,//required
    		"amount"=> $Price,//required
    		"orderId"=> $InvoiceNumber,
		);

	$result = postToZibal('request', $parameters);

	if ($result->result == 100){
		
		$au = $result->trackId;
		$update['payment_rand'] = $au;
		$sql = $db->prepare("UPDATE `payment` SET `payment_rand` = ? WHERE `payment_rand` = ? LIMIT 1");
		$sql->execute(array (
			$update['payment_rand'],
			$invoice_id
		));

		redirect_to("https://gateway.zibal.ir/start/$au");
	} else {
		$error = $result->result;
		$data['title'] 	= 'خطای سیستم';
		$data['message'] 	= '<font color="red">در ارتباط با درگاه زیبال مشکلی به وجود آمده است. لطفا مطمئن شوید کد مرچنت کد خود را به درستی در قسمت مدیریت وارد کرده اید.</font> شماره خطا: '.$error.'<br /><a href="index.php" class="button">بازگشت</a>';
		return $data;
		exit;
	}
}

function callback__zibal($data)
{
	global $db,$get;
	
	$MerchantID 		= $data['merchant'];
	$trackId 			= $_POST['trackId'];
	$InvoiceNumber 		= $_POST['orderId'];
	
	$sql 				= 'SELECT * FROM `payment` WHERE `payment_rand` = "'.$InvoiceNumber.'" LIMIT 1;';
	$payment 			= $db->query($sql)->fetch();

	$Price 				= $payment['payment_amount'];

	if ($_POST['success'] == 1) {
		
		 //start verfication
   		 $parameters = array(
        		"merchant" => $MerchantID,//required
        		"trackId" => $trackId,//required

    		);

    		$response = postToZibal('verify', $parameters);

		if ($result->result == 100) {
			$output['status']		= 1;
			$output['res_num']	= $trackId;
			$output['ref_num']	= $result->refNumber;
			$output['payment_id']	= $payment['payment_id'];
		} else {
			$output['status']		= 0;
			$output['message'] 	= 'خطا در پرداخت, کد خطا : '. $result->status;
		}
	} else {
		$output['status']		= 0;
		$output['message'] 	= 'تراکنش لغو شده است';
	}
	return $output;
}

/**
 * connects to zibal's rest api
 * @param $path
 * @param $parameters
 * @return stdClass
 */
function postToZibal($path, $parameters)
{
    $url = 'https://gateway.zibal.ir/'.$path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response  = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}
