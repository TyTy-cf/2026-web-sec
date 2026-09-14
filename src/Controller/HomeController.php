<?php

namespace App\Controller;

use App\Repository\TopicRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{

    #[Route('/', name: 'app_home')]
    public function index(TopicRepository $topicRepository): Response
    {
        return $this->render('front/home/index.html.twig', [
            'topics' => $topicRepository->findBy([], ['createdAt' => 'DESC'], 20),
        ]);
    }

}
