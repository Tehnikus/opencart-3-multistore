<?php
class ControllerBlogTag extends Controller {
  
  private $error = [];
  public function index() {
    $this->load->language('blog/tag');
    $this->load->model('blog/tag');
    $this->document->setTitle($this->language->get('heading_title'));
    
    $this->getList();
  }
  
  public function getList() : void {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('blog/tag');
    $user_token = $this->session->data['user_token'];
    $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['page', 'order'])));
    
    // Get items list
    $filter_data = array(
      'sort'  => $this->request->get['sort'] ?? 'date_added',
      'order' => $this->request->get['order'] ?? 'DESC',
      'start' => (($this->request->get['page'] ?? 1) - 1) * $this->config->get('config_limit_admin'),
      'limit' => $this->config->get('config_limit_admin')
    );
    $items = $this->model_blog_tag->getList($filter_data);
    // Set each item edit link
    foreach ($items as $key => $item) {
      $items[$key]['edit'] = $this->url->link('blog/tag/edit', 'blog_tag_id=' . $item['blog_tag_id'] . '&user_token=' . $user_token, true);
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
      'add'           => $this->url->link('blog/tag/add', 'user_token=' . $user_token, true),
      'delete'        => $this->url->link('blog/tag/delete', 'user_token=' . $user_token, true),
      'sort'          => $this->request->get['sort'] ?? 'date_added',
      'sort_name'     => $this->url->link('blog/tag', 'user_token=' . $user_token . $this->getSortOrder('name') . $url, true),
    ];

    if (isset($this->session->data['success'])) {
      $data['text_success'] = $this->session->data['success'];
      unset($this->session->data['success']);
    }
    
    $this->response->setOutput($this->load->view('blog/tag_list', $data));
  }
  
  public function getForm() : void {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('blog/tag');
    $this->load->language('blog/tag');
    
    $data       = [];
    $id         = $this->request->get['blog_tag_id'] ?? null;
    $user_token = $this->session->data['user_token'];
    $url        = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
    
    $data = [
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'languages'     => $this->model_localisation_language->getLanguages(),
      'stores'        => $this->model_setting_store->getMultistores(),
      'action'        => $id ? $this->url->link('blog/tag/edit', 'user_token=' . $user_token . '&blog_tag_id=' . $id . $url, true) : $this->url->link('blog/tag/add', 'user_token=' . $user_token . $url, true),
      'cancel'        => $this->url->link('blog/tag', 'user_token=' . $user_token . $url, true),
    ];
    
    // Merge with errors array to hihlight faulty inputs. Merge saved data, POST data, errors and interface
    $data = [...$data, ...$this->error, ...$this->getFormData()];
    
    $this->response->setOutput($this->load->view('blog/tag_form', $data));
  }
  
  public function getFormData() : array {
    $formData       = [];
    $blog_tag_id = $this->request->get['blog_tag_id'] ?? null;
    $this->load->model('blog/tag');

    $formData['blog_tag']             = $this->model_blog_tag->getBlogTag($blog_tag_id);
    $formData['blog_tag_description'] = $this->model_blog_tag->getTagDescription($blog_tag_id);
    $formData['seo_url']              = $this->model_blog_tag->getSeoUrl($blog_tag_id);
    $formData['blog_tag_images']      = $this->model_blog_tag->getImages($blog_tag_id);
    // Replace actual data with POST data
    if ($this->request->server['REQUEST_METHOD'] == 'POST') {
      $formData = $this->request->post;
    }
    return $formData;
  }
  
  public function add() : void {
    $this->load->language('blog/tag');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('blog/tag');
    
    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
      $this->model_blog_tag->addTag($this->request->post);
      $this->session->data['success'] = $this->language->get('text_success_saved');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
      $this->response->redirect($this->url->link('blog/tag', 'user_token=' . $this->session->data['user_token'] . $url, true));
    }
    
    $this->getForm();
  }
  
  public function edit() : void {
    $this->load->language('blog/tag');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('blog/tag');
    
    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
      $this->model_blog_tag->editTag($this->request->get['blog_tag_id'], $this->request->post);
      $this->session->data['success'] = $this->language->get('text_success_saved');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
      $this->response->redirect($this->url->link('blog/tag', 'user_token=' . $this->session->data['user_token'] . $url, true));
    }
    
    $this->getForm();
  }
  
  public function delete() : void {
    $this->load->language('blog/tag');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('blog/tag');
    
    if (isset($this->request->post['selected']) && $this->validateDelete()) {
      foreach ($this->request->post['selected'] as $blog_tag_id) {
        $this->model_blog_tag->deleteTag($blog_tag_id);
      }
      $this->session->data['success'] = $this->language->get('text_success_deleted');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
      $this->response->redirect($this->url->link('blog/tag', 'user_token=' . $this->session->data['user_token'] . $url, true));
    }
    
    $this->getList();
  }
  
  public function displayBreadcrumbs() : array {
    $this->load->language('blog/tag');
    $breadcrumbs = [];
    $breadcrumbs[] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    ];
    $breadcrumbs[] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('blog/tag', 'user_token=' . $this->session->data['user_token'], true)
    ];
    
    return $breadcrumbs;
  }
  
  public function getPagination() : array {
    $this->load->model('blog/tag');
    $total = $this->model_blog_tag->getTagTotal();
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
    $pagination->url = $this->url->link('blog/tag', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
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
		if (!$this->user->hasPermission('modify', 'blog/tag')) {
			$this->error['warning'] = $this->language->get('e_permission');
		}

		foreach ($this->request->post['blog_tag_description'] as $language_id => $value) {
			if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 255)) {
				$this->error['error_name'][$language_id] = $this->language->get('e_name');
			}
		}

    // // Check if parent category is selected
    // if (!isset($this->request->post['filter_page_facet'][1])) {
    //   $this->error['error_select_category'] = $this->language->get('e_select_category');
    // }


		if ($this->request->post['seo_url']) {

			$this->load->model('design/seo_url');
      $storeId = (int) $this->session->data['store_id'];

      foreach ($this->request->post['seo_url'] as $langId => $currentUrl) {

        $currentUrl = trim(mb_strtolower($currentUrl));
        if (!$currentUrl) continue;

        $pageRequest = (isset($this->request->get['blog_tag_id'])) ? 'blog_tag_id=' . ((int) $this->request->get['blog_tag_id']) : '';

        $isUrlExists = $this->model_design_seo_url->checkUrlDuplicate($currentUrl, $langId, $storeId);
        // $isRequestExists = $this->model_design_seo_url->checkRequestDuplicate($pageRequest, $langId, $storeId);

        foreach ($isUrlExists ?? [] as $row) {
          if ($row['query'] !== $pageRequest) {
            $this->error['error_url_not_unique'][$langId] = $this->language->get('e_url_not_unique');
            break;
          }
        }

        // foreach ($isRequestExists ?? [] as $row) {
        //   if ($row['keyword'] !== $currentUrl) {
        //     $this->error['error_request_not_unique'][$langId] = sprintf($this->language->get('e_request_not_unique'), $row['keyword']);
        //     break;
        //   }
        // }
      }
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('e_warning');
		}

		return !$this->error;
	}
  
  protected function validateDelete() : bool {
    if (!$this->user->hasPermission('modify', 'blog/tag')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }
    return !$this->error;
  }

  public function autocomplete() : void {
    $post = $this->request->post;
    // $search = $post['search'] ?? [];
    
    $this->load->model('blog/tag');
    $tags = $this->model_blog_tag->getList($post);

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($tags));
  }

  public function fetchSetTagStatus() : void {
		$tagId 			    = (int) $this->request->post['blog_tag_id'];
		$currentStatus 	= (int) $this->request->post['status'];
		$newStatus 			= 0;

		if ($currentStatus === 0) {
			$newStatus = 1;
		}

		$this->load->model('blog/tag');
		$newStatus = $this->model_blog_tag->setTagStatus($tagId, $newStatus);
		$json = ['blogTagId' => $tagId, 'newStatus' => $newStatus];

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}