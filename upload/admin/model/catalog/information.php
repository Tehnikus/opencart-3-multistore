<?php
class ModelCatalogInformation extends Model {
	public function addInformation($data) {
		$this->db->query("
			INSERT INTO " . DB_PREFIX . "information 
			SET 
				`sort_order`   = '" . (int) $data['sort_order'] . "', 
				`bottom`       = '" . (isset($data['bottom']) ? (int) $data['bottom'] : 0) . "', 
				`status`       = '" . (int) $data['status'] . "'
			");

		$information_id = $this->db->getLastId();

		foreach ($data['information_description'] as $language_id => $value) {
			$this->db->query("
				INSERT INTO " . DB_PREFIX . "information_description 
				SET 
					`information_id`    = '" . (int) $information_id . "', 
					`language_id`       = '" . (int) $language_id . "', 
					`store_id`					= '" . (int) $this->session->data['store_id'] . "',
					`title`             = '" . $this->db->escape($value['title']) . "', 
					`description`       = '" . $this->db->escape($value['description']) . "', 
					`meta_title`        = '" . $this->db->escape($value['meta_title']) . "', 
					`meta_description`  = '" . $this->db->escape($value['meta_description']) . "', 
					`meta_keyword`      = '" . $this->db->escape($value['meta_keyword']) . "'
			");
		}

		if (isset($data['information_store'])) {
			foreach ($data['information_store'] as $store_id) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "information_to_store 
					SET 
						`information_id`  = '" . (int) $information_id . "', 
						`store_id`        = '" . (int) $store_id . "',
						`sort_order`   		= '" . (int) $data['sort_order'] . "', 
						`bottom`       		= '" . (isset($data['bottom']) ? (int) $data['bottom'] : 0) . "', 
						`status`       		= '" . (int) $data['status'] . "'
				");
			}
		}

		// SEO URL
		if (isset($data['information_seo_url'])) {
			foreach ($data['information_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "seo_url 
							SET 
								`store_id`    = '" . (int) $store_id . "', 
								`language_id` = '" . (int) $language_id . "', 
								`query`       = 'information_id=" . (int) $information_id . "', 
								`keyword`     = '" . $this->db->escape($keyword) . "'
						");
					}
				}
			}
		}
		
		if (isset($data['information_layout'])) {
			foreach ($data['information_layout'] as $store_id => $layout_id) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "information_to_layout 
					SET 
						`information_id` = '" . (int) $information_id . "', 
						`store_id`       = '" . (int) $store_id . "', 
						`layout_id`      = '" . (int) $layout_id . "'
				");
			}
		}

