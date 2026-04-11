<?php
class ModelSeoTag extends Model {

  private $sortOrders = [
    'name'          => 'bd.`name`',
    'date_modified' => 'b2s.`date_modified`',
  ];

  public function addTag($data) {

    $this->db->query("START TRANSACTION");

    try {

      // Save main table
      $this->db->query("
        INSERT INTO " . DB_PREFIX . "seo_tag_to_store 
        SET
          `store_id` 			= '" . (int) $this->session->data['store_id'] . "', 
          `inline_style`  = '" . $this->db->escape($data['inline_style']) . "',
          `inline_icon`   = '" . $this->db->escape($data['inline_icon']) . "',
          `show_as_flag`  = '" . ((isset($data['show_as_flag'])) ? '1' : '0') . "',
          `date_added`    = NOW(),
          `date_modified` = NOW()
        ");

      $seo_tag_id = $this->db->getLastId();

      // Save descriptions
      foreach ($data['seo_tag_description'] as $language_id => $value) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "seo_tag_description 
          SET 
            `seo_tag_id` 	= '" . (int) $seo_tag_id . "', 
            `language_id` = '" . (int) $language_id . "', 
            `store_id` 		= '" . (int) $this->session->data['store_id'] . "',
            `name` 				= '" . $this->db->escape($value['name']) . "' 
        ");
      }

      // Save URL
      foreach ($data['seo_url'] ?? [] as $language_id => $keyword) {
        // Mostly safety delete, should never happen
        $this->db->query("
          DELETE FROM " . DB_PREFIX . "seo_url 
          WHERE `query`       = 'tag_id=" . $seo_tag_id . "'
            AND `language_id` = '" . (int) $language_id . "'
            AND `store_id`    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              `store_id`    = '" . (int) $this->session->data['store_id'] . "',
              `language_id` = '" . (int) $language_id . "', 
              `query`       = 'tag_id=" . $seo_tag_id . "', 
              `keyword`     = '" . $this->db->escape($keyword) . "'
          ");
        }
      }
      
      $this->db->query("COMMIT");
      return $seo_tag_id;
      
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function editTag($seo_tag_id, $data) {
    
    $this->db->query("START TRANSACTION");

    try {

      // Update main table
      $this->db->query("
        UPDATE " . DB_PREFIX . "seo_tag_to_store 
        SET
          `inline_style`  = '" . $this->db->escape($data['inline_style']) . "',
          `inline_icon`   = '" . $this->db->escape($data['inline_icon']) . "',
          `show_as_flag`  = '" . ((isset($data['show_as_flag'])) ? '1' : '0') . "',
          `date_modified` = NOW()
        WHERE `seo_tag_id` = " . (int) $seo_tag_id . "
          AND `store_id` 	 = '" . (int) $this->session->data['store_id'] . "'
      ");

      // Update descriptions
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "seo_tag_description
        WHERE `seo_tag_id`  = " . (int) $seo_tag_id . "
          AND `store_id`    = '" . (int) $this->session->data['store_id'] . "'
      ");

      foreach ($data['seo_tag_description'] as $language_id => $value) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "seo_tag_description 
          SET 
            `seo_tag_id` 		  = '" . (int) $seo_tag_id . "', 
            `language_id` 			= '" . (int) $language_id . "', 
            `store_id` 					= '" . (int) $this->session->data['store_id'] . "',
            `name` 							= '" . $this->db->escape($value['name']) . "'
        ");
      }

      // Save URL
      foreach ($data['seo_url'] ?? [] as $language_id => $keyword) {
        // Mostly safety delete, should never happen
        $this->db->query("
          DELETE FROM " . DB_PREFIX . "seo_url 
          WHERE `query`       = 'seo_tag_id=" . $seo_tag_id . "'
            AND `language_id` = '" . (int) $language_id . "'
            AND `store_id`    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              `store_id`    = '" . (int) $this->session->data['store_id'] . "',
              `language_id` = '" . (int) $language_id . "', 
              `query`       = 'seo_tag_id=" . $seo_tag_id . "', 
              `keyword`     = '" . $this->db->escape($keyword) . "'
          ");
        }
      }
      
      $this->db->query("COMMIT");
      return $seo_tag_id;
      
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function getList($filter) : array {
    $result     = [];
    $storeId    = (int) $this->session->data['store_id'];
    $languageId = (int) $this->config->get('config_language_id');
    
    // Orders
    $ordering = '';
    $sortField = 'b2s.`date_modified`';

    if (!empty($filter['sort']) && isset($this->sortOrders[$filter['sort']])) {
      $sortField = $this->sortOrders[$filter['sort']];
    }

    $orderDirection = 'DESC';
    if (!empty($filter['order']) && in_array($filter['order'], ['ASC', 'DESC'])) {
      $orderDirection = $filter['order'];
    }

    $ordering = "ORDER BY {$sortField} {$orderDirection}";
      
    // Limits
    $limit  = max(1, (int) ($filter['limit'] ?? $this->config->get('config_limit_admin') ?? 20));
    $start  = max(0, (int) ($filter['start'] ?? 0));
    $limits = " LIMIT {$start}, {$limit}";

    $sql = "
      SELECT
        bd.`seo_tag_id`,
        bd.`name`,
        b2s.`date_modified`
      FROM " . DB_PREFIX . "seo_tag_description bd
      JOIN " . DB_PREFIX . "seo_tag_to_store b2s
        ON b2s.`seo_tag_id` = bd.`seo_tag_id`
      WHERE bd.`language_id` = {$languageId}
        AND b2s.`store_id`   = {$storeId}
        " . ((isset($filter['name'])) ? "AND bd.name LIKE '%" . $this->db->escape($filter['name']) . "%'" : '') . "
      {$ordering}
      {$limits}
    ";

    foreach($this->db->query($sql)->rows ?? [] as $row) {
      $row['seo'] = json_decode($row['seo'], true);
      $row['seo']['seoKeywords'] = count(array_filter(explode(',', $row['seo']['seoKeywords'])));
      $row['image'] = $row['image'] ? (HTTPS_CATALOG . 'image/' . $row['image']) : (HTTPS_CATALOG . 'image/no_image.webp');

      $result[] = $row;
    }

    return $result;
  }

  public function getSeoUrl($seo_tag_id = null) : array {
    $result   = [];
    $store_id = (int) $this->session->data['store_id'];

    if ($seo_tag_id === null) {
      return $result;
    }

    $query = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "seo_url su
      WHERE su.`query`    = 'seo_tag_id=" . (int) $seo_tag_id . "'
        AND su.`store_id` = " . $store_id . "
    ")->rows;

    foreach ($query as $row) {
      $result[$row['language_id']] = $row['keyword'];
    }

    return $result;
  }

  public function getTagDescription($pageId) : array {
    $pageId = (int) $pageId;
    $storeId = (int) $this->session->data['store_id'];
    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "seo_tag_description bd
      JOIN " . DB_PREFIX . "seo_tag_to_store b2s
        ON b2s.`seo_tag_id` = bd.`seo_tag_id`
        AND b2s.store_id = {$storeId}
      WHERE bd.`seo_tag_id` = {$pageId}
    ";
    
    foreach($this->db->query($sql)->rows ?? [] as $row) {
      $row['footer'] = json_decode($row['footer'] ?? '[]', true);
      $row['faq']    = json_decode($row['faq'] ?? '[]', true);
      $row['how_to'] = json_decode($row['how_to'] ?? '[]', true);
      $result[$row['language_id']] = $row;
    }
    
    return $result ?? [];
  }

  public function getTagTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "seo_tag_description bd
      JOIN " . DB_PREFIX . "seo_tag_to_store b2s
        ON b2s.`seo_tag_id` = bd.`seo_tag_id`
        AND b2s.`store_id`  = {$storeId}
    ");

    return (int) ($query->row['pages_count'] ?? 0);
  }
}