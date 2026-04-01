<?php
class ModelCatalogFilter extends Model {
	public function addFilter($data) {

		$this->db->query("START TRANSACTION");
		
		try {

			$this->db->query("
				INSERT INTO `" . DB_PREFIX . "filter_group` 
				SET 
					sort_order 	= '" . (int) $data['sort_order'] . "'
			");
	
			$filter_group_id = $this->db->getLastId();
	
			foreach ($data['filter_group_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "filter_group_description 
					SET 
						filter_group_id = '" . (int) $filter_group_id . "', 
						language_id 		= '" . (int) $language_id . "', 
						store_id 				= '" . (int) $this->session->data['store_id'] . "', 
						name 						= '" . $this->db->escape($value['name']) . "'
				");
			}
	
			if (isset($data['filter'])) {
				foreach ($data['filter'] as $filter) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "filter 
						SET 
							filter_group_id = '" . (int) $filter_group_id . "', 
							sort_order 			= '" . (int) $filter['sort_order'] . "', 
							store_id 				= '" . (int) $this->session->data['store_id'] . "'
					");
	
					$filter_id = $this->db->getLastId();
					// Delete cache
					$this->deleteCache($filter_id);
	
					foreach ($filter['filter_description'] as $language_id => $filter_description) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "filter_description 
							SET 
								filter_id 			= '" . (int) $filter_id . "', 
								language_id 		= '" . (int) $language_id . "', 
								filter_group_id = '" . (int) $filter_group_id . "', 
								store_id 				= '" . (int) $this->session->data['store_id'] . "', 
								name 						= '" . $this->db->escape($filter_description['name']) . "'
						");
	
						$this->db->query("
							DELETE FROM " . DB_PREFIX . "seo_url
							WHERE `query` 			= 'filter=" . (int) $filter_id . "'
								AND `language_id` = '" . (int) $language_id . "'
								AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
						");
	
						if (isset($filter_description['url']) && !empty($filter_description['url'])) {
							$this->db->query("
								INSERT INTO " . DB_PREFIX . "seo_url
								SET
									`query` 			= 'filter=" . (int) $filter_id . "', 
									`keyword` 		= '" . $this->db->escape($filter_description['url']) . "', 
									`language_id` = '" . (int) $language_id . "', 
									`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
							");
						}
					}
				}
			}

			// Stores association
			if (isset($data['stores_association']) && !empty($data['stores_association'])) {
				foreach ($data['stores_association'] as $store_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "filter_group_to_store
						SET
							`filter_group_id` = '" . (int) $filter_group_id . "', 
							`store_id` 				= '" . (int) $store_id . "',
							`sort_order` 			= '" . (int) $data['sort_order'] . "'
					");
				}
			}

			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_group_id: $filter_group_id, facet_type: 2, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_group_id: $filter_group_id, facet_type: 2, store_id: $store_id);

			return $filter_group_id;

		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
		}
	}

	public function editFilter($filter_group_id, $data) {

		$this->db->query("START TRANSACTION");

		try {
			$this->db->query("
				UPDATE `" . DB_PREFIX . "filter_group` 
				SET 
					sort_order = '" . (int)$data['sort_order'] . "' 
				WHERE filter_group_id = '" . (int)$filter_group_id . "'
			");
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "filter_group_description 
				WHERE filter_group_id = '" . (int)$filter_group_id . "'
					AND store_id = " . (int) $this->session->data['store_id'] . "
			");
	
			foreach ($data['filter_group_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "filter_group_description 
					SET 
						filter_group_id = '" . (int)$filter_group_id . "', 
						language_id = '" . (int)$language_id . "', 
						store_id = " . (int) $this->session->data['store_id'] . ",
						name = '" . $this->db->escape($value['name']) . "'
				");
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "filter 
				WHERE filter_group_id = '" . (int)$filter_group_id . "'
					AND store_id = " . (int) $this->session->data['store_id'] . "
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "filter_description 
				WHERE filter_group_id = '" . (int)$filter_group_id . "'
					AND store_id = " . (int) $this->session->data['store_id'] . "
			");
	
			if (isset($data['filter'])) {
				foreach ($data['filter'] as $filter) {
					if ($filter['filter_id']) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "filter 
							SET 
								filter_id 			= '" . (int) $filter['filter_id'] . "', 
								filter_group_id = '" . (int) $filter_group_id . "', 
								sort_order 			= '" . (int) $filter['sort_order'] . "', 
								store_id 				= '" . (int) $this->session->data['store_id'] . "'
						");
					} else {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "filter 
							SET 
								filter_group_id 	= '" . (int) $filter_group_id . "', 
								sort_order 				= '" . (int) $filter['sort_order'] . "', 
								store_id 					= '" . (int) $this->session->data['store_id'] . "'
						");
					}
	
					$filter_id = $this->db->getLastId();
					// Delete cache
					$this->deleteCache($filter_id);
	
					foreach ($filter['filter_description'] as $language_id => $filter_description) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "filter_description 
							SET 
								filter_id 			= '" . (int) $filter_id . "', 
								language_id 		= '" . (int) $language_id . "', 
								store_id 				= '" . $this->session->data['store_id'] . "', 
								filter_group_id = '" . (int) $filter_group_id . "', 
								name = '" . $this->db->escape($filter_description['name']) . "'
						");
	
						$this->db->query("
							DELETE FROM " . DB_PREFIX . "seo_url
							WHERE `query` 			= 'filter=" . (int) $filter_id . "'
								AND `language_id` = '" . (int) $language_id . "'
								AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
						");
	
						if (isset($filter_description['url']) && !empty($filter_description['url'])) {
							$this->db->query("
								INSERT INTO " . DB_PREFIX . "seo_url
								SET
									`query` 			= 'filter=" . (int) $filter_id . "', 
									`keyword` 		= '" . $this->db->escape($filter_description['url']) . "', 
									`language_id` = '" . (int) $language_id . "', 
									`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
							");
						}
					}
				}
			}

			// Stores association
			// Remove all unselected stores
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "filter_group_to_store
				WHERE `filter_group_id` = '" . (int) $filter_group_id . "' 
					AND `store_id` NOT IN (" . implode(',', array_map('intval', $data['stores_association'])) . ")
			");

			// Remove only current store no matter if it's selected
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "filter_group_to_store
				WHERE `filter_group_id` = '" . (int) $filter_group_id . "' 
					AND `store_id` 				= '" . (int) $this->session->data['store_id'] . "'
			");
			
			// Write store association and store related data
			if (isset($data['stores_association']) && !empty($data['stores_association'])) {
				foreach ($data['stores_association'] as $store_id) {
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						// Set data for current store
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "filter_group_to_store
							SET
								`filter_group_id` = '" . (int) $filter_group_id . "', 
								`store_id` 				= '" . (int) $store_id . "',
								`sort_order` 			= '" . (int) $data['sort_order'] . "' 
						");
					} else {
						// Skip if data for other stores already exists
						$this->db->query("
							INSERT IGNORE INTO " . DB_PREFIX . "filter_group_to_store
							SET
								`filter_group_id` = '" . (int) $filter_group_id . "', 
								`store_id` 				= '" . (int) $store_id . "',
								`sort_order` 			= '" . (int) $data['sort_order'] . "' 
						");						
					}
				}
			}

			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_group_id: $filter_group_id, facet_type: 2, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_group_id: $filter_group_id, facet_type: 2, store_id: $store_id);

			return $filter_group_id;

		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
		}
	}

	public function deleteFilter($filter_group_id) {

		$this->db->query("START TRANSACTION");

		try {

			$groupFiltersByStore = $this->db->query("
				SELECT 
					`filter_id`
				FROM " . DB_PREFIX . "filter
				WHERE filter_group_id =  " . (int) $filter_group_id . "
					AND `store_id` 				= " . (int) $this->session->data['store_id'] . "
			")->rows;

			$groupFiltersAll = $this->db->query("
				SELECT 
					`filter_id`
				FROM " . DB_PREFIX . "filter
				WHERE `filter_group_id` =  " . (int) $filter_group_id . "
			")->rows;
			
			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "filter_group_description` 
				WHERE `filter_group_id` = '" . (int)$filter_group_id . "' 
					AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "filter` 
				WHERE `filter_group_id` = '" . (int)$filter_group_id . "' 
					AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "filter_description` 
				WHERE `filter_group_id` = '" . (int)$filter_group_id . "' 
					AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "filter_group_to_store
				WHERE `filter_group_id` = '" . (int) $filter_group_id . "'
					AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
			");

			foreach ($groupFiltersByStore as $filter) {
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "product_filter
					WHERE `filter_id` = '" . (int) $filter['filter_id'] . "'
						AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "'
				");
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "category_filter
					WHERE `filter_id` = '" . (int) $filter['filter_id'] . "'
						AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "'
				");
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "seo_url
					WHERE `query` 	= 'filter_id=" . (int) $filter['filter_id'] . "'
						AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "'
				");

				// Delete cache
				$this->deleteCache($filter['filter_id']);
			}
			
			// Check if filter group is present in other stores
			$filterGroupInOtherStores = $this->db->query("
				SELECT
					`filter_group_id`
				FROM " . DB_PREFIX . "filter_group_to_store
				WHERE `filter_group_id`  = '" . (int) $filter_group_id . "'
					AND `store_id` 		<> '" . (int) $this->session->data['store_id'] . "' 
			")->num_rows;

			// Delete all filter group data if filter group is not present in any other store
			if (!$filterGroupInOtherStores) {
				$tables = [
					'filter_group',
					'filter_group_description',
					'filter',
					'filter_description',
					'filter_group_to_store',
				];

				// Remove all redundant data if present 
				foreach ($tables as $table) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . $table . "
						WHERE filter_group_id = " . (int) $filter_group_id
					);
				}

				foreach ($groupFiltersAll as $filter) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . "product_filter
						WHERE `filter_id` = '" . (int) $filter['filter_id'] . "'
					");
					$this->db->query("
						DELETE FROM " . DB_PREFIX . "category_filter
						WHERE `filter_id` = '" . (int) $filter['filter_id'] . "'
					");
					$this->db->query("
						DELETE FROM " . DB_PREFIX . "seo_url
						WHERE `query` = 'filter_id=" . (int) $filter['filter_id'] . "'
					");
				}
			}

			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_group_id: $filter_group_id, facet_type: 2, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_group_id: $filter_group_id, facet_type: 2, store_id: $store_id);

			return true;

		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
		}
	}

	public function getFilterGroup($filter_group_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM `" . DB_PREFIX . "filter_group` fg 
			LEFT JOIN " . DB_PREFIX . "filter_group_description fgd 
			ON (
				fg.filter_group_id = fgd.filter_group_id 
				-- AND fg.store_id = fgd.store_id
			) 
			WHERE fg.filter_group_id 	= '" . (int)$filter_group_id . "' 
				-- AND fg.store_id 				= '" . $this->session->data['store_id'] . "'
				AND fgd.language_id 		= '" . (int)$this->config->get('config_language_id') . "'
				AND fgd.store_id 				= '" . $this->session->data['store_id'] . "'
		");

		return $query->row;
	}
	
	// Display filter groups list in admin filters section
	// Should display all filters from all stores but switch names and values relative to current store id 
	public function getFilterGroups($data = []) : array {
		$result = [];

		$sql = "
			SELECT
				fg.filter_group_id,
				fg2s.sort_order,
				(
					SELECT 
						fgd.name 
					FROM " . DB_PREFIX . "filter_group_description fgd 
					WHERE fgd.filter_group_id = fg.filter_group_id 
					ORDER BY 
						FIELD(fgd.store_id, '" . (int) $this->session->data['store_id'] ."') DESC,
						FIELD(fgd.language_id, '" . (int) $this->config->get('config_language_id') . "') DESC
						LIMIT 1
				) AS `name`,
				(SELECT JSON_ARRAYAGG(fd.name) FROM " . DB_PREFIX . "filter_description fd WHERE fd.filter_group_id = fg.filter_group_id AND fd.language_id = '" . (int) $this->config->get('config_language_id') . "' AND fd.store_id = '" . (int) $this->session->data['store_id'] . "') AS values_list,
				(
					SELECT 
						COUNT(DISTINCT pf.product_id) 
					FROM " . DB_PREFIX . "product_filter pf 
					WHERE pf.filter_id IN (
						SELECT 
							f.filter_id
						FROM " . DB_PREFIX . "filter f
						WHERE f.filter_group_id = fg.filter_group_id
							AND f.store_id = '" . $this->session->data['store_id'] . "'
					)
						AND pf.store_id = '" . $this->session->data['store_id'] . "'
				) AS product_count,
				(SELECT COUNT(DISTINCT(f.filter_id)) as filter_count FROM " . DB_PREFIX . "filter f WHERE f.filter_group_id = fg.filter_group_id) AS filter_count,
				(SELECT JSON_ARRAYAGG(fg2s.store_id) FROM " . DB_PREFIX . "filter_group_to_store fg2s WHERE fg.filter_group_id = fg2s.filter_group_id) AS stores
			FROM " . DB_PREFIX . "filter_group fg
			LEFT JOIN " . DB_PREFIX . "filter_group_to_store fg2s 
				ON fg2s.filter_group_id = fg.filter_group_id
				AND fg2s.store_id = '" . (int) $this->session->data['store_id'] . "'
		";

		$sort_data = array(
			'name',
			'fg2s.sort_order'
		);

		// Order by current store, then by $data['sort']
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY FIELD(fg2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, " . $data['sort'];
		} else {
			$sql .= " ORDER BY FIELD(fg2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, name";
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
			$row['values_list'] = json_decode($row['values_list'] ?? '[]');
			$result[] = $row;
		}

		return $result;
	}
	
	public function getFilterGroupDescriptions($filter_group_id) {
		$filter_group_data = array();

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "filter_group_description 
			WHERE filter_group_id = '" . (int)$filter_group_id . "'
				AND store_id = '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$filter_group_data[$result['language_id']] = array('name' => $result['name']);
		}

		return $filter_group_data;
	}

	// Get filter description for category and product forms
	public function getFilter($filter_id) {
		$query = $this->db->query("
			SELECT 
				*, 
				(SELECT 
						name 
					FROM " . DB_PREFIX . "filter_group_description fgd 
					WHERE f.filter_group_id = fgd.filter_group_id 
						AND fgd.language_id 	= '" . (int)$this->config->get('config_language_id') . "'
						AND fgd.store_id 			= '" . $this->session->data['store_id'] . "'
				) AS `group` 
			FROM " . DB_PREFIX . "filter f 
			LEFT JOIN " . DB_PREFIX . "filter_description fd ON (f.filter_id = fd.filter_id AND f.store_id = fd.store_id) 
			WHERE f.filter_id 		= '" . (int)$filter_id . "' 
				AND fd.language_id 	= '" . (int)$this->config->get('config_language_id') . "'
				AND fd.store_id 		= '" . $this->session->data['store_id'] . "'
		");

		return $query->row;
	}

	public function getFilters($data) {
		$sql = "SELECT *, (SELECT name FROM " . DB_PREFIX . "filter_group_description fgd WHERE f.filter_group_id = fgd.filter_group_id AND fgd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS `group` FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_description fd ON (f.filter_id = fd.filter_id) WHERE fd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND fd.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		$sql .= " ORDER BY f.sort_order ASC";

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

	public function getFilterDescriptions($filter_group_id) {
		$filter_data = array();

		$filter_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "filter WHERE filter_group_id = '" . (int)$filter_group_id . "'");

		foreach ($filter_query->rows as $filter) {
			$filter_description_data = array();

			$filter_description_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "filter_description WHERE filter_id = '" . (int)$filter['filter_id'] . "'");

			foreach ($filter_description_query->rows as $filter_description) {
				$filter_description_data[$filter_description['language_id']] = array('name' => $filter_description['name']);
			}

			$filter_data[] = array(
				'filter_id'          => $filter['filter_id'],
				'filter_description' => $filter_description_data,
				'sort_order'         => $filter['sort_order']
			);
		}

		return $filter_data;
	}

	public function getTotalFilterGroups() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "filter_group`");

		return $query->row['total'];
	}
}
