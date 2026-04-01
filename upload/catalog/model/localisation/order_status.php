<?php
class ModelLocalisationOrderStatus extends Model
{
	public function getOrderStatus($order_status_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE order_status_id = '" . (int) $order_status_id . "' AND language_id = '" . (int) $this->config->get('config_language_id') . "'");
		return $query->row;
	}

	public function getOrderStatuses() {
		$query = $this->db->query("SELECT order_status_id, name FROM " . DB_PREFIX . "order_status WHERE language_id = '" . (int) $this->config->get('config_language_id') . "' ORDER BY name");
		$order_status_data = $query->rows;
		return $order_status_data;
	}
}