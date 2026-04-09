<?php
class ModelBlogTag extends Model {

  private $sortOrders = [
    'name'          => 'bd.`name`',
    'date_modified' => 'b2s.`date_modified`',
  ];

  public function addTag($data) {

    $this->db->query("START TRANSACTION");

    try {

      // Save main table
      $this->db->query("
        INSERT INTO " . DB_PREFIX . "blog_tag_to_store 
        SET
          `store_id` 			 = '" . (int) $this->session->data['store_id'] . "', 
          `date_added`     = NOW(),
          `date_modified`  = NOW()
        ");

      $blog_tag_id = $this->db->getLastId();

      // Save descriptions
      foreach ($data['blog_tag_description'] as $language_id => $value) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "blog_tag_description 
          SET 
            `blog_tag_id` 		  = '" . (int) $blog_tag_id . "', 
            `language_id` 			= '" . (int) $language_id . "', 
            `store_id` 					= '" . (int) $this->session->data['store_id'] . "',
            `name` 							= '" . $this->db->escape($value['name']) . "', 
            `h1`                = '" . $this->db->escape($value['h1']) . "', 
            `description` 			= '" . $this->db->escape($value['description']) . "', 
            `meta_title` 				= '" . $this->db->escape($value['meta_title']) . "', 
            `meta_description` 	= '" . $this->db->escape($value['meta_description']) . "', 
            `meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "',
            `seo_description` 	= '" . $this->db->escape($value['seo_description']) . "', 
            `footer`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['footer'] ?? []), JSON_UNESCAPED_UNICODE)) . "',
            `faq`               = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['faq'] ?? [], ['@type', '@context']), JSON_UNESCAPED_UNICODE)) . "',
            `how_to`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['how_to'] ?? [], ['@type', '@context']), JSON_UNESCAPED_UNICODE)) . "'
        ");
      }

      $this->editImages($blog_tag_id, $data['blog_tag_images']);

      // Save URL
      foreach ($data['seo_url'] ?? [] as $language_id => $keyword) {
        // Mostly safety delete, should never happen
        $this->db->query("
          DELETE FROM " . DB_PREFIX . "seo_url 
          WHERE `query`       = 'blog_tag_id=" . $blog_tag_id . "'
            AND `language_id` = '" . (int) $language_id . "'
            AND `store_id`    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              `store_id`    = '" . (int) $this->session->data['store_id'] . "',
              `language_id` = '" . (int) $language_id . "', 
              `query`       = 'blog_tag_id=" . $blog_tag_id . "', 
              `keyword`     = '" . $this->db->escape($keyword) . "'
          ");
        }
      }
      
      $this->db->query("COMMIT");
      return $blog_tag_id;
      
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function editTag($blog_tag_id, $data) {
    
    $this->db->query("START TRANSACTION");

    try {

      // Update main table
      $this->db->query("
        UPDATE " . DB_PREFIX . "blog_tag_to_store 
        SET
          `date_modified`  = NOW()
        WHERE blog_tag_id = " . (int) $blog_tag_id . "
      ");

      // Update descriptions
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "blog_tag_description
        WHERE `blog_tag_id` = " . (int) $blog_tag_id . "
      ");

      foreach ($data['blog_tag_description'] as $language_id => $value) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "blog_tag_description 
          SET 
            `blog_tag_id` 		  = '" . (int) $blog_tag_id . "', 
            `language_id` 			= '" . (int) $language_id . "', 
            `store_id` 					= '" . (int) $this->session->data['store_id'] . "',
            `name` 							= '" . $this->db->escape($value['name']) . "', 
            `h1`                = '" . $this->db->escape($value['h1']) . "', 
            `description` 			= '" . $this->db->escape($value['description']) . "', 
            `meta_title` 				= '" . $this->db->escape($value['meta_title']) . "', 
            `meta_description` 	= '" . $this->db->escape($value['meta_description']) . "', 
            `meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "',
            `seo_description` 	= '" . $this->db->escape($value['seo_description']) . "', 
            `footer`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['footer'] ?? []), JSON_UNESCAPED_UNICODE)) . "',
            `faq`               = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['faq'] ?? [], ['@type', '@context']), JSON_UNESCAPED_UNICODE)) . "',
            `how_to`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['how_to'] ?? [], ['@type', '@context']), JSON_UNESCAPED_UNICODE)) . "'
        ");
      }

      $this->editImages($blog_tag_id, $data['blog_tag_images']);


      // Save URL
      foreach ($data['seo_url'] ?? [] as $language_id => $keyword) {
        // Mostly safety delete, should never happen
        $this->db->query("
          DELETE FROM " . DB_PREFIX . "seo_url 
          WHERE `query`       = 'blog_tag_id=" . $blog_tag_id . "'
            AND `language_id` = '" . (int) $language_id . "'
            AND `store_id`    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              `store_id`    = '" . (int) $this->session->data['store_id'] . "',
              `language_id` = '" . (int) $language_id . "', 
              `query`       = 'blog_tag_id=" . $blog_tag_id . "', 
              `keyword`     = '" . $this->db->escape($keyword) . "'
          ");
        }
      }
      
      $this->db->query("COMMIT");
      return $blog_tag_id;
      
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function editImages($page_id, $image_data = []) : int {

    $page_id = (int) $page_id;
    $store_id = (int) $this->session->data['store_id'];

    $this->db->query("
      DELETE FROM `". DB_PREFIX . "blog_tag_image` 
      WHERE `blog_tag_id`   = '" . (int) $page_id . "'
        AND `store_id`        = " . (int) $store_id . "
    ");

    $this->db->query("
      DELETE FROM `". DB_PREFIX . "blog_tag_image_description` 
      WHERE `blog_tag_id`   = '" . (int) $page_id . "'
        AND `store_id`        = " . (int) $store_id . "
    ");


    foreach ($image_data as $image) {

      // Check if image actually exists
      if (!empty($image['image'])) {
        $this->db->query("
          INSERT INTO `". DB_PREFIX . "blog_tag_image`
          SET 
            `blog_tag_id` 	    = '" . (int) $page_id . "', 
            `image` 				    = '" . $this->db->escape($image['image']) . "', 
            `sort_order` 		    = '" . (int) $image['sort_order'] . "',
            `store_id`          = '" . $store_id . "'
        ");

        // Add multilang multistore image descriptions
        $image_id = $this->db->getLastId();

        foreach ($image['description'] as $language_id => $image_description) {
          if (!$image_description) {
            continue;
          }
          $this->db->query("
            INSERT INTO `". DB_PREFIX . "blog_tag_image_description`
            SET
              `image_id` 	      = '" . (int) $image_id . "',
              `blog_tag_id` 	  = '" . (int) $page_id . "',
              `language_id` 		= '" . (int) $language_id . "',
              `store_id` 				= '" . (int) $store_id . "',
              `description` 		= '" . $this->db->escape($image_description) ."'
          ");
        }
      }
    }

    return $this->db->countAffected();
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
        bd.`blog_tag_id`,
        bd.`name`,
        b2s.`date_modified`,
        (SELECT bi.`image` FROM " . DB_PREFIX . "blog_tag_image bi WHERE bi.`blog_tag_id` = b2s.`blog_tag_id` AND bi.`store_id` = b2s.`store_id` ORDER BY bi.`sort_order` LIMIT 1) AS `image`,
        (
          SELECT JSON_OBJECT(
            'descriptionLength',    COALESCE(CHAR_LENGTH(bd.`description`), 0),
            'seoDescriptionLength', COALESCE(CHAR_LENGTH(bd.`seo_description`), 0),
            'seoKeywords',          bd.`seo_keywords`,
            'hasFooter',            CHAR_LENGTH(COALESCE(bd.`footer`, '')) > 2,
            'hasFaq',               CHAR_LENGTH(COALESCE(bd.`faq`, '')) > 2,
            'hasHowTo',             CHAR_LENGTH(COALESCE(bd.`how_to`, '')) > 2
          )
        ) AS seo
      FROM " . DB_PREFIX . "blog_tag_description bd
      JOIN " . DB_PREFIX . "blog_tag_to_store b2s
        ON b2s.`blog_tag_id` = bd.`blog_tag_id`
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

  public function getSeoUrl($blog_tag_id = null) : array {
    $result   = [];
    $store_id = (int) $this->session->data['store_id'];

    if ($blog_tag_id === null) {
      return $result;
    }

    $query = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "seo_url su
      WHERE su.`query`    = 'blog_tag_id=" . (int) $blog_tag_id . "'
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
      FROM " . DB_PREFIX . "blog_tag_description bd
      JOIN " . DB_PREFIX . "blog_tag_to_store b2s
        ON b2s.`blog_tag_id` = bd.`blog_tag_id`
        AND b2s.store_id = {$storeId}
      WHERE bd.`blog_tag_id` = {$pageId}
    ";
    
    foreach($this->db->query($sql)->rows ?? [] as $row) {
      $row['footer'] = json_decode($row['footer'] ?? '[]', true);
      $row['faq']    = json_decode($row['faq'] ?? '[]', true);
      $row['how_to'] = json_decode($row['how_to'] ?? '[]', true);
      $result[$row['language_id']] = $row;
    }
    
    return $result ?? [];
  }

  public function getImages($page_id) : array {
    $result = [];
    $store_id = (int) $this->session->data['store_id'];
    $images = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "blog_tag_image
      WHERE `blog_tag_id` = " . (int) $page_id . "
        AND `store_id`         = " . (int) $store_id . "
      ORDER BY `sort_order`
    ")->rows;

    foreach ($images as $row) {
      $descriptions = $this->db->query("
        SELECT
          `language_id`,
          `description`
        FROM " . DB_PREFIX . "blog_tag_image_description
        WHERE `image_id` = " . (int) $row['image_id'] . "
          AND `store_id` = " . (int) $store_id . "
      ")->rows;
      foreach ($descriptions as $description) {
        $row['description'][$description['language_id']] = $description['description'];
      }
      $result[] = $row;
    }

    return $result;
  }

  public function getTagTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "blog_tag_description bd
      JOIN " . DB_PREFIX . "blog_tag_to_store b2s
        ON b2s.`blog_tag_id` = bd.`blog_tag_id`
        AND b2s.`store_id`  = {$storeId}
    ");

    return (int) ($query->row['pages_count'] ?? 0);
  }

  
  /**
   * Filter array recursively and remove empty key => value pairs
   * @param array $array The array to be affected
   * @param array $deletedKeys The array of keys that will be treated as empty if all other keys are empty on this level 
   * @return array
   */
  public function filterArrayRecursively(array $array = [], array $deletedKeys = []): array {
    $filtered = [];

    foreach ($array as $key => $value) {
      if (is_array($value)) {
        $value = $this->filterArrayRecursively($value, $deletedKeys);
        if (!empty($value)) {
          $filtered[$key] = $value;
        }
      // $value !== '&lt;p&gt;&lt;br&gt;&lt;/p&gt;' is a workaround for empty Summernote editor which always places this string: '<p><br></p>'
      } elseif (trim((string) $value) !== '' && $value !== '&lt;p&gt;&lt;br&gt;&lt;/p&gt;') {
        $filtered[$key] = $value;
      }
    }

    // If result array is not empty, but only includes deletedKeys - then clear them also
    if (!empty($filtered)) {
      $nonDeletedKeys = array_diff(array_keys($filtered), $deletedKeys);

      // Check recursively
      $hasMeaningfulData = !empty($nonDeletedKeys);
      foreach ($filtered as $key => $value) {
        if (is_array($value) && !empty($value)) {
          $hasMeaningfulData = true;
          break;
        }
      }

      if (!$hasMeaningfulData) {
        return [];
      }
    }

    return $filtered;
  }
}