		return $information_id;
	}

	public function editInformation($information_id, $data) {
		$this->db->query("
			UPDATE " . DB_PREFIX . "information 
			SET 
				`sort_order`  = '" . (int) $data['sort_order'] . "', 
				`bottom`      = '" . (isset($data['bottom']) ? (int) $data['bottom'] : 0) . "', 
				`status`      = '" . (int) $data['status'] . "' 
			WHERE `information_id` = '" . (int) $information_id . "'
		");

		$this->db->query("
			DELETE FROM " . DB_PREFIX . "information_description 
			WHERE `information_id` 	= '" . (int) $information_id . "'
				AND `store_id` 				= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($data['information_description'] as $language_id => $value) {
			$this->db->query("
				INSERT INTO " . DB_PREFIX . "information_description 
				SET 
					`information_id`    = '" . (int) $information_id . "', 
					`language_id`       = '" . (int) $language_id . "', 
					`store_id`					= '" . (int) $this->session->data['store_id'] . "',
					`title`             = '" . $this->db->escape($value['title']) . "', 
					`description`       = '" . $this->db->escape($value['description']) . "', 
					`meta_title`        = '" . $this->db->escape($value['meta_title']) . "', 
					`meta_description`  = '" . $this->db->escape($value['meta_description']) . "', 
					`meta_keyword`      = '" . $this->db->escape($value['meta_keyword']) . "'
			");
		}

		// Store related data
		// Remove all unselected stores
		$this->db->query("
			DELETE FROM " . DB_PREFIX . "information_to_store
			WHERE `information_id` = '" . (int) $information_id . "'
				AND `store_id` NOT IN (" . implode(',', array_map('intval', $data['information_store'])) . ")
		");

		// Remove only current store no matter if it's selected
		$this->db->query("
			DELETE FROM " . DB_PREFIX . "information_to_store
			WHERE `information_id` 	= '" . (int) $information_id . "'
				AND `store_id` 				= '" . (int) $this->session->data['store_id'] . "'
		");

		// Write store association and store related data
		if (isset($data['information_store'])) {
			foreach ($data['information_store'] as $store_id) {
				if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
					// Set data for current store
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "information_to_store 
						SET 
							`information_id`  = '" . (int) $information_id . "', 
							`store_id`        = '" . (int) $store_id . "',
							`sort_order`   		= '" . (int) $data['sort_order'] . "', 
							`bottom`       		= '" . (isset($data['bottom']) ? (int) $data['bottom'] : 0) . "', 
							`status`       		= '" . (int) $data['status'] . "'
					");
				} else {
					// Skip if data for other stores already exists
					$this->db->query("
						INSERT IGNORE INTO " . DB_PREFIX . "information_to_store
						SET 
							`information_id` 	= '" . (int) $information_id . "',
							`store_id`    		= '" . (int) $store_id . "'
					");
				}
			}
		}

		$this->db->query("
			DELETE FROM " . DB_PREFIX . "seo_url 
			WHERE `query` = 'information_id=" . (int) $information_id . "'
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		");

		if (isset($data['information_seo_url'])) {
			foreach ($data['information_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						$this->db->query("
							INSERT INTO `" . DB_PREFIX . "seo_url` 
							SET 
								`store_id`    = '" . (int) $store_id . "', 
								`language_id` = '" . (int) $language_id . "', 
								`query`       = 'information_id=" . (int) $information_id . "', 
								`keyword`     = '" . $this->db->escape($keyword) . "'
						");
					}
				}
			}
		}

		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "information_to_layout` 
			WHERE `information_id` = '" . (int) $information_id . "'
		");

		if (isset($data['information_layout'])) {
			foreach ($data['information_layout'] as $store_id => $layout_id) {
				$this->db->query("
					INSERT INTO `" . DB_PREFIX . "information_to_layout` 
					SET 
						`information_id` = '" . (int) $information_id . "', 
						`store_id`       = '" . (int) $store_id . "', 
						`layout_id`      = '" . (int) $layout_id . "'
				");
			}
		}
	}

	public function deleteInformation($information_id) : bool {
		
		$this->db->query("START TRANSACTION");
		try {
			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "information_description` 
				WHERE `information_id` = '" . (int) $information_id . "'
					AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "information_to_store` 
				WHERE information_id = '" . (int) $information_id . "'
					AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "information_to_layout` 
				WHERE `information_id` = '" . (int) $information_id . "'
					AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "seo_url` 
				WHERE `query` = 'information_id=" . (int) $information_id . "'
					AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
			");
	
			// Update information store configs
			$this->db->query("
				UPDATE " . DB_PREFIX . "setting
					SET `value` = '0'
				WHERE `key` 			= 'config_account_id'
					AND	`value` 		= '" . (int) $information_id . "'
					AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "' 
			");
			$this->db->query("
				UPDATE " . DB_PREFIX . "setting
					SET `value` = '0'
				WHERE `key` 			= 'config_checkout_id'
					AND	`value` 		= '" . (int) $information_id . "'
					AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "' 
			");
			$this->db->query("
				UPDATE " . DB_PREFIX . "setting
					SET `value` = '0'
				WHERE `key` 			= 'config_affiliate_id'
					AND	`value` 		= '" . (int) $information_id . "'
					AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "' 
			");
			$this->db->query("
				UPDATE " . DB_PREFIX . "setting
					SET `value` = '0'
				WHERE `key` 			= 'config_return_id'
					AND	`value` 		= '" . (int) $information_id . "'
					AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "' 
			");
	
			// Next code will fully delete every information data if it is not associated to any store
			// Check if information present in other stores
			$informationInOtherStores = $this->db->query("
				SELECT
					information_id
				FROM " . DB_PREFIX . "information_to_store
				WHERE information_id  = '" . (int) $information_id . "'
					AND store_id 				<> '" . (int) $this->session->data['store_id'] . "' 
			")->num_rows;
			
			// Delete all information data row if information is not present in any other store
			if (!$informationInOtherStores) {
				$tables = [
					'information',
					'information_description',
					'information_to_layout',
				];
	
				// Remove all redundant data if present 
				foreach ($tables as $table) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . $table . "
						WHERE information_id = " . (int) $information_id
					);
				}
	
				// Cleanup URLs
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "seo_url
					WHERE `query` = 'information_id=" . (int) $information_id . "'
				");
			}

			$this->db->query("COMMIT");

			return true;
			
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	public function getInformation($information_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "information i
			LEFT JOIN " . DB_PREFIX . "information_to_store i2s
				ON i2s.information_id = i.information_id
				AND i2s.store_id = '" . (int) $this->session->data['store_id'] . "'
			WHERE i.`information_id` = '" . (int) $information_id . "'
		");

		return $query->row;
	}

	/**
	 * Get information list in admin information list /admin/controller/catalog/information.php
	 * admin default store settings /admin/controller/setting/setting.php
	 * and admin store settings /admin/controller/setting/store.php
	 * @param array $data
	 * @return array
	 */
	public function getInformations($data = []) : array {
		$result = [];
		$where = [];

		// Where clause
		// Connect to external table
		$where[] = "
			i2.information_id = i.information_id
		";
		// Set current language
		$where[] = "
			id.language_id = '" . (int)$this->config->get('config_language_id') . "'
		";

		if (isset($data['store_id'])) {
			$where[] = "
			 	i2s.store_id = '" . (int) $data['store_id'] . "'
			";
		}

		$sql = "
			SELECT 
				i.`information_id`,
				i2s.`store_id`,
				i2s.`sort_order`,
				i2s.`bottom`,
				i2s.`status`,
				(
					SELECT 
						id.title 
					FROM " . DB_PREFIX . "information_description id 
					WHERE id.information_id = i.information_id 
					ORDER BY 
						FIELD(id.store_id, '" . (int) $this->session->data['store_id'] ."') DESC,
						FIELD(id.language_id, '" . (int) $this->config->get('config_language_id') . "') DESC
						LIMIT 1
				) AS `title`,
				(SELECT JSON_ARRAYAGG(i2s.store_id) FROM " . DB_PREFIX . "information_to_store i2s WHERE i2s.information_id = i.information_id) AS stores,
				(SELECT JSON_OBJECTAGG(i2s.store_id, i2s.status) FROM " . DB_PREFIX . "information_to_store i2s WHERE i2s.information_id = i.information_id) AS status_to_store
			FROM " . DB_PREFIX . "information i 
			
			LEFT JOIN " . DB_PREFIX . "information_to_store i2s
				ON i.information_id = i2s.information_id 
				AND i2s.store_id = '" . (int) $this->session->data['store_id'] . "'

			WHERE EXISTS (
				SELECT
					1
				FROM " . DB_PREFIX . "information i2
				JOIN " . DB_PREFIX . "information_description id 
					ON i2.information_id = id.information_id
				JOIN " . DB_PREFIX . "information_to_store i2s2
					ON i2s2.information_id = i2.information_id
				WHERE " . implode(' AND ', $where) . "
			)
		";

		$sort_data = array(
			'title',
			'i2s.sort_order',
			'i2s.bottom',
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY FIELD(i2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, " . $data['sort'];
		} else {
			$sql .= " ORDER BY FIELD(i2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, title";
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

			$sql .= " LIMIT " . (int) $data['start'] . "," . (int) $data['limit'];
		}

		$query = $this->db->query($sql);

		foreach ($query->rows ?? [] as $row) {
			$row['stores'] 					= json_decode($row['stores'] ?? '[]');
			$row['status_to_store'] = json_decode($row['status_to_store'] ?? '[]', true);

			$result[] = $row;
		}

		return $result;
	}

	public function getInformationDescriptions($information_id) {
		$information_description_data = array();

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "information_description id
			WHERE id.information_id = '" . (int) $information_id . "'
				AND id.store_id 			= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$information_description_data[$result['language_id']] = array(
				'title'            => $result['title'],
				'description'      => $result['description'],
				'meta_title'       => $result['meta_title'],
				'meta_description' => $result['meta_description'],
				'meta_keyword'     => $result['meta_keyword']
			);
		}

		return $information_description_data;
	}

	public function getInformationStores($information_id) {
		$information_store_data = array();

		$query = $this->db->query("
			SELECT * FROM " . DB_PREFIX . "information_to_store WHERE `information_id` = '" . (int) $information_id . "'
		");

		foreach ($query->rows as $result) {
			$information_store_data[] = $result['store_id'];
		}

		return $information_store_data;
	}

	public function getInformationSeoUrls($information_id) {
		$information_seo_url_data = array();
		
		$query = $this->db->query("
			SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = 'information_id=" . (int) $information_id . "'
		");

		foreach ($query->rows as $result) {
			$information_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $information_seo_url_data;
	}

	public function getInformationLayouts($information_id) {
		$information_layout_data = array();

		$query = $this->db->query("
			SELECT * FROM " . DB_PREFIX . "information_to_layout WHERE `information_id` = '" . (int) $information_id . "'
		");

		foreach ($query->rows as $result) {
			$information_layout_data[$result['store_id']] = $result['layout_id'];
		}

		return $information_layout_data;
	}

	public function getTotalInformations() {
		$query = $this->db->query("
			SELECT COUNT(*) AS total FROM " . DB_PREFIX . "information
		");

		return $query->row['total'];
	}

	public function getTotalInformationsByLayoutId($layout_id) {
		$query = $this->db->query("
			SELECT COUNT(*) AS total FROM " . DB_PREFIX . "information_to_layout WHERE `layout_id` = '" . (int) $layout_id . "'
		");

		return $query->row['total'];
	}
}