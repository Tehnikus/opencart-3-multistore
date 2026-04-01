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
	
			foreach ($data['attribute_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "attribute_description SET 
						`attribute_id` 	= '" . (int) $attribute_id . "', 
						`language_id` 	= '" . (int) $language_id . "', 
						`store_id` 			= '" . (int) $this->session->data['store_id'] . "', 
						`name` 					= '" . $this->db->escape($value['name']) . "'
				");
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

	public function getAttribute($attribute_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE a.attribute_id = '" . (int)$attribute_id . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

	public function getAttributes($data = array()) {
		$sql = "SELECT *, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS attribute_group FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND ad.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (!empty($data['filter_attribute_group_id'])) {
			$sql .= " AND a.attribute_group_id = '" . $this->db->escape($data['filter_attribute_group_id']) . "'";
		}

		$sort_data = array(
			'ad.name',
			'attribute_group',
			'a.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY attribute_group, ad.name";
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

	public function getAttributeDescriptions($attribute_id) {
		$attribute_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "'");

		foreach ($query->rows as $result) {
			$attribute_data[$result['language_id']] = array('name' => $result['name']);
		}

		return $attribute_data;
	}

	public function getTotalAttributes() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "attribute");

		return $query->row['total'];
	}

	public function getTotalAttributesByAttributeGroupId($attribute_group_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "attribute WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");

		return $query->row['total'];
	}
}
