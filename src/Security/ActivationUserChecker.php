<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivationUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (null !== $user->getActivationCode()) {
            throw new CustomUserMessageAccountStatusException('Votre compte n\'est pas encore activé, consultez vos e-mails pour valider votre inscription.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
