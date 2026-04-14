<?php
class ModelCatalogAttribute extends Model {
	public function addAttribute($data) : int {

		$this->db->query("START TRANSACTION");

		try {

			$this->db->query("
				INSERT INTO " . DB_PREFIX . "attribute 
				SET 
					`attribute_group_id` 	= '" . (int) $data['attribute_group_id'] . "', 
					`sort_order` 					= '" . (int) $data['sort_order'] . "' 
			");
	
			$attribute_id = $this->db->getLastId();

			// Delete SEO URL just in case, should never happen
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "seo_url
				WHERE `query` 	 = 'attribute_id=" . (int) $attribute_id . "'
					AND `store_id` = " . (int) $this->session->data['store_id'] . "
			");

			// Delete cache
			$this->deleteCache($attribute_id);
	
			foreach ($data['attribute_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "attribute_description 
					SET 
						`attribute_id` 	= '" . (int) $attribute_id . "', 
						`language_id` 	= '" . (int) $language_id . "', 
						`store_id` 			= '" . (int) $this->session->data['store_id'] . "', 
						`name` 					= '" . $this->db->escape($value['name']) . "'
				");

				// Save new attribute URL
				if ($value['url']) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "seo_url
						SET
							`query`   = 'attribute_id=" . (int) $attribute_id . "',
							`keyword` = '" . $this->db->escape($value['url']) . "',
							`language_id` 	= '" . (int) $language_id . "', 
							`store_id` 			= '" . (int) $this->session->data['store_id'] . "'
					");
				}
			}

			// Stores association
			if (isset($data['stores_association']) && !empty($data['stores_association'])) {
				foreach ($data['stores_association'] as $store_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "attribute_to_store
						SET
							`attribute_id` 				= '" . (int) $attribute_id . "', 
							`attribute_group_id` 	= '" . (int) $data['attribute_group_id'] . "', 
							`store_id` 		 				= '" . (int) $store_id . "',
							`sort_order` 	 				= '" . (int) $data['sort_order'] . "'
					");
				}
			}
	
			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_value_id: $attribute_id, facet_type: 4, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_value_id: $attribute_id, facet_type: 4, store_id: $store_id);
			
			return (int) $attribute_id;
			
		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
			
		}
		
	}

