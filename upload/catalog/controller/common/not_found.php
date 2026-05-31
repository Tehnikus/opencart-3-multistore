<?php
class ControllerCommonNotFound extends Controller {
  public function index() {
    $this->document->setTitle($this->language->get('text_error'));
    $this->document->setRobots('robots', 'noindex, nofollow');

    $this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

    $data['column_left']    = $this->load->controller('common/column_left');
    $data['column_right']   = $this->load->controller('common/column_right');
    $data['content_top']    = $this->load->controller('common/content_top');
    $data['content_bottom'] = $this->load->controller('common/content_bottom');
    $data['footer']         = $this->load->controller('common/footer');
    $data['header']         = $this->load->controller('common/header');

    $this->response->setOutput($this->load->view('error/not_found', $data));
  }
}