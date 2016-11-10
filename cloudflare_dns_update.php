<?php

/* 
 * Clouflare API DNS update script
 * 
 * This script will periodically check for a change in the IP
 * address of a host behind a dynamic IP internet connection.
 * 
 * Upon discovering a change in IP it will then update the 
 * Cloudfare domain record as configured and will optionally send an email.
 *
 * Version 1.0 
 * 10 November 2016
 *
 * Copyright Ryan Lovett (c) 2016 ryan@skerric.com.au
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


/* Script configuration */
date_default_timezone_set('Australia/Sydney');							// Sometimes PHP requires this to be set, depends on your environment

$config['get_ip_url'] = "http://api.ipify.org";							// URL of an IP provider
$config['run_cron'] = false;											// Should the script be run from CRON (skips looping)
$config['run_period'] = 30;												// The period in seconds between each IP check run

$config['cf_email'] = "";									// The email address associated with your Cloudflare account
$config['cf_apikey'] = "";									// The global API key from your Cloudflare accoumt

$config['cf_zone_name'] = "";								// The domain name which contains the record you would like to update
$config['cf_zone_id'] = "";									// The Cloudflare ID of the above domain

$config['cf_record_name'] = "";								// The DNS A record you would like to update
$config['cf_record_id'] = "";								// The Cloudflare ID of the above record
$config['cf_record_ttl'] = 120;											// The TTL your would like to set for the above record
$config['cf_record_proxiable'] = false;									// The proxiable value you would like to set for the above record
$config['cf_record_proxied'] = false;									// The proxied value you would like to set for the above record
$config['cf_record_locked'] = false;									// The locked value you would like to set for the above record

$config['email_send'] = false;											// Should the script send an email when the IP is updated?
$config['email_phpmailer'] = "phpmailer/PHPMailerAutoload.php";			// The path to PHP mailer to send mail
$config['email_host'] = "smtp.gmail.com";								// An email hostname to use for SMTP
$config['email_port'] = 587;											// A port to use for SMTP
$config['email_secure'] = "tls";										// A protocol to use for SMTP
$config['email_user'] = "";									// A username to use for SMTP
$config['email_pass'] = "";									// A password to use for SMTP
$config['email_from'] = '';									// An email from address
$config['email_to_1'] = '';									// An email to address
$config['email_to_2'] = '';									// An email to address
$config['email_subject'] = "Cloudflare IP Address Update";				// An email subject prefix (the IP adderss will be appended)



/* Set up the monitoring script */
if ($config['email_send'] === true)	{ require $config['email_phpmailer']; }
$run_oldip = '';
$run_seq = 0;

if ($config['run_cron'] === false) { echo "\r\n".'Managing dynamic DNS for: '.$config['cf_record_name']."\n\r"; }

