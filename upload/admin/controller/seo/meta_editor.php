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
    $this->load->model('setting/setting');

    $this->document->setTitle($this->language->get('meta_editor'));
    
    $types        = $this->model_seo_meta_editor->getTypes();
    $formulas     = $this->model_setting_setting->getSetting('meta_editor_formulas');
    
    $requestType  = $this->request->get['type'];
    $current_type = isset($types[$requestType]) ? $requestType : 'category';
    $column_id    = isset($types[$requestType]) ? $types[$requestType]['column_id'] : 'category_id';
    $path         = isset($types[$requestType]) ? $types[$requestType]['path'] : 'catalog/category';


    $data = [
      'page_type'         => $current_type,
      'column_id'         => $column_id,
      'path'              => $path,
      'formulas'          => $formulas['meta_editor_formulas_' . $current_type]['formulas'] ?? [],
      'fetchSaveFormulas' => $this->url->link('seo/meta_editor/fetchSaveFormulas', 'user_token=' . $this->session->data['user_token'] . '&type=' . $current_type, true),
    ];

    $data = [...$data, ...$this->getCommonListData()];

    $this->response->setOutput($this->load->view('seo/meta_editor', $data));
  }

  private function getCommonListData() : array {
    $this->load->model('localisation/language');
    $this->load->model('localisation/currency');
    $this->load->language('seo/meta_editor');
    $currencies = $this->model_localisation_currency->getCurrencies();
    $languages = $this->model_localisation_language->getLanguages();
    $target_options = [
      'meta_title' => $this->language->get('input_meta_title'),
      'h1' => $this->language->get('input_h1'),
      'meta_description' => $this->language->get('input_meta_description')
    ];
    $data = [
      'languages'      => $languages,
      'currencies'     => $currencies,
      'target_options' => $target_options,
      'store_id'       => (int) $this->session->data['store_id'],
      'breadcrumbs'    => $this->displayBreadcrumbs(),
      'user_token'     => $this->session->data['user_token'],
      'column_left'    => $this->load->controller('common/column_left'),
      'footer'         => $this->load->controller('common/footer'),
      'header'         => $this->load->controller('common/header'),
    ];
    return $data;
  }

  public function displayBreadcrumbs() {
    $breadcrumbs = [];
    $this->load->model('seo/meta_editor');
    $types = $this->model_seo_meta_editor->getTypes();
    foreach ($types as $key => $type) {
      $breadcrumbs[] = [
        'text' => $this->language->get('header_' . $key),
        'href' => $this->url->link('seo/meta_editor', 'user_token=' . $this->session->data['user_token'] . '&type=' . $key, true)
      ];
    }
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
    $languagesById = [];
    foreach ($languages as $language) {
      $languagesById[$language['language_id']] = $language;
    }
    // Return JSON to fetch
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        [
          'lang'          => $lang->data,
          'stores'        => $stores,
          'languages'     => $languagesById,
          'defaultLanguageId' => (int) $this->config->get('config_language_id'),
        ]
      )
    );
  }

  public function fetchSaveFormulas() {
    $json = [];
    $this->load->model('setting/setting');
    $this->language->load('seo/meta_editor');
		$post_data = $this->request->post;
		$saveResult = $this->model_setting_setting->editSetting('meta_editor_formulas', ['meta_editor_formulas_' . $this->request->get['type'] => $post_data]);

		$json['success'] = true;
		$json['message'] = $this->language->get('message_formulas_saved');
		$json['response'] = $saveResult;
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));

	}
}