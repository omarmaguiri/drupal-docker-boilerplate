<?php

namespace Drupal\tawjeeh_content\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PreparationCenterController extends ControllerBase {

  public function __construct(protected FileUrlGeneratorInterface $fileUrlGenerator)
  {
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('file_url_generator'),
    );
  }

  public function get(NodeInterface $center): CacheableJsonResponse
  {
    if ('preparation_center' !== $center->getType()) {
      throw new NotFoundHttpException();
    }

    $cacheMetadata['#cache'] = [
      'tags' => [
        'node:' . $center->id(),
        'taxonomy_term_list:cities',
      ]
    ];
    return (new CacheableJsonResponse($this->centerNormalize($center)))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata));
  }
  public function all(Request $request): CacheableJsonResponse
  {
    $query = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->condition('status', 1)
      ->condition('type', 'preparation_center')
      ->sort('created', 'DESC');

    if ($city = $request->query->get('city')) {
      $query->condition('field_prep_center_city', $city);
    }

    $nids = $query->execute();
    $nodes = Node::loadMultiple($nids);
    $centers = [];
    foreach ($nodes as $node) {
      $centers[] = $this->centerNormalize($node);
    }

    $cacheMetadata['#cache'] = [
      'tags' => [
        'node_list:preparation_center',
        'taxonomy_term_list:cities',
      ]
    ];
    return (new CacheableJsonResponse($centers))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata)->addCacheContexts(['url.query_args:city']));
  }
  public function cities(): CacheableJsonResponse
  {
    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'preparation_center',
      ]);

    $cities = [];
    foreach ($nodes as $node) {
      if ($cities[$node->get('field_prep_center_city')->entity->id()] ?? FALSE) {
        continue;
      }
      $cities[$node->get('field_prep_center_city')->entity->id()] = [
        'id' => $node->get('field_prep_center_city')->entity->id(),
        'name' => $node->get('field_prep_center_city')->entity->label(),
      ];
    }

    $cacheMetadata['#cache'] = [
      'tags' => [
        'node_list:preparation_center',
        'taxonomy_term_list:cities',
      ]
    ];
    return (new CacheableJsonResponse(array_values($cities)))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata));
  }

  private function centerNormalize(EntityInterface $node): array
  {
    return [
      'lang' => $node->get('langcode')->value,
      'id' => $node->id(),
      'name' => $node->label(),
      'description' => $node->get('field_prep_center_description')->value,
      'competition' => $node->get('field_prep_center_competition')->value,
      'picture' => $this->fileUrlGenerator->generateAbsoluteString(File::load($node->get('field_prep_center_picture')->target_id)->getFileUri()),
      'email' => $node->get('field_prep_center_email')->value,
      'phone' => $node->get('field_prep_center_phone')->value,
      'whatsapp' => $node->get('field_prep_center_whatsapp')->value,
      'city' => $node->get('field_prep_center_city')->entity->label(),
      'location' => $node->get('field_prep_center_location')->first()?->getValue(),
    ];
  }
}
