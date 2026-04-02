<?php
class ModelCatalogProduct extends Model {
	public function addProduct($data) {

		$this->db->query("START TRANSACTION");

		try {
			$this->db->query("
				INSERT INTO " . DB_PREFIX . "product 
				SET 
					`model`             = '" . $this->db->escape($data['model']) . "', 
					`parent_id`  				= '" . (isset($data['parent_id']) ? ((int) $data['parent_id']) : '0') . "',
					`sku`               = '" . $this->db->escape($data['sku']) . "', 
					`upc`               = '" . $this->db->escape($data['upc']) . "', 
					`ean`               = '" . $this->db->escape($data['ean']) . "', 
					`jan`               = '" . $this->db->escape($data['jan']) . "', 
					`isbn`              = '" . $this->db->escape($data['isbn']) . "', 
					`mpn`               = '" . $this->db->escape($data['mpn']) . "', 
					`location`          = '" . $this->db->escape($data['location']) . "', 
					`quantity`          = '" . (int) $data['quantity'] . "', 
					`minimum`           = '" . (int) $data['minimum'] . "', 
					`subtract`          = '" . (int) $data['subtract'] . "', 
					`stock_status_id`   = '" . (int) $data['stock_status_id'] . "', 
					`date_available`    = '" . $this->db->escape($data['date_available']) . "', 
					`manufacturer_id`   = '" . (int) $data['manufacturer_id'] . "', 
					`shipping`          = '" . (int) $data['shipping'] . "', 
					`price`             = '" . (float) $data['price'] . "', 
					`wholesale_price`   = '" . (float) $data['wholesale_price'] . "', 
					`points`            = '" . (int) $data['points'] . "', 
					`weight`            = '" . (float) $data['weight'] . "', 
					`weight_class_id`   = '" . (int) $data['weight_class_id'] . "', 
					`length`            = '" . (float) $data['length'] . "', 
					`width`             = '" . (float) $data['width'] . "', 
					`height`            = '" . (float) $data['height'] . "', 
					`length_class_id`   = '" . (int) $data['length_class_id'] . "', 
					`status`            = '" . (int) $data['status'] . "', 
					`is_available`      = '" . (int) $data['is_available'] . "', 
					`tax_class_id`      = '" . (int) $data['tax_class_id'] . "', 
					`sort_order`        = '" . (int) $data['sort_order'] . "', 
					`image` 						= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "',
					`date_added`        = NOW(), 
					`date_modified`     = NOW()
			");
	
			$product_id = $this->db->getLastId();

	
			foreach ($data['product_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "product_description 
					SET 
						`product_id` 				= '" . (int) $product_id . "', 
						`language_id` 			= '" . (int) $language_id . "', 
						`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
						`name` 							= '" . $this->db->escape($value['name']) . "', 
						`description` 			= '" . $this->db->escape($value['description']) . "', 
						`tag` 							= '" . $this->db->escape($value['tag']) . "', 
						`meta_title` 				= '" . $this->db->escape($value['meta_title']) . "', 
						`meta_description` 	= '" . $this->db->escape($value['meta_description']) . "', 
						`meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "'
				");
			}
	
			if (isset($data['product_store'])) {
				foreach ($data['product_store'] as $store_id) {
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						// Write data for current store
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "product_to_store 
							SET 
								`product_id` 		= '" . (int) $product_id . "', 
								`store_id` 			= '" . (int) $store_id . "',
								`sort_order` 		= '" . (int) $data['sort_order'] . "',
								`parent_id`  		= '" . (isset($data['parent_id']) ? ((int) $data['parent_id']) : '1') . "',
								`status`     		= '" . (int) $data['status'] . "',
								`is_available`  = '" . (int) $data['is_available'] . "', 
								`price`         = '" . (float) $data['price'] . "', 
								`image` 				= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "'
						");
					} else {
						// Set association for other selected stores
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "product_to_store 
							SET 
								`product_id` 		= '" . (int) $product_id . "', 
								`store_id`    	= '" . (int) $store_id . "'
						");
					}
				}
			}
	
			if (isset($data['product_attribute'])) {
				foreach ($data['product_attribute'] as $product_attribute) {
					if ($product_attribute['attribute_id']) {
						// Removes duplicates
						$this->db->query("
							DELETE FROM " . DB_PREFIX . "product_attribute 
							WHERE `product_id` 		= '" . (int) $product_id . "' 
								AND `attribute_id` 	= '" . (int) $product_attribute['attribute_id'] . "'
								AND `store_id` 			= '" . (int) $this->session->data['store_id'] . "'
						");
	
						foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {
							$this->db->query("
								DELETE FROM " . DB_PREFIX . "product_attribute 
								WHERE `product_id` 		= '" . (int) $product_id . "' 
									AND `attribute_id` 	= '" . (int) $product_attribute['attribute_id'] . "' 
									AND `language_id`		= '" . (int) $language_id . "'
									AND `store_id` 			= '" . (int) $this->session->data['store_id'] . "'
							");
	
							$this->db->query("
								INSERT INTO " . DB_PREFIX . "product_attribute 
								SET 
									`product_id` 					= '" . (int) $product_id . "', 
									`attribute_id` 				= '" . (int) $product_attribute['attribute_id'] . "', 
									`attribute_group_id` 	= (SELECT a2s.attribute_group_id FROM " . DB_PREFIX . "attribute_to_store a2s WHERE a2s.attribute_id = '" . (int) $product_attribute['attribute_id'] . "' AND a2s.store_id = '" . (int) $this->session->data['store_id'] . "'),
									`store_id` 						= '" . (int) $this->session->data['store_id'] . "',
									`language_id` 				= '" . (int) $language_id . "', 
									`text` 								= '" . $this->db->escape($product_attribute_description['text']) . "'
							");
						}
					}
				}
			}
	
			if (isset($data['product_option'])) {
				foreach ($data['product_option'] as $product_option) {
					if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
						if (isset($product_option['product_option_value'])) {
							$this->db->query("
								INSERT INTO " . DB_PREFIX . "product_option 
								SET 
									`product_id` 	= '" . (int) $product_id . "', 
									`option_id` 	= '" . (int) $product_option['option_id'] . "', 
									`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
									`required` 		= '" . (int) $product_option['required'] . "'
							");
	
							$product_option_id = $this->db->getLastId();
	
							foreach ($product_option['product_option_value'] as $product_option_value) {
								$this->db->query("
									INSERT INTO " . DB_PREFIX . "product_option_value 
									SET 
										`product_option_id` = '" . (int) $product_option_id . "', 
										`product_id` 				= '" . (int) $product_id . "', 
										`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
										`option_id` 				= '" . (int) $product_option['option_id'] . "', 
										`option_value_id` 	= '" . (int) $product_option_value['option_value_id'] . "', 
										`quantity` 					= '" . (int) $product_option_value['quantity'] . "',
										`subtract` 					= '" . (int) $product_option_value['subtract'] . "', 
										`price` 						= '" . (float) $product_option_value['price'] . "', 
										`price_prefix` 			= '" . $this->db->escape($product_option_value['price_prefix']) . "', 
										`points` 						= '" . (int) $product_option_value['points'] . "', 
										`points_prefix` 		= '" . $this->db->escape($product_option_value['points_prefix']) . "', 
										`weight` 						= '" . (float) $product_option_value['weight'] . "', 
										`weight_prefix` 		= '" . $this->db->escape($product_option_value['weight_prefix']) . "'
								");
							}
						}
					} else {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "product_option 
							SET 
								`product_id` 	= '" . (int) $product_id . "', 
								`option_id` 	= '" . (int) $product_option['option_id'] . "',
								`store_id` 		= '" . (int) $this->session->data['store_id'] . "', 
								`value` 			= '" . $this->db->escape($product_option['value']) . "', 
								`required` 		= '" . (int) $product_option['required'] . "'
						");
					}
				}
			}
	
			if (isset($data['product_recurring'])) {
				foreach ($data['product_recurring'] as $recurring) {
	
					$query = $this->db->query("
						SELECT 
							`product_id` 
						FROM `" . DB_PREFIX . "product_recurring` 
						WHERE `product_id` 				= '" . (int) $product_id . "' 
							AND `customer_group_id` = '" . (int) $recurring['customer_group_id'] . "' 
							AND `recurring_id` 			= '" . (int) $recurring['recurring_id'] . "'
							AND `store_id` 						= '" . (int) $this->session->data['store_id'] . "'
					");
	
					if (!$query->num_rows) {
						$this->db->query("
							INSERT INTO `" . DB_PREFIX . "product_recurring` 
							SET 
								`product_id` 				= '" . (int) $product_id . "', 
								`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
								`customer_group_id` = '" . (int) $recurring['customer_group_id'] . "', 
								`recurring_id` 			= '" . (int) $recurring['recurring_id'] . "'
						");
					}
				}
			}
			
			if (isset($data['product_discount'])) {
				foreach ($data['product_discount'] as $product_discount) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_discount 
						SET 
							`product_id` 				= '" . (int) $product_id . "', 
							`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
							`customer_group_id` = '" . (int) $product_discount['customer_group_id'] . "', 
							`quantity` 					= '" . (int) $product_discount['quantity'] . "', 
							`priority` 					= '" . (int) $product_discount['priority'] . "', 
							`price` 						= '" . (float) $product_discount['price'] . "', 
							`date_start` 				= '" . $this->db->escape($product_discount['date_start']) . "', 
							`date_end` 					= '" . $this->db->escape($product_discount['date_end']) . "'
					");
				}
			}
	
			if (isset($data['product_special'])) {
				foreach ($data['product_special'] as $product_special) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_special 
						SET 
							`product_id` 					= '" . (int) $product_id . "', 
							`store_id` 						= '" . (int) $this->session->data['store_id'] . "',
							`customer_group_id` 	= '" . (int) $product_special['customer_group_id'] . "', 
							`priority` 						= '" . (int) $product_special['priority'] . "', 
							`price` 							= '" . (float) $product_special['price'] . "', 
							`date_start` 					= '" . $this->db->escape($product_special['date_start']) . "', 
							`date_end` 						= '" . $this->db->escape($product_special['date_end']) . "'
					");
				}
			}
	
			if (isset($data['product_image'])) {
				foreach ($data['product_image'] as $product_image) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_image 
						SET 
							`product_id` 	= '" . (int) $product_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`image` 			= '" . $this->db->escape($product_image['image']) . "', 
							`sort_order` 	= '" . (int) $product_image['sort_order'] . "'
					");
				}
			}
	
			if (isset($data['product_download'])) {
				foreach ($data['product_download'] as $download_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_to_download 
							SET 
								`product_id` 	= '" . (int) $product_id . "', 
								`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
								`download_id` = '" . (int) $download_id . "'
					");
				}
			}
	
			if (isset($data['product_category'])) {
				foreach ($data['product_category'] as $category_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_to_category 
						SET 
							`product_id` 	= '" . (int) $product_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`category_id` = '" . (int) $category_id . "'
					");
				}
			}
	
			if (isset($data['product_filter'])) {
				foreach ($data['product_filter'] as $filter_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_filter 
						SET 
							`product_id` 			= '" . (int) $product_id . "', 
							`store_id` 				= '" . (int) $this->session->data['store_id'] . "',
							`filter_id` 			= '" . (int) $filter_id . "',
							`filter_group_id` = (SELECT f.`filter_group_id` FROM `" . DB_PREFIX . "filter` f WHERE f.`filter_id` = '" . (int) $filter_id . "' AND f.`store_id` = '" . (int) $this->session->data['store_id'] . "' LIMIT 1)
					");
				}
			}
	
			if (isset($data['product_related'])) {
				foreach ($data['product_related'] as $related_id) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . "product_related 
						WHERE `product_id` = '" . (int) $product_id . "' 
							AND `related_id` = '" . (int) $related_id . "'
							AND `store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
					");
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_related 
						SET 
							`product_id` 	= '" . (int) $product_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`related_id` 	= '" . (int) $related_id . "'
					");
					$this->db->query("
						DELETE FROM " . DB_PREFIX . "product_related 
						WHERE `product_id` = '" . (int) $related_id . "' 
							AND `related_id` = '" . (int) $product_id . "'
							AND `store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
					");
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_related 
						SET 
							`product_id` 	= '" . (int) $related_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`related_id` 	= '" . (int) $product_id . "'
					");
				}
			}
	
			if (isset($data['product_reward'])) {
				foreach ($data['product_reward'] as $customer_group_id => $product_reward) {
					if ((int) $product_reward['points'] > 0) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "product_reward 
							SET 
								`product_id` 				= '" . (int) $product_id . "', 
								`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
								`customer_group_id` = '" . (int) $customer_group_id . "', 
								`points` 						= '" . (int) $product_reward['points'] . "'
						");
					}
				}
			}
			
			// SEO URL
			if (isset($data['product_seo_url'])) {
				foreach ($data['product_seo_url'] as $store_id => $language) {
					foreach ($language as $language_id => $keyword) {
						if (!empty($keyword)) {
							$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET `store_id` = '" . (int)$store_id . "', `language_id` = '" . (int)$language_id . "', `query` = 'product_id=" . (int)$product_id . "', `keyword` = '" . $this->db->escape($keyword) . "'");
						}
					}
				}
			}
			
			if (isset($data['product_layout'])) {
				foreach ($data['product_layout'] as $store_id => $layout_id) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET `product_id` = '" . (int)$product_id . "', `store_id` = '" . (int)$store_id . "', `layout_id` = '" . (int)$layout_id . "'");
				}
			}

			// Commit DB queries
			$this->db->query("COMMIT");
			
			// Add product to facet filter index
			$this->load->model('catalog/facet');
			$this->model_catalog_facet->buildFacetIndex(product_id: (int) $product_id, store_id: (int) $this->session->data['store_id']);
			$this->model_catalog_facet->buildFacetSorts(product_id: (int) $product_id, store_id: (int) $this->session->data['store_id']);

			// Delete cache
			$this->deleteCache($product_id, $this->session->data['store_id']);

			return $product_id;

		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
		}

	}

	public function editProduct($product_id, $data) {

		$this->db->query("START TRANSACTION");

		try {

			$this->db->query("
				UPDATE " . DB_PREFIX . "product 
				SET 
					`model`             = '" . $this->db->escape($data['model']) . "', 
					`parent_id`  				= '" . (isset($data['parent_id']) ? ((int) $data['parent_id']) : '1') . "',
					`sku`               = '" . $this->db->escape($data['sku']) . "', 
					`upc`               = '" . $this->db->escape($data['upc']) . "', 
					`ean`               = '" . $this->db->escape($data['ean']) . "', 
					`jan`               = '" . $this->db->escape($data['jan']) . "', 
					`isbn`              = '" . $this->db->escape($data['isbn']) . "', 
					`mpn`               = '" . $this->db->escape($data['mpn']) . "', 
					`location`          = '" . $this->db->escape($data['location']) . "', 
					`quantity`          = '" . (int) $data['quantity'] . "', 
					`minimum`           = '" . (int) $data['minimum'] . "', 
					`subtract`          = '" . (int) $data['subtract'] . "', 
					`stock_status_id`   = '" . (int) $data['stock_status_id'] . "', 
					`date_available`    = '" . $this->db->escape($data['date_available']) . "', 
					`manufacturer_id`   = '" . (int) $data['manufacturer_id'] . "', 
					`shipping`          = '" . (int) $data['shipping'] . "', 
					`price`             = '" . (float) $data['price'] . "', 
					`wholesale_price`   = '" . (float) $data['wholesale_price'] . "', 
					`points`            = '" . (int) $data['points'] . "', 
					`weight`            = '" . (float) $data['weight'] . "', 
					`weight_class_id`   = '" . (int) $data['weight_class_id'] . "', 
					`length`            = '" . (float) $data['length'] . "', 
					`width`             = '" . (float) $data['width'] . "', 
					`height`            = '" . (float) $data['height'] . "', 
					`length_class_id`   = '" . (int) $data['length_class_id'] . "', 
					`status`            = '" . (int) $data['status'] . "', 
					`is_available`      = '" . (int) $data['is_available'] . "', 
					`tax_class_id`      = '" . (int) $data['tax_class_id'] . "', 
					`sort_order`        = '" . (int) $data['sort_order'] . "', 
					`image` 						= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "',
					`date_modified`     = NOW() 
				WHERE `product_id`    = '" . (int) $product_id . "'
			");
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_description 
				WHERE product_id  = '" . (int) $product_id . "'
					AND store_id 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			foreach ($data['product_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "product_description 
					SET 
						product_id 				= '" . (int) $product_id . "', 
						language_id 			= '" . (int) $language_id . "', 
						store_id 					= '" . (int) $this->session->data['store_id'] . "',
						name 							= '" . $this->db->escape($value['name']) . "', 
						description 			= '" . $this->db->escape($value['description']) . "', 
						tag 							= '" . $this->db->escape($value['tag']) . "', 
						meta_title 				= '" . $this->db->escape($value['meta_title']) . "', 
						meta_description 	= '" . $this->db->escape($value['meta_description']) . "', 
						meta_keyword 			= '" . $this->db->escape($value['meta_keyword']) . "'
				");
			}
	
			// Stores association
			// Remove all not selected stores
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_to_store
				WHERE `product_id` = '" . (int) $product_id . "'
					AND `store_id` NOT IN (" . implode(',', array_map('intval', $data['product_store'])) . ")
			");

			// Remove only current store no matter if it's selected
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_to_store
				WHERE `product_id` = '" . (int) $product_id . "'
					AND `store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
			");
	
			// Write store association and store related data
			if (isset($data['product_store'])) {
				foreach ($data['product_store'] as $store_id) {
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						// Set data for current store
						$sql = "
							INSERT INTO " . DB_PREFIX . "product_to_store (`product_id`, `store_id`, `sort_order`, `parent_id`, `status`, `is_available`, `price`, `image`)
							VALUES (
								'" . (int) $product_id . "',
								'" . (int) $store_id . "',
								'" . (int) $data['sort_order'] . "',
								'" . (isset($data['parent_id']) ? ((int) $data['parent_id']) : '0') . "',
								'" . (int) $data['status'] . "',
								'" . (int) $data['is_available'] . "',
								'" . (float) $data['price'] . "',
								'" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "'
							)
							ON DUPLICATE KEY UPDATE
								`sort_order` 		= '" . (int) $data['sort_order'] . "',
								`parent_id`  		= '" . (isset($data['parent_id']) ? ((int) $data['parent_id']) : '0') . "',
								`status`     		= '" . (int) $data['status'] . "',
								`is_available`  = '" . (int) $data['is_available'] . "', 
								`price` 				= '" . (float) $data['price'] . "',
								`image` 				= '" . (isset($data['image']) ? ($this->db->escape($data['image'])) : '') . "'
						";
						$this->db->query($sql);
					} else {
						// Skip if data for other stores already exists
						$this->db->query("
							INSERT IGNORE INTO " . DB_PREFIX . "product_to_store 
							SET 
								`product_id` 		= '" . (int) $product_id . "', 
								`store_id`    	= '" . (int) $store_id . "'
						");
					}
				}
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_attribute 
				WHERE `product_id` 	= '" . (int) $product_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (!empty($data['product_attribute'])) {
				foreach ($data['product_attribute'] as $product_attribute) {
					if ($product_attribute['attribute_id']) {
						// Removes duplicates
						$this->db->query("
							DELETE FROM " . DB_PREFIX . "product_attribute 
							WHERE `product_id` 		= '" . (int) $product_id . "' 
								AND `attribute_id` 	= '" . (int) $product_attribute['attribute_id'] . "'
								AND `store_id` 			= '" . (int) $this->session->data['store_id'] . "'
						");
	
						foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {
							$this->db->query("
								INSERT INTO " . DB_PREFIX . "product_attribute 
								SET 
									`product_id` 					= '" . (int) $product_id . "', 
									`store_id` 						= '" . (int) $this->session->data['store_id'] . "',
									`attribute_id` 				= '" . (int) $product_attribute['attribute_id'] . "', 
									`attribute_group_id` 	= (SELECT a2s.attribute_group_id FROM " . DB_PREFIX . "attribute_to_store a2s WHERE a2s.attribute_id = '" . (int) $product_attribute['attribute_id'] . "' AND a2s.store_id = '" . (int) $this->session->data['store_id'] . "'),
									`language_id` 				= '" . (int) $language_id . "', 
									`text` 								= '" .  $this->db->escape($product_attribute_description['text']) . "'
							");
						}
					}
				}
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_option 
				WHERE `product_id` 	= '" . (int) $product_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_option_value 
				WHERE `product_id` 	= '" . (int) $product_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_option'])) {
				foreach ($data['product_option'] as $product_option) {
					if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
						if (isset($product_option['product_option_value'])) {
	
							$this->db->query("
								INSERT INTO " . DB_PREFIX . "product_option 
								SET 
									`product_option_id` = '" . (int) $product_option['product_option_id'] . "', 
									`product_id` 				= '" . (int) $product_id . "', 
									`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
									`option_id` 				= '" . (int) $product_option['option_id'] . "', 
									`required` 					= '" . (int) $product_option['required'] . "'
							");
	
							$product_option_id = $this->db->getLastId();
	
							foreach ($product_option['product_option_value'] as $product_option_value) {
								$this->db->query("
									INSERT INTO " . DB_PREFIX . "product_option_value 
									SET 
										`product_option_value_id` = '" . (int) $product_option_value['product_option_value_id'] . "', 
										`product_option_id` 			= '" . (int) $product_option_id . "', 
										`product_id` 							= '" . (int) $product_id . "',  
										`store_id` 								= '" . (int) $this->session->data['store_id'] . "',
										`option_id` 							= '" . (int) $product_option['option_id'] . "',  
										`option_value_id` 				= '" . (int) $product_option_value['option_value_id'] . "',  
										`quantity` 								= '" . (int) $product_option_value['quantity'] . "',  
										`subtract` 								= '" . (int) $product_option_value['subtract'] . "',  
										`price` 									= '" . (float) $product_option_value['price'] . "',  
										`price_prefix` 						= '" . $this->db->escape($product_option_value['price_prefix']) . "',  
										`points` 									= '" . (int) $product_option_value['points'] . "',  
										`points_prefix` 					= '" . $this->db->escape($product_option_value['points_prefix']) . "',  
										`weight` 									= '" . (float) $product_option_value['weight'] . "',  
										`weight_prefix` 					= '" . $this->db->escape($product_option_value['weight_prefix']) . "'
									");
							}
						}
					} else {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "product_option 
							SET 
								`product_option_id` = '" . (int) $product_option['product_option_id'] . "', 
								`product_id` 				= '" . (int) $product_id . "', 
								`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
								`option_id` 				= '" . (int) $product_option['option_id'] . "', 
								`value` 						= '" . $this->db->escape($product_option['value']) . "', 
								`required` 					= '" . (int) $product_option['required'] . "'
						");
					}
				}
			}
	
			$this->db->query("
				DELETE FROM `" . DB_PREFIX . "product_recurring` 
				WHERE `product_id`  = '" . (int) $product_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_recurring'])) {
				foreach ($data['product_recurring'] as $product_recurring) {
					$query = $this->db->query("
						SELECT `product_id` FROM `" . DB_PREFIX . "product_recurring` 
						WHERE `product_id` 				= '" . (int) $product_id . "' 
							AND `store_id` 					= '" . (int) $this->session->data['store_id'] . "'
							AND `customer_group_id` = '" . (int) $product_recurring['customer_group_id'] . "' 
							AND `recurring_id` 			= '" . (int) $product_recurring['recurring_id'] . "'
					");
	
					if (!$query->num_rows) {
						$this->db->query("
							INSERT INTO `" . DB_PREFIX . "product_recurring` 
							SET 
								`product_id` 				= '" . (int) $product_id . "', 
								`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
								`customer_group_id` = '" . (int) $product_recurring['customer_group_id'] . "', 
								`recurring_id` 			= '" . (int) $product_recurring['recurring_id'] . "'
						");
					}				
				}
			}
			
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_discount 
				WHERE `product_id` 	= '" . (int) $product_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_discount'])) {
				foreach ($data['product_discount'] as $product_discount) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_discount 
						SET 
							`product_id` 				= '" . (int) $product_id . "', 
							`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
							`customer_group_id` = '" . (int) $product_discount['customer_group_id'] . "', 
							`quantity` 					= '" . (int) $product_discount['quantity'] . "', 
							`priority` 					= '" . (int) $product_discount['priority'] . "', 
							`price` 						= '" . (float) $product_discount['price'] . "', 
							`date_start` 				= '" . $this->db->escape($product_discount['date_start']) . "', 
							`date_end` 					= '" . $this->db->escape($product_discount['date_end']) . "'
					");
				}
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_special 
				WHERE `product_id`  = '" . (int) $product_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_special'])) {
				foreach ($data['product_special'] as $product_special) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_special 
						SET 
							`product_id` 					= '" . (int) $product_id . "', 
							`store_id` 						= '" . (int) $this->session->data['store_id'] . "',
							`customer_group_id` 	= '" . (int) $product_special['customer_group_id'] . "', 
							`priority` 						= '" . (int) $product_special['priority'] . "', 
							`price` 							= '" . (float) $product_special['price'] . "', 
							`date_start` 					= '" . $this->db->escape($product_special['date_start']) . "', 
							`date_end` 						= '" . $this->db->escape($product_special['date_end']) . "'
					");
				}
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_image 
				WHERE `product_id` 	= '" . (int) $product_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_image'])) {
				foreach ($data['product_image'] as $product_image) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_image 
						SET 
							`product_id` 	= '" . (int) $product_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`image` 			= '" . $this->db->escape($product_image['image']) . "', 
							`sort_order` 	= '" . (int) $product_image['sort_order'] . "'
					");
				}
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_to_download 
				WHERE `product_id` = '" . (int) $product_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_download'])) {
				foreach ($data['product_download'] as $download_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_to_download
						SET 
							`product_id` 	= '" . (int) $product_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`download_id` = '" . (int) $download_id . "'
					");
				}
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_to_category 
				WHERE `product_id` 	= '" . (int) $product_id . "'
					AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_category'])) {
				foreach ($data['product_category'] as $category_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_to_category 
						SET 
							`product_id` 	= '" . (int) $product_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`category_id` = '" . (int) $category_id . "'
					");
				}
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_filter 
				WHERE `product_id` = '" . (int) $product_id . "'
					AND `store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_filter'])) {
				foreach ($data['product_filter'] as $filter_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_filter
						SET 
							`product_id`  		= '" . (int) $product_id . "', 
							`store_id` 				= '" . (int) $this->session->data['store_id'] . "', 
							`filter_id`   		= '" . (int) $filter_id . "',
							`filter_group_id` = (SELECT f.`filter_group_id` FROM `" . DB_PREFIX . "filter` f WHERE f.`filter_id` = '" . (int) $filter_id . "' AND f.`store_id` = '" . (int) $this->session->data['store_id'] . "' LIMIT 1)
					");
				}
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_related 
				WHERE `product_id` = '" . (int) $product_id . "' 
					AND `store_id`   = '" . (int) $this->session->data['store_id'] . "'
				");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_related 
				WHERE `related_id` = '" . (int) $product_id . "' 
					AND `store_id`   = '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_related'])) {
				foreach ($data['product_related'] as $related_id) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . "product_related 
						WHERE `product_id` = '" . (int) $product_id . "' 
							AND `related_id` = '" . (int) $related_id . "'
							AND `store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
					");
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_related 
						SET 
							`product_id` 	= '" . (int) $product_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`related_id` 	= '" . (int) $related_id . "'
					");
					$this->db->query("
						DELETE FROM " . DB_PREFIX . "product_related 
						WHERE `product_id` = '" . (int) $related_id . "' 
							AND `related_id` = '" . (int) $product_id . "'
							AND `store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
					");
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "product_related 
						SET 
							`product_id` 	= '" . (int) $related_id . "', 
							`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
							`related_id` 	= '" . (int) $product_id . "'
					");
				}
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_reward 
				WHERE `product_id` = '" . (int) $product_id . "'
					AND `store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
			");
	
			if (isset($data['product_reward'])) {
				foreach ($data['product_reward'] as $customer_group_id => $value) {
					if ((int) $value['points'] > 0) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "product_reward 
							SET 
								`product_id` 				= '" . (int) $product_id . "', 
								`store_id` 					= '" . (int) $this->session->data['store_id'] . "',
								`customer_group_id` = '" . (int) $customer_group_id . "', 
								`points` 						= '" . (int) $value['points'] . "'
						");
					}
				}
			}
			
			// SEO URL
			$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE `query` 		= 'product_id=" . (int) $product_id . "'");
			
			if (isset($data['product_seo_url'])) {
				foreach ($data['product_seo_url']as $store_id => $language) {
					foreach ($language as $language_id => $keyword) {
						if (!empty($keyword)) {
							$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET `store_id` = '" . (int)$store_id . "', `language_id` = '" . (int)$language_id . "', `query` = 'product_id=" . (int)$product_id . "', `keyword` = '" . $this->db->escape($keyword) . "'");
						}
					}
				}
			}
			
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int) $product_id . "'");
	
			if (isset($data['product_layout'])) {
				foreach ($data['product_layout'] as $store_id => $layout_id) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET `product_id` = '" . (int)$product_id . "', `store_id` = '" . (int)$store_id . "', `layout_id` = '" . (int)$layout_id . "'");
				}
			}

			$this->db->query("COMMIT");
			
			// Add product to facet filter index
			$this->load->model('catalog/facet');
			$this->model_catalog_facet->buildFacetIndex(product_id: (int) $product_id, store_id: (int) $this->session->data['store_id']);
			$this->model_catalog_facet->buildFacetSorts(product_id: (int) $product_id, store_id: (int) $this->session->data['store_id']);

			// Delete cache
			$this->deleteCache($product_id, $this->session->data['store_id']);

		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
		}
	}

	public function copyProduct($product_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "product p WHERE p.product_id = '" . (int)$product_id . "'");

		if ($query->num_rows) {
			$data = $query->row;

			$data['sku'] = '';
			$data['upc'] = '';
			$data['viewed'] = '0';
			$data['keyword'] = '';
			$data['status'] = '0';

			$data['product_attribute'] = $this->getProductAttributes($product_id);
			$data['product_description'] = $this->getProductDescriptions($product_id);
			$data['product_discount'] = $this->getProductDiscounts($product_id);
			$data['product_filter'] = $this->getProductFilters($product_id);
			$data['product_image'] = $this->getProductImages($product_id);
			$data['product_option'] = $this->getProductOptions($product_id);
			$data['product_related'] = $this->getProductRelated($product_id);
			$data['product_reward'] = $this->getProductRewards($product_id);
			$data['product_special'] = $this->getProductSpecials($product_id);
			$data['product_category'] = $this->getProductCategories($product_id);
			$data['product_download'] = $this->getProductDownloads($product_id);
			$data['product_layout'] = $this->getProductLayouts($product_id);
			$data['product_store'] = $this->getProductStores($product_id);
			$data['product_recurrings'] = $this->getRecurrings($product_id);

			$this->addProduct($data);
		}
	}

	public function deleteProduct($product_id) {

		// Delete cache
		$this->deleteCache($product_id, $this->session->data['store_id']);

		// List of tables with product data
		$tables = [
			'product_attribute',
			'product_description',
			'product_discount',
			'product_filter',
			'product_image',
			'product_option',
			'product_option_value',
			'product_related',
			'product_reward',
			'product_special',
			'product_to_category',
			'product_to_download',
			'product_to_layout',
			'product_to_store',
			'product_recurring',
			'review',
			'coupon_product',
			'facet_index',
			'facet_sort',
		];

		// Delete cache
		$this->deleteCache($product_id, (int) $this->session->data['store_id']);

		// SQL transaction
		$this->db->query("START TRANSACTION");

		try {
			// First delete prodcut data from current store
			foreach ($tables as $table) {
				$this->db->query("
					DELETE FROM " . DB_PREFIX . $table . "
					WHERE `product_id` = '" . (int) $product_id . "'
						AND store_id = '" . (int) $this->session->data['store_id'] . "'		
				");
			}

			// And delete product URL from current store
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "seo_url 
				WHERE `query` 		= 'product_id=" . (int) $product_id . "' 
					AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "'
			");
			
			// Check if product present in other stores
			$productInOtherStores = $this->db->query("
				SELECT
					product_id
				FROM " . DB_PREFIX . "product_to_store
				WHERE `product_id` = '" . (int) $product_id . "'
					AND `store_id` <> '" . (int) $this->session->data['store_id'] . "' 
			")->rows;

			// If product is not present in any other store delete all product data from all tables without store_id context
			// Also delete main `product` table row
			if (empty($productInOtherStores)) {
				
				// Delete cache
				$this->deleteCache($product_id);

				$this->db->query("
					DELETE FROM " . DB_PREFIX . "product
					WHERE product_id = '" . (int) $product_id . "'
				");

				// Remove all remaining data if present 
				foreach ($tables as $table) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . $table . "
						WHERE `product_id` = " . (int) $product_id
					);
				}

				// Cleanup URLs
				$this->db->query("
					DELETE FROM " . DB_PREFIX . "seo_url
					WHERE `query` = 'product_id=" . (int) $product_id . "'
				");
			}

			$this->db->query("COMMIT");

			return true;

		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
		}
		
	}

	// Get product basic data in various admin controllers
	// Should always rely on store_id, not involved in product lists
	public function getProduct($product_id) {
		$query = $this->db->query("
			SELECT 
				p.`product_id`,
				p.`model`,
				p.`sku`,
				p.`upc`,
				p.`ean`,
				p.`jan`,
				p.`isbn`,
				p.`mpn`,
				p.`location`,
				p.`quantity`,
				p.`stock_status_id`,
				p.`manufacturer_id`,
				p.`shipping`,
				COALESCE(p2s.`price`, p.`price`) AS price,
				p.`wholesale_price`,
				p.`points`,
				p.`tax_class_id`,
				p.`date_available`,
				p.`weight`,
				p.`weight_class_id`,
				p.`length`,
				p.`width`,
				p.`height`,
				p.`length_class_id`,
				p.`subtract`,
				p.`minimum`,
				p.`viewed`,
				p.`date_added`,
				p2s.`store_id`,
				p2s.`sort_order`,
				p2s.`parent_id`,
				p2s.`status`,
				p2s.`is_available`,
				p2s.`image`,
				p2s.`date_modified`,
				pd.`name`,
				pd.`description`
			FROM " . DB_PREFIX . "product p 
			LEFT JOIN " . DB_PREFIX . "product_to_store p2s
				ON  p.`product_id` 		= p2s.`product_id`
				AND p2s.`store_id` 		=	'" . (int) $this->session->data['store_id'] . "' 
			LEFT JOIN " . DB_PREFIX . "product_description pd 
				ON  p.`product_id` 		= pd.`product_id` AND pd.`store_id` = p2s.`store_id`
				AND pd.`language_id` 	= '" . (int) $this->config->get('config_language_id') . "'
			WHERE p.`product_id` 		= '" . (int) $product_id . "' 
		");

		return $query->row;
	}

	// Get product list
	// Used in admin product list and product autocomplete
	// Should get all products if no $data['store_id'] is set OR only store specific products otherwise 
	public function getProducts($data = []) {
		$result = [];
		
		$where = [];

		// Where clause
		// Connect to external table
		$where[] = "
			p2.`product_id` = p.`product_id`
		";
		$where[] = "
			pd.`language_id` = '" . (int)$this->config->get('config_language_id') . "'
		";

		if (isset($data['store_id'])) {
			$where[] = "
			 	p2s2.`store_id` = '" . (int) $data['store_id'] . "'
			";
		}

		if (!empty($data['filter_name'])) {
			$where[] = "
			 	AND pd.`name` LIKE '%" . $this->db->escape($data['filter_name']) . "%'
			";
		}

		if (!empty($data['filter_model'])) {
			$where[] = "
			 	AND p.`model` LIKE '%" . $this->db->escape($data['filter_model']) . "%'
			";
		}

		if (!empty($data['filter_price'])) {
			$where[] = "
			 	AND p.`price` LIKE '" . $this->db->escape($data['filter_price']) . "%'
			";
		}

		if (isset($data['filter_quantity']) && $data['filter_quantity'] !== '') {
			$where[] = "
			 	AND p.`quantity` = '" . (int) $data['filter_quantity'] . "'
			";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$where[] = "
			 	AND p.`status` = '" . (int) $data['filter_status'] . "'
			";
		}

		// Main query
		$sql = "
			SELECT 
				p.`product_id`,
				p.`model`,
				COALESCE(p2s.`price`, p.`price`) AS price,
				p.`wholesale_price`,
				p.`quantity`,
				p2s.`image`,
				p2s.`status`,
				p2s.`is_available`,
				p2s.`sort_order`,
				p2s.`date_modified`,
				p2s.`parent_id`,
				pst.`views`,
				pst.`orders`,
				(
					SELECT 
						pd.`name` 
					FROM " . DB_PREFIX . "product_description pd 
					WHERE pd.`product_id` = p.`product_id` 
					ORDER BY 
						FIELD(pd.`store_id`, '" . (int) $this->session->data['store_id'] ."') DESC,
						FIELD(pd.`language_id`, '" . (int) $this->config->get('config_language_id') . "') DESC
						LIMIT 1
				) AS `name`,
				(
					SELECT 
						GROUP_CONCAT(t.`name` ORDER BY t.`level` SEPARATOR '&nbsp;&#9656;&nbsp;')
					FROM (
						SELECT
							cp.`level`,
							(
								SELECT cd2.`name`
								FROM " . DB_PREFIX . "category_description cd2
								WHERE cd2.`category_id` = cp.`path_id`
								ORDER BY
									FIELD(cd2.`store_id`, '" . (int)$this->session->data['store_id'] . "') DESC,
									FIELD(cd2.`language_id`, '" . (int)$this->config->get('config_language_id') . "') DESC
								LIMIT 1
							) AS `name`
					FROM " . DB_PREFIX . "category_path cp
					WHERE cp.category_id = p2s.parent_id
						AND cp.store_id = (
							SELECT cp2.store_id
							FROM " . DB_PREFIX . "category_path cp2
							WHERE cp2.category_id = p2s.parent_id
							ORDER BY FIELD(cp2.store_id, '" . (int)$this->session->data['store_id'] . "') DESC
							LIMIT 1
						)
					) t
				) AS `parent_name`,
				(SELECT JSON_ARRAYAGG(p2s.`store_id`) FROM " . DB_PREFIX . "product_to_store p2s WHERE p2s.`product_id` = p.`product_id`) AS stores,
				(SELECT JSON_OBJECTAGG(p2s.`store_id`, p2s.`status`) FROM " . DB_PREFIX . "product_to_store p2s WHERE p2s.`product_id` = p.`product_id`) AS status_to_store,
				(SELECT COUNT(pa.`attribute_id`) FROM " . DB_PREFIX . "product_attribute pa WHERE pa.`product_id` = p.`product_id` AND pa.`store_id` = p2s.`store_id`) AS product_attributes,

				-- Product options list
				(SELECT 
					JSON_OBJECTAGG(
						t.option_id,
						JSON_OBJECT(
							'name', od.`name`,
							'group_id', od.`option_id`,
							'values', options_json
						)
					)
					FROM (
						SELECT
							pov.`option_id`,
							JSON_OBJECTAGG(pov.`option_value_id`, ovd.`name`) AS options_json
						FROM " . DB_PREFIX . "product_option_value pov
						JOIN " . DB_PREFIX . "option_value_description ovd
							ON 	ovd.`option_value_id` = pov.`option_value_id`
							AND ovd.`language_id` 		= '" . (int) $this->config->get('config_language_id') . "'
						WHERE pov.`product_id` 	= p.`product_id`
							AND pov.`store_id` 		= p2s.`store_id`
						GROUP BY pov.`option_id`
					) t
				 	JOIN " . DB_PREFIX . "option_description od
				 		ON od.`option_id` 	 = t.`option_id`
				 		AND od.`language_id` = '" . (int) $this->config->get('config_language_id') . "'
						AND od.`store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
				) AS product_options,

				-- Product filters list
				(
					SELECT JSON_OBJECTAGG(
						fgd.filter_group_id,
						JSON_OBJECT(
							'name', fgd.`name`,
							'group_id', fgd.`filter_group_id`,
							'values', filters_json
						)
					)
					FROM (
						SELECT
							f.`filter_group_id`,
							JSON_OBJECTAGG(f.`filter_id`, fd.`name`) AS filters_json
						FROM " . DB_PREFIX . "product_filter pf
						JOIN " . DB_PREFIX . "filter f
							ON f.`filter_id` = pf.`filter_id`
						JOIN " . DB_PREFIX . "filter_description fd
							ON 	fd.`filter_id` 		= f.`filter_id`
							AND fd.`language_id` 	= '" . (int) $this->config->get('config_language_id') . "'
						WHERE pf.`product_id` = p.`product_id`
							AND pf.`store_id` 	= p2s.`store_id`
						GROUP BY f.`filter_group_id`
					) t
					JOIN " . DB_PREFIX . "filter_group_description fgd
						ON fgd.`filter_group_id` = t.`filter_group_id`
						AND fgd.`language_id` = '" . (int) $this->config->get('config_language_id') . "'
						AND fgd.`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
				) AS product_filters

			FROM " . DB_PREFIX . "product p 

			LEFT JOIN " . DB_PREFIX . "product_to_store p2s 
				ON 	p2s.`product_id` 	= p.`product_id`
				AND p2s.`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			LEFT JOIN " . DB_PREFIX . "facet_sort pst
				ON 	pst.`product_id` = p.`product_id`
				AND pst.`store_id` 		= '" . (int) $this->session->data['store_id'] . "'

			WHERE EXISTS (
				SELECT
					1
				FROM " . DB_PREFIX . "product p2
				JOIN " . DB_PREFIX . "product_description pd 
					ON p2.`product_id` = pd.`product_id`
				JOIN " . DB_PREFIX . "product_to_store p2s2
					ON p2s2.`product_id` = p2.`product_id`
				WHERE " . implode(' AND ', $where) . "
			)
				
		";

		$sort_data = [
			'name',
			'p.model',
			'p.price',
			'p.quantity',
			'p2s.status',
			'p2s.sort_order',
			'p2s.date_modified'
		];

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY FIELD(p2s.`store_id`, '" . (int) $this->session->data['store_id'] ."') DESC, " . $data['sort'];
		} else {
			$sql .= " ORDER BY FIELD(p2s.`store_id`, '" . (int) $this->session->data['store_id'] ."') DESC, `name`";
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
			$row['product_filters'] = json_decode($row['product_filters'] ?? '[]', true);
			$row['product_options'] = json_decode($row['product_options'] ?? '[]', true);

			$result[] = $row;
		}

		return $result;

	}

	// Not used?
	public function getProductsByCategoryId($category_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product p 
			LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.`product_id` = pd.`product_id`) 
			LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.`product_id` = p2c.`product_id`) 
			WHERE pd.`language_id`  = '" . (int) $this->config->get('config_language_id') . "' 
				AND p2c.`category_id` = '" . (int) $category_id . "' 
				AND pd.`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
				AND p2c.`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			ORDER BY pd.`name` ASC
		");

		return $query->rows;
	}

	// Get product description for product edit form
	// Should always rely on store_id
	public function getProductDescriptions($product_id) : array {
		$product_description_data = [];

		$query = $this->db->query("
			SELECT 
				*
			FROM " . DB_PREFIX . "product_description pd
			WHERE pd.`product_id` = '" . (int)$product_id . "' 
				AND pd.`store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$product_description_data[$result['language_id']] = [
				'name'             	=> $result['name'],
				'description'      	=> $result['description'],
				'meta_title'       	=> $result['meta_title'],
				'meta_description' 	=> $result['meta_description'],
				'meta_keyword'     	=> $result['meta_keyword'],
				'tag'              	=> $result['tag'],
				'seo_keywords'     	=> $result['seo_keywords'],
				'seo_description'  	=> $result['seo_description'],
				'faq'              	=> $result['faq'],
				'how_to'           	=> $result['how_to'],
				'footer'           	=> $result['footer'],
			];
		}

		return $product_description_data;
	}

	public function getPlaceholders($product_id = null) : array {
		$placeholders = [];
		if ($product_id === null) {
			return $placeholders;
		}
		$query = $this->db->query("
			SELECT
				pd.language_id,
				(
					SELECT 
						pd2.`name` 
					FROM " . DB_PREFIX . "product_description pd2
					WHERE pd2.`product_id` = " . $product_id . "
					ORDER BY 
						FIELD(pd2.`store_id`, 	 '" . (int) $this->session->data['store_id'] ."') DESC,
						FIELD(pd2.`language_id`, pd.language_id) DESC
					LIMIT 1
				) AS `placeholder_name`,
				(
					SELECT 
						pd2.`meta_title` 
					FROM " . DB_PREFIX . "product_description pd2
					WHERE pd2.`product_id` = " . $product_id . "
					ORDER BY 
						FIELD(pd2.`store_id`, 	 '" . (int) $this->session->data['store_id'] ."') DESC,
						FIELD(pd2.`language_id`, pd.language_id) DESC
					LIMIT 1
				) AS `placeholder_meta_title`,
				(
					SELECT 
						pd2.`meta_description` 
					FROM " . DB_PREFIX . "product_description pd2
					WHERE pd2.`product_id` = " . $product_id . "
					ORDER BY 
						FIELD(pd2.`store_id`, 	 '" . (int) $this->session->data['store_id'] ."') DESC,
						FIELD(pd2.`language_id`, pd.language_id) DESC
					LIMIT 1
				) AS `placeholder_meta_description`
			FROM " . DB_PREFIX . "product_description pd
			WHERE pd.product_id = '" . (int) $product_id . "'
		");

		foreach ($query->rows as $result) {
			$placeholders[$result['language_id']] = [
				'placeholder_name'             => $result['placeholder_name'],
				'placeholder_meta_title'       => $result['placeholder_meta_title'],
				'placeholder_meta_description' => $result['placeholder_meta_description'],
			];
		}

		return $placeholders;
	}

	// Get product associated categories
	// Used in admin/controller/catalog/product/getForm() to show product form data 
	// and in admin/model/catalog/copyProduct() to duplicate product
	// Should always rely on store_id context
	// Returns only ids, so no lang context needed here
	public function getProductCategories($product_id) : array {
		$product_category_data = [];

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_to_category 
			WHERE `product_id`  = '" . (int) $product_id . "' 
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$product_category_data[] = $result['category_id'];
		}

		return $product_category_data;
	}

	// Get product associated filters
	// Used in admin/controller/catalog/product/getForm() to show product form data 
	// and in admin/model/catalog/copyProduct() to duplicate product
	// Should always rely on store_id context
	// Returns only ids, so no lang context needed here
	public function getProductFilters($product_id) : array {
		$product_filter_data = [];

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_filter 
			WHERE `product_id`  = '" . (int) $product_id . "'
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$product_filter_data[] = $result['filter_id'];
		}

		return $product_filter_data;
	}

	// Get product associated attributes and attribute descriptions
	// Used in admin/controller/catalog/product/getForm() to show product form data 
	// and in admin/model/catalog/copyProduct() to duplicate product
	// Should always rely on store_id context
	// Returns all languages data, so no lang context needed here
	public function getProductAttributes($product_id) : array {
		$product_attribute_data = [];

		$product_attribute_query = $this->db->query("
			SELECT 
				`attribute_id` 
			FROM " . DB_PREFIX . "product_attribute 
			WHERE `product_id`  = '" . (int)$product_id . "' 
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			GROUP BY `attribute_id`
		");

		foreach ($product_attribute_query->rows as $product_attribute) {
			$product_attribute_description_data = [];

			$product_attribute_description_query = $this->db->query("
				SELECT 
					* 
				FROM " . DB_PREFIX . "product_attribute 
				WHERE `product_id` 		= '" . (int) $product_id . "' 
					AND `attribute_id` 	= '" . (int) $product_attribute['attribute_id'] . "'
					AND `store_id` 			= '" . (int) $this->session->data['store_id'] . "'
			");

			foreach ($product_attribute_description_query->rows as $product_attribute_description) {
				$product_attribute_description_data[$product_attribute_description['language_id']] = ['text' => $product_attribute_description['text']];
			}

			$product_attribute_data[] = [
				'attribute_id'                  => $product_attribute['attribute_id'],
				'product_attribute_description' => $product_attribute_description_data
			];
		}

		return $product_attribute_data;
	}

	// Get product associated options
	// Used in admin/controller/catalog/product/getForm() to show product form data 
	// and in admin/model/catalog/copyProduct() to duplicate product
	// Should always rely on store_id context
	// Returns only non-language data
	public function getProductOptions($product_id) : array {
		$product_option_data = [];

		$product_option_query = $this->db->query("
			SELECT 
				* 
			FROM `" . DB_PREFIX . "product_option` po 
			LEFT JOIN `" . DB_PREFIX . "option` o ON po.`option_id` = o.`option_id` 
			LEFT JOIN " . DB_PREFIX . "option_to_store o2s ON o2s.`option_id` = po.`option_id` 
				AND o2s.`store_id` = po.`store_id`
			LEFT JOIN `" . DB_PREFIX . "option_description` od ON o.`option_id` = od.`option_id`
			WHERE po.`product_id` 	= '" . (int) $product_id . "' 
				AND od.`language_id` 	= '" . (int) $this->config->get('config_language_id') . "' 
				AND o2s.`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
				AND od.`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			ORDER BY o.`sort_order` ASC
		");

		foreach ($product_option_query->rows as $product_option) {
			$product_option_value_data = [];

			$product_option_value_query = $this->db->query("
				SELECT 
					* 
				FROM " . DB_PREFIX . "product_option_value pov 
				LEFT JOIN " . DB_PREFIX . "option_value ov ON(pov.option_value_id = ov.option_value_id) 
				WHERE pov.product_option_id = '" . (int) $product_option['product_option_id'] . "' 
					AND ov.store_id 					= '" . (int) $this->session->data['store_id'] . "'
					AND pov.store_id 					= '" . (int) $this->session->data['store_id'] . "'
				ORDER BY ov.sort_order ASC");

			foreach ($product_option_value_query->rows as $product_option_value) {
				$product_option_value_data[] = [
					'product_option_value_id' => $product_option_value['product_option_value_id'],
					'option_value_id'         => $product_option_value['option_value_id'],
					'quantity'                => $product_option_value['quantity'],
					'subtract'                => $product_option_value['subtract'],
					'price'                   => $product_option_value['price'],
					'price_prefix'            => $product_option_value['price_prefix'],
					'points'                  => $product_option_value['points'],
					'points_prefix'           => $product_option_value['points_prefix'],
					'weight'                  => $product_option_value['weight'],
					'weight_prefix'           => $product_option_value['weight_prefix']
				];
			}

			$product_option_data[] = [
				'product_option_id'    => $product_option['product_option_id'],
				'product_option_value' => $product_option_value_data,
				'option_id'            => $product_option['option_id'],
				'name'                 => $product_option['name'],
				'type'                 => $product_option['type'],
				'value'                => $product_option['value'],
				'required'             => $product_option['required']
			];
		}

		return $product_option_data;
	}

	public function getProductOptionValue($product_id, $product_option_value_id) {
		$query = $this->db->query("
			SELECT 
				pov.`option_value_id`, 
				ovd.`name`, 
				pov.`quantity`, 
				pov.`subtract`, 
				pov.`price`, 
				pov.`price_prefix`, 
				pov.`points`, 
				pov.`points_prefix`, 
				pov.`weight`, 
				pov.`weight_prefix` 
			FROM " . DB_PREFIX . "product_option_value pov 
			LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.`option_value_id` = ov.`option_value_id`) 
			LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.`option_value_id` = ovd.`option_value_id`) 
			WHERE pov.`product_id` = '" . (int) $product_id . "' 
				AND pov.`product_option_value_id` = '" . (int) $product_option_value_id . "' 
				AND ovd.`language_id` 						= '" . (int) $this->config->get('config_language_id') . "'
				AND ov.`store_id` 								= '" . (int) $this->session->data['store_id'] . "'
				AND ovd.`store_id` 								= '" . (int) $this->session->data['store_id'] . "'
				AND pov.`store_id` 								= '" . (int) $this->session->data['store_id'] . "'
		");

		return $query->row;
	}

	public function getProductImages($product_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_image 
			WHERE `product_id`  = '" . (int) $product_id . "' 
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			ORDER BY `sort_order` ASC
		");

		return $query->rows;
	}

	public function getProductDiscounts($product_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_discount 
			WHERE `product_id`  = '" . (int) $product_id . "' 
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			ORDER BY `quantity`, `priority`, `price`");

		return $query->rows;
	}

	public function getProductSpecials($product_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_special 
			WHERE `product_id`  = '" . (int) $product_id . "' 
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			ORDER BY `priority`, `price`
		");

		return $query->rows;
	}

	public function getProductRewards($product_id) {
		$product_reward_data = [];

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_reward 
			WHERE `product_id`  = '" . (int) $product_id . "'
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$product_reward_data[$result['customer_group_id']] = ['points' => $result['points']];
		}

		return $product_reward_data;
	}

	public function getProductDownloads($product_id) {
		$product_download_data = [];

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_to_download 
			WHERE `product_id`  = '" . (int)$product_id . "'
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$product_download_data[] = $result['download_id'];
		}

		return $product_download_data;
	}

	public function getProductStores($product_id) {
		$product_store_data = [];

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_store WHERE `product_id` = '" . (int)$product_id . "'");

		foreach ($query->rows as $result) {
			$product_store_data[] = $result['store_id'];
		}

		return $product_store_data;
	}
	
	public function getProductSeoUrls($product_id) {
		$product_seo_url_data = [];
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = 'product_id=" . (int)$product_id . "'");

		foreach ($query->rows as $result) {
			$product_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $product_seo_url_data;
	}
	
	public function getProductLayouts($product_id) {
		$product_layout_data = [];

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_layout WHERE `product_id` = '" . (int)$product_id . "'");

		foreach ($query->rows as $result) {
			$product_layout_data[$result['store_id']] = $result['layout_id'];
		}

		return $product_layout_data;
	}

	public function getProductRelated($product_id) {
		$product_related_data = [];

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_related 
			WHERE `product_id`  = '" . (int)$product_id . "'
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$product_related_data[] = $result['related_id'];
		}

		return $product_related_data;
	}

	public function getRecurrings($product_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM `" . DB_PREFIX . "product_recurring` 
			WHERE `product_id` = '" . (int)$product_id . "'
				AND `store_id` 		= '" . (int) $this->session->data['store_id'] . "'
		");

		return $query->rows;
	}

	// TODO Rewrite this to rely on product_to_store table
	public function getTotalProducts($data = []) {
		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)";

		$sql .= " WHERE pd.`language_id` = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND pd.`name` LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (!empty($data['filter_model'])) {
			$sql .= " AND p.`model` LIKE '" . $this->db->escape($data['filter_model']) . "%'";
		}

		if (isset($data['filter_price']) && !is_null($data['filter_price'])) {
			$sql .= " AND p.`price` LIKE '" . $this->db->escape($data['filter_price']) . "%'";
		}

		if (isset($data['filter_quantity']) && $data['filter_quantity'] !== '') {
			$sql .= " AND p.`quantity` = '" . (int)$data['filter_quantity'] . "'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND p.`status` = '" . (int)$data['filter_status'] . "'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getTotalProductsByTaxClassId($tax_class_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product WHERE `tax_class_id` = '" . (int)$tax_class_id . "'");

		return $query->row['total'];
	}

	public function getTotalProductsByStockStatusId($stock_status_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product WHERE `stock_status_id` = '" . (int)$stock_status_id . "'");

		return $query->row['total'];
	}

	public function getTotalProductsByWeightClassId($weight_class_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product WHERE `weight_class_id` = '" . (int)$weight_class_id . "'");

		return $query->row['total'];
	}

	public function getTotalProductsByLengthClassId($length_class_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product WHERE `length_class_id` = '" . (int)$length_class_id . "'");

		return $query->row['total'];
	}

	public function getTotalProductsByDownloadId($download_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product_to_download WHERE `download_id` = '" . (int)$download_id . "'");

		return $query->row['total'];
	}

	public function getTotalProductsByManufacturerId($manufacturer_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product WHERE `manufacturer_id` = '" . (int)$manufacturer_id . "'");

		return $query->row['total'];
	}

	// Used to prevent attribute deleting if it is associated with any product. Not used now
	public function getTotalProductsByAttributeId($attribute_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product_attribute WHERE `attribute_id` = '" . (int)$attribute_id . "'");

		return $query->row['total'];
	}

	// Used to prevent option deleting if it is associated with any product. Not used now
	public function getTotalProductsByOptionId($option_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product_option WHERE `option_id` = '" . (int)$option_id . "'");

		return $query->row['total'];
	}

	public function getTotalProductsByProfileId($recurring_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product_recurring WHERE `recurring_id` = '" . (int)$recurring_id . "'");

		return $query->row['total'];
	}

	public function getTotalProductsByLayoutId($layout_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product_to_layout WHERE `layout_id` = '" . (int)$layout_id . "'");

		return $query->row['total'];
	}

	// Set product status
	public function setProductStatus($product_id, $status) : int {
		// Check if product is assocated with current store
		$productIsAssociated = $this->db->query("
			SELECT
				`product_id`
			FROM " . DB_PREFIX . "product_to_store
			WHERE `product_id` = '" . (int) $product_id . "'
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		")->row;

		if (!isset($productIsAssociated['product_id'])) {
			return 0;
		}

		$this->db->query("
			UPDATE " . DB_PREFIX . "product_to_store
				SET 
					`status` = '" . (int) $status . "',
					`date_modified` = NOW()
			WHERE `product_id` = '" . (int) $product_id . "'
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		");

		$this->db->query("
			UPDATE " . DB_PREFIX . "product
				SET 
					`status` = '" . (int) $status . "',
					`date_modified` = NOW()
			WHERE `product_id` = '" . (int) $product_id . "'
		");

		$query = $this->db->query("
			SELECT p2s.`status` 
			FROM " . DB_PREFIX . "product_to_store p2s
			JOIN " . DB_PREFIX . "product p
				ON p2s.`product_id` = p.`product_id`
			WHERE p2s.`product_id` 	= '" . (int) $product_id . "'
				AND p2s.`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			LIMIT 1
		")->row;

		$newStatus = $query['status'];
	
		// Add product to facet filter index
		$this->load->model('catalog/facet');
		$this->model_catalog_facet->buildFacetIndex(product_id: (int) $product_id, store_id: (int) $this->session->data['store_id']);
		$this->model_catalog_facet->buildFacetSorts(product_id: (int) $product_id, store_id: (int) $this->session->data['store_id']);

		// Delete cache
		$this->deleteCache($product_id, (int) $this->session->data['store_id']);

		return (int) $newStatus;
	}

	// Set product is available for order
	public function setProductIsAvailable($product_id, $is_available) : int {

		// Check if product is assocated with current store
		$productIsAssociated = $this->db->query("
			SELECT
				`product_id`
			FROM " . DB_PREFIX . "product_to_store
			WHERE `product_id` = '" . (int) $product_id . "'
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		")->row;

		if (!isset($productIsAssociated['product_id'])) {
			return 0;
		}

		$this->db->query("
			UPDATE " . DB_PREFIX . "product_to_store
				SET 
					`is_available` = '" . (int) $is_available . "',
					`date_modified` = NOW()
			WHERE `product_id` = '" . (int) $product_id . "'
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		");

		$this->db->query("
			UPDATE " . DB_PREFIX . "product
				SET 
					`is_available` = '" . (int) $is_available . "',
					`date_modified` = NOW()
			WHERE `product_id` = '" . (int) $product_id . "'
		");

		$query = $this->db->query("
			SELECT p2s.`is_available` 
			FROM " . DB_PREFIX . "product_to_store p2s
			JOIN " . DB_PREFIX . "product p
				ON p2s.`product_id` = p.`product_id`
			WHERE p2s.`product_id` 	= '" . (int) $product_id . "'
				AND p2s.`store_id` 		= '" . (int) $this->session->data['store_id'] . "'
			LIMIT 1
		")->row;

		$newIsAvailable = $query['is_available'];

		// Delete cache
		$this->deleteCache($product_id, (int) $this->session->data['store_id']);
		
		// Add product to facet filter index
		$this->load->model('catalog/facet');
		$this->model_catalog_facet->buildFacetIndex(product_id: (int) $product_id, store_id: (int) $this->session->data['store_id']);
		$this->model_catalog_facet->buildFacetSorts(product_id: (int) $product_id, store_id: (int) $this->session->data['store_id']);

		return (int) $newIsAvailable;
	}

	// Build facet index
	// Should be called before previous SQL transaction committed

	// Delete cache
	public function deleteCache($product_id, $store_id = null) : void {

		if ($store_id === null) {
			$store_id = (int) $this->session->data['store_id'];
		}

		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		foreach ($languages as $language) {
			$language_id 	= (int) $language['language_id'];
			$store_id 		= (int) $store_id;

			// Delete product cache
			$productCacheName = "product.store_{$store_id}.language_{$language_id}." . (floor($product_id / 100)) . "00.product_{$product_id}";
			$this->cache->delete($productCacheName);

			// Delete related products cache
			$relatedProducts = $this->db->query("
				SELECT
					`related_id`
				FROM " . DB_PREFIX . "product_related
				WHERE `product_id` = '" . (int) $product_id . "'
					AND `store_id` = '" . (int) $store_id . "'
			")->rows;

			foreach ($relatedProducts as $relatedProduct) {
				$related_id = $relatedProduct['related_id'];
				$relatedCacheName 	= "product.store_{$store_id}.language_{$language_id}." . (floor($related_id / 100)) . "00.product_{$related_id}";
				$this->cache->delete($relatedCacheName);
			}

			$productCategories = $this->db->query("
				SELECT
					`category_id`
				FROM " . DB_PREFIX . "product_to_category
				WHERE `product_id` = '" . $product_id . "'
					AND `store_id` = '" . $store_id . "'
			")->rows;
			
			// Delete filter cache and product flags cache
			foreach ($productCategories as $productCategory) {
				$category_id = (int) $productCategory['category_id'];
				// DELETE filter cache to update each related category filter set. Parent category_id is included here by design
				$filterCacheName = "category.store_{$store_id}.language_{$language_id}." . (floor($category_id / 100)) . "00.filters_{$category_id}";
				$this->cache->delete($filterCacheName);

				// Delete product flags cache
				$flagsCacheName = "category.store_{$store_id}.language_{$language_id}." . (floor($category_id / 100)) . "00.product_flags_{$category_id}";
				$this->cache->delete($flagsCacheName);
			}

			// Delete URL cache
			$urlCacheName = "url.store_{$store_id}.language_{$language_id}.url";
			$this->cache->delete($urlCacheName);
		}
	}
}
