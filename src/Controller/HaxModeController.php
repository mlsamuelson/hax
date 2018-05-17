<?php

namespace Drupal\hax\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Controller\NodeViewController;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a controller to render a single node in HAX Mode.
 */
class HaxModeController extends NodeViewController {

  /**
   * Hax node edit form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node.
   * @param string $view_mode
   *   The node's view mode.
   * @param null $langcode
   *   The node's langcode.
   *
   * @return array
   *   The node's view render array.
   *
   * @todo: There's a good chance this logic isn't invoked.
   */
  public function haxNodeForm(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    // Based on NodeViewController's view() method.
    $build = parent::view($node, $view_mode, $langcode);

    // This method only seems useful for adding attachments, but not for
    // altering. Much of the contents of $build['#node'] are protected
    // Is hax_node_view() a better place for altering the node field output?
    // Or are there other hooks we're missing?
    // TODO maybe just route to the canonical if we end up not actually using
    // this controller.
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function title(EntityInterface $node) {
    // TODO Doesn't appear to be working, but shows up in router. What gives?
    return t("HAX edit @label", [
      '@label' => $this->entityManager->getTranslationFromContext($node)->label(),
    ]);
  }

  /**
   * Permission + Node access check.
   *
   * @param mixed $op
   *   The operation.
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Either allowed or forbidden access response.
   */
  public function haxNodeAccess($op, NodeInterface $node) {

    if (\Drupal::currentUser()->hasPermission('use hax') && node_node_access($node, $op, \Drupal::currentUser())) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Custom node save logic for hax.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The hax node.
   * @param mixed $token
   *   A token.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The http response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function haxNodeSave(NodeInterface $node, $token) {

    if (
      $_SERVER['REQUEST_METHOD'] == 'PUT' &&
      \Drupal::csrfToken()->validate($token)) {

      // We're not using the Drupal entity REST system outright here, as PUT
      // isn't supported. But we can, ahem, "patch" the behavior in ourselves.
      // HAX submitted value is right here.
      $body = file_get_contents('php://input');

      // Get the current format, and retain it. User will have to manage that
      // via the edit tab. We don't want to auto-set it. Making changes like
      // that without user intentionality is bad practice.
      $current_format = $node->get('body')->getValue()[0]['format'];

      // TODO Should we leverage the Text Editor API ?
      // https://www.drupal.org/docs/8/api/text-editor-api/overview
      // TODO Any santization or security checks on $body?
      $node->get('body')->setValue([
        'value' => $body,
        'format' => $current_format,
      ]);
      $node->save();

      // Build the response object.
      $response = new Response();
      $response->headers->set('Content-Type', 'application/json');
      $response->setStatusCode(200);
      $response->setContent(json_encode([
        'status' => 200,
        'message' => 'Save successful',
        'data' => $node,
      ]));
      return $response;
    }

    $response = new Response();
    $response->headers->set('Content-Type', 'application/json');
    $response->setStatusCode(403);
    $response->setContent(json_encode([
      'status' => 403,
      'message' => 'Unauthorized',
      'data' => NULL,
    ]));
    return $response;
  }

  /**
   * Permission + File access check.
   *
   * @param mixed $op
   *   The operation?
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Whether the file access is allowed or forbidden.
   *
   * @todo: param does not appear to be used.  Remove?
   */
  public function haxFileAccess($op) {
    // Ensure there are entity permissions to create a file via HAX.
    // See https://www.drupal.org/project/hax/issues/2962055#comment-12617576
    if (\Drupal::currentUser()->hasPermission('use hax') &&
      \Drupal::currentUser()->hasPermission('upload files via hax')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Save a file to the file system.
   *
   * @param mixed $token
   *   Is this a token object?
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The http response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @todo: Update data type for token.
   */
  public function haxFileSave($token) {
    $status = 403;
    $return = '';

    // Check for the uploaded file from our 1-page-uploader app
    // and ensure there are entity permissions to create a file via HAX.
    // See https://www.drupal.org/project/hax/issues/2962055#comment-12617576.
    if (\Drupal::csrfToken()->validate($token) &&
      \Drupal::currentUser()->hasPermission('upload files via hax') && isset($_FILES['file-upload'])) {
      $upload = $_FILES['file-upload'];
      // Check for a file upload.
      if (isset($upload['tmp_name']) && is_uploaded_file($upload['tmp_name'])) {
        // Get contents of the file if it was uploaded into a variable.
        $data = file_get_contents($upload['tmp_name']);
        $params = filter_var_array($_GET, FILTER_SANITIZE_STRING);
        // See if we had a file_wrapper defined, otherwise this is public.
        if (isset($params['file_wrapper'])) {
          $file_wrapper = $params['file_wrapper'];
        }
        else {
          $file_wrapper = 'public';
        }
        // See if Drupal can load from this data source.
        if ($file = file_save_data($data, $file_wrapper . '://' . $upload['name'])) {
          $file->save();
          $file->url = file_create_url($file->getFileUri());
          $return = ['file' => $file];
          $status = 200;
        }
      }
    }

    // Build the response object.
    $response = new Response();
    $response->headers->set('Content-Type', 'application/json');
    $response->setStatusCode($status);
    $response->setContent(json_encode([
      'status' => $status,
      'data' => $return,
    ]));
    return $response;
  }

  /**
   * Load app store.
   *
   * @param mixed $token
   *   The app store token.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The http response.
   */
  public function haxLoadAppStore($token) {
    // Ensure we had data PUT here and it is valid.
    if (\Drupal::csrfToken()->validate($token)) {

      // Hooks and alters.
      $appStore = \Drupal::moduleHandler()->invokeAll('hax_app_store');
      \Drupal::moduleHandler()->alter('hax_app_store', $appStore);
      $staxList = \Drupal::moduleHandler()->invokeAll('hax_stax');
      \Drupal::moduleHandler()->alter('hax_stax', $staxList);

      // Send the Response object with Apps and StaxList.
      $response = new Response();
      $response->headers->set('Content-Type', 'application/json');
      $response->setStatusCode(200);
      $response->setContent(json_encode([
        'status' => 200,
        'apps' => $appStore,
        'stax' => $staxList,
      ]));

      return $response;
    }

    // "Unauthorized" response.
    $response = new Response();
    $response->setStatusCode(403);

    return $response;
  }

}
