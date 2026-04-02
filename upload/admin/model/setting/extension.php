<?php
class ModelSettingExtension extends Model {	
	// Get installed extensions by code and store
	// Should always rely on store_id to display separate extensions list for different stores
	public function getInstalled($type, $store_id = null) {

		if ($store_id === null) {
			$store_id = (int) $this->session->data['store_id'];
		}

		$extension_data = array();

		$query = $this->db->query("
			SELECT 
				* 
			FROM `" . DB_PREFIX . "extension` 
			WHERE `type` 			= '" . $this->db->escape($type) . "' 
				AND `store_id` 	= '" . (int) $store_id . "'
			ORDER BY `code`
		");

		foreach ($query->rows as $result) {
			$extension_data[] = $result['code'];
		}

		return $extension_data;
	}

	// Install extension
	// Should always rely on store_id to separate extensions of different stores
	public function install($type, $code, $store_id = null) {
		if ($store_id === null) {
			$store_id = (int) $this->session->data['store_id'];
		}
		$extensions = $this->getInstalled($type, $store_id);

		if (!in_array($code, $extensions)) {
			$this->db->query("
				INSERT INTO `" . DB_PREFIX . "extension` 
				SET 
					`type` 			= '" . $this->db->escape($type) . "', 
					`code` 			= '" . $this->db->escape($code) . "',
					`store_id` 	= '" . (int) $store_id . "'
			");
		}
	}
	
	// Install extension
	// Should always rely on store_id to separate extensions of different stores
	public function uninstall($type, $code) {
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "extension` 
			WHERE `type` = '" . $this->db->escape($type) . "' 
				AND `code` = '" . $this->db->escape($code) . "'
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		");
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "setting` 
			WHERE `code` = '" . $this->db->escape($type . '_' . $code) . "'
				AND `store_id` = '" . (int) $this->session->data['store_id'] . "'
		");
	}	

	public function addExtensionInstall($filename, $extension_download_id = 0) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "extension_install` SET `filename` = '" . $this->db->escape($filename) . "', `extension_download_id` = '" . (int)$extension_download_id . "', `date_added` = NOW()");
	
		return $this->db->getLastId();
	}
	
	public function deleteExtensionInstall($extension_install_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "extension_install` WHERE `extension_install_id` = '" . (int)$extension_install_id . "'");
	}

	public function getExtensionInstalls($start = 0, $limit = 10) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}		
		
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "extension_install` ORDER BY date_added ASC LIMIT " . (int)$start . "," . (int)$limit);
	
		return $query->rows;
	}

	public function getExtensionInstallByExtensionDownloadId($extension_download_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "extension_install` WHERE `extension_download_id` = '" . (int)$extension_download_id . "'");

		return $query->row;
	}
		
	public function getTotalExtensionInstalls() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "extension_install`");

		return $query->row['total'];
	}
		
	public function addExtensionPath($extension_install_id, $path) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "extension_path` SET `extension_install_id` = '" . (int)$extension_install_id . "', `path` = '" . $this->db->escape($path) . "', `date_added` = NOW()");
	}
		
	public function deleteExtensionPath($extension_path_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "extension_path` WHERE `extension_path_id` = '" . (int)$extension_path_id . "'");
	}
	
	public function getExtensionPathsByExtensionInstallId($extension_install_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "extension_path` WHERE `extension_install_id` = '" . (int)$extension_install_id . "' ORDER BY `date_added` ASC");

		return $query->rows;
	}
}