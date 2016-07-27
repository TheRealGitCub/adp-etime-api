<?php
	require 'credentials.php';
	require '../simple_html_dom.php';

	error_reporting(E_ALL);
	ini_set('display_errors', '1');


	function request($url, $cookie = null, $post = null, $headers = true) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ch, CURLOPT_USERPWD, ADP_USERNAME . ":" . ADP_PASSWORD);
		if ($headers) {
			curl_setopt($ch, CURLOPT_HEADER, 1);
		}
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		if ($post != null) {
			$post_string = "";
			foreach($post as $key=>$value) {
				$post_string .= $key.'='.$value.'&';
			}
			rtrim($post_string, '&');
			curl_setopt($ch,CURLOPT_POST, count($post));
			curl_setopt($ch,CURLOPT_POSTFIELDS, $post_string);
		}
		$result = curl_exec($ch);
		$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code

		if (!$result) {
			echo $status_code;
		}

		curl_close ($ch);

		return $result;
	}

	function getAuth() {
		$step1 = request("https://eet60.adp.com/public/etime/index.html");

		if (!$step1) {
			return false;
		}

		$step2 = request("https://eet60.adp.com/public/etime/html.html");

		if (!$step2) {
			return false;
		}

		$step3 = request("https://egateway.adp.com/siteminderagent/nocert/1469632901/smgetcred.scc?TYPE=16777217&REALM=-SM-eTime%20User%20Login%20[11%3a21%3a41%3a9595]&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-oIGrqvxRRgQwXH0RjYx5NR%2fiDc4IOercxn63JJxjcNcF7%2bRA5OZ%2bVPzurWMNDH%2bi&TARGET=-SM-HTTPS%3a%2f%2feet60%2eadp%2ecom%2fwfc%2fSSOLogon%2flogonWithUID%3fIntendedURL%3d%2fwfc%2fapplications%2fsuitenav%2fnavigation%2edo%3fESS%3dtrue");

		if (!$step3) {
			return false;
		}

		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $step3, $matches);
		$cookies = array();

		$newCookie = implode(";", $matches[1]);

		$step4 = request("https://eet60.adp.com/wfc/SSOLogon/logonWithUID?IntendedURL=/wfc/applications/suitenav/navigation.do?ESS=true", $newCookie);

		if (!$step4) {
			return false;
		}

		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $step4, $matches);
		$cookies = array();

		$authCookie = implode(";", $matches[1]);

		return $authCookie;

	}

	$sessionCookie = getAuth();

	if ($_GET["method"] == "record-stamp") {
		request("https://eet60.adp.com/wfc/applications/wtk/html/ess/timestamp-record.jsp",
			$sessionCookie,
			[ "transfer" => "" ]
		);
	}
	if ($_GET["method"] == "view-timesheet") {
		$sheet = request("https://eet60.adp.com/wfc/applications/mss/esstimecard.do",
			$sessionCookie,
			null, false
		);

		// echo "<pre>" . htmlspecialchars($sheet) . "</pre>";

		$html = str_get_html($sheet);

		echo $html->find("table.Timecard",0)->outertext;

	}

?>
