<?php
class ModelSettingStore extends Model {
	public function addStore($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "store SET name = '" . $this->db->escape($data['config_name']) . "', `url` = '" . $this->db->escape($data['config_url']) . "', `ssl` = '" . $this->db->escape($data['config_ssl']) . "'");

		$store_id = $this->db->getLastId();

		// Language to store association
		$this->db->query("
			DELETE FROM " . DB_PREFIX . "language_to_store
			WHERE store_id = '" . (int) $store_id . "'
		");

		if (isset($data['languages_association']) && !empty($data['languages_association'])) {
			foreach ($data['languages_association'] as $language_id) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "language_to_store
					SET
						`language_id` 				= '" . (int) $language_id . "', 
						`store_id` 		 				= '" . (int) $store_id . "'
				");
			}
		}
		
		return $store_id;
	}

	public function editStore($store_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "store SET name = '" . $this->db->escape($data['config_name']) . "', `url` = '" . $this->db->escape($data['config_url']) . "', `ssl` = '" . $this->db->escape($data['config_ssl']) . "' WHERE store_id = '" . (int)$store_id . "'");

		// Language to store association
		$this->db->query("
			DELETE FROM " . DB_PREFIX . "language_to_store
			WHERE store_id = '" . (int) $store_id . "'
		");

		if (isset($data['languages_association']) && !empty($data['languages_association'])) {
			foreach ($data['languages_association'] as $language_id) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "language_to_store
					SET
						`language_id` 				= '" . (int) $language_id . "', 
						`store_id` 		 				= '" . (int) $store_id . "'
				");
			}
		}

		// Install selected theme if not installed
		$this->load->model('setting/extension');
		$this->model_setting_extension->install('theme', $data['config_theme']);

		return (int) $store_id;
	}

	public function deleteStore($store_id = 0) {

	if ($store_id === 0) {
		throw new Exception("You cannot delete default store");
	}

	$this->db->query("START TRANSACTION");

	try {

		// Get all tables with store_id
		$tablesToDelete = $this->db->query("
			SELECT DISTINCT TABLE_NAME AS `table`
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE COLUMN_NAME = 'store_id'
				AND TABLE_SCHEMA = '" . DB_DATABASE . "'
				AND TABLE_NAME LIKE '" . DB_PREFIX . "%'
		")->rows;

		foreach ($tablesToDelete as $table) {

			$this->db->query("
				DELETE FROM `" . $table['table'] . "`
				WHERE store_id = '" . (int) $store_id . "'
			");
		}

		$this->db->query("COMMIT");

	} catch (\Throwable $e) {

		$this->db->query("ROLLBACK");
		throw $e;
	}
}

	public function getStore($store_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "store WHERE store_id = '" . (int)$store_id . "'");

		return $query->row;
	}

	public function getStores($data = array()) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "store ORDER BY url");
		$store_data = $query->rows;
		return $store_data;
	}

	public function getTotalStores() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "store");

		return $query->row['total'];
	}

	public function getTotalStoresByLayoutId($layout_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting WHERE `key` = 'config_layout_id' AND `value` = '" . (int)$layout_id . "' AND store_id != '0'");

		return $query->row['total'];
	}

	public function getTotalStoresByLanguage($language) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting WHERE `key` = 'config_language' AND `value` = '" . $this->db->escape($language) . "' AND store_id != '0'");

		return $query->row['total'];
	}

	public function getTotalStoresByCurrency($currency) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting WHERE `key` = 'config_currency' AND `value` = '" . $this->db->escape($currency) . "' AND store_id != '0'");

		return $query->row['total'];
	}

	public function getTotalStoresByCountryId($country_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting WHERE `key` = 'config_country_id' AND `value` = '" . (int)$country_id . "' AND store_id != '0'");

		return $query->row['total'];
	}

	public function getTotalStoresByZoneId($zone_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting WHERE `key` = 'config_zone_id' AND `value` = '" . (int)$zone_id . "' AND store_id != '0'");

		return $query->row['total'];
	}

	public function getTotalStoresByCustomerGroupId($customer_group_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting WHERE `key` = 'config_customer_group_id' AND `value` = '" . (int)$customer_group_id . "' AND store_id != '0'");

		return $query->row['total'];
	}

	public function getTotalStoresByInformationId($information_id) {
		$account_query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting WHERE `key` = 'config_account_id' AND `value` = '" . (int)$information_id . "' AND store_id != '0'");

		$checkout_query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting WHERE `key` = 'config_checkout_id' AND `value` = '" . (int)$information_id . "' AND store_id != '0'");

		return ($account_query->row['total'] + $checkout_query->row['total']);
	}

	public function getTotalStoresByOrderStatusId($order_status_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "setting WHERE `key` = 'config_order_status_id' AND `value` = '" . (int)$order_status_id . "' AND store_id != '0'");

		return $query->row['total'];
	}

	public function getMultistores() {
		$stores = [];

		$stores[0] = [
			'store_id' => 0,
			'name'     => $this->config->get('config_name'),
			'url'	     => HTTPS_CATALOG
		];

		$multiStores = $this->getStores();

		foreach ($multiStores as $store) {
			$stores[(int) $store['store_id']] = [
				'store_id' => (int) $store['store_id'],
				'name'     => $store['name'],
				'url'	     => $this->request->server['HTTPS'] ? $store['ssl'] : $store['url'],
			];
		}

		return $stores;
	}

	// Language to store association
		public function getLanguagesAssociation($id = null) : array {
		$result = [];

		if (!isset($id)) {
			$id === 0;
		}

		// Get stores association
		$storeData = $this->db->query("
			SELECT
				language_id
			FROM `" . DB_PREFIX . "language_to_store`
			WHERE store_id = '" . (int) $id . "'
		");
		foreach ($storeData->rows as $store) {
			$result[] = $store['language_id']; 
		}

		return $result;
	}

	public function cloneLayouts($targetStoreId) : array {
		
	$layoutMap = [];
		// Get default store layouts
		$layouts = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "layout
			WHERE store_id = 0
		")->rows;

		// Copy new layouts with new store_id and map them to create associated routes
		foreach ($layouts as $layout) {
			$this->db->query("
				INSERT INTO " . DB_PREFIX . "layout
				SET 
					name 			= '" . $this->db->escape($layout['name']) . "',
					store_id 	= '" . (int) $targetStoreId . "'
			");
			$new_layout_id = $this->db->getLastId();
			$layoutMap[$layout['layout_id']] = $new_layout_id;
		}

		// Copy routes
		foreach ($layoutMap as $oldId => $newId) {
			// Get routes from default store
			$routes = $this->db->query("
				SELECT 
					*
				FROM " . DB_PREFIX . "layout_route
				WHERE layout_id = '" . (int) $oldId . "'
					AND store_id = 0
			")->rows;

			// Copy each route and assign new layout_id as its parent
			foreach ($routes as $route) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "layout_route
					SET 
						layout_id   = '" . (int) $newId . "',
						store_id    = '" . (int) $targetStoreId . "',
						route       = '" . $this->db->escape($route['route']) . "',
						is_wildcard = '" . (int) $route['is_wildcard'] . "'
				");
			}
		}

		return $layoutMap;
	}

	// Get all themes, installed and not installed
	public function getAllThemes() : array {
		$themes = [];

		$directories = glob(DIR_CATALOG . 'view/theme/*', GLOB_ONLYDIR);

		foreach ($directories as $directory) {
			$themes[] = basename($directory);
		}

		return $themes;
	}
}