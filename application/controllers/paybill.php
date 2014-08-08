<?php
defined ( 'BASEPATH' ) or exit ( 'No direct script access allowed' );
require APPPATH . '/libraries/AfricasTalkingGateway.php';
class Paybill extends CI_Controller {
	function Paybill() {
		parent::__construct ();
		$this->load->model ( 'Paybill_model', 'paybill' );
		$this->load->library ( 'CoreScripts' );
		$this->load->library ( 'curl' );
		$this->load->helper ( 'file' );
	}
	function index() {
		// Log the details
		$myFile = "application/controllers/mpesalog.txt";
		$input = $this->input->get ( NULL, TRUE );
		write_file ( $myFile, "=============================\n", 'a+' );
		foreach ( $input as $var => $value ) {
			if (! write_file ( $myFile, "$var = $value\n", 'a+' )) {
				echo "Unable to write to file!";
			}
		}
		
		// Get the input details
		$inp = array (
				'id' => $this->input->get ( 'id' ),
				'business_number' => $this->input->get ( 'business_number' ),
				'orig' => $this->input->get ( 'orig' ),
				'dest' => $this->input->get ( 'dest' ),
				'tstamp' => $this->input->get ( 'tstamp' ),
				'mpesa_code' => $this->input->get ( 'mpesa_code' ),
				'mpesa_acc' => $this->input->get ( 'mpesa_acc' ),
				'mpesa_msisdn' => $this->input->get ( 'mpesa_msisdn' ),
				'mpesa_trx_date' => $this->input->get ( 'mpesa_trx_date' ),
				'mpesa_trx_time' => $this->input->get ( 'mpesa_trx_time' ),
				'mpesa_amt' => $this->input->get ( 'mpesa_amt' ),
				'mpesa_sender' => $this->input->get ( 'mpesa_sender' ) 
		);
		$user = $this->input->get ( 'user' );
		$pass = $this->input->get ( 'pass' );
		
		if ($user == 'pioneerfsa' && $pass == 'financial@2013') {
			
			$firstName = $this->getFirstName ( $inp ['mpesa_sender'] ); // JOASH NYADUNDO
			$amount = number_format ( $inp ['mpesa_amt'] );
			$phoneNumber = $this->format_number ( $inp ['mpesa_msisdn'] );
			
			if ($inp ['mpesa_acc']) {
				$results = $this->paybill->checkCustomer ( $inp ['mpesa_acc'], $phoneNumber );
				
				if ($results ['success']) {
					$actualBal = $results ['balance'] + $amount;
					$balance = number_format ( $actualBal );
					// Send SMS to Client
					//Send SMS to Client
					$message ="Dear ". $firstName .", Your MPESA deposit of KES. ". $amount.
					" is confirmed and credited to your account.Thanks for banking with us!";

					//New balance KES. ".$balance."

					$sms_feedback = $this->corescripts->_send_sms2 ($phoneNumber, $message);
					
				} else {
					// Create Task in Wira
					// echo $results['clCode'];
					$this->createTask ( $inp, $results );
					
					// Send SMS to Client
					$message ="Dear ". $firstName .", Your MPESA deposit of KES. ". $amount.
					" confirmed. However,the Id Number you entered does not exist in our records.Kindly call 0705300035 to Update";
					 $sms_feedback = $this->corescripts->_send_sms2 ($phoneNumber, $message);
				}
			} else {
				$message = "Dear " . $firstName . ". Your MPESA deposit of KES. " . $amount . " confirmed." . " Always enter your ID No. " . "as the account number. Thanks for banking with us!";
				$sms_feedback = $this->corescripts->_send_sms2 ( $phoneNumber, $message );
			}
			
			// Record Trx to Database
			$transaction_registration = $this->paybill->record_transaction ( $inp );
			echo $transaction_registration ['message'];
		} else {
			echo "FAIL|The payment could not be completed at this time.Incorrect username / password combination." . "Pioneer FSA";
		}
	}
	function createTask($inp, $results) {
		$serverUrl = "http://127.0.0.1:8888/ipnserv";
		
		$parameters = array (
				'senderName' => $inp ['mpesa_sender'],
				'senderPhone' => $this->format_number ( $inp ['mpesa_msisdn'] ),
				'enteredId' => $inp ['mpesa_acc'],
				'mpesaCode' => $inp ['mpesa_code'],
				'mpesaDate' => $inp ['mpesa_trx_date'],
				'mpesaTime' => $inp ['mpesa_trx_time'],
				'mpesaAmount' => $inp ['mpesa_amt'],
				'customerNames' => $results ['customerNames'],
				'clCode' => $results ['clCode'],
				'docType' => 'MPESAIPN'
		);
		
		$response = $this->curl->simple_get ( $serverUrl, $parameters );
		
		//Should be saved in the database
		echo "BPM Response:" . $response;
	}
	
	function getFirstName($names) {
		$fullNames = explode ( " ", $names );
		$firstName = $fullNames [0];
		$customString = substr ( $firstName, 0, 1 ) . strtolower ( substr ( $firstName, 1 ) );
		return $customString;
	}
	function format_Number($phoneNumber) {
		$formatedNumber = "0" . substr ( $phoneNumber, 3 );
		return $formatedNumber;
	}
	function updateDetails() {
		$trxNo = $this->input->post( 'mpesaCode' );
		$idNo = $this->input->post( 'idNo' );
		$clientCode = $this ->input->post( 'clientCode' );
		
		if (isset ( $trxNo )) {
			$update = $this->paybill->updateRecords ( $trxNo, $idNo, $clientCode );
			
			if ($update) {
				echo "Correctly updated";
			} else {
				echo "Failed to update";
				header("HTTP/1.1 500 Internal Server Error");
			}
		}
		
	}
}

?>