<?php
class ControllerSeoFilterPage extends Controller {
  
  private $error = [];
  public $facetTypes = [];

  public function __construct($registry) {
		parent::__construct($registry);
    $this->language->load('seo/filter_page');$this->load->model('catalog/facet');
    $facetTypes = $this->model_catalog_facet->getFacetTypes();
    foreach ($facetTypes as $key => $value) {
      $this->facetTypes[$key] = [
        'title'      => $this->language->get($key),
        'facetType'  => $value,
        'required'   => false,
        'searchType' => 'autocomplete'
      ];
      if ($value == 1) {
        $this->facetTypes[$key]['required'] = true;
      }
      // Set searchType to checkbox to facets that have only boolean value - 1 or 0
      if (in_array($value, [8,9,10])) {
        $this->facetTypes[$key]['searchType'] = 'checkbox';
      }
    }
	}

  public function index() {
    $this->load->language('seo/filter_page');
    $this->load->model('seo/filter_page');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->getList();
  }

  public function getList() {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('seo/filter_page');
    $user_token = $this->session->data['user_token'];

    $data = [
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'breadcrumbs'   => $this->displayBreadcrumbs(),
      'pagination'    => $this->getPaginamtion()['pagination'],
      'results'       => $this->getPaginamtion()['results'],
      'user_token'    => $user_token,
      'add'           => $this->url->link('seo/filter_page/add', 'user_token=' . $user_token, true),
      'delete'        => $this->url->link('seo/filter_page/delete', 'user_token=' . $user_token, true),
    ];

    $this->response->setOutput($this->load->view('seo/filter_page_list', $data));
  }

