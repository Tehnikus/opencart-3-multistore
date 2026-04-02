<?php
class ModelCatalogProduct extends Model {

	public $sortOrders = [];
	public $facetTypes = [];
	public function __construct($registry) {
		parent::__construct($registry);
		/**
		 * Allowed sort orders and corresponding SQL queries
		 * Main table to sort products is facet_sort
		 */ 
		$this->sortOrders = [
			'sort_order'			=> 'pst.`sort_order` ASC',
			'sales'						=> 'pst.`orders` DESC',
			'rating'					=> '(CASE WHEN pst.`rating_avg` THEN pst.`rating_avg` ELSE pst.`sort_order` END) DESC',
			'views'						=> 'pst.`views` DESC',
			'date_added'			=> 'pst.`date_added` DESC',
			'available'				=> 'pst.`is_available` DESC',
			'featured'				=> 'pst.`is_featured` DESC',
			'price_asc'				=> '(CASE WHEN pst.`current_price` IS NOT NULL THEN pst.`current_price` ELSE pst.`sort_order` END) ASC',
			'price_desc'			=> '(CASE WHEN pst.`current_price` IS NOT NULL THEN pst.`current_price` ELSE pst.`sort_order` END) DESC',
			'discounts'				=> 'pst.`has_discount` DESC',
			'trends_all_time' => 'LOG(pst.`orders` + 1) * 4 + COALESCE(pst.`rating_avg`, 0) * LOG(pst.`review_count` + 1) * 2 + LOG(pst.`views` + 1)',
			'trends_by_date'  => '
				LOG(pst.`orders` + 1) * EXP(-0.01 * DATEDIFF(NOW(), COALESCE(pst.`date_last_order`, NOW())))
				+ LOG(pst.`views` + 1) * EXP(-0.005 * DATEDIFF(NOW(), COALESCE(pst.`date_last_view`, NOW())))
				+ (pst.`rating_avg` * LOG(pst.`review_count` + 1)) * EXP(-0.02 * DATEDIFF(NOW(), COALESCE(pst.`date_last_review`, NOW()))) DESC, 
				pst.`sort_order` ASC
			',
			// 'name'						=> 'pd.`name` ASC',
			// 'quantity'				=> 'p.`quantity` > p.`minimum` DESC, pst.`sort_order` ASC',
		];

		/**
		 * Allowed facet types 
		 * Integers correspond facet type in ENUM column `facet_type` in DB without 'filter_' prefix 
		 */
		$this->facetTypes = [
			'filter_category_id'   		=> 1,
			'filter_filter'        		=> 2,
			'filter_option'        		=> 3,
			'filter_attribute'     		=> 4,
			'filter_manufacturer_id'	=> 5,
			'filter_tag_id'           => 6,
			'filter_supplier_id'      => 7,
			'filter_is_available'  		=> 8,
			'filter_has_discount'  		=> 9,
			'filter_is_featured'   		=> 10,
		];
	}

	/**
	 * Return array of allowed sort order for external modules
	 * @return array
	 */
	public function getSortOrders() : array {
		return $this->sortOrders;
	}

	/**
	 * Return array of allowed facet types foe external modules
	 * @return array
	 */
	public function getFacetTypes() : array {
		return $this->facetTypes;
	}

