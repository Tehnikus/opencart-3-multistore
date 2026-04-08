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
          WHERE query       = 'article=" . $article_id . "'
            AND language_id = '" . (int) $language_id . "'
            AND store_id    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              store_id    = '" . (int) $this->session->data['store_id'] . "',
              language_id = '" . (int) $language_id . "', 
              query       = 'article=" . $article_id . "', 
              keyword     = '" . $this->db->escape($keyword) . "'
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

      // Delete previous URL and request
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "seo_url su
        WHERE su.`query` = (
          SELECT
            fp2s.`query`
          FROM " . DB_PREFIX . "article_to_store fp2s
          WHERE fp2s.article_id = " . (int) $article_id . "
            AND fp2s.store_id       = " . (int) $this->session->data['store_id'] . "
        )
        AND su.store_id = " . (int) $this->session->data['store_id'] . "
      ");

      // Update main table
      $this->db->query("
        UPDATE " . DB_PREFIX . "article_to_store 
        SET
          `date_modified`  = NOW()
        WHERE article_id = " . (int) $article_id . "
      ");

      // Update descriptions
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "article_description
        WHERE article_id = " . (int) $article_id . "
      ");

      foreach ($data['article_description'] as $language_id => $value) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "article_description 
          SET 
            `article_id` 		= '" . (int) $article_id . "', 
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
          WHERE query       = 'article=" . $article_id . "'
            AND language_id = '" . (int) $language_id . "'
            AND store_id    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              store_id    = '" . (int) $this->session->data['store_id'] . "',
              language_id = '" . (int) $language_id . "', 
              query       = 'article=" . $article_id . "', 
              keyword     = '" . $this->db->escape($keyword) . "'
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
    $result = [];
    $storeId = (int) $this->session->data['store_id'];

    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "article_description ad
      JOIN " . DB_PREFIX . "article_to_store a2s
        ON a2s.article_id = ad.article_id
        AND a2s.store_id = {$storeId}
    ";

    $this->db->query($sql);

    return $result;
  }

  public function getArticleDescriptions($pageId) : array {
    $pageId = (int) $pageId;
    $storeId = (int) $this->session->data['store_id'];
    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "article_description ad
      JOIN " . DB_PREFIX . "article_to_store a2s
        ON a2s.article_id = ad.article_id
        AND a2s.store_id = {$storeId}
      WHERE ad.article_id = {$pageId}
    ";
    
    return $this->db->query($sql)->rows ?? [];
  }

  public function getArticleTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "article_description ad
      JOIN " . DB_PREFIX . "article_to_store a2s
        ON a2s.article_id = ad.article_id
        AND a2s.store_id = {$storeId}
    ");

    return (int) ($query->row['pages_count'] ?? 0);
  }

}