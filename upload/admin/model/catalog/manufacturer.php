<?php
class ModelCatalogManufacturer extends Model {
	public function addManufacturer($data) : int {

		$this->db->query("START TRANSACTION");
		
		try {

			$this->db->query("
				INSERT INTO " . DB_PREFIX . "manufacturer 
				SET 
					`name` 				= '" . $this->db->escape($data['manufacturer_description'][(int) $this->config->get('config_language_id')]['name']) . "', 
					`sort_order` 	= '" . (int) $data['sort_order'] . "',
					`image` 			= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "'
			");

			$manufacturer_id = $this->db->getLastId();

			if (isset($data['manufacturer_store'])) {
				foreach ($data['manufacturer_store'] as $store_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "manufacturer_to_store 
						SET 
							`manufacturer_id` = '" . (int) $manufacturer_id . "', 
							`store_id` 				= '" . (int) $store_id . "',
							`sort_order` 			= '" . (int) $data['sort_order'] . "',
							`image` 					= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "'
					");
				}
			}

			foreach ($data['manufacturer_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "manufacturer_description 
					SET 
						`manufacturer_id` 	= '" . (int) $manufacturer_id . "', 
						`language_id` 			= '" . (int) $language_id . "', 
						`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
						`name` 							= '" . $this->db->escape($value['name']) . "', 
						`description` 			= '" . $this->db->escape($value['description']) . "', 
						`meta_title` 				= '" . $this->db->escape($value['meta_title']) . "', 
						`meta_description` 	= '" . $this->db->escape($value['meta_description']) . "', 
						`meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "'
				");
			}
					
			// SEO URL
			if (isset($data['manufacturer_seo_url'])) {
				foreach ($data['manufacturer_seo_url'] as $store_id => $language) {
					foreach ($language as $language_id => $keyword) {
						if (!empty($keyword)) {
							$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'manufacturer_id=" . (int)$manufacturer_id . "', keyword = '" . $this->db->escape($keyword) . "'");
						}
					}
				}
			}

			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_value_id: $manufacturer_id, facet_type: 5, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_value_id: $manufacturer_id, facet_type: 5, store_id: $store_id);
			
			return $manufacturer_id;
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	public function editManufacturer($manufacturer_id, $data) : int {

		$this->db->query("START TRANSACTION");
		
		try {

			$this->db->query("
				UPDATE " . DB_PREFIX . "manufacturer 
				SET 
					`name` 				= '" . $this->db->escape($data['name']) . "', 
					`sort_order` 	= '" . (int) $data['sort_order'] . "',
					`image` 			= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "'
				WHERE manufacturer_id = '" . (int)$manufacturer_id . "'
			");

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "manufacturer_description 
				WHERE `manufacturer_id` = '" . (int) $manufacturer_id . "'
					AND `store_id` 				= '" . (int) $this->session->data['store_id'] . "'
			");
	
			foreach ($data['manufacturer_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "manufacturer_description 
					SET 
						`manufacturer_id` 	= '" . (int) $manufacturer_id . "', 
						`language_id` 			= '" . (int) $language_id . "', 
						`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
						`name` 							= '" . $this->db->escape($value['name']) . "', 
						`description` 			= '" . $this->db->escape($value['description']) . "',
						`meta_title` 				= '" . $this->db->escape($value['meta_title']) . "', 
						`meta_description` 	= '" . $this->db->escape($value['meta_description']) . "', 
						`meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "'
				");
			}

			// Store related data
			// Remove all unselected stores
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "manufacturer_to_store
				WHERE `manufacturer_id` = '" . (int) $manufacturer_id . "'
					AND `store_id` NOT IN (" . implode(',', array_map('intval', $data['manufacturer_store'])) . ")
			");

			// Remove only current store no matter if it's selected
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "manufacturer_to_store
				WHERE `manufacturer_id` = '" . (int) $manufacturer_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			// Write store association and store related data
			if (isset($data['manufacturer_store'])) {
				foreach ($data['manufacturer_store'] as $store_id) {
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						// Set data for current store
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "manufacturer_to_store 
							SET 
								`manufacturer_id` = '" . (int) $manufacturer_id . "', 
								`store_id` 				= '" . (int) $store_id . "',
								`sort_order` 			= '" . (int) $data['sort_order'] . "',
								`image` 					= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "'
						");
					} else {
						// Skip if data for other stores already exists
						$this->db->query("
							INSERT IGNORE INTO " . DB_PREFIX . "manufacturer_to_store
							SET 
								`manufacturer_id` = '" . (int) $manufacturer_id . "',
								`store_id`    		= '" . (int) $store_id . "'
						");
					}
				}
			}

			$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'manufacturer_id=" . (int)$manufacturer_id . "'");

			if (isset($data['manufacturer_seo_url'])) {
				foreach ($data['manufacturer_seo_url'] as $store_id => $language) {
					foreach ($language as $language_id => $keyword) {
						if (!empty($keyword)) {
							$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'manufacturer_id=" . (int)$manufacturer_id . "', keyword = '" . $this->db->escape($keyword) . "'");
						}
					}
				}
			}

			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_value_id: $manufacturer_id, facet_type: 5, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_value_id: $manufacturer_id, facet_type: 5, store_id: $store_id);
			
			return $manufacturer_id;
			
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	public function deleteManufacturer($manufacturer_id) : bool {
		$this->db->query("START TRANSACTION");
		
		try {

			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "manufacturer_to_store` 
				WHERE manufacturer_id = '" . (int) $manufacturer_id . "'
					AND store_id 				= '" . (int) $this->session->data['store_id'] . "'
			");

			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "manufacturer_description` 
				WHERE manufacturer_id = '" . (int) $manufacturer_id . "'
					AND store_id 				= '" . (int) $this->session->data['store_id'] . "'
			");

			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "seo_url` 
				WHERE query 		= 'manufacturer_id=" . (int) $manufacturer_id . "'
					AND store_id 	= '" . (int) $this->session->data['store_id'] . "'
			");
			
			// Check manufacturer in other stores
			$manufacturerInOtherStores = $this->db->query("
				SELECT
					manufacturer_id
				FROM " . DB_PREFIX . "manufacturer_to_store
				WHERE manufacturer_id  = '" . (int) $manufacturer_id . "'
					AND store_id 		<> '" . (int) $this->session->data['store_id'] . "' 
			")->num_rows;

			if (!$manufacturerInOtherStores) {
				$tables = [
					'manufacturer',
					'manufacturer_description',
				];

				// Remove all redundant data if present 
				foreach ($tables as $table) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . $table . "
						WHERE manufacturer_id = " . (int) $manufacturer_id
					);
				}

				// Cleanup URLs
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "seo_url
					WHERE `query` = 'manufacturer_id=" . (int) $manufacturer_id . "'
				");

				// Update manufacturer to product connection
				$this->db->query("
					UPDATE " . DB_PREFIX . "product
					SET
						manufacturer_id = '0'
					WHERE manufacturer_id = '" . (int) $manufacturer_id . "'
				");
				
			}
			
			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_value_id: $manufacturer_id, facet_type: 5, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_value_id: $manufacturer_id, facet_type: 5, store_id: $store_id);

			return true;

		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	// Get manufacturer_id and name
	// Used in manufacturer form and product form
	// Should always rely on store_id
	public function getManufacturer($manufacturer_id) {
		$query = $this->db->query("
			SELECT 
				m.`manufacturer_id`,
				m2s.`image`,
				m2s.`sort_order`,
				md.`name`
			FROM " . DB_PREFIX . "manufacturer m
			LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s
				ON m2s.manufacturer_id 	= m.manufacturer_id
			LEFT JOIN " . DB_PREFIX . "manufacturer_description md
				ON md.manufacturer_id = m.manufacturer_id
				AND md.store_id = m2s.store_id
			WHERE m.manufacturer_id = '" . (int) $manufacturer_id . "'
				AND m2s.store_id 			= '" . (int) $this->session->data['store_id'] . "' 
		");

		return $query->row;
	}

	public function getManufacturers($data = []) : array {
		$result = [];
		$where = [];

		$where[] = "
			m.manufacturer_id = m2s.manufacturer_id
		";
		if (isset($data['filter_name'])) {
			$where[] = "md.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}
		if (isset($data['store_id'])) {
			$where[] = "m2s.store_id = '" . (int) $data['store_id'] . "'";
		}

		$sql = "
			SELECT
				m.manufacturer_id,
				m2s.sort_order,
				m2s.image,
				(
					SELECT 
						md.name 
					FROM " . DB_PREFIX . "manufacturer_description md
					WHERE md.manufacturer_id = m.manufacturer_id
					ORDER BY 
						FIELD(md.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, 
						FIELD(md.language_id, '" . (int) $this->config->get('config_language_id') . "') DESC
					LIMIT 1
				) AS `name`,
				(SELECT COUNT(product_id) FROM " . DB_PREFIX . "product p WHERE p.manufacturer_id = m.manufacturer_id) AS product_count,
				(SELECT JSON_ARRAYAGG(m2s.store_id) FROM " . DB_PREFIX . "manufacturer_to_store m2s WHERE m.manufacturer_id = m2s.manufacturer_id) AS stores
				FROM " . DB_PREFIX . "manufacturer m
				LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s
					ON m2s.manufacturer_id = m.manufacturer_id
					AND m2s.store_id = '" . (int) $this->session->data['store_id'] . "'
				WHERE EXISTS (
					SELECT
						1
					FROM " . DB_PREFIX . "manufacturer_to_store m2s
					JOIN " . DB_PREFIX . "manufacturer_description md
						ON md.manufacturer_id = m2s.manufacturer_id
						AND md.store_id = m2s.store_id
					WHERE " . implode(' AND ', $where) . "
				)
		";

		$sort_data = array(
			'name',
			'product_count',
			'm2s.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY FIELD(m2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, " . $data['sort'];
		} else {
			$sql .= " ORDER BY FIELD(m2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, name";
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

		foreach ($query->rows ?? [] as $row) {
			$row['stores'] = json_decode($row['stores'] ?? '[]');
			$result[] = $row;
		}

		return $result;
	}

	public function getManufacturerStores($manufacturer_id) : array {
		$manufacturer_store_data = [];

		$query = $this->db->query("
			SELECT 
				store_id 
			FROM " . DB_PREFIX . "manufacturer_to_store 
			WHERE manufacturer_id = '" . (int)$manufacturer_id . "'
		");

		foreach ($query->rows as $result) {
			$manufacturer_store_data[] = $result['store_id'];
		}

		return $manufacturer_store_data;
	}
	
	public function getManufacturerSeoUrls($manufacturer_id) : array {
		$manufacturer_seo_url_data = [];
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'manufacturer_id=" . (int)$manufacturer_id . "'");

		foreach ($query->rows as $result) {
			$manufacturer_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $manufacturer_seo_url_data;
	}
	
	public function getTotalManufacturers() : int {
		$query = $this->db->query("
			SELECT COUNT(manufacturer_id) AS total FROM " . DB_PREFIX . "manufacturer
		");

		return $query->row['total'] ?? 0;
	}

	public function getManufacturerDescriptions($manufacturer_id) : array {
		$manufacturer_description_data = [];

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "manufacturer_description 
			WHERE manufacturer_id = '" . (int) $manufacturer_id . "'
				AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$manufacturer_description_data[$result['language_id']] = array(
				'name'             => $result['name'],
				'meta_title'       => $result['meta_title'],
				'meta_description' => $result['meta_description'],
				'meta_keyword'     => $result['meta_keyword'],
				'description'      => $result['description']
			);
		}

		return $manufacturer_description_data;
	}
}
