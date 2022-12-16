<?php

namespace Drupal\tawjeeh_api_authentication\Controller;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Controller\ControllerBase;
use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for tawjeeh_api_authentication routes.
 */
class AuthenticationController extends ControllerBase {

  public function __construct(
    protected array $jwt,
    protected UuidInterface $uuid,
  ) { }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->getParameter('jwt'),
      $container->get('uuid'),
    );
  }

  public function login(): JsonResponse {
    $key = file_get_contents($this->jwt['private.key']);
    $payload = $this->jwt['claims'] + [
        'jti' => $this->uuid->generate(),
        'iat' => time(),
        'sub' => $this->currentUser()->getAccountName(),
    ];
    $jwt = JWT::encode($payload, $key, $this->jwt['algo']);
    return new JsonResponse([
      'token' => "Bearer $jwt"
    ]);
  }
}
