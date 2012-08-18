<?php 
$prod_WSDL = '';
$devel_WSDL = '';

//Preauth
if ($_POST['preauth']){
    $brandID = 0;
    $transID = time();
    $startingDate = $_POST['start'];
    $endingDate = $_POST['end'];
    $amount = $_POST['amount'];
    
    // Unneeded
    $maxNumberOfPayments = null;
    $paymentPeriod = null;
    $dateOfMonth = null;
    $dayOfWeek = null;
    $maxAmountPerPayment = null;
    $maxNumberOfPaymentsPerPeriod = null;
    $pinType = null;
    $returnUrl = '';
    $cancelUrl = 'http://www.google.com';
     
    $client = new SoapClient($devel_WSDL);
    $response = $client->preauthPaypal($brandID, $transID, $startingDate, $endingDate,
        $amount,$maxNumberOfPayments, $paymentPeriod, $dateOfMonth, $dayOfWeek,
        $maxAmountPerPayment, $maxNumberOfPaymentsPerPeriod, $pinType, $returnUrl,
        $cancelUrl);
    if ($response->platformResponse){
        $presp = explode("|", $response->platformResponse);
//        foreach ($presp as $k => $v){
//            echo $k . " - " . $v . "<br/>";
//        }
    }
    $key = explode("=", $presp[3]);
    echo "<br/><a href=\"https://www.sandbox.paypal.com/webscr?cmd=_ap-preapproval&preapprovalkey=" . 
		$key[1] . "\">Preauth</a><br/>";
}
else if ($_POST['pay'] && $_POST['amount'] && $_POST['key']){
    $brandID = 0;
    $transID = time();
    $amount = $_POST['amount'];
    $returnUrl = 'http://www.google.com';
    $cancelUrl = 'http://www.google.com';
    $key = $_POST['key'];
    $client = new SoapClient($devel_WSDL);
    $response = $client->capturePaypal($brandID, $transID, $amount, $returnUrl, $cancelUrl, $key);
    if ($response->platformResponse){
        $presp = explode("|", $response->platformResponse);
//        foreach ($presp as $k => $v){
//            echo $k . " - " . $v . "<br/>";
//        }
    }
    
}
else if ($_POST['preauthdetails'] && $_POST['key']){
    $brandID = 0;
    $client = new SoapClient($prod_WSDL);
    $response = $client->preapprovalDetails($brandID, $_POST['key']);
    if ($response->platformResponse){
        $presp = explode("|", $response->platformResponse);
//        foreach ($presp as $k => $v){
//            echo $k . " - " . $v . "<br/>";
//        }
    }
}
else if ($_POST['paydetails']){
    $brandID = 8;
    $client = new SoapClient($prod_WSDL);
    $response = $client->paymentDetails($brandID, $_POST['paykey'], $_POST['transid'], $_POST['trackingid']);
    if ($response->platformResponse){
        $presp = explode("|", $response->platformResponse);
//        foreach ($presp as $k => $v){
//            echo $k . " - " . $v . "<br/>";
//        }
    }
}

?>
<!DOCTYPE html> 
<html lang="en"> 
  <head> 
    <meta charset="utf-8"> 
    <title>Paypal Testing/Utility Page</title> 
    <meta name="description" content="Paypal testing and utility"> 
    <meta name="author" content="Brandon Schmidt"> 
 
    <!-- Le HTML5 shim, for IE6-8 support of HTML elements --> 
    <!--[if lt IE 9]>
    <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]--> 
 
    <!-- Le styles --> 
    <link rel="stylesheet" href="http://twitter.github.com/bootstrap/1.3.0/bootstrap.min.css">
    <style type="text/css"> 
      /* Override some defaults */
      html, body {
        background-color: #eee;
      }
      body {
        padding-top: 40px; /* 40px to make the container go all the way to the bottom of the topbar */
      }
      .container > footer p {
        text-align: center; /* center align it with the container */
      }
      .container {
        width: 820px; /* downsize our container to make the content feel a bit tighter and more cohesive. NOTE: this removes two full columns from the grid, meaning you only go to 14 columns and not 16. */
      }
 
      /* The white background content wrapper */
      .content {
        background-color: #fff;
        padding: 20px;
        margin: 0 -20px; /* negative indent the amount of the padding to maintain the grid system */
        -webkit-border-radius: 0 0 6px 6px;
           -moz-border-radius: 0 0 6px 6px;
                border-radius: 0 0 6px 6px;
        -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.15);
           -moz-box-shadow: 0 1px 2px rgba(0,0,0,.15);
                box-shadow: 0 1px 2px rgba(0,0,0,.15);
      }
 
      /* Page header tweaks */
      .page-header {
        background-color: #f5f5f5;
        padding: 20px 20px 10px;
        margin: -20px -20px 20px;
      }
 
      /* Styles you shouldn't keep as they are for displaying this base example only */
      .content .span10,
      .content .span4 {
        min-height: 500px;
      }
      /* Give a quick and non-cross-browser friendly divider */
      .content .span4 {
        margin-left: 0;
        padding-left: 19px;
        border-left: 1px solid #eee;
      }
 
      .topbar .btn {
        border: 0;
      }
 
    </style> 
 
    <!-- Le fav and touch icons --> 
    <link rel="shortcut icon" href="images/favicon.ico"> 
    <link rel="apple-touch-icon" href="images/apple-touch-icon.png"> 
    <link rel="apple-touch-icon" sizes="72x72" href="images/apple-touch-icon-72x72.png"> 
    <link rel="apple-touch-icon" sizes="114x114" href="images/apple-touch-icon-114x114.png"> 
  </head> 
 
