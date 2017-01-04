<?php

class CApiRequest {
	private $id = 0;
	private $sid = '';

	public function request($method, array $params) {
		$this->login();
		$response = $this->send($method, $params);
		$this->logout();
		return $response['result'];
	}

	private function login() {
		$response = $this->send('user.login', ['user' => PHPUNIT_LOGIN_NAME, 'password' => PHPUNIT_LOGIN_PWD], false);
		$this->sid = $response['result'];
	}

	private function logout() {
		$response = $this->send('user.logout', []);
		$this->sid = '';
	}

	private function send($method, $params, $auth = true) {
		$request = [
			'jsonrpc' => '2.0',
			'method'  => $method,
			'params'  => $params,
			'id'      => $this->id
		]
		+ ($auth ? ['auth' => $this->sid] : []);

		$this->id++;

		$request = json_encode($request);

		$stream_context = stream_context_create(array('http' => array(
			'method'  => 'POST',
			'header'  => 'Content-type: application/json-rpc'."\r\n"."Content-Length: ".mb_strlen($request)."\r\n\r\n",
			'content' => $request
		)));

		$url = PHPUNIT_URL.'api_jsonrpc.php';
		$fp = fopen($url, 'rb', false, $stream_context);
		if (!$fp)
			throw new Exception('Could not connect to "'.$url.'"');

		$response = stream_get_contents($fp);

		if($response === FALSE)
			throw new Exception('Could not read data from "'.$url.'"');

		$response = json_decode($response, true);

		if (!is_array($response))
			throw new Exception('Could not decode JSON response.');
		if (array_key_exists('error', $response))
			throw new Exception('API error '.$response['error']['code'].': '.$response['error']['data']);
		if (!array_key_exists('result', $response))
			throw new Exception('API error, no result');

		return $response;
	}
}
