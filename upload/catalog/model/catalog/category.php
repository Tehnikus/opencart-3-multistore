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

		$query = $this->db->query("
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
				cd.`language_id`
			FROM " . DB_PREFIX . "category_to_store c2s
			INNER JOIN " . DB_PREFIX . "category c
				ON c.`category_id` = c2s.`category_id`
			INNER JOIN " . DB_PREFIX . "category_description cd
				ON  cd.`category_id` 	= c2s.`category_id`
				AND cd.`language_id` 	= '" . (int) $this->config->get('config_language_id') . "'
				AND cd.`store_id` 		= c2s.`store_id`
			WHERE c2s.`category_id` = '" . (int) $category_id . "'
				AND c2s.`store_id` 		= '" . (int) $this->config->get('config_store_id') . "'
				AND c2s.`status` 			= 1
			LIMIT 1
		");

		$this->cache->set($categoryCacheName, $query->row);

		return $query->row ?? [];
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
				cd.`language_id`
			FROM " . DB_PREFIX . "category_to_store c2s
			INNER JOIN " . DB_PREFIX . "category c
				ON  c.`category_id` = c2s.`category_id`
			INNER JOIN " . DB_PREFIX . "category_description cd
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

	// Only used in upload\catalog\controller\extension\module\filter.php
	public function getCategoryFilters($category_id) {

		$sql = "
			SELECT 
				f.filter_id,
				fd.name AS filter_name,
				f.filter_group_id,
				f.sort_order AS filter_sort,
				fg2s.sort_order AS group_sort,
				fgd.name AS group_name
			FROM " . DB_PREFIX . "category_filter cf
			
			INNER JOIN " . DB_PREFIX . "filter f 
				ON cf.filter_id = f.filter_id 
				AND f.store_id = cf.store_id

			INNER JOIN " . DB_PREFIX . "filter_group fg
				ON fg.filter_group_id = f.filter_group_id

			INNER JOIN " . DB_PREFIX . "filter_description fd 
				ON 	f.filter_id 		= fd.filter_id 
				AND fd.language_id 	= '" . (int) $this->config->get('config_language_id') . "'
				AND fd.store_id 		= cf.store_id 

			INNER JOIN " . DB_PREFIX . "filter_group_to_store fg2s
				ON f.filter_group_id 	= fg2s.filter_group_id 
				AND fg2s.store_id 		= cf.store_id

			INNER JOIN " . DB_PREFIX . "filter_group_description fgd
				ON  f.filter_group_id  	= fgd.filter_group_id
				AND fgd.language_id 		= '" . (int) $this->config->get('config_language_id') . "'
				AND fgd.store_id 				= cf.store_id

			WHERE cf.category_id 	= '" . (int) $category_id . "'
				AND cf.store_id 		= '" . (int) $this->config->get('config_store_id') . "'

			ORDER BY fg2s.sort_order, 
				LCASE(group_name),
				f.sort_order,
				LCASE(filter_name)
		";

		$query = $this->db->query($sql);

		$filter_group_data = [];

		foreach ($query->rows as $row) {
			$group_id = $row['filter_group_id'];

			if (!isset($filter_group_data[$group_id])) {
				$filter_group_data[$group_id] = array(
					'filter_group_id' => $group_id,
					'name'            => $row['group_name'],
					'filter'          => []
				);
			}

			$filter_group_data[$group_id]['filter'][] = array(
				'filter_id' => $row['filter_id'],
				'name'      => $row['filter_name']
			);
		}

		// Reset keys to start from zero
		return array_values($filter_group_data);
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