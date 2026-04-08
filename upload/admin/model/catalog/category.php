<?php
class ModelCatalogCategory extends Model {
	public function addCategory($data) {

		$this->db->query("START TRANSACTION");
		
		try {

			$this->db->query("
				INSERT INTO " . DB_PREFIX . "category 
				SET 
					`parent_id` 		= '" . (int)$data['parent_id'] . "', 
					`top` 					= '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', 
					`column` 				= '" . (int)$data['column'] . "', 
					`sort_order` 		= '" . (int)$data['sort_order'] . "', 
					`status` 				= '" . (int)$data['status'] . "', 
					`date_modified` = NOW(), 
					`date_added` 		= NOW()
				");
	
			$category_id = $this->db->getLastId();
	
			if (isset($data['image'])) {
				$this->db->query("
					UPDATE " . DB_PREFIX . "category 
					SET 
						image = '" . $this->db->escape($data['image']) . "' 
					WHERE category_id = '" . (int) $category_id . "'
				");
			}
	
			foreach ($data['category_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "category_description 
					SET 
						`category_id` 			= '" . (int) $category_id . "', 
						`language_id` 			= '" . (int) $language_id . "', 
						`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
						`name` 							= '" . $this->db->escape($value['name']) . "', 
						`description` 			= '" . $this->db->escape($value['description']) . "', 
						`meta_title` 				= '" . $this->db->escape($value['meta_title']) . "', 
						`meta_description` 	= '" . $this->db->escape($value['meta_description']) . "', 
						`meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "',
						`seo_description` 	= '" . $this->db->escape($value['seo_description']) . "', 
            `footer`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['footer'] ?? []), JSON_UNESCAPED_UNICODE)) . "',
            `faq`               = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['faq'] ?? ['@type', '@context']), JSON_UNESCAPED_UNICODE)) . "',
            `how_to`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['how_to'] ?? ['@type', '@context']), JSON_UNESCAPED_UNICODE)) . "'
				");
			}
	
			// Start category tree
			// Current store
			$level = 0;
	
			$query = $this->db->query("
				SELECT 
					* 
				FROM `" . DB_PREFIX . "category_path` 
				WHERE `category_id` = '" . (int) $data['parent_id'] . "' 
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
				ORDER BY `level` ASC
			");
	
			foreach ($query->rows as $result) {
				$this->db->query("
					INSERT INTO `" . DB_PREFIX . "category_path` 
					SET 
						`category_id` = '" . (int) $category_id . "', 
						`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
						`path_id` 		= '" . (int) $result['path_id'] . "', 
						`level` 			= '" . (int) $level . "'
				");
	
				$level++;
			}
	
			$this->db->query("
				INSERT INTO `" . DB_PREFIX . "category_path` 
				SET 
					`category_id` = '" . (int) $category_id . "', 
					`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
					`path_id` 		= '" . (int) $category_id . "', 
					`level` 			= '" . (int) $level . "'
			");

			// Other associated stores
			foreach ($data['category_store'] as $store_id) {
				if (((int) $store_id) !== ((int) $this->session->data['store_id'])) {
					$this->db->query("
						INSERT INTO `" . DB_PREFIX . "category_path` 
						SET 
							`category_id` = '" . (int) $category_id . "', 
							`store_id` 		= '" . (int) $store_id . "',
							`path_id` 		= '" . (int) $category_id . "', 
							`level` 			= '0'
					");
				}
			}

			// End category tree

			if (isset($data['category_filter'])) {
				foreach ($data['category_filter'] as $filter_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "category_filter 
						SET 
							`category_id` = '" . (int) $category_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`filter_id` 	= '" . (int) $filter_id . "'
					");
				}
			}
	
			if (isset($data['category_store'])) {
				foreach ($data['category_store'] as $store_id) {
					// Write data for current store
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "category_to_store 
							SET 
								`category_id` 	= '" . (int) $category_id . "', 
								`store_id`    	= '" . (int) $store_id . "',
								`parent_id` 		= '" . (int) $data['parent_id'] . "', 
								`status` 				= '" . (int) $data['status'] . "',
								`sort_order` 		= '" . (int) $data['sort_order'] . "', 
								`top` 					= '" . (isset($data['top']) ? (int) $data['top'] : 0) . "', 
								`column` 				= '" . (int) $data['column'] . "',
								`image` 				= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "'
						");
					} else {
						// Set association for other selected stores
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "category_to_store 
							SET 
								`category_id` 	= '" . (int) $category_id . "', 
								`store_id`    	= '" . (int) $store_id . "'
						");
					}
				}
			}
			
			if (isset($data['category_seo_url'])) {
				foreach ($data['category_seo_url'] as $store_id => $language) {
					foreach ($language as $language_id => $keyword) {
						if (!empty($keyword)) {
							$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'category_id=" . (int)$category_id . "', keyword = '" . $this->db->escape($keyword) . "'");
						}
					}
				}
			}
			
			// Set which layout to use with this category
			if (isset($data['category_layout'])) {
				foreach ($data['category_layout'] as $store_id => $layout_id) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "category_to_layout SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
				}
			}
			
			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_value_id: $category_id, facet_type: 1, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_value_id: $category_id, facet_type: 1, store_id: $store_id);
			
			// Delete cache
			// While new category itself does not has cache yet, this method also clears it's parent category cache to update child categories 
			$this->deleteCache($category_id);

			return $category_id;
			
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	public function editCategory($category_id, $data) {
		
		$this->db->query("START TRANSACTION");
		
		try {

			$this->db->query("
				UPDATE " . DB_PREFIX . "category 
				SET 
					`parent_id` 		= '" . (int) $data['parent_id'] . "', 
					`top` 					= '" . (isset($data['top']) ? (int) $data['top'] : 0) . "', 
					`column` 				= '" . (int) $data['column'] . "', 
					`sort_order` 		= '" . (int) $data['sort_order'] . "', 
					`status` 				= '" . (int) $data['status'] . "', 
					`date_modified` = NOW() 
				WHERE `category_id` = '" . (int)$category_id . "'
			");
	
			if (isset($data['image'])) {
				$this->db->query("
					UPDATE " . DB_PREFIX . "category 
					SET 
						image = '" . $this->db->escape($data['image']) . "' 
					WHERE category_id = '" . (int)$category_id . "'
				");
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_description 
				WHERE `category_id` = '" . (int) $category_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			foreach ($data['category_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "category_description 
					SET 
						`category_id` 			= '" . (int) $category_id . "', 
						`language_id` 			= '" . (int) $language_id . "', 
						`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
						`name` 							= '" . $this->db->escape($value['name']) . "', 
						`description` 			= '" . $this->db->escape($value['description']) . "',
						`meta_title` 				= '" . $this->db->escape($value['meta_title']) . "', 
						`meta_description` 	= '" . $this->db->escape($value['meta_description']) . "', 
						`meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "',
						`seo_description` 	= '" . $this->db->escape($value['seo_description']) . "',
            `footer`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['footer'] ?? []), JSON_UNESCAPED_UNICODE)) . "',
            `faq`               = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['faq'] ?? ['@type', '@context']), JSON_UNESCAPED_UNICODE)) . "',
            `how_to`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['how_to'] ?? ['@type', '@context']), JSON_UNESCAPED_UNICODE)) . "'
				");
			}
	
			// Start category tree
			// Surrent store
			$query = $this->db->query("
				SELECT 
				* 
				FROM `" . DB_PREFIX . "category_path` 
				WHERE path_id 	= '" . (int) $category_id . "' 
					AND store_id 	= '" . (int) $this->session->data['store_id'] . "'
				ORDER BY level ASC
			");
	
			if ($query->rows) {
				foreach ($query->rows as $category_path) {
					// Delete the path below the current one
					$this->db->query("
						DELETE FROM `" . DB_PREFIX . "category_path` 
						WHERE category_id = '" . (int) $category_path['category_id'] . "' 
							AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
							AND level 			< '" . (int) $category_path['level'] . "'
					");
	
					$path = array();
	
					// Get the nodes new parents
					$query = $this->db->query("
						SELECT 
							* 
						FROM `" . DB_PREFIX . "category_path` 
						WHERE category_id = '" . (int) $data['parent_id'] . "' 
							AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
						ORDER BY level ASC
					");
	
					foreach ($query->rows as $result) {
						$path[] = $result['path_id'];
					}
	
					// Get whats left of the nodes current path
					$query = $this->db->query("
						SELECT 
							* 
						FROM `" . DB_PREFIX . "category_path` 
						WHERE category_id = '" . (int) $category_path['category_id'] . "' 
							AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
						ORDER BY level ASC");
	
					foreach ($query->rows as $result) {
						$path[] = $result['path_id'];
					}
	
					// Combine the paths with a new level
					$level = 0;
	
					foreach ($path as $path_id) {
						$this->db->query("
							REPLACE INTO `" . DB_PREFIX . "category_path` 
							SET 
								`category_id` = '" . (int) $category_path['category_id'] . "', 
								`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
								`path_id` 		= '" . (int) $path_id . "', 
								`level` 			= '" . (int) $level . "'
						");
	
						$level++;
					}
				}
			} else {
				// Delete the path below the current one
				$this->db->query("
					DELETE FROM `" . DB_PREFIX . "category_path` 
					WHERE category_id = '" . (int) $category_id . "'
						AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
				");
	
				// Fix for records with no paths
				$level = 0;
	
				$query = $this->db->query("
					SELECT 
						* 
					FROM `" . DB_PREFIX . "category_path` 
					WHERE category_id 	= '" . (int) $data['parent_id'] . "' 
						AND store_id 			= '" . (int) $this->session->data['store_id'] . "'
					ORDER BY `level` ASC
				");
	
				foreach ($query->rows as $result) {
					$this->db->query("
						INSERT INTO `" . DB_PREFIX . "category_path` 
						SET 
							`category_id` = '" . (int) $category_id . "', 
							`path_id` 		= '" . (int) $result['path_id'] . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`level` 			= '" . (int) $level . "'
					");
	
					$level++;
				}
	
				$this->db->query("
					REPLACE INTO `" . DB_PREFIX . "category_path` 
					SET 
						`category_id` = '" . (int) $category_id . "', 
						`path_id` 		= '" . (int) $category_id . "', 
						`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
						`level` 			= '" . (int) $level . "'
				");
			}

			// Other associated stores
			// Set default path to root if category does not exist (user had set a new checkbox in store association)
			// If category exists ignore and skip
			foreach ($data['category_store'] as $store_id) {
				if (((int) $store_id) !== ((int) $this->session->data['store_id'])) {
					$this->db->query("
						INSERT IGNORE INTO `" . DB_PREFIX . "category_path` 
						SET 
							`category_id` = '" . (int) $category_id . "', 
							`store_id` 		= '" . (int) $store_id . "',
							`path_id` 		= '" . (int) $category_id . "', 
							`level` 			= '0'
					");
				}
			}
			// End category tree
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_filter 
				WHERE category_id = '" . (int) $category_id . "'
					AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['category_filter'])) {
				foreach ($data['category_filter'] as $filter_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "category_filter 
						SET 
							category_id = '" . (int) $category_id . "', 
							store_id 		= '" . (int) $this->session->data['store_id'] . "',
							filter_id 	= '" . (int) $filter_id . "'
					");
				}
			}
	
			// Store related data
			// Remove all unselected stores
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_to_store
				WHERE `category_id` = '" . (int) $category_id . "'
					AND `store_id` NOT IN (" . implode(',', array_map('intval', $data['category_store'])) . ")
			");

			// Remove only current store no matter if it's selected
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_to_store
				WHERE `category_id` = '" . (int) $category_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			// Write store association and store related data
			if (isset($data['category_store'])) {
				foreach ($data['category_store'] as $store_id) {
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						// Set data for current store
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "category_to_store 
							SET 
								`category_id` 	= '" . (int) $category_id . "', 
								`store_id`    	= '" . (int) $store_id . "',
								`parent_id` 		= '" . (int) $data['parent_id'] . "', 
								`status` 				= '" . (int) $data['status'] . "',
								`sort_order` 		= '" . (int) $data['sort_order'] . "', 
								`top` 					= '" . (isset($data['top']) ? (int) $data['top'] : 0) . "', 
								`column` 				= '" . (int) $data['column'] . "',
								`image` 				= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "'
						");
					} else {
						// Skip if data for other stores already exists
						$this->db->query("
							INSERT IGNORE INTO " . DB_PREFIX . "category_to_store
							SET 
								`category_id` = '" . (int) $category_id . "',
								`store_id`    = '" . (int) $store_id . "'
						");
					}
				}
			}
	
			// SEO URL
			$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'category_id=" . (int)$category_id . "'");
	
			if (isset($data['category_seo_url'])) {
				foreach ($data['category_seo_url'] as $store_id => $language) {
					foreach ($language as $language_id => $keyword) {
						if (!empty($keyword)) {
							$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'category_id=" . (int)$category_id . "', keyword = '" . $this->db->escape($keyword) . "'");
						}
					}
				}
			}
			
			$this->db->query("DELETE FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" . (int)$category_id . "'");
	
			if (isset($data['category_layout'])) {
				foreach ($data['category_layout'] as $store_id => $layout_id) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "category_to_layout SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
				}
			}

			// Check if user removed some store association checkboxes and delete all store-related data from non-checked stores
			if (isset($data['category_store'])) {
				$tables = [
					'category_description',
					'category_filter',
					'category_to_layout',
					'product_to_category',
					'category_path',
					'coupon_category',
				];

				// Remove all redundant data if present 
				foreach ($tables as $table) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . $table . "
						WHERE category_id = '" . (int) $category_id . "'
							AND store_id NOT IN (" . implode(',', $data['category_store']) . ")
					");
				}

				// Cleanup category path
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "category_path
					WHERE `path_id` = '" . (int) $category_id . "'
						AND store_id NOT IN (" . implode(',', $data['category_store']) . ")
				");
			}
	
			$this->db->query("COMMIT");

			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_value_id: $category_id, facet_type: 1, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_value_id: $category_id, facet_type: 1, store_id: $store_id);

			// Delete cache
			$this->deleteCache($category_id);

			return $category_id;

		} catch (\Throwable $e) {
			
			$this->db->query("ROLLBACK");
			
			throw $e;
		}
	}

	/**
	 * Delete category with SQL transaction to keep data integrity
	 * @param int $category_id category to be deleted
	 * @param bool $useTransaction used to avoid transaction nesting on nested call of deleteCategory() because SQL does not have nested transactions
	 * @return bool
	 */
	public function deleteCategory($category_id, $useTransaction = true) : bool {
		
		if ($useTransaction) {
			$this->db->query("START TRANSACTION");
		}

		try {
			// Delete cache
			$this->deleteCache($category_id);

			$query = $this->db->query("
				SELECT * FROM " . DB_PREFIX . "category_path 
				WHERE path_id  = '" . (int) $category_id . "'
					AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_path 
				WHERE category_id = '" . (int) $category_id . "'
					AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_description WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_filter WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_to_store WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_to_category WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'category_id=" . (int)$category_id . "' AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "coupon_category WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");

			// Delete children categories
			foreach ($query->rows as $result) {
				// Delete cache
				$this->deleteCache($result['category_id']);
				// Second param is false so SQL transaction is not closed prematurely
				$this->deleteCategory($result['category_id'], false);
			}

			// Next code will fully delete every category data if it is not associated to any store
			// Check if category present in other stores
			$categoryInOtherStores = $this->db->query("
				SELECT
					category_id
				FROM " . DB_PREFIX . "category_to_store
				WHERE category_id  = '" . (int) $category_id . "'
					AND store_id 		<> '" . (int) $this->session->data['store_id'] . "' 
			")->num_rows;

			// Delete all category data row if category is not present in any other store
			if (!$categoryInOtherStores) {
				$tables = [
					'category',
					'category_description',
					'category_filter',
					'category_to_layout',
					'product_to_category',
					'category_path',
					'coupon_category',
				];

				// Remove all redundant data if present 
				foreach ($tables as $table) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . $table . "
						WHERE category_id = " . (int) $category_id
					);
				}

				// Cleanup category path
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "category_path
					WHERE `path_id` = '" . (int) $category_id . "'
				");

				// Cleanup URLs
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "seo_url
					WHERE `query` = 'category_id=" . (int) $category_id . "'
				");
			}
	
			// Rebuild facet indexes
			$this->load->model('catalog/facet');
			$store_id = (int) $this->session->data['store_id'];
			$this->model_catalog_facet->buildFacetNames(facet_value_id: $category_id, facet_type: 1, store_id: $store_id);
			$this->model_catalog_facet->buildFacetIndex(facet_value_id: $category_id, facet_type: 1, store_id: $store_id);

			if ($useTransaction) {
				$this->db->query("COMMIT");
			}

			return true;
			
		} catch (\Throwable $e) {
			
			if ($useTransaction) {
				$this->db->query("ROLLBACK");
			}

			throw $e;
		}
	}

	public function repairCategories($parent_id = 0) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category WHERE parent_id = '" . (int)$parent_id . "'");

		foreach ($query->rows as $category) {
			// Delete the path below the current one
			$this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category['category_id'] . "'");

			// Fix for records with no paths
			$level = 0;

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$parent_id . "' ORDER BY level ASC");

			foreach ($query->rows as $result) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category['category_id'] . "', `path_id` = '" . (int)$result['path_id'] . "', level = '" . (int)$level . "'");

				$level++;
			}

			$this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category['category_id'] . "', `path_id` = '" . (int)$category['category_id'] . "', level = '" . (int)$level . "'");

			$this->repairCategories($category['category_id']);
		}
	}

	public function getCategory($category_id) {
		$query = $this->db->query("
			SELECT 
				*,
				c.category_id,
				c2s.image,
				c2s.status,
				c2s.sort_order,
				c2s.top,
				c2s.column,
				c2s.date_modified,
				(
					SELECT 
						GROUP_CONCAT(cd1.name ORDER BY level SEPARATOR '&nbsp;&#9656;&nbsp;') 
					FROM " . DB_PREFIX . "category_path cp 
					LEFT JOIN " . DB_PREFIX . "category_description cd1 
						ON (cp.path_id 				= cd1.category_id 
							AND cp.category_id 	!= cp.path_id
							AND cp.store_id 		= '" . (int) $this->session->data['store_id'] . "' 
						)
					WHERE cp.category_id = c.category_id 
						AND cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' 
						AND cd1.store_id 		= '" . (int) $this->session->data['store_id'] . "'
						AND cp.store_id 		= '" . (int) $this->session->data['store_id'] . "'
					GROUP BY cp.category_id
				) AS path 
			FROM " . DB_PREFIX . "category c 
			LEFT JOIN " . DB_PREFIX . "category_description cd2 
				ON (c.category_id = cd2.category_id) 
			LEFT JOIN " . DB_PREFIX . "category_to_store c2s
				ON c2s.category_id = c.category_id 
				AND c2s.store_id = '" . (int) $this->session->data['store_id'] . "'
			WHERE c.category_id 		= '" . (int)$category_id . "' 
				AND cd2.language_id 	= '" . (int)$this->config->get('config_language_id') . "'
				AND cd2.store_id 			= '" . (int) $this->session->data['store_id'] . "'
		");
		
		return $query->row;
	}

	// Get categories list
	// Used in admin category list AND autocomplete
	// Regular list should show categories in all stores with related to store
	// Autocomplete should always have parameter store_id to filter categories by store id
	public function getCategories($data = array()) {
		$result = [];
		$where = [];

		$where[] = "
			cd.language_id = '" . (int) $this->config->get('config_language_id') . "'
		";

		// Connect to external table
		$where[] = "
			cd.category_id = c.category_id
		";

		if (isset($data['filter_name'])) {
			$where[] = "
				cd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'
			";
		}
		if (isset($data['store_id'])) {
			$where[] = "
				cd.store_id = '" . (int) $data['store_id'] . "'
			";
		}

		$sql = "
			SELECT
				c.category_id,
				c2s.image,
				c2s.status,
				c2s.sort_order,
				c2s.top,
				c2s.column,
				(
					SELECT 
						GROUP_CONCAT(t.name ORDER BY t.level SEPARATOR '&nbsp;&#9656;&nbsp; ')
					FROM (
						SELECT
							cp.level,
							(
								SELECT cd2.name
								FROM " . DB_PREFIX . "category_description cd2
								WHERE cd2.category_id = cp.path_id
								ORDER BY
									FIELD(cd2.store_id, '" . (int)$this->session->data['store_id'] . "') DESC,
									FIELD(cd2.language_id, '" . (int)$this->config->get('config_language_id') . "') DESC
								LIMIT 1
							) AS name
					FROM " . DB_PREFIX . "category_path cp
					WHERE cp.category_id = c.category_id
						AND cp.store_id = (
							SELECT cp2.store_id
							FROM " . DB_PREFIX . "category_path cp2
							WHERE cp2.category_id = c.category_id
							ORDER BY FIELD(cp2.store_id, '" . (int)$this->session->data['store_id'] . "') DESC
							LIMIT 1
						)
					) t
				) AS `name`,
				(SELECT JSON_OBJECTAGG(c2s.store_id, c2s.status) FROM " . DB_PREFIX . "category_to_store c2s WHERE c2s.category_id = c.category_id) AS status_to_store,
				(SELECT COUNT(cf.filter_id) FROM " . DB_PREFIX . "category_filter cf WHERE cf.category_id = c.category_id AND cf.store_id = '" . (int) $this->session->data['store_id'] . "') AS filter_count,
				(SELECT COUNT(p2c.product_id) FROM " . DB_PREFIX . "product_to_category p2c WHERE p2c.category_id = c.category_id AND p2c.store_id = '" . (int) $this->session->data['store_id'] . "') AS product_count,
				(SELECT JSON_ARRAYAGG(c2s.store_id) FROM " . DB_PREFIX . "category_to_store c2s WHERE c.category_id = c2s.category_id) AS stores
			FROM " . DB_PREFIX . "category c
			LEFT JOIN " . DB_PREFIX . "category_to_store c2s
				ON c2s.category_id = c.category_id
				AND c2s.store_id = '" . (int) $this->session->data['store_id'] . "' 
			WHERE EXISTS (
				SELECT
					1
				FROM " . DB_PREFIX . "category_to_store c2s
        JOIN " . DB_PREFIX . "category_description cd
          ON cd.category_id = c2s.category_id
          AND cd.store_id = c2s.store_id
				WHERE " . implode(' and ', $where) . "
			)
		";

		$sort_data = array(
			'name',
			'stores',
			'product_count',
			'c2s.top',
			'c2s.parent_id',
			'c2s.sort_order',
			'c2s.date_modified'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY FIELD(c2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, " . $data['sort'];
		} else {
			$sql .= " ORDER BY FIELD(c2s.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, c2s.sort_order";
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
			$row['stores'] 					= json_decode($row['stores'] ?? '[]');
			$row['status_to_store'] = json_decode($row['status_to_store'] ?? '[]', true);
			$result[] = $row;
		}

		return $result;
	}

	public function getCategoryDescriptions($category_id) {
		$category_description_data = array();

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "category_description 
			WHERE category_id = '" . (int) $category_id . "'
				AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$category_description_data[$result['language_id']] = array(
				'name'             => $result['name'],
				'meta_title'       => $result['meta_title'],
				'meta_description' => $result['meta_description'],
				'meta_keyword'     => $result['meta_keyword'],
				'description'      => $result['description'],
				'seo_description'  => $result['seo_description'],
				'footer' 					 => json_decode($result['footer'] ?? '[]', true),
				'faq'    					 => json_decode($result['faq'] ?? '[]', true),
				'how_to' 					 => json_decode($result['how_to'] ?? '[]', true),
			);
		}

		return $category_description_data;
	}
	
	public function getCategoryPath($category_id) {
		$query = $this->db->query("
			SELECT 
				category_id, 
				path_id, 
				level 
			FROM " . DB_PREFIX . "category_path 
			WHERE category_id = '" . (int) $category_id . "'
				AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
		");

		return $query->rows;
	}
	
	public function getCategoryFilters($category_id) {
		$category_filter_data = array();

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "category_filter 
			WHERE category_id = '" . (int) $category_id . "'
				AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$category_filter_data[] = $result['filter_id'];
		}

		return $category_filter_data;
	}

	public function getCategoryStores($category_id) {
		$category_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_to_store WHERE category_id = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_store_data[] = $result['store_id'];
		}

		return $category_store_data;
	}
	
	public function getCategorySeoUrls($category_id) {
		$category_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'category_id=" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $category_seo_url_data;
	}
	
	public function getCategoryLayouts($category_id) {
		$category_layout_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_layout_data[$result['store_id']] = $result['layout_id'];
		}

		return $category_layout_data;
	}

	public function getTotalCategories() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "category");

		return $query->row['total'];
	}
	
	public function getTotalCategoriesByLayoutId($layout_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "category_to_layout WHERE layout_id = '" . (int)$layout_id . "'");

		return $query->row['total'];
	}

	// Set category status
	public function setCategoryStatus($category_id, $status) : int {
		$this->db->query("
			UPDATE " . DB_PREFIX . "category_to_store
				SET `status` = '" . (int) $status . "',
				`date_modified` = NOW()
			WHERE `category_id` = '" . (int) $category_id . "'
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		");

		$this->db->query("
			UPDATE " . DB_PREFIX . "category
				SET `status` = '" . (int) $status . "',
				`date_modified` = NOW()
			WHERE `category_id` = '" . (int) $category_id . "'
		");

		$query = $this->db->query("
			SELECT c2s.`status` 
			FROM " . DB_PREFIX . "category_to_store c2s
			JOIN " . DB_PREFIX . "category c
				ON c2s.`category_id` = c.`category_id`
			WHERE c2s.category_id 	= '" . (int) $category_id . "'
				AND c2s.store_id 		= '" . (int) $this->session->data['store_id'] . "'
			LIMIT 1
		")->row;

		$newStatus = $query['status'];

		// Rebuild facet indexes
		$this->load->model('catalog/facet');
		$store_id = (int) $this->session->data['store_id'];
		$this->model_catalog_facet->buildFacetNames(facet_value_id: $category_id, facet_type: 1, store_id: $store_id);
		$this->model_catalog_facet->buildFacetIndex(facet_value_id: $category_id, facet_type: 1, store_id: $store_id);
		
		$this->deleteCache($category_id);
		
		return (int) $newStatus;
	}

	// Delete cache
	public function deleteCache($category_id, $store_id = null) : void {
		
		if ($store_id === null) {
			$store_id = (int) $this->session->data['store_id'];
		}
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();
		$parent_id = $this->db->query("
			SELECT
				parent_id
			FROM " . DB_PREFIX . "category_to_store
			WHERE category_id = {$category_id}
			AND store_id = {$store_id}
		")->row['parent_id'] ?? 0;

		foreach ($languages as $language) {
			$language_id = $language['language_id'];

			// Main cache
			$categoryCacheName 	= "category.store_{$store_id}.language_{$language_id}." . (floor($category_id / 100)) . "00.category_{$category_id}";
			$this->cache->delete($categoryCacheName);

			// Filter cache
			$filterCacheName = "category.store_{$store_id}.language_{$language_id}." . (floor($category_id / 100)) . "00.filters_{$category_id}";
			$this->cache->delete($filterCacheName);

			// Children categories cache of this category 
			$childrenCacheName 	= "category.store_{$store_id}.language_{$language_id}." . (floor($parent_id / 100)) . "00.child_categories_{$category_id}";
			$this->cache->delete($childrenCacheName);

			// Children categories cache of parent category 
			$parentCacheName 	= "category.store_{$store_id}.language_{$language_id}." . (floor($parent_id / 100)) . "00.child_categories_{$parent_id}";
			$this->cache->delete($parentCacheName);

			// URL cache
			$urlCacheName = "url.store_{$store_id}.language_{$language_id}.url";
			$this->cache->delete($urlCacheName);
		}
	}

	/**
   * Filter array recursively and remove empty key => value pairs
   * @param array $array The array to be affected
   * @param array $deletedKeys The array of keys that will be treated as empty if all other keys are empty on this level 
   * @return array
   */
  public function filterArrayRecursively(array $array = [], array $deletedKeys = []): array {
    $filtered = [];

    foreach ($array as $key => $value) {
      if (is_array($value)) {
        $value = $this->filterArrayRecursively($value, $deletedKeys);
        if (!empty($value)) {
          $filtered[$key] = $value;
        }
      // $value !== '&lt;p&gt;&lt;br&gt;&lt;/p&gt;' is a workaround for empty Summernote editor which always places this string: '<p><br></p>'
      } elseif (trim((string) $value) !== '' && $value !== '&lt;p&gt;&lt;br&gt;&lt;/p&gt;') {
        $filtered[$key] = $value;
      }
    }

    // If result array is not empty, but only includes deletedKeys - then clear them also
    if (!empty($filtered)) {
      $nonDeletedKeys = array_diff(array_keys($filtered), $deletedKeys);

      // Check recursively
      $hasMeaningfulData = !empty($nonDeletedKeys);
      foreach ($filtered as $key => $value) {
        if (is_array($value) && !empty($value)) {
          $hasMeaningfulData = true;
          break;
        }
      }

      if (!$hasMeaningfulData) {
        return [];
      }
    }

    return $filtered;
  }
}