<?php

declare(strict_types=1);

namespace ApiKit\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Base controller for API controllers.
 *
 * Extends Symfony's AbstractController and includes ApiControllerTrait,
 * providing standardized response methods out of the box.
 *
 * Use this when your controller does not already extend another class.
 * If you need multiple inheritance (e.g. you already extend AbstractController
 * or another base), use ApiControllerTrait directly instead.
 *
 * @example
 *   final class UserController extends AbstractApiController
 *   {
 *       #[Route('/api/users', methods: ['GET'])]
 *       public function list(): JsonResponse
 *       {
 *           return $this->respondSuccess($this->userService->findAll());
 *       }
 *   }
 */
abstract class AbstractApiController extends AbstractController
{
    use ApiControllerTrait;
}
