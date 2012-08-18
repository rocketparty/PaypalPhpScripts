<?php
/**
 * Base class for implementing the Paypal Adaptive API gateway protocol.
 *
 * This abstract subclass of PaymentGateway implements the Paypal adaptive API
 *
 * @author iZAP Team <support@izap.in>
 * @see PaypalAdaptiveBasic
 *
 */
abstract class PaypalAdaptiveGateway extends PaymentGateway {

  
//------------------------------------
// PayPal API Credentials
// Replace <API_USERNAME> with your API Username
// Replace <API_PASSWORD> with your API Password
// Replace <API_SIGNATURE> with your Signature
//------------------------------------
public $API_Endpoint;
  spublic $API_UserName;
  public $API_Password;
  public $API_Signature;
  
  // Replace with actual account of receiver
  public $Paypal_receiver_accId;
  public $Env;
  
  // enable or disable test mode
  public $testMode = 'TRUE';
  public $API_AppID;
  public $USE_PROXY;
  public $PROXY_PORT;
  public $PROXY_HOST;

  // Request specific required fields
  protected $actionType;
  // TODO - Return url, will be used to redirect if payment cancelled by user or declined by system.
  // TODO - If you are not executing the Pay call for a preapproval,
  //        then you must set a valid cancelUrl for the web approval flow
  //        that immediately follows this Pay call
  public $cancelUrl;
  // TODO -  Return url, will be used to redirect after payment success.
  // TODO - If you are not executing the Pay call for a preapproval,
  //        then you must set a valid returnUrl for the web approval flow
  //        that immediately follows this Pay call
  public $returnUrl;
  public $currencyCode;

  // A basic payment has 1 receiver
  // TODO - specify the receiver email
  protected $receiverEmailArray;

  // TODO - specify the receiver amount as the amount of money, for example, '5' or '5.55'
  protected $receiverAmountArray;

  // for basic payment, no primary indicators are needed, so set empty array
  protected $receiverPrimaryArray;

  // TODO - Set invoiceId to uniquely identify the transaction associated with the receiver
  //		  You can set this to the same value as trackingId if you wish
  protected $receiverInvoiceIdArray;

  // Request specific optional or conditionally required fields
  //   Provide a value for each field that you want to include in the request, if left as an empty string the field will not be passed in the request
  // TODO - If you are executing the Pay call against a preapprovalKey, you should set senderEmail
  //        It is not required if the web approval flow immediately follows this Pay call
  protected $feesPayer;
  public $ipnNotificationUrl;
  
  // maxlength is 1000 characters
  protected $memo;
  
  // TODO - If you are executing the Pay call against an existing preapproval
  //        the requires a pin, then you must set this
  protected $pin;
  
  // TODO - If you are executing the Pay call against an existing preapproval, set the preapprovalKey here
  protected $preapprovalKey;
  
  // Do not specify for basic payment
  protected $reverseAllParallelPaymentsOnError;

  // generateTrackingID function
  protected $trackingId	;

  // properties to be used in ipn validation
  protected $last_error;
  protected $ipn_response;
  protected $ipn_log;
  protected $ipn_data;
  protected $ipn_posted_vars;
  protected $ipn_log_file;
  protected $ipn_response_text;
  
  public function initialize($anAction) {
      parent::initialize($anAction);
      
      $this->USE_PROXY  = false;
      $this->PROXY_HOST = "127.0.0.1";
      $this->PROXY_PORT = "808";

      if($this->testMode == 'TRUE') {
          $this->API_AppID  = "";
          $this->Env            = "sandbox";
          $this->API_Endpoint   = "";
          //$this->API_UserName   = "";
          //$this->API_Password   = "";
          //$this->API_Signature  = "";
          //$this->Paypal_receiver_accId  = "";
      }
      else {
          $this->API_AppID  = '';
          $this->API_Endpoint   = "";  
          //$this->API_UserName   = "";
          //$this->API_Password   = "";
          //$this->API_Signature  = "";
          //$this->Paypal_receiver_accId = "";
      }
    
      $this->trackingId = $this->generateTrackingID();
      $this->actionType = "PAY";
    
      $this->currencyCode = "USD";
      $this->receiverEmailArray	= array($this->Paypal_receiver_accId,);
      if($this->anAction) {
          $this->receiverAmountArray = array($this->anAction->amount,);
      }
      else {
          $this->receiverAmountArray = array();
      }
      $this->receiverPrimaryArray = array();
      $this->receiverInvoiceIdArray = array($this->trackingId,);
      $this->feesPayer = "";
      $this->memo = "";
      $this->pin = "";
      $this->preapprovalKey = "";
      $this->reverseAllParallelPaymentsOnError = "";
    
//    if (session_id() == "") {
//      session_start();
//    }

      $this->ipn_log = true;
      $this->ipn_log_file = dirname(__FILE__) . "/paypal.log";

      return ;
  }

