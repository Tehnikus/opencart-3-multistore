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

	public function getCategories($parent_id = 0) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'  AND c.status = '1' ORDER BY c.sort_order, LCASE(cd.name)");

		return $query->rows;
	}

	public function getCategoryFilters($category_id) {
		$implode = array();

		$query = $this->db->query("SELECT filter_id FROM " . DB_PREFIX . "category_filter WHERE category_id = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$implode[] = (int)$result['filter_id'];
		}

		$filter_group_data = array();

		if ($implode) {
			$filter_group_query = $this->db->query("SELECT DISTINCT f.filter_group_id, fgd.name, fg.sort_order FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_group fg ON (f.filter_group_id = fg.filter_group_id) LEFT JOIN " . DB_PREFIX . "filter_group_description fgd ON (fg.filter_group_id = fgd.filter_group_id) WHERE f.filter_id IN (" . implode(',', $implode) . ") AND fgd.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY f.filter_group_id ORDER BY fg.sort_order, LCASE(fgd.name)");

			foreach ($filter_group_query->rows as $filter_group) {
				$filter_data = array();

				$filter_query = $this->db->query("SELECT DISTINCT f.filter_id, fd.name FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_description fd ON (f.filter_id = fd.filter_id) WHERE f.filter_id IN (" . implode(',', $implode) . ") AND f.filter_group_id = '" . (int)$filter_group['filter_group_id'] . "' AND fd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY f.sort_order, LCASE(fd.name)");

				foreach ($filter_query->rows as $filter) {
					$filter_data[] = array(
						'filter_id' => $filter['filter_id'],
						'name'      => $filter['name']
					);
				}

				if ($filter_data) {
					$filter_group_data[] = array(
						'filter_group_id' => $filter_group['filter_group_id'],
						'name'            => $filter_group['name'],
						'filter'          => $filter_data
					);
				}
			}
		}

		return $filter_group_data;
	}

	public function getCategoryLayoutId($category_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" . (int)$category_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}
}