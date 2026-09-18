<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/inscription', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()))
                ->setRoles([])
                ->setCreatedAt(new \DateTime())
                ->setActivationCode(uniqid());

            $entityManager->persist($user);
            $entityManager->flush();

            $activationUrl = $this->generateUrl(
                'app_register_validate',
                ['activationCode' => $user->getActivationCode()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $email = (new Email())
                ->from('no-reply@reddit-ish.local')
                ->to($user->getEmail())
                ->subject('Confirmation de votre inscription')
                ->text("Merci de votre inscription, finalisez celle-ci en cliquant sur ce lien : {$activationUrl}");

            $mailer->send($email);

            $this->addFlash('success', 'flash.account_created');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route(path: '/valider-inscription/{activationCode}', name: 'app_register_validate')]
    public function validateRegistration(
        string $activationCode,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
    ): Response {
        $user = $userRepository->findOneBy(['activationCode' => $activationCode]);

        if (null === $user) {
            throw $this->createNotFoundException();
        }

        $user->setActivationCode(null);
        $entityManager->flush();

        $this->addFlash('success', 'flash.account_activated');

        return $this->redirectToRoute('app_login');
    }
}
