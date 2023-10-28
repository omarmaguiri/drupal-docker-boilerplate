<?php

namespace Drupal\tawjeeh_content\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\file\Entity\File;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SchoolController extends ControllerBase {

  public function __construct(protected FileUrlGeneratorInterface $fileUrlGenerator, protected UrlGeneratorInterface $urlGenerator)
  {
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('file_url_generator'),
      $container->get('url_generator'),
    );
  }

  public function get(NodeInterface $school): CacheableJsonResponse
  {
    if ('school' !== $school->getType()) {
      throw new NotFoundHttpException();
    }

    $cacheMetadata['#cache'] = [
      'tags' => [
        'node:' . $school->id(),
        'taxonomy_term_list:cities',
        'taxonomy_term_list:school_categories',
        'taxonomy_term_list:school_groups',
      ]
    ];
    return (new CacheableJsonResponse($this->schoolNormalize($school)))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata));
  }

  public function all(): CacheableJsonResponse
  {
    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'school',
      ]);
    $schools = [];
    foreach ($nodes as $node) {
      $schools[] = $this->schoolNormalize($node);
    }

    $cacheMetadata['#cache'] = [
      'tags' => [
        'node_list:school',
        'taxonomy_term_list:cities',
        'taxonomy_term_list:school_categories',
        'taxonomy_term_list:school_groups',
      ]
    ];
    return (new CacheableJsonResponse($schools))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata));
  }

  public function getCategories(): CacheableJsonResponse
  {
    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'school',
      ]);
    $categories = [];
    foreach ($nodes as $node) {
      foreach ($node->get('field_school_category')->referencedEntities() as $category) {
        if (!array_key_exists($category->id(), $categories)) {
          $categories[$category->id()] = [
            'id' => $category->id(),
            'name' => $category->label(),
            '@link' => $this->urlGenerator->generateFromRoute('tawjeeh_content.api.schools_categories.get', ['category' => $category->id()], [], TRUE)->getGeneratedUrl(),
            'count' => 1,
          ];
          continue;
        }
        $categories[$category->id()]['count']++;
      }
    }

    $cacheMetadata['#cache'] = [
      'tags' => [
        'node_list:school',
        'taxonomy_term_list:school_categories',
      ]
    ];
    return (new CacheableJsonResponse(array_values($categories)))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata));
  }

  public function getSchoolsByCategory(TermInterface $category): CacheableJsonResponse
  {
    if ('school_categories' !== $category->bundle()) {
      throw new NotFoundHttpException();
    }

    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'school',
        'field_school_category' => $category->id(),
      ]);

    $schools = ['groups' => [], 'schools' => []];
    foreach ($nodes as $node) {
      if (!$node->get('field_school_group')->entity) {
        $schools['schools'][] = $this->schoolNormalize($node, TRUE);
        continue;
      }
      $gid = $node->get('field_school_group')->entity->id();
      if (!array_key_exists($gid, $schools['groups'])) {
        $schools['groups'][$gid] = [
          'id' => $gid,
          'name' => $node->get('field_school_group')->entity->label(),
          '@link' => $this->urlGenerator->generateFromRoute('tawjeeh_content.api.schools_groups.get', ['group' => $gid], [], TRUE)->getGeneratedUrl(),
          'count' => 1,
        ];
        continue;
      }
      $schools['groups'][$gid]['count']++;
    }
    $schools['groups'] = array_values($schools['groups']);

    $cacheMetadata['#cache'] = [
      'tags' => [
        'node_list:school',
        'taxonomy_term_list:school_categories',
        'taxonomy_term_list:school_groups',
      ]
    ];
    return (new CacheableJsonResponse($schools))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata));
  }

  public function getSchoolsByGroup(TermInterface $group): CacheableJsonResponse
  {
    $cacheMetadata['#cache'] = [
      'tags' => [
        'node_list:school',
        'taxonomy_term_list:school_categories',
        'taxonomy_term_list:school_groups',
      ]
    ];
    if ('school_groups' !== $group->bundle()) {
      throw new NotFoundHttpException();
    }

    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'school',
        'field_school_group' => $group->id(),
      ]);

    $schools = [];
    foreach ($nodes as $node) {
      $schools[] = $this->schoolNormalize($node, TRUE);
    }

    $cacheMetadata['#cache'] = [
      'tags' => [
        'node_list:school',
        'taxonomy_term_list:school_categories',
        'taxonomy_term_list:school_groups',
      ]
    ];
    return (new CacheableJsonResponse($schools))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata));
  }

  private function schoolNormalize(EntityInterface $node, bool $compact = FALSE): array
  {
    if ($compact) {
      return [
        'id' => $node->id(),
        'name' => $node->label(),
        '@link' => $this->urlGenerator->generateFromRoute('tawjeeh_content.api.schools.get', ['school' => $node->id()], [], TRUE)->getGeneratedUrl(),
      ];
    }
    $school = [
      'lang' => $node->get('langcode')->value,
      'id' => $node->id(),
      'name' => $node->label(),
      'description' => $node->get('field_school_description')->value,
      'type' => $node->get('field_school_type')->value,
      'picture' => $this->fileUrlGenerator->generateAbsoluteString(File::load($node->get('field_school_picture')->target_id)->getFileUri()),
      'email' => $node->get('field_school_email')->value,
      'phone' => $node->get('field_school_phone')->value,
      'website' => $node->get('field_school_website')->first() ? $node->get('field_school_website')->first()->getUrl()->toString() : null,
      'city' => $node->get('field_school_city')->entity ? $node->get('field_school_city')->entity->label() : null,
      'fields' => $node->get('field_school_fields')->value,
      'formations' => $node->get('field_school_formations')->value,
    ];
    foreach ($node->get('field_school_category')->referencedEntities() as $category) {
      $school['category'][] = [
        'id' => $category->id(),
        'name' => $category->label(),
      ];
    }
    if ($node->get('field_school_group')->entity) {
      $school['group'] = [
        'id' => $node->get('field_school_group')->entity->id(),
        'name' => $node->get('field_school_group')->entity->label(),
      ];
    }
    return $school;
  }
}
