<?php 
class ControllerSeoMetaEditor extends Controller {
  public function index() {
    $this->load->language('seo/meta_editor');
    $this->load->model('seo/meta_editor');
    $this->document->setTitle($this->language->get('meta_editor'));
    
    $this->getList();
  }

  public function getList() {
    $this->load->language('seo/meta_editor');
    $this->load->model('seo/meta_editor');
    $this->document->setTitle($this->language->get('meta_editor'));
    
    $types = $this->model_seo_meta_editor->getTypes();
    $current_type = isset($types[$this->request->get['type']]) ? $this->request->get['type'] : 'category';

    $data = [
      'type'        => $current_type,
      'column_id'   => 'product_id',
    ];

    $data = [...$data, ...$this->getCommonListData()];

    $this->response->setOutput($this->load->view('seo/meta_editor', $data));
  }

  private function getCommonListData() : array {
    $data = [
      'store_id'     => (int) $this->session->data['store_id'],
      'breadcrumbs'  => $this->displayBreadcrumbs(),
      'user_token'   => $this->session->data['user_token'],
      'column_left'  => $this->load->controller('common/column_left'),
      'footer'       => $this->load->controller('common/footer'),
      'header'       => $this->load->controller('common/header'),
    ];
    return $data;
  }

  public function displayBreadcrumbs() {
    $breadcrumbs = [];
    $breadcrumbs[] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    ];
    $breadcrumbs[] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('seo/meta_editor', 'user_token=' . $this->session->data['user_token'], true)
    ];
    return $breadcrumbs;
  }

  public function fetchGetInterface() : void {
    // Load new Language
    $lang = new Language();
    // First load admin translation 
    // $lang->load($this->config->get('config_admin_language'));
    // Then load current controller translation to overwrite same named entries with current controller translation
    $lang->load('seo/meta_editor');
    // Get languages and stores
    $this->load->model('localisation/language');
    $this->load->model('setting/store');
    $this->load->model('seo/meta_editor');
    $stores    = $this->model_setting_store->getMultistores();
    $languages = $this->model_localisation_language->getLanguages();
    // Return JSON to fetch
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        [
          'lang'          => $lang->data,
          'stores'        => $stores,
          'languages'     => $languages,
        ]
      )
    );
  }
}