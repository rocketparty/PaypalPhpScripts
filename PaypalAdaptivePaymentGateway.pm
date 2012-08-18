package PaypalAdaptivePaymentGateway;
###############################################################################
# File:		PaypalAdaptivePaymentGateway.pm
# Created by: Brandon
# Created on: Tue 22 Feb 2011
#
###############################################################################
use strict;
use LWP::UserAgent;
use Digest::MD5 qw(md5 md5_hex);
use SOAP::Lite;
use Data::Dumper;

sub WSDL { return $Config::FreeForm::J3RPC->{AIM_WSDL}; }

sub getPreauthKey {
    my ($transID, $brandID, $amount, $startingDate, $endingDate, $cancelUrl, $returnUrl) = @_;

    my $totalOfAllPayments = $amount;
    
    # Unused params
    my $maxNumberOfPayments     = '';
    my $paymentPeriod           = '';
    my $dateOfMonth             = '';
    my $dayOfWeek               = '';
    my $maxAmountPerPayment     = '';
    my $pinType                 = '';
    my $maxNumberOfPaymentsPerPeriod = '';
    
    my $service = SOAP::Lite->service(WSDL);

    my $resp = $service->preauthPaypal($brandID, $transID, $startingDate, $endingDate, $amount, $maxNumberOfPayments, 
        $paymentPeriod, $dateOfMonth, $dayOfWeek, $maxAmountPerPayment, $maxNumberOfPaymentsPerPeriod, $pinType,
	$returnUrl, $cancelUrl);
    
    my $response;
    if ($resp->{'platformResponse'}){
        my @rc = split(/\|/, $resp->{'platformResponse'});
        foreach my $r (@rc){
            my @rvalue = split(/\=/, $r);
            $response->{$rvalue[0]} = $rvalue[1];
        }
    }
    else {
        $response->{'ResponseReasonCode'} = 0;
        $response->{'ResponseCode'} = 0;
        $response->{'ResponseReasonText'} = 'FAIL';
    }

    return $response;
}

sub getPreapprovalDetails {
    my ($brandID, $preapprovalKey) = @_;
    my $service = SOAP::Lite->service(WSDL);

    my $resp = $service->preapprovalDetails($brandID, $preapprovalKey);
    my $response;
    if ($resp->{'platformResponse'}){
        my @rc = split(/\|/, $resp->{'platformResponse'});
	foreach my $r (@rc){
	    my @rvalue = split(/\=/, $r);
	    $response->{$rvalue[0]} = $rvalue[1];
	}
    }
    else {
	$response->{'ResponseReasonCode'} = 0;
	$response->{'ResponseCode'} = 0;
	$response->{'ResponseReasonText'} = 'FAIL';
    }
    return $response;
}

sub getPreapprovedPayment {
    my ($brandID, $transID, $amount, $preapproveKey, $returnUrl, $cancelUrl) = @_;

    my $service = SOAP::Lite->service(WSDL);
    my $format_amount = sprintf('%0.2f', $amount);

    my $resp = $service->capturePaypal($brandID, $transID, $format_amount, $returnUrl, $cancelUrl, $preapproveKey);

    my $response;
    if ($resp->{'platformResponse'}){
        my @rc = split(/\|/, $resp->{'platformResponse'});
        foreach my $r (@rc){
            my @rvalue = split(/\=/, $r);
            $response->{$rvalue[0]} = $rvalue[1];
        }
    }
    else {
        $response->{'ResponseReasonCode'} = 0;
        $response->{'ResponseCode'} = 0;
        $response->{'ResponseReasonText'} = 'FAIL';
    }

    return $response;
}



sub getPaymentDetailByPaymentKey {    
    my ($brandID, $payKey) = @_;
    
    my $service = SOAP::Lite->service(WSDL);
    my $resp = $service->paymentDetails($brandID, $payKey, undef, undef);
    
    my $response;
    if ($resp->{'platformResponse'}){
        my @rc = split(/\|/, $resp->{'platformResponse'});
        foreach my $r (@rc){
            my @rvalue = split(/\=/, $r);
            $response->{$rvalue[0]} = $rvalue[1];
        }
    }
    else {
        $response->{'ResponseReasonCode'} = 0;
        $response->{'ResponseCode'} = 0;
        $response->{'ResponseReasonText'} = 'FAIL';
    }

    return $response;
}


sub getPaymentDetailByTransactionId {
    my ($brandID, $transactionId) = @_;
    
    my $service = SOAP::Lite->service(WSDL);
    my $resp = $service->paymentDetails($brandID, undef, $transactionId, undef);

    my $response;
    if ($resp->{'platformResponse'}){
        my @rc = split(/\|/, $resp->{'platformResponse'});
        foreach my $r (@rc){
            my @rvalue = split(/\=/, $r);
            $response->{$rvalue[0]} = $rvalue[1];
        }
    }
    else {
        $response->{'ResponseReasonCode'} = 0;
        $response->{'ResponseCode'} = 0;
        $response->{'ResponseReasonText'} = 'FAIL';
    }

    return $response;
}


sub getPaymentDetailByTrackingId {
    my ($brandID, $trackingId) = @_;
    
    my $service = SOAP::Lite->service(WSDL);
    my $resp = $service->paymentDetails($brandID, undef, undef, $trackingId);
    
    my $response;
    if ($resp->{'platformResponse'}){
        my @rc = split(/\|/, $resp->{'platformResponse'});
        foreach my $r (@rc){
            my @rvalue = split(/\=/, $r);
            $response->{$rvalue[0]} = $rvalue[1];
        }
    }
    else {
        $response->{'ResponseReasonCode'} = 0;
        $response->{'ResponseCode'} = 0;
        $response->{'ResponseReasonText'} = 'FAIL';
    }

    return $response;
}


1;