<body> 
    <div class="container"> 
        <div class="content"> 
            <div class="page-header"> 
                <h1>Paypal Utility <small>Testing and production utility.</small></h1> 
            </div> 
            <?php if ($presp){ ?>
                <div class="alert-message block-message info"> 
                    <a class="close" href="#">&times;</a> 
                    <p>Gateway Response</p> 
                    <div class="alert-actions"> 
                        <?php 
                            foreach ($presp as $k => $v){
                                echo $k . " - " . $v . "<br/>";
                            }
                        ?>
                    </div> 
                </div> 
            <?php } ?>
            <!--<div class="alert-message error">
                <a class="close" href="#">×</a>
                <p><strong>Holy guacamole!</strong> Best check yo self, you’re not looking too good.</p>
            </div>-->
            <div class="row"> 
                <div class="span10"> 
                    <h2>Preauth</h2>
                    <fieldset>
                    <form method="post">
                        <div class="clearfix"> 
                            <label for="amount">Amount</label> 
                            <div class="input"> 
                                <input class="xlarge" type="text" name="amount" size="30" value="5.00" /> 
                            </div>
                        </div>
                        <div class="clearfix"> 
                            <label for="start">Start</label>
                            <div class="input">
                                <input class="xlarge" type="text" name="start" size="30" value="2011-07-26T00:00:00" />
                            </div>
                        </div>
                        <div class="clearfix"> 
                            <label for="end">End</label>
                            <div class="input">
                                <input class="xlarge" type="text" name="end" size="30" value="2012-07-26T00:00:00" />
                            </div>
                        </div><!-- /clearfix --> 
                        <div class="actions">
                            <input type="submit" name="preauth" value="preauth" class="btn default"/>
                        </div>
                    </form>
                    </fieldset>
                    <h2>Preauth Details</h2>
                    <fieldset>
                    <form method="post">
                        <div class="clearfix"> 
                            <label for="amount">Preauth Key</label> 
                            <div class="input"> 
                                <input class="xlarge" type="text" name="key" size="30" /> 
                            </div>
                        </div>
                        <div class="actions">
                            <input type="submit" name="preauthdetails" value="preauthdetails" class="btn default"/>
                        </div>
                    </form>
                    </fieldset>
                    <h2>Pay</h2>
                    <fieldset>
                    <form method="post">
                        <div class="clearfix"> 
                            <label for="amount">Amount</label> 
                            <div class="input"> 
                                <input class="xlarge" type="text" name="amount" size="30" value="1.00" /> 
                            </div>
                        </div>
                        <div class="clearfix"> 
                            <label for="amount">Preauth Key</label> 
                            <div class="input"> 
                                <input class="xlarge" type="text" name="pay" size="30" value="pay" /> 
                            </div>
                        </div>
                        <div class="actions">
                            <input type="submit" name="pay" value="pay" class="btn default"/>
                        </div>
                    </form>
                    </fieldset>
                    <h2>Pay Details</h2>
                    <fieldset>
                    <form method="post">
                        <div class="clearfix"> 
                            <label for="pKey">Pay Key</label> 
                            <div class="input"> 
                                <input class="xlarge" type="text" name="paykey" size="30" /> 
                            </div>
                        </div>
                        <div class="clearfix"> 
                            <label for="tId">Trans Id</label> 
                            <div class="input"> 
                                <input class="xlarge" type="text" name="transid" size="30" /> 
                            </div>
                        </div>
                        <div class="clearfix"> 
                            <label for="trackId">Tracking Id</label> 
                            <div class="input"> 
                                <input class="xlarge" type="text" name="trackingid" size="30" /> 
                            </div>
                        </div>
                        <div class="actions">
                            <input type="submit" name="paydetails" value="paydetails" class="btn default"/>
                        </div>
                    </form>
                    </fieldset>
                </div> 
                <div class="span4"> 
                    <h3>Paypal utility</h3>
                    Check the code for devel or production settings. SoapClient will either be prod_WSDL or devel_WSDL. Lookup and set the correct company for each/any requests.  
                </div> 
            </div> 
        </div> 
        <footer> 
        </footer> 
    </div> <!-- /container --> 
</body> 
</html> 
