<?php
class ControllerSeoFilterPage extends Controller {
  
  private $error = [];
  public $facetTypes = [];

  public function __construct($registry) {
		parent::__construct($registry);
    $this->language->load('seo/filter_page');$this->load->model('catalog/facet');
    $facetTypes = $this->model_catalog_facet->getFacetTypes();
    foreach ($facetTypes as $key => $facet_type) {
      $this->facetTypes[$key] = [
        'title'      => $this->language->get("autocomplete_" . $key),
        'facetType'  => $facet_type,
        'required'   => false,
        'searchType' => 'autocomplete'
      ];
      // Category facet_type === 1, set as required
      if ($facet_type == 1) {
        $this->facetTypes[$key]['required'] = true;
      }
      // Set searchType to checkbox to facets that have only boolean value - 1 or 0
      if (in_array($facet_type, [8,9,10])) {
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
    $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['page'])));

    $filter_data = array(
			'sort'  => $this->request->get['sort'] ?? 'date_modified',
			'order' => $this->request->get['order'] ?? 'DESC',
			'start' => (($this->request->get['page'] ?? 1) - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

    $items = $this->model_seo_filter_page->getList($filter_data);
    foreach ($items as $key => $item) {
      $items[$key]['edit'] = $this->url->link('seo/filter_page/edit', 'filter_page_id=' . $item['filter_page_id'] . '&user_token=' . $user_token, true);
    }

    $data = [
      // Items list
      'items'              => $items,
      // Common interface
      'column_left'        => $this->load->controller('common/column_left'),
      'footer'             => $this->load->controller('common/footer'),
      'header'             => $this->load->controller('common/header'),
      // Form interface
      'breadcrumbs'        => $this->displayBreadcrumbs(),
      'pagination'         => $this->getPagination()['pagination'],
      'results'            => $this->getPagination()['results'],
      'user_token'         => $user_token,
      // Form actions
      'add'                => $this->url->link('seo/filter_page/add', 'user_token=' . $user_token, true),
      'delete'             => $this->url->link('seo/filter_page/delete', 'user_token=' . $user_token, true),
      'success'            => $this->session->data['success'] ?? false,
      // Sorts and orders
      'sort'               => $this->request->get['sort'] ?? 'date_modified',
      'order'              => $this->request->get['order'] ?? 'DESC',
      'sort_name'          => $this->url->link('seo/filter_page', 'user_token=' . $user_token . $this->getSortOrder('name') . $url, true),
      'sort_category'      => $this->url->link('seo/filter_page', 'user_token=' . $user_token . $this->getSortOrder('category') . $url, true),
      'sort_date_modified' => $this->url->link('seo/filter_page', 'user_token=' . $user_token . $this->getSortOrder('date_modified') . $url, true),
      'sort_product_count' => $this->url->link('seo/filter_page', 'user_token=' . $user_token . $this->getSortOrder('product_count') . $url, true),
    ];

    $this->response->setOutput($this->load->view('seo/filter_page_list', $data));
  }

  private function getSortOrder(string $column): string {
    $currentSort  = $this->request->get['sort'] ?? '';
    $currentOrder = $this->request->get['order'] ?? 'ASC';

    if ($currentSort === $column) {
      $order = ($currentOrder === 'ASC') ? 'DESC' : 'ASC';
    } else {
      $order = 'ASC';
    }

    return '&sort=' . $column . '&order=' . $order;
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
    $formData['filter_page_images']      = $this->model_seo_filter_page->getImages($filter_page_id);
    $formData['seo_url']                 = $this->model_seo_filter_page->getSeoUrl($filter_page_id);
    // Replace actual data with POST data
    if ($this->request->server['REQUEST_METHOD'] == 'POST') {
      $formData = $this->request->post;
    }
    
    foreach ($formData['filter_page_facet'] ?? [] as $facetTypeId => $facetType) {
      foreach ($facetType as $facetGroupId => $facetGroup) {
        foreach ($facetGroup as $facetValueId => $facetValue) {
          $facetNames = $this->model_seo_filter_page->getFacetName($facetTypeId, $facetGroupId, $facetValue);
          $formData['filter_page_facet'][$facetTypeId][$facetGroupId][$facetValueId] = [
            'name'           => $facetNames['name'] ?? '',
            'group_name'     => $facetNames['group_name'] ?? '',
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
			$this->session->data['success'] = $this->language->get('text_filter_page_saved');
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

			$this->session->data['success'] = $this->language->get('text_filter_page_saved');

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

			$this->session->data['success'] = $this->language->get('text_filter_page_deleted');

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

  public function getPagination() {
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
    if (!isset($this->request->post['filter_page_facet']) || empty($this->request->post['filter_page_facet'])) {
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

    // Check if filter page with selected facets already exists
    if (!empty($this->request->post['filter_page_facet'])) {
      $currentId = $this->request->get['filter_page_id'] ?? null;
      $existingPages = $this->model_seo_filter_page->getExistingPage($this->request->post['filter_page_facet']);
      $isDuplicate = $existingPages && (
        !$currentId ||
        !in_array($currentId, array_column($existingPages, 'filter_page_id'))
      );
      if ($isDuplicate) {
        $this->error['error_facet_not_unique'] = $this->language->get('e_facet_not_unique');
      }
    }

		if ($this->request->post['seo_url']) {

			$this->load->model('design/seo_url');
      $storeId = (int) $this->session->data['store_id'];

      foreach ($this->request->post['seo_url'] as $langId => $currentUrl) {

        $currentUrl = trim(mb_strtolower($currentUrl));
        if (!$currentUrl) continue;

        $pageRequest = $this->model_design_seo_url->buildQuery(
          $this->request->post['filter_page_facet'] ?? []
        );

        $isUrlExists = $this->model_design_seo_url->checkUrlDuplicate($currentUrl, $langId, $storeId);
        $isRequestExists = $this->model_design_seo_url->checkRequestDuplicate($pageRequest, $langId, $storeId);

        foreach ($isUrlExists ?? [] as $row) {
          if ($row['query'] !== $pageRequest) {
            $this->error['error_url_not_unique'][$langId] = $this->language->get('e_url_not_unique');
            break;
          }
        }

        foreach ($isRequestExists ?? [] as $row) {
          if ($row['keyword'] !== $currentUrl) {
            $this->error['error_request_not_unique'][$langId] = sprintf($this->language->get('e_request_not_unique'), $row['keyword']);
            break;
          }
        }
      }
		}

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
    $post = $this->request->post;
    $selectedFacets = $post['filter_page_facet'] ?? [];
    $search = $post['search'] ?? [];
    
    $this->load->model('catalog/facet');
    $facets = $this->model_catalog_facet->getFacets($search, $selectedFacets);

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($facets));
  }

  public function fetchCheckExistingPage() : void {
    $filters = json_decode($this->request->post['filters'] ?? '[]', true);
    $this->load->model('catalog/facet');
    $facets = $this->model_catalog_facet->getExistingPage($filters);

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($facets));
  }
}