/* Check if first run and, if so, get the current IP */
if ($run_oldip == '') 
	{ 
		// Get the current IP address from Cloudflare
		
		//set up the curl request
		$process = curl_init('https://api.cloudflare.com/client/v4/zones/'.$config['cf_zone_id'].'/dns_records/'.$config['cf_record_id']);   //add url

		//run the request out
		curl_setopt($process, CURLOPT_HTTPHEADER, array('X-Auth-Email: '.$config['cf_email'], 'X-Auth-Key: '.$config['cf_apikey'], 'Content-Type: application/json'));
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($process, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		//execute the request
		$return = curl_exec($process);
		
		//close curl resource to free up system resources
		curl_close($process);		
		
		//get the output
		$return = json_decode($return, true);
		
		if ($return['success'] == 'true') 
			{ 
				$run_oldip = $return['result']['content']; 
				if ($config['run_cron'] === false) { echo 'Retrieved current IP from Cloudflare: '.$run_oldip."\n\r"; }
			}
		else
			{
				echo 'ERROR retrieving current IP from Cloudflare.'."\n\r";
			}
		
	}


/* Start the monitoring loop */
while (TRUE)
{

// Check for the IP address from an external source
$run_newip = @file_get_contents($config['get_ip_url']);
if ($run_newip === FALSE) { echo 'ERROR geting IP address from '.$config['get_ip_url'].'. Will try again in '.$config['run_period'].' seconds.'."\n\r"; }

// Looks like a change has happened
if ($run_newip !== $run_oldip && $run_newip !== FALSE)
	{
		// Update the new IP address to Cloudflare
		
		//build the payload array
		$payload['id'] = $config['cf_record_id'];
		$payload['type'] = 'A';
		$payload['name'] = $config['cf_record_name'];
		$payload['content'] = $run_newip;
		$payload['proxiable'] = $config['cf_record_proxiable'];
		$payload['proxied'] = $config['cf_record_proxied'];
		$payload['ttl'] = $config['cf_record_ttl'];
		$payload['locked'] = $config['cf_record_locked'];
		$payload['zone_id'] = $config['cf_zone_id'];
		$payload['zone_name'] = $config['cf_zone_name'];
		$payload['data'] = array();

		$payload = json_encode($payload);
		
		//set up the curl request
		$process = curl_init('https://api.cloudflare.com/client/v4/zones/'.$config['cf_zone_id'].'/dns_records/'.$config['cf_record_id']);   //add url

		//run the request out
		curl_setopt($process, CURLOPT_HTTPHEADER, array('X-Auth-Email: '.$config['cf_email'], 'X-Auth-Key: '.$config['cf_apikey'], 'Content-Type: application/json'));
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($process, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		//execute the request
		$return = curl_exec($process);
		
		//close curl resource to free up system resources
		curl_close($process);		
		
		//get the output
		$return = json_decode($return, true);

		if ($return['success'] == 1) { echo 'Cloudflare updated with changed IP: '.$return['result']['content'].' ('.$return['result']['name'].') - '; }
			else { echo 'ERROR Updating: '.$return['errors'][0]['message'].' - '; }

		// Should we send an email notification
		if ($config['email_send'] === true && $return['success'] == 1)
			{
				echo 'Sending notifiction email: ';

				$mail = new PHPMailer;
				$mail->isSMTP();
				$mail->SMTPOptions = array ('ssl' => array ('verify_peer'  => false, 'verify_peer_name'  => false, 'allow_self_signed' => true));
				$mail->Host = $config['email_host'];
				$mail->Port = $config['email_port'];
				$mail->SMTPSecure = $config['email_secure'];
				$mail->SMTPAuth = true;
				$mail->Username = $config['email_user'];
				$mail->Password = $config['email_pass'];
				$mail->setFrom($config['email_from']);
				$mail->addAddress($config['email_to_1']);
				$mail->addAddress($config['email_to_2']);
				$mail->Subject = $config['email_subject'].' '.$run_newip.' ('.$run_seq.')';

				$mail->Body = $run_newip;

				if (!$mail->send()) 
					{
				    	echo "Error: " . $mail->ErrorInfo;
					} 
				else 
					{
				    	echo "sent!";
					}
			}
		elseif ($config['email_send'] === true && $return['success'] != 1)
			{
				echo 'Sending ERROR email: ';

				$mail = new PHPMailer;
				$mail->isSMTP();
				$mail->SMTPOptions = array ('ssl' => array ('verify_peer'  => false, 'verify_peer_name'  => false, 'allow_self_signed' => true));
				$mail->Host = $config['email_host'];
				$mail->Port = $config['email_port'];
				$mail->SMTPSecure = $config['email_secure'];
				$mail->SMTPAuth = true;
				$mail->Username = $config['email_user'];
				$mail->Password = $config['email_pass'];
				$mail->setFrom($config['email_from']);
				$mail->addAddress($config['email_to_1']);
				$mail->addAddress($config['email_to_2']);
				$mail->Subject = 'ERROR: '.$config['email_subject'].' '.$run_newip.' ('.$run_seq.')';

				$mail->Body = print_r($return['errors'], true);

				if (!$mail->send()) 
					{
				    	echo "Error: " . $mail->ErrorInfo;
					} 
				else 
					{
				    	echo "sent!";
					}
			}

		echo "\n\r";
		
		// Update the IPs
		$run_oldip = $run_newip;
	}
elseif ($run_newip !== false && $config['run_cron'] === false)
	{
		echo $run_seq."\n\r";
	}


/* Should we loop? */
if ($config['run_cron'] === true) { break; }


/* Do some tidy up and finish the loop */
$run_seq++;
sleep($config['run_period']);

}

?>
