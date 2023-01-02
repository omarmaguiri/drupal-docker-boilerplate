<?php

namespace Drupal\tawjeeh_content\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\Entity\File;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ScholarshipController extends ControllerBase {


  public function __construct(protected FileUrlGeneratorInterface $fileUrlGenerator)
  {
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('file_url_generator'),
    );
  }

  public function get(NodeInterface $scholarship): CacheableJsonResponse
  {
    if ('scholarship' !== $scholarship->getType()) {
      throw new NotFoundHttpException();
    }

    $cacheMetadata['#cache'] = [
      'tags' => [
        'node:' . $scholarship->id(),
        'taxonomy_term_list:cities',
      ]
    ];
    return (new CacheableJsonResponse($this->scholarshipNormalize($scholarship)))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata));
  }
  public function all(): CacheableJsonResponse
  {
    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'scholarship',
      ]);

    $scholarships = [];
    foreach ($nodes as $node) {
      $scholarships[] = $this->scholarshipNormalize($node);
    }
    $cacheMetadata['#cache'] = [
      'tags' => [
        'node_list:scholarship',
        'taxonomy_term_list:cities',
      ]
    ];
    return (new CacheableJsonResponse($scholarships))->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadata));
  }

  private function scholarshipNormalize(EntityInterface $node): array
  {
    return [
      'id' => $node->id(),
      'name' => $node->label(),
      'country' => [
        'code' => $node->get('field_scholarship_country')->first()->getString(),
        'flag' => sprintf('https://countryflagsapi.com/png/%s', $node->get('field_scholarship_country')->first()->getString()),
        'name' => \Drupal::service('country_manager')->getList()[$node->get('field_scholarship_country')->first()->getString()]->render(),
      ],
      'description' => $node->get('field_scholarship_description')->value,
      'eligibility' => $node->get('field_scholarship_eligibility')->value,
      'candidacyRequirements' => $node->get('field_scholarship_candidacy_req')->value,
      'candidacyProcedure' => $node->get('field_scholarship_candidacy_proc')->value,
      'selectionProcedure' => $node->get('field_scholarship_selection_proc')->value,
      'deadline' => $node->get('field_scholarship_candidacy_date')->value,
      'additionalInformation' => $node->get('field_scholarship_additional_inf')->value,
      'documents' => array_map(function (array $value, File $file) {
        return ['name' => $value['description'], 'file' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri())];
      }, $node->get('field_scholarship_attached_docs')->getValue(), $node->get('field_scholarship_attached_docs')->referencedEntities()),
    ];
  }
}