  public function canAuthCapture() {
      return true;
  }

  public function canIssueCredit() {
      return true;
  }

  public function mustDelegateBeforeCapture() {
      return true;
  }

	protected function generateCharacter () {
		$possible = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
		return $char;
	}

	protected function generateTrackingID () {
		$GUID = $this->generateCharacter() . $this->generateCharacter() . $this->generateCharacter() . $this->generateCharacter() . $this->generateCharacter();
		$GUID .= $this->generateCharacter() . $this->generateCharacter() . $this->generateCharacter() . $this->generateCharacter();
		return $GUID;
	}

	/**
	 * -------------------------------------------------------------------------------------------------------------------------------------------
	 * Purpose: 	Prepares the parameters for the Refund API Call.
	 * 			The API credentials used in a Pay call can make the Refund call
	 * 			against a payKey, or a tracking id, or to specific receivers of a payKey or a tracking id
	 * 			that resulted from the Pay call
	 * 
	 * 			A receiver itself with its own API credentials can make a Refund call against the transactionId corresponding to their transaction.
	 * 			The API credentials used in a Pay call cannot use transactionId to issue a refund
	 * 			for a transaction for which they themselves were not the receiver
	 * 
	 * 			If you do specify specific receivers, keep in mind that you must provide the amounts as well
	 * 			If you specify a transactionId, then only the receiver of that transactionId is affected therefore
	 * 			the receiverEmailArray and receiverAmountArray should have 1 entry each if you do want to give a partial refund
	 *  Inputs:
	 * 
	 *  Conditionally Required:
	 * 		One of the following:  payKey or trackingId or trasactionId or
	 *  Returns:
	 * 		The NVP Collection object of the Refund call response.
	 * --------------------------------------------------------------------------------------------------------------------------------------------
	*/
	protected function CallRefund( $payKey, $transactionId, $trackingId, $currencyCode )
	{
		// The variable nvpstr holds the name value pairs
		$nvpstr = "";

		// conditionally required fields
		if ("" != $payKey){
			$nvpstr = "payKey=" . urlencode($payKey);
		}
		elseif ("" != $trackingId){
			$nvpstr = "trackingId=" . urlencode($trackingId);
		}
		elseif ("" != $transactionId){
			$nvpstr = "transactionId=" . urlencode($transactionId);
		}
        
        // Required
        $nvpstr .= "&currencyCode=" . urlencode($currencyCode);

		/* Make the Refund call to PayPal */
		$resArray = $this->hash_call("Refund", $nvpstr);

		/* Return the response array */
		return $resArray;
	}

	/**
	 * -------------------------------------------------------------------------------------------------------------------------------------------
	 * Purpose: 	Prepares the parameters for the PaymentDetails API Call.
	 * 			The PaymentDetails call can be made with either
	 * 			a payKey, a trackingId, or a transactionId of a previously successful Pay call.
	 * Inputs:
	 * 
	 * Conditionally Required:
	 * 		One of the following:  payKey or transactionId or trackingId
	 * Returns:
	 * 		The NVP Collection object of the PaymentDetails call response.
	 * --------------------------------------------------------------------------------------------------------------------------------------------
	 */
	protected function CallPaymentDetails( $payKey, $transactionId, $trackingId )
	{
		$nvpstr = "";

		// conditionally required fields
		if ("" != $payKey) {
			$nvpstr = "payKey=" . urlencode($payKey);
		}
		elseif ("" != $transactionId) {
			$nvpstr = "transactionId=" . urlencode($transactionId);
		}
		elseif ("" != $trackingId) {
			$nvpstr = "trackingId=" . urlencode($trackingId);
		}

		/* Make the PaymentDetails call to PayPal */
		$resArray = $this->hash_call("PaymentDetails", $nvpstr);

		/* Return the response array */
		return $resArray;
	}

