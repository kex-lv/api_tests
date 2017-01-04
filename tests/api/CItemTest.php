<?php

class CItemTest extends CApiTest {

	/**
	 * @dataProvider deleteItemIfKeyExistsProvider
	 */
	public function testDeleteItemIfKeyExists($create_item_request_data, $search_item_request_data) {
		$api = new CApiRequest();

		unset($search_item_request_data['hostids']);
		$search_item_request_data['search'] = ['key_' => $create_item_request_data['key_']];

		$item_search_response = $api->request('item.get', $search_item_request_data);

		if ($item_search_response) {
			$itemids = [];

			foreach ($item_search_response as $item) {
				$itemids[] = $item['itemid'];
			}

			$item_delete_response = $api->request('item.delete', $itemids);

			$this->assertGreaterThan(0, count($item_delete_response));
			$this->assertArrayHasKey('itemids', $item_delete_response);
			$this->assertCount(count($itemids), $item_delete_response['itemids']);
		}
	}

	/**
	 * @dataProvider itemCreateProvider
	 */
	public function testItemCreate(
		$get_all_hosts_request_data,
		$create_item_request_data,
		$get_item_request_data,
		$get_item_response_data,
		$update_item_request_data,
		$search_item_request_data
	) {
		$api = new CApiRequest();

		$hosts = $api->request('host.get', $get_all_hosts_request_data);
		$this->assertGreaterThan(0, count($hosts));

		$host = reset($hosts);
		$hostid = $host['hostid'];
		$this->assertTrue(is_numeric($hostid));

		// get interfaceid
		$this->assertArrayHasKey('interfaces', $host);
		$this->assertGreaterThan(0, count($host['interfaces']));
		$interfaceid = reset($host['interfaces'])['interfaceid'];

		// get applications
		$this->assertArrayHasKey('applications', $host);
		$this->assertGreaterThan(0, count($host['applications']));
		$applicationid = reset($host['applications'])['applicationid'];

		$create_item_request_data['hostid'] = $hostid;
		$create_item_request_data['interfaceid'] = $interfaceid;
		$create_item_request_data['applications'] = [$applicationid];

		// get lasts item id

		$create_item_response = $api->request('item.create', $create_item_request_data);
		$this->assertTrue(array_key_exists('itemids', $create_item_response));

		$itemid = reset($create_item_response['itemids']);
		$this->assertTrue(is_numeric($itemid));

		//assert id is correctly incremented

		// get item
		$get_item_request_data['itemids'] = [$itemid];
		$get_item_response = $api->request('item.get', $get_item_request_data);
		$this->assertCount(1, $get_item_response);

		unset($create_item_request_data['applications']);
		$get_item_response_data = array_merge($get_item_response_data, $create_item_request_data);
		$get_item_response_data['itemid'] = $itemid;
		$this->assertEquals([0 => $get_item_response_data], $get_item_response);

		$get_item_response = reset($get_item_response);
		$this->assertEmpty($get_item_response['error']);
		$this->assertContains($itemid, $get_item_response);
		$this->assertArrayHasKey('itemid', $get_item_response);
		$this->assertArraySubset($create_item_request_data, $get_item_response);

		// update item
		$update_item_request_data['itemid'] = $itemid;
		$update_item_request_data['applications'] = [$applicationid];
		$update_item_response = $api->request('item.update', $update_item_request_data);
		$this->assertTrue(array_key_exists('itemids', $update_item_response));
		$this->assertTrue(is_numeric(reset($update_item_response['itemids'])));


		// get item

		// delete item
		$delete_item_response = $api->request('item.delete', [$itemid]);
		$this->assertGreaterThan(0, count($delete_item_response));
		$this->assertCount(1, $delete_item_response);
		$this->assertArrayHasKey('itemids', $delete_item_response);
	}

	public function itemCreateProvider() {
		return $this->read_json([
			'host_get_all_request',
			'item_create_request',
			'item_get_request',
			'item_get_response',
			'item_update_request',
			'item_search_request'
		]);
	}

	public function deleteItemIfKeyExistsProvider() {
		return $this->read_json([
			'item_create_request',
			'item_search_request'
		]);
	}
}
