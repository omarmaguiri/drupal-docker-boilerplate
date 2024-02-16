<?php

namespace Drupal\tawjeeh_api_authentication\Authentication;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\Request;

class ApiJwtAuthenticationProvider implements AuthenticationProviderInterface
{
  const API_ROUTES_PREFIX = '/api';
  const LOGIN_ROUTE_NANE = 'tawjeeh_api_authentication.login';

  public function __construct(
    protected array $jwt,
    protected RouteProviderInterface $routeProvider,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) { }

  public function applies(Request $request)
  {
    return
      str_starts_with($request->getRequestUri(), self::API_ROUTES_PREFIX)
      && $this->routeProvider->getRouteByName(self::LOGIN_ROUTE_NANE)->getPath() !== $request->getRequestUri()
      && $request->headers->has('Authorization')
      && preg_match('#^Bearer (.+)$#', $request->headers->get('Authorization'));
  }

  public function authenticate(Request $request)
  {
    if (!preg_match('#^Bearer (?<token>.+)$#', $request->headers->get('Authorization'), $matches)) {
      return NULL;
    }
    $key = file_get_contents($this->jwt['public.key']);
    try {
      $payload = JWT::decode($matches['token'], new Key($key, $this->jwt['algo']));
      if ($users = $this->entityTypeManager->getStorage('user')->loadByProperties([ 'uid' => 1 ])) {
        $user = reset($users);
        if ($user->isBlocked()) {
          return NULL;
        }
        return $user;
      }
    } catch (\LogicException $e) {
      // errors having to do with environmental setup or malformed JWT Keys
    } catch (\UnexpectedValueException $e) {
      // errors having to do with JWT signature and claims
    }

    return NULL;
  }
}
