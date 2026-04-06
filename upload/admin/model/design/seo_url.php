<?php
class ModelDesignSeoUrl extends Model {

	public $requestOrder = [];
	public function __construct($registry) {
		parent::__construct($registry);
		$this->requestOrder = [
			// Catalog
			'category_id',
			'tag',
			'product_id',
			'filter',
			'option',
			'attribute',
			'manufacturer_id',
			'tag_id',
			'supplier_id',
			'is_available',
			'has_discount',
			'is_featured',
			// BLog
			'tag',
			'article',
			// Common
			'sort',
			'page',
		];
	}

	public function getRequestOrder() : array {
		return $this->requestOrder;
	}

	public function addSeoUrl($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$data['store_id'] . "', language_id = '" . (int)$data['language_id'] . "', query = '" . $this->db->escape($data['query']) . "', keyword = '" . $this->db->escape($data['keyword']) . "'");
	}

	public function editSeoUrl($seo_url_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$data['store_id'] . "', language_id = '" . (int)$data['language_id'] . "', query = '" . $this->db->escape($data['query']) . "', keyword = '" . $this->db->escape($data['keyword']) . "' WHERE seo_url_id = '" . (int)$seo_url_id . "'");
	}

	public function deleteSeoUrl($seo_url_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE seo_url_id = '" . (int)$seo_url_id . "'");
	}
	
	public function getSeoUrl($seo_url_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE seo_url_id = '" . (int)$seo_url_id . "'");

		return $query->row;
	}

	public function getSeoUrls($data = array()) {
		$sql = "SELECT *, (SELECT `name` FROM `" . DB_PREFIX . "store` s WHERE s.store_id = su.store_id) AS store, (SELECT `name` FROM `" . DB_PREFIX . "language` l WHERE l.language_id = su.language_id) AS language FROM `" . DB_PREFIX . "seo_url` su";

		$implode = array();

		if (!empty($data['filter_query'])) {
			$implode[] = "`query` LIKE '" . $this->db->escape($data['filter_query']) . "'";
		}
		
		if (!empty($data['filter_keyword'])) {
			$implode[] = "`keyword` LIKE '" . $this->db->escape($data['filter_keyword']) . "'";
		}
		
		if (isset($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$implode[] = "`store_id` = '" . (int)$data['filter_store_id'] . "'";
		}
				
		if (!empty($data['filter_language_id']) && $data['filter_language_id'] !== '') {
			$implode[] = "`language_id` = '" . (int)$data['filter_language_id'] . "'";
		}
		
		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}	
		
		$sort_data = array(
			'query',
			'keyword',
			'language_id',
			'store_id'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY query";
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

	public function getTotalSeoUrls($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "seo_url`";
		
		$implode = array();

		if (!empty($data['filter_query'])) {
			$implode[] = "query LIKE '" . $this->db->escape($data['filter_query']) . "'";
		}
		
		if (!empty($data['filter_keyword'])) {
			$implode[] = "keyword LIKE '" . $this->db->escape($data['filter_keyword']) . "'";
		}
		
		if (!empty($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$implode[] = "store_id = '" . (int)$data['filter_store_id'] . "'";
		}
				
		if (!empty($data['filter_language_id']) && $data['filter_language_id'] !== '') {
			$implode[] = "language_id = '" . (int)$data['filter_language_id'] . "'";
		}
		
		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}		
		
		$query = $this->db->query($sql);

		return $query->row['total'];
	}
	
	public function getSeoUrlsByKeyword($keyword) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE keyword = '" . $this->db->escape($keyword) . "'");

		return $query->rows;
	}	
	
	public function getSeoUrlsByQuery($query) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "'");

		return $query->rows;
	}
	
	public function getSeoUrlsByQueryId($seo_url_id, $query) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "' AND seo_url_id != '" . (int)$seo_url_id . "'");

		return $query->rows;
	}	

	public function getSeoUrlsByKeywordId($seo_url_id, $keyword) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE keyword = '" . $this->db->escape($keyword) . "' AND seo_url_id != '" . (int)$seo_url_id . "'");

		return $query->rows;
	}

	public function checkUrlDuplicate($url, $languageId, $storeId) : array {
		$result 		= [];
		$languageId = (int) $languageId;
		$storeId 		= (int) $storeId;
		$url 				= $this->db->escape(strtolower($url));

		$query = $this->db->query("
			SELECT
				*
			FROM " . DB_PREFIX . "seo_url
			WHERE `keyword` 	= '{$url}'
				AND language_id = {$languageId}
				AND store_id 		= {$storeId}
		")->rows;
		
		foreach ($query as $row) {
			$result[$row['seo_url_id']] = $row;
		}

		return $result;
	}

	public function checkRequestDuplicate($request, $languageId, $storeId) : array {
		$result 		= [];
		$languageId = (int) $languageId;
		$storeId 		= (int) $storeId;
		$request 		= $this->db->escape(strtolower($request));

		$query = $this->db->query("
			SELECT
				*
			FROM " . DB_PREFIX . "seo_url
			WHERE `query` 		= '{$request}'
				AND language_id = {$languageId}
				AND store_id 		= {$storeId}
		")->rows;

		foreach ($query as $row) {
			$result[$row['seo_url_id']] = $row;
		}

		return $result;
	}

	  /**
   * Build canonical query for filter page
   * Sorts filter page request params in the same order, avoiding query dublicates
   * @param array $facets POST data from filter_page_form
   * @return string sorted query
   */
  public function buildQuery(array $facets = []) : string {

    $this->load->model('catalog/facet');
    $this->load->model('design/seo_url');
    // Load allowed orders
    $facetTypes   = $this->model_catalog_facet->getFacetTypes(); // name => type
    $requestOrder = $this->model_design_seo_url->getRequestOrder();

    // Flip type => name
    $typeToName = array_flip($facetTypes);

    // Get values
    $grouped = [];

    foreach ($facets as $facetType => $groups) {
      $facetType = (int)$facetType;

      if (!isset($typeToName[$facetType])) {
        continue;
      }

      $name = $typeToName[$facetType];

      foreach ($groups as $groupId => $values) {
        foreach ($values as $valueId) {
          $grouped[$name][] = (int)$valueId;
        }
      }
    }

    // Unique and sort values
    foreach ($grouped as $name => $values) {
      $values = array_unique($values);
      sort($values, SORT_NUMERIC);
      $grouped[$name] = $values;
    }

    // Order by requestOrder
    $ordered = [];

    foreach ($requestOrder as $key) {
      if (isset($grouped[$key])) {
        $ordered[$key] = $grouped[$key];
        unset($grouped[$key]);
      }
    }

    // In case if some new facet_types are added that are not in requestOrder add those queries to the end
    foreach ($grouped as $key => $values) {
      $ordered[$key] = $values;
    }

    // Assemble query
    $parts = [];

    foreach ($ordered as $key => $values) {
      $parts[] = $key . '=' . implode(',', $values);
    }

    return implode('&', $parts);
  }
}