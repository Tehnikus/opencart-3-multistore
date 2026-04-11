<?php
class ModelSeoTag extends Model {

  private $sortOrders = [
    'name'          => 'td.`name`',
    'date_modified' => 't2s.`date_modified`',
  ];

  public function addTag($data) {

    $this->db->query("START TRANSACTION");

    try {

      // Save main table
      $this->db->query("
        INSERT INTO " . DB_PREFIX . "seo_tag_to_store 
        SET
          `store_id` 			= '" . (int) $this->session->data['store_id'] . "', 
          `inline_style`  = '" . $this->db->escape($data['tag']['inline_style'] ?? '') . "',
          `inline_icon`   = '" . $this->db->escape($data['tag']['inline_icon'] ?? '') . "',
          `show_as_flag`  = '" . ((isset($data['tag']['show_as_flag'])) ? '1' : '0') . "',
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

      foreach ($data['product_tags'] ?? [] as $product_id) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "product_seo_tag
          SET
            `product_id` = '" . (int) $product_id . "',
            `store_id`   = '" . (int) $this->session->data['store_id'] . "',
            `seo_tag_id` = '" . (int) $seo_tag_id . "'
        ");
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
          `inline_style`  = '" . $this->db->escape($data['tag']['inline_style'] ?? '') . "',
          `inline_icon`   = '" . $this->db->escape($data['tag']['inline_icon'] ?? '') . "',
          `show_as_flag`  = '" . ((isset($data['tag']['show_as_flag'])) ? '1' : '0') . "',
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

      $this->db->query("
        DELETE FROM " . DB_PREFIX . "product_seo_tag
          WHERE `seo_tag_id` = '" . (int) $seo_tag_id . "'
            AND `store_id`   = '" . (int) $this->session->data['store_id'] . "'
      ");

      foreach ($data['product_tags'] ?? [] as $product_id) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "product_seo_tag
          SET
            `product_id` = '" . (int) $product_id . "',
            `store_id`   = '" . (int) $this->session->data['store_id'] . "',
            `seo_tag_id` = '" . (int) $seo_tag_id . "'
        ");
      }
      
      $this->db->query("COMMIT");
      return $seo_tag_id;
      
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function deleteTag($seo_tag_id) : bool {
    $tables = [
      'product_seo_tag',
      'seo_tag_to_store',
      'seo_tag_description',
    ];

    $this->db->query("START TRANSACTION");

    try {
      foreach ($tables as $table) {
        $this->db->query("
          DELETE FROM " . DB_PREFIX . $table . "
          WHERE seo_tag_id = '" . (int) $seo_tag_id . "'
            AND store_id   = '" . (int) $this->session->data['store_id'] . "'
        ");
      }

      // Delete SEO URL
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "seo_url su
        WHERE su.`query`    = 'tag_id=" . (int) $seo_tag_id . "'
          AND su.`store_id` = '" . (int) $this->session->data['store_id'] . "'
      ");

      $this->db->query("COMMIT");
      return true;

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
    $sortField = 't2s.`date_modified`';

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
        t2s.*,
        td.`seo_tag_id`,
        td.`name`,
        (SELECT COUNT(*) FROM " . DB_PREFIX . "product_seo_tag pt WHERE pt.`seo_tag_id` = t2s.`seo_tag_id` AND pt.`store_id` = t2s.`store_id`) AS product_count
      FROM " . DB_PREFIX . "seo_tag_description td
      JOIN " . DB_PREFIX . "seo_tag_to_store t2s
        ON t2s.`seo_tag_id` = td.`seo_tag_id`
      WHERE td.`language_id` = {$languageId}
        AND t2s.`store_id`   = {$storeId}
        " . ((isset($filter['name'])) ? "AND td.`name` LIKE '%" . $this->db->escape($filter['name']) . "%'" : '') . "
      {$ordering}
      {$limits}
    ";

    foreach($this->db->query($sql)->rows ?? [] as $row) {
      $result[] = $row;
    }

    return $result;
  }

  public function getSeoUrl($seo_tag_id) : array {
    $result   = [];
    $store_id = (int) $this->session->data['store_id'];

    if ($seo_tag_id === null) {
      return $result;
    }

    $query = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "seo_url su
      WHERE su.`query`    = 'tag_id=" . (int) $seo_tag_id . "'
        AND su.`store_id` = " . $store_id . "
    ")->rows;

    foreach ($query as $row) {
      $result[$row['language_id']] = $row['keyword'];
    }

    return $result;
  }

  public function getTagData($seo_tag_id) : array {

    if ($seo_tag_id === null) {
      return [];
    }

    $query = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "seo_tag_to_store
      WHERE `seo_tag_id` = " . (int) $seo_tag_id . "
        AND `store_id`   = " . (int) $this->session->data['store_id'] . "
    ");
    
    return $query->row ?? [];
  }

  public function getTagDescription($seo_tag_id) : array {
    
    if ($seo_tag_id === null) {
      return [];
    }

    $query = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "seo_tag_description
      WHERE `seo_tag_id`  = " . (int) $seo_tag_id . "
        AND `store_id`    = " . (int) $this->session->data['store_id'] . "
    ");
    
    foreach($query->rows ?? [] as $row) {
      $result[$row['language_id']] = $row;
    }

    return $result ?? [];
  }

  public function getProductTags($seo_tag_id) : array {
    
    if ($seo_tag_id === null) {
      return [];
    }

    $query = $this->db->query("
      SELECT
        product_id
      FROM " . DB_PREFIX . "product_seo_tag
      WHERE `seo_tag_id`  = " . (int) $seo_tag_id . "
        AND `store_id`    = " . (int) $this->session->data['store_id'] . "
    ");

    return $query->rows ?? [];
  }

  public function getUsedIcons() : array {
    
    $query = $this->db->query("
      SELECT
        DISTINCT `inline_icon`
      FROM " . DB_PREFIX . "seo_tag_to_store
      WHERE `inline_icon` <> ''
    ");

    return $query->rows ?? [];
  }
  
  public function getUsedStyles() : array {

    $query = $this->db->query("
      SELECT
        DISTINCT `inline_style`
      FROM " . DB_PREFIX . "seo_tag_to_store
      WHERE `inline_style` <> ''
    ");

    return $query->rows ?? [];
  }

  public function getTagTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "seo_tag_description td
      JOIN " . DB_PREFIX . "seo_tag_to_store t2s
        ON t2s.`seo_tag_id` = td.`seo_tag_id`
        AND t2s.`store_id`  = {$storeId}
    ");

    return (int) ($query->row['pages_count'] ?? 0);
  }
}