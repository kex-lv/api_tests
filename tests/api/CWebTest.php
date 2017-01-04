<?php

class CWebTest extends CApiTest {
	private static $httptestid = 0;
	private static $hostid = 0;
	private static $applicationid = 0;
	private static $db_httptest = [];

	/**
	 * @dataProvider deleteHttptestIfNameExistsProvider
	 */
	public function testDeleteHttptestIfNameExists($create_httptest_request_data, $get_httptest_request_data) {
		unset($get_httptest_request_data['httptestids']);
		$get_httptest_request_data['search'] = ['name' => $create_httptest_request_data['name']];

		$get_httptest_response = $this->request('httptest.get', $get_httptest_request_data);

		if ($get_httptest_response) {
			$httptestids = [];

			foreach ($get_httptest_response as $httptest) {
				$httptestids[] = $httptest['httptestid'];
			}

			$delete_httptest_response = $this->request('httptest.delete', $httptestids);

			$this->assertArrayHasKey('httptestids', $delete_httptest_response);
			$this->assertCount(count($httptestids), $delete_httptest_response['httptestids']);
		}
	}

	/**
	 * @dataProvider getHostProvider
	 */
	public function testGetHost($get_all_hosts_request_data) {
		$hosts = $this->request('host.get', $get_all_hosts_request_data);
		$this->assertGreaterThan(0, count($hosts));

		$host = reset($hosts);
		$this->assertTrue(is_numeric(self::$hostid));
		self::$hostid = $host['hostid'];

		$this->assertArrayHasKey('applications', $host);
		$this->assertGreaterThan(0, count($host['applications']));
		self::$applicationid = reset($host['applications'])['applicationid'];
	}

	/**
	 * @dataProvider deleteAllHostHttptestsProvider
	 *
	 * @depends testGetHost
	 */
	public function testDeleteAllHostHttptests($get_httptest_request_data, $search_web_items_request_data) {
		$get_httptest_request_data['hostids'] = [self::$hostid];
		$get_httptest_response = $this->request('httptest.get', $get_httptest_request_data);

		if ($get_httptest_response) {
			$httptestids = [];

			foreach ($get_httptest_response as $httptest) {
				$httptestids[] = $httptest['httptestid'];
			}

			$delete_httptest_response = $this->request('httptest.delete', $httptestids);

			$this->assertArrayHasKey('httptestids', $delete_httptest_response);
			$this->assertCount(count($httptestids), $delete_httptest_response['httptestids']);
		}

		// check host has no web item
		$search_web_items_request_data['hostids'] = [self::$hostid];
		$search_web_items_response = $this->request('item.get', $search_web_items_request_data);
		$this->assertEmpty($search_web_items_response);
	}

	/**
	 * @dataProvider createHttptestRequestProvider
	 *
	 * @depends testDeleteAllHostHttptests
	 */
	public function testHttptestCreate($create_httptest_request_data) {
		$create_httptest_request_data['hostid'] = self::$hostid;
		$create_httptest_response = $this->request('httptest.create', $create_httptest_request_data);
		$this->assertArrayHasKey('httptestids', $create_httptest_response);
		$this->assertCount(1, $create_httptest_response['httptestids']);

		$httptestid = reset($create_httptest_response['httptestids']);
		$this->assertTrue(is_numeric($httptestid));
		self::$httptestid = $httptestid;
	}

