<?php// Version 1.00// Author: CloudRebels.com, M. Kimmel, 2012// no direct accessdefined('_JEXEC') or die('Restricted access');jimport('joomla.application.component.controller');class PaymillapiController extends JController {    //standard function to display view    function display() {        parent::display();    }	function chkPayment() {		$db =& JFactory::getDBO();		$vm_pid = $_POST["virtuemart_paymentmethod_id"];		$amount = floatval($_POST["pm_amount"]) * 100;				$q = "SELECT payment_params FROM #__virtuemart_paymentmethods WHERE virtuemart_paymentmethod_id = " .$vm_pid;		$db->setQuery( $q );		$vm_params = $db->loadResult();				//GET PRIVATE KEY		$key = explode("|", $vm_params);		$pat = '/\"([^\"]*?)\"/';		preg_match($pat, $key[11], $matches);		$private_key = $matches[1];		//define vars		define('API_HOST', 'https://api.paymill.com/v2/');		define('API_KEY', $private_key);		set_include_path(implode(PATH_SEPARATOR, array( realpath(realpath(dirname(__FILE__)) . '/lib'), get_include_path(), )));			$token = $_POST['paymillToken'];		if ($token) {			require "components/com_paymillapi/lib/Services/Paymill/Transactions.php";					$transactionsObject = new Services_Paymill_Transactions(API_KEY, API_HOST);			$params = array(				'amount' => $amount,				'currency' => 'eur',				'token' => $token,				'description' => $_POST['pm_email']			);			$transaction = $transactionsObject->create($params);						$email = $transaction['description'];			$status = $transaction['status'];						$q = "INSERT INTO #__paymill (token, status, email, created) VALUES ('".$token."', '".$status."', '".$email."', NOW())";	        $db->setQuery( $q );	        $db->query();						return var_dump($transaction, true);		}	}	function saveToken() {		$db =& JFactory::getDBO();		$token = $_GET['token'];		if ($token) {			$q = "INSERT INTO #__paymill (token, created) VALUES ('".$token."', NOW())";	        $db->setQuery( $q );	        $db->query();			$session =& JFactory::getSession();			$session->set( 'pm_token', '' );			$session->set( 'pm_token', $token );						$testtoken = $session->get("pm_token");			error_log("Bah: ".$testtoken);			//return false;		}	}}?>