	/**
	 * -------------------------------------------------------------------------------------------------------------------------------------------
	 * Purpose: 	Prepares the parameters for the Pay API Call.
	 * Inputs:
	 * 
	 * Required:
	 * 
	 * Optional:
	 * 
	 * Returns:
	 * 		The NVP Collection object of the Pay call response.
	 * --------------------------------------------------------------------------------------------------------------------------------------------
	 */
	protected function CallPay( $actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray, $receiverAmountArray,
						$receiverPrimaryArray, $receiverInvoiceIdArray, $feesPayer, $memo, $pin, $preapprovalKey,
                        $reverseAllParallelPaymentsOnError, $trackingId )
	{
      
        
        // Logging the transaction
        $log = PPTransactionLog::start(array(
                      'trackingId'          => $trackingId,
                      'preapprovalKey'      => $preapprovalKey,
                      'ipnNotificationUrl'  => $this->ipnNotificationUrl,
                      'receiverAmountArray' => $receiverAmountArray[0],
                      'currencyCode'        => $currencyCode,
                      'returnUrl'           => $returnUrl,
                      'cancelUrl'           => $cancelUrl,
                      'api_operation'       => 'PAY',
                      ),
                      $this->anAction->customer->brand->id
                      );
      
        /* Gather the information to make the Pay call.
		*  The variable nvpstr holds the name value pairs
		*/

		// required fields
		$nvpstr = "actionType=" . urlencode($actionType) . "&currencyCode=" . urlencode($currencyCode);
		$nvpstr .= "&returnUrl=" . urlencode($returnUrl) . "&cancelUrl=" . urlencode($cancelUrl);

		if (0 != count($receiverAmountArray)) {
            reset($receiverAmountArray);
			while (list($key, $value) = each($receiverAmountArray)){
				if ("" != $value)
					$nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
			}
		}

		//if (0 != count($receiverEmailArray)){
		//	reset($receiverEmailArray);
		//	while (list($key, $value) = each($receiverEmailArray)){
		//		if ("" != $value)
		//			$nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
		//	}
		//}
        // Receiver email
        $brandAPI = $this->getBrandAPI($this->anAction->customer->brandid);
        $nvpstr .= "&receiverList.receiver(0).email=" . urlencode($brandAPI['Paypal_receiver_accId']);

		if (0 != count($receiverPrimaryArray)){
			reset($receiverPrimaryArray);
			while (list($key, $value) = each($receiverPrimaryArray)){
				if ("" != $value)
					$nvpstr = $nvpstr . "&receiverList.receiver(" . $key . ").primary=" . urlencode($value);
			}
		}

		if (0 != count($receiverInvoiceIdArray)){
			reset($receiverInvoiceIdArray);
			while (list($key, $value) = each($receiverInvoiceIdArray)){
				if ("" != $value)
					$nvpstr = $nvpstr . "&receiverList.receiver(" . $key . ").invoiceId=" . urlencode($value);
			}
		}

		// optional fields
		if ("" != $feesPayer)
			$nvpstr .= "&feesPayer=" . urlencode($feesPayer);
		if ("" != $this->ipnNotificationUrl)
			$nvpstr .= "&ipnNotificationUrl=" . urlencode($this->ipnNotificationUrl);
		if ("" != $memo)
			$nvpstr .= "&memo=" . urlencode($memo);
		if ("" != $pin)
			$nvpstr .= "&pin=" . urlencode($pin);
		if ("" != $preapprovalKey)
			$nvpstr .= "&preapprovalKey=" . urlencode($preapprovalKey);
		if ("" != $reverseAllParallelPaymentsOnError)
			$nvpstr .= "&reverseAllParallelPaymentsOnError=" . urlencode($reverseAllParallelPaymentsOnError);
		if ("" != $trackingId)
			$nvpstr .= "&trackingId=" . urlencode($trackingId);

		/* Make the Pay call to PayPal */
		$resArray = $this->hash_call("Pay", $nvpstr);
        
        $log->finish($resArray);

		/* Return the response array */
		return $resArray;
	}

