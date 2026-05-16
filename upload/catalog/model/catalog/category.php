<?php
class ModelCatalogCategory extends Model {
	public function getCategory($category_id) : array {
		$category_id 	= (int) $category_id;
		$language_id 	= (int) $this->config->get('config_language_id');
		$store_id 		= (int) $this->config->get('config_store_id');

		// Cache
		$categoryCacheName 	= "category.store_{$store_id}.language_{$language_id}." . (floor($category_id / 100)) . "00.category_{$category_id}";
		$cachedData 				= $this->cache->get($categoryCacheName);
		if ($cachedData) {
			return $cachedData;
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
				cd.`faq`,
				cd.`how_to`,
				cd.`footer`,
				cd.`date_modified`,
				cd.`language_id`,
				(SELECT COUNT(fi.`product_id`) FROM " . DB_PREFIX . "facet_index fi WHERE fi.`facet_value_id` = c.`category_id` AND fi.`facet_type` = 1 AND fi.`store_id` = c2s.`store_id`) AS product_count,
				JSON_ARRAYAGG(
					JSON_OBJECT(
						'image', 				ci.`image`,
						'sort_order', 	ci.`sort_order`,
						'description', 	cid.`description`
					)
				) AS images
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
			WHERE c2s.`category_id` = '" . (int) $category_id . "'
				AND c2s.`store_id` 		= '" . (int) $this->config->get('config_store_id') . "'
				AND c2s.`status` 			= 1
			LIMIT 1
		";

		$data = $this->db->query($sql)->row;
		
		// Safely return if category does not exist
		if (empty(array_filter($data))) {return false;}

		$data['seo_keywords'] = json_decode($data['seo_keywords'] ?? '[]', true);
		$data['faq'] 					= json_decode($data['faq'] ?? '[]', true);
		$data['how_to'] 			= json_decode($data['how_to'] ?? '[]', true);
		$data['footer'] 			= json_decode($data['footer'] ?? '[]', true);
		$data['images']				= json_decode($data['images'] ?? '[]', true);
    usort(array: $data['images'], callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);

		$this->cache->set($categoryCacheName, $data);

		return $data;
	}


	public function getCategories($parent_id = 0) : array {

		$parent_id 		= (int) $parent_id;
		$language_id 	= (int) $this->config->get('config_language_id');
		$store_id 		= (int) $this->config->get('config_store_id');

		// Cache
		$childrenCacheName 	= "category.store_{$store_id}.language_{$language_id}." . (floor($parent_id / 100)) . "00.child_categories_{$parent_id}";
		$cachedData 				= $this->cache->get($childrenCacheName);

		if ($cachedData) {
			return $cachedData;
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

		$this->cache->set($childrenCacheName, $query->rows);
		
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