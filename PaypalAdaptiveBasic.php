<?php

//-------------------------------------------------
// When you integrate this code
// look for TODO as an indication
// that you may need to provide a value or take action
// before executing this code
//-------------------------------------------------

// ==================================
// PayPal Platform Basic Payment Module
// ==================================

class PaypalAdaptiveBasic extends PaypalAdaptiveGateway {

  public function initialize($anAction) {
      $this->anAction = $anAction;
      parent::initialize($anAction);
  }

  public function auth($anID = null) {
      $resArray = $this->CallPay ($this->actionType, $this->cancelUrl, $this->returnUrl, $this->currencyCode, $this->receiverEmailArray,
                              $this->receiverAmountArray, $this->receiverPrimaryArray, $this->receiverInvoiceIdArray,
                              $this->feesPayer, $this->ipnNotificationUrl, $this->memo, $this->pin, $this->preapprovalKey,
                              $this->reverseAllParallelPaymentsOnError, $this->trackingId
                              );

      $ack = strtoupper($resArray["responseEnvelope.ack"]);
      if($ack=="SUCCESS") {
          if ("" == $this->preapprovalKey) {
            return new GatewayResponse("1", $resArray["paymentExecStatus"], $resArray["payKey"], true, $this->ipnNotificationUrl);
          }
          else {
              // payKey is the key that you can use to identify the payment resulting from the Pay call
              $payKey = urldecode($resArray["payKey"]);
              // paymentExecStatus is the status of the payment
              $paymentExecStatus = urldecode($resArray["paymentExecStatus"]);
          }
      }
      else {
          return new GatewayResponse($resArray["responseEnvelope.ack"], $resArray['error(0).message'], null);
      }
  }

  public function preauth(){
      // For now the currency code is always USD. 
      $currencyCode   = 'USD';
        
      // These are required for the preauth request to be successful
      $startingDate = $this->anAction->paymentMethod->startingdate;
      $endingDate   = $this->anAction->paymentMethod->endingdate;
      $returnUrl    = $this->anAction->paymentMethod->returnurl;
      $cancelUrl    = $this->anAction->paymentMethod->cancelurl;
      
      // Optional values that can be passed
      $paymentPeriod  = ($this->anAction->paymentMethod->paymentperiod)
                      ? $this->anAction->paymentMethod->paymentperiod : '';
      $dateOfMonth    = ($this->anAction->paymentMethod->dateofmonth)
                      ? $this->anAction->paymentMethod->dateofmonth : '';
      $dayOfWeek      = ($this->anAction->paymentMethod->dayofweek)
                      ?$this->anAction->paymentMethod->dayofweek : '';
      $pinType        = ($this->anAction->paymentMethod->pintype)
                      ? $this->anAction->paymentMethod->pintype : '';
      $maxNumberOfPayments  = ($this->anAction->paymentMethod->maxnumberofpayments)
                            ? $this->anAction->paymentMethod->maxnumberofpayments : '';
      $maxAmountPerPayment  = ($this->anAction->paymentMethod->maxamountperpayment)
                            ? $this->anAction->paymentMethod->maxamountperpayment : '';
      $maxTotalAmountOfAllPayments  = ($this->anAction->paymentMethod->maxtotalamountofallpayments)
                                    ? $this->anAction->paymentMethod->maxtotalamountofallpayments : '';
      $maxNumberOfPaymentsPerPeriod = ($this->anAction->paymentMethod->maxnumberofpaymentsperperiod)
                                    ? $this->anAction->paymentMethod->maxnumberofpaymentsperperiod : '';
                                    
      $resp = $this->CallPreapproval($returnUrl, $cancelUrl, $currencyCode, $startingDate, $endingDate, $maxTotalAmountOfAllPayments,
                                    $maxNumberOfPayments, $paymentPeriod, $dateOfMonth, $dayOfWeek,
                                    $maxAmountPerPayment, $maxNumberOfPaymentsPerPeriod, $pinType);
      
      $ack = strtoupper($resp['responseEnvelope.ack']);
      
      if ($ack == 'SUCCESS'){
          // Success case
           $rv = array(
               'ResponseCode'       => 1,
               'ResponseReasonCode' => 1,
               'ResponseReasonText' => 'SUCCESS',
               'preapprovalKey'     => urldecode($resp['preapprovalKey']),
            );
      }
      else {
          // Error case
          $rv = array(
              'ResponseCode'  => urldecode($resp["error(0).errorId"]),
              'ResponseReasonCode' => urldecode($resp["error(0).category"]),
              'ResponseReasonText' => urldecode($resp["error(0).message"]),
              );
      }
      
      $pr = array();
	  foreach($rv as $key => $value) {
          array_push($pr, sprintf("%s=%s", $key, $value));
	  }
      $retval = new GatewayResponse($rv['ResponseCode'], $rv['ResponseReasonText']);
	  $retval->platformResponse = join("|", $pr);
      
      return $retval;
  }

