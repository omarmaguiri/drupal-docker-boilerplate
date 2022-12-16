<?php

namespace Drupal\tawjeeh_api_authentication\Authentication;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\user\UserAuthInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiBasicAuthenticationProvider implements AuthenticationProviderInterface
{
  const LOGIN_ROUTE_NANE = 'tawjeeh_api_authentication.login';

  public function __construct(
    protected RouteProviderInterface $routeProvider,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected UserAuthInterface $userAuth,
  ) { }

  public function applies(Request $request)
  {
    return
      str_starts_with($request->getRequestUri(), $this->routeProvider->getRouteByName(self::LOGIN_ROUTE_NANE)->getPath())
      && $request->headers->has('Authorization')
      && preg_match('#^Basic (.+)$#', $request->headers->get('Authorization'));
  }

  public function authenticate(Request $request)
  {
    if (!preg_match('#^Basic (?<credentials>.+)$#', $request->headers->get('Authorization'), $matches)) {
      return NULL;
    }

    [$username, $password] = explode(':', base64_decode($matches['credentials']));
    if (!$username || !$password) {
      return NULL;
    }
    $uid = $this->userAuth->authenticate($username, $password);
    if (!$uid) {
      return NULL;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user->isBlocked()) {
      return NULL;
    }
    return $user;
  }
}
