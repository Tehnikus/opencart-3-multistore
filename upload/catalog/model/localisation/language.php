<?php
class ModelLocalisationLanguage extends Model {
	public function getLanguage($language_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "language l
			INNER JOIN " . DB_PREFIX . "language_to_store l2s
				ON l.`language_id` = l2s.language_id
				AND l2s.`store_id` = '" . (int) $this->config->get('config_store_id') . "'
			WHERE l.`language_id` = '" . (int)$language_id . "'
		");

		return $query->row;
	}

	public function getLanguages() {

		$language_data = [];

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "language l
			INNER JOIN " . DB_PREFIX . "language_to_store l2s
				ON l.`language_id` = l2s.`language_id`
				AND l2s.`store_id` = '" . (int) $this->config->get('config_store_id') . "'
			WHERE l.`status` = '1' 
			ORDER BY `sort_order`, `name`
		");

		foreach ($query->rows as $result) {
			$language_data[$result['code']] = array(
				'language_id' => $result['language_id'],
				'name'        => $result['name'],
				'code'        => $result['code'],
				'locale'      => $result['locale'],
				'image'       => $result['image'],
				'directory'   => $result['directory'],
				'sort_order'  => $result['sort_order'],
				'status'      => $result['status']
			);
		}

		return $language_data;
	}
}