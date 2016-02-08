<?php
#ini_set("display_errors", true);
#error_reporting(E_ALL);

if (!defined('_VALID_MOS') && !defined('_JEXEC')) {
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');
}

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentFondy extends vmPSPlugin
{

    // instance of class
    public static $_this = false;


    function __construct(& $subject = null, $config = null)
    {

        if ($subject && $config) {
            parent::__construct($subject, $config);
        }

        $this->_psType = 'payment';
        $this->_configTable = '#__virtuemart_' . $this->_psType . 'methods';
        $this->_configTableFieldName = $this->_psType . '_params';
        $this->_configTableFileName = $this->_psType . 'methods';
        $this->_configTableClassName = 'Table' . ucfirst($this->_psType) . 'methods';


        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());

        $varsToPush = array('payment_logos' => array('', 'char'),
                            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
                            'payment_info' => array('', 'string'),
                            'FONDY_MERCHANT' => array('', 'string'),
                            'FONDY_SECRET_KEY' => array('', 'string'),
                            'status_pending' => array('', 'string'),
                            'status_success' => array('', 'string'),
                            'FONDY_SYSTEM_CURRENCY' => array('', 'string'),
                            'FONDY_COUNTRY' => array('', 'string'),
                            'FONDY_LANGUAGE' => array('', 'string'));

        $res = $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

    }

    /**
     * Create the table for this plugin if it does not yet exist.
     */
    protected function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Standard Table');
    }

    /**
     * Fields to create the payment table
     * @return string SQL Fileds
     */
    function getTableSQLFields()
    {
        $SQLfields = array('id' => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
                           'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
                           'order_number' => 'char(32) DEFAULT NULL',
                           'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
                           'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
                           'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
                           'payment_currency' => 'char(3) ',
                           'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
                           'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
                           'tax_id' => 'smallint(11) DEFAULT NULL');

        return $SQLfields;
    }


    function __getVmPluginMethod($method_id)
    {
        if (!($method = $this->getVmPluginMethod($method_id))) {
            return null;
        } else {
            return $method;
        }
    }

    function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        include_once(dirname(__FILE__) . DS . "/Fondy.cls.php");
        if (!class_exists('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        }

        JFactory::getLanguage()->load($filename = 'com_virtuemart', JPATH_ADMINISTRATOR);
        $vendorId = 0;

        $html = "";

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $this->getPaymentCurrency($method);

        if ($method->FONDY_SYSTEM_CURRENCY == 1) {
            $currencyModel = new VirtueMartModelCurrency();
            $currencyObj = $currencyModel->getCurrency($order['details']['BT']->order_currency);
            $currency = $currencyObj->currency_code_3;
        } else {
            switch ($method->FONDY_COUNTRY) {
                case "RU": $currency = "RUB";
                    break;
                case "EN": $currency = "USD";
                    break;
                default: $currency = "UAH";
                    break;
            }
        }

        list($lang,) = explode('-', JFactory::getLanguage()->getTag());

        $paymentMethodID = $order['details']['BT']->virtuemart_paymentmethod_id;
        $responseUrl = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm=' . $paymentMethodID);
        $callbackUrl = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm=' . $paymentMethodID);

        $user = & $cart->BT;
        $formFields = array('order_id' => $cart->order_number . Fondy::ORDER_SEPARATOR . time(),
                             'merchant_id' => $method->FONDY_MERCHANT,
                             'order_desc' => $cart->order_number,
                             'amount' => Fondy::getAmount($order),
                             'currency' => $currency,
                             'server_callback_url' => $callbackUrl,
                             'response_url' => $responseUrl,
                             'lang' => strtoupper($lang),
                             'sender_email' => $user['email']);

        $formFields['signature'] = Fondy::getSignature($formFields, $method->FONDY_SECRET_KEY);


        $fondyArgsArray = array();
        foreach ($formFields as $key => $value) {
            $fondyArgsArray[] = "<input type='hidden' name='$key' value='$value'/>";
        }

        $html = '	<form action="' . Fondy::URL . '" method="post" id="fondy_payment_form">
  				' . implode('', $fondyArgsArray) .
            '</form>' .
            "<div><img src='https://fondy.eu/img/loader.gif' width='50px' style='margin:20px 20px;'></div>".
            "<script> setTimeout(function() {
                 document.getElementById('fondy_payment_form').submit();
             }, 100);
            </script>";

        // 	2 = don't delete the cart, don't send email and don't redirect
        return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, '');
    }

    function plgVmOnPaymentResponseReceived(&$html) {
        $method = $this->getVmPluginMethod(JRequest::getInt('pm', 0));
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartCart'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');

        // get the correct cart / session
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();

        return true;
    }

    function plgVmOnUserPaymentCancel() {
		
        $data = JRequest::get('get');

        list($order_id,) = explode(Fondy::ORDER_SEPARATOR, $data['order_id']);
        $order = new VirtueMartModelOrders();

        $order_s_id = $order->getOrderIdByOrderNumber($order_id);
        $orderitems = $order->getOrder($order_s_id);

        $method = $this->getVmPluginMethod($orderitems['details']['BT']->virtuemart_paymentmethod_id);
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

        $this->handlePaymentUserCancel($data['oid']);

        return true;
    }

    function plgVmOnPaymentNotification() {
			If (empty($_POST)){
			 $fap = json_decode(file_get_contents("php://input"));
        $_POST=array();
        foreach($fap as $key=>$val)
        {
          $_POST[$key] =  $val ;
        }
		}
		
        $_SERVER['REQUEST_URI']='';
        $_SERVER['SCRIPT_NAME']='';
        $_SERVER['QUERY_STRING']='';
        define('_JEXEC', 1);
        define('DS', DIRECTORY_SEPARATOR);
        $option='com_virtuemart';
        $my_path = dirname(__FILE__);
        $my_path = explode(DS.'plugins',$my_path);
        $my_path = $my_path[0];
        if (file_exists($my_path . '/defines.php')) {
            include_once $my_path . '/defines.php';
        }
        if (!defined('_JDEFINES')) {
            define('JPATH_BASE', $my_path);
            require_once JPATH_BASE.'/includes/defines.php';
        }
        define('JPATH_COMPONENT',				JPATH_BASE . '/components/' . $option);
        define('JPATH_COMPONENT_SITE',			JPATH_SITE . '/components/' . $option);
        define('JPATH_COMPONENT_ADMINISTRATOR',	JPATH_ADMINISTRATOR . '/components/' . $option);
        require_once JPATH_BASE.'/includes/framework.php';
        $app = JFactory::getApplication('site');
        $app->initialise();
        if (!class_exists( 'VmConfig' )) require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart'.DS.'helpers'.DS.'config.php');
        VmConfig::loadConfig();
        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.fphp' );
        if (!class_exists('plgVmPaymentFondy'))
            require(dirname(__FILE__). DS . 'fondy.php');

        require(dirname(__FILE__). DS . 'Fondy.cls.php');
		//print_r ($_POST);die;
        list($order_id,) = explode(Fondy::ORDER_SEPARATOR, $_POST['order_id']);
        $order = new VirtueMartModelOrders();

        $method = new plgVmPaymentFondy();
		
        $order_s_id = $order->getOrderIdByOrderNumber($order_id);
        $orderitems = $order->getOrder($order_s_id);
        $methoditems = $method->__getVmPluginMethod($orderitems['details']['BT']->virtuemart_paymentmethod_id);

        $option  = array(   'merchant_id' => $methoditems->FONDY_MERCHANT,
            'secret_key' =>  $methoditems->FONDY_SECRET_KEY);
		
        $response = Fondy::isPaymentValid($option, $_POST);
		
        if ($response === true) {
			$red = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $paymentMethodID);
			header('Location:'. $red);
            $datetime = date("YmdHis");
            echo "OK";
        } else {
			
            echo "<!-- {$response} -->";
        }

        $orderitems['order_status'] = $methoditems->status_success;
        $orderitems['customer_notified'] = 0;
        $orderitems['virtuemart_order_id'] = $order_s_id;
        $orderitems['comments'] = 'Fondy ID: '.$order_id. " Ref ID : ". $_POST['payment_id'];
        $order->updateStatusForOneOrder($order_s_id, $orderitems, true);
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @author: Valerie Isaksen
     *
     * @param $cart_prices : cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        return true;
    }

    /*
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart : the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
    {
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
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
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

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
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
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
    {
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
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }


    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

}

// No closing tag
