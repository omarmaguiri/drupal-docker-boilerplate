<?php

namespace Drupal\tawjeeh_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\Entity\File;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LetterController extends ControllerBase {


  public function __construct(protected FileUrlGeneratorInterface $fileUrlGenerator)
  {
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('file_url_generator'),
    );
  }

  public function get(NodeInterface $letter): JsonResponse
  {
    if ('letter' !== $letter->getType()) {
      throw new NotFoundHttpException();
    }
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    $fields = array_map(fn ($field) => $field['target_id'], $letter->get('field_letter_bac_fields')->getValue());
    $packs = array_map(fn ($field) => $field['target_id'], $letter->get('field_letter_packs')->getValue());
    if (
      !in_array($user->get('field_student_bac_field')->target_id, $fields)
      && !in_array($user->get('field_student_package')->target_id, $packs)
    ) {
      throw new NotFoundHttpException();
    }

    return new JsonResponse($this->letterNormalize($letter));
  }
  public function all(): JsonResponse
  {
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'letter',
      ]);

    $letters = [];
    foreach ($nodes as $node) {
      $fields = array_map(fn ($field) => $field['target_id'], $node->get('field_letter_bac_fields')->getValue());
      $packs = array_map(fn ($field) => $field['target_id'], $node->get('field_letter_packs')->getValue());
      if (
        !in_array($user->get('field_student_bac_field')->target_id, $fields)
        && !in_array($user->get('field_student_package')->target_id, $packs)
      ) {
        continue;
      }

      $letters[] = $this->letterNormalize($node);
    }

    return new JsonResponse($letters);
  }

  private function letterNormalize(EntityInterface $node): array
  {
    return [
      'id' => $node->id(),
      'title' => $node->label(),
      'picture' => $this->fileUrlGenerator->generateAbsoluteString($node->get('field_letter_picture')->entity->getFileUri()),
      'school' => [
        'type' => $node->get('field_letter_school_type')->value,
        'description' => $node->get('field_letter_school_description')->value,
        'cities' => $node->get('field_letter_school_cities')->value,
        'fields' => $node->get('field_letter_available_fields')->value,
        'conditions' => $node->get('field_letter_access_conditions')->value,
        'candidacy' => $node->get('field_letter_candidacy_req')->value,
      ],
      'registration' => [
        'link' => [
          'title' => $node->get('field_letter_register_link')->first()->getValue()['title'],
          'uri' => $node->get('field_letter_register_link')->first()->getValue()['uri'],
        ],
        'deadline' => $node->get('field_letter_register_deadline')->value,
        'method' => $node->get('field_letter_register_method')->value,
      ],
      'dates' => $node->get('field_letter_important_dates')->value,
      'promotions' => $node->get('field_letter_tawjeeh_promotion')->entity?->get('field_travel_promo_description')->value,
      'additionalInformation' => $node->get('field_letter_additional_inf')->value,
      'documents' => array_map(function (array $value, File $file) {
        return ['name' => $value['description'], 'file' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri())];
      }, $node->get('field_letter_attached_docs')->getValue(), $node->get('field_letter_attached_docs')->referencedEntities()),
    ];
  }
}
