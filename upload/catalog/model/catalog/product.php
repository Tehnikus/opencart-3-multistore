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
			'relevance'  			=> 'f.`relevance` DESC',
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
			'filter_tag'           		=> 6,
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
	private function getValidDiscount(array $rows, int $customerGroupId) : ?array {
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
			// $now = date('Y-m-d H:i:s');
			// Get valid discount float prices and dates in YYYY-MM-DD format
			$product['discount'] 						= $this->getValidDiscount($product['discounts'], $customer_group_id)['price'] 	 ?? null;
			$product['special'] 						= $this->getValidDiscount($product['specials'],  $customer_group_id)['price'] 	 ?? null;
			$product['discount_date_end'] 	= $this->getValidDiscount($product['discounts'], $customer_group_id)['date_end'] ?? null;
			$product['special_date_end'] 		= $this->getValidDiscount($product['specials'],  $customer_group_id)['date_end'] ?? null;

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
						pi.image_id, JSON_OBJECT(
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
		$product['footer'] 							= json_decode($product['footer'] 			 ?? '[]', true);
		$product['faq'] 								= json_decode($product['faq'] 			   ?? '[]', true);
		$product['how_to'] 							= json_decode($product['how_to'] 			 ?? '[]', true);
		$product['seo_keywords'] 				= json_decode($product['seo_keywords'] ?? '[]', true);
		$product['images'] 							= json_decode($product['images'] 			 ?? '[]', true);
		$product['specials'] 						= json_decode($product['specials'] 		 ?? '[]', true);
		$product['discounts'] 					= json_decode($product['discounts'] 	 ?? '[]', true);
		$product['options'] 						= json_decode($product['options'] 		 ?? '[]', true);
		$product['attributes'] 					= json_decode($product['attributes'] 	 ?? '[]', true);
		$product['reward'] 							= json_decode($product['rewards'] 		 ?? '[]', true)[$customer_group_id] ?? null;
		// Get valid discount float prices and dates in YYYY-MM-DD format
		$product['discount'] 						= $this->getValidDiscount($product['discounts'], $customer_group_id)['price'] 	 ?? null; // Single valid discount price
		$product['discount_date_end'] 	= $this->getValidDiscount($product['discounts'], $customer_group_id)['date_end'] ?? null; // Discount date end
		$product['special'] 						= $this->getValidDiscount($product['specials'],  $customer_group_id)['price'] 	 ?? null; // Single valid special price
		$product['special_date_end'] 		= $this->getValidDiscount($product['specials'],  $customer_group_id)['date_end'] ?? null; // Special date end
		// Sort data
		usort(array: $product['images'], 		 callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);
		usort(array: $product['options'], 	 callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);
		usort(array: $product['attributes'], callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);
		usort(array: $product['specials'], 	 callback: fn ($a, $b) =>  $a['priority'] 	<=> $b['priority']);
		array_multisort(
			$product['discounts'],
			array_column($product['discounts'], 'quantity'), SORT_ASC,
			array_column($product['discounts'], 'priority'), SORT_ASC,
			array_column($product['discounts'], 'price'),  	 SORT_ASC,
		);

		// Set cache
		$this->cache->set($cacheName, $product);

		// Filter specials and discounts - return arrays filtered by customer group id and now() date
		// These arrays are used to show multiple discounts at once
		$now = date('Y-m-d H:i:s');
		// Array of specials
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
		// Array of discounts
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

	/**
	 * Get products list
	 * Includes both facet filter and FULLTEXT search
	 * @param array $data The array of filters and search words
	 * @return array<array|bool>
	 */
	public function getProducts(array $data = [], $withTotal = false): array {
    $data     = array_filter($data, fn($v) => $v !== '' && $v !== null);
    $store_id = (int)$this->config->get('config_store_id');
    $facets   = [];
    $where    = [];
		$selectTotal = $withTotal ? ", COUNT(*) OVER() AS total_count" : "";

    foreach ($this->facetTypes as $key => $type) {
			if (!empty($data[$key])) {
				if (!in_array($type, [8, 9, 10])) {
					$ids      = array_values(array_unique(array_map('intval', explode(',', $data[$key]))));
					$facets[] = "(facet_value_id IN(" . implode(',', $ids) . ") AND facet_type = {$type})";
				} else {
					$facets[] = "(facet_value_id = 1 AND facet_type = {$type})";
				}
			}
    }

    $hasSearch = !empty($data['filter_name']);
    $hasFacets = !empty($facets);

    if (!$hasFacets && !$hasSearch) {
			return [];
    }

    [$cteList, $from, $facetJoin, $havingClause] = $this->buildQueryParts(
			$data, $store_id, $facets, $where, $hasSearch, $hasFacets
    );

    // Sort
    $sortKey = $data['sort'] ?? ($hasSearch ? 'relevance' : $this->config->get('config_default_product_sort')) ?? 'sort_order';

    if ($hasSearch && $sortKey === 'relevance') {
			$order = 'f.`relevance` DESC';
    } elseif (in_array($sortKey, array_keys($this->getSortOrders()))) {
			$order = $this->getSortOrders()[$sortKey];
    } else {
			$order = 'pst.`sort_order` ASC';
    }

    $groupBy = $hasSearch ? "GROUP BY f.`product_id`, f.`relevance`" : "GROUP BY f.`product_id`";

    $start = max(0, (int)($data['start'] ?? 0));
    $limit = max(1, (int)($data['limit'] ?? 20));

    $sql = "
			WITH " . implode(',', $cteList) . "
			SELECT 
				f.`product_id`
				{$selectTotal}
			{$from}
			{$facetJoin}
			LEFT JOIN `" . DB_PREFIX . "facet_sort` pst
				ON  pst.`product_id` = f.`product_id`
				AND pst.`store_id`   = {$store_id}
			{$groupBy}
			{$havingClause}
			ORDER BY {$order}
			LIMIT {$limit} OFFSET {$start}
    ";

    $rows     = $this->db->query($sql)->rows;
    $products = [];

    foreach ($rows as $row) {
			$products[] = $this->getProduct((int)$row['product_id']);
    }

    // Return total if withTotal flag is true
    if ($withTotal) {
			return [
				'products' => $products,
				'total'    => (int)($rows[0]['total_count'] ?? 0),
			];
    }

    return $products;
	}

	/**
	 * Count total products
	 * This method is left for separate calls, otherwise total products can be obtained in single request like this:
	 * $result   = $this->model->getProducts($data, true);
	 * $products = $result['products'];
	 * $total    = $result['total'];
	 * @param array $data
	 * @return int
	 */
	public function getTotalProducts(array $data = []): int {
    $result = $this->getProducts($data, true);
    return (int) $result['total'] ?? 0;
	}

	/**
	 * Returns total number of products matching the given filters and search query.
	 * Used for pagination.
	 * @return int
	 */
	public function getTotalProducts(array $data = []) : int {
		$data     = array_filter($data, fn($v) => $v !== '' && $v !== null);
		$store_id = (int)$this->config->get('config_store_id');
		$facets   = [];
		$where    = [];

		foreach ($this->facetTypes as $key => $type) {
			if (!empty($data[$key])) {
				if (!in_array($type, [8, 9, 10])) {
					$ids      = array_values(array_unique(array_map('intval', explode(',', $data[$key]))));
					$facets[] = "(facet_value_id IN(" . implode(',', $ids) . ") AND facet_type = {$type})";
				} else {
					$facets[] = "(facet_value_id = 1 AND facet_type = {$type})";
				}
			}
		}

		$hasSearch = !empty($data['filter_name']);
		$hasFacets = !empty($facets);

		if (!$hasFacets && !$hasSearch) {
			return 0;
		}

		[$cteList, $from, $facetJoin, $havingClause] = $this->buildQueryParts(
				$data, $store_id, $facets, $where, $hasSearch, $hasFacets
		);

		$sql = "
			WITH " . implode(',', $cteList) . "
			SELECT COUNT(*) AS total FROM (
				SELECT f.`product_id`
				{$from}
				{$facetJoin}
				LEFT JOIN `" . DB_PREFIX . "facet_sort` pst
					ON  pst.`product_id` = f.`product_id`
					AND pst.`store_id`   = {$store_id}
				" . ($hasSearch ? "GROUP BY f.`product_id`, f.`relevance`" : "GROUP BY f.`product_id`") . "
				{$havingClause}
			) AS counted
		";

		return (int)$this->db->query($sql)->row['total'] ?? 0;
	}

	/**
	 * Builds reusable CTE + FROM + JOIN + HAVING parts
	 * shared between getProducts() and getTotalProducts().
	 * @return array
	 */
	private function buildQueryParts(
		array  $data,
		int    $store_id,
		array  $facets,
		array  $where,
		bool   $hasSearch,
		bool   $hasFacets
	) : array {
    $cteList = [];

    if ($hasSearch) {
			$language_id = (int)$this->config->get('config_language_id');
			$boolQuery   = $this->buildSearchQuery($data['filter_name']);

			$matchExpr = "
				MATCH(`name`)         AGAINST('{$boolQuery}' IN NATURAL LANGUAGE MODE) * 10 +
				MATCH(`manufacturer`) AGAINST('{$boolQuery}' IN NATURAL LANGUAGE MODE) * 5  +
				MATCH(`category`)     AGAINST('{$boolQuery}' IN NATURAL LANGUAGE MODE) * 5  +
				MATCH(`extra`)        AGAINST('{$boolQuery}' IN NATURAL LANGUAGE MODE) * 1
			";

			$cteList[] = "
				search_results AS (
					SELECT `product_id`, ({$matchExpr}) AS relevance
					FROM `" . DB_PREFIX . "product_search_index`
					WHERE `language_id` = {$language_id}
						AND `store_id`    = {$store_id}
						AND ({$matchExpr}) > 0
				)
			";
    }

    if ($hasFacets) {
			$where[] = "store_id = {$store_id}";
			$where[] = "(" . implode(" OR ", $facets) . ")";

			$cteList[] = "
				facet_temp AS (
					SELECT `product_id`, `facet_type`, `facet_group_id`
					FROM `" . DB_PREFIX . "facet_index`
					WHERE " . implode(" AND ", $where) . "
				),
				group_count AS (
					SELECT COUNT(DISTINCT `facet_type`, `facet_group_id`) AS cnt
					FROM facet_temp
				)
			";
    }

    if ($hasSearch) {
			$from         = "FROM search_results f";
			$facetJoin    = $hasFacets ? "JOIN facet_temp ft ON ft.`product_id` = f.`product_id`" : "";
			$havingClause = $hasFacets ? "HAVING COUNT(DISTINCT ft.`facet_type`, ft.`facet_group_id`) = (SELECT cnt FROM group_count)" : "";
    } else {
			$from         = "FROM facet_temp f";
			$facetJoin    = "";
			$havingClause = "HAVING COUNT(DISTINCT f.`facet_type`, f.`facet_group_id`) = (SELECT cnt FROM group_count)";
		}

		// echo '<pre>' . htmlspecialchars(print_r([$cteList, $from, $facetJoin, $havingClause], true)) . '</pre>';

    return [$cteList, $from, $facetJoin, $havingClause];
	}

	/**
	 * Build BOOLEAN MODE expression from search string:
	 * "red sweater"  =>  "+red* +sweater*" (AND)
	 * "red sweater"  =>  "red* sweater*"   (OR)
	 */
	private function buildSearchQuery(string $query): string {
		$words = $this->tokenize($query);
		return implode(' ', array_map(
			fn(string $w) => $this->db->escape($w),
			$words
		));
	}

	/**
	 * Tokenize: clear reserved symbols for BOOLEAN MODE, lowercase, filter short words, only unique words
	 * Minimal word lenght must match ngram_token_size in my.cnf (default 2).
	 */
	private function tokenize(string $query, int $min_length = 2): array {
		// Remove BOOLEAN MODE reserved symbols
		$query = str_replace(['+', '-', '>', '<', '(', ')', '~', '*', '"', '@', '\\'], ' ', $query);

		$words = explode(' ', mb_strtolower(trim($query)));

		// Only unique words
		return array_values(
			array_unique(
				array_filter(
					$words,
					fn(string $w) => mb_strlen($w) >= $min_length
				)
			)
		);
	}
	
	public function getProductSpecials($data) : array {
		$data['filter_has_discount'] = 1;
		$productData = $this->getProducts($data);
		return $productData;
	}

	public function getLatestProducts($data) {
		$data['sort'] = 'date_added';
		$productData = $this->getProducts($data);
		return $productData;
	}

	public function getPopularProducts($data) {
		$data['sort'] = 'trends_by_date';
		$productData = $this->getProducts($data);
		return $productData;
	}

	public function getBestSellerProducts($data) : array {
		$data['sort'] = 'sales';
		$productData = $this->getProducts($data);
		return $productData;
	}

	public function getProductAttributes($product_id) : array {
		$product = $this->getProduct($product_id);
		$attributes = $product['attributes'] ?? [];
		return $attributes;
	}

	public function getProductOptions($product_id) : array {
		$product = $this->getProduct($product_id);
		$options = $product['options'] ?? [];
		return $options;
	}

	// Product bulk discounts
	public function getProductDiscounts($product_id) {
		$product = $this->getProduct($product_id);
		$discounts = $product['discounts'] ?? [];
		return $discounts;
	}

	// Additional product images
	public function getProductImages($product_id) : array {
		$product = $this->getProduct($product_id);
		$images = $product['images'] ?? [];
		return $images;
	}

	// Related products list in the bottom of product page
	public function getProductRelated($product_id) {
		$product_data = array();

		// Get product related ids
		$query = $this->db->query("
			SELECT 
				pr.related_id 
			FROM " . DB_PREFIX . "product_related pr 
			JOIN " . DB_PREFIX . "product p 
				ON p.product_id = pr.related_id
				AND p.status 		= 1
			JOIN " . DB_PREFIX . "product_to_store p2s 
				ON p2s.product_id = pr.product_id
				AND p2s.store_id  = pr.store_id
				AND p2s.status 		= 1
			WHERE pr.product_id = '" . (int) $product_id . "' 
				AND pr.store_id 	= '" . (int) $this->config->get('config_store_id') . "'
		");

		// Get products data
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

	// Only used in google_base.php
	public function getCategories($product_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_to_category 
			WHERE product_id = '" . (int) $product_id . "'
				AND store_id = '" . (int) $this->config->get('config_store_id') . "'
		");

		return $query->rows;
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

	public function getFilters($data = []) {
		$store_id    				 = (int) $this->config->get('config_store_id');
		$language_id 				 = (int) $this->config->get('config_language_id');
		$facetTypes 				 = $this->getFacetTypes();
		$conditions 				 = [];
		$base_facet_type     = null; // Page type, category = 1, manufacturer = 5, has_discount = 9, is_featured = 10
		$base_facet_value_id = null; // Page id if applicable, i.e. category_id. If not applicable then 0 
		
		// Set base facet to filter base product set on this page
		if ($this->request->get['route'] === 'product/category') {
			$path 							 = $this->request->get['category_id'] ?? $this->request->get['path'] ?? '';
			$category_id 				 = explode('_', (string) $path);
			$category_id 				 = end($category_id);
			$base_facet_type 		 = 1;
			$base_facet_value_id = (int) $category_id;
		}

		if ($this->request->get['route'] === 'product/manufacturer') {
			$base_facet_type 		 = 5;
			$base_facet_value_id = (int) $this->request->get['manufacturer_id'];
		}

		if ($this->request->get['route'] === 'product/special') {
			$base_facet_type     = 9;
			$base_facet_value_id = 0;
		}

		if ($this->request->get['route'] === 'product/featured') {
			$base_facet_type     = 10;
			$base_facet_value_id = 0;
		}

		if ($base_facet_type === null || $base_facet_value_id === null) {
			$this->log->write("model->product->getFilters(), Unknown request: \r\n" . htmlspecialchars(print_r($this->request->get, true)));
			return [];
		}

		// Base facet is intentionally included in selected_conditions
		// so that base page context is part of selected_groups for AND-between-groups logic
		$conditions[] = "(facet_type = {$base_facet_type} AND facet_value_id IN (" . $base_facet_value_id . "))";
		
		foreach ($data as $key => $ids) {
			if (!isset($facetTypes[$key])) continue;
			if ($facetTypes[$key] == $base_facet_type && $ids == $base_facet_value_id) continue;

			$type = (int) $facetTypes[$key];
			$ids = array_values(array_unique(array_map('intval', explode(',', $ids))));

			if (!$ids) continue;

			$conditions[] = "(facet_type = {$type} AND facet_value_id IN (" . implode(',', $ids) . "))";

		}

		$selected_conditions = $conditions ? implode(" OR ", $conditions) : "1";

		$sql = "
			/* Base facet list to be displayed on the page - all the facets from current page */
			WITH base_facet_list AS (
				SELECT
					i.facet_value_id,
					i.facet_type,
					i.facet_group_id,
					COUNT(DISTINCT(i.product_id)) AS base_count
				FROM " . DB_PREFIX . "facet_index i
				WHERE EXISTS(
					SELECT
						1
					FROM " . DB_PREFIX . "facet_index p
					WHERE p.product_id = i.product_id
						-- Current base page
						AND p.facet_type     = {$base_facet_type} 		-- Base page type, category = 1, manufacturer = 5, has_discount = 9, is_featured = 10
						AND p.facet_value_id = {$base_facet_value_id} -- Base facet entity id: category_id, manufacturer_id. If facet_type = has_discount, then 0
						AND store_id 				 = {$store_id} 						-- store id condition
				)
				AND store_id = {$store_id}
				GROUP BY i.facet_type, i.facet_group_id, i.facet_value_id
				ORDER BY NULL
			),
			
			/* 
				Current facets selected by user 
				Used to count
			*/
			selected_facets AS (
				SELECT
					`facet_type`, `facet_group_id`, facet_value_id
				FROM " . DB_PREFIX . "facet_index
				WHERE (
					/*
						Base facet AND selected facets joined with OR, example:
						(facet_value_id IN(1) AND facet_type = 1)    -- base facet: type = category (1), category_id = (1)
						OR (facet_value_id IN(2) AND facet_type = 5) -- selected facet: type = manufacturer (5), manufacturer_id = 2
						OR (facet_value_id IN(9,10) AND facet_type = 2) -- selected facet: type - filter (2), filter_id - 9,10
					*/
					{$selected_conditions}
				) 
				AND store_id = {$store_id} -- store id condition
				
				GROUP BY facet_type, facet_group_id, facet_value_id
				ORDER BY NULL
			),
			
			selected_groups AS (
				SELECT DISTINCT facet_type, facet_group_id
				FROM selected_facets
			),
			
			/* User selected facets */
			facet_temp (`product_id`, `facet_type`, `facet_group_id`) AS (
				SELECT
					`product_id`, `facet_type`, `facet_group_id`
				FROM " . DB_PREFIX . "facet_index
				WHERE (
					{$selected_conditions}
				) AND store_id = {$store_id}
				ORDER BY NULL
			),
			
			group_count AS (
				SELECT COUNT(DISTINCT `facet_type`, `facet_group_id`) AS cnt
				FROM `facet_temp`
				ORDER BY NULL
			),
			
			/* Current products to ignore already displayed products */
			current_products AS (
				SELECT 
					f.`product_id`
				FROM facet_temp f
				GROUP BY f.`product_id`
				HAVING COUNT(DISTINCT f.`facet_type`, f.`facet_group_id`) = (SELECT `cnt` FROM group_count)
				ORDER BY null
			),
			
			/* Base products */
			base_products AS (
				SELECT p.product_id
				FROM " . DB_PREFIX . "facet_index p
				WHERE p.facet_type = {$base_facet_type}
				AND p.facet_value_id = {$base_facet_value_id}
				AND p.store_id = {$store_id}
			),
			
			count_products AS (
				SELECT
					b.facet_type,
					b.facet_group_id,
					b.facet_value_id,
			
					COUNT(DISTINCT fi.product_id) AS current_count
			
				FROM base_facet_list b
			
				INNER JOIN " . DB_PREFIX . "facet_index fi 
				-- USE INDEX (PRIMARY)
					ON  fi.facet_value_id = b.facet_value_id
					AND fi.facet_type     = b.facet_type
					AND fi.facet_group_id = b.facet_group_id
					AND fi.store_id       = {$store_id}
			
				-- Get base products
				INNER JOIN base_products bp
					ON bp.product_id = fi.product_id
			
				-- Get current products (already shown)
				LEFT JOIN current_products c
					ON c.product_id = fi.product_id
			
				WHERE
					-- 1. AND between groups: the product matches all foreign groups
					NOT EXISTS (
						SELECT 1
						FROM selected_groups sg
						WHERE
							NOT (sg.facet_type = b.facet_type AND sg.facet_group_id = b.facet_group_id)
							AND NOT EXISTS (
								SELECT 1
								FROM " . DB_PREFIX . "facet_index fi2
			
								INNER JOIN selected_facets sf
									ON  sf.facet_type     = fi2.facet_type
									AND sf.facet_group_id = fi2.facet_group_id
									AND sf.facet_value_id = fi2.facet_value_id
								
								WHERE fi2.product_id     = fi.product_id
									AND fi2.store_id       = {$store_id}
									AND fi2.facet_type     = sg.facet_type
									AND fi2.facet_group_id = sg.facet_group_id
							)
					)
					-- 2. Чистый прирост: только для OR-групп исключаем уже показанные товары
					AND NOT (
						EXISTS (
							SELECT 1 FROM selected_groups sg2
							WHERE sg2.facet_type     = b.facet_type
							AND   sg2.facet_group_id = b.facet_group_id
						)
						AND fi.product_id IN (SELECT product_id FROM current_products)
					)
			
				GROUP BY b.facet_type, b.facet_group_id, b.facet_value_id
				ORDER BY null
			),
			
			available_facets AS (
				SELECT DISTINCT
					fi.facet_value_id,
					fi.facet_type,
					fi.facet_group_id
				FROM current_products cp
				-- Only facets that satisfy current products
				INNER JOIN " . DB_PREFIX . "facet_index fi
					ON  fi.product_id = cp.product_id
					AND fi.store_id   = {$store_id}
				-- Only current page facets
				INNER JOIN base_facet_list b
					ON  b.facet_value_id = fi.facet_value_id
					AND b.facet_type     = fi.facet_type
					AND b.facet_group_id = fi.facet_group_id
			)
			
			SELECT
				b.facet_value_id,
				b.facet_type,
				b.facet_group_id,
				b.base_count,
				COALESCE(c.current_count, 0) AS current_count,
				n.name AS facet_name,
				n.group_name AS facet_group_name,
				n.sort_order AS facet_sort_order,
				n.group_sort_order AS group_sort_order,
				CASE WHEN sf.facet_value_id IS NOT NULL THEN 1 ELSE 0 END AS facet_is_selected,
				CASE WHEN sg.facet_group_id IS NOT NULL THEN 1 ELSE 0 END AS group_is_selected,
				/* The facet is available if:
				1. There are some products after facet is applied (current_count > 0) OR
				2. Facet products intersect with current_products (products exist, but is is excluded as a duplicate of the OR-group - facet's parent group)
				*/
				CASE
					WHEN c.current_count > 0  THEN 1
					WHEN af.facet_value_id IS NOT NULL THEN 1
					ELSE 0
				END AS facet_is_available
			FROM base_facet_list b
			
			-- Facet names table, doesn't affect anything, just displays facet names
			LEFT JOIN " . DB_PREFIX . "facet_name n
				ON n.facet_type      = b.facet_type
				AND n.facet_group_id = b.facet_group_id
				AND n.facet_value_id = b.facet_value_id
				AND n.language_id    = {$language_id} -- language id condition
				AND n.store_id       = {$store_id}    -- store id condition
				
			-- Join selected facets to mark them as selected
			LEFT JOIN selected_facets sf
				ON  sf.facet_type     = b.facet_type
				AND sf.facet_group_id = b.facet_group_id
				AND sf.facet_value_id = b.facet_value_id
				
			-- Join selected groups to mark them as selected
			LEFT JOIN selected_groups sg
				ON  sg.facet_type     = b.facet_type
				AND sg.facet_group_id = b.facet_group_id
			
			-- Count products taking in account applied facets
			LEFT JOIN count_products c
				ON c.facet_value_id  = b.facet_value_id
				AND c.facet_type     = b.facet_type
				AND c.facet_group_id = b.facet_group_id
			
			-- Add flag if facet is available, despite all its product are already displayed
			LEFT JOIN available_facets af
				ON  af.facet_value_id = b.facet_value_id
				AND af.facet_type     = b.facet_type
				AND af.facet_group_id = b.facet_group_id
		";

		$query = $this->db->query($sql);
		return $query->rows;
	}

	
	// public function getProducts($data = []) : array {
	// 	$data 			= array_filter($data, fn($v) => $v !== '' && $v !== null); // Remove empty array entries, skipping zero, as zero is also a value
	// 	$store_id 	= (int) $this->config->get('config_store_id');
	// 	$filters 		= [];
	// 	$facets 		= [];
	// 	$where 			= [];
	// 	$order 			= '';
	// 	$limit			= '';
	// 	$products 	= [];
	// 	$sortOrders = $this->getSortOrders(); // Allowed sort orders

	// 	// Facet filters
	// 	foreach ($data as $filterKey => $filterData) {
	// 		if (str_starts_with($filterKey, 'filter_') && !empty($filterData)) {
	// 			$filters[$filterKey] = $filterData;
	// 		}
	// 	}

	// 	foreach ($filters as $filterKey => $filter) {
			
	// 		// Sanitize and unique facet ids
	// 		$filterIds = array_values(array_unique(array_map('intval', explode(',', $filter))));

	// 		if ($filterKey === 'filter_category_id') {
	// 			$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 1)";
	// 		}
	// 		if ($filterKey === 'filter_filter') {
	// 			$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 2)";
	// 		}
	// 		if ($filterKey === 'filter_option') {
	// 			$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 3)";
	// 		}
	// 		if ($filterKey === 'filter_attribute') {
	// 			$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 4)";
	// 		}
	// 		if ($filterKey === 'filter_manufacturer_id') {
	// 			$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 5)";
	// 		}
	// 		if ($filterKey === 'filter_tag') {
	// 			$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 6)";
	// 		}
	// 		if ($filterKey === 'filter_supplier') {
	// 			$facets[] = "(facet_value_id IN(" . implode(',', $filterIds) .") AND facet_type = 7)";
	// 		}
	// 		if ($filterKey === 'filter_is_available') {
	// 			$facets[] = "(facet_value_id = 1 AND facet_type = 8)";
	// 		}
	// 		if ($filterKey === 'filter_has_discount') {
	// 			$facets[] = "(facet_value_id = 1 AND facet_type = 9)";
	// 		}
	// 		if ($filterKey === 'filter_is_featured') {
	// 			$facets[] = "(facet_value_id = 1 AND facet_type = 10)";
	// 		}
	// 	}

	// 	// Fallback if $facets is empty, so SQL query is not broken
	// 	if (empty($facets)) {
	// 		return [];
	// 	}

	// 	$where[] = "(" . implode(" OR ", $facets) . ")";
	// 	$where[] = "store_id = {$store_id}";

	// 	// Sort order
	// 	$sortOrder = $data['sort'] ?? $this->config->get('config_default_product_sort') ?? 'sort_order';
	// 	if (in_array($sortOrder, array_keys($sortOrders))) {
	// 		$order = $sortOrders[$sortOrder];
	// 	} else {
	// 		$order = 'sort_order';
	// 	}

	// 	if (isset($data['start']) || isset($data['limit'])) {
	// 		if (!isset($data['start']) || $data['start'] < 0) {
	// 			$data['start'] = 0;
	// 		}

	// 		if (!isset($data['limit']) || $data['limit'] < 1) {
	// 			$data['limit'] = 20;
	// 		}

	// 		$limit = " LIMIT " . (int) $data['limit'] . " OFFSET " . (int) $data['start'];
	// 	}

	// 	// Main query. Get product ids, sort, limit
	// 	$sql = "
	// 		WITH facet_temp (`product_id`, `facet_type`, `facet_group_id`) AS (
	// 			SELECT
	// 				`product_id`, `facet_type`, `facet_group_id`
	// 			FROM " . DB_PREFIX . "facet_index
	// 			WHERE " . implode(" AND ", $where) . "
	// 			ORDER BY NULL
	// 		),

	// 		group_count AS (
	// 			SELECT COUNT(DISTINCT `facet_type`, `facet_group_id`) AS cnt
	// 			FROM `facet_temp`
	// 			ORDER BY NULL
	// 		)

	// 		SELECT 
	// 			f.`product_id`
	// 		FROM facet_temp f
	// 		LEFT JOIN " . DB_PREFIX . "facet_sort pst
	// 			ON  pst.`product_id` = f.`product_id`
	// 			AND pst.`store_id` 	 = {$store_id}

	// 		GROUP BY f.`product_id`
	// 		HAVING COUNT(DISTINCT f.`facet_type`, f.`facet_group_id`) = (SELECT `cnt` FROM group_count)
	// 		ORDER BY {$order}
	// 		{$limit}
	// 	";

	// 	$productRows = $this->db->query($sql)->rows;
	// 	foreach ($productRows as $row) {
	// 		$products[] = $this->getProduct((int) $row['product_id']);
	// 	}

	// 	return $products;
	// }

		/**
	 * Get all facets with names and product count
	 * This method counts products that will be displayed if facet is applied, like [filter [+3]] or [filter [-2]]
	 * Use same $data array as getProducts() method
	 * @param mixed $data
	 * @return array of filter type, group, values, names and product count
	 */
	// public function getFilters2($data = []) {
	// 	$store_id    				 = (int) $this->config->get('config_store_id');
	// 	$language_id 				 = (int) $this->config->get('config_language_id');
	// 	$facetTypes 				 = $this->getFacetTypes();
	// 	$conditions 				 = [];
	// 	$base_facet_type     = null; // Page type, category = 1, manufacturer = 5, has_discount = 9, is_featured = 10
	// 	$base_facet_value_id = null; // Page id if applicable, i.e. category_id. If not applicable then 0 
		
	// 	// Set base facet to filter base product set on this page
	// 	if ($this->request->get['route'] === 'product/category') {
	// 		$path 							 = $this->request->get['category_id'] ?? $this->request->get['path'] ?? '';
	// 		$category_id 				 = explode('_', (string) $path);
	// 		$category_id 				 = end($category_id);
	// 		$base_facet_type 		 = 1;
	// 		$base_facet_value_id = (int) $category_id;
	// 	}

	// 	if ($this->request->get['route'] === 'product/manufacturer') {
	// 		$base_facet_type 		 = 5;
	// 		$base_facet_value_id = (int) $this->request->get['manufacturer_id'];
	// 	}

	// 	if ($this->request->get['route'] === 'product/special') {
	// 		$base_facet_type     = 9;
	// 		$base_facet_value_id = 0;
	// 	}

	// 	if ($this->request->get['route'] === 'product/featured') {
	// 		$base_facet_type     = 10;
	// 		$base_facet_value_id = 0;
	// 	}

	// 	if ($base_facet_type === null || $base_facet_value_id === null) {
	// 		$this->log->write("model->product->getFilters(), Unknown request: \r\n" . htmlspecialchars(print_r($this->request->get, true)));
	// 		return [];
	// 	}

	// 	// Base facet is intentionally included in selected_conditions
	// 	// so that base page context is part of selected_groups for AND-between-groups logic
	// 	$conditions[] = "(facet_type = {$base_facet_type} AND facet_value_id IN (" . $base_facet_value_id . "))";
		
	// 	foreach ($data as $key => $ids) {
	// 		if (!isset($facetTypes[$key])) continue;
	// 		if ($facetTypes[$key] == $base_facet_type && $ids == $base_facet_value_id) continue;

	// 		$type = (int) $facetTypes[$key];
	// 		$ids = array_values(array_unique(array_map('intval', explode(',', $ids))));

	// 		if (!$ids) continue;

	// 		$conditions[] = "(facet_type = {$type} AND facet_value_id IN (" . implode(',', $ids) . "))";

	// 	}

	// 	$selected_conditions = $conditions ? implode(" OR ", $conditions) : "1";

	// 	$sql = "
			
	// 		-- Base facet list for current page
	// 		WITH base_facet_list AS (
	// 			SELECT
	// 				i.facet_value_id,
	// 				i.facet_type,
	// 				i.facet_group_id,
	// 				COUNT(DISTINCT(i.product_id)) AS base_count
	// 			FROM " . DB_PREFIX . "facet_index i
	// 			WHERE EXISTS(
	// 				SELECT
	// 					1
	// 				FROM " . DB_PREFIX . "facet_index p
	// 				WHERE p.product_id = i.product_id
	// 					-- Current base page
	// 					AND p.facet_type     = {$base_facet_type} -- Base page type, category = 1, manufacturer = 5, has_discount = 9, is_featured = 10
	// 					AND p.facet_value_id = {$base_facet_value_id} -- Base facet entity id: category_id, manufacturer_id. If facet_type = has_discount, then 0
	// 					AND store_id         = {$store_id} -- store id condition
	// 			)
	// 			AND store_id = {$store_id}
	// 			GROUP BY i.facet_type, i.facet_group_id, i.facet_value_id
	// 			ORDER BY NULL
	// 		),
			
	// 		-- Current facets selected by user
	// 		selected_facets AS (
	// 			SELECT
	// 				`facet_type`, `facet_group_id`, facet_value_id
	// 			FROM " . DB_PREFIX . "facet_index
	// 			WHERE (
	// 				-- Base facet AND selected facets joined with OR, example:
	// 				-- (facet_value_id IN(1) AND facet_type = 1)    -- base facet: type = category (1), category_id = (1)
	// 				-- OR (facet_value_id IN(2) AND facet_type = 5) -- selected facet: type = manufacturer (5), manufacturer_id = 2
	// 				-- OR (facet_value_id IN(9) AND facet_type = 2) -- selected facet: type - filter (2), filter_id - 9,10
	// 				{$selected_conditions}
	// 			) 
	// 			AND store_id = {$store_id} -- store id condition
				
	// 			GROUP BY facet_type, facet_group_id, facet_value_id
	// 			ORDER BY NULL
	// 		),
			
	// 		selected_groups AS (
	// 			SELECT DISTINCT facet_type, facet_group_id
	// 			FROM selected_facets
	// 		),
			
	// 		count_products AS (
	// 			SELECT
	// 				b.facet_type,
	// 				b.facet_group_id,
	// 				b.facet_value_id,
	// 				COUNT(DISTINCT fi.product_id) AS current_count
	// 			FROM base_facet_list b
			
	// 			-- Get product_id list for each facet from base facets list
	// 			INNER JOIN " . DB_PREFIX . "facet_index fi USE INDEX (facetCandidates)
	// 				ON  fi.facet_value_id = b.facet_value_id
	// 				AND fi.facet_type     = b.facet_type
	// 				AND fi.facet_group_id = b.facet_group_id
	// 				AND fi.store_id       = {$store_id}
			
	// 			-- Every product MUST exist in base product list
	// 			INNER JOIN " . DB_PREFIX . "facet_index base_page
	// 				ON  base_page.product_id     = fi.product_id
	// 				AND base_page.facet_type     = {$base_facet_type}
	// 				AND base_page.facet_value_id = {$base_facet_value_id}
	// 				AND base_page.store_id       = {$store_id}
			
	// 			-- For each selected group (except the candidate's own group):
	// 			-- Product MUST have at least one selected facet from this group (AND between groups)
	// 			WHERE NOT EXISTS (
	// 				SELECT 1
	// 				FROM selected_groups sg
	// 				WHERE
	// 					-- Skip the candidate's group.
	// 					NOT (sg.facet_type = b.facet_type AND sg.facet_group_id = b.facet_group_id)
	// 					-- Check: Does the product have at least one selected facet from this foreign group?
	// 					-- If NO, the product is skippet.
	// 					AND NOT EXISTS (
	// 						SELECT 1
	// 						FROM " . DB_PREFIX . "facet_index fi2
	// 						INNER JOIN selected_facets sf
	// 							ON  sf.facet_type     = fi2.facet_type
	// 							AND sf.facet_group_id = fi2.facet_group_id
	// 							AND sf.facet_value_id = fi2.facet_value_id
	// 						WHERE fi2.product_id = fi.product_id
	// 							AND fi2.store_id   = {$store_id}
	// 							AND fi2.facet_type     = sg.facet_type
	// 							AND fi2.facet_group_id = sg.facet_group_id
	// 					)
	// 			)
			
	// 			GROUP BY b.facet_type, b.facet_group_id, b.facet_value_id
	// 			ORDER BY NULL
	// 		)
			
	// 		SELECT
	// 			b.facet_value_id,
	// 			b.facet_type,
	// 			b.facet_group_id,
	// 			b.base_count,
	// 			c.current_count,
	// 			n.name AS facet_name,
	// 			n.group_name AS facet_group_name,
	// 			n.sort_order AS facet_sort_order,
	// 			n.group_sort_order AS group_sort_order,
	// 			CASE WHEN sf.facet_value_id IS NOT NULL THEN 1 ELSE 0 END AS facet_is_selected,
	// 			CASE WHEN sg.facet_group_id IS NOT NULL THEN 1 ELSE 0 END AS group_is_selected
	// 		FROM base_facet_list b
			
	// 		-- Facet names table, doesn't affect anything, just displays facet names
	// 		LEFT JOIN " . DB_PREFIX . "facet_name n
	// 			ON n.facet_type      = b.facet_type
	// 			AND n.facet_group_id = b.facet_group_id
	// 			AND n.facet_value_id = b.facet_value_id
	// 			AND n.language_id    = {$language_id} -- language id condition
	// 			AND n.store_id       = {$store_id}    -- store id condition
				
	// 		-- Join selected facets to mark them as selected
	// 		LEFT JOIN selected_facets sf
	// 			ON  sf.facet_type     = b.facet_type
	// 			AND sf.facet_group_id = b.facet_group_id
	// 			AND sf.facet_value_id = b.facet_value_id
				
	// 		-- Join selected groups to mark them as selected
	// 		LEFT JOIN selected_groups sg
	// 			ON  sg.facet_type     = b.facet_type
	// 			AND sg.facet_group_id = b.facet_group_id

	// 		-- Count products taking in account applied facets
	// 		LEFT JOIN count_products c
	// 			ON c.facet_value_id  = b.facet_value_id
	// 			AND c.facet_type     = b.facet_type
	// 			AND c.facet_group_id = b.facet_group_id
	// 	";

	// 	$query = $this->db->query($sql);
	// 	return $query->rows;
	// }

	// Some strange check that product is associated to category in case if SEO URLs is turned off?
	// public function checkProductCategory($product_id, $category_ids) {
		
	// 	$implode = array();

	// 	foreach ($category_ids as $category_id) {
	// 		$implode[] = (int)$category_id;
	// 	}
		
	// 	$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "' AND category_id IN(" . implode(',', $implode) . ")");
  // 	    return $query->row;
	// }
}
