<?php

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 * @version $Id: paymill.php,v 1.0
 *
 * @author Max Kimmel
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentPaymill extends vmPSPlugin {

    // instance of class
    public static $_this = false;

    function __construct(& $subject, $config) {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());

        $varsToPush = array(
            'payment_uid' => array('', 'char'),
            'payment_pid' => array('', 'char'),
            'payment_pas' => array('', 'char'),
            'payment_npas' => array('', 'char'),
            'payment_info' => array('', 'char'),
            'debug' => array(0, 'int'),
            'status_pending' => array('', 'char'),
            'status_success' => array('', 'char'),
            'status_canceled' => array('', 'char'),
        );

        //$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$varsToPush = $this->getVarsToPush ();

		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
    }

    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Paymill Table');
    }

    function getTableSQLFields() {

        $SQLfields = array(
            'id' 										 => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' 						 => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' 								 => 'char(32) DEFAULT NULL',
            'virtuemart_paymentmethod_id' 				 => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' 								 => 'char(255) NOT NULL DEFAULT \'\' ',
            'payment_order_total' 						 => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'payment_currency' 							 => 'char(3) ',
            'tax_id'                                     => 'smallint(1)',
			'paymill_payment_id'						 => 'varchar(64)',
			'paymill_transaction_id'				     => 'varchar(64)',
			'paymill_transaction_status'		         => 'varchar(32)',
			'paymill_client_email'					     => 'varchar(64)',
			'paymill_transaction_object'			     => 'text'
        );
        return $SQLfields;
    }


    function plgVmConfirmedOrder($cart, $order) {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $session = JFactory::getSession();
        $return_context = $session->getId();
        $this->_debug = $method->debug;
        $this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

        //$usr = & JFactory::getUser();
        $html = '';

        $usrBT = $order['details']['BT'];
        $address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

        $vendorModel = new VirtueMartModelVendor();
        $vendorModel->setId(1);
        $vendor = $vendorModel->getVendor();
        $this->getPaymentCurrency($method);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = &JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();

        //$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        //$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total,false), 2);
        $totalInPaymentCurrency = round($order['details']['BT']->order_total, 2);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);

        $user_title = $address->title;
        $user_email = $address->email;
        $user_name = $address->first_name . ' ' . $address->last_name;
        $user_city = $address->city;
        $user_address = $address->address_1;
        $user_zip = $address->zip;
        $user_country = ShopFunctions::getCountryByID($address->virtuemart_country_id, 'country_3_code');

        $msg_1 = $user_name . " Kd-nr " . $usrBT->virtuemart_user_id;
        $msg_2 = "Bestellnr " . $order['details']['BT']->order_number;

		$cont=$method->payment_uid."|".$method->payment_pid."|||||".$totalInPaymentCurrency."|".$currency_code_3."|".$msg_1."|".$msg_2."|".$order['details']['BT']->order_number."|".$order['details']['BT']->virtuemart_paymentmethod_id."|VM v2.1||||".$method->payment_pas;
		$hash = md5($cont);

		$html .= '<div style="text-align: left; margin-top: 25px; margin-bottom: 25px;">';
		$html .= 'Ihre Bestellung ist bei uns eingegangen und wird umgehend von uns bearbeitet.';
		$html .= '</div>';
           
        // Prepare data that should be stored in the database
        $dbValues = array();
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['payment_currency'] = $method->payment_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $this->storePSPluginInternalData($dbValues);

		$new_status = 'C';
        return $this->processConfirmedOrderPaymentResponse(1, $cart, $order, $html, $dbValues['payment_name'], $new_status);
    }

    function plgVmOnPaymentResponseReceived(&$html) {

        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);

        $vendorId = 0;
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

	if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $order_number = JRequest::getVar('on');
        if (!$order_number)
            return false;
        $db = JFactory::getDBO();
        $query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->_tablename . " WHERE  `order_number`= '" . $order_number . "'";

        $db->setQuery($query);
        $virtuemart_order_id = $db->loadResult();

        if (!$virtuemart_order_id) {
            return null;
        }

        if ($virtuemart_order_id) {
                if (!class_exists('VirtueMartCart'))
                        require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
                // get the correct cart / session
                $cart = VirtueMartCart::getCart();

                // send the email ONLY if payment has been accepted
                if (!class_exists('VirtueMartModelOrders'))
                        require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
                $order = new VirtueMartModelOrders();
                $orderitems = $order->getOrder($virtuemart_order_id);
                //$cart->sentOrderConfirmedEmail($orderitems);
                $cart->emptyCart();
        }
        return true;
    }

	function _getHtmlRow($label, $value) {
		return '<tr><td>' . $label . '</td><td>' . $value . '</td></tr>';
	}

    function plgVmOnUserPaymentCancel() {

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $order_number = JRequest::getVar('on');
        if (!$order_number)
            return false;
        $db = JFactory::getDBO();
        $query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->_tablename . " WHERE  `order_number`= '" . $order_number . "'";

        $db->setQuery($query);
        $virtuemart_order_id = $db->loadResult();

        if (!$virtuemart_order_id) {
            return null;
        }
        $this->handlePaymentUserCancel($virtuemart_order_id);

        //JRequest::setVar('paymentResponse', $returnValue);
        return true;
    }
	// MAX: PAYMENT WAS OK, THEN SAVE TO DB AND NOTIFY ADMIN
    function plgVmOnPaymentNotification() {

        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
		$order_number = JRequest::getInt('on', 0);

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
        $this->logInfo('plgVmOnPaymentNotification: virtuemart_order_id  found ' . $virtuemart_order_id, 'message');

        if (!$virtuemart_order_id) {
            $this->_debug = true; // force debug here
            $this->logInfo('plgVmOnPaymentNotification: virtuemart_order_id not found ', 'ERROR');
            // send an email to admin, and ofc not update the order status: exit  is fine
            //$this->sendEmailToVendorAndAdmins(JText::_('VMPAYMENT_PAYMILL_ERROR_EMAIL_SUBJECT'), JText::_('VMPAYMENT_PAYMILL_UNKNOW_ORDER_ID'));
            exit;
        }
        $vendorId = 0;
        
        $payment = $this->getDataByOrderId($virtuemart_order_id);
        
        $method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->_debug = $method->debug;
        if (!$payment) {
            $this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
            return null;
        }

        $new_status = 'C';
		$new_comment = 'Paymill - Geld ist eingegangen.';

        $this->logInfo('plgVmOnPaymentNotification return new_status:' . $new_status, 'message');

        if ($virtuemart_order_id) {
            // send the email only if payment has been accepted
            if (!class_exists('VirtueMartModelOrders'))
                require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
            $modelOrder = new VirtueMartModelOrders();
            $order['order_status'] = $new_status;
            $order['comments'] = $new_comment;           
            $order['virtuemart_order_id'] = $virtuemart_order_id;
            $order['customer_notified'] = 0;

        	// TOKEN FROM SESSION
        	$session = JFactory::getSession();
			$pm_token = $session->get('pm_token');

			//define NEW PM_VARS
			define('API_HOST', 'https://api.paymill.de/v1/');
			define('API_KEY', $method->private_key);
			
			if ($pm_token) {
				require "components/com_paymillapi/lib/Services/Paymill/Transactions.php";
			
				$transactionsObject = new Services_Paymill_Transactions(API_KEY, API_HOST);
				$params = array(
					'amount' => $totalInPaymentCurrency * 100,
					'currency' => 'eur',
					'token' => $pm_token,
					'description' => $address->email
				);
				$transaction = $transactionsObject->create($params);
				
				$pm_status = $transaction['status'];
				
				$q = "UPDATE #__paymill SET status = '".$pm_status."', email = '".$address->email."' WHERE token = '" .$pm_token. "'";
		        $db->setQuery( $q );
		        $db->query();
			
				$new_status = 'C';
				$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);		
			}
			else {
				echo "Ihre Kreditkartenzahlung war leider fehlerhaft. Bitte überprüfen Sie Ihre Eingabe.<br /><br /><a href='".JURI::root()."/component/virtuemart/cart/editpayment?Itemid=0'>Zurück zur Bezahlung</a>";
			}
			// END NEW PM_VARS
			
            //$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
        }
        return true;
    }

    /**
     * Display stored payment data for an order
     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {

        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return null; // Another method was selected, do nothing
        }

        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` '
                . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);
        if (!($paymentTable = $db->loadObject())) {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        $this->getPaymentCurrency($paymentTable);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $paymentTable->payment_currency . '" ';
        $db = &JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();
        $html = '<table class="adminlist">' . "\n";
        $html .=$this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('PAYMYILL_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('PAYMILL_PAYMENT_TOTAL_CURRENCY', round($paymentTable->payment_order_total, 2).' '.$currency_code_3);
        $code = "paymill_";
        foreach ($paymentTable as $key => $value) {
            if (substr($key, 0, strlen($code)) == $code) {
                $html .= $this->getHtmlRowBE($key, $value);
            }
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    /**
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
		if ($this->getPluginMethods ($cart->vendorId) === 0) {
			if (empty($this->_name)) {
				$app = JFactory::getApplication ();
				$app->enqueueMessage (JText::_ ('COM_VIRTUEMART_CART_NO_' . strtoupper ($this->_psType)));
				return FALSE;
			} else {
				return FALSE;
			}
		}
        return $this->OnSelectCheck($cart);
		
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Max Kimmel
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		if ($this->getPluginMethods ($cart->vendorId) === 0) {
			if (empty($this->_name)) {
				$app = JFactory::getApplication ();
				$app->enqueueMessage (JText::_ ('COM_VIRTUEMART_CART_NO_' . strtoupper ($this->_psType)));
				return FALSE;
			} else {
				return FALSE;
			}
		}
		JFactory::getLanguage ()->load ('com_virtuemart');
		//MAX: use loop if payment method is used multiple times (no limitation by VM possible at the moment)
		foreach ($this->methods as $method) {
			if ($this->checkConditions ($cart, $method, $cart->pricesUnformatted)) {
				$methodSalesPrice = $this->calculateSalesPrice ($cart, $method, $cart->pricesUnformatted);
				//$method->$method_name = $this->renderPluginName ($method);
				$html = $this->getPluginHtml ($method, $selected, $methodSalesPrice);

				$html .= '
					<script type="text/javascript" src="https://bridge.paymill.de/"></script>
					<script type="text/javascript" src="'.JURI::root().'plugins/vmpayment/paymill/assets/js/paymill.js"></script>
					<script type="text/javascript">
						PAYMILL_PUBLIC_KEY = "'.$method->public_key.'";
						checkBridge();
					</script>
					<br />
					<span class="vmpayment_cardinfo">' . JText::_ ('VMPAYMENT_PAYMILL_COMPLETE_FORM').'
		    		<div id="iframeerror"></div>

		    		<table border="0" cellspacing="0" cellpadding="2" width="100%">
		    			<tr valign="top">
					        <td nowrap width="10%" align="right">
					        	<label for="cardholdername">' . JText::_ ('VMPAYMENT_PAYMILL_CREDITCARDOWNER') . '</label>
					        </td>
					        <td>
					        	<input type="text" id="cardholdername" />
					        </td>
		    			</tr>
		    			<tr valign="top">
					        <td nowrap width="10%" align="right">
					        	<label for="cardnumber">' . JText::_ ('VMPAYMENT_PAYMILL_CREDITCARDNUMBER') . '</label>
					        </td>
					        <td>
					        	<input type="text" id="cardnumber" />
					        </td>
		    			</tr>
					    <tr valign="top">
					        <td nowrap width="10%" align="right">
					        	<label for="cardCvc">' . JText::_ ('VMPAYMENT_PAYMILL_CVC') . '</label>
					        </td>
					        <td>
			        			<input type="text" id="cardCvc" maxlength="4" size="5" />
								<span class="hasTip" title="' . JText::sprintf ("VMPAYMENT_PAYMILL_WHATISCVC_TOOLTIP", $cvc_images) . ' ">' .
									JText::_ ('VMPAYMENT_PAYMILL_WHATISCVC') . '
								</span>
						    </td>
		    			</tr>
		    			<tr>
		        			<td nowrap width="10%" align="right">' . JText::_ ('VMPAYMENT_PAYMILL_EXDATE') . '</td>
		        			<td> ' .shopfunctions::listMonths ('cardExpMonth', $this->_cc_expire_month). ' / ' .shopfunctions::listYears ('cardExpYear', $this->_cc_expire_year, NULL, 2022). '</td>
		        		</tr>
		        	</table>
		        	<input type="hidden" id="pm_amount" name="pm_amount" value="'.$cart->pricesUnformatted["billTotal"].'" />
		        	<input type="text" id="paymillTokenField" name="paymillToken" />
		        	<input type="hidden" id="pm_email" name="pm_email" />
		        	<input type="hidden" id="root_url" value="'.JURI::root().'" />
		        	<input type="hidden" id="formerror" />
		        	<input type="hidden" name="vm_paymentmethod_id" id="vm_paymentmethod_id" value="'.$method->virtuemart_paymentmethod_id.'" />

		        	<div id="loader" style="display: none">'.JText::_ ('VMPAYMENT_PAYMILL_PAYMENT_IS_PROCEEDED').'...</div>
		        	<div id="paymentErrors"></div>
		        	<div id="result" style="display: none"><span style="color: #009900">Daten erfolgreich eingegeben!</span></div>
		        	<img id="loadergif" src="plugins/vmpayment/paymill/assets/img/loader2.gif" style="display: none; margin: 0px 10px" />
		        	</span>';
				$htmla[] = $html;
			}
		}
		$htmlIn[] = $htmla;
        //return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @author Max Milbers

      public function plgVmOnCheckoutCheckDataPayment($psType, VirtueMartCart $cart) {
      return null;
      }
     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk

      public function plgVmOnUpdateOrderPayment(  $_formData) {
      return null;
      }
     */
    /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk

      public function plgVmOnUpdateOrderLine(  $_formData) {
      return null;
      }
     */
    /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk

      public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
      return null;
      }
     */

    /*
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk
     */

    public function plgVmOnShowOrderLineFE($_orderId, $_lineId) {
        return null;
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) {

// 		$params = new JParameter($payment->payment_params);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
                OR
                ($method->min_amount <= $amount AND ($method->max_amount == 0) ));
        if (!$amount_cond) {
            return false;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id']))
            $address['virtuemart_country_id'] = 0;
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            return true;
        }

        return false;
    }

}

// No closing tag
