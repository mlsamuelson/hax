<?php /**
 * @file
 * Contains \Drupal\hax\Controller\DefaultController.
 */

namespace Drupal\hax\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
//use Symfony\Component\HttpFoundation\Request;
use Drupal\editor\Entity\Editor;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Utility\Xss;


/**
 * Default controller for the hax module.
 */
class DefaultController extends ControllerBase {

  /**
   * Permission + Node access check.
   */
  public function _hax_node_access($op, \Drupal\node\NodeInterface $node) {


    if (\Drupal::currentUser()->hasPermission('use hax') && node_node_access($node, $op, \Drupal::currentUser())) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  // TODO Convert this to a REST resource ala ??? Nahh, we want to use PUT.
  //  https://www.drupal.org/docs/8/api/restful-web-services-api/custom-rest-resources

  public function _hax_node_save(\Drupal\node\NodeInterface $node, $token) {

    if ($_SERVER['REQUEST_METHOD'] == 'PUT' && \Drupal::csrfToken()->validate($token)) {

      // We're not using the Drupal entity REST system outright here, as PUT
      // isn't supported. But we can, ahem, "patch" the behavior in ourselves.

      // Submitted value is right here. BOOM!
      $body = file_get_contents('php://input');

      $current_format = $node->get('body')->getValue()[0]['format'];

      // TODO explore creating our own HAX input format with pre-configed tags.

      //error_log(var_export($body, 1));
      //error_log(var_export(\Drupal::request()->request, 1));
      //error_log(var_export(Xss::filter($body, ['p']), 1));
      //error_log(var_export(EditorXssFilter::filterXss($body, $current_format), 1));

      // TODO use Xss::filter() with allowed tags from current input format???
      //use Drupal\editor\EditorXssFilter;
      //use Drupal\filter\FilterFormatInterface;
      // If uxing Xss::filter... it needs a whitelist of tags...
      //$node->get('body')->setValue(['value' => Xss::filter($body, ['p']), 'format' => $current_format]);


      // TODO Probably want some XSS or sanity checks here... See what node forms do.
      $node->get('body')->setValue(['value' => $body, 'format' => $current_format]);
      $node->save();

      // Build the response object.
      $response = new Response;
      $response->headers->set('Content-Type', 'application/json');
      $response->setStatusCode(200);
      $response->setContent(json_encode([
        'status' => 200,
        'message' => 'Save successful',
        'data' => $node,
      ]));
      return $response;
    }

    $response = new Response;
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
   */
  public function _hax_file_access($op) {
    // FIXME entity_access bit in next line needs to be updated for D8
    if (\Drupal::currentUser()->hasPermission('use hax') && entity_access('create', 'file', $_FILES['file-upload']['type'])) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Save a file to the file system.
   */
  public function _hax_file_save($token) {
    $status = 403;
    $return = '';

    // check for the uploaded file from our 1-page-uploader app
    // and ensure there are entity permissions to create a file of this type
    if (\Drupal::csrfToken()->validate($token) && isset($_FILES['file-upload']) && entity_access('create', 'file', $_FILES['file-upload']['type'])) {
      $upload = $_FILES['file-upload'];
      // check for a file upload
      if (isset($upload['tmp_name']) && is_uploaded_file($upload['tmp_name'])) {
        // get contents of the file if it was uploaded into a variable
        $data = file_get_contents($upload['tmp_name']);
        $params = filter_var_array($_GET, FILTER_SANITIZE_STRING);
        // see if we had a file_wrapper defined, otherwise this is public
        if (isset($params['file_wrapper'])) {
          $file_wrapper = $params['file_wrapper'];
        }
        else {
          $file_wrapper = 'public';
        }
        // see if Drupal can load from this data source
        if ($file = file_save_data($data, $file_wrapper . '://' . $upload['name'])) {
          file_save($file);
          $file->url = file_create_url($file->uri);
          $return = ['file' => $file];
          $status = 200;
        }
      }
    }

    // Build the response object.
    $response = new Response;
    $response->headers->set('Content-Type', 'application/json');
    $response->setStatusCode($status);
    $response->setContent(json_encode([
      'status' => $status,
      'data' => $return,
    ]));
    return $response;
  }

  public function _hax_load_app_store($token) {
    // Ensure we had data PUT here and it is valid
    if (\Drupal::csrfToken()->validate($token)) {

      // Hooks and alters.
      $appStore = \Drupal::moduleHandler()->invokeAll('hax_app_store');
      \Drupal::moduleHandler()->alter('hax_app_store', $appStore);
      $staxList = \Drupal::moduleHandler()->invokeAll('hax_stax');
      \Drupal::moduleHandler()->alter('hax_stax', $staxList);

      // Send the Response object with Apps and StaxList.
      $response = new Response;
      $response->headers->set('Content-Type', 'application/json');
      $response->setStatusCode(200);
      $response->setContent(json_encode([
        'status' => 200,
        'apps' => $appStore,
        'stax' => $staxList
      ]));
      return $response;

    }

    // "Unauthorized" response.
    $response = new Response;
    $response->setStatusCode(403);
    return $response;
  }


  /**
   * Present the node form but wrap the content in hax-body tag
   * @param  [type] $node [description]
   * @return [type]       [description]
   */
  /* Was rendering without the page - we needed render WITH the Response object.
   * See HaxModeController.php and the routing.yml for this, now.
  public function _hax_node_form(\Drupal\node\NodeInterface $node) {
    // set page title
    // @FIXME
    // drupal_set_title() has been removed. There are now a few ways to set the title
    // dynamically, depending on the situation.
    //
    //
    // @see https://www.drupal.org/node/2067859
    // drupal_set_title(t('HAX edit @title', array('@title' => $node->getTitle())), PASS_THROUGH);

    // fake a component to get it into the head of the document, heavy weighting
    $component = new \stdClass();
    $component->machine_name = 'cms-hax';
    // pull in from webcomponents location
    // @FIXME
    // Remove. Moved this to hax_page_attachments() ... is that working and appropriate?
    ////$component->file = libraries_get_path('webcomponents') . '/polymer/bower_components/cms-hax/cms-hax.html';
    //// Not relying on libraries
    //$component->file = 'libraries/webcomponents/polymer/bower_components/cms-hax/cms-hax.html';
    ////_webcomponents_add_to_head($component, 10000);
    //$element = [
    //  '#tag' => 'link', // The #tag is the html tag
    //  '#attributes' => [ // Set up an array of attributes inside the tag
    //    'href' => base_path() . $component->file,
    //    'rel' => 'import',
    //  ],
    //];
    ////drupal_add_html_head($element, 'webcomponent-' . $component->machine_name);
    //$build['#attached']['html_head'][] = [$element, 'webcomponent-' . $component->machine_name];

    // generate autoload list
    $elementstring = \Drupal::config('hax.settings')->get('hax_autoload_element_list');
    // blow up based on space
    $elements = explode(' ', $elementstring);
    $components = '';
    foreach ($elements as $element) {
      // sanity check
      if (!empty($element)) {
        $components .= '<' . $element . ' slot="autoloader">' . '</' . $element . '>';
      }
    }
    $appStoreConnection = array(
      'url' => base_path() . 'hax-app-store/' . \Drupal::csrfToken()->get(),
    );
    // write content to screen, wrapped in tag to do all the work
    $content = '
    <cms-hax open-default end-point="' . base_path() . 'hax-node-save/' . $node->id() . '/' . \Drupal::csrfToken()->get() . '" body-offset-left="' . \Drupal::config('hax.settings')->get('hax_offset_left') . '" app-store-connection=' . "'" . json_encode($appStoreConnection) . "'" . '>'
    . $components .
      check_markup($node->body[0]->value, $node->body[0]->format)
    .'</cms-hax>';

    // TODO how to get the rest of the page?
    // TODO Confirm is fit for purpose (was req'd by controller): return Response object instead of string
    $response = new \Symfony\Component\HttpFoundation\Response();
    $response->setContent($content);
    $response->setMaxAge(1);
    return $response;

    //return $content;
  }
*/


}
