<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private RouterInterface $router
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        // 1. Si l’utilisateur venait d’une page protégée → on le renvoie où il voulait aller
        if ($targetPath = $this->getTargetPath($request->getSession(), 'main')) {
            return new RedirectResponse($targetPath);
        }

        $roles = $token->getUser()->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_admin_dashboard'));
        }

        if (in_array('ROLE_CLIENT', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_client_dashboard'));
        }

        if (in_array('ROLE_SERVEUR', $roles, true) || in_array('ROLE_SERVER', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_serveur_dashboard'));
        }

        // Page par défaut pour tous les autres (ex: un utilisateur qui vient de s’inscrire)
        return new RedirectResponse($this->router->generate('app_admin_dashboard'));
    }
}
