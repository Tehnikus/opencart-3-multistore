<?php
class ModelCatalogAttributeGroup extends Model {
	public function addAttributeGroup($data) : int {

		$this->db->query("START TRANSACTION");
		
		try {
			$this->db->query("
				INSERT INTO " . DB_PREFIX . "attribute_group 
				SET 
					sort_order = '" . (int) $data['sort_order'] . "'
			");

			$attribute_group_id = $this->db->getLastId();

			// Delete cache
			$this->deleteCache($attribute_group_id);

			foreach ($data['attribute_group_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "attribute_group_description 
					SET 
						attribute_group_id 	= '" . (int) $attribute_group_id . "', 
						language_id 				= '" . (int) $language_id . "', 
						store_id 						= '" . (int) $this->session->data['store_id'] . "',
						name 								= '" . $this->db->escape($value['name']) . "'
				");
			}

			// Stores association
			if (isset($data['stores_association']) && !empty($data['stores_association'])) {
				foreach ($data['stores_association'] as $store_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "attribute_group_to_store
						SET
							`attribute_group_id` 	= '" . (int) $attribute_group_id . "', 
							`store_id` 						= '" . (int) $store_id . "',
							`sort_order` 					= '" . (int) $data['sort_order'] . "'
					");
				}
			}

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_group_id: $attribute_group_id, facet_type: 4, store_id: $store_id);

			$this->db->query("COMMIT");

			return $attribute_group_id;

		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
		}
	}

	public function editAttributeGroup($attribute_group_id, $data) : int {

		$this->db->query("START TRANSACTION");

		try {
			$this->db->query("
				UPDATE " . DB_PREFIX . "attribute_group 
				SET 
					sort_order = '" . (int)$data['sort_order'] . "' 
				WHERE attribute_group_id = '" . (int)$attribute_group_id . "'
			");
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "attribute_group_description 
				WHERE attribute_group_id = '" . (int)$attribute_group_id . "'
					AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");
	
			foreach ($data['attribute_group_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "attribute_group_description 
					SET 
						attribute_group_id = '" . (int)$attribute_group_id . "', 
						language_id = '" . (int)$language_id . "', 
						store_id = '" . (int) $this->session->data['store_id'] . "', 
						name = '" . $this->db->escape($value['name']) . "'
				");
			}
	
			// Stores association
			// Remove all unselected stores
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "attribute_group_to_store
				WHERE `attribute_group_id` 	= '" . (int) $attribute_group_id . "'
					AND `store_id` NOT IN (" . implode(',', array_map('intval', $data['stores_association'])) . ")
			");

			// Remove only current store no matter if it's selected
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "attribute_group_to_store
				WHERE `attribute_group_id` 	= '" . (int) $attribute_group_id . "'
					AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
			");

			// Write store association and store related data
			if (isset($data['stores_association']) && !empty($data['stores_association'])) {
				foreach ($data['stores_association'] as $store_id) {
					// Set data for current store
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						$this->db->query("
						INSERT INTO " . DB_PREFIX . "attribute_group_to_store
						SET
							`attribute_group_id` 	= '" . (int) $attribute_group_id . "', 
							`store_id` 						= '" . (int) $store_id . "',
							`sort_order` 					= '" . (int) $data['sort_order'] . "'
						");
					} else {
						// Skip if data for other stores already exists
						$this->db->query("
							INSERT IGNORE INTO " . DB_PREFIX . "attribute_group_to_store
							SET
								`attribute_group_id` 	= '" . (int) $attribute_group_id . "', 
								`store_id` 						= '" . (int) $store_id . "',
								`sort_order` 					= '" . (int) $data['sort_order'] . "'
						");
					}
				}
			}
			// End stores association

			$this->db->query("COMMIT");

			// Delete cache
			$this->deleteCache($attribute_group_id);
			
			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_group_id: $attribute_group_id, facet_type: 4, store_id: $store_id);

			return $attribute_group_id;
			
		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");
			
			throw $e;
		}
		
	}

	public function deleteAttributeGroup($attribute_group_id) : bool {

		$this->db->query("START TRANSACTION");

		try {

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "attribute_group_description 
				WHERE attribute_group_id 	= '" . (int) $attribute_group_id . "'
					AND store_id 						= '" . (int) $this->session->data['store_id'] . "'
			");

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "attribute_group_to_store
				WHERE attribute_group_id 	= '" . (int) $attribute_group_id . "'
					AND store_id 						= '" . (int) $this->session->data['store_id'] . "'
			");

			// Check if attribute group is present in other stores
			$attributeGroupInOtherStores = $this->db->query("
				SELECT
					attribute_group_id
				FROM " . DB_PREFIX . "attribute_group_to_store
				WHERE attribute_group_id  = '" . (int) $attribute_group_id . "'
					AND store_id 						<> '" . (int) $this->session->data['store_id'] . "' 
			")->num_rows;

			// Delete attributes in this group and current store
			$attributesInGroup = $this->db->query("
				SELECT
					attribute_id
				FROM " . DB_PREFIX . "attribute_to_store
				WHERE attribute_group_id = '" . (int) $attribute_group_id . "'
					AND store_id = '" . (int) $this->session->data['store_id'] . "'
			")->rows;

			$this->load->model('catalog/attribute');
			foreach ($attributesInGroup as $attribute) {
				$this->model_catalog_attribute->deleteAttribute($attribute['attribute_id']);
			}

			// Delete all attribute group data rows if attribute group is not present in any other store
			if (!$attributeGroupInOtherStores) {
				$tables = [
					'attribute',
					'attribute_group',
					'attribute_group_description',
				];

				// Remove all redundant data if present 
				foreach ($tables as $table) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . $table . "
						WHERE attribute_group_id = " . (int) $attribute_group_id
					);
				}				
			}

			$this->db->query("COMMIT");

			// Delete cache
			$this->deleteCache($attribute_group_id);

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_group_id: $attribute_group_id, facet_type: 4, store_id: $store_id);
			
			return true;
			
		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
		}
		
	}

	public function getAttributeGroup($attribute_group_id) {

		$query = $this->db->query("
			SELECT * FROM " . DB_PREFIX . "attribute_group 
			WHERE attribute_group_id = '" . (int)$attribute_group_id . "'
		");

		return $query->row;
	}

	// Get attribute groups in admin attribute group list 
	// and in attribute form
	// Should show all attribute groups in admin attribute group list 
	// and only selected store attribute groups in attribute form 
	public function getAttributeGroups($data = array()) : array {
		$result = [];
		$where = [];

		$where[] = "
			ag2s.attribute_group_id = ag.attribute_group_id
		";
		
		// Filter by store_id
		if (isset($data['store_id'])) {
			$where[] = "
				ag2s.store_id = " . (int) $data['store_id'] . "
			";
		}

		$sql = "
			SELECT 
				ag.attribute_group_id,
				ag2s.sort_order,
				(
					SELECT 
						agd.name 
					FROM " . DB_PREFIX . "attribute_group_description agd 
					WHERE agd.attribute_group_id = ag.attribute_group_id 
					ORDER BY 
						FIELD(agd.store_id, '" . (int) $this->session->data['store_id'] ."') DESC,
						FIELD(agd.language_id, '" . (int) $this->config->get('config_language_id') . "') DESC
						LIMIT 1
				) AS `name`,
				(SELECT JSON_ARRAYAGG(ag2s.store_id) FROM " . DB_PREFIX . "attribute_group_to_store ag2s WHERE ag.attribute_group_id = ag2s.attribute_group_id) AS stores
			FROM " . DB_PREFIX . "attribute_group_to_store ag2s
			JOIN " . DB_PREFIX . "attribute_group ag
				ON ag.attribute_group_id = ag2s.attribute_group_id
			WHERE EXISTS (
				SELECT
					1
				FROM " . DB_PREFIX . "attribute_group_to_store ag2s
				WHERE " . implode(' AND ', $where) . "
			)
		";

		$sort_data = array(
			'name',
			'ag2s.sort_order',
			'attribute_count'
		);

		// First order by store_id, the by other fields
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY FIELD(ag2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, " . $data['sort'];
		} else {
			$sql .= " ORDER BY FIELD(ag2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, name";
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

	public function getAttributeGroupDescriptions($attribute_group_id) : array {
		$attribute_group_data = array();

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "attribute_group_description 
			WHERE attribute_group_id = '" . (int)$attribute_group_id . "'
				AND store_id = '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$attribute_group_data[$result['language_id']] = array('name' => $result['name']);
		}

		return $attribute_group_data;
	}

	public function getTotalAttributeGroups() {
		$query = $this->db->query("
			SELECT COUNT(*) AS total 
			FROM " . DB_PREFIX . "attribute_group
		");

		return $query->row['total'];
	}
}