<?php
/**
 * This controller allows to load and save data in batches with JS in admin panel
 * To load data in JS use:
 * const data = await loadBatch("folder/controller", "getDataFunction", {start: 0, limit: 10000}, userToken, batchSize = 200);
 * returns array of objects
 * To save data use:
 * const savedResult = await saveBatch("folder/controller", "saveDataFunction", [savedData], userToken, batchSize = 200);
 * Accepts array of objects
 */
class ControllerCommonBatchloader extends Controller
{

  private function jsonResponse(array $data, int $statusCode = 200): void {
    $this->response->addHeader('Content-Type: application/json; charset=utf-8');
    $this->response->addHeader('Access-Control-Allow-Origin: *');
    $this->response->addHeader("HTTP/1.1 {$statusCode}");
    $this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE));
  }

  private function jsonError(string $message, int $statusCode = 400): void {
    $this->jsonResponse([
      'status' => 'error',
      'message' => $message
    ], $statusCode);
  }


  public function saveBatch() {
    $this->response->addHeader('Content-Type: application/json');

    $model = $this->request->get['model'] ?? '';
    $method = $this->request->get['method'] ?? '';

    if (!$model || !$method) {
      $this->jsonError('Model or method is not set', 400);
      return;
    }

    $args = [];

    if (!empty($this->request->post['args'])) {
      $args = json_decode(html_entity_decode($this->request->post['args']), true);
    }

    if (!is_array($args)) {
      $args = [];
    }

    $rows = [];
    if (!empty($this->request->post['rows'])) {
      // If POST ['rows'] appeared to be a string, then try to decode JSON string. Else leave as is 
      $rows = is_string($this->request->post['rows'])
        ? json_decode(html_entity_decode($this->request->post['rows']), true)
        : $this->request->post['rows'];
    }

    if (!is_array($rows)) {
      $this->jsonError('Invalid rows format', 400);
      return;
    }

    $this->load->model($model);
    $modelInstance = $this->{"model_" . str_replace('/', '_', $model)};

    try {
      $result = $modelInstance->$method($rows, ...$args);
      $this->jsonResponse([
        'status' => 'ok',
        'saved' => count($rows),
        'result' => $result ?? []
      ], 200);
    } catch (\Throwable $e) {
      $this->jsonError("Error on method call {$method}: " . $e->getMessage(), 400);
    }
  }

  public function loadBatch() {
    $this->response->addHeader('Content-Type: application/json');

    $model = $this->request->get['model'] ?? '';
    $method = $this->request->get['method'] ?? '';

    $filter = [];
    foreach ($this->request->post as $key => $value) {
      $filter[$key] = $value;
    }

    // Default batch params - load from firs to 100th row
    // $filter['start'] = isset($this->request->post['start']) ? (int) $this->request->post['start'] : 0;
    // $filter['limit'] = isset($this->request->post['limit']) ? (int) $this->request->post['limit'] : 100;

    if (!$model || !$method) {
      $this->jsonError('Model or method is not set', 400);
      return;
    }

    $this->load->model($model);
    $modelInstance = $this->{"model_" . str_replace('/', '_', $model)};

    try {
      $result = $modelInstance->$method($filter);
      $this->jsonResponse([
        'status' => 'ok',
        'rows' => $result['rows'] ?? $result ?? []
      ], 200);
    } catch (\Throwable $e) {
      $this->jsonError("Error on method call {$method}: " . $e->getMessage(), 400);
    }
  }
}