  public function authCapture($anID = null) {
      $res = $this->auth();
      if($res->responseCode == "1") {
          // redirect for web approval flow
          return $this->capture($res->responseID);
      }
      return $res;
  }

  public function capture($responseID) {
      $cmd = "cmd=_ap-payment&paykey=" . urldecode($responseID);
      $url = $this->RedirectToPayPal ( $cmd );
      $res = new GatewayResponse(1, 'Delegation requested', $responseID, true, $url);
      return $res;
  }
  
  public function pay() {

      // For now the currency code is always USD and action is always pay for this function
      $currencyCode = 'USD';
      $actionType   = 'PAY';
      
      // TODO - Set invoiceId to uniquely identify the transaction associated with the receiver
      //        You can set this to the same value as trackingId if you wish
      $receiverInvoiceIdArray   = array($this->anAction->tid,);
      $receiverEmailArray	    = array('');
      $trackingId               = $this->anAction->tid;
      $preapprovalKey           = $this->anAction->paymentMethod->preapprovalkey;
      $returnUrl                = $this->anAction->paymentMethod->returnurl;
      $cancelUrl                = $this->anAction->paymentMethod->cancelurl;
      $receiverAmountArray      = array(
                                   $this->anAction->amount
                                  );
                                    
      // Optional and for the time unnecessary fields. These are not needed for basic payment types
      $reverseAllParallelPaymentsOnError  = "";
      $receiverPrimaryArray = array();
      $feesPayer            = "";
      $memo	                = "";
      $pin                  = "";
        
      $resp = $this->CallPay ($actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray,
						$receiverAmountArray, $receiverPrimaryArray, $receiverInvoiceIdArray,
						$feesPayer, $memo, $pin, $preapprovalKey, $reverseAllParallelPaymentsOnError, $trackingId
                        );

      // Dealing with the response 
      $ack = strtoupper($resp['responseEnvelope.ack']);
      if (isset($resp['paymentExecStatus'])){
          $execStatus = strtoupper($resp['paymentExecStatus']);
      }
      else {
          $execStatus = 'ERROR';
      }
      
      if ($ack == 'SUCCESS' && $execStatus != 'ERROR'){
          // Success case
           $rv = array(
               'ResponseCode'       => 1,
               'ResponseReasonCode' => 1,
               'ResponseReasonText' => 'SUCCESS',
               'payKey'             => urldecode($resp['payKey']),
               'payExecStatus'      => urldecode($resp['paymentExecStatus']),
            );
      }
      else if ($ack == 'FAILURE' && isset($resp['error(0).message'])){
          $rv = array(
              'ResponseCode'        => urldecode($resp["error(0).errorId"]),
              'ResponseReasonCode'  => urldecode($resp["error(0).category"]),
              'ResponseReasonText'  => urldecode($resp["error(0).message"]),
              'payExecStatus'       => 'ERROR',
          );
      }
      else if ($execStatus == 'ERROR'){
          $rv = array(
              'ResponseCode'        => 0,
              'ResponseReasonCode'  => 0,
              'ResponseReasonText'  => 'Paypal payment sourcing error',
              'payExecStatus'       => 'ERROR',
              );
      }
      else {
          // Error case
          $rv = array(
              'ResponseCode'        => urldecode($resp["error(0).errorId"]),
              'ResponseReasonCode'  => urldecode($resp["error(0).category"]),
              'ResponseReasonText'  => urldecode($resp["error(0).message"]),
              'payExecStatus'       => 'ERROR',
              );
      }

      $pr = array();
	  foreach($rv as $key => $value) {
          array_push($pr, sprintf("%s=%s", $key, $value));
	  }
	  
      $retval = new GatewayResponse($rv['ResponseCode'], $rv['ResponseReasonText']);
	  $retval->platformResponse = join("|", $pr);

      return $retval;
  }
  
  public function cancel() {
      // For now the currency code is always USD and action is always pay for this function
      $currencyCode = 'USD';
      $actionType   = 'PAY';
      $preapprovalKey = $this->anAction->paymentMethod->preapprovalkey;
        
      $resp = $this->CallCancel ($currencyCode, $actionType, $preapprovalKey);
      
      // Dealing with the response 
      $ack = strtoupper($resp['responseEnvelope.ack']);
      
      if ($ack == 'SUCCESS'){
          // Success case
           $rv = array(
               'ResponseCode'       => 1,
               'ResponseReasonCode' => 1,
               'ResponseReasonText' => 'SUCCESS',
            );
      }
      else {
          // Error case
          $rv = array(
              'ResponseCode'  => urldecode($resp["error(0).errorId"]),
              'ResponseReasonCode' => urldecode($resp["error(0).category"]),
              'ResponseReasonText' => urldecode($resp["error(0).message"]),
              );
      }

      $pr = array();
	  foreach($rv as $key => $value) {
          array_push($pr, sprintf("%s=%s", $key, $value));
	  }
	  
      $retval = new GatewayResponse($rv['ResponseCode'], $rv['ResponseReasonText']);
	  $retval->platformResponse = join("|", $pr);

      return $retval;
  }
  