  public function getForm() : void {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('seo/filter_page');
    $this->load->language('seo/filter_page');
    $this->document->addScript('view/javascript/niftyAutocomplete.js');
		$this->document->addStyle('view/stylesheet/niftyAutocomplete.css');
    
    $data       = [];
    $id         = $this->request->get['filter_page_id'] ?? null;
    $user_token = $this->session->data['user_token'];
    $url        = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));

    $data = [
      'facetTypes'    => $this->facetTypes,
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'languages'     => $this->model_localisation_language->getLanguages(),
      'stores'        => $this->model_setting_store->getMultistores(),
      'action'        => $id ? $this->url->link('seo/filter_page/edit', 'user_token=' . $user_token . '&filter_page_id=' . $id . $url, true) : $this->url->link('seo/filter_page/add', 'user_token=' . $user_token . $url, true),
      'cancel'        => $this->url->link('seo/filter_page', 'user_token=' . $user_token . $url, true),
      'user_token'    => (string) $this->session->data['user_token'],
    ];

    // Merge with errors array to hihlight faulty inputs. Merge saved data, POST data, errors and interface
    $data = [...$data, ...$this->error, ...$this->getFormData()];

    $this->response->setOutput($this->load->view('seo/filter_page_form', $data));
  }

  public function getFormData() : array {
    $formData       = [];
    $filter_page_id = $this->request->get['filter_page_id'] ?? null;
    $this->load->model('seo/filter_page');
    // Get actual data
    $formData['filter_page_description'] = $this->model_seo_filter_page->getFilterPageDescription($filter_page_id);
    $formData['filter_page_facet']       = $this->model_seo_filter_page->getFilterPageFacets($filter_page_id);
    // REplace actual data with POST data
    $formData = array_replace_recursive($formData, $this->request->post);
    
    foreach ($formData['filter_page_facet'] ?? [] as $facetTypeId => $facetType) {
      foreach ($facetType as $facetGroupId => $facetGroup) {
        foreach ($facetGroup as $facetValueId => $facetValue) {
          $facetNames = $this->model_seo_filter_page->getFacetName($facetTypeId, $facetGroupId, $facetValue);
          // echo '<pre>' . htmlspecialchars(print_r($facetNames, true)) . '</pre>';
          $formData['filter_page_facet'][$facetTypeId][$facetGroupId][$facetValueId] = [
            'name'           => $facetNames['name'],
            'group_name'     => $facetNames['group_name'],
            'facet_type'     => $facetTypeId,
            'facet_group_id' => $facetGroupId,
            'facet_value_id' => $facetValueId,
          ];
        }
      }
    }

    return $formData;
  }

  public function add() {
    $this->load->language('seo/filter_page');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('seo/filter_page');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_seo_filter_page->addFilterPage($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));

			$this->response->redirect($this->url->link('seo/filter_page', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
  }

	public function edit() {
		$this->load->language('seo/filter_page');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('seo/filter_page');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_seo_filter_page->editFilterPage($this->request->get['filter_page_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));

			$this->response->redirect($this->url->link('seo/filter_page', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('seo/filter_page');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('seo/filter_page');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $filter_page_id) {
				$this->model_seo_filter_page->deleteFilterPage($filter_page_id);
			}

			$this->session->data['success'] = $this->language->get('text_success_deleted');

      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));

			$this->response->redirect($this->url->link('seo/filter_page', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

  public function displayBreadcrumbs() {
    $this->load->language('seo/filter_page');
    $breadcrumbs = [];
    $breadcrumbs[] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    ];
    $breadcrumbs[] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('seo/filter_page', 'user_token=' . $this->session->data['user_token'], true)
    ];
    return $breadcrumbs;
  }

  public function getPaginamtion() {
    $this->load->model('seo/filter_page');
    $total = $this->model_seo_filter_page->getFilterPageTotal();
    $page = (int) ($this->request->get['page'] ?? 1);

    $params = ['sort', 'order'];

    $query = [];
    
    foreach ($params as $param) {
      if (isset($this->request->get[$param])) {
        $query[$param] = $this->request->get[$param];
      }
    }
    
    $url = $query ? '&' . http_build_query($query) : '';

    $pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page  = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('seo/filter_page', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($total - $this->config->get('config_limit_admin'))) ? $total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $total, ceil($total / $this->config->get('config_limit_admin')));

    return $data;
  }

  protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'seo/filter_page')) {
			$this->error['warning'] = $this->language->get('e_permission');
		}

		foreach ($this->request->post['filter_page_description'] as $language_id => $value) {
			if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 255)) {
				$this->error['error_name'][$language_id] = $this->language->get('e_name');
			}
		}

    // Check if parent category is selected
    if (!isset($this->request->post['filter_page_facet'][1])) {
      $this->error['error_select_category'] = $this->language->get('e_select_category');
    }

    // Check if at least one facet selected
    if (!isset($this->request->post['filter_page_facet'])) {
      $this->error['error_select_one_facet'] = $this->language->get('e_select_one_facet');
    } else {
      $hasSelected = false;
      foreach (($this->request->post['filter_page_facet'] ?? []) as $facetType => $facetGroup) {
        if ($facetType == 1) continue;
        if (!empty(array_filter($facetGroup))) {
          $hasSelected = true;
          break;
        }
      }
      if (!$hasSelected) {
        $this->error['error_select_one_facet'] = $this->language->get('e_select_one_facet');
      }
    }

		// if ($this->request->post['filter_page_seo_url']) {
		// 	$this->load->model('design/seo_url');

		// 	foreach ($this->request->post['filter_page_seo_url'] as $store_id => $language) {
		// 		foreach ($language as $language_id => $keyword) {
		// 			if (!empty($keyword)) {
		// 				if (count(array_keys($language, $keyword)) > 1) {
		// 					$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_unique');
		// 				}

		// 				$seo_urls = $this->model_design_seo_url->getSeoUrlsByKeyword($keyword);

		// 				foreach ($seo_urls as $seo_url) {
		// 					if (($seo_url['store_id'] == $store_id) && (!isset($this->request->get['filter_page_id']) || ($seo_url['query'] != 'filter_page_id=' . $this->request->get['filter_page_id']))) {
		// 						$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_keyword');

		// 						break;
		// 					}
		// 				}
		// 			}
		// 		}
		// 	}
		// }

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('e_warning');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'seo/filter_page')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

  public function autocomplete() : void {
    $filters = json_decode(html_entity_decode($this->request->post['filters']) ?? '[]', true);
    $this->load->model('catalog/facet');
    $facets = $this->model_catalog_facet->getFacets($filters);

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($facets));
  }
}