	/**
	 * @dataProvider httptestGetProvider
	 *
	 * @depends testHttptestCreate
	 */
	public function testHttptestGet($create_httptest_request_data, $get_httptest_request_data, $get_httptest_response_data) {
		$get_httptest_request_data['httptestids'] = [self::$httptestid];
		$get_httptest_response = $this->request('httptest.get', $get_httptest_request_data);
		$this->assertCount(1, $get_httptest_response);

		$get_httptest_response = reset($get_httptest_response);
		$this->assertArrayHasKey('steps', $get_httptest_response);
		$this->assertCount(count($create_httptest_request_data['steps']), $get_httptest_response['steps']);

		$step_fields = reset($get_httptest_response_data['steps']);
		$steps_response_data = [];
		foreach ($create_httptest_request_data['steps'] as $step_key => $step) {
			$step_data = array_merge($step_fields, $step);
			$step_data['httpstepid'] = $get_httptest_response['steps'][$step_key]['httpstepid'];
			$step_data['httptestid'] = self::$httptestid;
			$steps_response_data[] = $step_data;
		}

		$create_httptest_request_data['hostid'] = self::$hostid;
		$get_httptest_response_data = array_merge($get_httptest_response_data, $create_httptest_request_data);
		$get_httptest_response_data['httptestid'] = self::$httptestid;
		$get_httptest_response_data['steps'] = $steps_response_data;
		$this->assertEquals($get_httptest_response_data, $get_httptest_response);

		$this->assertEmpty($get_httptest_response['http_proxy']);
		$this->assertContains(self::$httptestid, $get_httptest_response);
		$this->assertArrayHasKey('httptestid', $get_httptest_response);
		$this->assertArraySubset($create_httptest_request_data, $get_httptest_response);

		self::$db_httptest = $get_httptest_response_data;
	}

	/**
	 * @dataProvider httptestUpdateProvider
	 *
	 * @depends testHttptestGet
	 */
	public function testHttptestUpdate($update_httptest_request_data, $search_web_items_request_data) {
		$update_httptest_request_data['httptestid'] = self::$httptestid;
		$update_httptest_request_data['applicationid'] = self::$applicationid;
		$update_httptest_response = $this->request('httptest.update', $update_httptest_request_data);
		$this->assertTrue(array_key_exists('httptestids', $update_httptest_response));
		$this->assertTrue(is_numeric(reset($update_httptest_response['httptestids'])));

		// check applicationid for all items
		$search_web_items_request_data['hostids'] = [self::$hostid];
		$search_web_items_response = $this->request('item.get', $search_web_items_request_data);
		$this->assertCount(9, $search_web_items_response);

		foreach ($search_web_items_response as $web_item) {
			$this->assertArrayHasKey('applications', $web_item);
			$this->assertCount(1, $web_item['applications']);
			$this->assertEquals(self::$applicationid, $web_item['applications'][0]['applicationid']);
			$this->assertEquals(self::$hostid, $web_item['applications'][0]['hostid']);
			$this->assertArraySubset(['applicationid' => self::$applicationid, 'hostid' => self::$hostid], $web_item['applications'][0]);
		}
	}

	/**
	 * @dataProvider httptestUpdateProvider
	 *
	 * @depends testHttptestGet
	 */
	public function test_update_applicationid($update_httptest_request_data, $search_web_items_request_data) {
		// set application id to 0
		$update_httptest_request_data['httptestid'] = self::$httptestid;
		$update_httptest_request_data['applicationid'] = 0;
		$update_httptest_response = $this->request('httptest.update', $update_httptest_request_data);

		// check applicationid for all items
		$search_web_items_request_data['hostids'] = [self::$hostid];
		$search_web_items_response = $this->request('item.get', $search_web_items_request_data);
		$this->assertCount(9, $search_web_items_response);

		foreach ($search_web_items_response as $web_item) {
			$this->assertArrayHasKey('applications', $web_item);
			$this->assertEmpty($web_item['applications']);
		}
	}

