<?php
class ModelSeoKeyword extends Model
{

  public function getKeywords($filter_data = []) {
    $where = [];
    if (!empty($filter_data['keyword_id'])) {
      if (is_array($filter_data['keyword_id'])) {
        $ids = array_map('intval', $filter_data['keyword_id']);
        $where[] = "k.keyword_id IN (" . implode(',', $ids) . ")";
      } else {
        $where[] = "k.keyword_id = '" . (int) $filter_data['keyword_id'] . "'";
      }
    }
    if (!empty($filter_data['keyword_text'])) {
      $where[] = "k.keyword_text LIKE '%" . $this->db->escape($filter_data['keyword_text']) . "%'";
    }
    if (!empty($filter_data['keyword_url'])) {
      $where[] = "k.keyword_url LIKE '%" . $this->db->escape($filter_data['keyword_url']) . "%'";
    }
    if (!empty($filter_data['language_id'])) {
      $where[] = "k.language_id = '" . (int) $filter_data['language_id'] . "'";
    }
    if (!empty($filter_data['store_id'])) {
      $where[] = "k.store_id = '" . (int) $filter_data['store_id'] . "'";
    }

    $sql = "
      SELECT 
        * 
      FROM " . DB_PREFIX . "seo_keyword k 
    ";

    if (!empty($where)) {
      $sql .= " WHERE " . implode(' AND ', $where);
    }

    // LIMIT и OFFSET
    $start = isset($filter_data['start']) ? (int) $filter_data['start'] : 0;
    $limit = isset($filter_data['limit']) ? (int) $filter_data['limit'] : 0;

    if ($limit > 0) {
      $sql .= " LIMIT " . $start . ", " . $limit;
    }

    $query = $this->db->query($sql);
    return $query->rows;
  }

  public function saveData(array $rows): bool {
    if (empty($rows)) {
      return false;
    }

    // Column names
    $columns = ['keyword_id', 'keyword_text', 'keyword_url', 'keyword_group_id', 'language_id', 'store_id'];

    // Escape values
    $escapedRows = [];

    foreach ($rows as $row) {
      $escaped = [];
    
      foreach ($columns as $col) {
        $value = $row[$col] ?? null;
    
        if (is_null($value)) {
          $escaped[] = "NULL";
        } elseif (is_numeric($value)) {
          $escaped[] = (string)(int)$value;
        } else {
          $escaped[] = "'" . $this->db->escape($value) . "'";
        }
      }
    
      $escapedRows[] = "(" . implode(", ", $escaped) . ")";
    }

    // Make SQL query
    $sql = "
      INSERT INTO `" . DB_PREFIX . "seo_keyword` 
        (`" . implode("`, `", $columns) . "`) 
      VALUES " . implode(", ", $escapedRows) . "
      ON DUPLICATE KEY UPDATE " . implode(", ", array_map(function ($col) {
        return "`$col` = VALUES(`$col`)";
    }, $columns));

    $this->db->query($sql);
    return true;
  }

  public function deleteData(array $ids) : mixed {
    if (empty($ids)) {
      return false;
    }

    $sql = "
      DELETE FROM " . DB_PREFIX . "seo_keyword k
    ";
    
    $where = [];
    if (!empty($ids)) {
      if (is_array($ids)) {
        $ids = array_map('intval', $ids);
        $where[] = "k.keyword_id IN (" . implode(',', $ids) . ")";
      } else {
        $where[] = "k.keyword_id = '" . (int) $ids . "'";
      }
    }

    if ($where) {
      $sql .= " WHERE " . implode(' AND ', $where);
    }

    $this->db->query($sql);
    return $this->db->countAffected();
  }

  public function getKeywordGroups() : array {
    $result = [];
    $query = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "seo_keyword_group
    ");

    foreach ($query->rows ?? [] as $row) {
      $result[] = $row;
    }

    return $result;
  }

  public function saveKeywordGroup($data) : int {
    $this->db->query("
      INSERT INTO " . DB_PREFIX . "seo_keyword_group
      (`keyword_group_name`)
      VALUES (
        '" . $this->db->escape($data) . "'
      )
    ");
    $id = $this->db->getLastId();
    return $id;
  }

  public function deleteKeywordGroup($id) : int {
    $this->db->query("
      DELETE FROM " . DB_PREFIX . "seo_keyword_group
      WHERE keyword_group_id = '" . (int) $id . "'
    ");
    return $this->db->getLastId();
  }
}
