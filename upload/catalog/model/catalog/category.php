<?php
class ModelCatalogCategory extends Model {
	public function getCategory($category_id) : array|bool {
		$category_id 	= (int) $category_id;
		$language_id 	= (int) $this->config->get('config_language_id');
		$store_id 		= (int) $this->config->get('config_store_id');
		$cacheSetting = (bool) $this->config->get('cache_categories');

		// Cache
		if ($cacheSetting) {
			$cacheName 	= "category.store_{$store_id}.language_{$language_id}." . (floor($category_id / 100)) . "00.category_{$category_id}";
			$cachedData = $this->cache->get($cacheName);
			if ($cachedData) {
				return $cachedData;
			}
		}

		$sql = "
			SELECT 
				c.`category_id`,
				c2s.`store_id`,
				c2s.`parent_id`,
				c2s.`sort_order`,
				c2s.`status`,
				c2s.`top`,
				c2s.`image`,
				c2s.`column`,
				cd.`name`,
				cd.`meta_title`,
				cd.`meta_description`,
				cd.`meta_keyword`,
				cd.`description`,
				cd.`seo_keywords`,
				cd.`seo_description`,
				cd.`faq` 		AS `faq_json`,
				cd.`how_to` AS `how_to_json`,
				cd.`footer` AS `seoFooter`,
				cd.`date_modified`,
				cd.`language_id`,
				JSON_ARRAYAGG(
					JSON_OBJECT(
						'image', 				ci.`image`,
						'sort_order', 	ci.`sort_order`,
						'description', 	cid.`description`
					)
				) AS images,
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
					WHERE r.`category_id` = c2s.`category_id`
						AND r.`store_id`	 	= c2s.`store_id`
						AND r.`language_id` = cd.`language_id`
						AND r.`status` 			= 1
					ORDER BY r.`date_modified` DESC
					LIMIT 10
				) AS last_reviews

			FROM " . DB_PREFIX . "category_to_store c2s
			LEFT JOIN " . DB_PREFIX . "category c
				ON c.`category_id` = c2s.`category_id`
			LEFT JOIN " . DB_PREFIX . "category_description cd
				ON  cd.`category_id` 	= c2s.`category_id`
				AND cd.`language_id` 	= '" . (int) $this->config->get('config_language_id') . "'
				AND cd.`store_id` 		= c2s.`store_id`
			LEFT JOIN " . DB_PREFIX . "category_image ci
				ON ci.category_id = c2s.`category_id`
				AND ci.store_id 	= c2s.`store_id`
			LEFT JOIN " . DB_PREFIX . "category_image_description cid
				ON cid.image_id 		= ci.image_id
				AND cid.language_id = cd.language_id
				AND cid.store_id 		= c2s.store_id
			-- LEFT JOIN category_stats cs
			-- 	ON cs.`category_id` = c2s.`category_id`
			WHERE c2s.`category_id` = '" . (int) $category_id . "'
				AND c2s.`store_id` 		= '" . (int) $this->config->get('config_store_id') . "'
				AND c2s.`status` 			= 1
			LIMIT 1
		";

		$data = $this->db->query($sql)->row;
		
		// Safely return if category does not exist
		if (empty(array_filter($data))) {return false;}

		$data['cache_date'] 			= strtotime($data['date_modified']); // Cache version
		$data['seo_keywords'] 		= json_decode($data['seo_keywords'] ?? '[]', true);
		$data['faq'] 							= json_decode($data['faq_json'] 		?? '[]', true);
		$data['how_to'] 					= json_decode($data['how_to_json'] 	?? '[]', true);
		$data['seoFooter'] 				= json_decode($data['seoFooter'] 		?? '[]', true);
		$data['images']						= json_decode($data['images'] 			?? '[]', true);
		$data['last_reviews'] 		= json_decode($data['last_reviews'] ?? '[]', true);
		$data['child_categories']	= $this->getCategories($category_id);
		$data['description']			= html_entity_decode($data['description'], ENT_QUOTES, 'UTF-8');
		$data['seo_description']	= html_entity_decode($data['seo_description'], ENT_QUOTES, 'UTF-8');
		foreach ($data['seoFooter'] ?? [] as $key => $tab) {
			$data['seoFooter'][$key]['description'] = html_entity_decode($tab['description'], ENT_QUOTES, 'UTF-8');
		}
		usort(array: $data['images'], callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);

