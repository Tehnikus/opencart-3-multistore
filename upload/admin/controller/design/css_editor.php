<?php
class ControllerDesignCssEditor extends Controller {

  private $filePath;
  public function __construct($registry) {
    parent::__construct($registry);
    $store_id = (int) $this->session->data['store_id'];
    $themeName = $this->config->get('config_theme');
    $this->load->model('setting/setting');
    $themeDir = $this->model_setting_setting->getSettingValue("theme_{$themeName}_directory", $store_id);
    $this->filePath = DIR_CATALOG . "view/theme/" . $themeDir . "/css/";
  }
  
  private $error = [];
  public function index() {
    $this->load->language('design/css_editor');
    $this->document->setTitle($this->language->get('heading_title'));
    
    $this->getForm();
  }
  
  public function getForm() : void {

    $this->load->language('design/css_editor');
    $user_token = (string) $this->session->data['user_token'];    
    
    $data = [
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'action'        => $this->url->link('design/css_editor/edit', 'user_token=' . $user_token, true),
      'cancel'        => $this->url->link('design/css_editor', 'user_token=' . $user_token, true),
      'user_token'    => $user_token,
    ];
    
    $data = [...$data, ...$this->getFormData()];
    
    $this->response->setOutput($this->load->view('design/css_editor_form', $data));
  }
  
  public function getFormData() : array {
    $this->load->language('design/css_editor');
    $formData = [];
    $store_id = (int) $this->session->data['store_id'];
    $path = dirname($this->filePath) . "/custom_{$store_id}.css";
    
    if (!is_writable(dirname($path))) {
      return ['error_message' => $this->language->get('message_css_not_writable')];
    }

    if (!is_file($path)) {
      @touch($path);
    }

    $formData['css'] = file_exists($path) ? file_get_contents($path) : '';

    return $formData;
  }

  public function saveCss(string $content): bool {
    $store_id = (int) $this->session->data['store_id'];
    $path = dirname($this->filePath) . "/custom_{$store_id}.css";

    if (!is_writable(dirname($path))) {
      return false;
    }

    return file_put_contents($path, $content, LOCK_EX) !== false;
  }
  
  public function fetchSave() {
    $this->load->language('design/css_editor');
    $result = $this->saveCss(html_entity_decode($this->request->post['css']));
    $json = [
      'success' => $result,
      'message' => $result ? $this->language->get('message_css_saved') : $this->language->get('message_css_not_writable')
    ]; 
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }
}