	public function updateViewed($product_id) {
		$this->db->query("
			INSERT INTO " . DB_PREFIX . "facet_sort (product_id, store_id, views, date_last_view)
			VALUES ('" . (int) $product_id . "', '" . $this->config->get('config_store_id') . "', 1, NOW())
			ON DUPLICATE KEY UPDATE 
				views = (views + 1),
				date_last_view = NOW()
		");
	}

	/**
	 * Get valid discount row from all product discounts
	 * Compares date start and end, and customer group
	 * And returns array of discount with dates and discount value if valid row is found
	 * @param array $rows
	 * @param int $customerGroupId
	 * @return array|null
	 */
	private function getValidDiscount(array $rows, int $customerGroupId): ?array {
    $now = time();

    $valid = array_filter($rows, function ($r) use ($customerGroupId, $now) : bool {

			// Remove all rows that don't match customer_group_id 
			if ((int) $r['customer_group_id'] !== $customerGroupId) {
				return false;
			}

			// Normalize null dates from non strict SQL to null
			$start 	= (!$r['date_start'] || str_starts_with($r['date_start'],	'0000-00-00')) ? null : strtotime($r['date_start']);
			$end 	= (!$r['date_end'] || str_starts_with($r['date_end'],	'0000-00-00')) ? null : strtotime($r['date_end']);;
			
			// Remove all rows where discount starts later then now
			if ($start && $start > $now) {return false;}
			// remove all rows where discount ends earlier then now 
			if ($end && $end < $now) {return false;}

			return true;
    });

		// Order rows first by priority then by price
    usort($valid, fn($a,$b) =>
			[$a['priority'], $a['price']] <=> [$b['priority'], $b['price']]
    );

		// Return first valid row
    return $valid[0] ?? null;
	}

	/**
	 * Get all  data of a single product and put it into cache
	 * @param mixed $product_id
	 * @return array|bool
	 */
	public function getProduct($product_id) : array|bool {
		$product_id 				= (int) $product_id;
		$language_id 				= (int) $this->config->get('config_language_id');
		$store_id 					= (int) $this->config->get('config_store_id');
		$customer_group_id 	= (int) $this->config->get('config_customer_group_id');
		
		// Cache
		$cacheName 	= "product.store_{$store_id}.language_{$language_id}." . (floor($product_id / 100)) . "00.product_{$product_id}";
		$product 		= $this->cache->get($cacheName);
		
		if ($product) {
			/**
			 * Filter specials and discounts from cached data so cache shoul not invalidate if (current time > date end)
			 * TODO use $this->getValidDiscount() here for clarity, as these are mostly the same functions
			 */ 
			$now = date('Y-m-d H:i:s');
			$product['specials'] = array_filter($product['specials'], function ($var) use ($now) {
				return 
					strtotime($var['date_start']) <= strtotime($now)
					&& (
						strtotime($var['date_end']) >= strtotime($now) 
						|| $var['date_end'] === null
						|| str_contains($var['date_end'], '0000-00-00')
					)
				;
			});
			$product['discounts'] = array_filter($product['discounts'], function ($var) use ($now) {
				return 
					strtotime($var['date_start']) <= strtotime($now)
					&& (
						strtotime($var['date_end']) >= strtotime($now) 
						|| $var['date_end'] === null
						|| str_contains($var['date_end'], '0000-00-00')
					)
				;
			});

			return $product;
		}

		$sql = "
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
				
				p2s.`sort_order`,
				p2s.`parent_id`,
				p2s.`status`,				
				p2s.`date_modified`,

				COALESCE(p2s.`image`, p.`image`) AS image,
				COALESCE(NULLIF(p2s.`price`, 0), p.`price`) AS price,

				pd.`name`,
				pd.`meta_title`,
				pd.`meta_description`,
				pd.`meta_keyword`,
				pd.`tag`,
				pd.`description`,
				pd.`seo_keywords`,
				pd.`seo_description`,
				pd.`faq`,
				pd.`how_to`,
				pd.`footer`,
				pd.`date_modified` AS description_date_modified,

				pst.`views`,
				pst.`orders`,
				pst.`returns`,
				pst.`review_count` AS reviews, 
				pst.`rating_avg` AS rating,

				(
					SELECT 
						md.name 
					FROM " . DB_PREFIX . "manufacturer_description md 
					WHERE md.manufacturer_id = p.manufacturer_id 
						AND md.language_id 	= {$language_id}
						AND md.store_id 		= {$store_id}
				) AS manufacturer,

				(
					SELECT JSON_OBJECTAGG(
						pi.product_image_id, JSON_OBJECT(
							'image', 			pi.`image`,
							'sort_order', pi.`sort_order`
						)
					)
					FROM " . DB_PREFIX . "product_image pi
					WHERE pi.`product_id` = p2s.`product_id`
						AND pi.`store_id`   = p2s.`store_id`
				) AS images,

				(
					SELECT JSON_OBJECTAGG(
						ps.product_special_id, JSON_OBJECT(
							'product_id',         ps.`product_id`,
							'store_id',           ps.`store_id`,
							'customer_group_id',  ps.`customer_group_id`,
							'priority',           ps.`priority`,
							'price',              ps.`price`,
							'date_start',         ps.`date_start`,
							'date_end',           ps.`date_end`
						)
					) FROM " . DB_PREFIX . "product_special ps
					 WHERE ps.`product_id` = p2s.`product_id`
					 AND ps.`store_id` 		 = p2s.`store_id`
				) AS specials,

				(
					SELECT JSON_OBJECTAGG(
						pd.product_discount_id, JSON_OBJECT(
							'product_id',          pd.`product_id`,
							'store_id',            pd.`store_id`,
							'customer_group_id',   pd.`customer_group_id`,
							'quantity',            pd.`quantity`,
							'priority',            pd.`priority`,
							'price',               pd.`price`,
							'date_start',          pd.`date_start`,
							'date_end',            pd.`date_end`
						)
					) FROM " . DB_PREFIX . "product_discount pd
					 WHERE pd.`product_id` = p2s.`product_id`
					 AND pd.`store_id` 		 = p2s.`store_id`
				) AS discounts,

				(
					SELECT JSON_ARRAYAGG(
						JSON_OBJECT(
							'attribute_group_id', t.`attribute_group_id`,
							'name', 							t.`group_name`,
							'attribute', 					t.`attributes_json`
						)
					)
					FROM (
						SELECT
							pa.attribute_group_id,
							agd.`name` AS `group_name`,
				
							JSON_ARRAYAGG(
								JSON_OBJECT(
									'name', 				ad.`name`,
									'attribute_id', pa.`attribute_id`,
									'text', 				pa.`text`,
									'sort_order', 	a2s.`sort_order`
								)
							) AS `attributes_json`
				
						FROM " . DB_PREFIX . "product_attribute pa
				
						LEFT JOIN " . DB_PREFIX . "attribute_description ad
							ON ad.`attribute_id` = pa.`attribute_id`
							AND ad.`language_id` = pa.`language_id`
							AND ad.`store_id` = pa.`store_id`
				
						LEFT JOIN " . DB_PREFIX . "attribute_group_description agd
							ON agd.`attribute_group_id` = pa.`attribute_group_id`
							AND agd.`language_id` = pa.`language_id`
							AND agd.`store_id` = pa.`store_id`
				
						LEFT JOIN " . DB_PREFIX . "attribute_to_store a2s
							ON a2s.`attribute_id` = pa.`attribute_id`
							AND a2s.`store_id` = pa.`store_id`
				
						WHERE pa.product_id = p2s.product_id
							AND pa.`language_id` = pd.`language_id`
							AND pa.`store_id` = p2s.`store_id`
				
						GROUP BY pa.`attribute_group_id`
					) t
				) AS attributes,

				(
					SELECT JSON_OBJECTAGG(
						po.product_option_id, JSON_OBJECT(

							'product_option_id', 		po.`product_option_id`,
							'option_id', 						po.`option_id`,
							'value', 								po.`value`,
							'required', 						po.`required`,
							'type', 								o.`type`,
							'sort_order', 					(SELECT o2s.`sort_order` FROM " . DB_PREFIX . "option_to_store o2s WHERE o2s.`option_id` = po.`option_id` AND o2s.`store_id` = po.`store_id` LIMIT 1),
							'name', 								od.`name`,
						
							'product_option_value', (
								SELECT JSON_ARRAYAGG(
									JSON_OBJECT(
										'product_option_value_id',	pov.`product_option_value_id`,
										'product_option_id',				pov.`product_option_id`,
										'option_id',								pov.`option_id`,
										'option_value_id',					pov.`option_value_id`,
										'quantity',									pov.`quantity`,
										'subtract',									pov.`subtract`,
										'price',										pov.`price`,
										'price_prefix',							pov.`price_prefix`,
										'points',										pov.`points`,
										'points_prefix',						pov.`points_prefix`,
										'weight',										pov.`weight`,
										'weight_prefix',						pov.`weight_prefix`,
										'image',										(SELECT ov.`image` FROM " . DB_PREFIX . "option_value ov WHERE ov.`option_value_id` = pov.`option_value_id` AND ov.`store_id` = p2s.`store_id`),
										'sort_order',								(SELECT ov.`sort_order` FROM " . DB_PREFIX . "option_value ov WHERE ov.`option_value_id` = pov.`option_value_id` AND ov.`store_id` = p2s.`store_id`),
										'name',											(SELECT ovd.`name` FROM " . DB_PREFIX . "option_value_description ovd WHERE ovd.`option_value_id` = pov.`option_value_id` AND ovd.`language_id` = pd.`language_id` AND ovd.`store_id` = p2s.`store_id`)
									)
								)
								FROM " . DB_PREFIX . "product_option_value pov
								WHERE pov.product_id 				= po.product_id
									AND pov.product_option_id = po.product_option_id
									AND pov.store_id 					= po.store_id
							)
						)
					)
					FROM " . DB_PREFIX . "product_option po
					JOIN `" . DB_PREFIX . "option` o
						ON o.`option_id` 			= po.`option_id`
					JOIN " . DB_PREFIX . "option_to_store o2s
						ON 	o2s.`option_id` 	= po.`option_id`
						AND o2s.store_id 		= p2s.store_id
					JOIN " . DB_PREFIX . "option_description od
						ON 	od.`option_id` 		= po.`option_id`
						AND od.`language_id`	= pd.`language_id`
						AND od.`store_id` 		= p2s.`store_id`
					WHERE po.`product_id` 	= p.`product_id`
						AND po.`store_id` 		= p2s.`store_id`
				) AS options,

				(
					SELECT JSON_OBJECTAGG(
						pr.customer_group_id, JSON_OBJECT(
							'product_reward_id', 	pr.`product_reward_id`,
							'customer_group_id', 	pr.`customer_group_id`,
							'points',            	pr.`points`
						)
					)
						FROM " . DB_PREFIX . "product_reward pr
						WHERE pr.`product_id` = p2s.`product_id`
							AND pr.`store_id` 	= p2s.`store_id`
				) AS rewards, 

				(
					SELECT 
						ss.name 
					FROM " . DB_PREFIX . "stock_status ss 
					WHERE ss.`stock_status_id` = p.`stock_status_id` 
						AND ss.`language_id` = {$language_id}
				) AS stock_status, 

				(
					SELECT 
						wcd.`unit` 
					FROM " . DB_PREFIX . "weight_class_description wcd 
					WHERE p.`weight_class_id` = wcd.`weight_class_id` 
						AND wcd.`language_id` = {$language_id}
				) AS weight_class, 

				(
					SELECT 
						lcd.`unit` 
					FROM " . DB_PREFIX . "length_class_description lcd 
					WHERE p.`length_class_id` = lcd.`length_class_id` 
						AND lcd.`language_id` = {$language_id}
				) AS length_class

			FROM " . DB_PREFIX . "product_to_store p2s
			LEFT JOIN " . DB_PREFIX . "facet_sort pst
				ON 	pst.`product_id` = p2s.`product_id`
				AND pst.`store_id` 	 = p2s.`store_id`
			JOIN " . DB_PREFIX . "product p
				ON p.`product_id` = p2s.`product_id`
			JOIN " . DB_PREFIX . "product_description pd
				ON 	pd.`product_id`  	= p2s.`product_id`
				AND pd.`language_id` 	= {$language_id}
				AND pd.`store_id` 		= p2s.`store_id`
			WHERE p2s.`product_id` 	= '" . (int) $product_id . "'
				AND p2s.`store_id` 		= {$store_id}
				AND p2s.`status` 			= 1
			LIMIT 1
		";

		$product = $this->db->query($sql)->row;

		if (empty($product)) {
			return false;
		}

		/**
		 * Decode JSON aggregated data
		 * Faster then bouncing requests to get separate product data and easier to store cached data
		 */
		$product['images'] 							= json_decode($product['images'] 			?? '[]', true);
		$product['specials'] 						= json_decode($product['specials'] 		?? '[]', true);
		$product['discounts'] 					= json_decode($product['discounts'] 	?? '[]', true);
		$product['options'] 						= json_decode($product['options'] 		?? '[]', true);
		$product['attributes'] 					= json_decode($product['attributes'] 	?? '[]', true);
		$product['reward'] 							= json_decode($product['rewards'] 		?? '[]', true)[$customer_group_id] ?? null;
		// Get valid discount float prices and dates in YYYY-MM-DD format
		$product['discount'] 						= $this->getValidDiscount($product['discounts'], $customer_group_id)['price'] 		?? null;
		$product['special'] 						= $this->getValidDiscount($product['specials'],  $customer_group_id)['price'] 		?? null;
		$product['discount_date_end'] 	= $this->getValidDiscount($product['discounts'], $customer_group_id)['date_end'] ?? null;
		$product['special_date_end'] 		= $this->getValidDiscount($product['specials'],  $customer_group_id)['date_end'] ?? null;
		// Sort data
		usort(array: $product['images'], 		callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);
		usort(array: $product['options'], 		callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);
		usort(array: $product['attributes'], callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);
		usort(array: $product['specials'], 	callback: fn ($a, $b) =>  $a['priority'] 	<=> $b['priority']);
		array_multisort(
			$product['discounts'],
			array_column($product['discounts'], 'quantity'),  SORT_ASC,
			array_column($product['discounts'], 'priority'),  SORT_ASC,
			array_column($product['discounts'], 'price'),  SORT_ASC,
		);

		$this->cache->set($cacheName, $product);

		// Filter specials and discounts
		$now = date('Y-m-d H:i:s');
		$product['specials'] = array_filter($product['specials'], function ($var) use ($now) {
			return 
				strtotime($var['date_start']) <= strtotime($now)
				&& (
					strtotime($var['date_end']) >= strtotime($now) 
					|| $var['date_end'] === null
					|| str_contains($var['date_end'], '0000-00-00')
				)
			;
		});
		$product['discounts'] = array_filter($product['discounts'], function ($var) use ($now) {
			return 
				strtotime($var['date_start']) <= strtotime($now)
				&& (
					strtotime($var['date_end']) >= strtotime($now) 
					|| $var['date_end'] === null
					|| str_contains($var['date_end'], '0000-00-00')
				)
			;
		});
		return $product;
	}

	public function getProducts($data = []) : array {
		$data 			= array_filter($data); // Remove empty array entries 
		$store_id 	= (int) $this->config->get('config_store_id');
		$filters 		= [];
		$facets 		= [];
		$where 			= [];
		$order 			= '';
		$limit			= '';
		$products 	= [];
		$sortOrders = $this->getSortOrders(); // Allowed sort orders

		// Facet filters
		foreach ($data as $filterKey => $filterData) {
			if (str_starts_with($filterKey, 'filter_') && !empty($filterData)) {
				$filters[$filterKey] = $filterData;
			}
		}

		foreach ($filters as $filterKey => $filter) {
			
			// Sanitize and unique facet ids
			$filterIds = array_values(array_unique(array_map('intval', explode(',', $filter))));

			if ($filterKey === 'filter_category_id') {
				$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 1)";
			}
			if ($filterKey === 'filter_filter') {
				$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 2)";
			}
			if ($filterKey === 'filter_option') {
				$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 3)";
			}
			if ($filterKey === 'filter_attribute') {
				$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 4)";
			}
			if ($filterKey === 'filter_manufacturer_id') {
				$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 5)";
			}
			if ($filterKey === 'filter_tag') {
				$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 6)";
			}
			if ($filterKey === 'filter_supplier') {
				$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 7)";
			}
			if ($filterKey === 'filter_is_available') {
				$facets[] = "(facet_value_id = 1 AND facet_type = 8)";
			}
			if ($filterKey === 'filter_has_discount') {
				$facets[] = "(facet_value_id = 1 AND facet_type = 9)";
			}
			if ($filterKey === 'filter_is_featured') {
				$facets[] = "(facet_value_id = 1 AND facet_type = 10)";
			}
		}

		$where[] = "(" . implode(" OR ", $facets) . ")";
		$where[] = "store_id = {$store_id}";

		// Sort order
		$sortOrder = $data['sort'] ?? $this->config->get('config_default_product_sort') ?? 'sort_order';
		if (in_array($sortOrder, array_keys($sortOrders))) {
			$order = $sortOrders[$sortOrder];
		} else {
			$order = 'sort_order';
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if (!isset($data['start']) || $data['start'] < 0) {
				$data['start'] = 0;
			}

			if (!isset($data['limit']) || $data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$limit = " LIMIT " . (int) $data['start'] . "," . (int) $data['limit'];
		}

		// Main query. Get product ids, sort, limit
		$sql = "
			WITH facet_temp (`product_id`, `facet_type`, `facet_group_id`) AS (
				SELECT
					`product_id`, `facet_type`, `facet_group_id`
				FROM " . DB_PREFIX . "facet_index
				WHERE " . implode(" AND ", $where) . "
				ORDER BY NULL
			),

			group_count AS (
				SELECT COUNT(DISTINCT `facet_type`, `facet_group_id`) AS cnt
				FROM `facet_temp`
				ORDER BY NULL
			)

			SELECT 
				f.`product_id`
			FROM facet_temp f
			LEFT JOIN " . DB_PREFIX . "facet_sort pst
				ON  pst.`product_id` = f.`product_id`
				AND pst.`store_id` 	 = {$store_id}

			GROUP BY f.`product_id`
			HAVING COUNT(DISTINCT f.`facet_type`, f.`facet_group_id`) = (SELECT `cnt` FROM group_count)
			ORDER BY {$order}
			{$limit}
		";

		$productRows = $this->db->query($sql)->rows;
		foreach ($productRows as $row) {
			$products[] = $this->getProduct((int) $row['product_id']);
		}

		return $products;
	}
	}

	public function getProductDiscounts($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity > 1 AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity ASC, priority ASC, price ASC");

		return $query->rows;
	}

	public function getProductImages($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");

		return $query->rows;
	}

	public function getProductRelated($product_id) {
		$product_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_related pr LEFT JOIN " . DB_PREFIX . "product p ON (pr.related_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pr.product_id = '" . (int)$product_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		foreach ($query->rows as $result) {
			$product_data[$result['related_id']] = $this->getProduct($result['related_id']);
		}

		return $product_data;
	}

	public function getProductLayoutId($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}

	public function getCategories($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

		return $query->rows;
	}

	public function getTotalProducts($data = array()) {
		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
			} else {
				$sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
			}

			if (!empty($data['filter_filter'])) {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
			} else {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
			}
		} else {
			$sql .= " FROM " . DB_PREFIX . "product p";
		}

		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
			} else {
				$sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
			}

			if (!empty($data['filter_filter'])) {
				$implode = array();

				$filters = explode(',', $data['filter_filter']);

				foreach ($filters as $filter_id) {
					$implode[] = (int)$filter_id;
				}

				$sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
			}
		}

		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
				$sql .= " OR ";
			}

			if (!empty($data['filter_tag'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

				foreach ($words as $word) {
					$implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		if (!empty($data['filter_manufacturer_id'])) {
			$sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getProfile($product_id, $recurring_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "recurring r JOIN " . DB_PREFIX . "product_recurring pr ON (pr.recurring_id = r.recurring_id AND pr.product_id = '" . (int)$product_id . "') WHERE pr.recurring_id = '" . (int)$recurring_id . "' AND status = '1' AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'");

		return $query->row;
	}

	public function getProfiles($product_id) {
		$query = $this->db->query("SELECT rd.* FROM " . DB_PREFIX . "product_recurring pr JOIN " . DB_PREFIX . "recurring_description rd ON (rd.language_id = " . (int)$this->config->get('config_language_id') . " AND rd.recurring_id = pr.recurring_id) JOIN " . DB_PREFIX . "recurring r ON r.recurring_id = rd.recurring_id WHERE pr.product_id = " . (int)$product_id . " AND status = '1' AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' ORDER BY sort_order ASC");

		return $query->rows;
	}

	public function getTotalProductSpecials() {
		$query = $this->db->query("SELECT COUNT(DISTINCT ps.product_id) AS total FROM " . DB_PREFIX . "product_special ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))");

		if (isset($query->row['total'])) {
			return $query->row['total'];
		} else {
			return 0;
		}
	}

	public function checkProductCategory($product_id, $category_ids) {
		
		$implode = array();

		foreach ($category_ids as $category_id) {
			$implode[] = (int)$category_id;
		}
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "' AND category_id IN(" . implode(',', $implode) . ")");
  	    return $query->row;
	}
}