	/**
	 * -------------------------------------------------------------------------------------------------------------------------------------------
	 * Purpose: 	Prepares the parameters for the PreapprovalDetails API Call.
	 * Inputs:
	 * 
	 * Required:
	 * 		preapprovalKey:		A preapproval key that identifies the agreement resulting from a previously successful Preapproval call.
	 * Returns:
	 * 	The NVP Collection object of the PreapprovalDetails call response.
	 * --------------------------------------------------------------------------------------------------------------------------------------------
	 */
	protected function CallPreapprovalDetails( $preapprovalKey ) {

		// required fields
		$nvpstr = "preapprovalKey=" . urlencode($preapprovalKey);

		/* Make the PreapprovalDetails call to PayPal */
		$resArray = $this->hash_call("PreapprovalDetails", $nvpstr);

		/* Return the response array */
		return $resArray;
	}

	protected function CallPreapproval( $returnUrl, $cancelUrl, $currencyCode, $startingDate, $endingDate, $maxTotalAmountOfAllPayments,
								$maxNumberOfPayments, $paymentPeriod, $dateOfMonth, $dayOfWeek,
								$maxAmountPerPayment, $maxNumberOfPaymentsPerPeriod, $pinType ) {

        // required fields
		$nvpstr = "returnUrl=" . urlencode($returnUrl) . "&cancelUrl=" . urlencode($cancelUrl) . "&currencyCode=" . urlencode($currencyCode);
        $nvpstr .= "&startingDate=" . urlencode(date('Y-m-d\Z', strtotime($startingDate . ' - 5 hours')));
		$nvpstr .= "&endingDate=" . urlencode(date('Y-m-d\Z', strtotime($endingDate . ' - 5 hours')));
		$nvpstr .= "&maxTotalAmountOfAllPayments=" . urlencode($maxTotalAmountOfAllPayments);

		// optional fields
		if ("" != $maxNumberOfPayments)
			$nvpstr .= "&maxNumberOfPayments=" . urlencode($maxNumberOfPayments);
		if ("" != $paymentPeriod)
			$nvpstr .= "&paymentPeriod=" . urlencode($paymentPeriod);
		if ("" != $dateOfMonth)
			$nvpstr .= "&dateOfMonth=" . urlencode($dateOfMonth);
		if ("" != $dayOfWeek)
			$nvpstr .= "&dayOfWeek=" . urlencode($dayOfWeek);
		if ("" != $maxAmountPerPayment)
			$nvpstr .= "&maxAmountPerPayment=" . urlencode($maxAmountPerPayment);
		if ("" != $maxNumberOfPaymentsPerPeriod)
			$nvpstr .= "&maxNumberOfPaymentsPerPeriod=" . urlencode($maxNumberOfPaymentsPerPeriod);
		if ("" != $pinType)
			$nvpstr .= "&pinType=" . urlencode($pinType);
        if ("" != $this->ipnNotificationUrl)
            $nvpstr .= "&ipnNotificationUrl=" . urlencode($this->ipnNotificationUrl);

		// Make the Preapproval call to PayPal
		$resArray = $this->hash_call("Preapproval", $nvpstr);

		// Return the response array
		return $resArray;
	}
    

    protected function CallCancel($currencyCode, $actionType, $preapprovalKey){
        $nvpstr = "preapprovalKey=" . urlencode($preapprovalKey);
        $nvpstr .= "&currencyCode=" . urlencode($currencyCode);
        
        /* Make the PreapprovalDetails call to PayPal */
		$resArray = $this->hash_call("CancelPreapproval", $nvpstr);

		/* Return the response array */
		return $resArray;
    }

