<?php
class ModelSettingModule extends Model {
	// Save module settings for modules that can have multiple instances
	// Should always save module setting in relation to store_id
	public function addModule($code, $data) {
		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "module` 
			SET 
				`name` 			= '" . $this->db->escape($data['name']) . "', 
				`code` 			= '" . $this->db->escape($code) . "', 
				`setting` 	= '" . $this->db->escape(json_encode($data)) . "',
				`store_id` 	= '" . (int) $this->session->data['store_id'] . "'
		");
	}

	// Edit module settings for modules that can have multiple instances
	// Should always edit module setting in relation to store_id
	public function editModule($module_id, $data) {
		$this->db->query("
			UPDATE `" . DB_PREFIX . "module` 
			SET 
				`name` 			= '" . $this->db->escape($data['name']) . "', 
				`setting` 	= '" . $this->db->escape(json_encode($data)) . "'
			WHERE `module_id` = '" . (int) $module_id . "'
				AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "'
		");
	}

	// Delete module setting for single module instance for modules that can have multiple instances
	// Should always delete module setting in relation to store_id
	public function deleteModule($module_id) {
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "module` 
			WHERE `module_id` = '" . (int) $module_id . "'
				AND `store_id`  = '" . (int) $this->session->data['store_id'] . "'
		");
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "layout_module` 
			WHERE `code` LIKE '%." . (int) $module_id . "'
				AND `store_id`  = '" . (int) $this->session->data['store_id'] . "'
		");
	}

	// Get module settings for modules that can have multiple instances and decode json 
	// Should always get module setting in relation to store_id	
	public function getModule($module_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM `" . DB_PREFIX . "module` 
			WHERE `module_id` = '" . (int) $module_id . "'
				AND `store_id`  = '" . (int) $this->session->data['store_id'] . "'
		");

		if ($query->row) {
			return json_decode($query->row['setting'], true);
		} else {
			return array();
		}
	}
	
	// Not used?
	public function getModules() {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "module` ORDER BY `code`");

		return $query->rows;
	}

	// Get module settings by module code for modules that can have multiple instances, e.g. 'featured' => [' array of several settings' ]
	// Should always get module setting in relation to store_id	
	public function getModulesByCode($code) {
		$sql = "
			SELECT 
				* 
			FROM `" . DB_PREFIX . "module` 
			WHERE `code` 			= '" . $this->db->escape($code) . "' 
				AND `store_id` 	= '" . $this->session->data['store_id'] . "'
			ORDER BY `name`
		";

		$query = $this->db->query($sql);

		return $query->rows;
	}	
	
	// Delete all module setting by code
	public function deleteModulesByCode($code) {
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "module` 
			WHERE `code` = '" . $this->db->escape($code) . "'
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		");
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "layout_module` 
			WHERE (`code` LIKE '" . $this->db->escape($code) . "' OR `code` LIKE '" . $this->db->escape($code . '.%') . "')
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		");
	}	
}