	public function editAttribute($attribute_id, $data) : int {

		$this->db->query("START TRANSACTION");

		try {
			$this->db->query("
				UPDATE " . DB_PREFIX . "attribute 
				SET 
					`attribute_group_id` 	= '" . (int) $data['attribute_group_id'] . "', 
					`sort_order` 					= '" . (int) $data['sort_order'] . "' 
				WHERE `attribute_id` 	= '" . (int) $attribute_id . "' 
			");
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "attribute_description 
				WHERE `attribute_id` 	= '" . (int) $attribute_id . "' 
					AND `store_id` 			= '" . (int) $this->session->data['store_id'] . "'
			");

			// Delete previous attribute URL
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "seo_url
				WHERE `query` 	 = 'attribute_id=" . (int) $attribute_id . "'
					AND `store_id` = " . (int) $this->session->data['store_id'] . "
			");
	
			foreach ($data['attribute_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "attribute_description SET 
						`attribute_id` 	= '" . (int) $attribute_id . "', 
						`language_id` 	= '" . (int) $language_id . "', 
						`store_id` 			= '" . (int) $this->session->data['store_id'] . "', 
						`name` 					= '" . $this->db->escape($value['name']) . "'
				");

				// Save new attribute URL
				if ($value['url']) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "seo_url
						SET
							`query`   = 'attribute_id=" . (int) $attribute_id . "',
							`keyword` = '" . $this->db->escape($value['url']) . "',
							`language_id` 	= '" . (int) $language_id . "', 
							`store_id` 			= '" . (int) $this->session->data['store_id'] . "'
					");
				}
			}

			// Stores association
			// Remove all unselected stores
			$this->db->query("
			DELETE FROM " . DB_PREFIX . "attribute_to_store
			WHERE `attribute_id` 	= '" . (int) $attribute_id . "'
				AND `store_id` NOT IN (" . implode(',', array_map('intval', $data['stores_association'])) . ")
			");
			
			// Remove only current store no matter if it's selected
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "attribute_to_store
				WHERE `attribute_id` 	= '" . (int) $attribute_id . "'
					AND `store_id`			= '" . (int) $this->session->data['store_id'] . "'
			");

			// Write store association and store related data
			if (isset($data['stores_association']) && !empty($data['stores_association'])) {
				foreach ($data['stores_association'] as $store_id) {
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						// Set data for current store
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "attribute_to_store
							SET
								`attribute_id` 				= '" . (int) $attribute_id . "', 
								`attribute_group_id` 	= '" . (int) $data['attribute_group_id'] . "', 
								`store_id` 		 				= '" . (int) $store_id . "',
								`sort_order` 	 				= '" . (int) $data['sort_order'] . "'
						");
					} else {
						// Skip if data for other stores already exists
						$this->db->query("
							INSERT IGNORE INTO " . DB_PREFIX . "attribute_to_store
							SET
								`attribute_id` 				= '" . (int) $attribute_id . "', 
								`attribute_group_id` 	= '" . (int) $data['attribute_group_id'] . "', 
								`store_id` 		 				= '" . (int) $store_id . "',
								`sort_order` 	 				= '" . (int) $data['sort_order'] . "'
						");
					}
				}
			}

			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_value_id: $attribute_id, facet_type: 4, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_value_id: $attribute_id, facet_type: 4, store_id: $store_id);

			// Delete cache
			$this->deleteCache($attribute_id);

			return (int) $attribute_id;

		} catch (\Throwable $e) {
			
			$this->db->query("ROLLBACK");

			throw $e;
		}
	}

	public function deleteAttribute($attribute_id) : bool {
		
		$this->db->query("START TRANSACTION");

		try {

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "attribute_description 
				WHERE `attribute_id` 	= '" . (int) $attribute_id . "'
					AND `store_id` 			= '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "attribute_to_store 
				WHERE `attribute_id` 	= '" . (int) $attribute_id . "'
					AND `store_id` 			= '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_attribute
				WHERE `attribute_id` 	= '" . (int) $attribute_id . "'
					AND `store_id` 			= '" . (int) $this->session->data['store_id'] . "'		
			");

			// Delete attribute URL
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "seo_url
				WHERE `query` 	 = 'attribute_id=" . (int) $attribute_id . "'
					AND `store_id` = " . (int) $this->session->data['store_id'] . "
			");

			// Check if attribute exists in other stores
			$attributeInOtherStores = $this->db->query("
				SELECT
					attribute_id
				FROM " . DB_PREFIX . "attribute_to_store
				WHERE attribute_id  = '" . (int) $attribute_id . "'
					AND store_id 			<> '" . (int) $this->session->data['store_id'] . "' 
			")->num_rows;
			// Delete all attribute data row if attribute group is not present in any other store
			if (!$attributeInOtherStores) {
				$tables = [
					'attribute',
					'attribute_description',
					'product_attribute',
				];

				// Remove all redundant data if present 
				foreach ($tables as $table) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . $table . "
						WHERE attribute_id = " . (int) $attribute_id
					);
				}

				// Cleanup URLs
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "seo_url
					WHERE `query` 	 = 'attribute_id=" . (int) $attribute_id . "'
				");
			}

			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_value_id: $attribute_id, facet_type: 4, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_value_id: $attribute_id, facet_type: 4, store_id: $store_id);
			
			// Delete cache
			$this->deleteCache($attribute_id);

			return true;

		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");
			
			throw $e;
		}
		
	}

	/**
	 * Display attribute on admin product form
	 * @param mixed $attribute_id
	 */
	public function getAttribute($attribute_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "attribute a 
			LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) 
			WHERE a.attribute_id = '" . (int)$attribute_id . "' 
				AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'
				AND ad.store_id 			 = '" . (int) $this->session->data['store_id'] . "'
		");

		return $query->row;
	}

	/**
	 * Show attributes list.
	 * Used in admin attribute list and in attribute autocomplete.
	 * If $data['store_id'] is not set shows attributes from all stores with store badges representing store relation
	 * If $data['store_id'] is set shows only attributes from store_id = n. This is used in autocomplete to show only store related attributes
	 * @param mixed $data
	 * @return array
	 */
	public function getAttributes($data = array()) {

		$result = [];
		$where = [];
		$orderClause = "";
		$limitClause = "";

		// Where clause
		$where[] = " a2s.attribute_id = a.attribute_id "; // Connect WHERE EXISTS with external SELECT "attribute a" table
		if (isset($data['filter_name'])) {
			$where[] = " ad.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (isset($data['filter_attribute_group_id'])) {
			$where[] = " a.attribute_group_id = '" . $this->db->escape($data['filter_attribute_group_id']) . "'";
		}

		if (isset($data['store_id'])) {
			$where[] = " a2s.store_id = '" . (int) $data['store_id'] . "'";
		}

		// Order clause
		$sort_data = array(
			'name',
			'attribute_group_id',
			'a2s.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$orderClause .= " ORDER BY FIELD(a2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, " . $data['sort'];
		} else {
			$orderClause .= " ORDER BY FIELD(a2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, attribute_group_id, name";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$orderClause .= " DESC";
		} else {
			$orderClause .= " ASC";
		}

		// Limit clause
		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$limitClause .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$sql = "
			SELECT
				a.attribute_id,
				-- Coalesce attribute_group_id so if attribute group is not associated with store, it is retrieved anyway
				COALESCE(a2s.attribute_group_id, a.attribute_group_id) AS attribute_group_id,
				a2s.sort_order,
				(
					SELECT 
						ad.name 
					FROM " . DB_PREFIX . "attribute_description ad
					WHERE ad.attribute_id = a.attribute_id
					ORDER BY 
						FIELD(ad.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, 
						FIELD(ad.language_id, '" . (int) $this->config->get('config_language_id') . "') DESC
					LIMIT 1
				) AS `name`,
				(
					SELECT 
						agd.name 
					FROM " . DB_PREFIX . "attribute_group_description agd 
					WHERE agd.attribute_group_id = (
						SELECT
							a2s2.attribute_group_id
						FROM " . DB_PREFIX . "attribute_to_store a2s2
						WHERE a2s2.attribute_id = a.attribute_id
						ORDER BY 
							FIELD(a2s2.store_id, '" . (int) $this->session->data['store_id'] ."') DESC
						LIMIT 1
					)
					ORDER BY 
						FIELD(agd.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, 
						FIELD(agd.language_id, '" . (int) $this->config->get('config_language_id') . "') DESC
					LIMIT 1
				) AS `attribute_group`,
				(SELECT COUNT(pa.product_id) FROM " . DB_PREFIX . "product_attribute pa WHERE pa.attribute_id = a.attribute_id AND pa.store_id = a2s.store_id) AS product_count,
 				(SELECT JSON_ARRAYAGG(a2s.store_id) FROM " . DB_PREFIX . "attribute_to_store a2s WHERE a.attribute_id = a2s.attribute_id) AS stores,
 				(SELECT JSON_ARRAYAGG(ag2s.store_id) FROM " . DB_PREFIX . "attribute_group_to_store ag2s WHERE a.attribute_group_id = ag2s.attribute_group_id) AS attribute_group_stores
			FROM " . DB_PREFIX . "attribute a 
			LEFT JOIN " . DB_PREFIX . "attribute_description ad 
				ON ad.attribute_id 	= a.attribute_id 
				AND ad.language_id 	= " . (int) $this->config->get('config_language_id') . "
				AND ad.store_id 		= " . (int) $this->session->data['store_id'] . "
			LEFT JOIN " . DB_PREFIX . "attribute_to_store a2s 
				ON a2s.attribute_id = a.attribute_id 
				AND a2s.store_id 		= " . (int) $this->session->data['store_id'] . "
      WHERE EXISTS (
        SELECT 1
        FROM " . DB_PREFIX . "attribute_to_store a2s
        JOIN " . DB_PREFIX . "attribute_description ad
          ON ad.attribute_id = a2s.attribute_id
          AND ad.store_id = a2s.store_id
        WHERE " . implode(' AND ', $where) . "
      )
			" . $orderClause . "
			" . $limitClause . "
		";

		$query = $this->db->query($sql);

		foreach ($query->rows ?? [] as $row) {
			$row['stores'] 									= json_decode($row['stores'] ?? '[]');
			$row['attribute_group_stores'] 	= json_decode($row['attribute_group_stores'] ?? '[]');
			$result[] = $row;
		}

		return $result;
	}

	public function getAttributeDescriptions($attribute_id) {
		$attribute_data = array();

		$query = $this->db->query("
			SELECT * FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "' AND store_id = '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$attribute_data[$result['language_id']] = array('name' => $result['name']);
		}

		return $attribute_data;
	}

	public function getTotalAttributes() {
		$query = $this->db->query("
			SELECT COUNT(*) AS total FROM " . DB_PREFIX . "attribute
		");

		return $query->row['total'];
	}

	public function getTotalAttributesByAttributeGroupId($attribute_group_id) {
		$query = $this->db->query("
			SELECT COUNT(*) AS total FROM " . DB_PREFIX . "attribute 
			WHERE attribute_group_id = '" . (int)$attribute_group_id . "'
		");

		return $query->row['total'];
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
			FROM `" . DB_PREFIX . "attribute_to_store`
			WHERE attribute_id = '" . (int) $id . "'
		");
		foreach ($storeData->rows as $store) {
			$result[] = $store['store_id']; 
		}

		return $result;
	}

	public function deleteCache($attribute_id, $store_id = null) : void {
		
		$products 	= [];
		$categories = [];
		
		if ($store_id === null) {
			$store_id = $this->session->data['store_id'];
		}

		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		$products = $this->db->query("
			SELECT
				DISTINCT product_id
			FROM " . DB_PREFIX . "product_attribute
			WHERE attribute_id = '" . $attribute_id . "'
				AND store_id = '" . (int) $store_id . "'
		")->rows;

		if (!empty($products)) {
			$categories = $this->db->query("
				SELECT
					DISTINCT category_id
				FROM " . DB_PREFIX . "product_to_category
				WHERE product_id IN(" . implode(',',array_column($products, 'product_id')). ")
					AND store_id = '" . (int) $store_id . "'
			")->rows;
		}

		foreach ($languages as $language) {
			$language_id = $language['language_id'];
			// Product cache
			if (!empty($products)) {
				foreach ($products as $product) {
					$productCacheName 	= "product.store_{$store_id}.language_{$language_id}." . (floor($product['product_id'] / 100)) . "00.product_" . $product['product_id'];
					$this->cache->delete($productCacheName);
				}
			}

			if (!empty($categories)) {
				foreach ($categories as $category) {
					$category_id = (int) $category['category_id'];
	
					// Filter cache
					$filterCacheName = "category.store_{$store_id}.language_{$language_id}." . (floor($category_id / 100)) . "00.filters_{$category_id}";
					$this->cache->delete($filterCacheName);
				}
			}
		}
	}
}
