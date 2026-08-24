<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * IMPORTANT: "user management" / admin-panel routes. NOTE: the
 * {@see IsGranted} attribute enforces ROLE_USER; the
 * {@see App\EventSubscriber\InactiveUserSubscriber} takes care of
 * "blocked / deleted" cases.
 */
#[IsGranted('ROLE_USER')]
final class UserController extends AbstractController
{
    /**
     * IMPORTANT: the table page. Renders the sorted user list plus
     * the toolbar of actions. NOTE: sort is taken from the query
     * string (?sort=lastLoginAt&dir=desc) and validated against a
     * whitelist — no raw user input reaches the ORDER BY clause.
     */
    #[Route('/users', name: 'app_users', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $sort = (string) $request->query->get('sort', 'lastLoginAt');
        $dir = (string) $request->query->get('dir', 'DESC');

        if (!array_key_exists($sort, UserRepository::SORTABLE_COLUMNS)) {
            $sort = 'lastLoginAt';
        }
        if (strtoupper($dir) !== 'ASC') {
            $dir = 'DESC';
        }

        $users = $userRepository->findAllSorted($sort, $dir);

        // IMPORTANT: pre-compute the "opposite" direction so the template can
        // render clickable column headers that flip the sort.
        $sortContext = [
            'current' => $sort,
            'dir' => $dir,
            'oppositeDir' => $dir === 'ASC' ? 'DESC' : 'ASC',
        ];

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'sortContext' => $sortContext,
        ]);
    }

    /**
     * IMPORTANT: toolbar action — block N users.
     * POST-only, CSRF-protected, accepts an array of user IDs.
     * NOTE: a user may block themselves — that is explicitly allowed
     * by the task ("All users should be able to block or delete
     * themselves or any other user").
     */
    #[Route('/users/block', name: 'app_users_block', methods: ['POST'])]
    public function block(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->validateCsrf($request);
        $ids = $this->extractSelectedIds($request);
        if ($ids === []) {
            $this->addFlash('warning', 'No users selected.');

            return $this->redirectToRoute('app_users');
        }

        $users = $userRepository->findBy(['id' => $ids]);
        $count = 0;
        foreach ($users as $user) {
            // IMPORTANT: stashing the pre-block status lets "Unblock"
            // restore exactly the status the user had before — so an
            // unverified user who is blocked and then unblocked stays
            // unverified (they still need to confirm the e-mail).
            if ($user->getStatus() !== UserStatus::Blocked) {
                $user->setPreviousStatus($user->getStatus());
                $user->setStatus(UserStatus::Blocked);
                ++$count;
            }
        }
        $em->flush();

        $this->addFlash('success', sprintf('Blocked %d user(s).', $count));

        return $this->redirectToRoute('app_users');
    }

    /**
     * IMPORTANT: toolbar action — unblock N users. Restores each user's
     * pre-block status instead of unconditionally setting Active.
     */
    #[Route('/users/unblock', name: 'app_users_unblock', methods: ['POST'])]
    public function unblock(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->validateCsrf($request);
        $ids = $this->extractSelectedIds($request);
        if ($ids === []) {
            $this->addFlash('warning', 'No users selected.');

            return $this->redirectToRoute('app_users');
        }

        $users = $userRepository->findBy(['id' => $ids]);
        $count = 0;
        foreach ($users as $user) {
            if ($user->getStatus() !== UserStatus::Blocked) {
                // NOTE: not blocked — nothing to do.
                continue;
            }

            // IMPORTANT: restore the status that was active before the block.
            // NOTE: if previousStatus is null (legacy data or a blocked
            // user from before this column existed), fall back to Active —
            // never Blocked, since that would defeat the unblock.
            $restore = $user->getPreviousStatus() ?? UserStatus::Active;
            if ($restore === UserStatus::Blocked) {
                $restore = UserStatus::Active;
            }

            $user->setStatus($restore);
            $user->setPreviousStatus(null);
            ++$count;
        }
        $em->flush();

        $this->addFlash('success', sprintf('Unblocked %d user(s).', $count));

        return $this->redirectToRoute('app_users');
    }

    /**
     * IMPORTANT: toolbar action — hard delete (DB row removed, not soft).
     */
    #[Route('/users/delete', name: 'app_users_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->validateCsrf($request);
        $ids = $this->extractSelectedIds($request);
        if ($ids === []) {
            $this->addFlash('warning', 'No users selected.');

            return $this->redirectToRoute('app_users');
        }

        $users = $userRepository->findBy(['id' => $ids]);
        $count = count($users);

        // IMPORTANT: Doctrine nulls out an entity's identifier after a
        // successful DELETE in flush(). Detect "deleting self" BEFORE the
        // flush so we still know the user's id, then wipe the security
        // state BEFORE flush() so kernel.response can't serialise a token
        // pointing at a User whose id is already null. invalidate() alone
        // is not enough — it regenerates the session id but preserves the
        // existing data, so we also remove the "_security_main" key.
        $current = $this->getUser();
        $currentId = $current instanceof User ? $current->getId() : null;
        $deletingSelf = $currentId !== null && in_array($currentId, $ids, true);

        if ($deletingSelf) {
            $tokenStorage->setToken(null);
            $request->getSession()->remove('_security_main');
        }

        foreach ($users as $user) {
            $em->remove($user);
        }
        $em->flush();

        $this->addFlash('success', sprintf('Deleted %d user(s).', $count));

        if ($deletingSelf) {
            $request->getSession()->invalidate();

            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('app_users');
    }

    /**
     * IMPORTANT: toolbar action — deletes EVERY user with status
     * Unverified, regardless of selection. NOTE: the task wording is
     * "Delete unverified" — a bulk action with no row selection needed.
     * Same self-deletion safety pattern as {@see delete()}: if the current
     * user is itself unverified, we wipe the security state BEFORE flush()
     * (Doctrine nulls entity identifiers on DELETE), invalidate the session,
     * and bounce the user to /login.
     */
    #[Route('/users/delete-unverified', name: 'app_users_delete_unverified', methods: ['POST'])]
    public function deleteUnverified(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->validateCsrf($request);

        $unverified = $userRepository->findUnverified();
        $count = count($unverified);

        // IMPORTANT: capture the current user's id BEFORE flush and clear
        // security state up front, otherwise kernel.response serialises a
        // token pointing at a User whose id has just been nulled by Doctrine
        // and the next request blows up with "does not contain an identifier".
        $current = $this->getUser();
        $currentId = $current instanceof User ? $current->getId() : null;
        $deletingSelf = $currentId !== null && in_array($currentId, array_map(static fn (User $u) => $u->getId(), $unverified), true);

        if ($deletingSelf) {
            $tokenStorage->setToken(null);
            $request->getSession()->remove('_security_main');
        }

        foreach ($unverified as $user) {
            $em->remove($user);
        }
        $em->flush();

        $this->addFlash('success', sprintf('Deleted %d unverified user(s).', $count));

        if ($deletingSelf) {
            $request->getSession()->invalidate();

            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('app_users');
    }

    /**
     * IMPORTANT: extract selected IDs from the request payload. NOTE:
     * accepts both an array parameter `selected[]` and a CSV string
     * `selected` — convenient for both the "select-all" and the
     * explicit-checkboxes cases.
     *
     * @return int[]
     */
    private function extractSelectedIds(Request $request): array
    {
        $raw = $request->request->all('selected');
        if (!is_array($raw)) {
            $raw = explode(',', (string) $raw);
        }
        $ids = [];
        foreach ($raw as $value) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
            if ($value !== false && $value > 0) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * IMPORTANT: validates the CSRF token from the toolbar form.
     * NOTE: one token id (`users-toolbar`) is reused across all toolbar
     * actions because they are mutually exclusive — only one button
     * submits at a time.
     *
     * The {@see AbstractController::isCsrfTokenValid()} helper takes the
     * CSRF token ID as a string and the token value as the second argument
     * — NOT a {@see CsrfToken} object. (The object form is for
     * {@see CsrfTokenManagerInterface::isTokenValid()} when you have
     * already built the object elsewhere.)
     */
    private function validateCsrf(Request $request): void
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('users-toolbar', $token)) {
            $this->addFlash('danger', 'Invalid security token. Please try again.');

            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}