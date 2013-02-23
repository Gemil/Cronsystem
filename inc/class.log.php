<?php
/*
 * Logklasse von Gerrit Paepcke
 */
class Log {
	private static $log = array();
	
	public static function newEntry($prio, $message) {
		if (DEBUG) echo $message ."\n";
		self::$log[] = array( "prio" => $prio, "message" => $message, "timestamp" => time(), "microtime" => microtime(true));
	}
	
	public static function fatal($message) {	
		self::newEntry(5, "[FATAL] ".$message);
		$msg = '';
		foreach (self::getLog(0) as $entry) {
			$msg .= "<br>".date("d.m.Y H:i", $entry['timestamp'])." - ".$entry['message'];
		}
	//	if( self::mail("Fatalerror Cronsystem - Server ".php_uname("n"), $msg) == false ) {
	//		self::error( "Could not send Mail to system@homerj.de" );
	//	}
	//	throw new FatalException("Fatal Error: ".$message);		
	}
	
	public static function error($message) {
		return self::newEntry(4, "[ERROR] ".$message);
	}
	
	public static function warn($message) {
		return self::newEntry(2, "[WARN] ".$message);
	}
	
	public static function info($message) {
		return self::newEntry(1, "[INFO] ".$message);
	}
	
	public static function getLog($microtime,$unset=false) {
		$return = array();
		
		foreach( self::$log as $k => $v ) {
			if( $v['microtime'] > $microtime ) {
				$return[] = $v;
			}
		}
		
		if( $unset ) {
			self::$log = array();
		}
		
		return $return;
	}
	
	public static function mail($subject, $message) {
		/*
		$from = "admin@homerj.de";
		$namefrom = "Cronsystem";
		$to = "system@schenckmedia.de";
		$nameto = "System";
		
		$smtpServer = "smtp.homerj.de";
		$port = "25";
		$timeout = "30";
		$username = "admin@homerj.de";
		$password = "u27faw3f";
		$localhost = "localhost";
		$newLine = "\r\n";
		*/
		//Connect to the host on the specified port
		$smtpConnect = fsockopen($smtpServer, $port, $errno, $errstr, $timeout);
		$smtpResponse = fgets($smtpConnect, 515);
		if(empty($smtpConnect)) 
		{
			self::error("Failed to connect: $smtpResponse");
			return false;
		}
		else
		{
			$logArray['connection'] = "Connected: $smtpResponse";
		}

		//Request Auth Login
		fputs($smtpConnect,"AUTH LOGIN" . $newLine);
		$smtpResponse = fgets($smtpConnect, 515);
		$logArray['authrequest'] = "$smtpResponse";

		//Send username
		fputs($smtpConnect, base64_encode($username) . $newLine);
		$smtpResponse = fgets($smtpConnect, 515);
		$logArray['authusername'] = "$smtpResponse";

		//Send password
		fputs($smtpConnect, base64_encode($password) . $newLine);
		$smtpResponse = fgets($smtpConnect, 515);
		$logArray['authpassword'] = "$smtpResponse";

		//Say Hello to SMTP
		fputs($smtpConnect, "HELO $localhost" . $newLine);
		$smtpResponse = fgets($smtpConnect, 515);
		$logArray['heloresponse'] = "$smtpResponse";

		//Email From
		fputs($smtpConnect, "MAIL FROM: $from" . $newLine);
		$smtpResponse = fgets($smtpConnect, 515);
		$logArray['mailfromresponse'] = "$smtpResponse";

		//Email To
		fputs($smtpConnect, "RCPT TO: $to" . $newLine);
		$smtpResponse = fgets($smtpConnect, 515);
		$logArray['mailtoresponse'] = "$smtpResponse";

		//The Email
		fputs($smtpConnect, "DATA" . $newLine);
		$smtpResponse = fgets($smtpConnect, 515);
		$logArray['data1response'] = "$smtpResponse";

		//Construct Headers
		$headers = "MIME-Version: 1.0" . $newLine;
		$headers .= "Content-type: text/html; charset=iso-8859-1" . $newLine;

		fputs($smtpConnect, "To: $nameto <$to>\nFrom: $namefrom <$from>\nSubject: $subject\n$headers\n\n$message\n.\n");
		$smtpResponse = fgets($smtpConnect, 515);
		$logArray['data2response'] = "$smtpResponse";

		// Say Bye to SMTP
		fputs($smtpConnect,"QUIT" . $newLine); 
		$smtpResponse = fgets($smtpConnect, 515);
		$logArray['quitresponse'] = "$smtpResponse"; 
		return true;
	}
}

?>