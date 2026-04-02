<?php
class ModelCatalogInformation extends Model {
	public function getInformation($information_id) {
		$query = $this->db->query("
			SELECT 
				i.`information_id`,
				id.`title`,
				id.`description`,
				id.`meta_title`,
				id.`meta_description`,
				id.`meta_keyword`,
				id.`date_modified`,
				i2s.`sort_order`,
				i2s.`bottom`,
				i2s.`status`
			FROM " . DB_PREFIX . "information_to_store i2s 
			JOIN " . DB_PREFIX . "information_description id 
				ON  id.`information_id` = i2s.`information_id`
				AND id.`language_id` 	  = '" . (int) $this->config->get('config_language_id') . "' 
				AND id.`store_id` 			= i2s.`store_id`
			JOIN " . DB_PREFIX . "information i
				ON i.`information_id` = i2s.`information_id` 
			WHERE i2s.`information_id` = '" . (int) $information_id . "' 
				AND i2s.`store_id` 			 = '" . (int) $this->config->get('config_store_id') . "' 
				AND i2s.`status` 				 = '1'
		");

		return $query->row;
	}

	public function getInformations() {
		$query = $this->db->query("
			SELECT 
				i.`information_id`,
				id.`title`,
				id.`date_modified`,
				i2s.`sort_order`,
				i2s.`bottom`,
				i2s.`status`
			FROM " . DB_PREFIX . "information_to_store i2s
			JOIN " . DB_PREFIX . "information i
				ON i.`information_id` = i2s.`information_id`
			JOIN " . DB_PREFIX . "information_description id
				ON id.`information_id` = i2s.`information_id`
				AND id.`language_id` 	 = '" . (int) $this->config->get('config_language_id') . "'
			WHERE i2s.`store_id` = '" . (int)$this->config->get('config_store_id') . "' 
				AND i2s.`status` 	 = '1' 
			ORDER BY i2s.sort_order, LCASE(id.title) ASC
		");

		return $query->rows;
	}

	public function getInformationLayoutId($information_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_to_layout WHERE information_id = '" . (int)$information_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}
}