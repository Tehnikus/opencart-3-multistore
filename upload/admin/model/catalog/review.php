<?php
class ModelCatalogReview extends Model {
	public function addReview($data) {
		$this->db->query("
			INSERT INTO " . DB_PREFIX . "review 
			SET 
				product_id 	= '" . (int) $data['product_id'] . "', 
				language_id = '" . (int) $data['language_id'] . "',
				store_id 		= '" . (int) $data['store_id'] . "',
				status 			= '" . (int) $data['status'] . "', 
				rating 			= '" . (int) $data['rating'] . "', 
				author 			= '" . $this->db->escape($data['author']) . "', 
				text 				= '" . $this->db->escape(strip_tags($data['text'])) . "', 
				date_added 	= '" . $this->db->escape($data['date_added']) . "'
		");

		$this->db->query("
			INSERT INTO " . DB_PREFIX . "facet_sort (product_id, store_id, review_count, rating_avg, date_last_review)
			VALUES ('" . (int) $data['product_id'] . "', '" . (int) $data['store_id'] . "', 1, '" . (int) $data['rating'] . "', NOW())
			ON DUPLICATE KEY UPDATE 
				review_count = (
					SELECT 
						COUNT(*) 
					FROM " . DB_PREFIX . "review r1 
					WHERE r1.product_id = '" . (int) $data['product_id'] . "'
						AND r1.store_id 	= '" . (int) $data['store_id'] . "'
						AND r1.status 		= '1' 
				),
				rating_avg = (
					SELECT 
						AVG(rating) AS total 
					FROM " . DB_PREFIX . "review r1 
					WHERE r1.product_id = '" . (int) $data['product_id'] . "'
						AND r1.store_id 	= '" . (int) $data['store_id'] . "'
						AND r1.status 		= '1' 
				),
				date_last_review = NOW()
		");

		$review_id = $this->db->getLastId();

		// Delete cache
		$this->load->model('catalog/product');
		$this->model_catalog_product->deleteCache($data['product_id'], $data['store_id']);

		return $review_id;
	}

	public function editReview($review_id, $data) {
		$this->db->query("
			UPDATE " . DB_PREFIX . "review 
			SET 
				product_id 	= '" . (int) $data['product_id'] . "', 
				language_id = '" . (int) $data['language_id'] . "',
				store_id 		= '" . (int) $data['store_id'] . "',
				status 			= '" . (int) $data['status'] . "', 
				rating 			= '" . (int) $data['rating'] . "', 
				author 			= '" . $this->db->escape($data['author']) . "', 
				text 				= '" . $this->db->escape(strip_tags($data['text'])) . "', 
				date_added 	= '" . $this->db->escape($data['date_added']) . "',
				date_modified = NOW() 
			WHERE 
				review_id = '" . (int)$review_id . "'
		");

		$this->db->query("
			INSERT INTO " . DB_PREFIX . "facet_sort (product_id, store_id, review_count, rating_avg, date_last_review)
			VALUES ('" . (int) $data['product_id'] . "', '" . (int) $data['store_id'] . "', 1, '" . (int) $data['rating'] . "', NOW())
			ON DUPLICATE KEY UPDATE 
				review_count = (
					SELECT 
						COUNT(*) 
					FROM " . DB_PREFIX . "review r1 
					WHERE r1.product_id = '" . (int) $data['product_id'] . "'
						AND r1.store_id 	= '" . (int) $data['store_id'] . "'
						AND r1.status 		= '1' 
				),
				rating_avg = (
					SELECT 
						AVG(rating) AS total 
					FROM " . DB_PREFIX . "review r1 
					WHERE r1.product_id = '" . (int) $data['product_id'] . "'
						AND r1.store_id 	= '" . (int) $data['store_id'] . "'
						AND r1.status 		= '1' 
				),
				date_last_review = NOW()
		");

		// Delete cache
		$this->load->model('catalog/product');
		$this->model_catalog_product->deleteCache($data['product_id'], $data['store_id']);
	}

	public function deleteReview($review_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "review WHERE review_id = '" . (int)$review_id . "'");

		$this->cache->delete('product');
	}

	public function getReview($review_id) {
		$query = $this->db->query("SELECT DISTINCT *, (SELECT pd.name FROM " . DB_PREFIX . "product_description pd WHERE pd.product_id = r.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS product FROM " . DB_PREFIX . "review r WHERE r.review_id = '" . (int)$review_id . "'");

		return $query->row;
	}

	public function getReviews($data = array()) {
		$sql = "SELECT r.review_id, pd.name, r.author, r.rating, r.status, r.date_added FROM " . DB_PREFIX . "review r LEFT JOIN " . DB_PREFIX . "product_description pd ON (r.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_product'])) {
			$sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_product']) . "%'";
		}

		if (!empty($data['filter_author'])) {
			$sql .= " AND r.author LIKE '" . $this->db->escape($data['filter_author']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND r.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(r.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		$sort_data = array(
			'pd.name',
			'r.author',
			'r.rating',
			'r.status',
			'r.date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY r.date_added";
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

		return $query->rows;
	}

	public function getTotalReviews($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r LEFT JOIN " . DB_PREFIX . "product_description pd ON (r.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_product'])) {
			$sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_product']) . "%'";
		}

		if (!empty($data['filter_author'])) {
			$sql .= " AND r.author LIKE '" . $this->db->escape($data['filter_author']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND r.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(r.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getTotalReviewsAwaitingApproval() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review WHERE status = '0'");

		return $query->row['total'];
	}
}