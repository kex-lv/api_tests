<?php

class CApiTest extends PHPUnit_Framework_TestCase {
	private $api;

	public function request($method, $parameters) {
		$this->api = new CApiRequest();

		$result = $this->api->request($method, $parameters);

		return $result;
	}

	protected function read_json(array $files) {
		$data_array = [];

		foreach ($files as $file) {
			$data_array[0][] = json_decode(file_get_contents(__DIR__.'/data/'.$file.'.json'), true);
		}

		return $data_array;
	}
}
