<?php

namespace App\Controller\Admin;

use App\Repository\CommentRepository;
use App\Repository\TopicRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(TopicRepository $topicRepository, CommentRepository $commentRepository): Response
    {
        return $this->render('back/dashboard/index.html.twig', [
            'latest_topics' => $topicRepository->LastByCreatedAt(5),
            'latest_comments' => $commentRepository->LastByCreatedAt(5),
        ]);
    }
}
