<?php
class ModelDesignLayout extends Model {
	public function addLayout($data) : int {
		$this->db->query("START TRANSACTION");
		try {
			$this->db->query("
				INSERT INTO " . DB_PREFIX . "layout 
				SET 
					`name` 			= '" . $this->db->escape($data['name']) . "',
					`store_id` 	= '" . (int) $this->session->data['store_id'] . "'
			");
	
			$layout_id = $this->db->getLastId();
	
			if (isset($data['layout_route'])) {
	
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "layout_route 
					SET 
						`layout_id` 	= '" . (int) $layout_id . "', 
						`store_id`		= '" . (int) $this->session->data['store_id'] . "',
						`route` 			= '" . $this->db->escape($data['layout_route']['route']) . "',
						`is_wildcard` = '" . (str_contains($data['layout_route']['route'], '%') ? '1' : '0') . "'
				");
				
			}
	
			if (isset($data['layout_module'])) {
				foreach ($data['layout_module'] as $layout_module) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "layout_module 
						SET 
							`layout_id` 	= '" . (int) $layout_id . "', 
							`store_id`		= '" . (int) $this->session->data['store_id'] . "',
							`code` 				= '" . $this->db->escape($layout_module['code']) . "', 
							`position` 		= '" . $this->db->escape($layout_module['position']) . "', 
							`sort_order` 	= '" . (int) $layout_module['sort_order'] . "'
					");
				}
			}
	
			$this->db->query("COMMIT");
			
			return $layout_id;

		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	public function editLayout($layout_id, $data) : int {
		
		$this->db->query("START TRANSACTION");
		
		try {

			$this->db->query("
				UPDATE " . DB_PREFIX . "layout 
				SET 
					`name` = '" . $this->db->escape($data['name']) . "' 
				WHERE `layout_id` = '" . (int) $layout_id . "'
					AND `store_id`  = '" . (int) $this->session->data['store_id'] . "'
			");
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "layout_route 
				WHERE `layout_id` = '" . (int) $layout_id . "'
			");
	
			if (isset($data['layout_route'])) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "layout_route 
					SET 
						`layout_id` 	= '" . (int) $layout_id . "', 
						`store_id`		= '" . (int) $this->session->data['store_id'] . "',
						`route` 			= '" . $this->db->escape($data['layout_route']['route']) . "',
						`is_wildcard` = '" . (str_contains($data['layout_route']['route'], '%') ? '1' : '0') . "'
				");
			}
	
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "layout_module 
				WHERE `layout_id` = '" . (int) $layout_id . "'
			");
	
			if (isset($data['layout_module'])) {
				foreach ($data['layout_module'] as $layout_module) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "layout_module 
						SET 
							`layout_id`  = '" . (int) $layout_id . "', 
							`store_id`	 = '" . (int) $this->session->data['store_id'] . "',
							`code` 			 = '" . $this->db->escape($layout_module['code']) . "', 
							`position` 	 = '" . $this->db->escape($layout_module['position']) . "', 
							`sort_order` = '" . (int) $layout_module['sort_order'] . "'
					");
				}
			}

			$this->db->query("COMMIT");
			return $layout_id;
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	public function deleteLayout($layout_id) : bool {
		
		$this->db->query("START TRANSACTION");
		
		try {
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "layout 
				WHERE `layout_id` = '" . (int) $layout_id . "'
					AND `store_id`  = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "layout_route 
				WHERE `layout_id` = '" . (int) $layout_id . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "layout_module 
				WHERE `layout_id` = '" . (int) $layout_id . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "category_to_layout 
				WHERE `layout_id` = '" . (int) $layout_id . "'
					AND `store_id`  = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_to_layout 
				WHERE `layout_id` = '" . (int) $layout_id . "'
					AND `store_id`  = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "information_to_layout 
				WHERE `layout_id` = '" . (int) $layout_id . "'
					AND `store_id`  = '" . (int) $this->session->data['store_id'] . "'
			");
			$this->db->query("COMMIT");
			return true;
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	// Get layout data in admin layout form
	public function getLayout($layout_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "layout 
			WHERE `layout_id` = '" . (int) $layout_id . "'
				AND `store_id`  = '" . (int) $this->session->data['store_id'] . "'
		");

		return $query->row;
	}

	// Get layout list in admin layout list controller
	// Loads layouts for current store
	public function getLayouts($data = []) : array {
		$result = [];
		$sql = "
			SELECT 
				*,
				(
					SELECT JSON_OBJECTAGG(t.position, t.codes)
					FROM (
						SELECT 
							lm.position,
							JSON_ARRAYAGG(lm.code) AS codes
						FROM " . DB_PREFIX . "layout_module lm
						WHERE lm.layout_id = l.layout_id
						GROUP BY lm.position
					) t
				) AS modules,
				(SELECT route FROM " . DB_PREFIX . "layout_route lr WHERE lr.layout_id = l.layout_id AND lr.store_id = l.store_id) AS route
			FROM " . DB_PREFIX . "layout l
			WHERE l.`store_id` = '" . (isset($data['store_id']) ? ((int) $data['store_id']) : ((int) $this->session->data['store_id'])) . "'
		";

		$sort_data = array('name');

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY `name`";
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

		foreach ($query->rows as $row) {
			$row['modules'] = json_decode($row['modules'] ?? '[]', true);
			$result[] = $row;
		}

		return $result;
	}

	// Get layout routes
	// No need of store_id, because store_id is now in " . DB_PREFIX . "layout table
	// And every layout is now unique for every store
	public function getLayoutRoutes($layout_id = null) : array {
		if ($layout_id === null) {
			return [];
		}

		$query = $this->db->query("
			SELECT 
				*
			FROM " . DB_PREFIX . "layout_route 
			WHERE `layout_id` = '" . (int) $layout_id . "'
		");

		return $query->row ?? [];
	}


	// Get modules in current layout in admin layout form
	// Because every layout is now unique for every store, no need of store_id filtering here
	public function getLayoutModules($layout_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "layout_module 
			WHERE `layout_id` = '" . (int) $layout_id . "' 
			ORDER BY `position` ASC, `sort_order` ASC
		");

		return $query->rows;
	}

	// Count layouts by store
	public function getTotalLayouts() {
		$query = $this->db->query("
			SELECT 
				COUNT(*) AS total 
			FROM " . DB_PREFIX . "layout
			WHERE `store_id` = '" . (int) $this->session->data['store_id'] . "'
		");

		return $query->row['total'];
	}
}