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
	public function getManufacturer($manufacturer_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");

		return $query->row;
	}

	public function getManufacturers($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "manufacturer";

		if (!empty($data['filter_name'])) {
			$sql .= " WHERE name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		$sort_data = array(
			'name',
			'sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY name";
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
	}

	public function getManufacturerStores($manufacturer_id) {
		$manufacturer_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer_to_store WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");

		foreach ($query->rows as $result) {
			$manufacturer_store_data[] = $result['store_id'];
		}

		return $manufacturer_store_data;
	}
	
	public function getManufacturerSeoUrls($manufacturer_id) {
		$manufacturer_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'manufacturer_id=" . (int)$manufacturer_id . "'");

		foreach ($query->rows as $result) {
			$manufacturer_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $manufacturer_seo_url_data;
	}
	
	public function getTotalManufacturers() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "manufacturer");

		return $query->row['total'];
	}
}