	/**
	 * @dataProvider httptestUpdateProvider
	 *
	 * @depends testHttptestGet
	 */
	public function test_update_httptest_with_steps($update_httptest_request_data, $search_web_items_request_data) {
		$update_httptest_request_data['httptestid'] = self::$httptestid;
		$update_httptest_request_data['applicationid'] = self::$applicationid;
		$update_httptest_request_data['steps'] = self::$db_httptest['steps'];
		$update_httptest_response = $this->request('httptest.update', $update_httptest_request_data);
		$this->assertTrue(array_key_exists('httptestids', $update_httptest_response));
		$this->assertTrue(is_numeric(reset($update_httptest_response['httptestids'])));

		// check applicationid for all items
		$search_web_items_request_data['hostids'] = [self::$hostid];
		$search_web_items_response = $this->request('item.get', $search_web_items_request_data);
		$this->assertCount(9, $search_web_items_response);

		foreach ($search_web_items_response as $web_item) {
			$this->assertArrayHasKey('applications', $web_item);
			$this->assertCount(1, $web_item['applications'], 'item has updated application');
			$this->assertEquals(self::$applicationid, $web_item['applications'][0]['applicationid']);
			$this->assertEquals(self::$hostid, $web_item['applications'][0]['hostid']);
			$this->assertArraySubset(['applicationid' => self::$applicationid, 'hostid' => self::$hostid], $web_item['applications'][0]);
		}
	}

	/**
	 * Update only applicationid for httptest and all steps.
	 *
	 * @dataProvider httptestUpdateProvider
	 *
	 * @depends testHttptestGet
	 */
	public function test_update_only_applicationid($update_httptest_request_data, $search_web_items_request_data) {
		// set application id to 0
		$update_httptest_request_data['httptestid'] = self::$httptestid;
		$update_httptest_request_data['applicationid'] = 0;
		$update_httptest_response = $this->request('httptest.update', $update_httptest_request_data);

		// upate httptest with steps, update only application
		$update_httptest_app_request_data = ['httptestid' => self::$httptestid];
		$update_httptest_app_request_data['applicationid'] = self::$applicationid;
		$update_httptest_app_request_data['steps'] = self::$db_httptest['steps'];
		$update_httptest_response = $this->request('httptest.update', $update_httptest_app_request_data);
		$this->assertTrue(array_key_exists('httptestids', $update_httptest_response));
		$this->assertTrue(is_numeric(reset($update_httptest_response['httptestids'])));

		// check applicationid for all items
		$search_web_items_request_data['hostids'] = [self::$hostid];
		$search_web_items_response = $this->request('item.get', $search_web_items_request_data);
		$this->assertCount(9, $search_web_items_response);

		foreach ($search_web_items_response as $web_item) {
			$this->assertArrayHasKey('applications', $web_item);
			$this->assertCount(1, $web_item['applications'], 'item has updated application');
			$this->assertEquals(self::$applicationid, $web_item['applications'][0]['applicationid']);
			$this->assertEquals(self::$hostid, $web_item['applications'][0]['hostid']);
			$this->assertArraySubset(['applicationid' => self::$applicationid, 'hostid' => self::$hostid], $web_item['applications'][0]);
		}
	}

	/**
	 * @depends test_update_only_applicationid
	 */
	public function test_delete_httptest() {
		$delete_httptest_response = $this->request('httptest.delete', [self::$httptestid]);

		$this->assertArrayHasKey('httptestids', $delete_httptest_response);
		$this->assertCount(1, $delete_httptest_response['httptestids']);
	}

	public function deleteHttptestIfNameExistsProvider() {
		return $this->read_json([
			'httptest_create_request',
			'httptest_get_request'
		]);
	}

	public function getHostProvider() {
		return $this->read_json(['host_get_all_request']);
	}

	public function deleteAllHostHttptestsProvider() {
		return $this->read_json([
			'httptest_get_request',
			'web_items_search_request'
		]);
	}

	public function createHttptestRequestProvider() {
		return $this->read_json(['httptest_create_request']);
	}

	public function httptestGetProvider() {
		return $this->read_json([
			'httptest_create_request',
			'httptest_get_request',
			'httptest_get_response'
		]);
	}

	public function httptestUpdateProvider() {
		return $this->read_json([
			'httptest_update_request',
			'web_items_search_request'
		]);
	}
}