  public function predetails(){
      // There is only one value that this function needs
      $preapprovalKey = $this->anAction->paymentMethod->preapprovalkey;
      
      $resp = $this->CallPreapprovalDetails($preapprovalKey);
      
      // Dealing with the response
      $ack = strtoupper($resp['responseEnvelope.ack']);

      if ($ack == 'SUCCESS'){
          // Success case
          $rv = array(
              'ResponseCode'        => 1,
              'ResponseReasonCode'  => 1,
              'ResponseReasonText'  => 'SUCCESS',
              );
          foreach($resp as $key => $value){
              $rv[$key] = $value;
          }
      }
      else {
          // Error case
          $rv = array(
              'ResponseCode' => urldecode($resp["error(0).errorId"]),
              'ResponseReasonCode'  => urldecode($resp["error(0).category"]),
              'ResponseReasonText'  => urldecode($resp["error(0).message"]),
              );
      }
      
      $pr = array();
      foreach($rv as $key => $value){
          array_push($pr, sprintf("%s=%s", $key, $value));
      }
      
      $retval = new GatewayResponse($rv['ResponseCode'], $rv['ResponseReasonText']);
      $retval->platformResponse = join("|", $pr);
      
      return $retval;
  }
  
  public function paydetails(){
      $payKey         = $this->anAction->paymentMethod->paykey;
      $transactionid  = $this->anAction->paymentMethod->transactionid;
      $trackingid     = $this->anAction->paymentMethod->trackingid;
      
      $resp = $this->CallPaymentDetails($payKey, $transactionid, $trackingid);
      
      // Dealing with the response
      $ack = strtoupper($resp['responseEnvelope.ack']);
      
      if ($ack == 'SUCCESS'){
          // Success case
          $rv = array(
              'ResponseCode'        => 1,
              'ResponseReasonCode'  => 1,
              'ResponseReasonText'  => 'SUCCESS',
              );
          foreach($resp as $key => $value){
              $rv[$key] = $value;
          }
      }
      else {
          // Error
          $rv = array(
              'ResponseCode'        => urldecode($resp["error(0).errorId"]),
              'ResponseReasonCode'  => urldecode($resp["error(0).errorId"]),
              'ResponseReasonText'  => urldecode($resp["error(0).errorId"]),
              );
      }
      
      $pr = array();
      foreach ($rv as $key => $value){
          array_push($pr, sprintf("%s=%s", $key, $value));
      }
      
      $retval = new GatewayResponse($rv['ResponseCode'], $rv['ResponseReasonText']);
      $retval->platformResponse = join("|", $pr);
      
      return $retval;
          
  }
  
  
  public function refund(){
      // This email needs replaced with the production email.
      // Move this and all other occurences into another location (config file or something)
      $currencyCode = 'USD';

      $paykey         = $this->anAction->paymentMethod->paykey;
      $transactionid  = $this->anAction->paymentMethod->transactionid;
      $trackingid     = $this->anAction->paymentMethod->trackingid;
        
      $resp = $this->CallRefund ($paykey, $transactionid, $trackingid, $currencyCode);
      
      // Dealing with the response 
      $ack = strtoupper($resp['responseEnvelope.ack']);
      
      if ($ack == 'SUCCESS'){
          // Success case
           $rv = array(
               'ResponseCode'       => 1,
               'ResponseReasonCode' => 1,
               'ResponseReasonText' => 'SUCCESS',
            );
      }
      else {
          // Error case
          $rv = array(
              'ResponseCode'  => urldecode($resp["error(0).errorId"]),
              'ResponseReasonCode' => urldecode($resp["error(0).category"]),
              'ResponseReasonText' => urldecode($resp["error(0).message"]),
              );
      }

      $pr = array();
	  foreach($rv as $key => $value) {
          array_push($pr, sprintf("%s=%s", $key, $value));
	  }
	  
      
      $retval = new GatewayResponse($rv['ResponseCode'], $rv['ResponseReasonText']);
	  $retval->platformResponse = join("|", $pr);

      return $retval;
  }

  public function void($id) {
  }

  public function issueCredit($id) {
  }

}
