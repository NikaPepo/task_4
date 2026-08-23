<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * IMPORTANT: редиректит с корня / на /login.
 *
 * Нужен по двум причинам:
 * 1. Render health-check стучится на / — без этого маршрута получал 404
 * 2. Пользователь, зашедший на главную, попадает сразу на форму входа
 *
 * Если пользователь уже залогинен — редиректим на /users вместо /login
 * (так удобнее — зашёл на главную, попал в личный кабинет).
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        // Если юзер уже залогинен — кидаем в личный кабинет, иначе на форму входа
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_users_index');
        }

        return $this->redirectToRoute('app_login');
    }
}
