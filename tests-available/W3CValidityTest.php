<?php

namespace PrestaShop\FunctionalTest;

class W3CValidityTest extends \PrestaShop\TestCase\LazyTestCase {

	public static function w3cValidateHTML($html, $options = array())
	{
		static $last_called_at = 0;
		// Ensure we don't call this too often, as per the validator guidelines.
		if (time() < $last_called_at + 1)
		{
			sleep(1);
			$last_called_at = time();
		}

		$options = array_merge(['ignore &amp;' => true], $options);

		$ch = curl_init('http://validator.w3.org/check');

		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "cURL");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, [
			"uploaded_file" => $html,
			"output" => "soap12"
		]);

		$response = curl_exec($ch);
		$ok = (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200);

		curl_close($ch);

		if (!$ok)
			return false;

		$xml = simplexml_load_string($response);

		$ns = $xml->getNamespaces(true);
		$soap = $xml->children($ns['env']);
		$body = $soap->Body;
		$validation = $body->children($ns['m'])->markupvalidationresponse;

		$errors = [];

		foreach ($validation->errors->errorlist->error as $error)
		{
			$messageid = (string)$error->messageid;
			$message = (string)$error->message;

			if (!empty($options['ignore &amp;']))
			{
				if (0 === strpos($message, '& did not start a character reference.'))
					continue;
			}

			$errors[] = [
				'line' => (int)$error->line,
				'col' => (int)$error->col,
				'source' => (string)$error->source,
				'explanation' => (string)$error->explanation,
				'messageid' => $messageid,
				'message' => $message
			];
		}

		return [
			'valid' => ((string)$validation->validity[0] === 'true'),
			'errorCount' => (int)$validation->errors->errorcount,
			'errors' => $errors
		];
	}

	public static function formatReport($validation, $name)
	{
		$html  = '<html><body>';
		$html .= "<h1>$name: {$validation['errorCount']} errors.</h1>";
		$html .= "<p>(and I'm not even counting &amp;amp; errors!)</p>";

		foreach ($validation['errors'] as $error)
		{
			$html .= "<h2>{$error['message']}</h2>";
			$html .= "<p>At line {$error['line']}, column {$error['col']}:</p>";
			$html .= "<pre>".$error['source']."</pre>";
			$html .= "<br>";
		}

		$html .= '</body></html>';

		return $html;
	}

	public function testBackOfficeW3CValidity()
	{
		$shop = static::getShop();
		$browser = $shop->getBrowser();

		$bo = $shop->getBackOfficeNavigator();
		$bo->login();

		$controllers = $bo->getMenuLinks();

		$totalErrorCount = 0;

		$incomplete = [];
		$failing = [];

		foreach ($controllers as $name => $url)
		{
			$bo->visit($name);
			$validation = self::w3cValidateHTML($browser->getPageSource());

			if ($validation === false)
			{
				$incomplete[] = $name;
				continue;
			}

			$errorCount = $validation['errorCount'];
			$totalErrorCount += $errorCount;

			if ($errorCount > 0)
			{
				$failing[] = $name;
				$this->writeArtefact("$name.html", self::formatReport($validation, $name));
			}
		}

		if ($totalErrorCount > 0) {
			$messages[] = "Found $totalErrorCount W3C validation errors in the BackOffice.";
			$messages[] = "Individual reports can be found under test-results/W3CValidityTest";
			$messages[] = "The following controllers have errors: ".implode(', ', $failing);
		}

		if (count($incomplete) > 0) {
			$messages[] = "Please note that the following pages were "
							. "not validated because the W3C validator mysteriously failed: "
							. implode(", ", $incomplete);
		}

		if (count($messages > 0))
		{
			throw new \PrestaShop\Exception\FailedTestException(implode("\n", $messages));
		}
	}

}