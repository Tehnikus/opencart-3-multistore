<?php
class ModelDesignLayout extends Model {
	// public function getLayout($route) {
	// 	$query = $this->db->query("
	// 		SELECT 
	// 			* 
	// 		FROM " . DB_PREFIX . "layout_route 
	// 		WHERE '" . $this->db->escape($route) . "' LIKE route 
	// 			AND store_id = '" . (int) $this->config->get('config_store_id') . "' 
	// 		ORDER BY route DESC LIMIT 1
	// 	");

	// 	if ($query->num_rows) {
	// 		return (int)$query->row['layout_id'];
	// 	} else {
	// 		return 0;
	// 	}
	// }
	public function getLayout($route) {

		// $cache_key = 'layout.route.' . (int) $this->config->get('config_store_id') . '.' . md5($route);
		// $layout_id = $this->cache->get($cache_key);

		// if ($layout_id !== false) {
		// 	return (int) $layout_id;
		// }

		// Exact match first
		$sql = "
			SELECT 
				layout_id
			FROM " . DB_PREFIX . "layout_route
			WHERE `route` = '" . $this->db->escape($route) . "'
				AND `store_id` = '" . (int) $this->config->get('config_store_id') . "'
				AND `is_wildcard` = 0
			LIMIT 1
		";
		$query = $this->db->query($sql);

		if ($query->num_rows) {
			$layout_id = (int)$query->row['layout_id'];
		} else {

			// Wildcard fallback
			$query = $this->db->query("
				SELECT 
					layout_id
				FROM " . DB_PREFIX . "layout_route
				WHERE '" . $this->db->escape($route) . "' LIKE `route`
					AND `store_id` = '" . (int) $this->config->get('config_store_id') . "'
					AND `is_wildcard` = 1
				ORDER BY LENGTH(route) DESC
				LIMIT 1
			");

			$layout_id = $query->num_rows ? (int) $query->row['layout_id'] : 0;
		}

		// $this->cache->set($cache_key, $layout_id);

		return $layout_id;
	}
	
	public function getLayoutModules($layout_id, $position) {

		// $cache_key = 'layout.modules.' . (int) $this->config->get('config_store_id') . '.' . $layout_id . '.' . md5($position);
		// $modules = $this->cache->get($cache_key);

		// if ($modules !== false) {
		// 	return $modules;
		// }

		$query = $this->db->query("
			SELECT 
				*
			FROM " . DB_PREFIX . "layout_module
			WHERE layout_id = '" . (int) $layout_id . "'
				AND position = '" . $this->db->escape($position) . "'
				AND store_id = '" . (int) $this->config->get('config_store_id') . "'
			ORDER BY sort_order
		");

		$modules = $query->rows;

		// $this->cache->set($cache_key, $modules);

		return $modules;
	}
}