<?php 
class ControllerSeoKeyword extends Controller {
  public function index() {
    $this->load->language('seo/keyword');
    $this->load->model('seo/keyword');
    $this->document->setTitle($this->language->get('heading_title'));

    $this->getList();
  }

  public function getList() {
    $this->document->addScript('view/javascript/nimbleTable.js');
    $this->document->addScript('view/javascript/batchloader.js');

    $this->load->model('setting/store');
    $this->load->model('localisation/language');

    $data = [
      'column_left'            => $this->load->controller('common/column_left'),
      'footer'                 => $this->load->controller('common/footer'),
      'header'                 => $this->load->controller('common/header'),
      'breadcrumbs'            => $this->displayBreadcrumbs(),
      'user_token'             => $this->session->data['user_token'],
    ];

    $this->response->setOutput($this->load->view('seo/keyword', $data));
  }

  public function displayBreadcrumbs() {
    $breadcrumbs = [];
    $breadcrumbs[] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    ];
    $breadcrumbs[] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('seo/keyword', 'user_token=' . $this->session->data['user_token'], true)
    ];
    return $breadcrumbs;
  }

  public function fetchGetInterface() : void {
    // Load new Language
    $lang = new Language();
    // First load admin translation 
    // $lang->load($this->config->get('config_admin_language'));
    // Then load current controller translation to overwrite same named entries with current controller translation
    $lang->load('seo/keyword');
    // Get languages and stores
    $this->load->model('localisation/language');
    $this->load->model('setting/store');
    $this->load->model('seo/keyword');
    $stores    = $this->model_setting_store->getMultistores();
    $languages = $this->model_localisation_language->getLanguages();
    $keywordGroups = $this->model_seo_keyword->getKeywordGroups();
    // Return JSON to fetch
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        [
          'lang'          => $lang->data,
          'stores'        => $stores,
          'languages'     => $languages,
          'keywordGroups' => $keywordGroups,
        ]
      )
    );
  }

  public function fetchSaveKeywordGroup() : void {
    $response = [];
    $group = $this->request->post['keyword_group_name'] ?? [];

    if (empty($group)) {
      $response['keyword_group_name'] = 0;
    } else {
      $this->load->model('seo/keyword');
      $response['keyword_group_id'] = $this->model_seo_keyword->saveKeywordGroup($group);
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        $response
      )
    );
  }

  public function fetchDeleteKeywordGroup() : void {
    $response = [];
    $group = $this->request->post['keyword_group_id'] ?? [];
    if (empty($group)) {
      $response['keyword_group_id'] = 0;
    } else {
      $this->load->model('seo/keyword');
      $response['keyword_group_id'] = $this->model_seo_keyword->deleteKeywordGroup($group);
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        $response
      )
    );
  }

  public function fetchGetKeywords() : void {
    $this->load->model('seo/keyword');
    $keywords = $this->model_seo_keyword->getKeywords();
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        $keywords
      )
    );
  }

  public function fetchSaveKeywords() : void {
    $response = ['success' => false];
    $keywords = $this->request->post['keywords'] ?? "[]";
    $data = json_decode(html_entity_decode($keywords, ENT_QUOTES, 'UTF-8'), true);
    
    if (!empty($data)) {
      $this->load->model('seo/keyword');
      $result = $this->model_seo_keyword->saveData($data);
    
      $response = [
        'success' => (bool)$result
      ];
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($response));
  }

  public function fetchDeleteKeywords() : void {
    $response = [];
    $keywords = $this->request->post['keywords'] ?? [];

    $response = [
      'success' => false
    ];
    
    if (!empty($keywords) && is_array($keywords)) {
      $this->load->model('seo/keyword');
      $result = $this->model_seo_keyword->deleteData($keywords);
    
      $response = [
        'success' => (bool)$result
      ];
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($response));
  }
}