<?php
class ModelLocalisationLanguage extends Model {
	public function addLanguage($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "language SET name = '" . $this->db->escape($data['name']) . "', code = '" . $this->db->escape($data['code']) . "', locale = '" . $this->db->escape($data['locale']) . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "'");
		
		$language_id = $this->db->getLastId();
		$source_language_id = $this->config->get('config_language_id');

		// Language to store association
		if (isset($data['stores_association']) && !empty($data['stores_association'])) {
			foreach ($data['stores_association'] as $store_id) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "language_to_store
					SET
						`language_id` 				= '" . (int) $language_id . "', 
						`store_id` 		 				= '" . (int) $store_id . "'
				");
			}
		}

		// New language add 
		return $this->cloneLanguage($language_id, $source_language_id);		
	}

	public function editLanguage($language_id, $data) {
		$language_query = $this->db->query("SELECT `code` FROM " . DB_PREFIX . "language WHERE language_id = '" . (int)$language_id . "'");
		
		$this->db->query("UPDATE " . DB_PREFIX . "language SET name = '" . $this->db->escape($data['name']) . "', code = '" . $this->db->escape($data['code']) . "', locale = '" . $this->db->escape($data['locale']) . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "' WHERE language_id = '" . (int)$language_id . "'");

		if ($language_query->row['code'] != $data['code']) {
			$this->db->query("UPDATE " . DB_PREFIX . "setting SET value = '" . $this->db->escape($data['code']) . "' WHERE `key` = 'config_language' AND value = '" . $this->db->escape($language_query->row['code']) . "'");
			$this->db->query("UPDATE " . DB_PREFIX . "setting SET value = '" . $this->db->escape($data['code']) . "' WHERE `key` = 'config_admin_language' AND value = '" . $this->db->escape($language_query->row['code']) . "'");
		}

		// Language to store association
		$this->db->query("
			DELETE FROM " . DB_PREFIX . "language_to_store
			WHERE language_id = '" . (int) $language_id . "'
		");

		if (isset($data['stores_association']) && !empty($data['stores_association'])) {
			foreach ($data['stores_association'] as $store_id) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "language_to_store
					SET
						`language_id` 				= '" . (int) $language_id . "', 
						`store_id` 		 				= '" . (int) $store_id . "'
				");
			}
		}
	}
	
	public function deleteLanguage($language_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "language WHERE language_id = '" . (int)$language_id . "'");
 		$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE language_id = '" . (int)$language_id . "'"); 

		// Language to store association
		$this->db->query("
			DELETE FROM " . DB_PREFIX . "language_to_store
			WHERE language_id = '" . (int) $language_id . "'
		");
	}

	public function getLanguage($language_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "language WHERE language_id = '" . (int)$language_id . "'");

		return $query->row;
	}

	public function getLanguages($data = array()) {
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "language";

			$sort_data = array(
				'name',
				'code',
				'sort_order'
			);

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];
			} else {
				$sql .= " ORDER BY sort_order, name";
			}

			if (isset($data['order']) && ($data['order'] == 'DESC')) {
				$sql .= " DESC";
			} else {
				$sql .= " ASC";
			}

			if (isset($data['start']) || isset($data['limit'])) {
				if ($data['start'] < 0) {
					$data['start'] = 0;
				}

				if ($data['limit'] < 1) {
					$data['limit'] = 20;
				}

				$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
			}

			$query = $this->db->query($sql);

			return $query->rows;
		} else {
			$language_data = array();
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "language ORDER BY sort_order, name");
			foreach ($query->rows as $result) {
				$language_data[$result['code']] = array(
					'language_id' => $result['language_id'],
					'name'        => $result['name'],
					'code'        => $result['code'],
					'locale'      => $result['locale'],
					'image'       => $result['image'],
					'directory'   => $result['directory'],
					'sort_order'  => $result['sort_order'],
					'status'      => $result['status']
				);
			}

			return $language_data;
		}
	}

	public function getLanguageByCode($code) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE code = '" . $this->db->escape($code) . "'");

		return $query->row;
	}

	public function getTotalLanguages() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "language");

		return $query->row['total'];
	}

	public function cloneLanguage($new_language_id, $source_language_id) {

		$tables = [
			'attribute_description',
			'attribute_group_description',
			'banner_image',
			'category_description',
			'customer_group_description',
			'custom_field_description',
			'custom_field_value_description',
			'download_description',
			'filter_description',
			'filter_group_description',
			'information_description',
			'length_class_description',
			'option_description',
			'option_value_description',
			'order_status',
			'product_attribute',
			'product_description',
			'recurring_description',
			'return_action',
			'return_reason',
			'return_status',
			'stock_status',
			'voucher_theme_description',
			'weight_class_description',
			// New tables 
			'manufacturer_description',
			// 'blog_article_image_description',
			// 'blog_category_image_description',
			// 'category_image_description',
			// 'product_image_description',
			// 'filter_page_image_description',
			// 'search_page_image_description',
			// 'blog_category_description',
			// 'blog_article_description',
			// 'search_page_description',
			// 'filter_page_description',
			// 'tag_page_description',
			// 'supplier_description',
		];

		$this->db->query("START TRANSACTION");

		try {

			foreach ($tables as $table) {

				$result = $this->db->query("
					SHOW COLUMNS FROM `" . DB_PREFIX . $table . "`
				");

				$columns = [];
				$select_columns = [];

				foreach ($result->rows as $row) {
					$column = $row['Field'];

					$columns[] = "`$column`";

					if ($column === 'language_id') {
						$select_columns[] = (int)$new_language_id . " AS `language_id`";
					} else {
						$select_columns[] = "`$column`";
					}
				}

				$column_list = implode(',', $columns);
				$select_list = implode(',', $select_columns);

				$sql = "
					INSERT IGNORE INTO `" . DB_PREFIX . $table . "` ($column_list)
					SELECT $select_list
					FROM `" . DB_PREFIX . $table . "`
					WHERE `language_id` = '" . (int) $source_language_id . "'
				";

				$this->db->query($sql);
			}

			$this->db->query("COMMIT");

			return $new_language_id;

		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	public function getStoresAssociation($id = null) : array {
		$result = [];

		if (!$id) {
			return $result;
		}

		// Get stores association
		$storeData = $this->db->query("
			SELECT
				store_id
			FROM `" . DB_PREFIX . "language_to_store`
			WHERE language_id = '" . (int) $id . "'
		");
		foreach ($storeData->rows as $store) {
			$result[] = $store['store_id']; 
		}

		return $result;
	}
}
