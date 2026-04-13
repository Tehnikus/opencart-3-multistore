<?php
class ControllerSeoTag extends Controller {
  
  private $error = [];
  public function index() {
    $this->load->language('seo/tag');
    $this->load->model('seo/tag');
    $this->document->setTitle($this->language->get('heading_title'));
    
    $this->getList();
  }
  
  public function getList() : void {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('seo/tag');
    $user_token = $this->session->data['user_token'];
    $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['page'])));
    
    // Get items list
    $filter_data = array(
      'sort'  => $this->request->get['sort'] ?? 'date_added',
      'order' => $this->request->get['order'] ?? 'DESC',
      'start' => (($this->request->get['page'] ?? 1) - 1) * $this->config->get('config_limit_admin'),
      'limit' => $this->config->get('config_limit_admin')
    );
    $items = $this->model_seo_tag->getList($filter_data);
    // Set each item edit link
    foreach ($items as $key => $item) {
      $items[$key]['inline_icon'] = html_entity_decode($items[$key]['inline_icon']);
      $items[$key]['edit'] = $this->url->link('seo/tag/edit', 'seo_tag_id=' . $item['seo_tag_id'] . '&user_token=' . $user_token, true);
    }

    $data = [
      'items'         => $items,
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'breadcrumbs'   => $this->displayBreadcrumbs(),
      'pagination'    => $this->getPagination()['pagination'],
      'results'       => $this->getPagination()['results'],
      'user_token'    => $user_token,
      'add'           => $this->url->link('seo/tag/add', 'user_token=' . $user_token, true),
      'delete'        => $this->url->link('seo/tag/delete', 'user_token=' . $user_token, true),
      // Sorts
      'sort'                  => $this->request->get['sort'] ?? 'sort_order',
      'order'                 => $this->request->get['order'] ?? '',
      'sort_name'             => $this->url->link('seo/tag', 'user_token=' . $user_token . $this->getSortOrder('name') . $url, true),
      'sort_product_count'    => $this->url->link('seo/tag', 'user_token=' . $user_token . $this->getSortOrder('product_count') . $url, true),
      'sort_sort_order'       => $this->url->link('seo/tag', 'user_token=' . $user_token . $this->getSortOrder('sort_order') . $url, true),
      'sort_show_as_facet'    => $this->url->link('seo/tag', 'user_token=' . $user_token . $this->getSortOrder('show_as_facet') . $url, true),
      'sort_show_as_flag'     => $this->url->link('seo/tag', 'user_token=' . $user_token . $this->getSortOrder('show_as_flag') . $url, true),
    ];

    if (isset($this->session->data['success'])) {
      $data['success'] = $this->session->data['success'];
      unset($this->session->data['success']);
    }
    
    $this->response->setOutput($this->load->view('seo/tag_list', $data));
  }
  
  public function getForm() : void {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('seo/tag');
    $this->load->language('seo/tag');
    $this->document->addScript('view/javascript/niftyAutocomplete.js');
		$this->document->addStyle('view/stylesheet/niftyAutocomplete.css');
    
    $data       = [];
    $id         = $this->request->get['seo_tag_id'] ?? null;
    $user_token = $this->session->data['user_token'];
    $url        = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
    
    $data = [
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'languages'     => $this->model_localisation_language->getLanguages(),
      'stores'        => $this->model_setting_store->getMultistores(),
      'action'        => $id ? $this->url->link('seo/tag/edit', 'user_token=' . $user_token . '&seo_tag_id=' . $id . $url, true) : $this->url->link('seo/tag/add', 'user_token=' . $user_token . $url, true),
      'cancel'        => $this->url->link('seo/tag', 'user_token=' . $user_token . $url, true),
      'user_token'    => $user_token,
    ];
    
    // Merge with errors array to hihlight faulty inputs. Merge saved data, POST data, errors and interface
    $data = [...$data, ...$this->error, ...$this->getFormData()];
    
    $this->response->setOutput($this->load->view('seo/tag_form', $data));
  }
  
  public function getFormData() : array {
    $formData       = [];
    $seo_tag_id = $this->request->get['seo_tag_id'] ?? null;
    $this->load->model('seo/tag');
    $formData['tag'] = $this->model_seo_tag->getTagData($seo_tag_id);
    $formData['seo_tag_description']  = $this->model_seo_tag->getTagDescription($seo_tag_id);
    $formData['seo_url'] = $this->model_seo_tag->getSeoUrl($seo_tag_id);
    $formData['product_tags'] = $this->model_seo_tag->getProductTags($seo_tag_id);
    
    // Replace actual data with POST data
    if ($this->request->server['REQUEST_METHOD'] == 'POST') {
      $formData = $this->request->post;
    }

    if (isset($this->request->post['product_tags'])) {
      $formData['product_tags'] = [];
      foreach ($this->request->post['product_tags'] as $product_id) {
        $formData['product_tags'][] = [
          'product_id' => $product_id
        ];
      }
    }

    // Get inline icons. This array is not related to POST so it should be added regardless of POST data
    $formData['icons'] = $this->model_seo_tag->getUsedIcons();
    foreach ($formData['icons'] as $key => $icon) {
      $formData['icons'][$key] = html_entity_decode($icon['inline_icon']);
    }

    // Get inline styles. This array is not related to POST so it should be added regardless of POST data
    $formData['styles'] = $this->model_seo_tag->getUsedStyles();
    foreach ($formData['styles'] as $key => $style) {
      $formData['styles'][$key] = html_entity_decode($style['inline_style']);
    }

    // Get product names
    $this->load->model('catalog/product');
    foreach ($formData['product_tags'] ?? [] as $key => $product) {
      $product = $this->model_catalog_product->getProduct($product['product_id']);
      $formData['product_tags'][$key]['name'] = $product['name'];
    }

    return $formData;
  }
  
  public function add() : void {
    $this->load->language('seo/tag');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('seo/tag');
    
    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
      $this->model_seo_tag->addTag($this->request->post);
      $this->session->data['success'] = $this->language->get('text_success');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
      $this->response->redirect($this->url->link('seo/tag', 'user_token=' . $this->session->data['user_token'] . $url, true));
    }
    
    $this->getForm();
  }
  
  public function edit() : void {
    $this->load->language('seo/tag');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('seo/tag');
    
    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
      $this->model_seo_tag->editTag($this->request->get['seo_tag_id'], $this->request->post);
      $this->session->data['success'] = $this->language->get('text_success');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
      $this->response->redirect($this->url->link('seo/tag', 'user_token=' . $this->session->data['user_token'] . $url, true));
    }
    
    $this->getForm();
  }
  
  public function delete() : void {
    $this->load->language('seo/tag');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('seo/tag');
    
    if (isset($this->request->post['selected']) && $this->validateDelete()) {
      foreach ($this->request->post['selected'] as $seo_tag_id) {
        $this->model_seo_tag->deleteTag($seo_tag_id);
      }
      $this->session->data['success'] = $this->language->get('text_success_deleted');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
      $this->response->redirect($this->url->link('seo/tag', 'user_token=' . $this->session->data['user_token'] . $url, true));
    }
    
    $this->getList();
  }
  
  public function displayBreadcrumbs() : array {
    $this->load->language('seo/tag');
    $breadcrumbs = [];
    $breadcrumbs[] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    ];
    $breadcrumbs[] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('seo/tag', 'user_token=' . $this->session->data['user_token'], true)
    ];
    
    return $breadcrumbs;
  }
  
  public function getPagination() : array {
    $this->load->model('seo/tag');
    $total = $this->model_seo_tag->getTagTotal();
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
    $pagination->url = $this->url->link('seo/tag', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
    $data['pagination'] = $pagination->render();
    $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($total - $this->config->get('config_limit_admin'))) ? $total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $total, ceil($total / $this->config->get('config_limit_admin')));
    
    return $data;
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

  protected function validateForm() : bool {
		if (!$this->user->hasPermission('modify', 'seo/tag')) {
			$this->error['warning'] = $this->language->get('e_permission');
		}

		foreach ($this->request->post['seo_tag_description'] as $language_id => $value) {
			if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 255)) {
				$this->error['error_name'][$language_id] = $this->language->get('e_name');
			}
    }

		if ($this->request->post['seo_url']) {

			$this->load->model('design/seo_url');
      $storeId = (int) $this->session->data['store_id'];

      foreach ($this->request->post['seo_url'] as $langId => $currentUrl) {

        $currentUrl = trim(mb_strtolower($currentUrl));
        if (!$currentUrl) continue;

        $pageRequest = (isset($this->request->get['seo_tag_id'])) ? 'tag_id=' . ((int) $this->request->get['seo_tag_id']) : '';

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
  
  protected function validateDelete() : bool {
    if (!$this->user->hasPermission('modify', 'seo/tag')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }
    return !$this->error;
  }

  public function autocomplete() : void {
    $post = $this->request->post;
    // $search = $post['search'] ?? [];
    
    $this->load->model('seo/tag');
    $tags = $this->model_seo_tag->getList($post);

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($tags));
  }
}