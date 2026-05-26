<?php
class ControllerDesignJsEditor extends Controller {

  private $filePath;
  private $allowedFiles;
  public function __construct($registry) {
    parent::__construct($registry);
    $store_id = (int) $this->session->data['store_id'];
    $themeName = $this->config->get('config_theme');
    $this->load->model('setting/setting');
    $themeDir = $this->model_setting_setting->getSettingValue("theme_{$themeName}_directory", $store_id);
    $this->filePath = DIR_CATALOG . "view/theme/{$themeDir}/js/";

    $this->allowedFiles = ["custom_{$store_id}", "main"];
  }
  
  public function index() {
    $this->load->language('design/js_editor');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->getForm();
  }
  
  public function getForm() : void {

    $this->load->language('design/js_editor');
    $user_token = (string) $this->session->data['user_token'];    
    
    $data = [
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'action'        => $this->url->link('design/js_editor/edit', 'user_token=' . $user_token, true),
      'cancel'        => $this->url->link('design/js_editor', 'user_token=' . $user_token, true),
      'user_token'    => $user_token,
    ];
    
    $data = [...$data, ...$this->getFormData()];
    
    $this->response->setOutput($this->load->view('design/js_editor_form', $data));
  }
  
  public function getFormData() : array {
    $this->load->language('design/js_editor');
    $formData = [];

    foreach ($this->allowedFiles as $fileName) {
      $fullPath = "{$this->filePath}{$fileName}.js";
      
      if (!is_writable($this->filePath)) {
        return ['error_message' => sprintf($this->language->get('message_js_not_writable'), $fileName, $this->filePath)];
      }
      $formData['files'][$fileName] = [
        'name' => $fileName,
        'code' => file_exists($fullPath) ? file_get_contents($fullPath) : '',
      ];
    }

    return $formData;
  }

  public function saveJs(string $content, string $fileName) : bool {
    // Check file name before save
    if (!in_array($fileName, $this->allowedFiles)) {
      return false;
    }
    $fullPath = "{$this->filePath}{$fileName}.js";

    if (!is_writable(dirname($fullPath))) {
      return false;
    }

    return file_put_contents($fullPath, $content, LOCK_EX) !== false;
  }
  
  public function fetchSave() : void {
    $this->load->language('design/js_editor');
    $fileName = $this->request->post['fileName'] ?? '';
    $code     = $this->request->post['code'] ?? '';
    $result   = $this->saveJs(html_entity_decode($code), $fileName);
    $json = [
      'success' => $result,
      'message' => $result ? sprintf($this->language->get('message_js_saved'), $fileName) : sprintf($this->language->get('message_js_not_writable'), $fileName, $this->filePath)
    ]; 
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }
}