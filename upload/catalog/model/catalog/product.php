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
		 * Description:
		 * $facet_id_in_db => [
		 * 	'facetType' => 'request param, e.g. category_id', 
		 * 	'isBool'		=> 'only facet presence is considered, any facet value will match filter, e.g. DB entry top_rated=123 is matching filter top_rated=1', 
		 * 	'group' 		=> 'separate group name to join different facets in groups, like flags or other',
		 *  'sort'      => 'default sort order for this facet',
		 * 	'route' 		=> 'route to controller, e.g. product/category', 
		 * ],
		 */
		$this->facetTypes = [
			1   => ['facetType' => 'category_id',			'isBool' => false, 'group' => false,  	'sort' => 'sort_order', 		'route' => 'product/category'],
			2   => ['facetType' => 'manufacturer_id',	'isBool' => false, 'group' => false,  	'sort' => 'sort_order', 		'route' => 'product/manufacturer'],
			3   => ['facetType' => 'option',					'isBool' => false, 'group' => false,  	'sort' => 'sort_order', 		'route' => false],
			4   => ['facetType' => 'attribute',				'isBool' => false, 'group' => false,  	'sort' => 'sort_order', 		'route' => false],
			5   => ['facetType' => 'filter',					'isBool' => false, 'group' => false,  	'sort' => 'sort_order', 		'route' => false],
			6   => ['facetType' => 'tag',							'isBool' => false, 'group' => false,  	'sort' => 'sort_order', 		'route' => false],
			7   => ['facetType' => 'supplier_id',			'isBool' => false, 'group' => false,  	'sort' => 'sort_order', 		'route' => 'product/supplier'],
			8   => ['facetType' => 'is_available',		'isBool' => true,  'group' => 'flags',  'sort' => 'sort_order', 		'route' => false],
			9   => ['facetType' => 'has_discount',		'isBool' => true,  'group' => 'flags',  'sort' => 'discounts', 			'route' => 'product/special'],
			10  => ['facetType' => 'is_featured',			'isBool' => true,  'group' => 'flags',  'sort' => 'sort_order', 		'route' => 'product/featured'],
      11  => ['facetType' => 'latest',					'isBool' => true,  'group' => 'flags',  'sort' => 'date_added', 		'route' => 'product/latest'],
			12  => ['facetType' => 'bestseller',			'isBool' => true,  'group' => 'flags',  'sort' => 'sales', 					'route' => 'product/bestseller'],
      13  => ['facetType' => 'top_rated',				'isBool' => true,  'group' => 'flags',  'sort' => 'rating', 				'route' => 'product/top_rated'],
      14  => ['facetType' => 'popular',					'isBool' => true,  'group' => 'flags',  'sort' => 'trends_by_date', 'route' => 'product/popular'],
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

	/**
	 * Prebuild array of params for getProducts() and getFilters() to remove repeating code
	 * Used in: 
	 * controller/category
	 * controller/special
	 * controller/latest
	 * controller/bestseller
	 * controller/popular
	 * controller/featured
	 * controller/manufacturer
	 * 
	 * @param mixed $request
	 * @return array
	 */
	public function prepareGetProductsRequest(array $request) : array {
		$result = [];

		// Category from path
		if (isset($request['path'])) {
			$parts = explode('_', (string) $request['path']);
			$result['category_id'] = (int) end($parts) ?: null;
		}

		// Facet filters from request
		foreach ($this->facetTypes as $facet) {
			if (isset($request[$facet['facetType']])) {
				$result[$facet['facetType']] = $request[$facet['facetType']];
			}
		}

		// Search query — filter_name is canonical, search is alias
		$searchQuery = $request['filter_name'] ?? $request['search'] ?? '';
		if ($searchQuery !== '') {
			$result['filter_name'] = (string) $searchQuery;
		}

		// Pagination
		if (isset($request['start'])) {
			$result['start'] = (int) $request['start'];
		}

		$result['limit'] = (int) ($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit') ?? 20);
		$result['page']  = (int) ($request['page'] ?? 1);

		// Sort order
		if (isset($request['sort']) && isset($this->sortOrders[strtolower((string) $request['sort'])])) {
			$result['sort'] = strtolower((string) $request['sort']);
		}
		if (isset($request['order']) && in_array(strtoupper((string) $request['order']), ['ASC', 'DESC'])) {
			$result['order'] = strtoupper((string) $request['order']);
		}

		// Route-specific params are built dynamically from facetTypes
		if (isset($request['route'])) {
			foreach ($this->facetTypes as $facetType => $facet) {

				if ($facet['route'] !== $request['route']) continue;

				// Boolean facets-routes is_featured, has_discount, latest, bestseller, top_rated
				// These do not need precise facet_value_id, just it's presence
				// Thus we can set facet_value_id=1 if $request['route'] matches $facet['route'] and $facet['isBool'] = true
				// And if facet_value_id is not set explicitly
				if ($facet['isBool'] && !isset($result[$facet['facetType']])) {
					$result[$facet['facetType']] = 1;
				}

				// Default route sort if not set explicitly
				if (!isset($result['sort']) && $facet['sort']) {
					$result['sort'] = $facet['sort'];
				}

				break; 
			}
		}

		return array_filter($result, fn($v) => $v !== '' && $v !== null);
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
			$end 	= (!$r['date_end'] || str_starts_with($r['date_end'],	'0000-00-00')) ? null : strtotime($r['date_end']);
			
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
		$cacheSetting 			= (bool) $this->config->get('cache_products');
		$theme        	 		= $this->config->get('config_theme');
		$imgManufacturerWidth  	= (int) ($this->config->get("theme_{$theme}_image_manufacturer_width") ?? 600);
		$imgManufacturerHeight 	= (int) ($this->config->get("theme_{$theme}_image_manufacturer_height") ?? 200);
		$imgCategoryWidth  			= (int) ($this->config->get("theme_{$theme}_image_category_width") ?? 500);
		$imgCategoryHeight 			= (int) ($this->config->get("theme_{$theme}_image_category_height") ?? 500);
		$imgMainWidth  					= (int) ($this->config->get("theme_{$theme}_image_product_main_width") ?? 2000);
		$imgMainHeight 					= (int) ($this->config->get("theme_{$theme}_image_product_main_height") ?? 2000);
		$imgMiniatureWidth  		= (int) ($this->config->get("theme_{$theme}_image_product_width") ?? 600);
		$imgMiniatureHeight 		= (int) ($this->config->get("theme_{$theme}_image_product_height") ?? 600);
		
		// Cache
		if ($cacheSetting) {
			$cacheName 	= "product.store_{$store_id}.language_{$language_id}." . (floor($product_id / 100)) . "00.product_{$product_id}";
			$product 		= $this->cache->get($cacheName);
			
			if ($product) {
				/**
				 * Filter specials and discounts from cached data so cache shoul not invalidate if (current time > date end)
				 */ 
				// $now = date('Y-m-d H:i:s');
				// Get valid discount float prices and dates in YYYY-MM-DD format
				$product['discount'] 				= $this->getValidDiscount($product['discounts'], $customer_group_id)['price'] 	 ?? null;
				$product['special'] 				= $this->getValidDiscount($product['specials'],  $customer_group_id)['price'] 	 ?? null;
				$product['discountDateEnd'] = $this->getValidDiscount($product['discounts'], $customer_group_id)['date_end'] ?? null;
				$product['specialDateEnd'] 	= $this->getValidDiscount($product['specials'],  $customer_group_id)['date_end'] ?? null;
				$this->buildProductDynamicData($product);
	
				return $product;
			}
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
				p.`date_added`,
				
				p2s.`sort_order`,
				p2s.`parent_id`,
				p2s.`status`,
				p2s.`date_modified`,
				p2s.`is_available` AS isAvailable,
				p2s.`is_featured`,

				COALESCE(p2s.`image`, p.`image`) AS image,
				COALESCE(NULLIF(p2s.`price`, 0), p.`price`) AS price,

				pd.`name`,
				pd.`h1`,
				pd.`meta_title`,
				pd.`meta_description`,
				pd.`meta_keyword`,
				pd.`description`,
				pd.`seo_keywords` 	 AS `seoKeywords`,
				pd.`seo_description` AS `seoDescription`,
				pd.`faq` 						 AS `faqJson`,
				pd.`how_to` 				 AS `howToJson`,
				pd.`footer` 				 AS `seoFooter`,
				pd.`date_modified` 	 AS descriptionDateModified,

				pst.`views`,
				pst.`orders`,
				pst.`returns`,
				pst.`review_count` AS reviews,
				pst.`rating_avg` AS rating,

				(SELECT m2s.image FROM " . DB_PREFIX . "manufacturer_to_store m2s WHERE m2s.manufacturer_id = p.manufacturer_id AND m2s.store_id = p2s.store_id LIMIT 1) AS manufacturerImage,
				(SELECT c2s.image FROM " . DB_PREFIX . "category_to_store c2s WHERE c2s.category_id = p2s.parent_id AND c2s.store_id = p2s.store_id LIMIT 1) AS parentImage,

				(
					SELECT JSON_ARRAYAGG(
						JSON_OBJECT(
							'facetName', 	 	fn.`name`,
							'groupName', 	 	fn.`group_name`,
							'sortOrder', 		fn.`sort_order`,
							'facetTypeId',  fi.`facet_type`,
							'facetGroupId', fi.`facet_group_id`,
							'facetValueId', fi.`facet_value_id`
						)
					)
					FROM " . DB_PREFIX . "facet_index fi
					LEFT JOIN " . DB_PREFIX . "facet_name fn
						ON  fn.`facet_value_id` = fi.`facet_value_id`
						AND fn.`facet_group_id` = fi.`facet_group_id`
						AND fn.`facet_type` 		= fi.`facet_type`
						AND fn.`language_id` 		= pd.`language_id`
						AND fn.`store_id` 			= p2s.`store_id`
					WHERE fi.`product_id` = p2s.`product_id`
						AND fi.`store_id` 	= p2s.`store_id`
				) AS facetsData,

				(
					SELECT JSON_OBJECTAGG(
						pi.image_id, JSON_OBJECT(
							'image_id',			pi.`image_id`,
							'image', 				pi.`image`,
							'sort_order', 	pi.`sort_order`,
							'description', 	pid.`description`
						)
					)
					FROM " . DB_PREFIX . "product_image pi
					LEFT JOIN " . DB_PREFIX . "product_image_description pid
						ON  pid.`image_id` 		= pi.`image_id`
						AND pid.`language_id` = pd.`language_id`
						AND pid.`store_id` 		= p2s.`store_id`
					WHERE pi.`product_id` 	= p2s.`product_id`
						AND pi.`store_id`   	= p2s.`store_id`
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
					FORCE INDEX (getProduct)
					 WHERE pd.`product_id` = p2s.`product_id`
					 AND pd.`store_id` 		 = p2s.`store_id`
				) AS discounts,


				(
					SELECT JSON_ARRAYAGG(
						JSON_OBJECT(
							'review_text', 		r.`text`,
							'review_date', 		r.`date_added`,
							'review_rating', 	r.`rating`,
							'author',					r.`author`	
						)
					)
					FROM " . DB_PREFIX . "review r
					WHERE r.`product_id` 	= p2s.`product_id`
						AND r.`store_id`	 	= p2s.`store_id`
						AND r.`language_id` = pd.`language_id`
						AND r.`status` 			= 1
					ORDER BY r.`date_modified` DESC
					LIMIT 10
				) AS lastReviews,

				(
					SELECT JSON_OBJECTAGG(
						po.`product_option_id`, JSON_OBJECT(

							'product_option_id', 		po.`product_option_id`,
							'option_id', 						po.`option_id`,
							'value', 								po.`value`,
							'required', 						po.`required`,
							'type', 								o.`type`,
							'sort_order', 					o2s.`sort_order`,
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
										'image',										(SELECT ov.`image` FROM " . DB_PREFIX . "option_value ov WHERE ov.`option_value_id` = pov.`option_value_id` AND ov.`store_id` = p2s.`store_id` LIMIT 1),
										'sort_order',								(SELECT ov.`sort_order` FROM " . DB_PREFIX . "option_value ov WHERE ov.`option_value_id` = pov.`option_value_id` AND ov.`store_id` = p2s.`store_id` LIMIT 1),
										'name',											(SELECT ovd.`name` FROM " . DB_PREFIX . "option_value_description ovd WHERE ovd.`option_value_id` = pov.`option_value_id` AND ovd.`language_id` = pd.`language_id` AND ovd.`store_id` = p2s.`store_id` LIMIT 1)
									)
								)
								FROM " . DB_PREFIX . "product_option_value pov
								FORCE INDEX (getProduct)
								WHERE pov.`product_id` 				= po.`product_id`
									AND pov.`product_option_id` = po.`product_option_id`
									AND pov.`store_id` 					= po.`store_id`
							)
						)
					)
					FROM " . DB_PREFIX . "product_option po
					LEFT JOIN `" . DB_PREFIX . "option` o 
					FORCE INDEX (PRIMARY)
						ON o.`option_id` 			= po.`option_id`
					LEFT JOIN " . DB_PREFIX . "option_to_store o2s 
					FORCE INDEX (PRIMARY)
						ON 	o2s.`option_id` 	= po.`option_id`
						AND o2s.`store_id` 		= p2s.`store_id`
					LEFT JOIN " . DB_PREFIX . "option_description od 
					FORCE INDEX (PRIMARY)
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
						FORCE INDEX (getProduct)
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
			FORCE INDEX (PRIMARY)
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
		$this->load->model('tool/image');
		$product['seoFooter'] 					= json_decode($product['seoFooter']		 		 ?? '[]', true);
		$product['faq'] 								= json_decode($product['faqJson'] 		 		 ?? '[]', true);
		$product['howTo'] 							= json_decode($product['howToJson']  	 		 ?? '[]', true);
		$product['seoKeywords'] 				= json_decode($product['seoKeywords']  		 ?? '[]', true);
		$product['images'] 							= json_decode($product['images'] 			 		 ?? '[]', true);
		$product['specials'] 						= json_decode($product['specials'] 		 		 ?? '[]', true);
		$product['discounts'] 					= json_decode($product['discounts'] 	 		 ?? '[]', true);
		$product['options'] 						= json_decode($product['options'] 		 		 ?? '[]', true);
		$product['attributes'] 					= json_decode($product['attributes'] 	 		 ?? '[]', true);
		$product['lastReviews'] 				= json_decode($product['lastReviews']  		 ?? '[]', true);
		$product['facetsData'] 					= json_decode($product['facetsData'] 			 ?? '[]', true);
		$product['reward'] 							= json_decode($product['rewards'] 		 		 ?? '[]', true)[$customer_group_id] ?? null;
		// Get valid discount float prices and dates in YYYY-MM-DD format
		$product['discount'] 						= $this->getValidDiscount($product['discounts'], $customer_group_id)['price'] 	 ?? null; // Single valid discount price
		$product['discountDateEnd'] 		= $this->getValidDiscount($product['discounts'], $customer_group_id)['date_end'] ?? null; // Discount date end
		$product['special'] 						= $this->getValidDiscount($product['specials'],  $customer_group_id)['price'] 	 ?? null; // Single valid special price
		$product['specialDateEnd'] 			= $this->getValidDiscount($product['specials'],  $customer_group_id)['date_end'] ?? null; // Special date end
		// Decode descriptions
		$product['description']			= html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8');
		$product['seoDescription']	= html_entity_decode($product['seoDescription'], ENT_QUOTES, 'UTF-8');
		foreach ($product['seoFooter'] ?? [] as $key => $tab) {
			$product['seoFooter'][$key]['description'] = html_entity_decode($tab['description'], ENT_QUOTES, 'UTF-8');
		}
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

		// Group product facets and add SEO links
		$facetGroups = [
			'facets' 			=> [],
			'tags' 				=> [],
			'categories' 	=> [],
		];
		foreach ($product['facetsData'] ?? [] as $facet) {
			$facet['facetType'] = $this->facetTypes[$facet['facetTypeId']]['facetType'];
			$facet['url'] 			= $this->url->link('product/category', "path={$product['parent_id']}&{$facet['facetType']}={$facet['facetValueId']}", true);
			// Boolean facets have no name in DB
			if (in_array($facet['facetType'], array_column(array_filter($this->facetTypes, fn($a) => $a['isBool']), 'facetType'))) {
				$facet['facetName'] = $this->language->get('facet_' . $facet['facetType']);
			}

			if ($facet['facetType'] === 'manufacturer_id') {
				$product['manufacturerData'] = [
					'name'			=> $facet['facetName'],
					'image' 		=> $this->model_tool_image->resize($product['manufacturerImage'] ?? 'no_image.webp', $imgManufacturerWidth, $imgManufacturerHeight),
					'url' 			=> $this->url->link('catalog/manufacturer', "manufacturer_id={$facet['facetValueId']}", true),
					'facetLink' => $this->url->link('product/category', "path={$product['parent_id']}&manufacturer_id={$facet['facetValueId']}", true),
				];
				continue;
			}

			if ($facet['facetType'] === 'category_id' && $facet['facetValueId'] == $product['parent_id']) {
				$product['parentData'] = [
					'name'			=> $facet['facetName'],
					'image' 		=> $this->model_tool_image->resize($product['manufacturerImage'] ?? 'no_image.webp', $imgCategoryWidth, $imgCategoryHeight),
					'url' 			=> $this->url->link('catalog/manufacturer', "manufacturer_id={$facet['facetValueId']}", true),
				];
				continue;
			}

			if ($facet['facetType'] === 'category_id' && $facet['facetValueId'] !== $product['parent_id']) {
				$facetGroups['categories'] = $facet;
			}

			// Facets that have group are gruped by type and group
			if ($facet['facetGroupId'] && $facet['facetType'] !== 'category_id') {
				// Create group if not exists
				if (!isset($facetGroups['facets'][$facet['facetTypeId']][$facet['facetGroupId']])) {
					$facetGroups['facets'][$facet['facetTypeId']][$facet['facetGroupId']] = [
						'groupName' 		=> $facet['groupName'],
						'facetGroupId' 	=> $facet['facetGroupId'],
						'facets' 				=> [],
					];
				}
				// Add facet to group 
				$facetGroups['facets'][$facet['facetTypeId']][$facet['facetGroupId']]['facets'][] = $facet;
			} else {
				$facetGroups['tags'][$facet['facetTypeId']] = $facet;
			}
		}
		$product['facetsData'] = $facetGroups;

		// Resize images to store prepared image links
		$cover 				 = [];
		$productImages = [];

		// Add cover to the beginning of images array
		$cover['image'] 		  = $product['image'] ?? 'no_image.webp';
		$cover['description'] = $product['name'];
		
		array_unshift($product['images'], $cover);

		foreach ($product['images'] as $img) {
			$productImages['covers'][] = [
				'src' 				=> $this->model_tool_image->resize($img['image'], $imgMainWidth, $imgMainHeight),
				'description' => $img['description'],
				'width'				=> $imgMainWidth,
				'height'			=> $imgMainHeight,
			];

			$productImages['miniatures'][] = [
				'src' 				=> $this->model_tool_image->resize($img['image'], $imgMiniatureWidth, $imgMiniatureHeight),
				'description' => $img['description'],
				'width'				=> $imgMiniatureWidth,
				'height'			=> $imgMiniatureHeight,
			];
		}

		$product['images'] = $productImages;
		// End images

		// Trim short description
		$theme        = $this->config->get('config_theme');
		$descLength   = (int) ($this->config->get("theme_{$theme}_product_description_length") ?? 255);

		$shortDesciption = '';
		$description = strip_tags($product['description']);
		if (mb_strlen($description) > $descLength) {
			$description = explode('.', $description);
			$sentenceCount = 0;
			while (mb_strlen($shortDesciption) < $descLength) {
				$shortDesciption .= $description[$sentenceCount] . ". ";
				$sentenceCount ++;
			}
		} else {
			$shortDesciption = $description;
		}
		$product['shortDescription'] = $shortDesciption;
		// End trim short description

		// URLs
		$product['url'] 						= $this->url->link('product/product', "product_id={$product_id}", true);
		// End URLs

		// Breadcrumbs
		$product['breadcrumbs'] = [];
		$product['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home'),
    ];
		$product['breadcrumbs'][] = [
			'text' => $product['parentData']['name'],
			'href' => $this->url->link('product/category', 'path=' . $product['parent_id']),
    ];
		$product['breadcrumbs'][] = [
			'text' => $product['name'],
			'href' => $this->url->link('product/product', 'product_id=' . $product_id),
		];
		// End breadcrumbs

		// Rating
		$product['rating'] = ($this->config->get('config_review_status')) ? round((float) $product['rating'], 1) : false;
		// End rating

		// Set cache
		if ($cacheSetting) {
			$this->cache->set($cacheName, $product);
		}

		// Following data is dynamic, thus can not be cached
		// This involves currency, customer group and current date
		$this->buildProductDynamicData($product);

		return $product;
	}

	private function buildProductDynamicData(&$data) : void {
		// Prices
		$tax 			= $this->config->get('config_tax');
		$currency = $this->session->data['currency'];

		$priceFormat = $this->currency->format($this->tax->calculate($data['price'], $data['tax_class_id'], $tax), $currency);
		if (!is_null($data['special']) && (float) $data['special'] >= 0) {
			$priceSpecialFormat = $this->currency->format($this->tax->calculate($data['special'], $data['tax_class_id'], $tax), $currency);
			$priceTaxValue 			= (float) $data['special'];
		} else {
			$priceSpecialFormat = false;
			$priceTaxValue 			= (float) $data['price'];
		}
		$priceTaxFormat = ($this->config->get('config_tax')) ? $this->currency->format($priceTaxValue, $currency) : false;
		$data['priceFormat'] 	 			= $priceFormat;
		$data['priceTaxFormat'] 		= $priceTaxFormat;
		$data['priceSpecialFormat'] = $priceSpecialFormat;
		$data['priceTaxValue'] 			= $priceTaxValue;
		$data['currencyCode'] 			= $this->session->data['currency'];
		$data['customerGroupId']		= (int) $this->config->get('config_customer_group_id');
		$data['showPrice']					= $this->customer->isLogged() || !$this->config->get('config_customer_price');
		// End prices

		// Options
		$options = [];
		foreach ($data['options'] ?? [] as $optionGroup) {
			foreach ($optionGroup['product_option_value'] as $key => $optionValue) {
				$optionValue['price_value'] = $optionValue['price'];
				$optionValue['price']       = $this->currency->format(
					$this->tax->calculate($optionValue['price'], $data['tax_class_id'], $this->config->get('config_tax')),
					$this->session->data['currency']
				);
				$optionGroup['product_option_value'][$key] = $optionValue;
			}
			$options[$optionGroup['product_option_id']] = $optionGroup; 
		}
		$data['options'] = $options;

		// Filter specials and discounts - return arrays filtered by customer group id and now() date
		// These arrays are used to show multiple discounts at once
		$now = date('Y-m-d H:i:s');
		// Array of specials
		$data['specials'] = array_filter($data['specials'], function ($var) use ($now) {
			return 
				strtotime($var['date_start']) <= strtotime($now)
				&& (
					strtotime($var['date_end']) >= strtotime($now) 
					|| $var['date_end'] === null
					|| str_contains($var['date_end'], '0000-00-00')
				)
			;
		});

		foreach ($data['specials'] as $key => $special) {
			$data['specials'][$key]['priceFormat'] = $this->currency->format($this->tax->calculate($special['price'], $data['tax_class_id'], $tax), $currency);
		}

		// Array of discounts
		$data['discounts'] = array_filter($data['discounts'], function ($var) use ($now) {
			return 
				strtotime($var['date_start']) <= strtotime($now)
				&& (
					strtotime($var['date_end']) >= strtotime($now) 
					|| $var['date_end'] === null
					|| str_contains($var['date_end'], '0000-00-00')
				)
			;
		});

		foreach ($data['discounts'] as $key => $special) {
			$data['discounts'][$key]['priceFormat'] = $this->currency->format($this->tax->calculate($special['price'], $data['tax_class_id'], $tax), $currency);
		}

		$data['cacheDate'] = max(strtotime($data['descriptionDateModified']), strtotime($data['date_modified']));
	}

	/**
	 * Get products list
	 * Includes both facet filter and FULLTEXT search
	 * @param array $data The array of filters and search words
	 * @return array<array|bool>
	 */
	public function getProducts(array $data = [], $withTotal = false) {
    $data         = array_filter($data, fn($v) => $v !== '' && $v !== null);
    $store_id     = (int) $this->config->get('config_store_id');
		$facets 		  = $this->buildFacetExpression($data);
		$ctes 			  = $this->buildCteExpression($data);  // CTE expressions for fulltext search and/or facets
		$searchExpression = $this->buildMatchExpression($data);
		$hasSearch 	  = !empty($searchExpression);
    $hasFacets 	  = !empty($facets);
		$hasShowAll		= !empty($data['show_all']);
    $limit 				= max(1, (int) ($data['limit'] ?? 20));
		$offset 			= max(0, ((int) $data['page'] - 1) * (int) $data['limit']);

		// Safely return
		if (!$hasSearch && !$hasFacets && !$hasShowAll) {
			return $withTotal ? ['products' => [], 'total' => 0] : [];
		}

		// Columns to select
		$selectColumns = ['f.`product_id`'];
		if ($withTotal) {
			$selectColumns[] = "COUNT(*) 									OVER() AS `total_count`";
			$selectColumns[] = "MAX(pst.`current_price`) 	OVER() AS `price_max`";
			$selectColumns[] = "MIN(pst.`current_price`) 	OVER() AS `price_min`";
			$selectColumns[] = "SUM(pst.`review_count`)		OVER() AS `reviews`";
			$selectColumns[] = "AVG(pst.`rating_avg`) 		OVER() AS `rating`";
		}

		// Main query part depending on present search, facets or search + facets
		if ($hasSearch) {
			$from      = "search_results f";
			$join    	 = $hasFacets ? "JOIN facet_temp ft ON f.`product_id` = ft.`product_id`" : "";
			$groupBy 	 = "f.`product_id`, f.`relevance`";
			$having 	 = $hasFacets ? "HAVING COUNT(DISTINCT ft.`facet_type`, ft.`facet_group_id`) = (SELECT cnt FROM group_count)" : "";
    } else {
			$from      = "facet_temp f";
			$join    	 = "";
			$groupBy 	 = "f.`product_id`";
			$having 	 = "HAVING COUNT(DISTINCT f.`facet_type`, f.`facet_group_id`) = (SELECT cnt FROM group_count)";
		}

		// Next block is used ONLY if no facet expression present on pages that have no base facet by design, but only specific sort order e.g. bestsellers, latest, trending
		// Flag to show all products without facet params
		// Used only on pages that don't have initial facet by design: product/bestseller, product/latest, product/popular
		if (!$hasSearch && !$hasFacets && $hasShowAll) {
			$maxProducts = (int) ($data['max_products'] ?? 100);

			// Sort order for main CTE. Sort order is required because CTE does not support LIMIT without ORDER BY
			$cteSortKey = $data['sort'] 
				?? $this->config->get('config_default_product_sort') 
				?? 'sort_order';
			
			$cteOrder = in_array($cteSortKey, array_keys($this->getSortOrders()))
				? $this->getSortOrders()[$cteSortKey]
				: 'pst.`sort_order` ASC';

			// Reset CTEs - remove group_count because without facet expression there is no group count present
			$ctes = [];
			// Add limit to main CTE so avoid showing all products at once
			// Also works with pagination correctly limiting max products before pagination applied
			$ctes[] = "
				facet_temp AS (
					SELECT fi.`product_id`, fi.`facet_type`, fi.`facet_group_id`
					FROM `" . DB_PREFIX . "facet_index` fi
					JOIN `" . DB_PREFIX . "facet_sort` pst
						ON  pst.`product_id` = fi.`product_id`
						AND pst.`store_id`   = {$store_id}
					WHERE fi.`store_id` = {$store_id}
					ORDER BY {$cteOrder}
					LIMIT {$maxProducts} -- Limit products
				)
			";

			$from    = "facet_temp f";
			$join    = "";
			$groupBy = "f.`product_id`";
			$having  = "";
		}
		
		// Sort key
		// If $data['sort'] is set, rhen use it as $sortKey. If $data['sort'] is not set, then if has search, set default sort to relevance. 
		// If does not have search set to default config, otherwise to default 'sort_order'
		$sortKey = $data['sort'] ?? ($hasSearch ? 'relevance' : $this->config->get('config_default_product_sort')) ?? 'sort_order';
		// If sort order is in allowed sort orders list, then use it
		if (in_array($sortKey, array_keys($this->getSortOrders()))) {
			$order = $this->getSortOrders()[$sortKey];
			// Restrict 'relevance' for product list without search, because relevance column is not available in that query
			if (!$hasSearch && $sortKey === 'relevance') {
				$order = 'pst.`sort_order` ASC';
			}
    } else {
			$order = 'pst.`sort_order` ASC';
    }

		$sql = "
			WITH " . implode(', ', $ctes) . "
			SELECT 
				" . implode(', ', $selectColumns) . "
			FROM {$from}
			{$join}
			LEFT JOIN `oc_facet_sort` pst
				ON  pst.`product_id` = f.`product_id`
				AND pst.`store_id`   = {$store_id}
			GROUP BY {$groupBy}
			{$having}
			ORDER BY {$order}
			LIMIT {$limit} OFFSET {$offset}
		";

    $rows     = $this->db->query($sql)->rows;
    $products = [];

    foreach ($rows as $row) {
			$products[] = $this->getProduct((int) $row['product_id']);
    }

    // Return total if withTotal flag is true
    if ($withTotal) {
			return [
				'products' 		=> $products,
				'total'    		=> (int)($rows[0]['total_count'] ?? 0),
				'price_min'   => (int)($rows[0]['price_min'] ?? 0),
				'price_max'   => (int)($rows[0]['price_max'] ?? 0),
				'reviews'   	=> (int)($rows[0]['reviews'] ?? 0),
				'rating'   		=> (int)($rows[0]['rating'] ?? 0),
			];
    }

    return $products;
	}

	public function getFilters($data = []) {
		$store_id    				 	= (int) $this->config->get('config_store_id');
		$language_id 				 	= (int) $this->config->get('config_language_id');
		$route 								= $this->request->get['route'] ?? '';
		$conditions 				 	= [];
		$base_facet_type     	= null; // Page type, category = 1, manufacturer = 5, has_discount = 9, is_featured = 10
		$base_facet_value_id 	= null; // Page id if applicable, i.e. category_id. If not applicable then 0 
		$searchExpression 	 	= $this->buildMatchExpression($data);
		$baseProductList 	 	 	= "";
		$hasSearch 						= !empty($searchExpression);
		$isCacheable 					= false;
		$cachedFacets 				= false;
		$cacheSetting 				= (bool) $this->config->get('cache_filter_list');
		// $hasFacets 						= !empty(array_intersect_key(array_filter($data), $this->facetTypes));

		// Base products list
		// Get all facets related to all products from current page
		// Assume either search of facet is present
		if ($hasSearch) {
			$baseProductList = "
				base_products AS (
					SELECT `product_id`
					FROM `" . DB_PREFIX . "product_search_index`
					WHERE `language_id` = {$language_id}
						AND `store_id`    = {$store_id}
						AND ({$searchExpression}) > 0
				)
			";

			$facetExpression     = $this->buildFacetExpression($data);
			$selected_conditions = !empty($facetExpression) ? $facetExpression : "0";

		} else {
			// Get base facet dynamically from $facetTypes
			foreach ($this->facetTypes as $typeId => $facet) {
				if ($facet['route'] !== $route) continue;

				$base_facet_type = $typeId;

				if ($facet['isBool']) {
					// Binary facets that don't need strict facet_value_id: is_featured, has_discount, latest, bestseller, etc.
					$base_facet_value_id = 1;
				} else {
					// Strict facet, get facet_value_id from request
					$base_facet_value_id = (int)($data[$facet['facetType']] ?? 0);

					// Separate category_id case because we have $request['path'] which also stands for category
					if ($facet['facetType'] === 'category_id' && !$base_facet_value_id) {
						$path                = $this->request->get['category_id'] ?? $this->request->get['path'] ?? '';
						$parts               = explode('_', (string) $path);
						$base_facet_value_id = (int)end($parts) ?: null;
					}
				}

				break;
			}

			if (!$base_facet_type || !$base_facet_value_id) {
				return [];
			}

			// The base facet is intentionally included in selected_conditions
			// So that the page context participates in the AND-between-groups logic.
			$conditions[] = "(facet_type = {$base_facet_type} AND facet_value_id = {$base_facet_value_id})";

			// User facets
			foreach ($this->facetTypes as $typeId => $facet) {
				$facetKey = $facet['facetType'];

				if (empty($data[$facetKey])) continue;

				// Skip base facet to avoid duplicate expression
				if ($typeId === $base_facet_type) continue;

				if ($facet['isBool']) {
					$conditions[] = "(facet_value_id = 1 AND facet_type = {$typeId})";
				} else {
					$ids = array_values(array_unique(array_map('intval', explode(',', $data[$facetKey]))));
					if (empty($ids)) continue;
					$conditions[] = "(facet_value_id IN(" . implode(',', $ids) . ") AND facet_type = {$typeId})";
				}
			}

			$selected_conditions = $conditions ? implode(" OR ", $conditions) : "1";

			$baseProductList = "
				base_products AS (
					SELECT `product_id`
					FROM `" . DB_PREFIX . "facet_index`
					WHERE `facet_type`     = {$base_facet_type}
						AND `facet_value_id` = {$base_facet_value_id}
						AND `store_id`       = {$store_id}
				)
			";
		}

		if (!$hasSearch && !$base_facet_type) {
			return [];
		}

		// Cache conditions:
		// Base facet type and value is set, only one facet selected and it is base, no search used
		if (!$hasSearch && $base_facet_type && $base_facet_value_id && count($conditions) === 1) {
			$isCacheable = true;
		}

		if ($isCacheable && $cacheSetting) {
			$facetCacheName = $this->facetTypes[$base_facet_type]['facetType'];
			$cacheName 			= "filter.store_{$store_id}.language_{$language_id}.{$facetCacheName}.{$base_facet_value_id}";
			$cachedFacets = $this->cache->get($cacheName);
			if ($cachedFacets) {
				return $cachedFacets;
			}
		}
		// End cache


		$sql = "
			WITH 

			/* Base products */
			{$baseProductList},

			/* Base facet list to be displayed on the page - all the facets from current page */
			base_facet_list AS (
				SELECT
					i.facet_value_id,
					i.facet_type,
					i.facet_group_id,
					COUNT(DISTINCT(i.product_id)) AS base_count
				FROM " . DB_PREFIX . "facet_index i
				JOIN base_products bp
					ON bp.product_id = i.product_id
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
					-- 2. For OR groups exclude products that already shown
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

		$facets = [];
		$query = $this->db->query($sql);
		foreach ($query->rows as $row) {
			$row['facet_type_id'] = $row['facet_type'];
			$row['facet_type'] 		= $this->facetTypes[$row['facet_type']]['facetType'];
			$facets[] = $row;
		}

		// Cache
		if ($isCacheable && !$cachedFacets && $cacheSetting) {
			$cacheName = "filter.store_{$store_id}.language_{$language_id}.{$facetCacheName}.{$base_facet_value_id}";
			$this->cache->set($cacheName, $facets);
		}
		// End cache

		return $facets;
	}

	
	/**
	 * Get SEO filter page by facet combination
	 * @param array $request - GET request
	 * @return array|bool
	 */
	public function getFilterPage($request) : array|bool {
		$facets 				 = $this->getFacetTypes();
		$facetTypes 		 = array_column($facets, 'facetType');
		$facetRequest 	 = $this->prepareGetProductsRequest($request);
		$facetExpression = $this->buildFacetExpression($facetRequest);
		$facetMatches 	 = array_intersect($facetTypes, array_keys($facetRequest));
		$language_id 		 = (int) $this->config->get('config_language_id');
		$store_id 			 = (int) $this->config->get('config_store_id');
		$cacheSetting 	 = (bool) $this->config->get('cache_filter_pages');
		
		// Return false if only one facet is applied
		if (count($facetMatches) <= 1) {
			return false;
		}

		$sql = "
			SELECT 
				filter_page_id
			FROM " . DB_PREFIX . "seo_filter_page_facet_index
			WHERE 1 AND {$facetExpression}
				AND store_id = {$store_id}
			GROUP BY filter_page_id
			
			HAVING COUNT(*) = " . count($facetMatches) . "
				AND  COUNT(*) = (
					SELECT 
						COUNT(*) 
					FROM " . DB_PREFIX . "seo_filter_page_facet_index fi2 
					WHERE fi2.filter_page_id = " . DB_PREFIX . "seo_filter_page_facet_index.filter_page_id
				)
			ORDER BY NULL
			LIMIT 1
		";
		$filterPageId = $this->db->query($sql)->row['filter_page_id'] ?? false;

		// Return false if no pages found
		if (!$filterPageId) {
			return false;
		}

		// Cache
		if ($cacheSetting) {
			$cacheName 	= "filter_page.store_{$store_id}.language_{$language_id}." . (floor($filterPageId / 100)) . "00.filter_page_{$filterPageId}";
			$cachedData = $this->cache->get($cacheName);
			if ($cachedData) {
				return $cachedData;
			}
		}

		// Get filter page data
		$sql = "
			SELECT
				sd.`filter_page_id`,
				sd.`language_id`,
				sd.`store_id`,
				sd.`name`,
				sd.`h1`,
				sd.`meta_title`,
				sd.`meta_description`,
				sd.`meta_keyword`,
				sd.`description`,
				sd.`seo_keywords` AS seoKeywords,
				sd.`seo_description`,
				sd.`faq` AS faqJson,
				sd.`how_to` AS howToJson,
				sd.`footer`,
				sd.`date_modified`,
				JSON_ARRAYAGG(
					JSON_OBJECT(
						'image', 				pi.`image`,
						'sort_order', 	pi.`sort_order`,
						'description', 	pid.`description`
					)
				) AS images
			FROM " . DB_PREFIX . "seo_filter_page_description sd
			LEFT JOIN " . DB_PREFIX . "seo_filter_page_image pi
				ON  pi.`filter_page_id` = sd.`filter_page_id`
				AND pi.`store_id` 	 		= sd.`store_id`
			LEFT JOIN " . DB_PREFIX . "seo_filter_page_image_description pid
				ON  pid.`image_id` 			= pi.`image_id`
				AND pid.`language_id` 	= sd.`language_id`
				AND pid.`store_id` 			= sd.`store_id`
			WHERE sd.`filter_page_id` = {$filterPageId}
				AND sd.`language_id` 	  = {$language_id}
				AND sd.`store_id` 		  = {$store_id}
		";

		$data = $this->db->query($sql)->row;

		$data['seoKeywords']  = json_decode($data['seoKeywords'] ?? '[]', true);
		$data['faq'] 					= json_decode($data['faqJson'] 		 ?? '[]', true);
		$data['howTo'] 				= json_decode($data['howToJson'] 	 ?? '[]', true);
		$data['footer'] 			= json_decode($data['footer'] 		 ?? '[]', true);
		$data['images']				= json_decode($data['images'] 		 ?? '[]', true);
		$data['description']	= html_entity_decode($data['description'], ENT_QUOTES, 'UTF-8');
		// Resize images to store prepared image links
		$this->load->model('tool/image');
		$cover 							= [];
		$images 						= [];
		$theme        	 		= $this->config->get('config_theme');
		$imgMainWidth  			= (int) ($this->config->get("theme_{$theme}_image_category_main_width") ?? 2000);
		$imgMainHeight 			= (int) ($this->config->get("theme_{$theme}_image_category_main_height") ?? 2000);
		$imgMiniatureWidth  = (int) ($this->config->get("theme_{$theme}_image_category_width") ?? 600);
		$imgMiniatureHeight = (int) ($this->config->get("theme_{$theme}_image_category_height") ?? 600);
		// Add cover to the beginning of images array
		$cover['image'] 		  = $data['image'] ?? 'no_image.webp';
		$cover['description'] = $data['name'];
		array_unshift($data['images'], $cover);

		foreach ($data['images'] as $img) {
			$images['covers'][] = [
				'src' 				=> $this->model_tool_image->resize($img['image'], $imgMainWidth, $imgMainHeight),
				'description' => $img['description'],
				'width'				=> $imgMainWidth,
				'height'			=> $imgMainHeight,
			];

			$images['miniatures'][] = [
				'src' 				=> $this->model_tool_image->resize($img['image'], $imgMiniatureWidth, $imgMiniatureHeight),
				'description' => $img['description'],
				'width'				=> $imgMiniatureWidth,
				'height'			=> $imgMiniatureHeight,
			];
		}

		$data['images'] = $images;
		// End images

		if ($cacheSetting) {
			$this->cache->set($cacheName, $data);
		}

		return $data;
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

	private function buildMatchExpression(array $data, int $ngramLength = 2) : string {
		$query = $data['filter_name'] ?? "";
		if (empty($query)) {
			return "";
		}
		// Remove BOOLEAN MODE reserved symbols
		$query = str_replace(['+', '-', '>', '<', '(', ')', '~', '*', '"', '@', '\\'], ' ', $query);

		$query = explode(' ', mb_strtolower(trim($query)));

		$query = array_filter(
			// Remove words shorter than ngram
			$query,
			fn(string $w) => mb_strlen($w) >= $ngramLength
		);

		// Safe return if search is empty
		if (empty($query)) {
			return "";
		}

		$query = implode(' ',
			array_map(
				// Escape special characters
				fn(string $w) => $this->db->escape($w),
				array_values(
					array_unique(
						// Remove word duplicates
						$query
					)
				)
			)
		);

		$string = "
			MATCH(`name`)         AGAINST('{$query}' IN NATURAL LANGUAGE MODE) * 10 +
			MATCH(`manufacturer`) AGAINST('{$query}' IN NATURAL LANGUAGE MODE) * 5  +
			MATCH(`category`)     AGAINST('{$query}' IN NATURAL LANGUAGE MODE) * 5  +
			MATCH(`extra`)        AGAINST('{$query}' IN NATURAL LANGUAGE MODE) * 1
		";

		return $string;
	}

	private function buildCteExpression($data) : array {
		$cteList 		 			= [];
		$language_id 			= (int) $this->config->get('config_language_id');
		$store_id 	 			= (int) $this->config->get('config_store_id');
		$facets 		 			= $this->buildFacetExpression($data);
		$searchExpression = $this->buildMatchExpression($data);
		$hasSearch 	 			= !empty($searchExpression);
		$hasFacets   			= !empty($facets);

		// Build CTE expression requied for facet search
		if ($hasFacets) {
			$where = [];
			$where[] = "(" . $facets . ")";
			$where[] = "store_id = {$store_id}";

			$cteList['facet_temp'] = "
				facet_temp AS (
					SELECT `product_id`, `facet_type`, `facet_group_id`
					FROM `" . DB_PREFIX . "facet_index`
					WHERE " . implode(" AND ", $where) . "
					ORDER BY NULL
				)
			";

			$cteList['group_count'] = "
				group_count AS (
					SELECT COUNT(DISTINCT `facet_type`, `facet_group_id`) AS cnt
					FROM facet_temp
				)
			";
    }

		// Build CTE expression required for FULLTEXT search
		if ($hasSearch) {
			$cteList['search_results'] = "
				search_results AS (
					SELECT `product_id`, ({$searchExpression}) AS relevance
					FROM `" . DB_PREFIX . "product_search_index`
					WHERE `language_id` = {$language_id}
						AND `store_id`    = {$store_id}
						AND ({$searchExpression}) > 0
				)
			";
		}

		return $cteList;
	}

	private function buildFacetExpression($data) : string {
		$facets = [];

		foreach ($this->facetTypes as $type => $facet) {
			if (!empty($data[$facet['facetType']])) {
				if ($facet['isBool'] === true) {
					$facets[] = "(facet_value_id = 1 AND facet_type = {$type})";
				} else {
					$ids      = array_values(array_unique(array_map('intval', explode(',', $data[$facet['facetType']]))));
					$facets[] = "(facet_value_id IN(" . implode(',', $ids) . ") AND facet_type = {$type})";
				}
			}
		}

		return !empty($facets) ? implode(' OR ', $facets) : "";
	}
	
	public function getProductSpecials($data, $withTotal) : array {
		$data['has_discount'] = 1;
		$productData = $this->getProducts($data, $withTotal);
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
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int) $product_id . "' AND store_id = '" . (int) $this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int) $query->row['layout_id'];
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
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "recurring r JOIN " . DB_PREFIX . "product_recurring pr ON (pr.recurring_id = r.recurring_id AND pr.product_id = '" . (int) $product_id . "') WHERE pr.recurring_id = '" . (int) $recurring_id . "' AND status = '1' AND pr.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "'");

		return $query->row;
	}

	public function getProfiles($product_id) {
		$query = $this->db->query("SELECT rd.* FROM " . DB_PREFIX . "product_recurring pr JOIN " . DB_PREFIX . "recurring_description rd ON (rd.language_id = " . (int) $this->config->get('config_language_id') . " AND rd.recurring_id = pr.recurring_id) JOIN " . DB_PREFIX . "recurring r ON r.recurring_id = rd.recurring_id WHERE pr.product_id = " . (int) $product_id . " AND status = '1' AND pr.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "' ORDER BY sort_order ASC");

		return $query->rows;
	}
}