		// Resize images to store prepared image links
		$this->load->model('tool/image');
		$cover 							= [];
		$categoryImages 		= [];
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
			$categoryImages['covers'][] = [
				'src' 				=> $this->model_tool_image->resize($img['image'], $imgMainWidth, $imgMainHeight),
				'description' => $img['description'],
				'width'				=> $imgMainWidth,
				'height'			=> $imgMainHeight,
			];

			$categoryImages['miniatures'][] = [
				'src' 				=> $this->model_tool_image->resize($img['image'], $imgMiniatureWidth, $imgMiniatureHeight),
				'description' => $img['description'],
				'width'				=> $imgMiniatureWidth,
				'height'			=> $imgMiniatureHeight,
			];
		}

		$data['images'] = $categoryImages;
		// End images

		// Child categories
		foreach ($data['child_categories'] as $key => $child_category) {
			$data['child_categories'][$key]['image'] = $this->model_tool_image->resize($child_category['image'] ?? 'no_image.webp', $imgMiniatureWidth, $imgMiniatureHeight);
			$data['child_categories'][$key]['href']  = $this->url->link('product/category', 'path=' . $child_category['category_id']);
			// Set cache version to max date among child categories
			if (strtotime($child_category['date_modified']) > $data['cache_date']) {
				$data['cache_date'] = strtotime($child_category['date_modified']);
			}
		}
		// End child categories

		// Breadcrumbs
		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home'),
    ];
		$data['breadcrumbs'][] = [
			'text' => $data['name'],
			'href' => $this->url->link('product/category', 'path=' . $data['category_id']),
		];
		// End breadcrumbs

		// Category URL
		$data['url'] = $this->url->link('product/category', "product_id={$category_id}", true);
		// End category URL

		if ($cacheSetting) {
			$this->cache->set($cacheName, $data);
		}

		return $data;
	}


	public function getCategories($parent_id = 0) : array {

		$parent_id 		= (int) $parent_id;
		$language_id 	= (int) $this->config->get('config_language_id');
		$store_id 		= (int) $this->config->get('config_store_id');
		$cacheSetting = (bool) $this->config->get('cache_categories');

		// Cache
		if ($cacheSetting) {
			$cacheName 	= "category.store_{$store_id}.language_{$language_id}." . (floor($parent_id / 100)) . "00.child_categories_{$parent_id}";
			$cachedData = $this->cache->get($cacheName);
	
			if ($cachedData) {
				return $cachedData;
			}
		}

		$sql = "
			SELECT 
				c.`category_id`,
				c2s.`store_id`,
				c2s.`parent_id`,
				c2s.`sort_order`,
				c2s.`status`,
				c2s.`top`,
				c2s.`image`,
				c2s.`column`,
				cd.`name`,
				cd.`seo_keywords`,
				cd.`date_modified`,
				cd.`language_id`,
				(SELECT COUNT(fi.`product_id`) FROM " . DB_PREFIX . "facet_index fi WHERE fi.`facet_value_id` = c.`category_id` AND fi.`facet_type` = 1 AND fi.`store_id` = c2s.`store_id`) AS product_count
			FROM " . DB_PREFIX . "category_to_store c2s
			LEFT JOIN " . DB_PREFIX . "category c
				ON  c.`category_id` = c2s.`category_id`
			LEFT JOIN " . DB_PREFIX . "category_description cd
				ON  cd.`category_id` 	= c.category_id
				AND cd.`language_id` 	= '" . (int) $this->config->get('config_language_id') . "'
				AND cd.`store_id` 		= c2s.`store_id`
			WHERE c2s.`parent_id` 	= '" . (int) $parent_id . "'
				AND c2s.`store_id` 		= '" . (int) $this->config->get('config_store_id') . "'
				AND c2s.`status` 			= 1
			ORDER BY c2s.`sort_order` ASC
		";

		$query = $this->db->query($sql);
		if ($cacheSetting) {
			$this->cache->set($cacheName, $query->rows);
		}
		
		return $query->rows ?? [];
	}

	public function getCategoryLayoutId($category_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "category_to_layout 
			WHERE category_id = '" . (int) $category_id . "' 
				AND store_id 		= '" . (int) $this->config->get('config_store_id') . "'
		");

		if ($query->num_rows) {
			return (int) $query->row['layout_id'];
		} else {
			return 0;
		}
	}
}