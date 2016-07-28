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
	
	function tdClean($item) {
		$item = str_replace("\n", "", $item);
		$item = trim($item);
		$item = str_replace("&nbsp;", "", $item);
		return $item;
	}
	
	$sessionCookie = getAuth();
	
	$response = [
		"status" => "FAILED",
		"message" => "Please supply a method"
	];

	if (isset($_GET["method"]) && $_GET["method"] == "record-stamp") {
		$request = request("https://eet60.adp.com/wfc/applications/wtk/html/ess/timestamp-record.jsp",
			$sessionCookie,
			[ "transfer" => "" ]
		);
		
		if ($request) {
			$response = [
				"status" => "OK"
			];
		}
		else {
			$response = [
				"status" => "FAILED"
			];
		}
		
	}
	if (isset($_GET["method"]) && $_GET["method"] == "view-timesheet") {
		$request = request("https://eet60.adp.com/wfc/applications/mss/esstimecard.do",
			$sessionCookie,
			null, false
		);

		// echo "<pre>" . htmlspecialchars($sheet) . "</pre>";

		$html = str_get_html($request);

		$rows = $html->find("table.Timecard",0)->find("tbody tr");
		$shifts = [];
		
		foreach ($rows as $row) {
			if ($row->find("td.Date",0)){
				
				$date = 		tdClean($row->find("td.Date",0)->plaintext);
				
				$timeIn = 		tdClean($row->find("td.InPunch", 0)->plaintext);
				$timeOut = 		tdClean($row->find("td.OutPunch", 0)->plaintext);
				
				$shiftTotal = 	tdClean($row->find("td.ShiftTotal", 0)->plaintext);
				$dayTotal = 	tdClean($row->find("td.DailyTotal",0)->plaintext);
				
				if ($timeIn != " " && $timeIn != "") {
					$shifts[$date]["shifts"][] = [
						"timeIn" => 		$timeIn,
						"timeOut" => 		$timeOut,
						"shiftTotal" => 	$shiftTotal,
					];
					
					if ($dayTotal != "" && $dayTotal != " ") {
						$shifts[$date]["dayTotal"] = $dayTotal;
					}
				}
			}
			
		}
		
		$response = [
			"total" => str_ireplace("Total:&nbsp;", "", $html->find("td.TotalsSummary",0)->plaintext),
			"period" =>	trim($html->find(".CTDisplay",0)->plaintext),
			"shifts" => $shifts
		];
		
	}
	
	if (isset($_GET["method"]) && $_GET["method"] == "clocked-in") {
		$request = request("https://eet60.adp.com/wfc/applications/mss/esstimecard.do",
			$sessionCookie,
			null, false
		);
		$html = str_get_html($request);
		$rows = $html->find("table.Timecard",0)->find("tbody tr");
		
		$range = trim($html->find(".CTDisplay",0)->plaintext);
		$range = explode(" - ", $range);
		
		$start = $range[0];
		$start = explode("/", $start);
		
		$year = $start[2];
		$now = time();
		
		$clockedIn = false;
		
		$lastTimeIn = null;
		
		foreach ($rows as $row) {
			if ($row->find("td.Date",0)){
			
				$timeIn = 		tdClean($row->find("td.InPunch", 0)->plaintext);
				$timeOut = 		tdClean($row->find("td.OutPunch", 0)->plaintext);
				
				$date = tdClean($row->find("td.Date",0)->plaintext) . "/" . $year;
				$date = strtotime($date);
				
				if ( (($now - $date) <= 86400) && ($timeIn != " " && $timeIn != "") ) {
					if ($timeOut == "" || $timeOut == " ") {
						$clockedIn = true;
						$lastTimeIn = $timeIn;
						break;
					}
					
				}
				
			}
			
		}
		
		if ($clockedIn) {
			$response = [
				"clockedIn" => true,
				"at" => $lastTimeIn
			];
		}
		else {
			$response = [
				"clockedIn" => false
			];
		}
		
		
	}
	
	if (isset($_GET["method"]) && $_GET["method"] == "missed-punch") {
		$request = request("https://eet60.adp.com/wfc/applications/mss/esstimecard.do",
			$sessionCookie,
			null, false
		);
		$html = str_get_html($request);
		$rows = $html->find("table.Timecard",0)->find("tbody tr");
		
		$missedPunch = false;
		$missedOn = null;
		
		foreach ($rows as $row) {
			if ($row->find("td.Date",0) && $row->find("td div.MissedPunchException",0)){
				$missedPunch = true;
				$missedOn = $row->find("td.Date",0)->plaintext;
			}
		}
		
		if ($missedPunch) {
			$response = [
				"missedPunch" => true,
				"missedOn" => $missedOn
			];
		}
		else {
			$response = [
				"missedPunch" => false
			];
		}
		
	}


	if (isset($_GET["method"]) && $_GET["method"] == "approve-timecard") {

		$timeframe = 1;

		if ( !isset($_GET["begin"]) || !isset($_GET["begin"]) ) {
			$response = [
				"status" => "FAILED",
				"message" => "Please provide a begin and end date for the timeframe."
			];
			goto end;
		}

		$begin = $_GET["begin"];
		$end = $_GET["end"];

		if (isset($_GET["timeframeId"])) {
			$timeframe = $_GET["timeframeId"];
		}

		$request = request("https://eet60.adp.com/wfc/applications/mss/esstimecard.do",
			$sessionCookie,
			[
				"com.kronos.wfc.ACTION" => "approve",
				"timeframeId" => $timeframe,
				"beginTimeframeDate" => $begin,
				"endTimeframeDate" => $end
			], false
		);

		if ($request) {
			$response = [
				"status" => "OK"
			];
		}
		else {
			$response = [
				"status" => "FAILED",
				"message" => "Something went wrong submitting the timecard approval form"
			];
		}

	}

	end:
	header('Content-Type: application/json');
	echo json_encode($response);

?>
