<?php
class ModelBlogArticle extends Model {

  public function addArticle($data) {

    $this->db->query("START TRANSACTION");

    try {

      // Save main table
      $this->db->query("
        INSERT INTO " . DB_PREFIX . "article_to_store 
        SET
          `store_id` 			 = '" . (int) $this->session->data['store_id'] . "', 
          `date_added`     = NOW(),
          `date_modified`  = NOW()
        ");

      $article_id = $this->db->getLastId();

      // Save descriptions
      foreach ($data['article_description'] as $language_id => $value) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "article_description 
          SET 
            `article_id` 		    = '" . (int) $article_id . "', 
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

      $this->editImages($article_id, $data['article_images']);

      // Save URL
      foreach ($data['seo_url'] ?? [] as $language_id => $keyword) {
        // Mostly safety delete, should never happen
        $this->db->query("
          DELETE FROM " . DB_PREFIX . "seo_url 
          WHERE `query`       = 'article_id=" . $article_id . "'
            AND `language_id` = '" . (int) $language_id . "'
            AND `store_id`    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              `store_id`    = '" . (int) $this->session->data['store_id'] . "',
              `language_id` = '" . (int) $language_id . "', 
              `query`       = 'article_id=" . $article_id . "', 
              `keyword`     = '" . $this->db->escape($keyword) . "'
          ");
        }
      }
      
      $this->db->query("COMMIT");
      return $article_id;
      
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function editArticle($article_id, $data) {
    
    $this->db->query("START TRANSACTION");

    try {

      // Update main table
      $this->db->query("
        UPDATE " . DB_PREFIX . "article_to_store 
        SET
          `date_modified`  = NOW()
        WHERE `article_id` = " . (int) $article_id . "
      ");

      // Update descriptions
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "article_description
        WHERE `article_id` = " . (int) $article_id . "
      ");

      foreach ($data['article_description'] as $language_id => $value) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "article_description 
          SET 
            `article_id` 		    = '" . (int) $article_id . "', 
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

      $this->editImages($article_id, $data['article_images']);


      // Save URL
      foreach ($data['seo_url'] ?? [] as $language_id => $keyword) {
        // Mostly safety delete, should never happen
        $this->db->query("
          DELETE FROM " . DB_PREFIX . "seo_url 
          WHERE `query`       = 'article_id=" . $article_id . "'
            AND `language_id` = '" . (int) $language_id . "'
            AND `store_id`    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              `store_id`    = '" . (int) $this->session->data['store_id'] . "',
              `language_id` = '" . (int) $language_id . "', 
              `query`       = 'article_id=" . $article_id . "', 
              `keyword`     = '" . $this->db->escape($keyword) . "'
          ");
        }
      }
      
      $this->db->query("COMMIT");
      return $article_id;
      
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function editImages($page_id, $image_data = []) : int {

    $page_id = (int) $page_id;
    $store_id = (int) $this->session->data['store_id'];

    $this->db->query("
      DELETE FROM `". DB_PREFIX . "article_image` 
      WHERE `article_id`    = '" . (int) $page_id . "'
        AND store_id        = " . (int) $store_id . "
    ");

    $this->db->query("
      DELETE FROM `". DB_PREFIX . "article_image_description` 
      WHERE `article_id`    = '" . (int) $page_id . "'
        AND store_id        = " . (int) $store_id . "
    ");


    foreach ($image_data as $image) {

      // Check if image actually exists
      if (!empty($image['image'])) {
        $this->db->query("
          INSERT INTO `". DB_PREFIX . "article_image`
          SET 
            `article_id` 	      = '" . (int) $page_id . "', 
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
            INSERT INTO `". DB_PREFIX . "article_image_description`
            SET
              `image_id` 	      = '" . (int) $image_id . "',
              `article_id` 	    = '" . (int) $page_id . "',
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
    $ordering = "ORDER BY " . ($this->sortOrders[$filter['sort']] ?? 'a2s.`date_modified`');
    if (!empty($filter['order']) && in_array($filter['order'], ['ASC', 'DESC'])) {
      $ordering .= " " . $filter['order'];
    }
      
    // Limits
    $limit  = max(1, (int) ($filter['limit'] ?? $this->config->get('config_limit_admin') ?? 20));
    $start  = max(0, (int) ($filter['start'] ?? 0));
    $limits = " LIMIT {$start}, {$limit}";

    $sql = "
      SELECT
        ad.`article_id`,
        ad.`name`,
        a2s.`date_modified`,
        (SELECT ai.`image` FROM " . DB_PREFIX . "article_image ai WHERE ai.`article_id` = a2s.`article_id` AND ai.`store_id` = a2s.`store_id` ORDER BY ai.`sort_order` LIMIT 1) AS `image`,
        (
          SELECT JSON_OBJECT(
            'descriptionLength',    COALESCE(CHAR_LENGTH(ad.`description`), 0),
            'seoDescriptionLength', COALESCE(CHAR_LENGTH(ad.`seo_description`), 0),
            'seoKeywords',          ad.seo_keywords,
            'hasFooter',            CHAR_LENGTH(COALESCE(ad.`footer`, '')) > 2,
            'hasFaq',               CHAR_LENGTH(COALESCE(ad.`faq`, '')) > 2,
            'hasHowTo',             CHAR_LENGTH(COALESCE(ad.`how_to`, '')) > 2
          )
        ) AS seo
      FROM " . DB_PREFIX . "article_description ad
      JOIN " . DB_PREFIX . "article_to_store a2s
        ON a2s.`article_id` = ad.`article_id`
      WHERE ad.`language_id` = {$languageId}
        AND a2s.`store_id`   = {$storeId}
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

  public function getSeoUrl($article_id = null) : array {
    $result   = [];
    $store_id = (int) $this->session->data['store_id'];

    if ($article_id === null) {
      return $result;
    }

    $query = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "seo_url su
      WHERE su.`query`  = 'article_id=" . (int) $article_id . "'
      AND su.`store_id` = " . $store_id . "
    ")->rows;

    foreach ($query as $row) {
      $result[$row['language_id']] = $row['keyword'];
    }

    return $result;
  }

  public function getImages($page_id) : array {
    $result = [];
    $store_id = (int) $this->session->data['store_id'];
    $images = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "article_image
      WHERE `article_id`     = " . (int) $page_id . "
        AND `store_id`         = " . (int) $store_id . "
      ORDER BY `sort_order`
    ")->rows;

    foreach ($images as $row) {
      $descriptions = $this->db->query("
        SELECT
          `language_id`,
          `description`
        FROM " . DB_PREFIX . "article_image_description
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

  public function getArticleDescription($pageId) : array {
    $pageId = (int) $pageId;
    $storeId = (int) $this->session->data['store_id'];
    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "article_description ad
      JOIN " . DB_PREFIX . "article_to_store a2s
        ON a2s.`article_id` = ad.`article_id`
        AND a2s.`store_id`  = {$storeId}
      WHERE ad.`article_id` = {$pageId}
    ";
    
    foreach($this->db->query($sql)->rows ?? [] as $row) {
      $row['footer'] = json_decode($row['footer'] ?? '[]', true);
      $row['faq']    = json_decode($row['faq'] ?? '[]', true);
      $row['how_to'] = json_decode($row['how_to'] ?? '[]', true);
      $result[$row['language_id']] = $row;
    }
    
    return $result ?? [];
  }

  public function getArticleTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "article_description ad
      JOIN " . DB_PREFIX . "article_to_store a2s
        ON a2s.`article_id` = ad.`article_id`
        AND a2s.`store_id` = {$storeId}
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