	protected function hash_call($methodName, $nvpStr) {
        $API_Endpoint = $this->API_Endpoint . "/" . $methodName;
        
        $brandAPI = $this->getBrandAPI($this->anAction->customer->brandid);
	
    	//setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$API_Endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLCERT, $brandAPI['PP_CERT']);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, 'password');
        
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);

		// Set the HTTP Headers
		curl_setopt($ch, CURLOPT_HTTPHEADER,  array(
            'X-PAYPAL-REQUEST-DATA-FORMAT: NV',
            'X-PAYPAL-RESPONSE-DATA-FORMAT: NV',
            'X-PAYPAL-SECURITY-USERID: ' . $brandAPI['API_UserName'],
            'X-PAYPAL-SECURITY-PASSWORD: ' . $brandAPI['API_Password'],
            'X-PAYPAL-SERVICE-VERSION: 1.3.0',
            'X-PAYPAL-APPLICATION-ID: ' . $this->API_AppID
        ));
        
        //curl_setopt($ch, CURLOPT_CAINFO, $brandAPI['PP_KEY']);
        //curl_setopt($ch, CURLOPT_SSLCERT, $brandAPI['PP_CERT']);

	    //if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
		//Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php
		if($this->USE_PROXY)
            curl_setopt ($ch, CURLOPT_PROXY, $this->PROXY_HOST. ":" . $this->PROXY_PORT);

		// RequestEnvelope fields
		$detailLevel	= urlencode("ReturnAll");	// See DetailLevelCode in the WSDL for valid enumerations
		$errorLanguage	= urlencode("en_US");		// This should be the standard RFC 3066 language identification tag, e.g., en_US

		// NVPRequest for submitting to server
		$nvpreq = "requestEnvelope.errorLanguage=$errorLanguage&requestEnvelope.detailLevel=$detailLevel";
		$nvpreq .= "&$nvpStr";

		//setting the nvpreq as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

		//getting response from server
		$response = curl_exec($ch);

		//converting NVPResponse to an Associative Array
		$nvpResArray=$this->deformatNVP($response);
		$nvpReqArray=$this->deformatNVP($nvpreq);
		$_SESSION['nvpReqArray']=$nvpReqArray;

		if (curl_errno($ch)){
            // moving to display page to display curl errors
			$_SESSION['curl_error_no']=curl_errno($ch) ;
			$_SESSION['curl_error_msg']=curl_error($ch);
			//TODO: Execute the Error handling module to display errors.
		}
		else {
			//closing the curl
		  	curl_close($ch);
		}

