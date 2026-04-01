<?php
class ModelSettingStore extends Model {
	public function getStores() {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "store ORDER BY url");
		$store_data = $query->rows;
		return $store_data;
	}
}