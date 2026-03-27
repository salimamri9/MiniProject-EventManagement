<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\PasskeyAuthService;
use App\Service\JWTTokenService;
use App\Repository\UserRepository;
use App\Repository\AdminRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class AuthController extends AbstractController
{
    public function __construct(
        private PasskeyAuthService $passkeyAuthService,
        private JWTTokenService $jwtTokenService,
        private UserRepository $userRepository,
        private AdminRepository $adminRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    // ==================== PAGE ROUTES ====================

    #[Route('/register', name: 'auth_register', methods: ['GET'])]
    public function registerPage(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('event_list');
        }
        return $this->render('auth/register.html.twig');
    }

    #[Route('/login', name: 'auth_login', methods: ['GET'])]
    public function loginPage(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('event_list');
        }
        return $this->render('auth/login.html.twig');
    }

    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function adminLoginPage(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/admin_login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    // ==================== PASSWORD AUTH API ====================

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function registerWithPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;
            $username = $data['username'] ?? null;
            $password = $data['password'] ?? null;

            if (!$email || !$password) {
                return $this->json(['error' => 'Email and password are required'], 400);
            }

            if (strlen($password) < 6) {
                return $this->json(['error' => 'Password must be at least 6 characters'], 400);
            }

            // Check if user exists
            if ($this->userRepository->findByEmail($email)) {
                return $this->json(['error' => 'An account with this email already exists'], 409);
            }

            // Create user
            $user = new User();
            $user->setUsername($username ?: explode('@', $email)[0]);
            $user->setEmail($email);
            
            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            
            $this->userRepository->save($user, true);

            // Log in the user
            $this->loginUser($request, $user);

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail()
                ],
                'redirect' => '/'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function loginWithPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            if (!$email || !$password) {
                return $this->json(['error' => 'Email and password are required'], 400);
            }

            $user = $this->userRepository->findByEmail($email);
            if (!$user) {
                return $this->json(['error' => 'No account found with this email'], 404);
            }

            if (!$this->passwordHasher->isPasswordValid($user, $password)) {
                return $this->json(['error' => 'Invalid password'], 401);
            }

            // Log in the user
            $this->loginUser($request, $user);

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail()
                ],
                'redirect' => '/'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Login failed: ' . $e->getMessage()], 500);
        }
    }

    // ==================== PASSKEY AUTH API ====================

    #[Route('/api/auth/passkey/register/options', name: 'api_auth_passkey_register_options', methods: ['POST'])]
    public function passkeyRegisterOptions(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;
            $username = $data['username'] ?? null;

            if (!$email) {
                return $this->json(['error' => 'Email required'], 400);
            }

            $existingUser = $this->userRepository->findByEmail($email);
            if ($existingUser) {
                return $this->json(['error' => 'User already exists'], 409);
            }

            $user = new User();
            $user->setUsername($username ?: explode('@', $email)[0]);
            $user->setEmail($email);
            $this->userRepository->save($user, true);

            $options = $this->passkeyAuthService->getRegistrationOptions($user);

            return $this->json([
                'options' => $options,
                'user_id' => $user->getId()
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/auth/passkey/register/verify', name: 'api_auth_passkey_register_verify', methods: ['POST'])]
    public function passkeyRegisterVerify(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $userId = $data['user_id'] ?? null;
            $credential = $data['credential'] ?? null;

            if (!$userId || !$credential) {
                return $this->json(['error' => 'User ID and credential required'], 400);
            }

            $user = $this->userRepository->find($userId);
            if (!$user) {
                return $this->json(['error' => 'User not found'], 404);
            }

            $this->passkeyAuthService->verifyRegistration($credential, $user);
            $this->loginUser($request, $user);

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail()
                ],
                'redirect' => '/'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/auth/passkey/login/options', name: 'api_auth_passkey_login_options', methods: ['POST'])]
    public function passkeyLoginOptions(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;

            if (!$email) {
                return $this->json(['error' => 'Email required'], 400);
            }

            $user = $this->userRepository->findByEmail($email);
            if (!$user) {
                return $this->json(['error' => 'No account found'], 404);
            }

            $options = $this->passkeyAuthService->getLoginOptions($email);
            return $this->json($options);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/auth/passkey/login/verify', name: 'api_auth_passkey_login_verify', methods: ['POST'])]
    public function passkeyLoginVerify(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;
            $credential = $data['credential'] ?? null;

            if (!$email || !$credential) {
                return $this->json(['error' => 'Email and credential required'], 400);
            }

            $user = $this->passkeyAuthService->verifyLogin($credential, $email);
            $this->loginUser($request, $user);

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail()
                ],
                'redirect' => '/'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    // ==================== ADMIN API ====================

    #[Route('/api/auth/admin/login', name: 'api_admin_login', methods: ['POST'])]
    public function apiAdminLogin(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            return $this->json(['error' => 'Username and password required'], 400);
        }

        $admin = $this->adminRepository->findByUsername($username);
        if (!$admin || !$this->passwordHasher->isPasswordValid($admin, $password)) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        $token = $this->jwtTokenService->createToken($admin);

        return $this->json([
            'token' => $token,
            'admin' => [
                'id' => $admin->getId(),
                'username' => $admin->getUsername()
            ]
        ]);
    }

    // ==================== HELPERS ====================

    private function loginUser(Request $request, User $user): void
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->container->get('security.token_storage')->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->json(['message' => 'Logged out']);
    }
}