		return $nvpResArray;
	}
    

    protected function getBrandAPI($brandID) {
        $brandAPI = array(
            'API_UserName'          => "",
            'API_Password'          => "",
            'PP_CERT'               => '',
            'Paypal_receiver_accId' => ""
        );

      return $brandAPI;
    }

	
	protected function RedirectToPayPal ( $cmd ) {
		// Redirect to paypal.com here

		$payPalURL = "";

		if ($this->Env == "sandbox") {
			$payPalURL = "https://www.sandbox.paypal.com/webscr?" . $cmd;
		}
		else {
			$payPalURL = "https://www.paypal.com/webscr?" . $cmd;
		}
		return $payPalURL;

	}


	protected function deformatNVP($nvpstr) {
		$intial=0;
	 	$nvpArray = array();

		while(strlen($nvpstr)) {
			//postion of Key
			$keypos= strpos($nvpstr,'=');
			//position of value
			$valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);

			/*getting the Key and Value values and storing in a Associative Array*/
			$keyval=substr($nvpstr,$intial,$keypos);
			$valval=substr($nvpstr,$keypos+1,$valuepos-$keypos-1);
			//decoding the respose
			$nvpArray[urldecode($keyval)] =urldecode( $valval);
			$nvpstr=substr($nvpstr,$valuepos+1,strlen($nvpstr));
	     }
		return $nvpArray;
	}

    public function setPreapprovalKey ( $var )  {
        $this->preapprovalKey = $var;
    }

  public function validateIpn() {
    $payPalURL = "";

     $raw_post_data = file_get_contents('php://input');
     $raw_post_array = explode('&', $raw_post_data);
     $_YOUR_POST = array();
     foreach ($raw_post_array as $keyval) {
       $keyval = explode ('=', $keyval);
       if (count($keyval) == 2) {
         $this->ipn_data[urldecode("{$keyval[0]}")] = $_YOUR_POST[$keyval[0]] = urldecode($keyval[1]);
       }
     }
     if (count($_YOUR_POST)<3) {
       $_YOUR_POST = $_POST;
       $original_post_used = TRUE;
     }
     else {
        $original_post_used = FALSE;
     }

     // Build final $_req postback request
     // Paypal's IPN Sample
     // read the post from PayPal system and add 'cmd'

     if ($original_post_used) {
       $_req = 'cmd=_notify-validate';
       foreach ($_YOUR_POST as $key => $value) {
         $value = urlencode(stripslashes($value));
         $_req .= "&$key=$value";
       }
     }
     else {
        $_req = $raw_post_data . '&cmd=_notify-validate';
     }

     // $_req is ready for postback to Paypal here...

		if ($this->Env == "sandbox")
		{
			$payPalURL = "https://www.sandbox.paypal.com/webscr";
		}
		else
		{
			$payPalURL = "https://www.paypal.com/webscr";
		}

    $url_parsed=parse_url($payPalURL);
    // open the connection to paypal
    $fp = fsockopen($url_parsed['host'],"80",$err_num,$err_str,30);
    if(!$fp) {
      // could not open the connection.  If loggin is on, the error message
      // will be in the log.
      $this->last_error = "fsockopen error no. $errnum: $errstr";
      $this->log_ipn_results(false);
      return false;
    } else {
      // Post the data back to paypal
      fputs($fp, "POST $url_parsed[path] HTTP/1.1\n");
      fputs($fp, "Host: $url_parsed[host]\n");
      fputs($fp, "Content-type: application/x-www-form-urlencoded\n");
      fputs($fp, "Content-length: ".strlen($_req)."\n");
      fputs($fp, "Connection: close\n\n");
      fputs($fp, $_req . "\n\n");

      // loop through the response from the server and append to variable
      while(!feof($fp)) {
        $this->ipn_response .= fgets($fp, 1024);
      }

      fclose($fp); // close connection
    }

    if (preg_match("/VERIFIED/",$this->ipn_response)) {

      // Valid IPN transaction.
      $this->log_ipn_results(true);
      return true;

    } else {

      // Invalid IPN transaction.  Check the log for details.
      $this->last_error = 'IPN Validation Failed.';
      $this->log_ipn_results(false);
      return false;

    }
  }

  public function log_ipn_results($success) {

    if (!$this->ipn_log) return;  // is logging turned off?

    // Timestamp
    $this->ipn_response_text = '['.date('m/d/Y g:i A').'] - ';

    // Success or failure being logged?
    if ($success) $this->ipn_response_text .= "SUCCESS!<br >\n";
    else $this->ipn_response_text .= 'FAIL: '.$this->last_error."<br >\n";

    // Log the POST variables
    $this->ipn_response_text .= "IPN POST Vars from Paypal:<br >\n";


    if(sizeof($this->ipn_data) && is_array($this->ipn_data)) {
      foreach ($this->ipn_data as $key=>$value) {        
        $this->ipn_response_text .= "$key=$value, <br >\n";
        $this->ipn_posted_vars .= $key.'='.$value."<br>";
      }
    }

    // Log the response from the paypal server
    $this->ipn_response_text .= "\nIPN Response from Paypal Server:<br >\n " . $this->ipn_response;

    // Write to log
    $fp=fopen($this->ipn_log_file,'a');
    fwrite($fp, $this->ipn_response_text . "\n\n");

    fclose($fp);  // close file
  }

  public function getIpn_response_text() {
    return $this->ipn_response_text;
  }

  public function getIpn_data() {
    return $this->ipn_data;
  }

}
